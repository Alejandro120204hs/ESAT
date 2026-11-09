<?php

namespace App\Http\Controllers\Docente\Grupos;

use App\Http\Controllers\Controller;
use App\Services\Academico\PanelDocenteService;
use Illuminate\View\View;

/**
 * Panel Docente › Mis grupos: los grupos que tiene a cargo (vigentes y finalizados) con su ficha de
 * estudiantes, malla y horario. Solo consulta; registra desde Asistencia y Notas.
 */
class GrupoController extends Controller
{
    public function __construct(private PanelDocenteService $panel) {}

    public function index(): View
    {
        return view('docente.grupos.index', ['datos' => $this->panel->datos(auth()->user())]);
    }
}
