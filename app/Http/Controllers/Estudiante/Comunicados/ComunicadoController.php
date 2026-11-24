<?php

namespace App\Http\Controllers\Estudiante\Comunicados;

use App\Http\Controllers\Controller;
use App\Models\Comunicacion\Comunicado;
use App\Models\User;
use App\Services\Academico\PanelEstudianteService;
use App\Services\Comunicacion\ComunicadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel Estudiante › Comunicados: los que le llegan (PanelEstudianteService::comunicadosVisibles, los destinatarios de
 * siempre). Abrir uno lo marca leído en comunicado_lecturas, y así la secretaría ve lecturas reales.
 */
class ComunicadoController extends Controller
{
    public function __construct(private PanelEstudianteService $panel) {}

    public function index(): View
    {
        return view('estudiante.comunicados.index', ['datos' => $this->panel->datos(auth()->user())]);
    }

    public function leido(Comunicado $comunicado): JsonResponse
    {
        $this->visible($comunicado);
        DB::table('comunicado_lecturas')->insertOrIgnore([
            'comunicado_id' => $comunicado->id, 'user_id' => auth()->id(), 'leido_en' => now(),
        ]);

        return response()->json(['ok' => true, 'mensaje' => '', 'datos' => $this->panel->datos(User::findOrFail(auth()->id()))]);
    }

    public function adjunto(Comunicado $comunicado): StreamedResponse
    {
        $this->visible($comunicado);
        abort_unless($comunicado->adjunto_ruta, 404);
        $disco = Storage::disk(ComunicadoService::disco());
        abort_unless($disco->exists($comunicado->adjunto_ruta), 404);

        return $disco->response($comunicado->adjunto_ruta, $comunicado->adjunto_nombre);
    }

    /** Solo los comunicados que le llegan (404 para cualquier otro: no se revela que existe). */
    private function visible(Comunicado $comunicado): void
    {
        $e = User::findOrFail(auth()->id());
        $ids = $this->panel->comunicadosVisibles($e, $this->panel->matricula($e))->pluck('id');
        abort_unless($ids->contains($comunicado->id), 404);
    }
}
