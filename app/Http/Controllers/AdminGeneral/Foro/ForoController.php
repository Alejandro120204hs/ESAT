<?php

namespace App\Http\Controllers\AdminGeneral\Foro;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Foro\EstadoTemaRequest;
use App\Http\Requests\AdminGeneral\Foro\OcultarMensajeRequest;
use App\Http\Requests\AdminGeneral\Foro\RespuestaRequest;
use App\Http\Requests\AdminGeneral\Foro\TemaRequest;
use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoReporte;
use App\Models\Comunicacion\ForoTema;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Comunicacion\ForoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * El foro de cada sede, visto desde el Admin: modera (ocultar con motivo, restaurar, descartar reportes, fijar, cerrar o
 * reabrir) y participa como Admin (abrir temas y responder). Usa ForoService, el mismo de la secretaría.
 */
class ForoController extends Controller
{
    private const ROLES = ['estudiante' => 'estudiante', 'docente' => 'docente', 'acudiente' => 'acudiente', 'admin' => 'secretaria', 'admin_general' => 'admin', 'superadmin' => 'admin'];

    public function __construct(private ForoService $foro) {}

    public function index(): View
    {
        $temas = ForoTema::with(['mensajes.autor', 'mensajes.ocultoPor', 'mensajes.reportes.usuario'])->orderBy('id')->get()
            ->filter(fn (ForoTema $t) => $t->mensajes->isNotEmpty())   // el primer mensaje es el que abre el tema
            ->values()
            ->map(fn (ForoTema $t) => [
                'id'        => $t->id,
                'sede_id'   => $t->sede_id,
                'categoria' => ForoTema::CATEGORIAS[$t->categoria] ?? $t->categoria,
                'titulo'    => $t->titulo,
                'fijado'    => (bool) $t->fijado,
                'cerrado'   => (bool) $t->cerrado_en,
                'mensajes'  => $t->mensajes->sortBy('id')->map(fn (ForoMensaje $m) => [
                    'id'       => $m->id,
                    'rol'      => self::ROLES[$m->autor?->role?->value] ?? 'secretaria',
                    'autor'    => $this->nombre($m->autor),
                    'texto'    => $m->texto,
                    'fecha'    => $m->created_at?->toIso8601String(),
                    'adjunto'  => $m->adjunto_nombre,
                    'oculto'   => $m->oculto_en ? ['motivo' => $m->motivo_oculto, 'por' => $this->nombre($m->ocultoPor), 'fecha' => $m->oculto_en->toIso8601String()] : null,
                    'reportes' => $m->reportes->map(fn (ForoReporte $r) => ['por' => $this->nombre($r->usuario), 'motivo' => $r->motivo, 'estado' => $r->estado])->values()->all(),
                ])->values()->all(),
            ])->all();

        return view('admin_general.foro.index', ['datos' => [
            'yo'         => $this->nombre(auth()->user()),
            'sedes'      => Sede::orderBy('nombre')->get(['id', 'nombre', 'ciudad'])->all(),
            'categorias' => array_values(ForoTema::CATEGORIAS),
            'foro'       => $temas,
        ]]);
    }

    public function store(TemaRequest $request): JsonResponse
    {
        $sede = Sede::findOrFail($request->integer('sede_id'));
        $t = $this->foro->crearTema($sede->id, $request->validated(), null);
        Auditoria::registrar('crear', $t, "Abrió el tema «{$t->titulo}» en el foro de {$sede->nombre}".($t->fijado ? ' y lo fijó arriba' : '').'.', $sede->id);

        return response()->json(['mensaje' => "Se publicó el tema en el foro de {$sede->nombre}.", 'id' => $t->id], 201);
    }

    public function responder(RespuestaRequest $request, ForoTema $tema): JsonResponse
    {
        $this->foro->responder($tema, $request->input('texto'), null);
        Auditoria::registrar('editar', $tema, "Respondió como Admin en el tema «{$tema->titulo}»: «".Str::limit($request->input('texto'), 80).'».', $tema->sede_id);

        return response()->json(['mensaje' => 'Se publicó tu respuesta.']);
    }

    /** Fijar o quitar de arriba (fijado), o cerrar o reabrir (cerrado). */
    public function estado(EstadoTemaRequest $request, ForoTema $tema): JsonResponse
    {
        if ($request->has('fijado')) {
            $si = $request->boolean('fijado');
            $this->foro->fijar($tema, $si);
            Auditoria::registrar('editar', $tema, ($si ? 'Fijó arriba' : 'Quitó de arriba').' el tema del foro «'.$tema->titulo.'».', $tema->sede_id);

            return response()->json(['mensaje' => $si ? 'El tema quedó fijado arriba.' : 'El tema ya no está fijado.']);
        }
        $si = $request->boolean('cerrado');
        $this->foro->cerrar($tema, $si);
        Auditoria::registrar($si ? 'desactivar' : 'activar', $tema, ($si ? 'Cerró' : 'Reabrió').' el tema del foro «'.$tema->titulo.'».', $tema->sede_id);

        return response()->json(['mensaje' => $si ? 'Se cerró el tema: nadie más puede responder.' : 'Se reabrió el tema.']);
    }

    /** Ocultar atiende los reportes pendientes; ocultar el primer mensaje oculta el tema. */
    public function ocultar(OcultarMensajeRequest $request, ForoMensaje $mensaje): JsonResponse
    {
        $primero = $mensaje->esPrimero();
        $motivo = trim($request->input('motivo'));
        $t = $this->foro->ocultar($mensaje, $motivo);
        $de = $this->nombre($mensaje->autor);
        Auditoria::registrar('desactivar', $mensaje, ($primero ? 'Ocultó el tema del foro «'.$t->titulo.'»' : "Ocultó un mensaje de {$de} en el tema «{$t->titulo}»").': '.rtrim($motivo, '.').'.', $t->sede_id);

        return response()->json(['mensaje' => $primero ? 'Se ocultó el tema: ya nadie lo ve.' : 'Se ocultó el mensaje.']);
    }

    public function restaurar(ForoMensaje $mensaje): JsonResponse
    {
        if (! $mensaje->oculto()) {
            return response()->json(['message' => 'Ese mensaje no está oculto.'], 422);
        }
        $t = $this->foro->restaurar($mensaje);
        Auditoria::registrar('activar', $mensaje, 'Restauró un mensaje oculto en el tema del foro «'.$t->titulo.'».', $t->sede_id);

        return response()->json(['mensaje' => 'Se restauró el mensaje.']);
    }

    public function descartar(ForoMensaje $mensaje): JsonResponse
    {
        if (! $mensaje->reportes()->where('estado', 'pendiente')->exists()) {
            return response()->json(['message' => 'Ese mensaje no tiene reportes por revisar.'], 422);
        }
        $t = $this->foro->descartarReportes($mensaje);
        Auditoria::registrar('editar', $mensaje, 'Descartó los reportes de un mensaje en el tema del foro «'.$t->titulo.'»: sigue visible.', $t->sede_id);

        return response()->json(['mensaje' => 'Se descartaron los reportes: el mensaje sigue visible.']);
    }

    private function nombre(?User $u): string
    {
        return $u ? trim($u->nombres.' '.$u->apellidos) : 'Cuenta eliminada';
    }
}
