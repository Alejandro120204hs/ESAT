<?php

namespace App\Services\Academico;

use App\Models\Academico\Actividad;
use App\Models\Academico\Curso;
use App\Models\Academico\Entrega;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\EvaluacionIntento;
use App\Models\Academico\Material;
use App\Models\Academico\Opcion;
use App\Models\Academico\Pregunta;
use App\Models\Academico\RespuestaEstudiante;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Support\Archivos;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
 * - Sus notas son aparte: no calculan la nota del módulo, que el docente sigue poniendo en Notas (allí las ve como
 *   referencia, notasPorModulo).
 * - 27-11-2026: una actividad puede llevar un archivo de apoyo del docente (la guía) y cada grupo tiene material de clase
 *   (un archivo, un enlace o los dos, de un módulo o de todo el grupo). Los descarga quien tiene matrícula en el grupo.
 * - 27-11-2026: una evaluación puede tener tiempo límite (el docente elige los minutos). Las preguntas se ven al
 *   comenzarla (iniciar) y el tiempo cuenta desde ahí, hasta su cierre como máximo; si se acaba, se envía lo marcado y lo
 *   que falte cuenta como malo. Quien la comenzó y no envió a tiempo queda sin responder. Con respuestas se pueden
 *   corregir textos, el cierre y hasta la respuesta correcta (se recalculan las notas), pero no agregar ni quitar
 *   preguntas u opciones.
 * Todo se vuelve a revisar aquí con la fila bloqueada (dos envíos a la vez, M13).
 */
class AulaService
{
    public const DISCO = 'local';
    public const MAX_ARCHIVO_KB = 10240;
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    /** Lo que sube el docente (guía de una actividad o material de clase): también presentaciones y hojas de cálculo. */
    public const EXTENSIONES_DOCENTE = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    public function __construct(private CalificacionService $calificaciones) {}

    /* ── Estudiante ──────────────────────────────────────────────────────── */

    /**
     * La matrícula activa del estudiante en ese grupo (solo con ella responde o entrega). Al escribir se bloquea ($bloquear):
     * así dos envíos suyos a la vez se atienden en orden sin bloquear la evaluación o la actividad, que comparte con todo el
     * grupo (M13, auditoría ES16).
     */
    public function matriculaActivaEn(User $estudiante, Curso $curso, bool $bloquear = false): ?Matricula
    {
        $q = Matricula::where('estudiante_id', $estudiante->id)->where('curso_id', $curso->id)->where('estado', 'activo');

        return ($bloquear ? $q->lockForUpdate() : $q)->first();
    }

    /**
     * El Aula de una matrícula: la de su grupo actual y la de los grupos por los que pasó (historial de grupos), para que
     * un cambio de grupo no le borre sus resultados ni el material (ES12). Solo responde o entrega en su grupo actual y con
     * la matrícula activa (`participa`); lo demás es de consulta (ES9).
     */
    public function paraMatricula(User $estudiante, Matricula $m, CarbonInterface $ahora): array
    {
        $ids = $m->grupos->sortBy('desde')->pluck('curso_id')->push($m->curso_id)->filter()->unique()->values();
        $cursos = Curso::whereIn('id', $ids)->get()->keyBy('id');
        $todo = ['evaluaciones' => [], 'actividades' => [], 'materiales' => []];
        foreach ($ids as $id) {
            if (! $c = $cursos->get($id)) {
                continue;
            }
            $actual = (int) $id === (int) $m->curso_id;
            $parte = $this->paraEstudiante($estudiante, $c, $ahora, $actual && $m->estado === 'activo', $actual ? null : $c->nombre, $m->ventanasEn($c));
            foreach ($todo as $k => $v) {
                $todo[$k] = array_merge($v, $parte[$k]);
            }
        }

        return $todo;
    }

