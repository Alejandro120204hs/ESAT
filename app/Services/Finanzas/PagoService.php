<?php

namespace App\Services\Finanzas;

use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Devolucion;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de cobro de una matrícula: derecho de matrícula, plan de cuotas (mensual o por
 * periodo, lo elige el estudiante), descuentos, pagos con recibo (abonos) y el resumen
 * que muestran Matrículas, Estudiantes y Acudientes.
 * Valor de cada cuota = precio del programa repartido - descuento + cuota del sistema (configuraciones).
 * Un pago (recaudo) es un monto libre que se aplica en orden: lo más viejo primero.
 */
class PagoService
{
    /** El derecho de matrícula se paga antes de empezar clases: vence el día que inicia el grupo (o hoy, si ya empezó). */
    public function vencimientoDerecho(Curso $grupo): CarbonInterface
    {
        return $grupo->fecha_inicio->gt(today()) ? $grupo->fecha_inicio : today();
    }

    /** Cobro del derecho de matrícula (si el programa lo tiene). Vence cuando empiezan las clases. */
    public function cobrarMatricula(Matricula $matricula, CarbonInterface $vence): ?Pago
    {
        $valor = (float) $matricula->programa->matricula;
        if ($valor <= 0) {
            return null;
        }

        return $matricula->pagos()->create([
            'tipo'              => 'matricula',
            'mes'               => $vence->month,
            'anio'              => $vence->year,
            'fecha_vencimiento' => $vence->toDateString(),
            'monto_programa'    => $valor,
            'monto_sistema'     => 0,
            'monto_total'       => $valor,
            'estado'            => 'pendiente',
        ]);
    }

    /**
     * Crea las cuotas que le faltan al plan de pago (todas al elegir el plan; las restantes
     * al reintegrarse), con el descuento de la matrícula. La primera vence el día 10 del mes de $desde.
     * Devuelve cuántas creó. Sin plan elegido no hay cuotas.
     */
    public function generarCuotas(Matricula $matricula, CarbonInterface $desde): int
    {
        $plan = $matricula->plan_pago;
        if (! $plan) {
            return 0;
        }
        $programa = $matricula->programa;
        $total    = $plan === 'mensual' ? $programa->duracion_meses : $programa->duracion_periodos;
        $vigentes = $matricula->pagos()->where('tipo', $plan)->where('estado', '!=', 'anulado')->count();
        $faltan   = $total - $vigentes;

        [$base, $sistema] = $this->valoresCuota($programa, $plan);
        $inicio = Carbon::parse($desde)->startOfMonth();

        for ($i = 0; $i < $faltan; $i++) {
            $numero = $vigentes + $i + 1;
            $meses  = $plan === 'mensual' ? $i : (int) floor($i * $programa->mesesPorPeriodo());
            $vence  = $inicio->copy()->addMonthsNoOverflow($meses)->day(Pago::DIA_VENCIMIENTO);
            // La última cuota absorbe los pesos que sobran al repartir el precio
            $valor     = $numero === $total ? (float) $programa->precio_total - $base * ($total - 1) : $base;
            $descuento = $this->descuentoDe($valor, (float) $matricula->descuento_pct);

            $matricula->pagos()->create([
                'tipo'              => $plan,
                'numero_cuota'      => $numero,
                'mes'               => $vence->month,
                'anio'              => $vence->year,
                'fecha_vencimiento' => $vence->toDateString(),
                'monto_programa'    => $valor,
                'descuento'         => $descuento,
                'monto_sistema'     => $sistema,
                'monto_total'       => $valor - $descuento + $sistema,
                'estado'            => $vence->lt(today()) ? 'vencido' : 'pendiente',
            ]);
        }
        // Lo trasladado (cambio de programa, reintegro) se aplica en cuanto hay cuotas
        $this->aplicarSaldoFavor($matricula->fresh());

        return max(0, $faltan);
    }

