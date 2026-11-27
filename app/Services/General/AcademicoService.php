<?php

namespace App\Services\General;

use App\Models\Institucional\Sede;
use App\Services\Academico\AsistenciaService;
use App\Services\Academico\CalificacionService;
use Carbon\Carbon;

/**
 * Seguimiento académico de todas las sedes (solo consulta): cada grupo con su avance, la asistencia y las notas, sacados
 * de los mismos resúmenes que ven las secretarías (AsistenciaService y CalificacionService), para que las cifras cuadren.
 */
class AcademicoService
{
    public function __construct(
        private PersonasService $personas,
        private AsistenciaService $asistencia,
        private CalificacionService $calificaciones,
    ) {}

    /** Grupos con: asistencia (%), bajo80, sin_registrar, promedio, por_habilitar, sin_nota, modulo_actual y avance (%). */
    public function grupos(): array
    {
        $asis = [];
        $notas = [];
        foreach (Sede::pluck('id') as $sedeId) {
            foreach ($this->asistencia->resumenSede($sedeId)['grupos'] as $g) {
                $asis[$g['id']] = $g;
            }
            foreach ($this->calificaciones->resumenSede($sedeId)['grupos'] as $g) {
                $notas[$g['id']] = $g;
            }
        }
        [, $grupos] = $this->personas->docentes();
        $hoy = now();

        return collect($grupos)->map(function (array $g) use ($asis, $notas, $hoy) {
            $a = $asis[$g['id']] ?? null;
            $n = $notas[$g['id']] ?? null;
            $avance = 0;
            if ($g['inicio'] && $g['fin']) {
                $ini = Carbon::parse($g['inicio']);
                $total = max(1, $ini->diffInDays(Carbon::parse($g['fin'])));
                $avance = (int) round(max(0, min(1, $ini->diffInDays($hoy, false) / $total)) * 100);
            }

            return $g + [
                'asistencia'    => $a['pct'] ?? null,
                'bajo80'        => $a['bajo80'] ?? 0,
                'sin_registrar' => $a ? count($a['sin_tomar']) : 0,
                'promedio'      => $n['prom'] ?? null,
                'por_habilitar' => $n['por_habilitar'] ?? 0,
                'sin_nota'      => $n ? count($n['sin_nota']) : 0,
                'modulo_actual' => $n['actual'] ?? null,
                'avance'        => ['finalizado' => 100, 'por_iniciar' => 0][$g['estado']] ?? $avance,
            ];
        })->all();
    }
}
