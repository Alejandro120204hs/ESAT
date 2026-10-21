<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Finanzas\CarteraService;
use App\Services\Finanzas\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Panel Admin › Pagos (Finanzas): la página por sede, los cobros y recibos que arma CarteraService
 * (con lo que se puede anular) y que registrar/anular desde aquí usa las mismas reglas de Matrículas.
 */
class PagosTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private Curso $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana'], 1600, 10);
        $this->grupo    = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-09-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_pagina_muestra_solo_las_matriculas_de_su_sede(): void
    {
        $m = $this->matriculaConPlan('1070000001');
        $otra = $this->otraSede();
        $this->programa->sedes()->attach($otra->id);
        $ajena = $this->matriculaConPlan('1070000002', $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-09-01', [], $otra), $otra->id);

        $this->actingAs($this->admin)->get('/admin/pagos')
            ->assertOk()
            ->assertViewHas('matriculas', fn ($ms) => $ms->pluck('id')->all() === [$m->id] && ! $ms->contains('id', $ajena->id));
    }

    public function test_cobros_con_lo_pagado_y_estado_actual(): void
    {
        $m = $this->matriculaConPlan('1070000001');
        // El grupo empezó el 1 de septiembre: lo más viejo es la cuota 1 (vence el 10 de septiembre, $ 302.000)
        app(PagoService::class)->registrarPago($m, 300000, ['fecha_pago' => '2026-10-21', 'metodo_pago' => 'Nequi', 'referencia' => 'N1']);

        $cobros = collect(app(CarteraService::class)->datosSede($this->sede->id)['matriculas']->firstWhere('id', $m->id)['cobros']);
        $cuota1 = $cobros->firstWhere(2, 'Cuota 1 de 10');

        $this->assertSame(['mensual', '2026-09-10', 302000, 300000, 'vencido'], [$cuota1[1], $cuota1[3], $cuota1[4], $cuota1[5], $cuota1[6]],
            'Queda abonada con 300.000 y sigue vencida por los 2.000 que faltan');
        $this->assertSame('pendiente', $cobros->firstWhere(1, 'matricula')[6]);
        $this->assertSame(['2026-09-10', '2026-10-10'], $cobros->where(1, 'mensual')->take(2)->pluck(3)->values()->all(), 'En orden de vencimiento');
    }

    public function test_recibos_con_lo_que_cubrieron_y_si_se_pueden_anular(): void
    {
        $m = $this->matriculaConPlan('1070000001');
        $r = app(PagoService::class)->registrarPago($m, 200000, ['fecha_pago' => '2026-10-21', 'metodo_pago' => 'Efectivo']);

        $recibo = app(CarteraService::class)->datosSede($this->sede->id)['recaudos']->firstWhere('id', $r->id);
        $this->assertSame([[$m->pagos()->where('numero_cuota', 1)->value('id'), 'Cuota 1 de 10', 200000]], $recibo['cubre']);
        $this->assertTrue($recibo['anulable']);
        $this->assertNull($recibo['anulado']);

        // Graduada: sus pagos ya no se anulan
        $m->update(['estado' => 'graduado', 'estado_fecha' => '2026-10-20']);
        $this->assertFalse(app(CarteraService::class)->datosSede($this->sede->id)['recaudos']->firstWhere('id', $r->id)['anulable']);
    }

    public function test_registrar_y_anular_desde_pagos_usa_las_reglas_de_matriculas(): void
    {
        $m = $this->matriculaConPlan('1070000001');

        // Con Nequi la referencia es obligatoria
        $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/pagos", ['valor' => 200000, 'fecha_pago' => '2026-10-21', 'metodo_pago' => 'Nequi'])
            ->assertUnprocessable()->assertJsonValidationErrors(['referencia']);

        $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/pagos", ['valor' => 200000, 'fecha_pago' => '2026-10-21', 'metodo_pago' => 'Nequi', 'referencia' => 'NEQ-1'])
            ->assertOk()->assertJsonFragment(['recibo' => 'RC-2026-00001']);
        $r = $m->recaudos()->first();

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/recaudos/{$r->id}/anular", ['motivo' => 'Se registró dos veces'])->assertOk();

        $recibo = app(CarteraService::class)->datosSede($this->sede->id)['recaudos']->firstWhere('id', $r->id);
        $this->assertSame('Se registró dos veces', $recibo['anulado']['motivo']);
        $this->assertFalse($recibo['anulable']);
        $this->assertDatabaseHas('auditorias', ['entidad_tipo' => 'Matricula', 'entidad_id' => $m->id, 'accion' => 'editar']);
    }

    /** Matrícula hecha por el flujo real (cobra el derecho de matrícula) y con el plan mensual ya elegido. */
    private function matriculaConPlan(string $documento, ?Curso $grupo = null, ?int $sedeId = null): Matricula
    {
        $grupo ??= $this->grupo;
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $sedeId ?? $this->sede->id, 'numero_documento' => $documento,
            'fecha_nacimiento' => '1998-05-10']);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));

        if ($sedeId && $sedeId !== $this->sede->id) {
            // De otra sede: basta con que exista
            return Matricula::create([
                'codigo' => 'EST-'.$documento, 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id, 'curso_id' => $grupo->id,
                'sede_id' => $sedeId, 'estado' => 'activo', 'estado_fecha' => '2026-08-20', 'fecha_inicio' => '2026-08-20',
            ]);
        }

        $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $grupo->id])->assertOk();
        $m = $e->matriculas()->first();
        $m->update(['plan_pago' => 'mensual']);
        app(PagoService::class)->generarCuotas($m, $grupo->fecha_inicio);

        return $m->fresh();
    }
}