    /** [valor del programa por cuota, cuota del sistema por cuota], en pesos enteros. */
    public function valoresCuota(Programa $programa, string $plan): array
    {
        $cuotas  = $plan === 'mensual' ? $programa->duracion_meses : $programa->duracion_periodos;
        $meses   = $plan === 'mensual' ? 1 : $programa->mesesPorPeriodo();

        return [
            (float) floor((float) $programa->precio_total / $cuotas),
            (float) round(Programa::cuotaSistemaMensual() * $meses),
        ];
    }

    /** Pesos que se rebajan de $valor con el porcentaje de descuento (enteros). */
    private function descuentoDe(float $valor, float $pct): float
    {
        return $pct > 0 ? (float) round($valor * min($pct, 100) / 100) : 0.0;
    }

    /**
     * Beca o convenio: porcentaje sobre el valor del programa (no sobre la cuota del sistema ni el
     * derecho de matrícula). Se aplica a las cuotas que no ha empezado a pagar y a las que se generen
     * después; las que ya tienen abonos conservan su valor. 0 quita el descuento.
     */
    public function aplicarDescuento(Matricula $matricula, float $pct, ?string $motivo): int
    {
        return DB::transaction(function () use ($matricula, $pct, $motivo) {
            $matricula->update(['descuento_pct' => $pct, 'descuento_motivo' => $pct > 0 ? $motivo : null]);

            $cuotas = $matricula->pagos()->where('tipo', '!=', 'matricula')->whereIn('estado', Pago::POR_PAGAR)
                ->where('abonado', 0)->get();
            foreach ($cuotas as $c) {
                $descuento = $this->descuentoDe((float) $c->monto_programa, $pct);
                $c->update(['descuento' => $descuento, 'monto_total' => (float) $c->monto_programa - $descuento + (float) $c->monto_sistema]);
            }

            return $cuotas->count();
        });
    }

    /** Lo que se debe, en el orden en que se paga: por vencimiento (lo más viejo primero) y al final el derecho en espera. */
    public function cobrosPorPagar(Matricula $matricula): Collection
    {
        return $matricula->pagos()->whereIn('estado', Pago::POR_PAGAR)->get()
            ->sortBy(fn (Pago $p) => [$p->fecha_vencimiento ? 0 : 1, $p->fecha_vencimiento?->toDateString() ?? '', $p->id])
            ->values();
    }

    /**
     * Registra un pago de $valor pesos con su recibo y lo reparte en orden: completa lo más viejo
     * y lo que sobra queda abonado al siguiente cobro. No se puede pagar más de lo que se debe.
     * $datos: fecha_pago, metodo_pago, referencia (opcional), observacion (opcional).
     */
    public function registrarPago(Matricula $matricula, float $valor, array $datos): Recaudo
    {
        return DB::transaction(function () use ($matricula, $valor, $datos) {
            Matricula::whereKey($matricula->id)->lockForUpdate()->first();
            $cobros = $this->cobrosPorPagar($matricula);
            abort_if($valor <= 0 || $valor > $cobros->sum(fn (Pago $p) => $p->restante()) + 0.001, 422, 'El valor no corresponde a lo que debe.');

            $recaudo = $matricula->recaudos()->create([
                'recibo'         => Pago::siguienteRecibo(),
                'fecha_pago'     => $datos['fecha_pago'],
                'valor'          => $valor,
                'metodo_pago'    => $datos['metodo_pago'],
                'referencia'     => $datos['referencia'] ?? null,
                'observacion'    => $datos['observacion'] ?? null,
                'registrado_por' => auth()->id(),
            ]);

            $queda = $valor;
            foreach ($cobros as $c) {
                if ($queda <= 0) {
                    break;
                }
                $aplica = min($queda, $c->restante());
                $recaudo->cobros()->attach($c->id, ['monto' => $aplica]);
                $abonado = round((float) $c->abonado + $aplica, 2);
                $completo = $abonado - (float) $c->reembolsado >= (float) $c->monto_total;
                $c->update([
                    'abonado'    => $abonado,
                    'estado'     => $completo ? 'pagado' : $c->estado,
                    'fecha_pago' => $completo ? $datos['fecha_pago'] : null,
                ]);
                $queda = round($queda - $aplica, 2);
            }

            return $recaudo->load('cobros');
        });
    }

