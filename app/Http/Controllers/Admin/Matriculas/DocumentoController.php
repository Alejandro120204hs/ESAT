<?php

namespace App\Http\Controllers\Admin\Matriculas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Matricula;
use App\Services\Finanzas\PagoService;
use Illuminate\View\View;

/**
 * Panel Admin › Matrículas › Documentos imprimibles para el estudiante:
 * - Constancia de matrícula: solo de una matrícula vigente (activa o aplazada).
 * - Paz y salvo: solo si no debe nada en ninguna de sus matrículas de la sede y ninguna vigente
 *   está sin plan de pago (sin plan no hay cuotas, pero tampoco ha pagado el programa).
 */
class DocumentoController extends Controller
{
    public function __construct(private PagoService $pagos)
    {
    }

    public function constancia(Matricula $matricula): View
    {
        $this->asegurarSede($matricula);
        abort_unless(in_array($matricula->estado, Matricula::VIGENTES, true), 404);
        $matricula->load(['estudiante', 'programa', 'curso', 'sede']);

        return view('admin.matriculas.constancia', ['m' => $matricula]);
    }

    public function pazYSalvo(Matricula $matricula): View
    {
        $this->asegurarSede($matricula);
        $matricula->load(['estudiante', 'programa', 'sede']);

        $matriculas = Matricula::where('estudiante_id', $matricula->estudiante_id)->where('sede_id', $matricula->sede_id)
            ->where('estado', '!=', 'anulado')->with(['pagos', 'recaudos', 'programa'])->orderBy('fecha_inicio')->get();
        $debe = $matriculas->sum(fn (Matricula $m) => $this->pagos->resumen($m)['por_pagar']);
        // Sin plan elegido "no debe nada" no significa que haya pagado el programa
        $sinPlan = $matriculas->contains(fn (Matricula $m) => in_array($m->estado, Matricula::VIGENTES, true) && ! $m->plan_pago);
        abort_if($debe > 0 || $sinPlan, 404);

        return view('admin.matriculas.paz-y-salvo', ['m' => $matricula, 'matriculas' => $matriculas]);
    }

    private function asegurarSede(Matricula $matricula): void
    {
        abort_unless($matricula->sede_id === (int) auth()->user()->sede_id, 404);
    }
}
