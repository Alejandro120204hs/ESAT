<?php

namespace App\Http\Controllers\Estudiante\Programa;

use App\Http\Controllers\Controller;
use App\Services\Academico\PanelEstudianteService;
use Illuminate\View\View;

/** Panel Estudiante › Mi programa: notas por módulo y asistencia, solo consulta (las pone el docente). */
class ProgramaController extends Controller
{
    public function __construct(private PanelEstudianteService $panel) {}

    public function index(): View
    {
        return view('estudiante.programa.index', ['datos' => $this->panel->datos(auth()->user())]);
    }
}
