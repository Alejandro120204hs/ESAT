<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\Devolucion;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\User;

/**
 * Lo que muestra el panel Admin › Pagos (Finanzas) de una sede: las matrículas con sus cobros, los
 * pagos recibidos (recaudos con su recibo) y las devoluciones. Las cuentas (vencido, a pagar este
 * mes, resumen de caja por fechas, por programa) se hacen en pagos.js con las mismas reglas de
 * PagoService::resumen(). Registrar y anular un pago usan las rutas y reglas de Matrículas
 * (PagoRequest / AnularPagoRequest + PagoService), para que haya una sola regla de cobro.
 */
class CarteraService
{
    public function datosSede(int $sedeId): array
    {
        $matriculas = Matricula::where('sede_id', $sedeId)
            ->where('estado', '!=', 'anulado')
            ->with([
                'programa.escuela', 'curso', 'estudiante.acudientes',
                'pagos' => fn ($q) => $q->where('estado', '!=', 'anulado'),
                'recaudos' => fn ($q) => $q->with(['cobros', 'registradoPor', 'anuladoPor']),
                'devoluciones.registradoPor',
            ])
            ->orderBy('id')
            ->get();

        return [
            'matriculas'   => $matriculas->map(fn (Matricula $m) => $this->matricula($m))->values(),
            'recaudos'     => $matriculas->flatMap(fn (Matricula $m) => $m->recaudos->map(fn (Recaudo $r) => $this->recaudo($r, $m)))
                ->sortBy([['fecha', 'desc'], ['id', 'desc']])->values(),
            'devoluciones' => $matriculas->flatMap(fn (Matricula $m) => $m->devoluciones->map(fn (Devolucion $d) => $this->devolucion($d, $m)))
                ->sortBy([['fecha', 'desc'], ['id', 'desc']])->values(),
        ];
    }

    /** cobros: [id, tipo, concepto, vence, valor, pagado (abonado − devuelto), estado actual]. */
    private function matricula(Matricula $m): array
    {
        $e = $m->estudiante;
        $a = $e->acudientes->first();
        $total = $this->totalCuotas($m);

        return [
            'id'           => $m->id,
            'codigo'       => $m->codigo,
            'estado'       => $m->estado,
            'estado_fecha' => $m->estado_fecha?->toDateString(),
            'plan'         => $m->plan_pago,
            'saldo_favor'  => (int) round((float) $m->saldo_favor),
            'nombres'      => $e->nombres,
            'apellidos'    => $e->apellidos,
            'tipo_doc'     => $e->tipo_documento,
            'documento'    => $e->numero_documento,
            'telefono'     => $this->digitos($e->telefono),
            'menor'        => $e->fecha_nacimiento !== null && $e->fecha_nacimiento->age < 18,
            'acudiente'    => $a ? [
                'nombre'     => trim($a->nombres.' '.$a->apellidos),
                'parentesco' => $a->pivot->parentesco,
                'telefono'   => $this->digitos($a->telefono),
            ] : null,
            'programa_id'  => $m->programa_id,
            'programa'     => $m->programa->nombre,
            'sigla'        => mb_strtolower($m->programa->escuela?->sigla ?? ''),
            'grupo'        => $m->curso?->nombre,
            'jornada'      => $m->curso?->jornada,
            'cobros'       => $m->pagos
                ->sortBy(fn (Pago $p) => [$p->fecha_vencimiento ? 0 : 1, $p->fecha_vencimiento?->toDateString() ?? '', $p->id])
                ->map(fn (Pago $p) => [
                    $p->id, $p->tipo, $this->concepto($p, $total), $p->fecha_vencimiento?->toDateString(),
                    (int) round((float) $p->monto_total), (int) round($p->disponible()), $p->estadoActual(),
                ])->values()->all(),
        ];
    }

    /** cubre: [id del cobro, concepto, monto]. anulable sigue las reglas de AnularPagoRequest. */
    private function recaudo(Recaudo $r, Matricula $m): array
    {
        $total = $this->totalCuotas($m);
        $anulado = $r->anulado_en !== null;

        return [
            'id'           => $r->id,
            'recibo'       => $r->recibo,
            'matricula_id' => $m->id,
            'fecha'        => $r->fecha_pago->toDateString(),
            'valor'        => (int) round((float) $r->valor),
            'metodo'       => $r->metodo_pago,
            'referencia'   => $r->referencia,
            'observacion'  => $r->observacion,
            'registrado'   => $this->nombre($r->registradoPor),
            'cubre'        => $r->cobros->map(fn (Pago $p) => [$p->id, $this->concepto($p, $total), (int) round((float) $p->pivot->monto)])->values()->all(),
            'anulado'      => $anulado ? [
                'fecha'  => $r->anulado_en->toDateString(),
                'motivo' => $r->motivo_anulacion,
                'por'    => $this->nombre($r->anuladoPor),
            ] : null,
            // Graduada (quedó a paz y salvo) o con dinero ya devuelto o trasladado: no se anula
            'anulable'     => ! $anulado && $m->estado !== 'graduado' && ! $r->cobros->contains(fn (Pago $p) => (float) $p->reembolsado > 0),
        ];
    }

    private function devolucion(Devolucion $d, Matricula $m): array
    {
        return [
            'id'           => $d->id,
            'comprobante'  => $d->comprobante,
            'matricula_id' => $m->id,
            'fecha'        => $d->fecha->toDateString(),
            'valor'        => (int) round((float) $d->valor),
            'metodo'       => $d->metodo_pago,
            'referencia'   => $d->referencia,
            'motivo'       => $d->motivo,
            'registrado'   => $this->nombre($d->registradoPor),
        ];
    }

    private function totalCuotas(Matricula $m): ?int
    {
        return $m->plan_pago === 'mensual' ? $m->programa->duracion_meses : ($m->plan_pago ? $m->programa->duracion_periodos : null);
    }

    /** Mismo texto que Matrículas y el recibo. */
    private function concepto(Pago $p, ?int $total): string
    {
        return $p->tipo === 'matricula' ? 'Derecho de matrícula' : "Cuota {$p->numero_cuota} de {$total}";
    }

    private function nombre(?User $u): string
    {
        return $u ? (trim($u->nombres.' '.$u->apellidos) ?: 'Administrador') : 'Administrador';
    }

    private function digitos(?string $tel): string
    {
        return preg_replace('/\D/', '', (string) $tel);
    }
}