    /**
     * Anula un pago mal registrado: devuelve lo que abonó a cada cobro (vuelven a deberse, pendientes
     * o vencidos según su fecha). El recaudo queda anulado con su motivo; su recibo no se reutiliza.
     */
    public function anularRecaudo(Recaudo $recaudo, string $motivo): void
    {
        DB::transaction(function () use ($recaudo, $motivo) {
            foreach ($recaudo->cobros()->lockForUpdate()->get() as $c) {
                $abonado = max(0, round((float) $c->abonado - (float) $c->pivot->monto, 2));
                $estado  = $c->estado;
                if ($estado === 'pagado') {
                    $estado = $c->fecha_vencimiento && $c->fecha_vencimiento->lt(today()) ? 'vencido' : 'pendiente';
                }
                $c->update(['abonado' => $abonado, 'estado' => $estado, 'fecha_pago' => $estado === 'pagado' ? $c->fecha_pago : null]);
            }
            $recaudo->update(['anulado_en' => now(), 'anulado_por' => auth()->id(), 'motivo_anulacion' => $motivo]);
        });
    }

    /**
     * Al aplazar o retirarse: las cuotas que vencen después de $desde ya no se deben. Las atrasadas sí.
     * Si una de ellas tenía un abono, ese dinero queda como adelantado (ver adelantado()).
     */
    public function anularCuotasFuturas(Matricula $matricula, CarbonInterface $desde): int
    {
        return $matricula->pagos()->where('tipo', '!=', 'matricula')
            ->whereIn('estado', Pago::POR_PAGAR)
            ->whereDate('fecha_vencimiento', '>', $desde->toDateString())
            ->update(['estado' => 'anulado']);
    }

    /* ── Dinero adelantado: devoluciones, traslados y saldo a favor ── */

    /**
     * Lo pagado de más en una matrícula aplazada o retirada: cuotas pagadas que vencen después del
     * aplazamiento/retiro y abonos que quedaron en cobros anulados. Devuelve esos cobros (lo que se puede
     * sacar de cada uno es disponible()).
     */
    public function adelantado(Matricula $matricula): Collection
    {
        $desde = $matricula->estado_fecha?->toDateString() ?? today()->toDateString();

        return $matricula->pagos()->whereColumn('abonado', '>', 'reembolsado')->get()
            ->filter(fn (Pago $p) => $p->estado === 'anulado'
                || ($p->estado === 'pagado' && $p->esCuota() && $p->fecha_vencimiento && $p->fecha_vencimiento->toDateString() > $desde))
            ->sortBy(fn (Pago $p) => [$p->fecha_vencimiento?->toDateString() ?? '', $p->id])
            ->values();
    }

    public function totalAdelantado(Matricula $matricula): int
    {
        return (int) round($this->adelantado($matricula)->sum(fn (Pago $p) => $p->disponible()));
    }

    /** Lo que se debe de lo ya vencido o del día (con fecha), en orden: lo primero que se cubre con lo adelantado. */
    private function deudaConFecha(Matricula $matricula): Collection
    {
        return $this->cobrosPorPagar($matricula)
            ->filter(fn (Pago $p) => $p->fecha_vencimiento && $p->fecha_vencimiento->lte(today()))->values();
    }

    /**
     * Cómo quedaría lo adelantado: [total, para cubrir lo que debe, para devolver/trasladar].
     * Primero se cubre lo vencido de la misma matrícula; solo se devuelve o traslada el resto.
     */
    public function repartoAdelantado(Matricula $matricula): array
    {
        $total = $this->totalAdelantado($matricula);
        $debe  = (int) round($this->deudaConFecha($matricula)->sum(fn (Pago $p) => $p->restante()));
        $cubre = min($total, $debe);

        return ['total' => $total, 'cubre' => $cubre, 'resto' => $total - $cubre];
    }

