<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\Devolucion;
use App\Models\Finanzas\Egreso;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel Admin › Contabilidad de una sede: ingresos (lo recaudado en Pagos, sin recibos anulados) contra
 * egresos (los que registra la admin + las devoluciones de Matrículas). Las cuentas del mes (saldo, por
 * categoría, gráfica) se hacen en contabilidad.js con estos datos.
 */
class ContabilidadService
{
    public function datosSede(int $sedeId): array
    {
        return [
            'docentes'     => $this->docentes($sedeId),
            'egresos'      => Egreso::where('sede_id', $sedeId)->with(['registradoPor', 'anuladoPor'])
                ->orderByDesc('fecha')->orderByDesc('id')->get()->map(fn (Egreso $e) => $this->egreso($e))->values(),
            'devoluciones' => $this->devoluciones($sedeId),
            'ingresos'     => $this->ingresosPorMes($sedeId),
            'metodos'      => Pago::METODOS,
        ];
    }

    /** Docentes de la sede para la nómina; activo = cuenta habilitada (los inactivos solo se ven en lo ya registrado). */
    public function docentes(int $sedeId): Collection
    {
        return User::where('role', 'docente')->where('sede_id', $sedeId)
            ->with('docentePerfil.escuela')->orderBy('nombres')->orderBy('apellidos')->get()
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'nombre'      => $this->nombre($u),
                'documento'   => $u->numero_documento,
                'escuela'     => $u->docentePerfil?->escuela?->nombre ?? 'Sin escuela',
                'vinculacion' => $u->docentePerfil?->vinculacion ?? 'hora_catedra',
                'activo'      => (bool) $u->activo && $u->docentePerfil?->estado !== 'inactivo',
            ])->values();
    }

    public function registrar(int $sedeId, array $datos): Egreso
    {
        return DB::transaction(function () use ($sedeId, $datos) {
            $egreso = Egreso::create($this->campos($datos) + [
                'sede_id'        => $sedeId,
                'comprobante'    => Egreso::siguienteComprobante(),
                'registrado_por' => auth()->id(),
            ]);

            return $egreso->load(['registradoPor', 'anuladoPor']);
        });
    }

    /** Edita los datos; el comprobante y quién lo registró no cambian. Devuelve los campos que cambiaron. */
    public function editar(Egreso $egreso, array $datos): array
    {
        $egreso->fill($this->campos($datos));
        $cambios = array_keys($egreso->getDirty());
        $egreso->save();

        return $cambios;
    }

    public function anular(Egreso $egreso, string $motivo): Egreso
    {
        $egreso->update([
            'anulado_en'       => now(),
            'anulado_por'      => auth()->id(),
            'motivo_anulacion' => trim($motivo),
        ]);

        return $egreso->load(['registradoPor', 'anuladoPor']);
    }

    /** Mismo formato que lee contabilidad.js. */
    public function egreso(Egreso $e): array
    {
        return [
            'id'           => $e->id,
            'comprobante'  => $e->comprobante,
            'fecha'        => $e->fecha->toDateString(),
            'categoria'    => $e->categoria,
            'concepto'     => $e->concepto,
            'docente_id'   => $e->docente_id,
            'beneficiario' => $e->beneficiario,
            'valor'        => (int) round((float) $e->valor),
            'metodo'       => $e->metodo_pago,
            'referencia'   => (string) $e->referencia,
            'factura'      => (string) $e->factura,
            'observacion'  => (string) $e->observacion,
            'registrado'   => $this->nombre($e->registradoPor),
            'anulado'      => $e->anulado() ? [
                'fecha'  => $e->anulado_en->toDateString(),
                'por'    => $this->nombre($e->anuladoPor),
                'motivo' => $e->motivo_anulacion,
            ] : null,
        ];
    }

    /**
     * Ingresos por mes ('AAAA-MM'): lo recaudado sin recibos anulados, separado en derecho de matrícula,
     * valor del programa y cuota del sistema. Lo abonado a una cuota se reparte entre programa y sistema
     * en la misma proporción en que están en el cobro.
     */
    public function ingresosPorMes(int $sedeId): Collection
    {
        $recaudos = Recaudo::whereNull('anulado_en')
            ->whereHas('matricula', fn ($q) => $q->where('sede_id', $sedeId))
            ->with('cobros')->get();

        $meses = [];
        foreach ($recaudos as $r) {
            $mes = $r->fecha_pago->format('Y-m');
            $m = $meses[$mes] ??= ['mes' => $mes, 'derecho' => 0.0, 'programa' => 0.0, 'sistema' => 0.0, 'pagos' => 0, 'metodos' => []];
            $m['pagos']++;
            $m['metodos'][$r->metodo_pago] = ($m['metodos'][$r->metodo_pago] ?? 0) + (float) $r->valor;
            foreach ($r->cobros as $p) {
                $monto = (float) $p->pivot->monto;
                if ($p->tipo === 'matricula') {
                    $m['derecho'] += $monto;
                    continue;
                }
                $total   = (float) $p->monto_total;
                $sistema = $total > 0 ? $monto * (float) $p->monto_sistema / $total : 0;
                $m['sistema']  += $sistema;
                $m['programa'] += $monto - $sistema;
            }
            $meses[$mes] = $m;
        }

        // Pesos enteros: el programa se lleva lo que sobra del redondeo para que el total sea el recaudado
        return collect($meses)->sortKeys()->map(function (array $m) {
            $total   = (int) round(array_sum($m['metodos']));
            $derecho = (int) round($m['derecho']);
            $sistema = (int) round($m['sistema']);

            return [
                'mes'      => $m['mes'],
                'derecho'  => $derecho,
                'programa' => $total - $derecho - $sistema,
                'sistema'  => $sistema,
                'pagos'    => $m['pagos'],
                'metodos'  => array_map(fn ($v) => (int) round($v), $m['metodos']),
            ];
        })->values();
    }

    /** Devoluciones de las matrículas de la sede (se registran en Matrículas; aquí cuentan como egreso). */
    private function devoluciones(int $sedeId): Collection
    {
        return Devolucion::whereHas('matricula', fn ($q) => $q->where('sede_id', $sedeId))
            ->with(['matricula.estudiante', 'matricula.programa', 'registradoPor'])
            ->orderByDesc('fecha')->orderByDesc('id')->get()
            ->map(fn (Devolucion $d) => [
                'id'          => $d->id,
                'comprobante' => $d->comprobante,
                'fecha'       => $d->fecha->toDateString(),
                'estudiante'  => $this->nombre($d->matricula->estudiante),
                'documento'   => $d->matricula->estudiante->numero_documento,
                'programa'    => str_replace('Técnico Laboral en ', '', $d->matricula->programa->nombre),
                'valor'       => (int) round((float) $d->valor),
                'metodo'      => $d->metodo_pago,
                'referencia'  => (string) $d->referencia,
                'motivo'      => $d->motivo,
                'registrado'  => $this->nombre($d->registradoPor),
            ])->values();
    }

    /** En nómina el beneficiario es el nombre del docente; en las demás, lo que escribió la admin. */
    private function campos(array $d): array
    {
        $docente = $d['categoria'] === 'nomina' ? User::find($d['docente_id']) : null;

        return [
            'fecha'        => $d['fecha'],
            'categoria'    => $d['categoria'],
            'concepto'     => $d['concepto'],
            'docente_id'   => $docente?->id,
            'beneficiario' => $docente ? $this->nombre($docente) : $d['beneficiario'],
            'valor'        => $d['valor'],
            'metodo_pago'  => $d['metodo_pago'],
            'referencia'   => $d['referencia'] ?? null,
            'factura'      => $d['factura'] ?? null,
            'observacion'  => $d['observacion'] ?? null,
        ];
    }

    private function nombre(?User $u): string
    {
        return $u ? (trim($u->nombres.' '.$u->apellidos) ?: 'Administrador') : 'Administrador';
    }
}
