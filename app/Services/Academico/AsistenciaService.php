<?php

namespace App\Services\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Curso;
use App\Models\Academico\CursoSesion;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reglas de asistencia (panel Admin › Asistencia, solo consulta; la toma el docente).
 * - Las clases de un grupo son los días de su horario entre su inicio y su fin; la de hoy
 *   cuenta cuando ya terminó.
 * - Una clase "sin registrar" es una clase ya dictada sin ningún registro del grupo: no cuenta
 *   en el porcentaje de nadie.
 * - Porcentaje = (presente + tarde) / (presente + tarde + ausente). La excusa no cuenta como falla.
 * - Es informativa: no bloquea matrículas, pagos ni grados. MINIMO es solo una referencia.
 * - De cada estudiante cuentan las clases desde su matrícula hasta que se aplazó o retiró.
 */
class AsistenciaService
{
    public const MINIMO = 80;

    private const LETRA = ['presente' => 'P', 'tarde' => 'T', 'ausente' => 'A', 'excusa' => 'E'];

    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** Una sola regla de porcentaje para Asistencia, Estudiantes, Acudientes y Matrículas. null = sin clases que contar. */
    public static function porcentaje(int $asistio, int $falto): ?int
    {
        $base = $asistio + $falto;

        return $base ? (int) round($asistio * 100 / $base) : null;
    }

    /** bien (90 % o más) · vigilar (80 a 89 %) · fallas (bajo el mínimo) · sin_datos. */
    public static function situacion(?int $pct): string
    {
        return match (true) {
            $pct === null       => 'sin_datos',
            $pct >= 90          => 'bien',
            $pct >= self::MINIMO => 'vigilar',
            default             => 'fallas',
        };
    }

    /**
     * Clases ya dictadas del grupo ('Y-m-d', de la más vieja a la más reciente).
     * Espera cargadas sesiones y programa.
     */
    public function fechasDictadas(Curso $curso, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();
        $hoy = $ahora->copy()->startOfDay();
        $finDia = $this->finPorDia($curso);
        if (! $finDia) {
            return [];
        }
        $fin = Curso::finPara($curso->fecha_inicio, $curso->programa);

        $fechas = [];
        for ($d = $curso->fecha_inicio->copy()->startOfDay(); $d->lt($fin) && $d->lte($hoy); $d->addDay()) {
            if (! isset($finDia[$d->dayOfWeekIso])) {
                continue;
            }
            if ($d->equalTo($hoy) && $ahora->lt($hoy->copy()->setTimeFromTimeString($finDia[$d->dayOfWeekIso]))) {
                continue;
            }
            $fechas[] = $d->toDateString();
        }

        return $fechas;
    }

