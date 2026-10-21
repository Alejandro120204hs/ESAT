<?php

namespace App\Http\Controllers\Admin\Calificaciones;

use App\Http\Controllers\Controller;
use App\Services\Academico\CalificacionService;
use Illuminate\View\View;

/**
 * Panel Admin › Calificaciones: solo consulta. La nota la pone el docente desde su panel;
 * aquí se ven las notas por grupo y por estudiante, los módulos sin nota y quién está listo para graduar.
 */
class CalificacionController extends Controller
{
    public function __construct(private CalificacionService $calificaciones) {}

    public function index(): View
    {
        return view('admin.calificaciones.index', $this->calificaciones->resumenSede($this->sedeId()));
    }

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }
}
