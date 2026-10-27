<?php

namespace App\Http\Controllers\Admin\Contabilidad;

use App\Http\Controllers\Controller;
use App\Services\Finanzas\ContabilidadService;
use Illuminate\View\View;

/**
 * Panel Admin › Contabilidad (Finanzas): ingresos del mes (lo recaudado en Pagos) contra los egresos de
 * la sede (los que registra la admin + las devoluciones de Matrículas). Registrar, editar y anular un
 * egreso: EgresoController.
 */
class ContabilidadController extends Controller
{
    public function __construct(private ContabilidadService $contabilidad) {}

    public function index(): View
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return view('admin.contabilidad.index', $this->contabilidad->datosSede($sedeId));
    }
}
