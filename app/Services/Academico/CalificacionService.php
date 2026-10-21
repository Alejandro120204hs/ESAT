<?php

namespace App\Services\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Curso;
use App\Models\Academico\CursoSesion;
use App\Models\Academico\ProgramaModulo;
use App\Models\Finanzas\Matricula;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reglas de calificaciones (panel Admin › Calificaciones, solo consulta; la nota la pone el docente).
 * - Nota por módulo de la malla (programa_modulos), de 1.0 a 5.0; aprueba 3.0. Una sola habilitación
 *   por módulo perdido (ver Calificacion::estado()).
 * - Las fechas de cada módulo no se escriben: las clases del grupo (horario, inicio → fin) se reparten
 *   en orden según las horas de cada módulo (fechasModulos()).
 * - Un módulo terminado sin nota es pendiente del docente, salvo que haya terminado antes de que el
 *   estudiante se matriculara (no_cursado: no lo vio en este grupo).
 * - Graduar exige todos los módulos de la malla aprobados (pendientesParaGraduar()).
 */
class CalificacionService
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /**
     * Módulos de la malla del grupo con sus fechas calculadas y su estado
     * (terminado · en_curso · por_iniciar). Espera cargadas programa.modulos y sesiones.
     */
    public function fechasModulos(Curso $curso, ?CarbonInterface $ahora = null): array
    {
        $hoy = ($ahora ?? now())->copy()->startOfDay();
        $malla = $curso->programa->modulos;
        $total = (int) $malla->sum('horas');

        // Días de clase entre el inicio y el fin del grupo, según su horario
        $dias = $curso->sesiones->map(fn (CursoSesion $s) => array_search($s->dia, CursoSesion::DIAS, true) + 1)->unique()->all();
        $fin = Curso::finPara($curso->fecha_inicio, $curso->programa);
        $clases = [];
        for ($d = $curso->fecha_inicio->copy()->startOfDay(); $d->lt($fin); $d->addDay()) {
            if (in_array($d->dayOfWeekIso, $dias, true)) {
                $clases[] = $d->copy();
            }
        }
        $n = count($clases);
        if (! $n || ! $total) {
            return [];
        }

        // Cada módulo ocupa, en orden, la parte de las clases que le toca por sus horas
        $acum = 0;
        $out = [];
        foreach ($malla as $m) {
            $desde = min((int) floor($n * $acum / $total), $n - 1);
            $acum += $m->horas;
            $hasta = min(max($desde, (int) floor($n * $acum / $total) - 1), $n - 1);
            $ini = $clases[$desde];
            $finM = $clases[$hasta];
            $out[] = [
                'id'      => $m->id,
                'nombre'  => $m->nombre,
                'horas'   => $m->horas,
                'ini'     => $ini->toDateString(),
                'fin'     => $finM->toDateString(),
                'ini_txt' => $this->corta($ini, $hoy),
                'fin_txt' => $this->corta($finM, $hoy),
                'estado'  => $finM->lt($hoy) ? 'terminado' : ($ini->lte($hoy) ? 'en_curso' : 'por_iniciar'),
            ];
        }

        return $out;
    }

    /**
     * Módulos de la malla que la matrícula todavía no tiene aprobados (nombres, en orden).
     * Graduar exige que no quede ninguno.
     */
    public function pendientesParaGraduar(Matricula $matricula): array
    {
        $matricula->loadMissing(['programa.modulos', 'calificaciones']);
        $aprobados = $matricula->calificaciones->filter->aprobada()->pluck('programa_modulo_id')->flip();

        return $matricula->programa->modulos
            ->reject(fn (ProgramaModulo $m) => $aprobados->has($m->id))
            ->pluck('nombre')->values()->all();
    }

    /** Todo lo que muestra el panel Admin › Calificaciones de una sede. */
    public function resumenSede(int $sedeId, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();
        $hoy = $ahora->copy()->startOfDay();

        // Grupos que ya empezaron, con horario y con malla (los que tienen algo que calificar)
        $cursos = Curso::deSede($sedeId)
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereHas('sesiones')
            ->whereHas('programa.modulos')
            ->with([
                'programa.escuela', 'programa.modulos', 'sesiones', 'docentes',
                'matriculas' => fn ($q) => $q->whereIn('estado', ['activo', 'graduado'])->with(['estudiante', 'calificaciones']),
            ])
            ->get()
            ->sortBy(fn (Curso $c) => [$c->programa->nombre, $c->nombre])
            ->values();

        $asistencia = $this->asistenciaPorEstudiante($cursos->pluck('id')->all());

        $grupos = [];
        $estudiantes = [];
        foreach ($cursos as $curso) {
            $modulos = $this->fechasModulos($curso, $ahora);
            if (! $modulos) {
                continue;
            }
            $fin = Curso::finPara($curso->fecha_inicio, $curso->programa);
            $terminado = $curso->estado === 'finalizado' || $fin->lte($hoy);

            $suyos = $curso->matriculas
                ->map(fn (Matricula $m) => $this->filaEstudiante($m, $curso, $modulos, $terminado, $asistencia))
                ->sortBy('apellidos')->values();

            // Promedio y pendientes de cada módulo, con los estudiantes del grupo
            foreach ($modulos as $i => &$mod) {
                $finales = $suyos->map(fn ($e) => $e['notas'][$i] === null ? null : ($e['notas'][$i]['h'] ?? $e['notas'][$i]['n']))->filter(fn ($v) => $v !== null);
                $mod['prom'] = $finales->isEmpty() ? null : round($finales->avg(), 1);
                $mod['sin_nota'] = $suyos->contains(fn ($e) => $e['estados'][$i] === 'sin_nota');
            }
            unset($mod);

            $docente = $curso->docente();
            $actual = collect($modulos)->search(fn ($m) => $m['estado'] === 'en_curso');
            $proms = $suyos->pluck('prom')->filter(fn ($v) => $v !== null);

            $grupos[] = [
                'id'            => $curso->id,
                'codigo'        => $curso->codigo,
                'programa'      => $curso->programa->nombre,
                'grupo'         => $curso->nombre,
                'jornada'       => $curso->jornada,
                'sigla'         => mb_strtolower($curso->programa->escuela?->sigla ?? ''),
                'escuela'       => $curso->programa->escuela?->nombre,
                'docente'       => $docente ? $docente->nombres.' '.$docente->apellidos : 'Sin docente asignado',
                'telefono'      => $docente ? preg_replace('/\D/', '', (string) $docente->telefono) : '',
                'horario'       => $this->horario($curso),
                'inicio_txt'    => $this->corta($curso->fecha_inicio, $hoy),
                'fin_txt'       => $this->corta($fin, $hoy),
                'terminado'     => $terminado,
                'modulos'       => $modulos,
                'actual'        => $actual === false ? null : $actual,
                'terminados'    => collect($modulos)->where('estado', 'terminado')->count(),
                'activos'       => $suyos->count(),
                'prom'          => $proms->isEmpty() ? null : round($proms->avg(), 1),
                'por_habilitar' => $suyos->where('situacion', 'habilitar')->count(),
                'reprobados'    => $suyos->where('situacion', 'reprobado')->count(),
                'listos'        => $suyos->where('situacion', 'listo')->count(),
                'graduados'     => $suyos->where('situacion', 'graduado')->count(),
                'sin_nota'      => collect($modulos)->where('sin_nota', true)->keys()->values()->all(),
            ];
            array_push($estudiantes, ...$suyos->all());
        }

        $grupos = collect($grupos);
        $estudiantes = collect($estudiantes);
        $proms = $estudiantes->pluck('prom')->filter(fn ($v) => $v !== null);
        $conSinNota = $grupos->filter(fn ($g) => count($g['sin_nota']));

        return [
            'grupos'      => $grupos->values(),
            'estudiantes' => $estudiantes->values(),
            'resumen'     => [
                'prom_sede'     => $proms->isEmpty() ? null : round($proms->avg(), 1),
                'listos'        => $estudiantes->where('situacion', 'listo')->count(),
                'por_habilitar' => $estudiantes->where('situacion', 'habilitar')->count(),
                'reprobados'    => $estudiantes->where('situacion', 'reprobado')->count(),
                'mod_sin_nota'  => $grupos->sum(fn ($g) => count($g['sin_nota'])),
                'doc_sin_nota'  => $conSinNota->pluck('docente')->unique()->count(),
            ],
            'jornadas'    => $grupos->pluck('jornada')->unique()->sort()->values(),
            'hoy'         => $hoy->toDateString(),
        ];
    }

    /**
     * Una fila por matrícula del grupo. notas[i] = {n, h} o null; estados[i] = aprobado · habilito ·
     * por_habilitar · reprobado · sin_nota · no_cursado · en_curso · por_iniciar.
     */
    private function filaEstudiante(Matricula $m, Curso $curso, array $modulos, bool $terminado, array $asistencia): array
    {
        $e = $m->estudiante;
        $porModulo = $m->calificaciones->keyBy('programa_modulo_id');
        $desde = $m->fecha_inicio->toDateString();

        $notas = []; $estados = []; $finales = [];
        $aprob = 0; $porHab = 0; $reprob = 0;
        foreach ($modulos as $mod) {
            $c = $porModulo->get($mod['id']);
            if ($c) {
                $notas[] = ['n' => $c->nota, 'h' => $c->habilitacion];
                $estado = $c->estado();
                $finales[] = $c->notaFinal();
                $aprob += in_array($estado, ['aprobado', 'habilito'], true) ? 1 : 0;
                $porHab += $estado === 'por_habilitar' ? 1 : 0;
                $reprob += $estado === 'reprobado' ? 1 : 0;
            } else {
                $notas[] = null;
                $estado = $mod['estado'] !== 'terminado' ? $mod['estado'] : ($mod['fin'] < $desde ? 'no_cursado' : 'sin_nota');
            }
            $estados[] = $estado;
        }
        $total = count($modulos);
        $a = $asistencia[$curso->id][$e->id] ?? ['si' => 0, 'no' => 0];

        return [
            'id'            => $m->id,
            'estudiante_id' => $e->id,
            'grupo_id'      => $curso->id,
            'nombres'       => $e->nombres,
            'apellidos'     => $e->apellidos,
            'tipo_doc'      => $e->tipo_documento,
            'documento'     => $e->numero_documento,
            'telefono'      => preg_replace('/\D/', '', (string) $e->telefono),
            'asistencia'    => AsistenciaService::porcentaje($a['si'], $a['no']),
            'notas'         => $notas,
            'estados'       => $estados,
            'prom'          => $finales ? round(array_sum($finales) / count($finales), 1) : null,
            'aprobados'     => $aprob,
            'total'         => $total,
            'por_habilitar' => $porHab,
            'reprobados'    => $reprob,
            'situacion'     => match (true) {
                $m->estado === 'graduado'          => 'graduado',
                $reprob > 0                        => 'reprobado',
                $porHab > 0                        => 'habilitar',
                $terminado && $aprob === $total    => 'listo',
                (bool) $finales                    => 'al_dia',
                default                            => 'sin_notas',
            },
        ];
    }

    /** curso_id => estudiante_id => {si: presente + tarde, no: ausente} (misma regla que Asistencia). */
    private function asistenciaPorEstudiante(array $cursoIds): array
    {
        $out = [];
        Asistencia::whereIn('curso_id', $cursoIds)
            ->selectRaw('curso_id, estudiante_id, estado, count(*) as total')
            ->groupBy('curso_id', 'estudiante_id', 'estado')
            ->get()
            ->each(function ($r) use (&$out) {
                $out[$r->curso_id][$r->estudiante_id] ??= ['si' => 0, 'no' => 0];
                if (in_array($r->estado, ['presente', 'tarde'], true)) {
                    $out[$r->curso_id][$r->estudiante_id]['si'] += (int) $r->total;
                } elseif ($r->estado === 'ausente') {
                    $out[$r->curso_id][$r->estudiante_id]['no'] += (int) $r->total;
                }
            });

        return $out;
    }

    /** "Lun, Mié, Vie · 08:00–12:00", una línea por rango de horas (espera cargadas las sesiones). */
    private function horario(Curso $curso): string
    {
        return $curso->sesiones
            ->groupBy(fn (CursoSesion $s) => $s->inicio().'–'.$s->fin())
            ->map(fn (Collection $ses, string $horas) => $ses->map(fn ($s) => Curso::DIA_CORTO[$s->dia])->implode(', ').' · '.$horas)
            ->implode("\n");
    }

    private function corta(CarbonInterface $f, CarbonInterface $hoy): string
    {
        return $f->day.' '.self::MESES[$f->month - 1].($f->year !== $hoy->year ? ' '.$f->year : '');
    }
}