    /**
     * Saca el dinero adelantado de sus cobros (que quedan anulados), cubre con él lo vencido de la
     * misma matrícula y devuelve lo que sobra como [[Pago, monto], ...] para devolverlo o trasladarlo.
     */
    private function liberarAdelantado(Matricula $matricula): array
    {
        $fuentes = [];
        foreach ($this->adelantado($matricula) as $p) {
            $monto = $p->disponible();
            $p->update(['reembolsado' => round((float) $p->reembolsado + $monto, 2), 'estado' => 'anulado']);
            $fuentes[] = [$p, $monto];
        }

        // Lo vencido de la misma matrícula se cubre primero, sacando de las fuentes en orden
        foreach ($this->deudaConFecha($matricula) as $deuda) {
            foreach ($fuentes as $i => [$fuente, $monto]) {
                if ($monto <= 0 || $deuda->restante() <= 0) {
                    continue;
                }
                $aplica = min($monto, $deuda->restante());
                $this->abonar($deuda, $aplica);
                $fuentes[$i][1] = round($monto - $aplica, 2);
            }
        }

        return array_values(array_filter($fuentes, fn ($f) => $f[1] > 0));
    }

    /**
     * Registra la devolución de lo adelantado (después de cubrir lo vencido de la misma matrícula),
     * con su comprobante. Devuelve [Devolucion|null, cubierto]: null si todo se usó para cubrir deuda.
     */
    public function registrarDevolucion(Matricula $matricula, array $datos): array
    {
        return DB::transaction(function () use ($matricula, $datos) {
            Matricula::whereKey($matricula->id)->lockForUpdate()->first();
            $reparto = $this->repartoAdelantado($matricula);
            $fuentes = $this->liberarAdelantado($matricula);
            $valor   = round(array_sum(array_column($fuentes, 1)), 2);
            if ($valor <= 0) {
                return [null, $reparto['cubre']];
            }

            $devolucion = $matricula->devoluciones()->create([
                'comprobante'    => Devolucion::siguienteComprobante(),
                'fecha'          => $datos['fecha'],
                'valor'          => $valor,
                'metodo_pago'    => $datos['metodo_pago'],
                'referencia'     => $datos['referencia'] ?? null,
                'motivo'         => $datos['motivo'],
                'registrado_por' => auth()->id(),
            ]);
            foreach ($fuentes as [$p, $monto]) {
                $devolucion->cobros()->attach($p->id, ['monto' => $monto]);
            }

            return [$devolucion, $reparto['cubre']];
        });
    }

    /**
     * Cambio de programa: lo adelantado de la matrícula vieja (después de cubrir lo que debía vencido)
     * pasa como saldo a favor a la nueva y se aplica de una vez a sus cobros. Devuelve lo trasladado.
     */
    public function trasladarAdelantado(Matricula $vieja, Matricula $nueva): float
    {
        $valor = round(array_sum(array_column($this->liberarAdelantado($vieja), 1)), 2);
        if ($valor > 0) {
            $nueva->update(['saldo_favor' => round((float) $nueva->saldo_favor + $valor, 2)]);
            $this->aplicarSaldoFavor($nueva->fresh());
        }

        return $valor;
    }

    /** Reintegro: los abonos que quedaron en cuotas anuladas al aplazarse vuelven a la matrícula como saldo a favor. */
    public function recuperarAdelantado(Matricula $matricula): float
    {
        $valor = 0.0;
        foreach ($this->adelantado($matricula)->where('estado', 'anulado') as $p) {
            $monto = $p->disponible();
            $p->update(['reembolsado' => round((float) $p->reembolsado + $monto, 2)]);
            $valor += $monto;
        }
        if ($valor > 0) {
            $matricula->update(['saldo_favor' => round((float) $matricula->saldo_favor + $valor, 2)]);
        }

        return $valor;
    }

