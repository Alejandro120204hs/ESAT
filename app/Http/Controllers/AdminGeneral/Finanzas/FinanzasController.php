<?php

namespace App\Http\Controllers\AdminGeneral\Finanzas;

use App\Http\Controllers\Controller;
use App\Services\General\FinanzasService;
use Illuminate\View\View;

/** Finanzas de todas las sedes (recaudo, cartera, contabilidad y devoluciones): solo consulta. Cobrar y registrar egresos se hace entrando a la sede. */
class FinanzasController extends Controller
{
    public function index(FinanzasService $finanzas): View
    {
        return view('admin_general.finanzas.index', ['datos' => $finanzas->datos()]);
    }
}
