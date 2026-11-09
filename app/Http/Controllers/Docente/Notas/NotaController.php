<?php

namespace App\Http\Controllers\Docente\Notas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docente\Notas\NotasRequest;
use App\Models\Academico\ProgramaModulo;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Panel Docente › Notas: el docente pone y corrige la nota de cada módulo y la habilitación
 * (Admin › Calificaciones solo la consulta).
 */
class NotaController extends Controller
{
    public function __construct(private PanelDocenteService $panel) {}

    public function index(): View
    {
        return view('docente.notas.index', ['datos' => $this->panel->datos(auth()->user())]);
    }

    public function store(NotasRequest $request, string $curso, ProgramaModulo $modulo): JsonResponse
    {
        $mensaje = $this->panel->guardarNotas($request->user(), $request->curso(), $modulo, $request->notas());

        return response()->json(['ok' => true, 'mensaje' => $mensaje]);
    }
}
