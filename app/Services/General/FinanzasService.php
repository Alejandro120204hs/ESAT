<?php

namespace App\Services\General;

use App\Models\Finanzas\Devolucion;
use App\Models\Finanzas\Egreso;
use App\Models\Institucional\Configuracion;

/**
 * Finanzas de todas las sedes para el panel del Admin (solo consulta): recibos, cartera de los estudiantes, egresos y
 * devoluciones del último año. Lo usan Finanzas y Reportes, con la misma forma (window.GEN_DATOS).
 */
class FinanzasService
{
    /** Nombre en pantalla de cada categoría de egreso (Egreso::CATEGORIAS), el que agrupan Finanzas y Reportes. */
    private const CATEGORIAS = ['nomina' => 'Nómina', 'arriendo' => 'Arriendo y servicios', 'materiales' => 'Materiales e insumos'];

    public function __construct(private PersonasService $personas, private EstructuraService $estructura) {}

    public function datos(): array
    {
        [$estudiantes, $recibos] = $this->personas->estudiantes();
        $desde = now()->subYear()->startOfMonth()->toDateString();
        $campos = ['id', 'sede_id', 'nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'telefono', 'email', 'edad', 'genero', 'acudiente',
            'programa_id', 'programa', 'grupo', 'estado', 'estado_fecha', 'estado_motivo', 'fecha_matricula', 'pago', 'saldo_vencido', 'por_pagar_mes', 'cuotas_vencidas'];

        return [
            'hoy'           => now()->toIso8601String(),
            'sedes'         => collect($this->estructura->sedes())->map(fn ($s) => collect($s)->only(['id', 'nombre', 'ciudad', 'direccion', 'programas']))->all(),
            'programas'     => collect($this->estructura->programas())->map(fn ($p) => collect($p)->only(['id', 'nombre']))->all(),
            'estudiantes'   => collect($estudiantes)->map(fn ($e) => collect($e)->only($campos))->all(),
            'recaudos'      => collect($recibos)->filter(fn ($r) => $r['fecha'] >= $desde)->values()->all(),
            // Los egresos anulados no cuentan
            'egresos'       => Egreso::whereNull('anulado_en')->where('fecha', '>=', $desde)->with('registradoPor:id,nombres,apellidos')->orderBy('fecha')->get()
                ->map(fn (Egreso $g) => [
                    'id' => $g->id, 'numero' => $g->comprobante, 'sede_id' => $g->sede_id, 'fecha' => $g->fecha?->toDateString(),
                    'categoria' => self::CATEGORIAS[$g->categoria] ?? $g->categoria, 'beneficiario' => $g->beneficiario ?: $g->concepto,
                    'valor' => (int) round((float) $g->valor), 'medio' => $g->metodo_pago,
                    'quien' => PersonasService::quien($g->registradoPor)['nombre'],
                ])->all(),
            'devoluciones'  => Devolucion::where('fecha', '>=', $desde)->with(['matricula:id,estudiante_id,sede_id', 'registradoPor:id,nombres,apellidos'])->orderBy('fecha')->get()
                ->map(fn (Devolucion $d) => [
                    'id' => $d->id, 'numero' => $d->comprobante, 'sede_id' => $d->matricula?->sede_id, 'est_id' => $d->matricula?->estudiante_id,
                    'fecha' => $d->fecha?->toDateString(), 'valor' => (int) round((float) $d->valor), 'motivo' => $d->motivo, 'medio' => $d->metodo_pago,
                    'quien' => PersonasService::quien($d->registradoPor)['nombre'],
                ])->all(),
            'configuracion' => ['institucion' => Configuracion::institucion()],
            'yo'            => trim(auth()->user()->nombres.' '.auth()->user()->apellidos) ?: 'Admin',
        ];
    }
}
