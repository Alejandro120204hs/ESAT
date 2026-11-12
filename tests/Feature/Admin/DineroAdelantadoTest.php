<?php

namespace Tests\Feature\Admin;

use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Institucional\Configuracion;
use App\Models\User;
use App\Services\Academico\MatriculaService;
use App\Services\Finanzas\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El dinero que un estudiante pagó no se pierde ni se le vuelve a cobrar al aplazarse, retirarse o cambiar de programa
 * (HALLAZGOS A4, A5, A6 y A9). Programa de prueba: 10 meses, $ 3.000.000 (cuotas de $ 300.000 más la cuota del sistema) y
 * derecho de $ 200.000. Al retirarse antes de clases se devuelve el 50 % del derecho pagado (Configuración).
 */
class DineroAdelantadoTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private PagoService $pagos;
    private MatriculaService $matriculas;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 10:00');
        $this->prepararSede();
        $this->actingAs($this->admin);
        $this->pagos = app(PagoService::class);
        $this->matriculas = app(MatriculaService::class);
    }

    /** Matrícula mensual en un grupo que empezó el 7-sep: cuotas del 10-sep al 10-jun. */
    private function matricula(string $inicioGrupo = '2026-09-07'): Matricula
    {
        $grupo = $this->grupo($this->programa(['Mañana'], 1600, 10), 'Mañana', null, 'activo', $inicioGrupo);
        $m = $this->matriculas->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $grupo, null);
        $m->update(['plan_pago' => 'mensual']);
        $this->pagos->generarCuotas($m->fresh(), $grupo->fecha_inicio);

        return $m->fresh();
    }

    /** Paga todo lo que vence hasta $hasta (derecho y cuotas) en un solo recibo. */
    private function pagarHasta(Matricula $m, string $hasta)
    {
        $valor = $this->pagos->cobrosPorPagar($m)->filter(fn (Pago $p) => ! $p->fecha_vencimiento || $p->fecha_vencimiento->toDateString() <= $hasta)
            ->sum(fn (Pago $p) => $p->restante());

        return $this->pagos->registrarPago($m, $valor, ['fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);
    }

    /** Valor de una cuota mensual (programa + cuota del sistema). */
    private function cuota(Matricula $m): int
    {
        return (int) round((float) Pago::where('matricula_id', $m->id)->where('tipo', 'mensual')->value('monto_total'));
    }

    private function porPagar(Matricula $m): int
    {
        return (int) round($this->pagos->cobrosPorPagar($m->fresh())->sum(fn (Pago $p) => $p->restante()));
    }

    /** A5: pagó dic, ene y feb por adelantado, se aplaza y después se retira: los tres meses siguen reconocidos. */
    public function test_dos_cambios_de_estado_no_olvidan_lo_pagado_por_adelantado(): void
    {
        $m = $this->matricula();
        $this->pagarHasta($m, '2027-02-10');                                    // hasta febrero

        Carbon::setTestNow('2026-11-20 10:00');
        $this->matriculas->cambiarEstado($m->fresh(), 'aplazado', today(), 'Viaje familiar');
        $this->assertSame(3 * $this->cuota($m), $this->pagos->totalAdelantado($m->fresh()));   // dic + ene + feb

        Carbon::setTestNow('2026-12-20 10:00');
        $this->matriculas->cambiarEstado($m->fresh(), 'retirado', today(), 'No vuelve');
        $this->assertSame(3 * $this->cuota($m), $this->pagos->totalAdelantado($m->fresh()), 'El retiro no puede olvidar lo pagado para los meses del aplazamiento');
    }

    /** A5 (2ª): cambio de programa desde una matrícula aplazada: se traslada todo lo adelantado. */
    public function test_el_cambio_de_programa_desde_un_aplazamiento_traslada_todo_lo_adelantado(): void
    {
        $m = $this->matricula();
        $this->pagarHasta($m, '2027-02-10');
        Carbon::setTestNow('2026-11-20 10:00');
        $this->matriculas->cambiarEstado($m->fresh(), 'aplazado', today(), 'Viaje familiar');

        Carbon::setTestNow('2026-12-20 10:00');
        $otro = $this->grupo($this->programa(['Mañana'], 1600, 10), 'Mañana', null, 'activo', '2027-01-11');
        $nueva = $this->matriculas->cambiarPrograma($m->fresh(), $otro, 'Prefiere otro programa');

        $trasladado = (int) round($nueva->saldo_favor + $nueva->pagos()->get()->sum(fn (Pago $p) => $p->disponible()));
        $this->assertSame(3 * $this->cuota($m), $trasladado);
    }

    /** A4: anular un recibo de una matrícula aplazada no revive las cuotas de después del aplazamiento. */
    public function test_anular_un_recibo_de_una_aplazada_no_revive_cuotas_que_ya_no_debe(): void
    {
        $m = $this->matricula();
        $recibo = $this->pagarHasta($m, '2027-02-10');
        Carbon::setTestNow('2026-11-20 10:00');
        $this->matriculas->cambiarEstado($m->fresh(), 'aplazado', today(), 'Viaje familiar');

        $this->pagos->anularRecaudo($recibo, 'Se digitó mal');

        // Debe solo lo que ya había vencido al aplazarse: derecho + sep + oct + nov
        $this->assertSame(200000 + 3 * $this->cuota($m), $this->porPagar($m));
    }

    /** A4: en una retirada, después de anular el recibo y pagar lo que de verdad debía, queda a paz y salvo. */
    public function test_una_retirada_con_un_recibo_anulado_puede_quedar_a_paz_y_salvo(): void
    {
        $m = $this->matricula();
        $recibo = $this->pagarHasta($m, '2027-02-10');
        Carbon::setTestNow('2026-11-20 10:00');
        $this->matriculas->cambiarEstado($m->fresh(), 'retirado', today(), 'Cambio de ciudad');

        $this->pagos->anularRecaudo($recibo, 'Se digitó mal');
        $debe = $this->porPagar($m);
        $this->assertSame(200000 + 3 * $this->cuota($m), $debe);
        $this->pagos->registrarPago($m->fresh(), $debe, ['fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);
        $this->assertSame(0, $this->porPagar($m));
    }

    /** El derecho de matrícula de la matrícula. */
    private function derecho(Matricula $m): Pago
    {
        return Pago::where('matricula_id', $m->id)->where('tipo', 'matricula')->firstOrFail();
    }

    /**
     * A9: abonó parte del derecho y se retira antes de empezar clases: no queda debiendo y se le puede devolver el
     * porcentaje de Configuración (50 % por defecto) de lo abonado; el resto lo retiene ESAT.
     */
    public function test_retirarse_antes_de_clases_con_el_derecho_a_medio_pagar(): void
    {
        $m = $this->matricula('2026-12-07');                                    // las clases empiezan en diciembre
        $this->pagos->registrarPago($m, 100000, ['fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);

        $this->matriculas->cambiarEstado($m->fresh(), 'retirado', today(), 'No alcanzó a empezar');

        $this->assertSame(0, $this->porPagar($m));
        $this->assertSame(50000, $this->pagos->totalAdelantado($m->fresh()));   // el 50 % de lo abonado
        $this->assertSame(50000, (int) $this->derecho($m)->retenido);
        Carbon::setTestNow('2027-01-15 10:00');
        $this->assertSame(0, $this->porPagar($m), 'Al empezar el grupo tampoco aparece como deuda');
    }

    /** Derecho pagado completo y retiro antes de clases: se devuelve el porcentaje de Configuración, ni más ni menos. */
    public function test_el_derecho_pagado_se_devuelve_segun_el_porcentaje_configurado(): void
    {
        foreach ([50 => 100000, 100 => 200000, 0 => 0] as $pct => $devolvible) {
            Configuracion::guardar('devolucion_derecho_pct', $pct);
            $m = $this->matricula('2026-12-07');
            $this->pagos->registrarPago($m, 200000, ['fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);

            $this->matriculas->cambiarEstado($m->fresh(), 'retirado', today(), 'No alcanzó a empezar');

            $this->assertSame($devolvible, $this->pagos->totalAdelantado($m->fresh()), "Con {$pct} %");
            $this->assertSame(200000 - $devolvible, (int) $this->derecho($m)->retenido, "Con {$pct} %");
            $this->assertSame($devolvible, $this->pagos->dineroEn($m->fresh()), 'Lo retenido ya no es dinero del estudiante');
        }
    }

    /** La devolución entrega solo lo devolvible; lo retenido se queda en el cobro. */
    public function test_la_devolucion_no_entrega_lo_retenido(): void
    {
        $m = $this->matricula('2026-12-07');
        $this->pagos->registrarPago($m, 200000, ['fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);
        $this->matriculas->cambiarEstado($m->fresh(), 'retirado', today(), 'No alcanzó a empezar');

        $this->postJson("/admin/matriculas/{$m->id}/devoluciones", ['fecha' => today()->toDateString(), 'metodo_pago' => 'Efectivo', 'motivo' => 'Retiro antes de clases'])
            ->assertSuccessful();

        $d = $this->derecho($m);
        $this->assertSame([100000, 100000], [(int) $d->reembolsado, (int) $d->retenido]);
        $this->assertSame(0, $this->pagos->totalAdelantado($m->fresh()));
    }

    /** Anular el recibo del derecho de quien se retiró antes de clases: no queda deuda ni nada retenido. */
    public function test_anular_el_recibo_del_derecho_retenido_lo_deja_en_cero(): void
    {
        $m = $this->matricula('2026-12-07');
        $recibo = $this->pagos->registrarPago($m, 200000, ['fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);
        $this->matriculas->cambiarEstado($m->fresh(), 'retirado', today(), 'No alcanzó a empezar');

        $this->pagos->anularRecaudo($recibo, 'Se digitó mal');

        $d = $this->derecho($m);
        $this->assertSame([0, 0, 'anulado'], [(int) $d->abonado, (int) $d->retenido, $d->estado]);
        $this->assertSame(0, $this->porPagar($m));
        $this->assertSame(0, $this->pagos->totalAdelantado($m->fresh()));
    }

    /** Con las clases ya empezadas el derecho se causó: no se anula ni se retiene nada. */
    public function test_retirarse_despues_de_empezar_no_toca_el_derecho_pagado(): void
    {
        $m = $this->matricula();                                                 // empezó el 7-sep
        $this->pagarHasta($m, '2026-11-30');
        $this->matriculas->cambiarEstado($m->fresh(), 'retirado', today(), 'Cambio de ciudad');

        $d = $this->derecho($m);
        $this->assertSame(['pagado', 0], [$d->estado, (int) $d->retenido]);
    }

    /** El cambio de programa no es un retiro: el derecho pagado sirve para la matrícula nueva y no se retiene nada. */
    public function test_el_cambio_de_programa_antes_de_clases_no_retiene_el_derecho(): void
    {
        $m = $this->matricula('2026-12-07');
        $this->pagos->registrarPago($m, 200000, ['fecha_pago' => today()->toDateString(), 'metodo_pago' => 'Efectivo']);
        $otro = $this->grupo($this->programa(['Mañana'], 1600, 10), 'Mañana', null, 'activo', '2027-01-11');

        $nueva = $this->matriculas->cambiarPrograma($m->fresh(), $otro, 'Prefiere otro programa');

        $this->assertSame(0, (int) $this->derecho($m)->retenido);
        $this->assertFalse($nueva->pagos()->where('tipo', 'matricula')->exists(), 'No se le cobra el derecho otra vez');
    }

    /** A6: una matrícula con plata trasladada de otra no se anula (ese dinero desaparecería); se retira. */
    public function test_no_se_anula_una_matricula_con_dinero_trasladado(): void
    {
        $m = $this->matricula();
        $this->pagarHasta($m, '2027-02-10');
        Carbon::setTestNow('2026-11-20 10:00');
        $otro = $this->grupo($this->programa(['Mañana'], 1600, 10), 'Mañana', null, 'activo', '2027-01-11');
        $nueva = $this->matriculas->cambiarPrograma($m->fresh(), $otro, 'Prefiere otro programa');

        $this->patchJson("/admin/matriculas/{$nueva->id}/estado", ['estado' => 'anulado', 'fecha' => today()->toDateString(), 'motivo' => 'Se hizo por error'])
            ->assertJsonValidationErrors('estado');
        $this->assertSame('activo', $nueva->fresh()->estado);
    }
}
