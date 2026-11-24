<?php

namespace App\Http\Controllers\Estudiante\Inicio;

use App\Http\Controllers\Controller;
use App\Services\Academico\PanelEstudianteService;
use Illuminate\View\View;

/** Panel Estudiante › Inicio: solo consulta (PanelEstudianteService::datos). */
class InicioController extends Controller
{
    public function __construct(private PanelEstudianteService $panel) {}

    public function index(): View
    {
        return view('estudiante.dashboard', ['datos' => $this->panel->datos(auth()->user())]);
    }
}
