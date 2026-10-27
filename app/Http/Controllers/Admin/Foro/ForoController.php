<?php

namespace App\Http\Controllers\Admin\Foro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Foro\EstadoTemaRequest;
use App\Http\Requests\Admin\Foro\OcultarMensajeRequest;
use App\Http\Requests\Admin\Foro\RespuestaRequest;
use App\Http\Requests\Admin\Foro\TemaRequest;
use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoTema;
use App\Models\Sistema\Auditoria;
use App\Services\Comunicacion\ForoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Admin › Foro (Comunicación): el foro general de la sede y su moderación. La administración abre temas,
 * responde, fija, cierra o reabre, oculta mensajes con motivo, los restaura y descarta reportes; todo queda en la
 * auditoría. Responde el tema en el formato que pinta foro.js.
 */
class ForoController extends Controller
{
    public function __construct(private ForoService $foro) {}

    public function index(): View
    {
        return view('admin.foro.index', $this->foro->datosSede($this->sedeId()));
    }

    public function store(TemaRequest $request): JsonResponse
    {
        $t = $this->foro->crearTema($this->sedeId(), $request->validated(), $request->file('adjunto'));
        Auditoria::registrar('crear', $t, "Abrió el tema del foro «{$t->titulo}» (".ForoTema::CATEGORIAS[$t->categoria].')'.($t->fijado ? ' y lo fijó arriba' : '').'.');

        return $this->respuesta($t, 'Se publicó el tema.');
    }

    public function responder(RespuestaRequest $request, ForoTema $tema): JsonResponse
    {
        $t = $this->foro->responder($tema, $request->input('texto'), $request->file('adjunto'));
        Auditoria::registrar('editar', $t, "Respondió como administración en el tema «{$t->titulo}»: «".Str::limit($request->input('texto'), 80).'».');

        return $this->respuesta($t, 'Se publicó tu respuesta.');
    }

    public function estado(EstadoTemaRequest $request, ForoTema $tema): JsonResponse
    {
        if ($request->has('fijado')) {
            $si = $request->boolean('fijado');
            $t = $this->foro->fijar($tema, $si);
            Auditoria::registrar('editar', $t, ($si ? 'Fijó arriba' : 'Quitó de arriba').' el tema del foro «'.$t->titulo.'».');

            return $this->respuesta($t, $si ? 'El tema quedó fijado arriba.' : 'Se quitó el tema de arriba.');
        }
        $si = $request->boolean('cerrado');
        $t = $this->foro->cerrar($tema, $si);
        Auditoria::registrar($si ? 'desactivar' : 'activar', $t, ($si ? 'Cerró' : 'Reabrió').' el tema del foro «'.$t->titulo.'».');

        return $this->respuesta($t, $si ? 'Se cerró el tema: ya nadie puede responder.' : 'Se reabrió el tema.');
    }

    public function ocultar(OcultarMensajeRequest $request, ForoMensaje $mensaje): JsonResponse
    {
        $primero = $mensaje->esPrimero();
        $motivo = trim($request->input('motivo'));
        $t = $this->foro->ocultar($mensaje, $motivo);
        $de = $mensaje->autor->nombres.' '.$mensaje->autor->apellidos;
        Auditoria::registrar('desactivar', $mensaje, ($primero ? 'Ocultó el tema del foro «'.$t->titulo.'»' : 'Ocultó un mensaje de '.trim($de).' en el tema «'.$t->titulo.'»').': '.rtrim($motivo, '.').'.');

        return $this->respuesta($t, $primero ? 'Se ocultó el tema: ya nadie lo ve.' : 'Se ocultó el mensaje: ya nadie lo ve.');
    }

    public function restaurar(ForoMensaje $mensaje): JsonResponse
    {
        $this->deMiSede($mensaje);
        abort_unless($mensaje->oculto(), 422, 'Ese mensaje no está oculto.');
        $t = $this->foro->restaurar($mensaje);
        Auditoria::registrar('activar', $mensaje, 'Restauró un mensaje oculto en el tema del foro «'.$t->titulo.'».');

        return $this->respuesta($t, 'Se restauró el mensaje: vuelve a verse en el foro.');
    }

    public function descartar(ForoMensaje $mensaje): JsonResponse
    {
        $this->deMiSede($mensaje);
        abort_unless($mensaje->reportes()->where('estado', 'pendiente')->exists(), 422, 'Ese mensaje no tiene reportes por revisar.');
        $t = $this->foro->descartarReportes($mensaje);
        Auditoria::registrar('editar', $mensaje, 'Descartó los reportes de un mensaje en el tema del foro «'.$t->titulo.'»: sigue visible.');

        return $this->respuesta($t, 'Se descartaron los reportes: el mensaje sigue visible.');
    }

    /** El adjunto de un mensaje se descarga por la app (vive en el disco privado), solo desde la misma sede. */
    public function adjunto(ForoMensaje $mensaje): StreamedResponse
    {
        abort_unless($mensaje->tema->sede_id === $this->sedeId() && $mensaje->adjunto_ruta, 404);
        $disco = Storage::disk(ForoService::disco());
        abort_unless($disco->exists($mensaje->adjunto_ruta), 404);

        return $disco->response($mensaje->adjunto_ruta, $mensaje->adjunto_nombre);
    }

    private function respuesta(ForoTema $t, string $mensaje): JsonResponse
    {
        return response()->json(['ok' => true, 'tema' => $this->foro->tema($t), 'mensaje' => $mensaje]);
    }

    private function deMiSede(ForoMensaje $m): void
    {
        abort_unless($m->tema->sede_id === $this->sedeId(), 403);
    }

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return (int) $sedeId;
    }
}
