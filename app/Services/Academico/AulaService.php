<?php

namespace App\Services\Academico;

use App\Models\Academico\Actividad;
use App\Models\Academico\Curso;
use App\Models\Academico\Entrega;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Opcion;
use App\Models\Academico\Pregunta;
use App\Models\Academico\RespuestaEstudiante;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Aula virtual (24-11-2026): el docente crea evaluaciones de selección múltiple y actividades para su grupo, cada una
 * con un módulo de la malla; el estudiante con matrícula activa en el grupo las responde o entrega. Reglas acordadas
 * con el usuario:
 * - Una evaluación se responde una sola vez y hasta su fecha límite; la nota sale al enviar (Evaluacion::nota) y las
 *   respuestas correctas solo se muestran después del cierre. El docente la edita o elimina mientras nadie la responda.
 * - Una actividad recibe un archivo (PDF, Word, JPG o PNG, máx. 10 MB) y se cambia hasta la fecha límite o hasta que
 *   el docente la califique (1,0 a 5,0 con un decimal y un comentario). Se elimina solo sin entregas.
 * - Sus notas son aparte: no calculan la nota del módulo, que el docente sigue poniendo en Notas.
 * Todo se vuelve a revisar aquí con la fila bloqueada (dos envíos a la vez, M13).
 */
class AulaService
{
    public const DISCO = 'local';
    public const MAX_ARCHIVO_KB = 10240;
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    public function __construct(private CalificacionService $calificaciones) {}

    /* ── Estudiante ──────────────────────────────────────────────────────── */

    /** La matrícula activa del estudiante en ese grupo (solo con ella responde o entrega). */
    public function matriculaActivaEn(User $estudiante, Curso $curso): ?Matricula
    {
        return Matricula::where('estudiante_id', $estudiante->id)->where('curso_id', $curso->id)->where('estado', 'activo')->first();
    }

