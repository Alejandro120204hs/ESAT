<?php

namespace App\Services\Finanzas;

use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Devolucion;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\Institucional\Configuracion;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de cobro de una matrícula: derecho de matrícula, plan de cuotas (mensual o por
 * periodo, lo elige el estudiante), descuentos, pagos con recibo (abonos) y el resumen
 * que muestran Matrículas y Estudiantes.
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
     * Con $hasta solo crea las que vencen hasta ese día (un tramo antes de un aplazamiento, ver tramosDeCuotas).
     * Devuelve cuántas creó. Sin plan elegido no hay cuotas.
     */
    public function generarCuotas(Matricula $matricula, CarbonInterface $desde, ?string $hasta = null): int
    {
        $plan = $matricula->plan_pago;
        if (! $plan) {
            return 0;
        }
        $programa = $matricula->programa;
        $total    = $plan === 'mensual' ? $programa->duracion_meses : $programa->duracion_periodos;
        $vigentes = $matricula->pagos()->where('tipo', $plan)->where('estado', '!=', 'anulado')->count();
        $faltan   = $total - $vigentes;

        $precio = $matricula->precioPrograma();
        [$base, $sistema] = $this->valoresCuota($programa, $plan, $precio);
        $inicio = Carbon::parse($desde)->startOfMonth();

        $creadas = 0;
        for ($i = 0; $i < $faltan; $i++) {
            $numero = $vigentes + $i + 1;
            // Por periodo: cada cuota vence al empezar su periodo y cobra la cuota del sistema de los meses que dura
            if ($plan !== 'mensual') {
                $sistema = (float) round(Programa::cuotaSistemaMensual() * $programa->mesesDelPeriodo($numero - 1));
            }
            $meses  = $plan === 'mensual' ? $i : $programa->inicioDelPeriodo($numero - 1) - $programa->inicioDelPeriodo($vigentes);
            $vence  = $inicio->copy()->addMonthsNoOverflow($meses)->day(Pago::DIA_VENCIMIENTO);
            if ($hasta !== null && $vence->toDateString() > $hasta) {
                break;
            }
            // La última cuota absorbe los pesos que sobran al repartir el precio
            $valor     = $numero === $total ? $precio - $base * ($total - 1) : $base;
            $descuento = $this->descuentoDe($valor, (float) $matricula->descuento_pct);
            $cobra     = $valor - $descuento + $sistema;

            $matricula->pagos()->create([
                'tipo'              => $plan,
                'numero_cuota'      => $numero,
                'mes'               => $vence->month,
                'anio'              => $vence->year,
                'fecha_vencimiento' => $vence->toDateString(),
                'monto_programa'    => $valor,
                'descuento'         => $descuento,
                'monto_sistema'     => $sistema,
                'monto_total'       => $cobra,
                // Una cuota en $ 0 (beca completa sin cuota del sistema) no se debe: queda pagada sin dinero
                'estado'            => $cobra <= 0 ? 'pagado' : ($vence->lt(today()) ? 'vencido' : 'pendiente'),
                'fecha_pago'        => $cobra <= 0 ? today() : null,
            ]);
            $creadas++;
        }
        // Lo trasladado (cambio de programa, reintegro) se aplica en cuanto hay cuotas
        $this->aplicarSaldoFavor($matricula->fresh());

        return $creadas;
    }

    /**
     * Día en que vence la primera cuota de un plan: el 10 del mes en que empiezan las clases del grupo, o del mes
     * siguiente si empiezan después del 10. Un plan elegido tarde también cuenta desde ahí (decidido con el usuario el
     * 24-11-2026): paga lo mismo que sus compañeros y las cuotas que ya pasaron quedan vencidas.
     */
    public function primeraCuota(Curso $grupo): CarbonInterface
    {
        $d = $grupo->fecha_inicio->copy()->startOfMonth();
        if ($grupo->fecha_inicio->day > Pago::DIA_VENCIMIENTO) {
            $d->addMonthNoOverflow();
        }

        return $d->day(Pago::DIA_VENCIMIENTO);
    }

    /**
     * Primera cuota de quien vuelve a clases el $fecha (reintegro): la que vence el día 10 a partir de esa fecha, y nunca
     * antes de que empiecen las clases de $grupo. La usan el reintegro (MatriculaService) y elegirPlan.
     */
    public function primeraCuotaDesde(CarbonInterface|string $fecha, Curso $grupo): CarbonInterface
    {
        $desde = Carbon::parse($fecha)->startOfDay();
        if ($desde->day > Pago::DIA_VENCIMIENTO) {
            $desde->addMonthNoOverflow();
        }
        $primera = $this->primeraCuota($grupo);

        return $primera->gt($desde) ? Carbon::parse($primera) : $desde;
    }

    /**
     * Tramos en que se cobran las cuotas de un plan elegido ahora: lo mismo que habría pasado con el plan elegido desde el
     * principio (ES11). Sin aplazamientos, un tramo desde primeraCuota del grupo. Si se aplazó y volvió, el primer tramo
     * cobra las cuotas que vencían hasta el aplazamiento (las demás se habrían anulado, anularCuotasFuturas) y cada
     * reintegro cobra las que faltan desde primeraCuotaDesde, como el reintegro. Cada tramo: ['desde' => fecha, 'hasta' =>
     * último día incluido o null]. Lee el historial de grupos de la matrícula: un aplazamiento deja un hueco entre pasos.
     */
    public function tramosDeCuotas(Matricula $m): array
    {
        $pasos = $m->grupos()->with('curso')->orderBy('desde')->orderBy('id')->get();
        if ($pasos->isEmpty()) {
            return [['desde' => $this->primeraCuota($m->curso), 'hasta' => null]];
        }
        $tramos = [];
        foreach ($pasos as $i => $p) {
            $previo = $i > 0 ? $pasos[$i - 1] : null;
            if (! $previo || ($previo->hasta && $previo->hasta->lt($p->desde))) {
                $tramos[] = ['inicio' => $p->desde, 'reintegro' => (bool) $previo, 'curso' => $p->curso, 'hasta' => null];
            }
            $k = count($tramos) - 1;
            // Un cambio de grupo antes del primer aplazamiento: las cuotas siguen al grupo nuevo (reprogramarCuotas)
            if (! $tramos[$k]['reintegro']) {
                $tramos[$k]['curso'] = $p->curso;
            }
            $tramos[$k]['hasta'] = $p->hasta?->toDateString();
        }
        $tramos[count($tramos) - 1]['hasta'] = null;

        return array_map(fn (array $t) => [
            'desde' => $t['reintegro'] ? $this->primeraCuotaDesde($t['inicio'], $t['curso']) : $this->primeraCuota($t['curso']),
            'hasta' => $t['hasta'],
        ], $tramos);
    }

    /** Fechas de vencimiento de las cuotas que crearía elegir ahora el $plan (las mismas cuentas de generarCuotas). */
    public function fechasCuotas(Matricula $m, string $plan): array
    {
        $programa = $m->programa;
        $total = $plan === 'mensual' ? (int) $programa->duracion_meses : (int) $programa->duracion_periodos;
        $fechas = [];
        foreach ($this->tramosDeCuotas($m) as $t) {
            $inicio = Carbon::parse($t['desde'])->startOfMonth();
            $base = count($fechas);
            for ($k = $base; $k < $total; $k++) {
                $meses = $plan === 'mensual' ? $k - $base : $programa->inicioDelPeriodo($k) - $programa->inicioDelPeriodo($base);
                $vence = $inicio->copy()->addMonthsNoOverflow($meses)->day(Pago::DIA_VENCIMIENTO);
                if ($t['hasta'] !== null && $vence->toDateString() > $t['hasta']) {
                    break;
                }
                $fechas[] = $vence;
            }
        }

        return $fechas;
    }

    /**
     * Elige el plan de pago de la matrícula (una sola vez: no cambia después) y genera todas sus cuotas desde la primera
     * del grupo (primeraCuota), o por tramos si se aplazó y volvió (tramosDeCuotas). Lo eligen el estudiante desde su
     * panel o, si todavía no lo eligió, la secretaría desde Matrículas. Se vuelve a revisar con la matrícula bloqueada:
     * otra persona pudo elegirlo al mismo tiempo (M13). Devuelve cuántas cuotas creó.
     */
    public function elegirPlan(Matricula $matricula, string $plan, bool $porLaSecretaria = false): int
    {
        if (! in_array($plan, ['mensual', 'periodo'], true)) {
            throw ValidationException::withMessages(['plan' => 'Elige el plan mensual o por periodo.']);
        }

        return DB::transaction(function () use ($matricula, $plan, $porLaSecretaria) {
            // Orden de M13: el estudiante antes que su matrícula
            User::whereKey($matricula->estudiante_id)->lockForUpdate()->first();
            $m = Matricula::whereKey($matricula->id)->lockForUpdate()->firstOrFail();
            if ($m->plan_pago) {
                throw ValidationException::withMessages(['plan' => 'El plan de pago ya se eligió y no se puede cambiar.']);
            }
            if ($m->estado !== 'activo') {
                throw ValidationException::withMessages(['plan' => 'Solo se elige el plan de una matrícula activa.']);
            }
            $grupo = $m->curso;
            if (! $grupo) {
                throw ValidationException::withMessages(['plan' => 'La matrícula no tiene grupo: no se pueden calcular las cuotas.']);
            }

            $m->update(['plan_pago' => $plan]);
            $tramos = $this->tramosDeCuotas($m);
            $n = 0;
            foreach ($tramos as $t) {
                $n += $this->generarCuotas($m->fresh(), $t['desde'], $t['hasta']);
            }
            $m->loadMissing('programa');
            $nombre = $plan === 'mensual' ? 'mensual' : ($m->programa->tipo_periodo === 'semestre' ? 'semestral' : 'trimestral');
            Auditoria::registrar('editar', $m, ($porLaSecretaria ? 'Eligió por el estudiante el plan de pago ' : 'Eligió su plan de pago ')
                .$nombre.': '.$n.' '.($n === 1 ? 'cuota' : 'cuotas').' desde el '.Carbon::parse($tramos[0]['desde'])->format('d/m/Y').'.', $m->sede_id);

            return $n;
        });
    }

    /**
     * [valor del programa por cuota, cuota del sistema por cuota], en pesos enteros. $precio: el pactado en la matrícula.
     * Por periodo, la cuota del sistema es la de un periodo completo (el último puede ser más corto: ver generarCuotas).
     */
    public function valoresCuota(Programa $programa, string $plan, ?float $precio = null): array
    {
        $cuotas  = $plan === 'mensual' ? $programa->duracion_meses : $programa->duracion_periodos;
        $meses   = $plan === 'mensual' ? 1 : $programa->mesesDelPeriodo(0);

        return [
            (float) floor(($precio ?? (float) $programa->precio_total) / $cuotas),
            (float) round(Programa::cuotaSistemaMensual() * $meses),
        ];
    }

    /** Pesos que se rebajan de $valor con el porcentaje de descuento (enteros). También lo usa el panel Estudiante. */
    public function descuentoDe(float $valor, float $pct): float
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
            Matricula::whereKey($matricula->id)->lockForUpdate()->first();   // no se cruza con un pago que entra a la vez
            $matricula->update(['descuento_pct' => $pct, 'descuento_motivo' => $pct > 0 ? $motivo : null]);

            // Sin abonos: las que se deben, y las que quedaron en $ 0 por una beca completa (pagadas sin dinero)
            $cuotas = $matricula->pagos()->where('tipo', '!=', 'matricula')->where('abonado', 0)
                ->where(fn ($q) => $q->whereIn('estado', Pago::POR_PAGAR)->orWhere(fn ($q) => $q->where('estado', 'pagado')->where('monto_total', '<=', 0)))
                ->get();
            foreach ($cuotas as $c) {
                $descuento = $this->descuentoDe((float) $c->monto_programa, $pct);
                $total     = (float) $c->monto_programa - $descuento + (float) $c->monto_sistema;
                // En $ 0 no se debe (pagada sin dinero); si vuelve a valer algo, se debe otra vez según su fecha
                $estado = $total <= 0 ? 'pagado' : ($c->fecha_vencimiento && $c->fecha_vencimiento->lt(today()) ? 'vencido' : 'pendiente');
                $c->update(['descuento' => $descuento, 'monto_total' => $total, 'estado' => $estado, 'fecha_pago' => $estado === 'pagado' ? today() : null]);
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
            // Mismo orden de bloqueo que pagar y devolver (primero la matrícula): dos anulaciones, o una anulación y una
            // devolución a la vez, van de a una y la segunda vuelve a revisar
            Matricula::whereKey($recaudo->matricula_id)->lockForUpdate()->first();
            $recaudo = Recaudo::whereKey($recaudo->id)->lockForUpdate()->firstOrFail();
            if ($error = $this->motivoNoAnulable($recaudo)) {
                throw ValidationException::withMessages(['motivo' => $error]);
            }
            foreach ($recaudo->cobros()->lockForUpdate()->get() as $c) {
                $abonado = max(0, round((float) $c->abonado - (float) $c->pivot->monto, 2));
                $retenido = (float) $c->abonado > 0 ? round((float) $c->retenido * $abonado / (float) $c->abonado) : 0;
                $estado  = $c->estado;
                if ($estado === 'pagado') {
                    $estado = $c->fecha_vencimiento && $c->fecha_vencimiento->lt(today()) ? 'vencido' : 'pendiente';
                }
                $c->update(['abonado' => $abonado, 'retenido' => $retenido, 'estado' => $estado, 'fecha_pago' => $estado === 'pagado' ? $c->fecha_pago : null]);
            }
            $recaudo->update(['anulado_en' => now(), 'anulado_por' => auth()->id(), 'motivo_anulacion' => $motivo]);

            // En una aplazada o retirada vuelve a ser deuda solo lo que ya se debía al aplazarse o retirarse: las cuotas
            // de después y el derecho que no alcanzó a causarse siguen sin cobrarse (HALLAZGOS A4)
            $m = Matricula::find($recaudo->matricula_id);
            if (in_array($m->estado, ['aplazado', 'retirado'], true) && $m->estado_fecha) {
                $this->anularCuotasFuturas($m, $m->estado_fecha);
                $m->estado === 'aplazado' ? $this->derechoEnEspera($m, $m->estado_fecha) : $this->anularDerechoSinCausar($m, $m->estado_fecha);
            }
        });
    }

    /**
     * Al aplazar o retirarse: las cuotas que vencen después de $desde ya no se deben. Las atrasadas sí.
     * Las que ya estaban pagadas también se anulan y conservan su abono: ese dinero queda marcado en el cobro como
     * adelantado (ver adelantado()), sin importar cuántos cambios de estado vengan después (HALLAZGOS A5).
     */
    public function anularCuotasFuturas(Matricula $matricula, CarbonInterface $desde): int
    {
        return $matricula->pagos()->where('tipo', '!=', 'matricula')
            ->whereIn('estado', [...Pago::POR_PAGAR, 'pagado'])
            ->whereDate('fecha_vencimiento', '>', $desde->toDateString())
            ->update(['estado' => 'anulado']);
    }

    /* ── Dinero adelantado: devoluciones, traslados y saldo a favor ── */

    /**
     * Lo pagado de más en una matrícula: abonos que quedaron en cobros anulados (cuotas de después de un aplazamiento o
     * retiro —se anulan con su abono, ver anularCuotasFuturas()— y el derecho abonado de quien se retiró antes de
     * empezar). Devuelve esos cobros (lo que se puede sacar de cada uno es disponible()).
     */
    public function adelantado(Matricula $matricula): Collection
    {
        return $matricula->pagos()->where('estado', 'anulado')->whereColumn('abonado', '>', 'reembolsado')->get()
            ->filter(fn (Pago $p) => $p->disponible() > 0)   // lo retenido no se devuelve
            ->sortBy(fn (Pago $p) => [$p->fecha_vencimiento?->toDateString() ?? '', $p->id])
            ->values();
    }

    /** Todo el dinero que tiene la matrícula: lo que sigue en sus cobros más el saldo a favor (HALLAZGOS A6). */
    public function dineroEn(Matricula $matricula): int
    {
        return (int) round($matricula->pagos()->get()->sum(fn (Pago $p) => $p->disponible()) + (float) $matricula->saldo_favor);
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

    /** Por qué no se puede anular ese pago (null si se puede). La usan AnularPagoRequest y anularRecaudo, ya bloqueado. */
    public function motivoNoAnulable(Recaudo $recaudo): ?string
    {
        return match (true) {
            $recaudo->anulado()                                            => 'Ese pago ya está anulado.',
            // Se graduó a paz y salvo: anular un pago la dejaría graduada con deuda
            $recaudo->matricula->estado === 'graduado'                     => 'La matrícula está graduada: sus pagos ya no se pueden anular.',
            // Ese dinero ya salió de aquí (se devolvió, cubrió otra deuda o pasó a otra matrícula)
            $recaudo->cobros()->where('reembolsado', '>', 0)->exists()     => 'Parte de este pago ya se devolvió o se trasladó a otra matrícula: no se puede anular.',
            default                                                        => null,
        };
    }

    /**
     * Registra la devolución de lo adelantado (después de cubrir lo vencido de la misma matrícula),
     * con su comprobante. Devuelve [Devolucion|null, cubierto]: null si todo se usó para cubrir deuda.
     */
    public function registrarDevolucion(Matricula $matricula, array $datos): array
    {
        return DB::transaction(function () use ($matricula, $datos) {
            Matricula::whereKey($matricula->id)->lockForUpdate()->first();
            // La Form Request lo revisó sin el bloqueo: con dos envíos a la vez, el segundo llega cuando el primero ya lo
            // devolvió todo, y no debe dejar en la auditoría que "se usó para cubrir lo que debía" (HALLAZGOS B27)
            if ($this->totalAdelantado($matricula) <= 0) {
                throw ValidationException::withMessages(['motivo' => 'Esta matrícula no tiene dinero adelantado para devolver.']);
            }
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

    /**
     * Al retirarse sin haber empezado: el derecho que no había vencido ya no se debe. De lo que alcanzó a pagar (parcial o
     * completo), se le devuelve el porcentaje de Configuración (`devolucion_derecho_pct`, 50 % por defecto) y el resto
     * queda retenido por la institución (`pagos.retenido`); lo devolvible queda como adelantado (HALLAZGOS A9).
     */
    public function anularDerechoSinCausar(Matricula $matricula, CarbonInterface $desde): int
    {
        $retiene = (100 - Configuracion::devolucionDerechoPct()) / 100;
        $cobros = $matricula->pagos()->where('tipo', 'matricula')->whereIn('estado', [...Pago::POR_PAGAR, 'pagado'])
            ->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>', $desde->toDateString()))
            ->get();
        foreach ($cobros as $c) {
            $pagado = max(0, (float) $c->abonado - (float) $c->reembolsado);
            $c->update(['estado' => 'anulado', 'retenido' => round($pagado * $retiene)]);
        }

        return $cobros->count();
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

    /**
     * Lo mismo para todas las matrículas activas de un grupo al que le cambiaron la fecha de inicio: el derecho y las
     * cuotas del plan vencen según el nuevo inicio de clases.
     */
    public function reprogramarDerechosDelGrupo(Curso $grupo): void
    {
        $grupo->matriculas()->where('estado', 'activo')->with(['curso.programa', 'programa'])->get()
            ->each(function (Matricula $m) {
                $this->reprogramarDerecho($m);
                $this->reprogramarCuotas($m);
            });
    }

    /**
     * El grupo de la matrícula empieza en otra fecha (pasó a otro grupo, o se corrió el inicio del suyo): las cuotas que
     * todavía debe vuelven a contar desde la primera cuota del grupo (primeraCuota), como si lo hubiera elegido ahí. Las
     * pagadas conservan su fecha. Solo si el plan nunca se interrumpió (sin cuotas anuladas): después de un aplazamiento
     * las cuotas cuentan desde el reintegro y no se mueven con el grupo. Devuelve cuántas cambiaron de fecha.
     */
    public function reprogramarCuotas(Matricula $matricula): int
    {
        $plan  = $matricula->plan_pago;
        $grupo = $matricula->curso;
        if (! $plan || ! $grupo || $matricula->estado !== 'activo'
            || $matricula->pagos()->where('tipo', $plan)->where('estado', 'anulado')->exists()) {
            return 0;
        }
        $programa = $matricula->programa;
        $inicio   = Carbon::parse($this->primeraCuota($grupo))->startOfMonth();
        $cambiadas = 0;
        foreach ($matricula->pagos()->where('tipo', $plan)->whereIn('estado', Pago::POR_PAGAR)->get() as $c) {
            $meses = $plan === 'mensual' ? $c->numero_cuota - 1 : $programa->inicioDelPeriodo($c->numero_cuota - 1);
            $vence = $inicio->copy()->addMonthsNoOverflow($meses)->day(Pago::DIA_VENCIMIENTO);
            if ($c->fecha_vencimiento && $c->fecha_vencimiento->isSameDay($vence)) {
                continue;
            }
            $c->update([
                'fecha_vencimiento' => $vence->toDateString(), 'mes' => $vence->month, 'anio' => $vence->year,
                'estado' => $vence->lt(today()) ? 'vencido' : 'pendiente',
            ]);
            $cambiadas++;
        }

        return $cambiadas;
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
        $restante = fn (Collection $c) => (int) round($c->sum(fn (Pago $p) => $p->restante()));

        return [
            'plan_pago'      => $matricula->plan_pago,
            'pago'           => $vencido->isNotEmpty() ? 'vencido' : ($delMes->isNotEmpty() ? 'pendiente' : 'al_dia'),
            'cuotas_total'   => $matricula->totalCuotas($matricula->pagos),
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