    /** Aplica el saldo a favor a lo que se debe, en orden (lo más viejo primero). Devuelve lo aplicado. */
    public function aplicarSaldoFavor(Matricula $matricula): float
    {
        $saldo = (float) $matricula->saldo_favor;
        if ($saldo <= 0) {
            return 0.0;
        }
        $usado = 0.0;
        foreach ($this->cobrosPorPagar($matricula) as $c) {
            if ($saldo - $usado <= 0) {
                break;
            }
            $aplica = min($saldo - $usado, $c->restante());
            $this->abonar($c, $aplica);
            $usado = round($usado + $aplica, 2);
        }
        $matricula->update(['saldo_favor' => round($saldo - $usado, 2)]);

        return $usado;
    }

    /** Suma $monto a lo abonado del cobro; si queda cubierto, pasa a pagado (hoy). */
    private function abonar(Pago $c, float $monto): void
    {
        $abonado  = round((float) $c->abonado + $monto, 2);
        $completo = $abonado - (float) $c->reembolsado >= (float) $c->monto_total;
        $c->update([
            'abonado'    => $abonado,
            'estado'     => $completo ? 'pagado' : $c->estado,
            'fecha_pago' => $completo ? today() : $c->fecha_pago,
        ]);
    }

    /** Matrícula anulada (hecha por error): nada de lo que tenía pendiente se cobra. */
    public function anularCobrosPendientes(Matricula $matricula): int
    {
        return $matricula->pagos()->whereIn('estado', Pago::POR_PAGAR)->update(['estado' => 'anulado']);
    }

    /**
     * Derecho de matrícula sin pagar que todavía no vencía el $desde (o que ya estaba en espera):
     * es el que se aplaza o se anula cuando el estudiante no alcanza a empezar clases.
     */
    private function derechoSinCausar(Matricula $matricula, CarbonInterface $desde)
    {
        return $matricula->pagos()->where('tipo', 'matricula')->whereIn('estado', Pago::POR_PAGAR)
            ->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>', $desde->toDateString()));
    }

    /** Al aplazar: el derecho que no ha vencido queda en espera (sin fecha) hasta que se reintegre. */
    public function derechoEnEspera(Matricula $matricula, CarbonInterface $desde): int
    {
        return $this->derechoSinCausar($matricula, $desde)
            ->update(['fecha_vencimiento' => null, 'mes' => null, 'anio' => null, 'estado' => 'pendiente']);
    }

    /** Al retirarse sin haber empezado: el derecho que no había vencido (y sin abonos) ya no se debe. */
    public function anularDerechoSinCausar(Matricula $matricula, CarbonInterface $desde): int
    {
        return $this->derechoSinCausar($matricula, $desde)->where('abonado', 0)->update(['estado' => 'anulado']);
    }

    /** Al reintegrarse: el derecho en espera vence cuando empiezan las clases del grupo al que vuelve. */
    public function programarDerecho(Matricula $matricula, Curso $grupo): int
    {
        $vence = $this->vencimientoDerecho($grupo);

        return $matricula->pagos()->where('tipo', 'matricula')->whereIn('estado', Pago::POR_PAGAR)->whereNull('fecha_vencimiento')
            ->update(['fecha_vencimiento' => $vence->toDateString(), 'mes' => $vence->month, 'anio' => $vence->year]);
    }

    /**
     * El grupo de la matrícula cambió de fecha de inicio (se pasó a otro grupo, o se corrió el inicio
     * en Grupos): el derecho sin pagar vence el nuevo día de inicio, si las clases aún no empiezan.
     * Así nadie queda bloqueado por una fecha que ya no es la de sus clases.
     */
    public function reprogramarDerecho(Matricula $matricula): int
    {
        $grupo = $matricula->curso;
        if (! $grupo || $matricula->estado !== 'activo' || ! $grupo->fecha_inicio->gt(today())) {
            return 0;
        }

        return $matricula->pagos()->where('tipo', 'matricula')->whereIn('estado', Pago::POR_PAGAR)->whereNotNull('fecha_vencimiento')
            ->update(['fecha_vencimiento' => $grupo->fecha_inicio->toDateString(), 'mes' => $grupo->fecha_inicio->month,
                      'anio' => $grupo->fecha_inicio->year, 'estado' => 'pendiente']);
    }

