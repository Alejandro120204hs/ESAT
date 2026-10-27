<?php

namespace App\Http\Controllers\Admin\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\ReporteService;
use Illuminate\View\View;

/**
 * Panel Admin › Reportes: los documentos formales que la sede entrega (Secretaría de Educación, aula y
 * administración), por mes. La página recibe los datos con sus fechas y reportes.js arma cada documento como
 * estaba al cierre del mes elegido. Solo consulta: no guarda nada.
 */
class ReporteController extends Controller
{
    public function __construct(private ReporteService $reportes) {}

    public function index(): View
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return view('admin.reportes.index', ['datos' => $this->reportes->datosSede((int) $sedeId)]);
    }
}
