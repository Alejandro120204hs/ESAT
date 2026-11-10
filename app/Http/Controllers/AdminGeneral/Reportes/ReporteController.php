<?php

namespace App\Http\Controllers\AdminGeneral\Reportes;

use App\Http\Controllers\Controller;
use App\Services\General\FinanzasService;
use Illuminate\View\View;

/** Reportes de todas las sedes por mes (matriculados, novedades, recaudo, ingresos y egresos, cartera): solo consulta. */
class ReporteController extends Controller
{
    public function index(FinanzasService $finanzas): View
    {
        return view('admin_general.reportes.index', ['datos' => $finanzas->datos()]);
    }
}
