<?php

namespace App\Http\Controllers\Admin\Comunicados;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Comunicados\ArchivarComunicadoRequest;
use App\Http\Requests\Admin\Comunicados\ComunicadoRequest;
use App\Models\Comunicacion\Comunicado;
use App\Models\Sistema\Auditoria;
use App\Services\Comunicacion\ComunicadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Admin › Comunicados (Comunicación): los comunicados de la sede (los del admin y los que los docentes
 * publican a sus grupos), quién los leyó, y crear / editar / archivar / eliminar. Todo queda en la auditoría.
 * Responde el comunicado en el formato que pinta comunicados.js.
 */
class ComunicadoController extends Controller
{
    public function __construct(private ComunicadoService $comunicados) {}

    public function index(): View
    {
        return view('admin.comunicados.index', $this->comunicados->datosSede($this->sedeId()));
    }

    public function store(ComunicadoRequest $request): JsonResponse
    {
        $accion = $request->input('accion');
        $c = $this->comunicados->guardar($this->sedeId(), null, $request->datos(), $accion, $request->file('adjunto'), false);

        Auditoria::registrar('crear', $c, $this->descripcion('Creó', $c, $accion));

        return $this->respuesta($c, ['borrador' => 'Se guardó el borrador.', 'programar' => 'Quedó programado.', 'publicar' => 'Se publicó el comunicado.'][$accion]);
    }

    public function update(ComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $antes = $comunicado->estadoActual();
        $accion = $request->input('accion');
        $c = $this->comunicados->guardar($this->sedeId(), $comunicado, $request->datos(), $accion, $request->file('adjunto'), $request->boolean('quitar_adjunto'));

        Auditoria::registrar('editar', $c, $this->descripcion('Editó', $c, $antes === 'publicado' ? 'editar' : $accion));

        $mensaje = $antes === 'publicado' ? 'Se guardaron los cambios.'
            : ['borrador' => 'Se guardó el borrador.', 'programar' => 'Quedó programado.', 'publicar' => 'Se publicó el comunicado.'][$accion];

        return $this->respuesta($c, $mensaje);
    }

    public function archivar(ArchivarComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $motivo = trim($request->input('motivo'));
        $c = $this->comunicados->archivar($comunicado, $motivo);
        $de = $c->esDelAdmin() ? '' : ' del docente '.trim($c->autor->nombres.' '.$c->autor->apellidos);

        Auditoria::registrar('desactivar', $c, "Archivó el comunicado{$de} «{$c->titulo}»: ".rtrim($motivo, '.').'.');

        return $this->respuesta($c, 'Se archivó el comunicado: ya nadie lo ve en su panel.');
    }

    public function destroy(Comunicado $comunicado): JsonResponse
    {
        abort_unless($comunicado->sede_id === $this->sedeId(), 403);
        abort_unless($comunicado->estado === 'borrador', 422, 'Solo se eliminan los borradores.');

        Auditoria::registrar('eliminar', $comunicado, "Eliminó el borrador «{$comunicado->titulo}».");
        $this->comunicados->eliminar($comunicado);

        return response()->json(['ok' => true, 'mensaje' => 'Se eliminó el borrador.']);
    }

    /** El adjunto se descarga por la app (vive en el disco privado), solo desde la misma sede. */
    public function adjunto(Comunicado $comunicado): StreamedResponse
    {
        abort_unless($comunicado->sede_id === $this->sedeId() && $comunicado->adjunto_ruta, 404);
        $disco = Storage::disk(ComunicadoService::disco());
        abort_unless($disco->exists($comunicado->adjunto_ruta), 404);

        return $disco->response($comunicado->adjunto_ruta, $comunicado->adjunto_nombre);
    }

    private function respuesta(Comunicado $c, string $mensaje): JsonResponse
    {
        return response()->json(['ok' => true, 'comunicado' => $this->comunicados->comunicado($c), 'mensaje' => $mensaje]);
    }

    private function descripcion(string $verbo, Comunicado $c, string $accion): string
    {
        $para = Comunicado::DESTINOS_ADMIN[$c->destino] ?? 'su grupo';
        $como = match ($accion) {
            'borrador'  => 'como borrador',
            'programar' => 'programado para el '.$c->publicado_en->format('d/m/Y H:i'),
            'editar'    => 'ya publicado',
            default     => 'y lo publicó',
        };

        return "{$verbo} el comunicado «{$c->titulo}» para ".mb_strtolower($para)." {$como}.";
    }

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return (int) $sedeId;
    }
}
