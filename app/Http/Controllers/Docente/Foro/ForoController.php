<?php

namespace App\Http\Controllers\Docente\Foro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\Foro\ReporteRequest;
use App\Http\Requests\Docente\Foro\RespuestaRequest;
use App\Http\Requests\Docente\Foro\TemaRequest;
use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoTema;
use App\Services\Comunicacion\ForoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Docente › Foro: el foro de su sede (ForoService, las reglas de la secretaría). Lee, abre temas, responde y
 * reporta (una vez por mensaje); no ve lo que la secretaría ocultó. Solo participa: la moderación sigue en Secretaría › Foro
 * (decidido con el usuario el 24-11-2026).
 */
class ForoController extends Controller
{
    public function __construct(private ForoService $foro) {}

    public function index(): View
    {
        return view('docente.foro.index', ['datos' => $this->datos()]);
    }

    public function tema(TemaRequest $request): JsonResponse
    {
        $t = $this->foro->crearTema((int) $request->user()->sede_id, $request->validated(), $request->file('adjunto'));

        return $this->respuesta('Publicaste el tema.', ['tema' => $t->id]);
    }

    public function responder(RespuestaRequest $request, ForoTema $tema): JsonResponse
    {
        $this->foro->responder($tema, $request->input('texto'), $request->file('adjunto'));

        return $this->respuesta('Publicaste tu respuesta.');
    }

    public function reportar(ReporteRequest $request, ForoMensaje $mensaje): JsonResponse
    {
        $this->foro->reportar($mensaje, $request->user(), $request->input('motivo'));

        return $this->respuesta('Gracias. El administrativo revisará el mensaje.');
    }

    public function adjunto(ForoMensaje $mensaje): StreamedResponse
    {
        $mensaje->loadMissing('tema');
        abort_unless($mensaje->tema->sede_id === (int) auth()->user()->sede_id && $mensaje->adjunto_ruta, 404);
        abort_if($mensaje->oculto() || $mensaje->tema->oculto(), 404);
        $disco = Storage::disk(ForoService::disco());
        abort_unless($disco->exists($mensaje->adjunto_ruta), 404);

        return $disco->response($mensaje->adjunto_ruta, $mensaje->adjunto_nombre);
    }

    private function datos(): array
    {
        return ['hoy' => today()->toDateString(), 'foro' => $this->foro->panel(auth()->user(), 'docente.foro.adjunto')];
    }

    private function respuesta(string $mensaje, array $extra = []): JsonResponse
    {
        return response()->json(['ok' => true, 'mensaje' => $mensaje, 'datos' => $this->datos()] + $extra);
    }
}
