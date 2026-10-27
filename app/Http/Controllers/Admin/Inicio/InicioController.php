<?php

namespace App\Http\Controllers\Admin\Inicio;

use App\Http\Controllers\Controller;
use App\Services\Inicio\InicioService;
use Illuminate\View\View;

/**
 * Panel Admin › Inicio: lo que está pasando hoy en la sede. Solo consulta; cada bloque enlaza a la pantalla donde se
 * actúa (Pagos, Asistencia, Calificaciones, Foro, Comunicados, Reportes).
 */
class InicioController extends Controller
{
    public function __construct(private InicioService $inicio) {}

    public function index(): View
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return view('admin.dashboard', $this->inicio->datosSede((int) $sedeId));
    }
}
