<?php

namespace App\Services\Academico;

use App\Models\Academico\Curso;
use App\Models\Academico\CursoSesion;
use App\Models\Academico\Programa;
use Carbon\CarbonInterface;

/**
 * Reglas del horario de un grupo. Lo usan Grupos y Horarios.
 *
 * Una sesión se maneja como ['dia' => 'Lunes', 'hora_inicio' => '08:00', 'hora_fin' => '12:00'].
 */
class HorarioService
{
    /** Minutos de clase por semana. */
    public function minutosSemanales(iterable $sesiones): int
    {
        $total = 0;
        foreach ($sesiones as $s) {
            $total += CursoSesion::aMinutos($s['hora_fin']) - CursoSesion::aMinutos($s['hora_inicio']);
        }

        return $total;
    }

    /** Semanas de clase entre el inicio y el fin del grupo. */
    public function semanas(CarbonInterface $inicio, CarbonInterface $fin): float
    {
        return max(0, $inicio->diffInDays($fin)) / 7;
    }

    /** Horas que suma el horario durante toda la duración del grupo. */
    public function horasProgramadas(iterable $sesiones, CarbonInterface $inicio, CarbonInterface $fin): float
    {
        return $this->minutosSemanales($sesiones) / 60 * $this->semanas($inicio, $fin);
    }

    /**
     * Tope: el horario no puede sumar más horas que las del programa.
     * Devuelve el mensaje de error o null si cumple.
     */
    public function excedeTope(Programa $programa, iterable $sesiones, CarbonInterface $inicio, CarbonInterface $fin): ?string
    {
        $horas = $this->horasProgramadas($sesiones, $inicio, $fin);
        if ($horas <= $programa->horas) {
            return null;
        }

        $semanales = $this->minutosSemanales($sesiones) / 60;
        $maxSemana = $programa->horas / max(0.01, $this->semanas($inicio, $fin));

        return sprintf(
            'El horario suma %s horas (%s h por semana durante %s semanas) y el programa tiene %s horas. Con estas fechas el máximo es %s h por semana.',
            $this->fmt($horas), $this->fmt($semanales), $this->fmt($this->semanas($inicio, $fin)),
            $this->fmt($programa->horas), $this->fmt(floor($maxSemana * 10) / 10)
        );
    }

    /**
     * Un docente no puede tener dos clases a la misma hora en grupos cuyas
     * fechas se cruzan. Devuelve el mensaje del primer cruce o null.
     */
    public function cruceDocente(int $docenteId, iterable $sesiones, CarbonInterface $inicio, CarbonInterface $fin, ?int $excluirCursoId = null): ?string
    {
        $otros = Curso::with(['sesiones', 'docentes'])
            ->where('estado', '!=', 'finalizado')
            ->when($excluirCursoId, fn ($q) => $q->where('id', '!=', $excluirCursoId))
            ->whereHas('docentes', fn ($q) => $q->where('users.id', $docenteId))
            // Solo grupos cuyas fechas se cruzan con las de este
            ->whereDate('fecha_inicio', '<=', $fin)
            ->whereDate('fecha_fin', '>=', $inicio)
            ->get();

        foreach ($sesiones as $a) {
            $ai = CursoSesion::aMinutos($a['hora_inicio']);
            $af = CursoSesion::aMinutos($a['hora_fin']);
            foreach ($otros as $otro) {
                foreach ($otro->sesiones as $b) {
                    if ($b->dia === $a['dia'] && $ai < CursoSesion::aMinutos($b->hora_fin) && CursoSesion::aMinutos($b->hora_inicio) < $af) {
                        $nombre = $otro->docentes->first()?->name ?? 'El docente';

                        return "{$nombre} ya tiene clase el ".mb_strtolower($a['dia'])." de {$b->inicio()} a {$b->fin()} con el {$otro->nombre} ({$otro->codigo}).";
                    }
                }
            }
        }

        return null;
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, ',', '.'), '0'), ',');
    }
}