    /**
     * Todo lo que muestra el panel Admin › Asistencia de una sede: grupos vigentes con su planilla,
     * estudiantes, docentes y el resumen de arriba.
     */
    public function resumenSede(int $sedeId, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();
        $hoy = $ahora->copy()->startOfDay();

        $cursos = Curso::deSede($sedeId)
            ->where('estado', '!=', 'finalizado')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereHas('sesiones')
            ->with([
                'programa.escuela',
                'sesiones',
                'docentes.docentePerfil.escuela',
                'matriculas' => fn ($q) => $q->where('estado', '!=', 'anulado')->with('estudiante.acudientes'),
            ])
            ->get()
            ->sortBy(fn (Curso $c) => [$c->programa->nombre, $c->nombre])
            ->values();

        // Registros del grupo por estudiante y fecha
        $indice = [];
        Asistencia::whereIn('curso_id', $cursos->pluck('id'))
            ->get(['curso_id', 'estudiante_id', 'fecha', 'estado', 'motivo'])
            ->each(function (Asistencia $a) use (&$indice) {
                $indice[$a->curso_id][$a->fecha->toDateString()][$a->estudiante_id] = $a;
            });

        $grupos = [];
        $estudiantes = [];
        foreach ($cursos as $curso) {
            $fechas    = $this->fechasDictadas($curso, $ahora);
            $delGrupo  = $indice[$curso->id] ?? [];
            $sinTomar  = array_values(array_filter($fechas, fn ($f) => ! isset($delGrupo[$f])));
            $docente   = $curso->docente();
            $sumAsis = 0; $sumFalta = 0; $activos = 0; $bajo = 0;

            foreach ($curso->matriculas as $m) {
                $fila = $this->filaEstudiante($m, $curso, $fechas, $delGrupo);
                if (! $fila) {
                    continue;
                }
                $estudiantes[] = $fila;
                if ($m->estado === 'activo') {
                    $activos++;
                    $sumAsis += $fila['P'] + $fila['T'];
                    $sumFalta += $fila['A'];
                    $bajo += $fila['situacion'] === 'fallas' ? 1 : 0;
                }
            }

            $tomadas = count($fechas) - count($sinTomar);
            $ultima  = collect($fechas)->reject(fn ($f) => in_array($f, $sinTomar, true))->last();
            $dias    = $curso->sesiones->map(fn (CursoSesion $s) => Curso::DIA_CORTO[$s->dia])->implode(' · ');
            $horas   = $curso->sesiones->map(fn (CursoSesion $s) => $s->inicio().' – '.$s->fin())->unique()->implode(' / ');

            $grupos[] = [
                'id'         => $curso->id,
                'codigo'     => $curso->codigo,
                'programa'   => $curso->programa->nombre,
                'grupo'      => $curso->nombre,
                'escuela'    => $curso->programa->escuela?->nombre,
                'sigla'      => mb_strtolower($curso->programa->escuela?->sigla ?? ''),
                'jornada'    => $curso->jornada,
                'horario'    => $dias.'  '.$horas,
                'dias_txt'   => $dias,
                'horas_txt'  => $horas,
                'docente_id' => $docente?->id,
                'docente'    => $docente ? $docente->nombres.' '.$docente->apellidos : 'Sin docente asignado',
                'fechas'     => $fechas,
                'clases'     => count($fechas),
                'tomadas'    => $tomadas,
                'sin_tomar'  => $sinTomar,
                'pct'        => self::porcentaje($sumAsis, $sumFalta),
                'activos'    => $activos,
                'bajo80'     => $bajo,
                'hoy'        => $this->claseDeHoy($curso, $delGrupo, $ahora),
                'ultima_txt' => $ultima ? $this->corta($ultima) : null,
            ];
        }

        $grupos = collect($grupos);
        $estudiantes = collect($estudiantes);
        $docentes = $this->docentes($cursos, $grupos);
        $vigentes = $estudiantes->where('estado', 'activo');

        return [
            'grupos'      => $grupos->values(),
            'estudiantes' => $estudiantes->values(),
            'docentes'    => $docentes,
            'resumen'     => [
                'pct_sede'      => self::porcentaje($vigentes->sum(fn ($e) => $e['P'] + $e['T']), $vigentes->sum('A')),
                'hoy_total'     => $grupos->whereNotNull('hoy')->count(),
                'hoy_tomadas'   => $grupos->where('hoy', 'tomada')->count(),
                'hoy_mas_tarde' => $grupos->where('hoy', 'pendiente')->count(),
                'sin_tomar'     => $grupos->sum(fn ($g) => count($g['sin_tomar'])),
                'bajo_minimo'   => $vigentes->where('situacion', 'fallas')->count(),
                'docentes_sin'  => $docentes->where('sin_tomar', '>', 0)->count(),
            ],
            'hoy'         => $hoy->toDateString(),
        ];
    }

