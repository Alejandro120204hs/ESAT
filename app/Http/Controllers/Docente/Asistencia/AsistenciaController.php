<?php

namespace App\Http\Controllers\Docente\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\Asistencia\AsistenciaRequest;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Panel Docente › Asistencia: el docente toma y corrige la asistencia de las clases de sus grupos
 * (Admin › Asistencia solo la consulta).
 */
class AsistenciaController extends Controller
{
    public function __construct(private PanelDocenteService $panel) {}

    public function index(): View
    {
        return view('docente.asistencia.index', ['datos' => $this->panel->datos(auth()->user())]);
    }

    public function store(AsistenciaRequest $request): JsonResponse
    {
        $mensaje = $this->panel->guardarAsistencia($request->user(), $request->curso(), $request->input('fecha'), $request->input('registros'));

        return response()->json(['ok' => true, 'mensaje' => $mensaje]);
    }
}
