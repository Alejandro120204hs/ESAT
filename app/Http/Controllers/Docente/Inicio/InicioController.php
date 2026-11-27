<?php

namespace App\Http\Controllers\Docente\Inicio;

use App\Http\Controllers\Controller;
use App\Services\Academico\PanelDocenteService;
use Illuminate\View\View;

/**
 * Panel Docente › Inicio: clases de hoy, lo que falta registrar, estudiantes bajo el 80 % y el horario
 * de la semana. Solo consulta; todo sale de los grupos que el docente tiene a cargo.
 */
class InicioController extends Controller
{
    public function __construct(private PanelDocenteService $panel) {}

    public function index(): View
    {
        return view('docente.dashboard', ['datos' => $this->panel->datos(auth()->user())]);
    }
}
