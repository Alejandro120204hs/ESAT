<?php

namespace App\Http\Controllers\Admin\Matriculas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Matriculas\AnularPagoRequest;
use App\Http\Requests\Admin\Matriculas\DevolucionRequest;
use App\Http\Requests\Admin\Matriculas\PagoRequest;
use App\Models\Finanzas\Devolucion;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\Sistema\Auditoria;
use App\Services\Finanzas\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Panel Admin › Matrículas › Pagos: registrar un pago (monto libre, aplicado en orden, con recibo),
 * anular un pago mal registrado, devolver lo adelantado al retirarse o aplazarse, y ver/imprimir
 * recibos y comprobantes de devolución. Todo queda en el historial de la matrícula.
 */
class PagoController extends Controller
{
    public function __construct(private PagoService $pagos)
    {
    }

    public function store(PagoRequest $request, Matricula $matricula): JsonResponse
    {
        $r = $this->pagos->registrarPago($matricula, (float) $request->integer('valor'), $request->datosPago());

        $valor = $this->pesos($r->valor);
        Auditoria::registrar('editar', $matricula,
            "Registró un pago de {$matricula->estudiante->name} por {$valor} ({$r->metodo_pago}, recibo {$r->recibo}): {$this->detalle($r)}.");

        return response()->json([
            'ok'     => true,
            'recibo' => $r->recibo,
            'url'    => route('admin.matriculas.recibo', $r),
            'mensaje' => "Pago registrado por {$valor}. Recibo {$r->recibo}.",
        ]);
    }

    public function anular(AnularPagoRequest $request, Matricula $matricula, Recaudo $recaudo): JsonResponse
    {
        $recaudo->load('cobros');
        $detalle = $this->detalle($recaudo);
        $this->pagos->anularRecaudo($recaudo, $request->input('motivo'));

        Auditoria::registrar('editar', $matricula,
            "Anuló el pago de {$matricula->estudiante->name} por {$this->pesos($recaudo->valor)} (recibo {$recaudo->recibo}, {$detalle}): ".rtrim($request->input('motivo'), '.').'.');

        return response()->json(['ok' => true, 'mensaje' => "Se anuló el recibo {$recaudo->recibo}. Lo que cubría vuelve a figurar como pendiente."]);
    }

    /** Recibo para imprimir o guardar en PDF (también los anulados, marcados como tales). */
    public function recibo(Recaudo $recaudo): View
    {
        $recaudo->load(['matricula.estudiante', 'matricula.programa', 'matricula.curso', 'matricula.sede', 'cobros', 'registradoPor', 'anuladoPor']);
        abort_unless($recaudo->matricula->sede_id === (int) auth()->user()->sede_id, 404);

        $m     = $recaudo->matricula;
        $total = $m->plan_pago === 'mensual' ? $m->programa->duracion_meses : $m->programa->duracion_periodos;

        return view('admin.matriculas.recibo', [
            'recaudo' => $recaudo,
            'm'       => $m,
            'lineas'  => $recaudo->cobros->sortBy(fn (Pago $p) => [$p->fecha_vencimiento ? 0 : 1, $p->fecha_vencimiento?->toDateString(), $p->id])
                ->map(fn (Pago $p) => [
                    'concepto' => $p->tipo === 'matricula' ? 'Derecho de matrícula' : "Cuota {$p->numero_cuota} de {$total}",
                    'vence'    => $p->fecha_vencimiento,
                    'monto'    => (float) $p->pivot->monto,
                    'valor'    => (float) $p->monto_total,
                    // Abono si este recibo no cubrió todo el cobro
                    'abono'    => (float) $p->pivot->monto < (float) $p->monto_total,
                ])->values(),
        ]);
    }

    /** Devuelve lo adelantado de una matrícula retirada o aplazada (primero cubre lo vencido). */
    public function devolucion(DevolucionRequest $request, Matricula $matricula): JsonResponse
    {
        [$d, $cubierto] = $this->pagos->registrarDevolucion($matricula, $request->datos());
        $nombre = $matricula->estudiante->name;
        $cubre  = $cubierto > 0 ? "Con {$this->pesos($cubierto)} se cubrió lo que debía vencido. " : '';

        if (! $d) {
            Auditoria::registrar('editar', $matricula, "{$cubre}Lo adelantado de {$nombre} se usó completo para cubrir lo que debía; no quedó nada por devolver.");

            return response()->json(['ok' => true, 'mensaje' => "{$cubre}No quedó nada por devolver."]);
        }

        Auditoria::registrar('editar', $matricula,
            "{$cubre}Devolvió a {$nombre} {$this->pesos($d->valor)} pagados por adelantado ({$d->metodo_pago}, comprobante {$d->comprobante}): ".rtrim($d->motivo, '.').'.');

        return response()->json([
            'ok'          => true,
            'comprobante' => $d->comprobante,
            'mensaje'     => "{$cubre}Devolución de {$this->pesos($d->valor)} registrada. Comprobante {$d->comprobante}.",
        ]);
    }

    /** Comprobante de una devolución, para imprimir o guardar en PDF. */
    public function comprobanteDevolucion(Devolucion $devolucion): View
    {
        $devolucion->load(['matricula.estudiante', 'matricula.programa', 'matricula.curso', 'matricula.sede', 'cobros', 'registradoPor']);
        abort_unless($devolucion->matricula->sede_id === (int) auth()->user()->sede_id, 404);
        $m     = $devolucion->matricula;
        $total = $m->plan_pago === 'mensual' ? $m->programa->duracion_meses : $m->programa->duracion_periodos;

        return view('admin.matriculas.devolucion', [
            'devolucion' => $devolucion,
            'm'          => $m,
            'lineas'     => $devolucion->cobros->map(fn (Pago $p) => [
                'concepto' => $p->tipo === 'matricula' ? 'Derecho de matrícula' : "Cuota {$p->numero_cuota} de {$total}",
                'vence'    => $p->fecha_vencimiento,
                'monto'    => (float) $p->pivot->monto,
            ])->values(),
        ]);
    }

    /* ── Auxiliares ── */

    private function pesos(float|string $v): string
    {
        return '$ '.number_format((float) $v, 0, ',', '.');
    }

    /** "derecho de matrícula, cuota 1 y abono a la cuota 2" */
    private function detalle(Recaudo $r): string
    {
        return $r->cobros->map(function (Pago $p) {
            $nombre = $p->tipo === 'matricula' ? 'derecho de matrícula' : "cuota {$p->numero_cuota}";

            return (float) $p->pivot->monto < (float) $p->monto_total ? "abono a {$nombre} ({$this->pesos($p->pivot->monto)})" : $nombre;
        })->join(', ', ' y ');
    }
}