    /**
     * Evaluaciones y actividades del grupo con lo que respondió o entregó el estudiante (forma de EST_DATOS).
     * $participa = puede comenzar, responder y entregar aquí; $grupo = nombre del grupo si no es el actual; $ventanas =
     * cuándo estuvo en el grupo (Matricula::ventanasEn): `leToco` es false si estuvo abierta solo cuando no estaba (antes
     * de llegar o mientras estaba aplazado), y entonces no cuenta como pendiente ni como "no respondida".
     */
    public function paraEstudiante(User $estudiante, Curso $curso, CarbonInterface $ahora, bool $participa = true, ?string $grupo = null, ?array $ventanas = null): array
    {
        $leToco = fn ($abre, $cierra) => $ventanas === null || Matricula::cruzaVentanas($ventanas, $abre->toDateString(), $cierra->toDateString());
        $evaluaciones = Evaluacion::where('curso_id', $curso->id)
            ->with(['preguntas.opciones', 'respuestasEstudiante' => fn ($q) => $q->where('estudiante_id', $estudiante->id),
                    'intentos' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->orderBy('fecha_limite')->get()
            ->map(function (Evaluacion $ev) use ($ahora, $participa, $grupo, $leToco) {
                $cerrada = $ev->fecha_limite->lte($ahora);
                // Las correctas no salen mientras alguien todavía pueda enviar (la gracia después del cierre, ES1)
                $verCorrectas = $ev->correctasVisibles($ahora);
                $resp = $ev->respuestasEstudiante;
                $total = $ev->preguntas->count();
                $aciertos = $resp->where('es_correcta', true)->count();
                $intento = $ev->intentos->first();
                $termina = $intento && $ev->duracion_minutos ? $ev->terminaPara($intento->iniciada_en) : null;
                $expirada = $termina && $resp->isEmpty() && $ahora->gt($termina->copy()->addSeconds(Evaluacion::GRACIA_SEGUNDOS));
                // Con tiempo límite las preguntas se ven al comenzarla (o ya respondida, o después del cierre)
                $verPreguntas = ! $ev->duracion_minutos || $resp->isNotEmpty() || $cerrada || ($intento && ! $expirada);

                return [
                    'id'          => $ev->id,
                    'tipo'        => 'evaluacion',
                    'titulo'      => $ev->titulo,
                    'descripcion' => (string) $ev->descripcion,
                    'moduloId'    => $ev->programa_modulo_id,
                    'abre'        => $ev->created_at->toDateString(),
                    'cierra'      => $ev->fecha_limite->format('Y-m-d H:i'),
                    'cerrada'     => $cerrada,
                    'verCorrectas' => $verCorrectas,
                    'participa'   => $participa,
                    'leToco'      => $leToco($ev->created_at, $ev->fecha_limite),
                    'grupo'       => $grupo,
                    'duracion'    => $ev->duracion_minutos,
                    'numPreguntas' => $total,
                    'intento'     => $intento && $resp->isEmpty() ? [
                        'inicio'   => $intento->iniciada_en->format('Y-m-d H:i:s'),
                        'termina'  => $termina?->format('Y-m-d H:i:s'),
                        'restante' => $termina ? max(0, $ahora->diffInSeconds($termina, false)) : null,
                        'expirada' => $expirada,
                    ] : null,
                    'preguntas'   => ! $verPreguntas ? [] : $ev->preguntas->map(fn (Pregunta $p) => [
                        'id'        => $p->id,
                        'enunciado' => $p->enunciado,
                        'opciones'  => $p->opciones->sortBy('id')->map(fn (Opcion $o) => ['id' => $o->id, 'texto' => $o->texto])->values()->all(),
                        // Las correctas solo salen cuando ya nadie puede enviar (así no se pasan las respuestas)
                        'correcta'  => $verCorrectas ? $p->opciones->firstWhere('es_correcta', true)?->id : null,
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
            ->map(function (Actividad $a) use ($ahora, $participa, $grupo, $leToco) {
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
                    'participa'     => $participa,
                    'leToco'        => $leToco($a->created_at, $a->fecha_limite),
                    'grupo'         => $grupo,
                    'adjunto'       => $this->adjuntoDatos($a, route('estudiante.aula.guia', $a)),
                    'entrega'       => $e ? $this->entregaDatos($e, route('estudiante.aula.archivo', $e)) : null,
                ];
            })->values()->all();

        $materiales = Material::where('curso_id', $curso->id)->latest()->get()
            ->map(fn (Material $m) => $this->materialDatos($m, route('estudiante.aula.material', $m)) + ['grupo' => $grupo])->values()->all();

        return ['evaluaciones' => $evaluaciones, 'actividades' => $actividades, 'materiales' => $materiales];
    }

    /**
     * El estudiante tuvo (o tiene) matrícula en el grupo: puede descargar su guía y su material. Cuenta también el grupo
     * del que se cambió (historial de grupos de la matrícula, ES12).
     */
    public function estudioEn(User $estudiante, int $cursoId): bool
    {
        return Matricula::where('estudiante_id', $estudiante->id)->where('estado', '!=', 'anulado')
            ->where(fn ($q) => $q->where('curso_id', $cursoId)->orWhereHas('grupos', fn ($g) => $g->where('curso_id', $cursoId)))
            ->exists();
    }

    /**
     * Comienza una evaluación con tiempo límite: desde ahora corre su tiempo (hasta el cierre como máximo). Si ya la había
     * comenzado devuelve el mismo intento (abrirla otra vez no reinicia el reloj).
     */
    public function iniciar(User $estudiante, Evaluacion $evaluacion): EvaluacionIntento
    {
        // La hora en que llegó: esperar el turno de otro envío no la corre (ES16)
        $ahora = now();

        return DB::transaction(function () use ($estudiante, $evaluacion, $ahora) {
            $ev = $this->evaluacionParaResponder($estudiante, $evaluacion, ['curso.programa']);
            $this->puedeResponder($estudiante, $ev, $ahora);
            if (! $ev->duracion_minutos) {
                throw ValidationException::withMessages(['evaluacion' => 'Esta evaluación no tiene tiempo límite: respóndela cuando quieras.']);
            }
            $intento = EvaluacionIntento::where('evaluacion_id', $ev->id)->where('estudiante_id', $estudiante->id)->first();
            if ($intento) {
                return $intento;
            }
            $intento = EvaluacionIntento::create(['evaluacion_id' => $ev->id, 'estudiante_id' => $estudiante->id, 'iniciada_en' => $ahora]);
            Auditoria::registrar('crear', $ev, sprintf('Comenzó la evaluación «%s» de %s · %s (%d minutos).',
                $ev->titulo, $ev->curso->programa->nombre, $ev->curso->nombre, $ev->duracion_minutos), $ev->curso->sede_id);

            return $intento;
        });
    }

    /**
     * Guarda las respuestas y devuelve [aciertos, preguntas, nota]. Sin tiempo límite van todas; con tiempo límite debe
     * haberla comenzado y estar dentro de su tiempo (más la gracia del envío automático), y lo que no respondió cuenta
     * como malo. Se rechaza si ya la respondió, si cerró o si ya no tiene matrícula activa en el grupo.
     */
    public function responder(User $estudiante, Evaluacion $evaluacion, array $marcadas): array
    {
        // La hora en que llegó: esperar el turno de otro envío no la corre (ES16)
        $ahora = now();

        return DB::transaction(function () use ($estudiante, $evaluacion, $marcadas, $ahora) {
            $ev = $this->evaluacionParaResponder($estudiante, $evaluacion, ['preguntas.opciones', 'curso.programa']);
            $conTiempo = (bool) $ev->duracion_minutos;
            if ($conTiempo) {
                $intento = EvaluacionIntento::where('evaluacion_id', $ev->id)->where('estudiante_id', $estudiante->id)->first();
                if (! $intento) {
                    throw ValidationException::withMessages(['respuestas' => 'Primero comienza la evaluación: tiene tiempo límite.']);
                }
                if ($ahora->gt($ev->terminaPara($intento->iniciada_en)->addSeconds(Evaluacion::GRACIA_SEGUNDOS))) {
                    throw ValidationException::withMessages(['respuestas' => 'Se acabó tu tiempo para esta evaluación: ya no recibe respuestas.']);
                }
                // Dentro de su tiempo el cierre no lo corta (su tiempo ya termina en el cierre como máximo)
                $this->puedeResponder($estudiante, $ev, $ahora, false);
            } else {
                $this->puedeResponder($estudiante, $ev, $ahora);
            }

            $aciertos = 0;
            foreach ($ev->preguntas as $i => $p) {
                $opcion = $p->opciones->firstWhere('id', (int) ($marcadas[$p->id] ?? 0));
                if (! $opcion && ! $conTiempo) {
                    throw ValidationException::withMessages(['respuestas' => 'Te falta responder la pregunta '.($i + 1).'.']);
                }
                RespuestaEstudiante::create([
                    'evaluacion_id' => $ev->id, 'estudiante_id' => $estudiante->id, 'pregunta_id' => $p->id,
                    'opcion_id' => $opcion?->id, 'es_correcta' => (bool) $opcion?->es_correcta,
                ]);
                $aciertos += $opcion?->es_correcta ? 1 : 0;
            }
            $total = $ev->preguntas->count();
            $nota = Evaluacion::nota($aciertos, $total);
            Auditoria::registrar('crear', $ev, sprintf('Respondió la evaluación «%s» de %s · %s: %d de %d (nota %s).',
                $ev->titulo, $ev->curso->programa->nombre, $ev->curso->nombre, $aciertos, $total, number_format($nota, 1, ',', '')), $ev->curso->sede_id);

            return [$aciertos, $total, $nota];
        });
    }

    /**
     * La evaluación para comenzarla o responderla: primero se bloquea la matrícula del estudiante (orden de M13) y después
     * se lee la evaluación con un bloqueo compartido, que deja responder a todo el grupo a la vez y solo espera si el
     * docente la está editando (ES16).
     */
    private function evaluacionParaResponder(User $estudiante, Evaluacion $evaluacion, array $con): Evaluacion
    {
        $curso = Curso::findOrFail($evaluacion->curso_id);
        $this->bloquearEstudiante($estudiante);
        if (! $this->matriculaActivaEn($estudiante, $curso, bloquear: true)) {
            throw ValidationException::withMessages(['respuestas' => 'Solo responde quien tiene la matrícula activa en el grupo.']);
        }

        return Evaluacion::whereKey($evaluacion->id)->sharedLock()->with($con)->firstOrFail();
    }

    /** Lo que se exige para comenzar o responder: sin cerrar (si $cierre) y sin responder (la matrícula ya se revisó). */
    private function puedeResponder(User $estudiante, Evaluacion $ev, CarbonInterface $ahora, bool $cierre = true): void
    {
        if ($cierre && $ev->fecha_limite->lte($ahora)) {
            throw ValidationException::withMessages(['respuestas' => 'La evaluación cerró el '.$ev->fecha_limite->format('d/m/Y \a \l\a\s H:i').': ya no recibe respuestas.']);
        }
        if (RespuestaEstudiante::where('evaluacion_id', $ev->id)->where('estudiante_id', $estudiante->id)->exists()) {
            throw ValidationException::withMessages(['respuestas' => 'Ya respondiste esta evaluación. Solo se responde una vez.']);
        }
    }

    /**
     * Entrega o cambia el archivo de una actividad. Devuelve true si cambió uno que ya había entregado. La fecha límite se
     * compara con la hora en que llegó la entrega, el archivo se guarda antes de bloquear nada y se bloquea la matrícula
     * del estudiante, no la actividad que comparte con el grupo (ES16). El archivo anterior se borra solo cuando la
     * petición confirma (ES13).
     */
    public function entregar(User $estudiante, Actividad $actividad, UploadedFile $archivo): bool
    {
        $ahora = now();
        $curso = Curso::findOrFail($actividad->curso_id);
        $ruta = $archivo->store("aula/{$curso->sede_id}/actividad-{$actividad->id}", self::DISCO);
        $viejo = null;
        try {
            $cambio = DB::transaction(function () use ($estudiante, $actividad, $archivo, $curso, $ruta, $ahora, &$viejo) {
                $this->bloquearEstudiante($estudiante);
                if (! $this->matriculaActivaEn($estudiante, $curso, bloquear: true)) {
                    throw ValidationException::withMessages(['archivo' => 'Solo entrega quien tiene la matrícula activa en el grupo.']);
                }
                $a = Actividad::whereKey($actividad->id)->sharedLock()->with('curso.programa')->firstOrFail();
                if ($a->fecha_limite->lte($ahora)) {
                    throw ValidationException::withMessages(['archivo' => 'La fecha límite ya pasó: esta actividad no recibe más entregas.']);
                }
                $e = Entrega::where('actividad_id', $a->id)->where('estudiante_id', $estudiante->id)->lockForUpdate()->first();
                if ($e && $e->calificacion !== null) {
                    throw ValidationException::withMessages(['archivo' => 'Tu docente ya calificó esta entrega: no se puede cambiar.']);
                }
                $viejo = $e?->archivo_path;
                $datos = [
                    'archivo_path'   => $ruta,
                    'archivo_nombre' => Archivos::nombre($archivo),
                    'archivo_tamano' => $archivo->getSize(),
                    'fecha_entrega'  => $ahora,
                ];
                $e ? $e->update($datos) : Entrega::create($datos + ['actividad_id' => $a->id, 'estudiante_id' => $estudiante->id]);
                Auditoria::registrar($e ? 'editar' : 'crear', $a, sprintf('%s la actividad «%s» de %s · %s.',
                    $e ? 'Cambió el archivo de' : 'Entregó', $a->titulo, $a->curso->programa->nombre, $a->curso->nombre), $a->curso->sede_id);

                return (bool) $e;
            });
        } catch (\Throwable $error) {
            // No se guardó: el archivo nuevo no queda huérfano
            Storage::disk(self::DISCO)->delete($ruta);
            throw $error;
        }
        // Si la petición se deshace después (por ejemplo, falla al armar la respuesta), tampoco
        DB::afterRollBack(fn () => Storage::disk(self::DISCO)->delete($ruta));
        $this->borrarAlConfirmar($viejo);

        return $cambio;
    }

    /**
     * Bloquea la fila del estudiante antes que su matrícula: el orden de M13 (estudiante → matrícula → grupo). Al revés,
     * una escritura del Aula y un cambio de carrera de la secretaría podían bloquearse entre sí (reauditoría, N2).
     */
    private function bloquearEstudiante(User $estudiante): void
    {
        User::whereKey($estudiante->id)->lockForUpdate()->first();
    }

    /**
     * Borra un archivo cuando la petición confirma (TransaccionEscritura): si algo falla después y se deshace, la fila
     * sigue apuntando a un archivo que existe (auditoría, ES13). Sin transacción abierta se borra en el acto.
     */
    private function borrarAlConfirmar(?string $ruta): void
    {
        if ($ruta) {
            DB::afterCommit(fn () => Storage::disk(self::DISCO)->delete($ruta));
        }
    }

    /* ── Docente ─────────────────────────────────────────────────────────── */

    /** Grupos del docente con su malla, sus estudiantes activos y lo que tiene en el Aula (forma de docente/aula.js). */
    public function paraDocente(User $docente, Collection $cursos, CarbonInterface $ahora): array
    {
        $ids = $cursos->pluck('id');
        $evaluaciones = Evaluacion::whereIn('curso_id', $ids)->with(['preguntas.opciones', 'respuestasEstudiante', 'intentos'])->orderBy('fecha_limite')->get()->groupBy('curso_id');
        $actividades = Actividad::whereIn('curso_id', $ids)->with('entregas')->orderBy('fecha_limite')->get()->groupBy('curso_id');
        $materiales = Material::whereIn('curso_id', $ids)->latest()->get()->groupBy('curso_id');

        return $cursos->map(function (Curso $c) use ($evaluaciones, $actividades, $materiales, $ahora) {
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
                    'duracion'    => $ev->duracion_minutos,
                    // Quién la comenzó y no ha enviado (con tiempo límite): {estudianteId: {inicio, vencido}}
                    'comenzadas'  => (object) $ev->intentos->whereNotIn('estudiante_id', $ev->respuestasEstudiante->pluck('estudiante_id')->unique()->all())
                        ->mapWithKeys(fn (EvaluacionIntento $i) => [$i->estudiante_id => [
                            'inicio'  => $i->iniciada_en->format('Y-m-d H:i'),
                            'vencido' => $ahora->gt($ev->terminaPara($i->iniciada_en)->addSeconds(Evaluacion::GRACIA_SEGUNDOS)),
                        ]])->all(),
                    'conIntentos' => $ev->intentos->isNotEmpty() || $ev->respuestasEstudiante->isNotEmpty(),
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
                    'adjunto'       => $this->adjuntoDatos($a, route('docente.aula.guia', $a)),
                    'entregas'      => (object) $a->entregas->keyBy('estudiante_id')
                        ->map(fn (Entrega $e) => $this->entregaDatos($e, route('docente.aula.archivo', $e)) + ['id' => $e->id])->all(),
                ])->values()->all(),
                'materiales'  => ($materiales->get($c->id) ?? collect())
                    ->map(fn (Material $m) => $this->materialDatos($m, route('docente.aula.material', $m)))->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Crea o edita una evaluación: título, descripción, módulo, fecha límite, tiempo límite y preguntas [{enunciado,
     * opciones[{texto, correcta}]}] ya validadas (EvaluacionRequest). Mientras nadie la comience ni la responda, las
     * preguntas se reemplazan enteras. Después (decidido con el usuario el 27-11-2026) se corrigen en su lugar: textos,
     * cierre y respuesta correcta (y se recalcula el acierto de quienes ya respondieron), pero sin agregar ni quitar
     * preguntas u opciones ni cambiar el tiempo límite.
     */
    public function guardarEvaluacion(User $docente, Curso $curso, ?Evaluacion $evaluacion, array $datos): Evaluacion
    {
        return DB::transaction(function () use ($docente, $curso, $evaluacion, $datos) {
            $usada = false;
            if ($evaluacion) {
                $evaluacion = Evaluacion::whereKey($evaluacion->id)->lockForUpdate()->with('preguntas.opciones')->firstOrFail();
                if ($evaluacion->cerrada() && $evaluacion->fecha_limite->format('Y-m-d H:i') !== Carbon::parse($datos['cierra'])->format('Y-m-d H:i')) {
                    throw ValidationException::withMessages(['cierra' => Evaluacion::NO_REABRIR]);
                }
                $usada = RespuestaEstudiante::where('evaluacion_id', $evaluacion->id)->exists() || EvaluacionIntento::where('evaluacion_id', $evaluacion->id)->exists();
            }
            $duracion = $datos['duracion'] ?? null;
            if ($usada) {
                $this->mismaEstructura($evaluacion, $datos['preguntas']);
                if ((int) $evaluacion->duracion_minutos !== (int) $duracion) {
                    throw ValidationException::withMessages(['duracion' => 'Alguien ya la comenzó o la respondió: el tiempo límite no se cambia.']);
                }
            }
            $ev = $evaluacion ?? new Evaluacion(['curso_id' => $curso->id]);
            $ev->fill([
                'titulo' => $datos['titulo'], 'descripcion' => $datos['descripcion'] ?? null,
                'programa_modulo_id' => $datos['modulo_id'], 'fecha_limite' => $datos['cierra'], 'duracion_minutos' => $duracion,
            ]);
            $ev->docente_id = $docente->id;
            $ev->save();

            $recalculadas = 0;
            if ($usada) {
                // Se corrige en su lugar: las respuestas siguen apuntando a las mismas preguntas y opciones
                foreach ($evaluacion->preguntas->values() as $i => $pregunta) {
                    $nueva = array_values($datos['preguntas'])[$i];
                    $pregunta->update(['enunciado' => $nueva['enunciado']]);
                    $antes = $pregunta->opciones->firstWhere('es_correcta', true)?->id;
                    foreach ($pregunta->opciones->sortBy('id')->values() as $k => $opcion) {
                        $o = array_values($nueva['opciones'])[$k];
                        $opcion->update(['texto' => $o['texto'], 'es_correcta' => (bool) $o['correcta']]);
                    }
                    $ahora = $pregunta->opciones()->where('es_correcta', true)->value('id');
                    if ($antes !== $ahora) {
                        // Cambió la correcta: se vuelve a calificar a quienes ya respondieron esta pregunta
                        $recalculadas += RespuestaEstudiante::where('pregunta_id', $pregunta->id)->count();
                        RespuestaEstudiante::where('pregunta_id', $pregunta->id)->update(['es_correcta' => false]);
                        RespuestaEstudiante::where('pregunta_id', $pregunta->id)->where('opcion_id', $ahora)->update(['es_correcta' => true]);
                    }
                }
            } else {
                // Nadie la ha comenzado: las preguntas se reemplazan enteras
                $ev->preguntas()->each(fn (Pregunta $p) => $p->delete());
                foreach (array_values($datos['preguntas']) as $i => $p) {
                    $pregunta = Pregunta::create(['evaluacion_id' => $ev->id, 'enunciado' => $p['enunciado'], 'orden' => $i + 1]);
                    foreach (array_values($p['opciones']) as $o) {
                        Opcion::create(['pregunta_id' => $pregunta->id, 'texto' => $o['texto'], 'es_correcta' => (bool) $o['correcta']]);
                    }
                }
            }
            Auditoria::registrar($evaluacion ? 'editar' : 'crear', $curso, sprintf('%s la evaluación «%s» (%d preguntas%s) en %s · %s%s.',
                $evaluacion ? 'Editó' : 'Publicó', $ev->titulo, count($datos['preguntas']), $duracion ? ", {$duracion} minutos" : '',
                $curso->programa->nombre, $curso->nombre, $recalculadas ? " y cambió una respuesta correcta: se recalcularon {$recalculadas} respuestas" : ''));
            $ev->recalculadas = $recalculadas;

            return $ev;
        });
    }

    /** Con respuestas o intentos: las mismas preguntas, con las mismas opciones (solo cambian textos y la correcta). */
    private function mismaEstructura(Evaluacion $ev, array $preguntas): void
    {
        $preguntas = array_values($preguntas);
        $igual = count($preguntas) === $ev->preguntas->count()
            && $ev->preguntas->values()->every(fn (Pregunta $p, int $i) => count($preguntas[$i]['opciones'] ?? []) === $p->opciones->count());
        if (! $igual) {
            throw ValidationException::withMessages(['preguntas' => 'Alguien ya la comenzó o la respondió: puedes corregir los textos, el cierre y la respuesta correcta, pero no agregar ni quitar preguntas u opciones.']);
        }
    }

    public function eliminarEvaluacion(Evaluacion $evaluacion): void
    {
        DB::transaction(function () use ($evaluacion) {
            $ev = Evaluacion::whereKey($evaluacion->id)->lockForUpdate()->with('curso.programa')->firstOrFail();
            if (RespuestaEstudiante::where('evaluacion_id', $ev->id)->exists() || EvaluacionIntento::where('evaluacion_id', $ev->id)->exists()) {
                throw ValidationException::withMessages(['evaluacion' => 'Alguien ya comenzó o respondió esta evaluación: no se puede eliminar.']);
            }
            Auditoria::registrar('eliminar', $ev->curso, sprintf('Eliminó la evaluación «%s» de %s · %s.', $ev->titulo, $ev->curso->programa->nombre, $ev->curso->nombre));
            $ev->delete();
        });
    }

    /**
     * Crea o edita una actividad (título, instrucciones, módulo y fecha límite ya validados por ActividadRequest) con su
     * archivo de apoyo opcional: uno nuevo reemplaza al anterior y $quitarAdjunto lo quita. El archivo viejo se borra
     * solo cuando lo nuevo ya quedó guardado.
     */
    public function guardarActividad(User $docente, Curso $curso, ?Actividad $actividad, array $datos, ?UploadedFile $adjunto = null, bool $quitarAdjunto = false): Actividad
    {
        $viejo = null;
        $a = DB::transaction(function () use ($docente, $curso, $actividad, $datos, $adjunto, $quitarAdjunto, &$viejo) {
            $a = $actividad ? Actividad::whereKey($actividad->id)->lockForUpdate()->firstOrFail() : new Actividad(['curso_id' => $curso->id]);
            $a->fill(['titulo' => $datos['titulo'], 'instrucciones' => $datos['instrucciones'], 'programa_modulo_id' => $datos['modulo_id'], 'fecha_limite' => $datos['cierra']]);
            $a->docente_id = $docente->id;
            $a->save();
            if ($adjunto || $quitarAdjunto) {
                $viejo = $a->adjunto_path;
                $a->update($adjunto ? $this->guardarArchivo($adjunto, "aula/{$curso->sede_id}/actividad-{$a->id}/guia", 'adjunto') : ['adjunto_path' => null, 'adjunto_nombre' => null, 'adjunto_tamano' => null]);
            }
            Auditoria::registrar($actividad ? 'editar' : 'crear', $curso, sprintf('%s la actividad «%s» en %s · %s (se entrega hasta el %s)%s.',
                $actividad ? 'Editó' : 'Publicó', $a->titulo, $curso->programa->nombre, $curso->nombre, $a->fecha_limite->format('d/m/Y H:i'),
                $a->adjunto_nombre ? ' con el archivo «'.$a->adjunto_nombre.'»' : ''));

            return $a;
        });
        if ($viejo !== $a->adjunto_path) {
            $this->borrarAlConfirmar($viejo);
        }

        return $a;
    }

    public function eliminarActividad(Actividad $actividad): void
    {
        $archivo = DB::transaction(function () use ($actividad) {
            $a = Actividad::whereKey($actividad->id)->lockForUpdate()->with('curso.programa')->firstOrFail();
            if (Entrega::where('actividad_id', $a->id)->exists()) {
                throw ValidationException::withMessages(['actividad' => 'Alguien ya entregó esta actividad: no se puede eliminar.']);
            }
            Auditoria::registrar('eliminar', $a->curso, sprintf('Eliminó la actividad «%s» de %s · %s.', $a->titulo, $a->curso->programa->nombre, $a->curso->nombre));
            $a->delete();

            return $a->adjunto_path;
        });
        $this->borrarAlConfirmar($archivo);
    }

    /**
     * Publica o edita material de clase: título, descripción y módulo opcionales, y un archivo, un enlace o los dos
     * (MaterialRequest exige al menos uno). Un archivo nuevo reemplaza al anterior; $quitarArchivo lo quita.
     */
    public function guardarMaterial(User $docente, Curso $curso, ?Material $material, array $datos, ?UploadedFile $archivo = null, bool $quitarArchivo = false): Material
    {
        $viejo = null;
        $m = DB::transaction(function () use ($docente, $curso, $material, $datos, $archivo, $quitarArchivo, &$viejo) {
            $m = $material ? Material::whereKey($material->id)->lockForUpdate()->firstOrFail() : new Material(['curso_id' => $curso->id]);
            $m->fill([
                'titulo' => $datos['titulo'], 'descripcion' => $datos['descripcion'] ?? null,
                'programa_modulo_id' => $datos['modulo_id'] ?? null, 'enlace' => $datos['enlace'] ?? null,
            ]);
            // Al menos un archivo o un enlace (se revisa antes de guardar el archivo para no dejarlo huérfano)
            if (! $archivo && ($quitarArchivo || ! $m->archivo_path) && ! $m->enlace) {
                throw ValidationException::withMessages(['archivo' => 'Sube un archivo o escribe un enlace: el material necesita al menos uno.']);
            }
            $m->docente_id = $docente->id;
            $m->save();
            if ($archivo || $quitarArchivo) {
                $viejo = $m->archivo_path;
                $m->update($archivo ? $this->guardarArchivo($archivo, "aula/{$curso->sede_id}/material-{$m->id}", 'archivo') : ['archivo_path' => null, 'archivo_nombre' => null, 'archivo_tamano' => null]);
            }
            Auditoria::registrar($material ? 'editar' : 'crear', $curso, sprintf('%s el material «%s» en %s · %s.',
                $material ? 'Editó' : 'Publicó', $m->titulo, $curso->programa->nombre, $curso->nombre));

            return $m;
        });
        if ($viejo !== $m->archivo_path) {
            $this->borrarAlConfirmar($viejo);
        }

        return $m;
    }

    public function eliminarMaterial(Material $material): void
    {
        $archivo = DB::transaction(function () use ($material) {
            $m = Material::whereKey($material->id)->lockForUpdate()->with('curso.programa')->firstOrFail();
            Auditoria::registrar('eliminar', $m->curso, sprintf('Eliminó el material «%s» de %s · %s.', $m->titulo, $m->curso->programa->nombre, $m->curso->nombre));
            $m->delete();

            return $m->archivo_path;
        });
        $this->borrarAlConfirmar($archivo);
    }

    /**
     * Lo que cada estudiante hizo en el Aula de cada módulo (la referencia que el docente ve en Notas):
     * [curso_id => [estudiante_id => [modulo_id => [{tipo, titulo, nota}]]]]. Solo lo calificado (evaluaciones
     * respondidas y entregas con nota).
     */
    public function notasPorModulo(Collection $cursos): array
    {
        $ids = $cursos->pluck('id');
        $out = [];
        Evaluacion::whereIn('curso_id', $ids)->whereNotNull('programa_modulo_id')->with(['preguntas:id,evaluacion_id', 'respuestasEstudiante'])->orderBy('fecha_limite')->get()
            ->each(function (Evaluacion $ev) use (&$out) {
                $total = $ev->preguntas->count();
                $ev->respuestasEstudiante->groupBy('estudiante_id')->each(function (Collection $rs, $estId) use ($ev, $total, &$out) {
                    $out[$ev->curso_id][$estId][$ev->programa_modulo_id][] = [
                        'tipo' => 'evaluacion', 'titulo' => $ev->titulo, 'nota' => Evaluacion::nota($rs->where('es_correcta', true)->count(), $total),
                    ];
                });
            });
        Actividad::whereIn('curso_id', $ids)->whereNotNull('programa_modulo_id')->with(['entregas' => fn ($q) => $q->whereNotNull('calificacion')])->orderBy('fecha_limite')->get()
            ->each(function (Actividad $a) use (&$out) {
                foreach ($a->entregas as $e) {
                    $out[$a->curso_id][$e->estudiante_id][$a->programa_modulo_id][] = ['tipo' => 'actividad', 'titulo' => $a->titulo, 'nota' => round((float) $e->calificacion, 1)];
                }
            });

        return $out;
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

    /** Guarda un archivo subido en el disco privado y devuelve sus columnas ({prefijo}_path, _nombre, _tamano). */
    private function guardarArchivo(UploadedFile $archivo, string $carpeta, string $prefijo): array
    {
        return [
            $prefijo.'_path'   => $archivo->store($carpeta, self::DISCO),
            $prefijo.'_nombre' => Archivos::nombre($archivo),
            $prefijo.'_tamano' => $archivo->getSize(),
        ];
    }

    /** El archivo de apoyo de una actividad (null si no tiene). */
    private function adjuntoDatos(Actividad $a, string $url): ?array
    {
        return $a->adjunto_path ? $this->archivoDatos($a->adjunto_nombre ?: basename($a->adjunto_path), (int) $a->adjunto_tamano, $url) : null;
    }

    /** Lo que ven el docente y el estudiante de un material de clase. */
    private function materialDatos(Material $m, string $url): array
    {
        return [
            'id'          => $m->id,
            'titulo'      => $m->titulo,
            'descripcion' => (string) $m->descripcion,
            'moduloId'    => $m->programa_modulo_id,
            'enlace'      => $m->enlace,
            'archivo'     => $m->archivo_path ? $this->archivoDatos($m->archivo_nombre ?: basename($m->archivo_path), (int) $m->archivo_tamano, $url) : null,
            'fecha'       => $m->created_at->format('Y-m-d H:i'),
        ];
    }

    private function archivoDatos(string $nombre, int $tamano, string $url): array
    {
        return ['nombre' => $nombre, 'tamano' => $tamano, 'tipo' => strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), 'url' => $url];
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
