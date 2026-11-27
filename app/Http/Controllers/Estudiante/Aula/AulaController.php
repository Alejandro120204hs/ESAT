<?php

namespace App\Http\Controllers\Estudiante\Aula;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudiante\Aula\EntregaRequest;
use App\Http\Requests\Estudiante\Aula\IniciarRequest;
use App\Http\Requests\Estudiante\Aula\RespuestasRequest;
use App\Models\Academico\Actividad;
use App\Models\Academico\Entrega;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Material;
use App\Models\User;
use App\Services\Academico\AulaService;
use App\Services\Academico\PanelEstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Estudiante › Aula virtual: responde las evaluaciones (una vez), entrega las actividades y descarga la guía de cada
 * actividad y el material de clase de su grupo que publica el docente (reglas en AulaService). Cada respuesta trae los
 * datos del panel recalculados.
 */
class AulaController extends Controller
{
    public function __construct(private PanelEstudianteService $panel, private AulaService $aula) {}

    public function index(): View
    {
        return view('estudiante.aula.index', ['datos' => $this->panel->datos(auth()->user())]);
    }

    public function iniciar(IniciarRequest $request, Evaluacion $evaluacion): JsonResponse
    {
        $this->aula->iniciar($request->user(), $evaluacion);

        return $this->respuesta('Comenzaste. Tienes '.$evaluacion->duracion_minutos.' minutos: al terminar el tiempo se envía lo que hayas marcado.');
    }

    public function responder(RespuestasRequest $request, Evaluacion $evaluacion): JsonResponse
    {
        [$aciertos, $total, $nota] = $this->aula->responder($request->user(), $evaluacion, (array) $request->input('respuestas'));

        return $this->respuesta('Enviada. Acertaste '.$aciertos.' de '.$total.': tu nota es '.number_format($nota, 1, ',', '').'.');
    }

    public function entregar(EntregaRequest $request, Actividad $actividad): JsonResponse
    {
        $cambio = $this->aula->entregar($request->user(), $actividad, $request->file('archivo'));

        return $this->respuesta($cambio ? 'Cambiaste tu archivo. Tu docente verá la versión nueva.' : 'Entregada. Puedes cambiar el archivo hasta la fecha límite.');
    }

    /** El archivo que entregó (solo el suyo). */
    public function archivo(Entrega $entrega): StreamedResponse
    {
        abort_unless($entrega->estudiante_id === auth()->id() && $entrega->archivo_path, 404);
        $disco = Storage::disk(AulaService::DISCO);
        abort_unless($disco->exists($entrega->archivo_path), 404);

        return $disco->response($entrega->archivo_path, $entrega->archivo_nombre ?: basename($entrega->archivo_path));
    }

    /** La guía de una actividad de un grupo en el que tiene (o tuvo) matrícula. */
    public function guia(Actividad $actividad): StreamedResponse
    {
        abort_unless($actividad->adjunto_path && $this->aula->estudioEn(auth()->user(), (int) $actividad->curso_id), 404);

        return $this->descargar($actividad->adjunto_path, $actividad->adjunto_nombre);
    }

    /** El archivo de un material de clase de un grupo en el que tiene (o tuvo) matrícula. */
    public function material(Material $material): StreamedResponse
    {
        abort_unless($material->archivo_path && $this->aula->estudioEn(auth()->user(), (int) $material->curso_id), 404);

        return $this->descargar($material->archivo_path, $material->archivo_nombre);
    }

    private function descargar(string $ruta, ?string $nombre): StreamedResponse
    {
        $disco = Storage::disk(AulaService::DISCO);
        abort_unless($disco->exists($ruta), 404);

        return $disco->response($ruta, $nombre ?: basename($ruta));
    }

    private function respuesta(string $mensaje): JsonResponse
    {
        return response()->json(['ok' => true, 'mensaje' => $mensaje, 'datos' => $this->panel->datos(User::findOrFail(auth()->id()))]);
    }
}
