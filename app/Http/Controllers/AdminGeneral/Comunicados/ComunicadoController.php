<?php

namespace App\Http\Controllers\AdminGeneral\Comunicados;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Comunicados\ArchivarComunicadoRequest;
use App\Http\Requests\AdminGeneral\Comunicados\ComunicadoRequest;
use App\Models\Comunicacion\Comunicado;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Services\General\ComunicacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Comunicados de todas las sedes: el Admin publica, programa o deja en borrador a todas o algunas sedes (una fila por sede,
 * agrupadas por lote), edita los suyos, archiva cualquiera con motivo y elimina sus borradores. Todo queda en la auditoría.
 */
class ComunicadoController extends Controller
{
    private const MENSAJES = ['borrador' => 'Se guardó el borrador.', 'programar' => 'Quedó programado.', 'publicar' => 'Se publicó el comunicado.'];

    public function __construct(private ComunicacionService $comunicacion) {}

    public function index(): View
    {
        return view('admin_general.comunicados.index', ['datos' => [
            'yo'          => trim(auth()->user()->nombres.' '.auth()->user()->apellidos) ?: 'Admin',
            'sedes'       => Sede::orderBy('nombre')->get(['id', 'nombre', 'ciudad'])->all(),
            'alcance'     => $this->comunicacion->alcance(),
            'comunicados' => $this->comunicacion->comunicados(),
        ]]);
    }

    public function store(ComunicadoRequest $request): JsonResponse
    {
        $accion = $request->input('accion');
        $sedes = $request->input('sedes');
        $c = $this->comunicacion->crear($sedes, $request->datos(), $accion);
        Auditoria::registrar('crear', $c, $this->descripcion($c, $accion, $sedes), count($sedes) === 1 ? $sedes[0] : null);

        return response()->json(['mensaje' => self::MENSAJES[$accion], 'id' => $c->id], 201);
    }

    public function update(ComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $antes = $comunicado->estadoActual();
        $accion = $request->input('accion');
        $sedes = $request->input('sedes');
        $c = $this->comunicacion->editar($comunicado, $sedes, $request->datos(), $accion);
        Auditoria::registrar('editar', $c, $this->descripcion($c, $antes === 'publicado' ? 'editar' : $accion, $sedes), count($sedes) === 1 ? $sedes[0] : null);

        return response()->json(['mensaje' => $antes === 'publicado' ? 'Se guardaron los cambios.' : self::MENSAJES[$accion], 'id' => $c->id]);
    }

    public function archivar(ArchivarComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $motivo = trim($request->input('motivo'));
        $sedes = $this->comunicacion->sedesDe($comunicado);
        $this->comunicacion->archivar($comunicado, $motivo);
        Auditoria::registrar('desactivar', $comunicado, "Archivó el comunicado «{$comunicado->titulo}»: ".rtrim($motivo, '.').'.', count($sedes) === 1 ? $sedes[0] : null);

        return response()->json(['mensaje' => 'Se archivó el comunicado: ya nadie lo ve en su panel.']);
    }

    /** Solo sus propios borradores (nadie los ha visto). */
    public function destroy(Comunicado $comunicado): JsonResponse
    {
        abort_unless($comunicado->autor?->role === RolUsuario::AdminGeneral, 403);
        if ($comunicado->estado !== 'borrador') {
            return response()->json(['message' => 'Solo se eliminan los borradores.'], 422);
        }
        Auditoria::registrar('eliminar', $comunicado, "Eliminó el borrador «{$comunicado->titulo}».");
        $this->comunicacion->eliminar($comunicado);

        return response()->json(['mensaje' => 'Se eliminó el borrador.']);
    }

    private function descripcion(Comunicado $c, string $accion, array $sedes): string
    {
        $para = ['sede' => 'todos', 'estudiantes' => 'los estudiantes', 'docentes' => 'los docentes', 'acudientes' => 'los acudientes'][$c->destino] ?? 'su grupo';
        $donde = count($sedes) === Sede::count() && count($sedes) > 1 ? 'todas las sedes' : Sede::whereIn('id', $sedes)->orderBy('nombre')->pluck('nombre')->join(', ', ' y ');
        $como = match ($accion) {
            'borrador'  => 'como borrador',
            'programar' => 'programado para el '.$c->publicado_en->format('d/m/Y H:i'),
            'editar'    => 'ya publicado',
            default     => 'y lo publicó',
        };
        $verbo = $accion === 'editar' ? 'Editó' : 'Creó';

        return "{$verbo} el comunicado «{$c->titulo}» para {$para} en {$donde} {$como}.";
    }
}
