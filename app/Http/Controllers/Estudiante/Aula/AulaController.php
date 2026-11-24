<?php

namespace App\Http\Controllers\Estudiante\Aula;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudiante\Aula\EntregaRequest;
use App\Http\Requests\Estudiante\Aula\RespuestasRequest;
use App\Models\Academico\Actividad;
use App\Models\Academico\Entrega;
use App\Models\Academico\Evaluacion;
use App\Models\User;
use App\Services\Academico\AulaService;
use App\Services\Academico\PanelEstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Estudiante › Aula virtual: responde las evaluaciones (una vez) y entrega las actividades de su grupo que
 * publica el docente (reglas en AulaService). Cada respuesta trae los datos del panel recalculados.
 */
class AulaController extends Controller
{
    public function __construct(private PanelEstudianteService $panel, private AulaService $aula) {}

    public function index(): View
    {
        return view('estudiante.aula.index', ['datos' => $this->panel->datos(auth()->user())]);
    }

    public function responder(RespuestasRequest $request, Evaluacion $evaluacion): JsonResponse
    {
        [$aciertos, $total, $nota] = $this->aula->responder($request->user(), $evaluacion, $request->input('respuestas'));

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

    private function respuesta(string $mensaje): JsonResponse
    {
        return response()->json(['ok' => true, 'mensaje' => $mensaje, 'datos' => $this->panel->datos(User::findOrFail(auth()->id()))]);
    }
}
