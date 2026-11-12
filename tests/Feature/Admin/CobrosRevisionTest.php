<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\User;
use App\Services\Academico\MatriculaService;
use App\Services\Finanzas\CarteraService;
use App\Services\Finanzas\PagoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Hallazgos de dinero de la segunda revisión: resumen de caja (M25), recibos de matrículas anuladas (B26), doble
 * devolución (B27), plan por periodo (B28), total de cuotas (B29), borrado en cascada (B35), cambio de año del
 * consecutivo y el comando pagos:vencer. Programa de prueba: 10 meses, $ 3.000.000, derecho de $ 200.000 y cuota del
 * sistema de $ 2.000 al mes.
 */
class CobrosRevisionTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private PagoService $pagos;
    private Programa $programa;
    private Curso $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        $this->actingAs($this->admin);
        $this->pagos    = app(PagoService::class);
        $this->programa = $this->programa(['Mañana'], 1600, 10);
        $this->grupo    = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-09-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function matricula(?string $plan = 'mensual', ?Curso $grupo = null): Matricula
    {
        $grupo ??= $this->grupo;
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'fecha_nacimiento' => '1998-05-10']);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));
        $m = app(MatriculaService::class)->matricular($e, $grupo, null);
        if ($plan) {
            $m->update(['plan_pago' => $plan]);
            $this->pagos->generarCuotas($m->fresh(), $grupo->fecha_inicio);
        }

        return $m->fresh();
    }

    private function pagar(Matricula $m, int $valor, string $fecha = '2026-10-21')
    {
        return $this->pagos->registrarPago($m->fresh(), $valor, ['fecha_pago' => $fecha, 'metodo_pago' => 'Efectivo']);
    }

    /** M25: la caja cuenta cada recibo el día en que se registró, aunque la fecha del pago sea otra. */
    public function test_la_caja_recibe_el_dia_en_que_se_registro_cada_pago(): void
    {
        $r = $this->pagar($this->matricula(), 200000, '2026-10-01');

        $recibo = app(CarteraService::class)->datosSede($this->sede->id)['recaudos']->firstWhere('id', $r->id);
        $this->assertSame(['2026-10-01', '2026-10-21'], [$recibo['fecha'], $recibo['registrado_en']]);

        Carbon::setTestNow('2026-11-03 10:00');
        $this->pagos->anularRecaudo($r->fresh(), 'Se digitó mal');
        $recibo = app(CarteraService::class)->datosSede($this->sede->id)['recaudos']->firstWhere('id', $r->id);
        $this->assertSame(['2026-10-21', '2026-11-03'], [$recibo['registrado_en'], $recibo['anulado']['fecha']],
            'Octubre conserva el recibo y la anulación cae en noviembre');
    }

    /** B26: los recibos (anulados) de una matrícula anulada siguen en Pagos recibidos; la matrícula no sale en Por cobrar. */
    public function test_los_recibos_de_una_matricula_anulada_no_desaparecen(): void
    {
        $m = $this->matricula();
        $r = $this->pagar($m, 200000);
        $this->patchJson("/admin/matriculas/{$m->id}/recaudos/{$r->id}/anular", ['motivo' => 'Se registró por error'])->assertOk();
        $this->patchJson("/admin/matriculas/{$m->id}/estado", ['estado' => 'anulado', 'fecha' => '2026-10-21', 'motivo' => 'Matrícula hecha por error'])->assertOk();

        $datos = app(CarteraService::class)->datosSede($this->sede->id);
        $this->assertNotNull($datos['recaudos']->firstWhere('id', $r->id), 'El RC sigue en la lista, marcado como anulado');
        $mat = $datos['matriculas']->firstWhere('id', $m->id);
        $this->assertSame('anulado', $mat['estado']);
        $this->assertSame([], collect($mat['cobros'])->filter(fn ($c) => $c[6] !== 'anulado')->values()->all(), 'No debe nada');
    }

    /** B27: el segundo envío de una devolución (cuando ya no queda nada) se rechaza y no deja auditoría falsa. */
    public function test_la_devolucion_sin_dinero_adelantado_se_rechaza_dentro_del_bloqueo(): void
    {
        $m = $this->matricula();
        $this->pagar($m, 200000 + 2 * 302000 + 3 * 302000);   // derecho y cuotas hasta enero
        app(MatriculaService::class)->cambiarEstado($m->fresh(), 'retirado', today(), 'Cambio de ciudad');
        [$d] = $this->pagos->registrarDevolucion($m->fresh(), ['fecha' => '2026-10-21', 'metodo_pago' => 'Efectivo', 'motivo' => 'Retiro']);
        $this->assertNotNull($d);

        $this->expectException(ValidationException::class);
        $this->pagos->registrarDevolucion($m->fresh(), ['fecha' => '2026-10-21', 'metodo_pago' => 'Efectivo', 'motivo' => 'Retiro']);
    }

    /** B28: 8 meses por trimestre son 3 + 3 + 2 meses: vencen al empezar cada uno y la cuota del sistema suma 8 meses. */
    public function test_plan_por_periodo_con_el_ultimo_periodo_mas_corto(): void
    {
        $programa = $this->programa(['Mañana'], 1600, 8);
        $programa->update(['tipo_periodo' => 'trimestre', 'duracion_periodos' => Programa::calcularPeriodos(8, 'trimestre')]);
        $m = $this->matricula('periodo', $this->grupo($programa, 'Mañana', null, 'activo', '2026-09-01'));

        $cuotas = $m->pagos()->where('tipo', 'periodo')->orderBy('numero_cuota')->get();
        $this->assertSame(['2026-09-10', '2026-12-10', '2027-03-10'], $cuotas->map(fn (Pago $p) => $p->fecha_vencimiento->toDateString())->all());
        $this->assertSame([6000, 6000, 4000], $cuotas->map(fn (Pago $p) => (int) $p->monto_sistema)->all());
        $this->assertSame(3000000, (int) $cuotas->sum('monto_programa'));
        $this->assertSame(3, $m->totalCuotas());
    }

    /** B28: el plan por periodo con una duración exacta sigue igual (12 meses por semestre: 2 cuotas de 6 meses). */
    public function test_plan_por_periodo_exacto(): void
    {
        $programa = $this->programa(['Mañana'], 1600, 12);
        $m = $this->matricula('periodo', $this->grupo($programa, 'Mañana', null, 'activo', '2026-09-01'));

        $cuotas = $m->pagos()->where('tipo', 'periodo')->orderBy('numero_cuota')->get();
        $this->assertSame(['2026-09-10', '2027-03-10'], $cuotas->map(fn (Pago $p) => $p->fecha_vencimiento->toDateString())->all());
        $this->assertSame([12000, 12000], $cuotas->map(fn (Pago $p) => (int) $p->monto_sistema)->all());
    }

    /** B29: "Cuota N de total" sale de las cuotas de la matrícula, no de la duración de hoy del programa. */
    public function test_el_total_de_cuotas_no_cambia_con_la_duracion_del_programa(): void
    {
        $m = $this->matricula();
        $m->update(['estado' => 'graduado', 'estado_fecha' => '2026-10-20']);
        $this->programa->update(['duracion_meses' => 12]);   // sin matrículas vigentes ya se puede cambiar

        $this->assertSame(10, $m->fresh()->totalCuotas());
        $cobros = collect(app(CarteraService::class)->datosSede($this->sede->id)['matriculas']->firstWhere('id', $m->id)['cobros']);
        $this->assertTrue($cobros->contains(fn ($c) => $c[2] === 'Cuota 10 de 10'));
        $this->assertSame(10, $this->pagos->resumen($m->fresh()->load('pagos'))['cuotas_total']);
    }

    /** B29: al aplazar, las cuotas anuladas siguen contando en el total del plan. */
    public function test_el_total_de_cuotas_cuenta_las_anuladas(): void
    {
        $m = $this->matricula();
        app(MatriculaService::class)->cambiarEstado($m->fresh(), 'aplazado', today(), 'Viaje familiar');

        $this->assertSame(10, $m->fresh()->totalCuotas());
    }

    /** B35: la BD no deja borrar a un estudiante con matrícula (se llevaría cobros y recibos). */
    public function test_no_se_borra_un_estudiante_con_matricula(): void
    {
        $m = $this->matricula();
        $this->pagar($m, 200000);

        $this->expectException(QueryException::class);
        $m->estudiante->delete();
    }

    /** B35: tampoco una matrícula con recibos. */
    public function test_no_se_borra_una_matricula_con_recibos(): void
    {
        $m = $this->matricula();
        $this->pagar($m, 200000);

        $this->expectException(QueryException::class);
        $m->delete();
    }

    /** B35: tampoco la cuenta de quien registró un recibo (quedaría sin autor); se desactiva en su lugar. */
    public function test_no_se_borra_la_cuenta_que_registro_un_recibo(): void
    {
        $this->pagar($this->matricula(), 200000);

        $this->expectException(QueryException::class);
        $this->admin->delete();
    }

    /** El consecutivo de recibos arranca de nuevo con el año. */
    public function test_el_consecutivo_de_recibos_cambia_de_ano(): void
    {
        $m = $this->matricula();
        Carbon::setTestNow('2026-12-31 17:00');
        $this->assertSame('RC-2026-00001', $this->pagar($m, 100000, '2026-12-31')->recibo);
        $this->assertSame('RC-2026-00002', $this->pagar($m, 100000, '2026-12-31')->recibo);

        Carbon::setTestNow('2027-01-02 08:00');
        $this->assertSame('RC-2027-00001', $this->pagar($m, 100000, '2027-01-02')->recibo);
    }

    /** El comando pagos:vencer guarda como vencidos los cobros pendientes que ya pasaron su fecha. */
    public function test_el_comando_pagos_vencer(): void
    {
        $m = $this->matricula();
        $m->pagos()->update(['estado' => 'pendiente']);

        $this->artisan('pagos:vencer')->assertSuccessful();

        $this->assertSame('vencido', $m->pagos()->where('numero_cuota', 1)->value('estado'));   // venció el 10-sep
        $this->assertSame('pendiente', $m->pagos()->where('numero_cuota', 3)->value('estado'));  // vence el 10-nov
    }
}
