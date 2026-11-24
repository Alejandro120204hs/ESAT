<?php

namespace App\Http\Controllers\Estudiante\Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estudiante\Pagos\PlanRequest;
use App\Models\User;
use App\Services\Academico\PanelEstudianteService;
use App\Services\Finanzas\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Panel Estudiante › Mis pagos: solo consulta (se paga en la sede), salvo elegir el plan de pago, una sola vez
 * (PagoService::elegirPlan: las cuotas cuentan desde el inicio de clases del grupo).
 */
class PagoController extends Controller
{
    public function __construct(private PanelEstudianteService $panel, private PagoService $pagos) {}

    public function index(): View
    {
        return view('estudiante.pagos.index', ['datos' => $this->panel->datos(auth()->user())]);
    }

    public function plan(PlanRequest $request): JsonResponse
    {
        $plan = $request->input('plan');
        $this->pagos->elegirPlan($request->matricula(), $plan);
        $datos = $this->panel->datos(User::findOrFail(auth()->id()));
        $nombre = mb_strtolower($datos['pagos']['opciones'][$plan]['nombre'] ?? $plan);

        return response()->json([
            'ok'      => true,
            'datos'   => $datos,
            'mensaje' => 'Listo: tu plan es '.$nombre.'. Tus cuotas ya están abajo con su fecha de vencimiento.',
        ]);
    }
}
