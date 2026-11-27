<?php

namespace App\Http\Controllers\Docente\Aula;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\Aula\ActividadRequest;
use App\Http\Requests\Docente\Aula\CalificarRequest;
use App\Http\Requests\Docente\Aula\EliminarRequest;
use App\Http\Requests\Docente\Aula\EvaluacionRequest;
use App\Http\Requests\Docente\Aula\MaterialRequest;
use App\Models\Academico\Actividad;
use App\Models\Academico\Entrega;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Material;
use App\Models\User;
use App\Services\Academico\AulaService;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Docente › Aula virtual: crea, edita y elimina evaluaciones, actividades (con su guía) y material de clase de sus
 * grupos, ve quién respondió o entregó y califica las entregas (reglas en AulaService). Cada escritura responde con los
 * grupos recalculados.
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
        $n = (int) $ev->recalculadas;

        return $this->respuesta('Guardaste los cambios de «'.$ev->titulo.'».'.($n ? ' Cambiaste una respuesta correcta: se recalcularon las notas de quienes ya la respondieron.' : ''));
    }

    public function eliminarEvaluacion(EliminarRequest $request, Evaluacion $evaluacion): JsonResponse
    {
        $this->aula->eliminarEvaluacion($evaluacion);

        return $this->respuesta('Eliminaste la evaluación «'.$evaluacion->titulo.'».');
    }

    public function crearActividad(ActividadRequest $request): JsonResponse
    {
        $a = $this->aula->guardarActividad($request->user(), $request->curso(), null, $request->validated(), $request->file('adjunto'), $request->boolean('quitar_adjunto'));

        return $this->respuesta('Publicaste la actividad «'.$a->titulo.'». Tus estudiantes ya la ven en su Aula virtual.');
    }

    public function editarActividad(ActividadRequest $request, Actividad $actividad): JsonResponse
    {
        $a = $this->aula->guardarActividad($request->user(), $request->curso(), $actividad, $request->validated(), $request->file('adjunto'), $request->boolean('quitar_adjunto'));

        return $this->respuesta('Guardaste los cambios de «'.$a->titulo.'».');
    }

    public function eliminarActividad(EliminarRequest $request, Actividad $actividad): JsonResponse
    {
        $this->aula->eliminarActividad($actividad);

        return $this->respuesta('Eliminaste la actividad «'.$actividad->titulo.'».');
    }

    public function crearMaterial(MaterialRequest $request): JsonResponse
    {
        $m = $this->aula->guardarMaterial($request->user(), $request->curso(), null, $request->validated(), $request->file('archivo'), $request->boolean('quitar_archivo'));

        return $this->respuesta('Publicaste «'.$m->titulo.'». Tus estudiantes ya lo ven en el material de su Aula virtual.');
    }

    public function editarMaterial(MaterialRequest $request, Material $material): JsonResponse
    {
        $m = $this->aula->guardarMaterial($request->user(), $request->curso(), $material, $request->validated(), $request->file('archivo'), $request->boolean('quitar_archivo'));

        return $this->respuesta('Guardaste los cambios de «'.$m->titulo.'».');
    }

    public function eliminarMaterial(EliminarRequest $request, Material $material): JsonResponse
    {
        $this->aula->eliminarMaterial($material);

        return $this->respuesta('Eliminaste «'.$material->titulo.'» del material del grupo.');
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

    /** La guía de una actividad de uno de sus grupos (también de los finalizados: es consulta). */
    public function guia(Actividad $actividad): StreamedResponse
    {
        abort_unless($actividad->adjunto_path && $this->panel->curso(auth()->user(), (int) $actividad->curso_id), 404);

        return $this->descargar($actividad->adjunto_path, $actividad->adjunto_nombre);
    }

    /** El archivo de un material de uno de sus grupos. */
    public function material(Material $material): StreamedResponse
    {
        abort_unless($material->archivo_path && $this->panel->curso(auth()->user(), (int) $material->curso_id), 404);

        return $this->descargar($material->archivo_path, $material->archivo_nombre);
    }

    private function descargar(string $ruta, ?string $nombre): StreamedResponse
    {
        $disco = Storage::disk(AulaService::DISCO);
        abort_unless($disco->exists($ruta), 404);

        return $disco->response($ruta, $nombre ?: basename($ruta));
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
