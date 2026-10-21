<?php

namespace App\Http\Controllers\Admin\Pagos;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Pago;
use App\Services\Finanzas\CarteraService;
use Illuminate\View\View;

/**
 * Panel Admin › Pagos (Finanzas): quién debe, los pagos recibidos, el resumen de caja por fechas,
 * lo recaudado por programa y las devoluciones de la sede. Registrar y anular un pago se hace con
 * las mismas rutas de Matrículas (Admin\Matriculas\PagoController), así la regla de cobro, el recibo
 * y el historial de la matrícula son uno solo.
 */
class CarteraController extends Controller
{
    public function __construct(private CarteraService $cartera) {}

    public function index(): View
    {
        return view('admin.pagos.index', $this->cartera->datosSede($this->sedeId()) + [
            'metodos' => Pago::METODOS,
        ]);
    }

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }
}