    /** Lo mismo para todas las matrículas activas de un grupo al que le cambiaron la fecha de inicio. */
    public function reprogramarDerechosDelGrupo(Curso $grupo): void
    {
        $grupo->matriculas()->where('estado', 'activo')->with('curso')->get()
            ->each(fn (Matricula $m) => $this->reprogramarDerecho($m));
    }

    /**
     * Derecho de matrícula vencido sin pagar completo en una matrícula vigente: el estudiante no
     * puede ingresar a la plataforma hasta pagarlo (antes de que empiecen sus clases sí puede).
     */
    public function derechoVencido(User $estudiante): ?Pago
    {
        return Pago::where('tipo', 'matricula')->whereIn('estado', Pago::POR_PAGAR)
            ->whereNotNull('fecha_vencimiento')->whereDate('fecha_vencimiento', '<', today()->toDateString())
            ->whereHas('matricula', fn ($q) => $q->where('estudiante_id', $estudiante->id)->whereIn('estado', Matricula::VIGENTES))
            ->orderBy('fecha_vencimiento')->first();
    }

    /** Guarda como vencidos los pendientes que ya pasaron su fecha (comando pagos:vencer). */
    public function vencerAtrasados(): int
    {
        return Pago::where('estado', 'pendiente')->whereDate('fecha_vencimiento', '<', today())->update(['estado' => 'vencido']);
    }

    /**
     * Resumen de pagos de la matrícula (espera cargadas las relaciones pagos, recaudos y programa).
     * pago: vencido > pendiente (cobro del mes sin pagar) > al_dia. saldo = lo que debe pagar este
     * mes (lo vencido + lo que vence este mes, descontando abonos); por_pagar = todo lo que falta.
     */
    public function resumen(Matricula $matricula): array
    {
        /** @var Collection<int, Pago> $pagos */
        $pagos   = $matricula->pagos->where('estado', '!=', 'anulado');
        $cuotas  = $pagos->filter(fn (Pago $p) => $p->esCuota());
        $debe    = $pagos->filter(fn (Pago $p) => $p->porPagar());
        $finMes  = today()->endOfMonth();
        $vencido = $debe->filter(fn (Pago $p) => $p->estadoActual() === 'vencido');
        $delMes  = $debe->filter(fn (Pago $p) => $p->fecha_vencimiento && $p->fecha_vencimiento->lte($finMes));
        $programa = $matricula->programa;
        $restante = fn (Collection $c) => (int) round($c->sum(fn (Pago $p) => $p->restante()));

        return [
            'plan_pago'      => $matricula->plan_pago,
            'pago'           => $vencido->isNotEmpty() ? 'vencido' : ($delMes->isNotEmpty() ? 'pendiente' : 'al_dia'),
            'cuotas_total'   => $matricula->plan_pago
                ? ($cuotas->count() ?: ($matricula->plan_pago === 'mensual' ? $programa->duracion_meses : $programa->duracion_periodos))
                : null,
            'cuotas_pagadas' => $cuotas->where('estado', 'pagado')->count(),
            'saldo'          => $restante($delMes),
            'saldo_vencido'  => $restante($vencido),
            'por_pagar'      => $restante($debe),
            // Lo que pagó y sigue en esta matrícula: abonos menos lo devuelto o trasladado, más el saldo a favor
            'pagado'         => (int) round($matricula->pagos->sum(fn (Pago $p) => $p->disponible()) + (float) $matricula->saldo_favor),
            'ultimo_pago'    => $matricula->recaudos->whereNull('anulado_en')->max('fecha_pago')?->format('Y-m-d'),
        ];
    }
}
