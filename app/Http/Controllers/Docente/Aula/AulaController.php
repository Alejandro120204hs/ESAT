<?php

namespace App\Http\Controllers\Docente\Aula;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\Aula\ActividadRequest;
use App\Http\Requests\Docente\Aula\CalificarRequest;
use App\Http\Requests\Docente\Aula\EliminarRequest;
use App\Http\Requests\Docente\Aula\EvaluacionRequest;
use App\Models\Academico\Actividad;
use App\Models\Academico\Entrega;
use App\Models\Academico\Evaluacion;
use App\Models\User;
use App\Services\Academico\AulaService;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Docente › Aula virtual: crea, edita y elimina evaluaciones y actividades de sus grupos, ve quién respondió o
 * entregó y califica las entregas (reglas en AulaService). Cada escritura responde con los grupos recalculados.
 */
class AulaController extends Controller
{
    public function __construct(private PanelDocenteService $panel, private AulaService $aula) {}

    public function index(): View
    {
        return view('docente.aula.index', ['datos' => $this->datos(auth()->user())]);
    }

    public function crearEvaluacion(EvaluacionRequest $request): JsonResponse
    {
        $ev = $this->aula->guardarEvaluacion($request->user(), $request->curso(), null, $request->validated());

        return $this->respuesta('Publicaste la evaluación «'.$ev->titulo.'». Tus estudiantes ya la ven en su Aula virtual.');
    }

    public function editarEvaluacion(EvaluacionRequest $request, Evaluacion $evaluacion): JsonResponse
    {
        $ev = $this->aula->guardarEvaluacion($request->user(), $request->curso(), $evaluacion, $request->validated());

        return $this->respuesta('Guardaste los cambios de «'.$ev->titulo.'».');
    }

    public function eliminarEvaluacion(EliminarRequest $request, Evaluacion $evaluacion): JsonResponse
    {
        $this->aula->eliminarEvaluacion($evaluacion);

        return $this->respuesta('Eliminaste la evaluación «'.$evaluacion->titulo.'».');
    }

    public function crearActividad(ActividadRequest $request): JsonResponse
    {
        $a = $this->aula->guardarActividad($request->user(), $request->curso(), null, $request->validated());

        return $this->respuesta('Publicaste la actividad «'.$a->titulo.'». Tus estudiantes ya la ven en su Aula virtual.');
    }

    public function editarActividad(ActividadRequest $request, Actividad $actividad): JsonResponse
    {
        $a = $this->aula->guardarActividad($request->user(), $request->curso(), $actividad, $request->validated());

        return $this->respuesta('Guardaste los cambios de «'.$a->titulo.'».');
    }

    public function eliminarActividad(EliminarRequest $request, Actividad $actividad): JsonResponse
    {
        $this->aula->eliminarActividad($actividad);

        return $this->respuesta('Eliminaste la actividad «'.$actividad->titulo.'».');
    }

    public function calificar(CalificarRequest $request, Entrega $entrega): JsonResponse
    {
        $e = $this->aula->calificar($request->user(), $entrega, (float) $request->input('nota'), $request->input('comentario'));

        return $this->respuesta('Calificaste la entrega de '.trim($e->estudiante->nombres.' '.$e->estudiante->apellidos).'.');
    }

    /** El archivo de una entrega de uno de sus grupos (también de los finalizados: es consulta). */
    public function archivo(Entrega $entrega): StreamedResponse
    {
        $entrega->loadMissing('actividad');
        abort_unless($entrega->archivo_path && $this->panel->curso(auth()->user(), (int) $entrega->actividad?->curso_id), 404);
        $disco = Storage::disk(AulaService::DISCO);
        abort_unless($disco->exists($entrega->archivo_path), 404);

        return $disco->response($entrega->archivo_path, $entrega->archivo_nombre ?: basename($entrega->archivo_path));
    }

    private function datos(User $docente): array
    {
        $ahora = now();

        return ['hoy' => $ahora->toDateString(), 'grupos' => $this->aula->paraDocente($docente, $this->panel->cursos($docente), $ahora)];
    }

    private function respuesta(string $mensaje): JsonResponse
    {
        return response()->json(['ok' => true, 'mensaje' => $mensaje, 'datos' => $this->datos(User::findOrFail(auth()->id()))]);
    }
}