    /**
     * Una fila por matrícula del grupo. cod tiene una letra por clase dictada:
     * P T A E = lo registrado, '-' = el docente no registró, '.' = no le corresponde
     * (antes de matricularse o después de aplazarse/retirarse).
     */
    private function filaEstudiante(Matricula $m, Curso $curso, array $fechas, array $delGrupo): ?array
    {
        $e = $m->estudiante;
        $desde  = max($m->fecha_inicio->toDateString(), $curso->fecha_inicio->toDateString());
        $salida = $m->estado !== 'activo' ? $m->estado_fecha?->toDateString() : null;
        if ($salida && $salida <= $desde) {
            return null;
        }

        $cod = '';
        $motivos = [];
        foreach ($fechas as $i => $f) {
            if ($f < $desde || ($salida && $f >= $salida)) {
                $cod .= '.';
            } elseif (! isset($delGrupo[$f][$e->id])) {
                $cod .= '-';
            } else {
                $a = $delGrupo[$f][$e->id];
                $cod .= self::LETRA[$a->estado] ?? '-';
                if ($a->estado === 'excusa' && $a->motivo) {
                    $motivos[$i] = $a->motivo;
                }
            }
        }

        $c = count_chars($cod, 1);
        [$P, $T, $A, $E] = [$c[ord('P')] ?? 0, $c[ord('T')] ?? 0, $c[ord('A')] ?? 0, $c[ord('E')] ?? 0];
        $pct = self::porcentaje($P + $T, $A);

        // Faltas seguidas en las últimas clases registradas (una excusa o asistencia corta la racha)
        $racha = 0;
        for ($i = strlen($cod) - 1; $i >= 0; $i--) {
            if ($cod[$i] === '-' || $cod[$i] === '.') {
                continue;
            }
            if ($cod[$i] !== 'A') {
                break;
            }
            $racha++;
        }
        $pos = strrpos($cod, 'A');
        $acudiente = $e->acudientes->first();

        return [
            'id'               => $e->id,
            'nombres'          => $e->nombres,
            'apellidos'        => $e->apellidos,
            'tipo_doc'         => $e->tipo_documento,
            'documento'        => $e->numero_documento,
            'telefono'         => preg_replace('/\D/', '', (string) $e->telefono),
            'grupo_id'         => $curso->id,
            'estado'           => $m->estado,
            'estado_desde'     => $salida ? $this->corta($salida) : null,
            'cod'              => $cod,
            'motivos'          => (object) $motivos,
            'P' => $P, 'T' => $T, 'A' => $A, 'E' => $E,
            'pct'              => $pct,
            'situacion'        => self::situacion($pct),
            'racha'            => $racha,
            'ultima_falla'     => $pos !== false ? $fechas[$pos] : null,
            'ultima_falla_txt' => $pos !== false ? $this->corta($fechas[$pos]) : null,
            'acudiente'        => $acudiente ? [
                'nombre'     => $acudiente->nombres.' '.$acudiente->apellidos,
                'parentesco' => $acudiente->pivot->parentesco,
                'telefono'   => preg_replace('/\D/', '', (string) $acudiente->telefono),
            ] : null,
        ];
    }

    /** null = hoy no tiene clase · pendiente = todavía no termina · tomada · sin_tomar. */
    private function claseDeHoy(Curso $curso, array $delGrupo, CarbonInterface $ahora): ?string
    {
        $hoy = $ahora->copy()->startOfDay();
        $finDia = $this->finPorDia($curso);
        $fin = Curso::finPara($curso->fecha_inicio, $curso->programa);
        if (! isset($finDia[$hoy->dayOfWeekIso]) || $hoy->lt($curso->fecha_inicio->copy()->startOfDay()) || $hoy->gte($fin)) {
            return null;
        }
        if ($ahora->lt($hoy->copy()->setTimeFromTimeString($finDia[$hoy->dayOfWeekIso]))) {
            return 'pendiente';
        }

        return isset($delGrupo[$hoy->toDateString()]) ? 'tomada' : 'sin_tomar';
    }

    /** Docentes de los grupos listados, con lo que llevan registrado. */
    private function docentes(Collection $cursos, Collection $grupos): Collection
    {
        return $cursos->flatMap->docentes->unique('id')
            ->map(function (User $d) use ($grupos) {
                $suyos = $grupos->where('docente_id', $d->id)->values();
                $ultima = $suyos->flatMap(fn ($g) => array_diff($g['fechas'], $g['sin_tomar']))->sort()->last();
                $escuela = $d->docentePerfil?->escuela;

                return [
                    'id'         => $d->id,
                    'nombres'    => $d->nombres,
                    'apellidos'  => $d->apellidos,
                    'tipo_doc'   => $d->tipo_documento,
                    'documento'  => $d->numero_documento,
                    'telefono'   => preg_replace('/\D/', '', (string) $d->telefono),
                    'escuela'    => $escuela?->nombre,
                    'sigla'      => mb_strtolower($escuela?->sigla ?? ''),
                    'grupos'     => $suyos->pluck('id')->all(),
                    'clases'     => $suyos->sum('clases'),
                    'tomadas'    => $suyos->sum('tomadas'),
                    'sin_tomar'  => $suyos->sum(fn ($g) => count($g['sin_tomar'])),
                    'ultima_txt' => $ultima ? $this->corta($ultima) : null,
                ];
            })
            ->sortBy([['sin_tomar', 'desc'], ['apellidos', 'asc']])
            ->values();
    }

    /** Día ISO (1 = lunes) => hora en que termina la clase de ese día. */
    private function finPorDia(Curso $curso): array
    {
        $dias = array_flip(CursoSesion::DIAS);
        $out = [];
        foreach ($curso->sesiones as $s) {
            $n = $dias[$s->dia] + 1;
            $out[$n] = max($out[$n] ?? '00:00:00', $s->hora_fin);
        }

        return $out;
    }

    private function corta(string $fecha): string
    {
        $f = Carbon::parse($fecha);

        return $f->day.' '.self::MESES[$f->month - 1];
    }
}
