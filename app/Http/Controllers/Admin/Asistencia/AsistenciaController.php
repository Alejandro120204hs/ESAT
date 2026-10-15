<?php

namespace App\Http\Controllers\Admin\Asistencia;

use App\Http\Controllers\Controller;
use App\Services\Academico\AsistenciaService;
use Illuminate\View\View;

/**
 * Panel Admin › Asistencia: solo consulta. La asistencia la toma el docente desde su panel;
 * aquí se ve qué grupos y estudiantes faltan y qué clases quedaron sin registrar.
 */
class AsistenciaController extends Controller
{
    public function __construct(private AsistenciaService $asistencia) {}

    public function index(): View
    {
        return view('admin.asistencia.index', $this->asistencia->resumenSede($this->sedeId()));
    }

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }
}