    /** Evaluaciones y actividades del grupo con lo que respondió o entregó el estudiante (forma de EST_DATOS). */
    public function paraEstudiante(User $estudiante, Curso $curso, CarbonInterface $ahora): array
    {
        $evaluaciones = Evaluacion::where('curso_id', $curso->id)
            ->with(['preguntas.opciones', 'respuestasEstudiante' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->orderBy('fecha_limite')->get()
            ->map(function (Evaluacion $ev) use ($ahora) {
                $cerrada = $ev->fecha_limite->lte($ahora);
                $resp = $ev->respuestasEstudiante;
                $total = $ev->preguntas->count();
                $aciertos = $resp->where('es_correcta', true)->count();

                return [
                    'id'          => $ev->id,
                    'tipo'        => 'evaluacion',
                    'titulo'      => $ev->titulo,
                    'descripcion' => (string) $ev->descripcion,
                    'moduloId'    => $ev->programa_modulo_id,
                    'abre'        => $ev->created_at->toDateString(),
                    'cierra'      => $ev->fecha_limite->format('Y-m-d H:i'),
                    'cerrada'     => $cerrada,
                    'preguntas'   => $ev->preguntas->map(fn (Pregunta $p) => [
                        'id'        => $p->id,
                        'enunciado' => $p->enunciado,
                        'opciones'  => $p->opciones->sortBy('id')->map(fn (Opcion $o) => ['id' => $o->id, 'texto' => $o->texto])->values()->all(),
                        // Las correctas solo salen después del cierre (así no se pasan las respuestas)
                        'correcta'  => $cerrada ? $p->opciones->firstWhere('es_correcta', true)?->id : null,
                    ])->values()->all(),
                    'respuesta'   => $resp->isEmpty() ? null : [
                        'fecha'    => $resp->min('created_at')->format('Y-m-d H:i'),
                        'marcadas' => $resp->mapWithKeys(fn (RespuestaEstudiante $r) => [$r->pregunta_id => $r->opcion_id])->all(),
                        'aciertos' => $aciertos,
                        'nota'     => Evaluacion::nota($aciertos, $total),
                    ],
                ];
            })->values()->all();

        $actividades = Actividad::where('curso_id', $curso->id)
            ->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->orderBy('fecha_limite')->get()
            ->map(function (Actividad $a) use ($ahora) {
                $e = $a->entregas->first();

                return [
                    'id'            => $a->id,
                    'tipo'          => 'actividad',
                    'titulo'        => $a->titulo,
                    'instrucciones' => (string) $a->instrucciones,
                    'moduloId'      => $a->programa_modulo_id,
                    'abre'          => $a->created_at->toDateString(),
                    'cierra'        => $a->fecha_limite->format('Y-m-d H:i'),
                    'cerrada'       => $a->fecha_limite->lte($ahora),
                    'entrega'       => $e ? $this->entregaDatos($e, route('estudiante.aula.archivo', $e)) : null,
                ];
            })->values()->all();

        return ['evaluaciones' => $evaluaciones, 'actividades' => $actividades];
    }

    /**
     * Guarda las respuestas (una por pregunta, todas) y devuelve [aciertos, preguntas, nota]. Se rechaza si ya la
     * respondió, si cerró o si ya no tiene matrícula activa en el grupo.
     */
    public function responder(User $estudiante, Evaluacion $evaluacion, array $marcadas): array
    {
        return DB::transaction(function () use ($estudiante, $evaluacion, $marcadas) {
            $ev = Evaluacion::whereKey($evaluacion->id)->lockForUpdate()->with(['preguntas.opciones', 'curso.programa'])->firstOrFail();
            if (! $this->matriculaActivaEn($estudiante, $ev->curso)) {
                throw ValidationException::withMessages(['respuestas' => 'Solo responde quien tiene la matrícula activa en el grupo.']);
            }
            if ($ev->cerrada()) {
                throw ValidationException::withMessages(['respuestas' => 'La evaluación cerró el '.$ev->fecha_limite->format('d/m/Y \a \l\a\s H:i').': ya no recibe respuestas.']);
            }
            if (RespuestaEstudiante::where('evaluacion_id', $ev->id)->where('estudiante_id', $estudiante->id)->exists()) {
                throw ValidationException::withMessages(['respuestas' => 'Ya respondiste esta evaluación. Solo se responde una vez.']);
            }

            $aciertos = 0;
            foreach ($ev->preguntas as $i => $p) {
                $opcion = $p->opciones->firstWhere('id', (int) ($marcadas[$p->id] ?? 0));
                if (! $opcion) {
                    throw ValidationException::withMessages(['respuestas' => 'Te falta responder la pregunta '.($i + 1).'.']);
                }
                RespuestaEstudiante::create([
                    'evaluacion_id' => $ev->id, 'estudiante_id' => $estudiante->id, 'pregunta_id' => $p->id,
                    'opcion_id' => $opcion->id, 'es_correcta' => $opcion->es_correcta,
                ]);
                $aciertos += $opcion->es_correcta ? 1 : 0;
            }
            $total = $ev->preguntas->count();
            $nota = Evaluacion::nota($aciertos, $total);
            Auditoria::registrar('crear', $ev, sprintf('Respondió la evaluación «%s» de %s · %s: %d de %d (nota %s).',
                $ev->titulo, $ev->curso->programa->nombre, $ev->curso->nombre, $aciertos, $total, number_format($nota, 1, ',', '')), $ev->curso->sede_id);

            return [$aciertos, $total, $nota];
        });
    }

    /** Entrega o cambia el archivo de una actividad. Devuelve true si cambió uno que ya había entregado. */
    public function entregar(User $estudiante, Actividad $actividad, UploadedFile $archivo): bool
    {
        $viejo = null;
        $cambio = DB::transaction(function () use ($estudiante, $actividad, $archivo, &$viejo) {
            $a = Actividad::whereKey($actividad->id)->lockForUpdate()->with('curso.programa')->firstOrFail();
            if (! $this->matriculaActivaEn($estudiante, $a->curso)) {
                throw ValidationException::withMessages(['archivo' => 'Solo entrega quien tiene la matrícula activa en el grupo.']);
            }
            if ($a->cerrada()) {
                throw ValidationException::withMessages(['archivo' => 'La fecha límite ya pasó: esta actividad no recibe más entregas.']);
            }
            $e = Entrega::where('actividad_id', $a->id)->where('estudiante_id', $estudiante->id)->lockForUpdate()->first();
            if ($e && $e->calificacion !== null) {
                throw ValidationException::withMessages(['archivo' => 'Tu docente ya calificó esta entrega: no se puede cambiar.']);
            }
            $viejo = $e?->archivo_path;
            $ruta = $archivo->store("aula/{$a->curso->sede_id}/actividad-{$a->id}", self::DISCO);
            $datos = [
                'archivo_path'   => $ruta,
                'archivo_nombre' => mb_substr($archivo->getClientOriginalName(), 0, 150),
                'archivo_tamano' => $archivo->getSize(),
                'fecha_entrega'  => now(),
            ];
            $e ? $e->update($datos) : Entrega::create($datos + ['actividad_id' => $a->id, 'estudiante_id' => $estudiante->id]);
            Auditoria::registrar($e ? 'editar' : 'crear', $a, sprintf('%s la actividad «%s» de %s · %s.',
                $e ? 'Cambió el archivo de' : 'Entregó', $a->titulo, $a->curso->programa->nombre, $a->curso->nombre), $a->curso->sede_id);

            return (bool) $e;
        });
        // El archivo anterior se borra solo cuando el nuevo ya quedó guardado
        if ($viejo) {
            Storage::disk(self::DISCO)->delete($viejo);
        }

        return $cambio;
    }

    /* ── Docente ─────────────────────────────────────────────────────────── */

    /** Grupos del docente con su malla, sus estudiantes activos y lo que tiene en el Aula (forma de docente/aula.js). */
    public function paraDocente(User $docente, Collection $cursos, CarbonInterface $ahora): array
    {
        $ids = $cursos->pluck('id');
        $evaluaciones = Evaluacion::whereIn('curso_id', $ids)->with(['preguntas.opciones', 'respuestasEstudiante'])->orderBy('fecha_limite')->get()->groupBy('curso_id');
        $actividades = Actividad::whereIn('curso_id', $ids)->with('entregas')->orderBy('fecha_limite')->get()->groupBy('curso_id');

        return $cursos->map(function (Curso $c) use ($evaluaciones, $actividades, $ahora) {
            $estudiantes = $c->matriculas->where('estado', 'activo')->sortBy(fn (Matricula $m) => $m->estudiante->apellidos.' '.$m->estudiante->nombres)
                ->map(fn (Matricula $m) => ['id' => $m->estudiante_id, 'nombre' => trim($m->estudiante->nombres.' '.$m->estudiante->apellidos)])->values();

            return [
                'id'          => $c->id,
                'codigo'      => $c->codigo,
                'nombre'      => $c->nombre,
                'programa'    => $c->programa->nombre,
                'jornada'     => $c->jornada,
                'estado'      => $c->estado,
                'soloConsulta' => $c->estado === 'finalizado',
                'modulos'     => collect($this->calificaciones->fechasModulos($c, $ahora))
                    ->map(fn (array $m) => array_intersect_key($m, array_flip(['id', 'nombre', 'ini', 'fin', 'estado'])))->values()->all(),
                'estudiantes' => $estudiantes->all(),
                'evaluaciones' => ($evaluaciones->get($c->id) ?? collect())->map(fn (Evaluacion $ev) => [
                    'id'          => $ev->id,
                    'moduloId'    => $ev->programa_modulo_id,
                    'titulo'      => $ev->titulo,
                    'descripcion' => (string) $ev->descripcion,
                    'cierra'      => $ev->fecha_limite->format('Y-m-d H:i'),
                    'cerrada'     => $ev->fecha_limite->lte($ahora),
                    'creada'      => $ev->created_at->format('Y-m-d H:i'),
                    'preguntas'   => $ev->preguntas->map(fn (Pregunta $p) => [
                        'id'        => $p->id,
                        'enunciado' => $p->enunciado,
                        'opciones'  => $p->opciones->sortBy('id')->map(fn (Opcion $o) => ['id' => $o->id, 'texto' => $o->texto, 'correcta' => $o->es_correcta])->values()->all(),
                    ])->values()->all(),
                    'respuestas'  => (object) $ev->respuestasEstudiante->groupBy('estudiante_id')->map(fn (Collection $rs) => [
                        'fecha'    => $rs->min('created_at')->format('Y-m-d H:i'),
                        'aciertos' => $rs->where('es_correcta', true)->count(),
                        'nota'     => Evaluacion::nota($rs->where('es_correcta', true)->count(), $ev->preguntas->count()),
                    ])->all(),
                ])->values()->all(),
                'actividades' => ($actividades->get($c->id) ?? collect())->map(fn (Actividad $a) => [
                    'id'            => $a->id,
                    'moduloId'      => $a->programa_modulo_id,
                    'titulo'        => $a->titulo,
                    'instrucciones' => (string) $a->instrucciones,
                    'cierra'        => $a->fecha_limite->format('Y-m-d H:i'),
                    'cerrada'       => $a->fecha_limite->lte($ahora),
                    'creada'        => $a->created_at->format('Y-m-d H:i'),
                    'entregas'      => (object) $a->entregas->keyBy('estudiante_id')
                        ->map(fn (Entrega $e) => $this->entregaDatos($e, route('docente.aula.archivo', $e)) + ['id' => $e->id])->all(),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Crea o edita una evaluación: título, descripción, módulo, fecha límite y preguntas [{enunciado, opciones[{texto,
     * correcta}]}] ya validadas (EvaluacionRequest). Una vez que alguien respondió, no se edita.
     */
    public function guardarEvaluacion(User $docente, Curso $curso, ?Evaluacion $evaluacion, array $datos): Evaluacion
    {
        return DB::transaction(function () use ($docente, $curso, $evaluacion, $datos) {
            if ($evaluacion) {
                $evaluacion = Evaluacion::whereKey($evaluacion->id)->lockForUpdate()->firstOrFail();
                if (RespuestaEstudiante::where('evaluacion_id', $evaluacion->id)->exists()) {
                    throw ValidationException::withMessages(['titulo' => 'Alguien ya respondió esta evaluación: no se puede cambiar.']);
                }
            }
            $campos = [
                'titulo' => $datos['titulo'], 'descripcion' => $datos['descripcion'] ?? null,
                'programa_modulo_id' => $datos['modulo_id'], 'fecha_limite' => $datos['cierra'],
            ];
            $ev = $evaluacion ?? new Evaluacion(['curso_id' => $curso->id]);
            $ev->fill($campos);
            $ev->docente_id = $docente->id;
            $ev->save();

            // Las preguntas se reemplazan enteras (nadie ha respondido todavía)
            $ev->preguntas()->each(fn (Pregunta $p) => $p->delete());
            foreach (array_values($datos['preguntas']) as $i => $p) {
                $pregunta = Pregunta::create(['evaluacion_id' => $ev->id, 'enunciado' => $p['enunciado'], 'orden' => $i + 1]);
                foreach (array_values($p['opciones']) as $o) {
                    Opcion::create(['pregunta_id' => $pregunta->id, 'texto' => $o['texto'], 'es_correcta' => (bool) $o['correcta']]);
                }
            }
            Auditoria::registrar($evaluacion ? 'editar' : 'crear', $curso, sprintf('%s la evaluación «%s» (%d preguntas) en %s · %s.',
                $evaluacion ? 'Editó' : 'Publicó', $ev->titulo, count($datos['preguntas']), $curso->programa->nombre, $curso->nombre));

            return $ev;
        });
    }

    public function eliminarEvaluacion(Evaluacion $evaluacion): void
    {
        DB::transaction(function () use ($evaluacion) {
            $ev = Evaluacion::whereKey($evaluacion->id)->lockForUpdate()->with('curso.programa')->firstOrFail();
            if (RespuestaEstudiante::where('evaluacion_id', $ev->id)->exists()) {
                throw ValidationException::withMessages(['evaluacion' => 'Alguien ya respondió esta evaluación: no se puede eliminar.']);
            }
            Auditoria::registrar('eliminar', $ev->curso, sprintf('Eliminó la evaluación «%s» de %s · %s.', $ev->titulo, $ev->curso->programa->nombre, $ev->curso->nombre));
            $ev->delete();
        });
    }

    /** Crea o edita una actividad (título, instrucciones, módulo y fecha límite ya validados por ActividadRequest). */
    public function guardarActividad(User $docente, Curso $curso, ?Actividad $actividad, array $datos): Actividad
    {
        return DB::transaction(function () use ($docente, $curso, $actividad, $datos) {
            $a = $actividad ? Actividad::whereKey($actividad->id)->lockForUpdate()->firstOrFail() : new Actividad(['curso_id' => $curso->id]);
            $a->fill(['titulo' => $datos['titulo'], 'instrucciones' => $datos['instrucciones'], 'programa_modulo_id' => $datos['modulo_id'], 'fecha_limite' => $datos['cierra']]);
            $a->docente_id = $docente->id;
            $a->save();
            Auditoria::registrar($actividad ? 'editar' : 'crear', $curso, sprintf('%s la actividad «%s» en %s · %s (se entrega hasta el %s).',
                $actividad ? 'Editó' : 'Publicó', $a->titulo, $curso->programa->nombre, $curso->nombre, $a->fecha_limite->format('d/m/Y H:i')));

            return $a;
        });
    }

    public function eliminarActividad(Actividad $actividad): void
    {
        DB::transaction(function () use ($actividad) {
            $a = Actividad::whereKey($actividad->id)->lockForUpdate()->with('curso.programa')->firstOrFail();
            if (Entrega::where('actividad_id', $a->id)->exists()) {
                throw ValidationException::withMessages(['actividad' => 'Alguien ya entregó esta actividad: no se puede eliminar.']);
            }
            Auditoria::registrar('eliminar', $a->curso, sprintf('Eliminó la actividad «%s» de %s · %s.', $a->titulo, $a->curso->programa->nombre, $a->curso->nombre));
            $a->delete();
        });
    }

    /** Califica (o corrige la calificación de) una entrega: nota 1,0 a 5,0 con un decimal y un comentario opcional. */
    public function calificar(User $docente, Entrega $entrega, float $nota, ?string $comentario): Entrega
    {
        return DB::transaction(function () use ($docente, $entrega, $nota, $comentario) {
            $e = Entrega::whereKey($entrega->id)->lockForUpdate()->with(['actividad.curso.programa', 'estudiante'])->firstOrFail();
            $corrige = $e->calificacion !== null;
            $e->update(['calificacion' => $nota, 'comentario_docente' => $comentario, 'calificado_en' => now(), 'calificado_por' => $docente->id]);
            $c = $e->actividad->curso;
            Auditoria::registrar('editar', $c, sprintf('%s la entrega de %s en «%s» (%s · %s): %s.',
                $corrige ? 'Corrigió la nota de' : 'Calificó', trim($e->estudiante->nombres.' '.$e->estudiante->apellidos), $e->actividad->titulo,
                $c->programa->nombre, $c->nombre, number_format($nota, 1, ',', '')));

            return $e;
        });
    }

    /** Los datos de una entrega que ven el estudiante y el docente. */
    private function entregaDatos(Entrega $e, string $url): array
    {
        $nombre = $e->archivo_nombre ?: basename((string) $e->archivo_path);

        return [
            'archivo'    => [
                'nombre' => $nombre,
                'tamano' => (int) $e->archivo_tamano,
                'tipo'   => strtolower(pathinfo($nombre, PATHINFO_EXTENSION)),
                'url'    => $url,
            ],
            'fecha'      => $e->fecha_entrega?->format('Y-m-d H:i'),
            'nota'       => $e->calificacion !== null ? round((float) $e->calificacion, 1) : null,
            'fechaNota'  => $e->calificado_en?->toDateString(),
            'comentario' => $e->comentario_docente,
        ];
    }
}
