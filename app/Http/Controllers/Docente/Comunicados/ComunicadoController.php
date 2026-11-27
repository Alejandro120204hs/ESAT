<?php

namespace App\Http\Controllers\Docente\Comunicados;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\Comunicados\ArchivarRequest;
use App\Http\Requests\Docente\Comunicados\ComunicadoRequest;
use App\Models\Comunicacion\Comunicado;
use App\Models\Sistema\Auditoria;
use App\Services\Comunicacion\ComunicadoDocenteService;
use App\Services\Comunicacion\ComunicadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Docente › Comunicados (ComunicadoDocenteService): los que le llegan (abrir uno lo marca leído) y los que publica
 * a sus grupos: publicar ya, programar o borrador, con adjunto; editar el texto, archivar con motivo y eliminar borradores.
 * Todo queda en la auditoría. Cada escritura responde {ok, mensaje, datos} con la pantalla recalculada.
 */
class ComunicadoController extends Controller
{
    private const MENSAJE = ['borrador' => 'Se guardó el borrador.', 'programar' => 'Quedó programado.', 'publicar' => 'Se publicó el comunicado: tus estudiantes ya lo ven en su panel.'];

    public function __construct(private ComunicadoDocenteService $servicio) {}

    public function index(): View
    {
        return view('docente.comunicados.index', ['datos' => $this->servicio->datos(auth()->user())]);
    }

    public function store(ComunicadoRequest $request): JsonResponse
    {
        $accion = $request->input('accion');
        $c = $this->servicio->guardar($request->user(), null, $request->datos(), $accion, $request->grupos(), $request->file('adjunto'), false);

        Auditoria::registrar('crear', $c, $this->descripcion('Creó', $c, $accion));

        return $this->respuesta(self::MENSAJE[$accion], ['comunicado' => $c->id]);
    }

    public function update(ComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $publicado = $this->servicio->lote($comunicado)->contains(fn (Comunicado $f) => $f->estadoActual() === 'publicado');
        $accion = $request->input('accion');
        $c = $this->servicio->guardar($request->user(), $comunicado, $request->datos(), $accion, $request->grupos(), $request->file('adjunto'), $request->boolean('quitar_adjunto'));

        Auditoria::registrar('editar', $c, $this->descripcion('Editó', $c, $publicado ? 'editar' : $accion));

        return $this->respuesta($publicado ? 'Se guardaron los cambios.' : self::MENSAJE[$accion], ['comunicado' => $c->id]);
    }

    public function archivar(ArchivarRequest $request, Comunicado $comunicado): JsonResponse
    {
        $motivo = trim($request->input('motivo'));
        $this->servicio->archivar($comunicado, $motivo);

        Auditoria::registrar('desactivar', $comunicado, "Archivó su comunicado «{$comunicado->titulo}» para {$this->servicio->para($comunicado)}: ".rtrim($motivo, '.').'.');

        return $this->respuesta('Se archivó el comunicado: tus estudiantes ya no lo ven.');
    }

    public function destroy(Comunicado $comunicado): JsonResponse
    {
        abort_unless($comunicado->autor_id === auth()->id() && $comunicado->destino === 'grupo', 403);
        $filas = $this->servicio->lote($comunicado);
        abort_unless($filas->every(fn (Comunicado $f) => $f->estado === 'borrador'), 422, 'Solo se eliminan los borradores.');

        Auditoria::registrar('eliminar', $comunicado, "Eliminó el borrador «{$comunicado->titulo}» para {$this->servicio->para($comunicado)}.");
        $this->servicio->eliminar($comunicado);

        return $this->respuesta('Se eliminó el borrador.');
    }

    /** Abrir uno que le llegó lo marca leído (la primera lectura no cambia). */
    public function leido(Comunicado $comunicado): JsonResponse
    {
        abort_unless($this->servicio->recibidosDe(auth()->user())->contains('id', $comunicado->id), 404);
        DB::table('comunicado_lecturas')->insertOrIgnore([
            'comunicado_id' => $comunicado->id, 'user_id' => auth()->id(), 'leido_en' => now(),
        ]);

        return $this->respuesta('');
    }

    /** El adjunto de uno que le llegó o de uno suyo (404 para cualquier otro: no se revela que existe). */
    public function adjunto(Comunicado $comunicado): StreamedResponse
    {
        $suyo = $comunicado->autor_id === auth()->id();
        abort_unless($suyo || $this->servicio->recibidosDe(auth()->user())->contains('id', $comunicado->id), 404);
        abort_unless($comunicado->adjunto_ruta, 404);
        $disco = Storage::disk(ComunicadoService::disco());
        abort_unless($disco->exists($comunicado->adjunto_ruta), 404);

        return $disco->response($comunicado->adjunto_ruta, $comunicado->adjunto_nombre);
    }

    private function respuesta(string $mensaje, array $extra = []): JsonResponse
    {
        return response()->json(['ok' => true, 'mensaje' => $mensaje, 'datos' => $this->servicio->datos(auth()->user())] + $extra);
    }

    private function descripcion(string $verbo, Comunicado $c, string $accion): string
    {
        $como = match ($accion) {
            'borrador'  => 'como borrador',
            'programar' => 'programado para el '.$c->publicado_en->format('d/m/Y H:i'),
            'editar'    => 'ya publicado',
            default     => 'y lo publicó',
        };

        return "{$verbo} el comunicado «{$c->titulo}» para {$this->servicio->para($c)} {$como}.";
    }
}
