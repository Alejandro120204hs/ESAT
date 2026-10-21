<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Panel Admin › Matrículas: matricular (cupo, una vigente por estudiante, menor con acudiente),
 * cambio de grupo, estados (aplazar, retirar, graduar, reintegrar), plan de cuotas, pagos
 * con recibo y anulación. Más la regla de cupos con matrícula.curso_id.
 */
class MatriculasTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private Curso $grupoA;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana', 'Tarde'], 1600, 12, 2);   // cupo de 2 para probar el tope
        $this->grupoA   = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-11-03');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ── Matricular ── */

    public function test_matricula_en_un_grupo_y_cobra_el_derecho_de_matricula(): void
    {
        $e = $this->estudiante('1070000001');

        $this->matricular($e, $this->grupoA)->assertOk()->assertJsonFragment(['ok' => true]);

        $m = $e->matriculas()->first();
        $this->assertSame('activo', $m->estado);
        $this->assertSame($this->grupoA->id, $m->curso_id);
        $this->assertTrue($this->grupoA->estudiantes()->whereKey($e->id)->exists(), 'Queda en la lista del grupo');
        $cobro = $m->pagos()->first();
        $this->assertSame('matricula', $cobro->tipo);
        $this->assertSame('pendiente', $cobro->estado);
        $this->assertSame('2026-11-03', $cobro->fecha_vencimiento->toDateString(), 'Vence cuando empiezan las clases');
        $this->assertNull($m->plan_pago, 'El plan lo elige el estudiante desde su panel');
        $this->assertDatabaseHas('auditorias', ['accion' => 'crear', 'entidad_tipo' => 'Matricula', 'entidad_id' => $m->id]);
    }

    public function test_puede_pagar_el_derecho_de_matricula_al_matricular(): void
    {
        $e = $this->estudiante('1070000001');

        $this->matricular($e, $this->grupoA, ['pagar_matricula' => true, 'fecha_pago' => '2026-10-07', 'metodo_pago' => 'Nequi', 'referencia' => 'M123'])->assertOk();

        $m = $e->matriculas()->first();
        $this->assertSame('pagado', $m->pagos()->first()->estado);
        $recaudo = $m->recaudos()->first();
        $this->assertSame('RC-2026-00001', $recaudo->recibo);
        $this->assertSame($this->admin->id, $recaudo->registrado_por);
        $this->assertEquals(200000, (float) $recaudo->valor);
    }

    public function test_un_estudiante_solo_tiene_una_matricula_vigente(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        $otro = $this->grupo($this->programa(), 'Tarde');

        $this->matricular($e, $otro)->assertUnprocessable()->assertJsonValidationErrors(['estudiante_id']);
    }

    public function test_un_menor_sin_acudiente_no_se_matricula(): void
    {
        $menor = $this->estudiante('1070000001', 16);

        $this->matricular($menor, $this->grupoA)->assertUnprocessable()->assertJsonValidationErrors(['estudiante_id']);
    }

    public function test_sin_todos_los_documentos_no_se_matricula(): void
    {
        $e = $this->estudiante('1000000099', conDocumentos: false);
        $e->documentos()->where('documento', EstudianteDocumento::REQUERIDOS[0])->update(['entregado_en' => now()]);

        $this->matricular($e, $this->grupoA)->assertStatus(422)->assertJsonValidationErrors('estudiante_id');
        $this->assertSame(0, $e->matriculas()->count());

        $e->documentos()->update(['entregado_en' => now()]);
        $this->matricular($e, $this->grupoA)->assertOk();
    }

    public function test_respeta_el_cupo_y_los_grupos_finalizados(): void
    {
        $this->matricular($this->estudiante('1070000001'), $this->grupoA)->assertOk();
        $this->matricular($this->estudiante('1070000002'), $this->grupoA)->assertOk();

        $this->matricular($this->estudiante('1070000003'), $this->grupoA)->assertUnprocessable()->assertJsonValidationErrors(['curso_id']);

        $terminado = $this->grupo($this->programa, 'Tarde', null, 'finalizado', '2025-01-13');
        $this->matricular($this->estudiante('1070000004'), $terminado)->assertUnprocessable()->assertJsonValidationErrors(['curso_id']);
    }

    public function test_no_matricula_en_grupos_de_otra_sede(): void
    {
        $sede = $this->otraSede();
        $this->programa->sedes()->attach($sede->id);
        $ajeno = $this->grupo($this->programa, 'Tarde', null, 'activo', '2026-11-03', [], $sede);

        $this->matricular($this->estudiante('1070000001'), $ajeno)->assertUnprocessable()->assertJsonValidationErrors(['curso_id']);
    }

    /* ── Cambio de grupo ── */

    public function test_cambia_de_grupo_dentro_del_mismo_programa(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA);
        $m = $e->matriculas()->first();
        $grupoB = $this->grupo($this->programa, 'Tarde', null, 'activo', '2027-02-01');

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/grupo", ['curso_id' => $grupoB->id])->assertOk();

        $this->assertSame($grupoB->id, $m->fresh()->curso_id);
        $this->assertSame(0, $this->grupoA->fresh()->estudiantesActivos()->count(), 'Libera el cupo del grupo anterior');
        $this->assertSame(1, $grupoB->fresh()->estudiantesActivos()->count());

        // El derecho de matrícula sin pagar vence cuando empieza el grupo nuevo
        $this->assertSame($grupoB->fecha_inicio->toDateString(), $m->pagos()->where('tipo', 'matricula')->first()->fecha_vencimiento->toDateString());

        $otroPrograma = $this->grupo($this->programa(), 'Tarde');
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/grupo", ['curso_id' => $otroPrograma->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['curso_id']);
    }

    /* ── Estados ── */

    public function test_aplazar_libera_el_cupo_anula_cuotas_futuras_y_pide_motivo(): void
    {
        $m = $this->matriculaConPlan('mensual');

        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07'])->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaje por trabajo'])->assertOk();

        $m->refresh();
        $this->assertSame('aplazado', $m->estado);
        $this->assertSame(0, $this->grupoA->fresh()->estudiantesActivos()->count());
        $this->assertSame(0, $m->pagos()->where('tipo', 'mensual')->whereIn('estado', Pago::POR_PAGAR)->count(), 'Las cuotas futuras se anulan');
        $this->assertSame(12, $m->pagos()->where('estado', 'anulado')->count());
    }

    public function test_reintegrar_vuelve_a_generar_las_cuotas_que_faltan(): void
    {
        $m = $this->matriculaConPlan('mensual');
        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaje por trabajo'])->assertOk();

        $this->estado($m, ['estado' => 'activo', 'fecha' => '2026-10-07'])->assertOk();

        $m->refresh();
        $this->assertSame('activo', $m->estado);
        $this->assertSame(12, $m->pagos()->where('tipo', 'mensual')->where('estado', '!=', 'anulado')->count());
        $this->assertSame(1, $this->grupoA->fresh()->estudiantesActivos()->count());
    }

    public function test_retirado_y_graduado_son_finales(): void
    {
        $m = $this->matriculaConPlan('mensual');
        $this->estado($m, ['estado' => 'retirado', 'fecha' => '2026-10-07', 'motivo' => 'Cambio de ciudad'])->assertOk();

        $this->estado($m, ['estado' => 'activo', 'fecha' => '2026-10-07'])->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    }

    public function test_graduar_exige_grupo_terminado_y_paz_y_salvo(): void
    {
        $m = $this->matriculaConPlan('mensual');

        $this->estado($m, ['estado' => 'graduado', 'fecha' => '2026-10-07'])->assertUnprocessable()->assertJsonValidationErrors(['estado']);

        // Grupo terminado pero con deuda
        $this->grupoA->update(['estado' => 'finalizado']);
        $this->estado($m, ['estado' => 'graduado', 'fecha' => '2026-10-07'])->assertUnprocessable()->assertJsonValidationErrors(['estado']);

        // A paz y salvo: paga todo lo que debe
        $this->pagar($m, ['valor' => $this->debe($m), 'metodo_pago' => 'Efectivo'])->assertOk();
        $this->estado($m, ['estado' => 'graduado', 'fecha' => '2026-10-07'])->assertOk();
        $this->assertSame('graduado', $m->fresh()->estado);

        // Graduada quedó a paz y salvo: sus pagos ya no se anulan
        $recaudo = $m->recaudos()->first();
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/recaudos/{$recaudo->id}/anular", ['motivo' => 'Prueba de anulación'])
            ->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
    }

    public function test_graduar_exige_todos_los_modulos_aprobados(): void
    {
        $mods = $this->programa->modulos()->createMany([
            ['nombre' => 'Contabilidad general', 'horas' => 800, 'orden' => 1],
            ['nombre' => 'Práctica empresarial', 'horas' => 800, 'orden' => 2],
        ]);
        $m = $this->matriculaConPlan('mensual');
        $this->grupoA->update(['estado' => 'finalizado']);
        $this->pagar($m, ['valor' => $this->debe($m), 'metodo_pago' => 'Efectivo'])->assertOk();
        $nota = fn ($mod, $n, $h = null) => $m->calificaciones()->updateOrCreate(['programa_modulo_id' => $mod->id], [
            'curso_id' => $this->grupoA->id, 'nota' => $n, 'fecha_nota' => '2026-10-01', 'habilitacion' => $h,
        ]);

        // Al día con los pagos pero sin notas: no se gradúa
        $this->estado($m, ['estado' => 'graduado', 'fecha' => '2026-10-07'])->assertUnprocessable()
            ->assertJsonValidationErrors(['estado' => 'le faltan 2']);

        // Perdió la habilitación del segundo módulo: sigue sin graduarse
        $nota($mods[0], 4.0);
        $nota($mods[1], 2.5, 2.8);
        $this->estado($m, ['estado' => 'graduado', 'fecha' => '2026-10-07'])->assertUnprocessable()
            ->assertJsonValidationErrors(['estado' => 'Práctica empresarial']);

        // Aprobar en habilitación cuenta como aprobado
        $nota($mods[1], 2.5, 3.2);
        $this->estado($m, ['estado' => 'graduado', 'fecha' => '2026-10-07'])->assertOk();
        $this->assertSame('graduado', $m->fresh()->estado);
    }

    public function test_sin_plan_elegido_no_se_gradua_aunque_no_deba_nada(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        $m = $e->matriculas()->first();
        $m->pagos()->update(['estado' => 'pagado', 'fecha_pago' => now()]);   // solo pagó el derecho de matrícula
        $this->grupoA->update(['estado' => 'finalizado']);

        $this->estado($m, ['estado' => 'graduado', 'fecha' => '2026-10-07'])->assertUnprocessable()->assertJsonValidationErrors(['estado']);
        $this->assertSame('activo', $m->fresh()->estado);
    }

    public function test_un_cambio_de_estado_no_puede_ser_anterior_al_ultimo(): void
    {
        $m = $this->matriculaConPlan('mensual');
        Carbon::setTestNow('2026-12-01 09:00:00');
        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-11-20', 'motivo' => 'Viaje por trabajo'])->assertOk();

        // Reintegrarlo antes del aplazamiento generaría cuotas ya vencidas
        $this->estado($m, ['estado' => 'activo', 'fecha' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors(['fecha']);
        $this->estado($m, ['estado' => 'activo', 'fecha' => '2026-11-20'])->assertOk();
    }

    public function test_aplazar_antes_de_empezar_deja_el_derecho_en_espera_hasta_reintegrarse(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        $m = $e->matriculas()->first();
        $derecho = $m->pagos()->where('tipo', 'matricula')->first();

        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaja con su familia'])->assertOk();
        $derecho->refresh();
        $this->assertSame('pendiente', $derecho->estado);
        $this->assertNull($derecho->fecha_vencimiento, 'En espera: no vence mientras está aplazado');

        // Pasa la fecha en que iba a empezar: sigue sin aparecer como vencido
        Carbon::setTestNow('2027-01-20 09:00:00');
        $this->assertSame('al_dia', app(PagoService::class)->resumen($m->fresh(['pagos', 'programa']))['pago']);

        // Se reintegra a un grupo que empieza en febrero: el derecho vence ese día
        $grupoB = $this->grupo($this->programa, 'Tarde', null, 'activo', '2027-02-01');
        $this->estado($m, ['estado' => 'activo', 'fecha' => '2027-01-20', 'curso_id' => $grupoB->id])->assertOk();
        $this->assertSame('2027-02-01', $derecho->fresh()->fecha_vencimiento->toDateString());
    }

    public function test_si_ya_pago_el_derecho_se_le_guarda_al_aplazar(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA, ['pagar_matricula' => true, 'fecha_pago' => '2026-10-07', 'metodo_pago' => 'Efectivo'])->assertOk();
        $m = $e->matriculas()->first();

        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaja con su familia'])->assertOk();

        $derecho = $m->pagos()->where('tipo', 'matricula')->first();
        $this->assertSame('pagado', $derecho->estado);
        $this->assertSame('2026-11-03', $derecho->fecha_vencimiento->toDateString());
    }

    public function test_retirarse_antes_de_empezar_anula_el_derecho_sin_pagar(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        $m = $e->matriculas()->first();

        $this->estado($m, ['estado' => 'retirado', 'fecha' => '2026-10-07', 'motivo' => 'Cambio de ciudad'])->assertOk();

        $this->assertSame('anulado', $m->pagos()->where('tipo', 'matricula')->value('estado'));
    }

    /* ── Acceso del estudiante a la plataforma ── */

    public function test_el_estudiante_ingresa_sin_matricula_o_antes_de_que_venza_el_derecho(): void
    {
        $sinMatricula = $this->estudiante('1070000001');
        $this->post('/login', ['email' => $sinMatricula->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($sinMatricula);
        auth()->guard('web')->logout();

        $e = $this->estudiante('1070000002');
        $this->matricular($e, $this->grupoA)->assertOk();
        auth()->guard('web')->logout();
        $this->post('/login', ['email' => $e->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($e);
    }

    public function test_con_el_derecho_de_matricula_vencido_sin_pagar_no_ingresa(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        auth()->guard('web')->logout();
        Carbon::setTestNow('2026-11-04 09:00:00');   // las clases empezaron el 3

        $this->post('/login', ['email' => $e->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Paga en la sede y ya puede ingresar
        $m = $e->matriculas()->first();
        $this->pagar($m, ['valor' => 200000, 'metodo_pago' => 'Efectivo', 'fecha_pago' => '2026-11-04'])->assertOk();
        auth()->guard('web')->logout();
        $this->post('/login', ['email' => $e->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($e);
    }

    public function test_volver_al_mismo_programa_ocupa_cupo_solo_en_el_grupo_nuevo(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA);
        $this->estado($e->matriculas()->first(), ['estado' => 'retirado', 'fecha' => '2026-10-07', 'motivo' => 'Cambio de ciudad'])->assertOk();
        $grupoB = $this->grupo($this->programa, 'Tarde', null, 'activo', '2026-11-03');

        $this->matricular($e, $grupoB)->assertOk();

        $this->assertSame(0, $this->grupoA->fresh()->estudiantesActivos()->count(), 'La matrícula vieja no ocupa cupo en su grupo');
        $this->assertSame(1, $grupoB->fresh()->estudiantesActivos()->count());
    }

    public function test_la_trayectoria_muestra_todas_sus_matriculas_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA);
        $this->estado($e->matriculas()->first(), ['estado' => 'retirado', 'fecha' => '2026-10-07', 'motivo' => 'Cambio de ciudad'])->assertOk();
        Carbon::setTestNow('2026-10-20 09:00:00');
        $otro = $this->grupo($this->programa(), 'Tarde', null, 'activo', '2026-11-03');
        $this->matricular($e, $otro)->assertOk();

        $e->load(['matriculas' => fn ($q) => $q->with(['programa', 'pagos', 'curso'])]);
        $t = app(\App\Services\Academico\EstudianteService::class)->trayectoria($e);

        $this->assertSame(['activo', 'retirado'], array_column($t, 'estado'));
        $this->assertSame('Cambio de ciudad', $t[1]['estado_motivo']);
        $this->actingAs($this->admin)->get('/admin/estudiantes')->assertOk()->assertSee('trayectoria', false);
    }

    /* ── Cuotas y pagos ── */

    public function test_el_plan_mensual_reparte_el_precio_exacto_y_vence_el_dia_10(): void
    {
        $m = $this->matriculaConPlan('mensual');
        $cuotas = $m->pagos()->where('tipo', 'mensual')->orderBy('numero_cuota')->get();

        $this->assertCount(12, $cuotas);
        $this->assertEquals((float) $this->programa->precio_total, $cuotas->sum(fn ($c) => (float) $c->monto_programa), 'La suma da el precio exacto');
        $this->assertSame('2026-11-10', $cuotas->first()->fecha_vencimiento->toDateString());
        $this->assertSame('2027-10-10', $cuotas->last()->fecha_vencimiento->toDateString());
    }

    public function test_un_pago_se_aplica_en_orden_y_lo_que_sobra_queda_abonado(): void
    {
        // Derecho $ 200.000 (vence el 3 nov) y cuotas de $ 252.000 (250.000 + 2.000 del sistema) desde el 10 nov
        $m = $this->matriculaConPlan('mensual');
        [$derecho, $cuota1, $cuota2] = $this->primerosCobros($m, 3);

        $this->pagar($m, ['valor' => 500000, 'metodo_pago' => 'Transferencia'])->assertUnprocessable()->assertJsonValidationErrors(['referencia']);
        $this->pagar($m, ['valor' => 500000, 'metodo_pago' => 'Transferencia', 'referencia' => 'TRX-889'])->assertOk()->assertJsonFragment(['recibo' => 'RC-2026-00001']);

        // Completa el derecho y la cuota 1; los $ 48.000 que sobran quedan abonados a la cuota 2
        $this->assertSame('pagado', Pago::find($derecho)->estado);
        $this->assertSame('pagado', Pago::find($cuota1)->estado);
        $c2 = Pago::find($cuota2);
        $this->assertSame('pendiente', $c2->estado);
        $this->assertEquals(48000, (float) $c2->abonado);
        $this->assertEquals(204000, $c2->restante());

        $recaudo = $m->recaudos()->with('cobros')->first();
        $this->assertCount(3, $recaudo->cobros, 'Un solo recibo cubre los tres cobros');
        $this->assertEquals(48000, (float) $recaudo->cobros->firstWhere('id', $cuota2)->pivot->monto);
    }

    public function test_no_se_paga_mas_de_lo_que_debe(): void
    {
        $m = $this->matriculaConPlan('mensual');

        $this->pagar($m, ['valor' => $this->debe($m) + 1, 'metodo_pago' => 'Efectivo'])->assertUnprocessable()->assertJsonValidationErrors(['valor']);
        $this->pagar($m, ['valor' => 0, 'metodo_pago' => 'Efectivo'])->assertUnprocessable()->assertJsonValidationErrors(['valor']);
        $this->pagar($m, ['valor' => $this->debe($m), 'metodo_pago' => 'Efectivo'])->assertOk();
        $this->pagar($m, ['valor' => 1000, 'metodo_pago' => 'Efectivo'])->assertUnprocessable()->assertJsonValidationErrors(['valor']);
    }

    public function test_anular_un_pago_devuelve_lo_abonado_y_su_recibo_no_se_reutiliza(): void
    {
        $m = $this->matriculaConPlan('mensual');
        [$derecho, $cuota1] = $this->primerosCobros($m, 2);
        $this->pagar($m, ['valor' => 300000, 'metodo_pago' => 'Efectivo'])->assertJsonFragment(['recibo' => 'RC-2026-00001']);
        $recaudo = $m->recaudos()->first();

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/recaudos/{$recaudo->id}/anular", ['motivo' => 'Se registró en la matrícula equivocada'])->assertOk();

        $this->assertSame('pendiente', Pago::find($derecho)->estado);
        $this->assertEquals(0, (float) Pago::find($derecho)->abonado);
        $this->assertEquals(0, (float) Pago::find($cuota1)->abonado);
        $recaudo->refresh();
        $this->assertTrue($recaudo->anulado());
        $this->assertSame('Se registró en la matrícula equivocada', $recaudo->motivo_anulacion);
        $this->assertDatabaseHas('auditorias', ['entidad_tipo' => 'Matricula', 'entidad_id' => $m->id, 'accion' => 'editar']);

        // No se anula dos veces, y el siguiente pago lleva un recibo nuevo
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/recaudos/{$recaudo->id}/anular", ['motivo' => 'Otra vez por error'])
            ->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
        $this->pagar($m, ['valor' => 200000, 'metodo_pago' => 'Efectivo'])->assertJsonFragment(['recibo' => 'RC-2026-00002']);
    }

    public function test_el_recibo_se_puede_ver_e_imprimir_solo_en_su_sede(): void
    {
        $m = $this->matriculaConPlan('mensual');
        $this->pagar($m, ['valor' => 200000, 'metodo_pago' => 'Nequi', 'referencia' => 'NQ-55'])->assertOk();
        $recaudo = $m->recaudos()->first();

        $this->actingAs($this->admin)->get("/admin/matriculas/recibos/{$recaudo->id}")
            ->assertOk()->assertSee('RC-2026-00001')->assertSee('NIT 900820764-1')->assertSee('Derecho de matrícula')->assertSee('NQ-55');

        $otroAdmin = User::factory()->create(['role' => 'admin', 'sede_id' => $this->otraSede()->id]);
        $this->actingAs($otroAdmin)->get("/admin/matriculas/recibos/{$recaudo->id}")->assertNotFound();
    }

    public function test_el_descuento_rebaja_el_programa_de_las_cuotas_sin_abonos(): void
    {
        $m = $this->matriculaConPlan('mensual');
        [, $cuota1, $cuota2] = $this->primerosCobros($m, 3);
        // Abona a la cuota 1: esa conserva su valor
        $this->pagar($m, ['valor' => 300000, 'metodo_pago' => 'Efectivo'])->assertOk();

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 20])
            ->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 120, 'motivo' => 'Convenio alcaldía'])
            ->assertUnprocessable()->assertJsonValidationErrors(['porcentaje']);
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 20, 'motivo' => 'Convenio alcaldía'])->assertOk();

        $this->assertEquals(252000, (float) Pago::find($cuota1)->monto_total, 'Con abonos conserva su valor');
        $c2 = Pago::find($cuota2);
        $this->assertEquals(50000, (float) $c2->descuento);
        $this->assertEquals(202000, (float) $c2->monto_total, '250.000 − 20 % + 2.000 del sistema');
        $this->assertEquals(200000, (float) $m->pagos()->where('tipo', 'matricula')->value('monto_total'), 'El derecho no cambia');
        $this->assertEquals(20, (float) $m->fresh()->descuento_pct);

        // 0 lo quita
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 0])->assertOk();
        $this->assertEquals(252000, (float) $c2->fresh()->monto_total);
    }

    public function test_el_descuento_se_aplica_a_las_cuotas_que_se_generen_despues(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        $m = $e->matriculas()->first();
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 50, 'motivo' => 'Beca por rendimiento'])->assertOk();

        $m->update(['plan_pago' => 'mensual']);
        app(PagoService::class)->generarCuotas($m->fresh(), $this->grupoA->fecha_inicio);

        $this->assertEquals(127000, (float) $m->pagos()->where('tipo', 'mensual')->orderBy('numero_cuota')->value('monto_total'));
    }

    /* ── Devoluciones y cambio de programa ── */

    public function test_al_retirarse_se_devuelve_lo_pagado_por_adelantado(): void
    {
        // Derecho (200.000) + cuotas de nov, dic y ene (252.000 c/u) pagadas el 7 de octubre
        $m = $this->matriculaConPlan('mensual');
        $this->pagar($m, ['valor' => 956000, 'metodo_pago' => 'Efectivo'])->assertOk();
        Carbon::setTestNow('2026-11-20 09:00:00');
        $this->estado($m, ['estado' => 'retirado', 'fecha' => '2026-11-20', 'motivo' => 'Cambio de ciudad'])->assertOk();

        $this->assertSame(504000, app(PagoService::class)->totalAdelantado($m->fresh()), 'Dic y ene ya no los va a estudiar');

        $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/devoluciones", ['fecha' => '2026-11-20', 'metodo_pago' => 'Nequi', 'motivo' => 'Pidió la devolución'])
            ->assertUnprocessable()->assertJsonValidationErrors(['referencia']);
        $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/devoluciones", ['fecha' => '2026-11-20', 'metodo_pago' => 'Nequi', 'referencia' => 'NQ-1', 'motivo' => 'Pidió la devolución'])
            ->assertOk()->assertJsonFragment(['comprobante' => 'DV-2026-00001']);

        $m->refresh()->load('pagos', 'recaudos', 'programa');
        $this->assertSame(0, app(PagoService::class)->totalAdelantado($m));
        $this->assertSame(452000, app(PagoService::class)->resumen($m)['pagado'], 'Lo que pagó menos lo devuelto');
        $this->assertSame(0, $m->pagos()->where('tipo', 'mensual')->where('estado', 'pagado')->whereDate('fecha_vencimiento', '>', '2026-11-20')->count());
        $this->actingAs($this->admin)->get('/admin/matriculas/devoluciones/'.$m->devoluciones()->first()->id)->assertOk()->assertSee('DV-2026-00001');

        // Ese dinero ya salió: el pago original no se puede anular
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/recaudos/{$m->recaudos->first()->id}/anular", ['motivo' => 'Se registró por error'])
            ->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
        // Y no se devuelve dos veces
        $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/devoluciones", ['fecha' => '2026-11-20', 'metodo_pago' => 'Efectivo', 'motivo' => 'Otra vez'])
            ->assertUnprocessable();
    }

    public function test_lo_adelantado_cubre_primero_lo_que_debe_vencido(): void
    {
        $m = $this->matriculaConPlan('mensual');
        $this->pagar($m, ['valor' => 704000, 'metodo_pago' => 'Efectivo'])->assertOk();   // derecho + nov + dic
        $this->pagar($m, ['valor' => 252000, 'metodo_pago' => 'Efectivo'])->assertOk();   // ene
        $primero = $m->recaudos()->orderBy('id')->first();
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/recaudos/{$primero->id}/anular", ['motivo' => 'El pago rebotó'])->assertOk();

        Carbon::setTestNow('2026-12-20 09:00:00');
        $this->estado($m, ['estado' => 'retirado', 'fecha' => '2026-12-20', 'motivo' => 'Cambio de ciudad'])->assertOk();
        // Debe derecho, nov y dic (704.000) y tiene enero pagado (252.000)
        $this->assertSame(['total' => 252000, 'cubre' => 252000, 'resto' => 0], app(PagoService::class)->repartoAdelantado($m->fresh()));

        $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/devoluciones", ['fecha' => '2026-12-20', 'metodo_pago' => 'Efectivo', 'motivo' => 'Aplicar a lo que debe'])
            ->assertOk()->assertJsonMissing(['comprobante' => 'DV-2026-00001']);

        $this->assertSame(0, $m->devoluciones()->count(), 'No se le devuelve nada a quien debe más');
        $this->assertSame('pagado', $m->pagos()->where('tipo', 'matricula')->value('estado'));
        $this->assertEquals(52000, (float) $m->pagos()->where('numero_cuota', 1)->value('abonado'));
    }

    public function test_cambio_de_programa_traslada_lo_adelantado_y_no_cobra_otra_vez_el_derecho(): void
    {
        $m = $this->matriculaConPlan('mensual');
        $this->pagar($m, ['valor' => 704000, 'metodo_pago' => 'Efectivo'])->assertOk();   // derecho + nov + dic (adelantadas)
        $otro = $this->programa(['Mañana', 'Tarde'], 1600, 12, 25);
        $grupoNuevo = $this->grupo($otro, 'Tarde', null, 'activo', '2026-11-03');

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/programa", ['curso_id' => $this->grupoA->id, 'motivo' => 'Quiere otro programa'])
            ->assertUnprocessable()->assertJsonValidationErrors(['curso_id']);
        $r = $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/programa", ['curso_id' => $grupoNuevo->id, 'motivo' => 'Le gustó más el otro programa'])->assertOk();

        $vieja = $m->fresh();
        $nueva = Matricula::find($r->json('id'));
        $this->assertSame('retirado', $vieja->estado);
        $this->assertStringContainsString('Cambio de programa', $vieja->estado_motivo);
        $this->assertSame($vieja->id, $nueva->origen_id);
        $this->assertSame('activo', $nueva->estado);
        $this->assertSame(0, $nueva->pagos()->where('tipo', 'matricula')->count(), 'Ya pagó el derecho');
        $this->assertEquals(504000, (float) $nueva->saldo_favor, 'Nov y dic pasan como abono');
        $this->assertFalse($this->grupoA->estudiantes()->whereKey($vieja->estudiante_id)->exists());
        $this->assertTrue($grupoNuevo->estudiantes()->whereKey($vieja->estudiante_id)->exists());

        // Cuando elige el plan, el saldo a favor paga sus primeras cuotas
        $nueva->update(['plan_pago' => 'mensual']);
        app(PagoService::class)->generarCuotas($nueva->fresh(), $grupoNuevo->fecha_inicio);
        $nueva->refresh();
        $this->assertEquals(0, (float) $nueva->saldo_favor);
        $this->assertSame(2, $nueva->pagos()->where('tipo', 'mensual')->where('estado', 'pagado')->count());
    }

    public function test_cambio_de_programa_sin_derecho_pagado_cobra_el_del_programa_nuevo(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        $m = $e->matriculas()->first();
        $grupoNuevo = $this->grupo($this->programa(), 'Tarde', null, 'activo', '2026-11-03');

        $r = $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/programa", ['curso_id' => $grupoNuevo->id, 'motivo' => 'Se equivocó de programa'])->assertOk();

        $this->assertSame('anulado', $m->pagos()->where('tipo', 'matricula')->value('estado'));
        $this->assertSame('pendiente', Matricula::find($r->json('id'))->pagos()->where('tipo', 'matricula')->value('estado'));
    }

    public function test_al_reintegrarse_lo_abonado_a_cuotas_anuladas_vuelve_a_sus_cuotas(): void
    {
        $m = $this->matriculaConPlan('mensual');
        $this->pagar($m, ['valor' => 552000, 'metodo_pago' => 'Efectivo'])->assertOk();   // derecho + nov + 100.000 a dic
        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaje por trabajo'])->assertOk();
        $this->assertSame(352000, app(PagoService::class)->totalAdelantado($m->fresh()));

        $this->estado($m, ['estado' => 'activo', 'fecha' => '2026-10-07'])->assertOk();

        $m->refresh();
        $this->assertEquals(0, (float) $m->saldo_favor);
        $this->assertSame(12, $m->pagos()->where('tipo', 'mensual')->where('estado', '!=', 'anulado')->count());
        $this->assertEquals(100000, (float) $m->pagos()->where('tipo', 'mensual')->where('estado', '!=', 'anulado')->where('numero_cuota', 2)->value('abonado'));
    }

    /* ── Documentos ── */

    public function test_constancia_solo_de_matricula_vigente_y_paz_y_salvo_solo_sin_deuda(): void
    {
        $m = $this->matriculaConPlan('mensual');

        $this->actingAs($this->admin)->get("/admin/matriculas/{$m->id}/constancia")->assertOk()->assertSee('Hace constar que')->assertSee($m->codigo);
        $this->actingAs($this->admin)->get("/admin/matriculas/{$m->id}/paz-y-salvo")->assertNotFound();

        $this->pagar($m, ['valor' => $this->debe($m), 'metodo_pago' => 'Efectivo'])->assertOk();
        $this->actingAs($this->admin)->get("/admin/matriculas/{$m->id}/paz-y-salvo")->assertOk()->assertSee('a paz y salvo');

        $otroAdmin = User::factory()->create(['role' => 'admin', 'sede_id' => $this->otraSede()->id]);
        $this->actingAs($otroAdmin)->get("/admin/matriculas/{$m->id}/constancia")->assertNotFound();

        $this->estado($m, ['estado' => 'retirado', 'fecha' => '2026-10-07', 'motivo' => 'Cambio de ciudad'])->assertOk();
        $this->actingAs($this->admin)->get("/admin/matriculas/{$m->id}/constancia")->assertNotFound();
    }

    public function test_sin_plan_elegido_no_hay_paz_y_salvo_aunque_no_deba_nada(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA, ['pagar_matricula' => true, 'fecha_pago' => '2026-10-07', 'metodo_pago' => 'Efectivo'])->assertOk();
        $m = $e->matriculas()->first();

        $this->actingAs($this->admin)->get("/admin/matriculas/{$m->id}/paz-y-salvo")->assertNotFound();
    }

    public function test_anular_una_matricula_hecha_por_error(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA)->assertOk();
        $m = $e->matriculas()->first();

        $this->estado($m, ['estado' => 'anulado', 'fecha' => '2026-10-07'])->assertUnprocessable()->assertJsonValidationErrors(['motivo']);
        $this->estado($m, ['estado' => 'anulado', 'fecha' => '2026-10-07', 'motivo' => 'Se matriculó en el programa equivocado'])->assertOk();

        $m->refresh();
        $this->assertSame('anulado', $m->estado);
        $this->assertSame(0, $m->pagos()->whereIn('estado', Pago::POR_PAGAR)->count(), 'No se le cobra nada');
        $this->assertFalse($this->grupoA->estudiantes()->whereKey($e->id)->exists(), 'Sale de la lista del grupo');
        $this->assertDatabaseHas('auditorias', ['entidad_tipo' => 'Matricula', 'entidad_id' => $m->id, 'accion' => 'eliminar']);

        // No cuenta en la trayectoria y se puede matricular de nuevo
        $e->load(['matriculas' => fn ($q) => $q->with(['programa', 'pagos', 'recaudos', 'curso'])]);
        $this->assertSame([], app(\App\Services\Academico\EstudianteService::class)->trayectoria($e));
        $this->matricular($e, $this->grupoA)->assertOk();
    }

    public function test_no_se_anula_una_matricula_con_pagos(): void
    {
        $e = $this->estudiante('1070000001');
        $this->matricular($e, $this->grupoA, ['pagar_matricula' => true, 'fecha_pago' => '2026-10-07', 'metodo_pago' => 'Efectivo'])->assertOk();
        $m = $e->matriculas()->first();

        $this->estado($m, ['estado' => 'anulado', 'fecha' => '2026-10-07', 'motivo' => 'Se matriculó por error'])
            ->assertUnprocessable()->assertJsonValidationErrors(['estado']);
        $this->assertSame('activo', $m->fresh()->estado);
    }

    public function test_un_pendiente_con_fecha_pasada_cuenta_como_vencido(): void
    {
        $m = $this->matriculaConPlan('mensual');
        Carbon::setTestNow('2026-11-15');

        $resumen = app(PagoService::class)->resumen($m->fresh(['pagos', 'programa']));

        $this->assertSame('vencido', $resumen['pago']);
        // El derecho de matrícula (vencía el 3) y la cuota 1 (el 10): el comando los deja guardados
        $this->assertSame(2, app(PagoService::class)->vencerAtrasados());
        $this->assertSame(2, $m->pagos()->where('estado', 'vencido')->count());
    }

    public function test_la_pantalla_carga(): void
    {
        $this->matriculaConPlan('mensual');
        $this->estudiante('1070000009');

        $this->actingAs($this->admin)->get('/admin/matriculas')->assertOk()->assertSee('MAT_DATA', false);
    }

    /* ── Auxiliares ── */

    private function estudiante(string $documento, int $edad = 25, bool $conDocumentos = true): User
    {
        $e = User::factory()->create([
            'role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento,
            'fecha_nacimiento' => now()->subYears($edad)->subDays(10)->toDateString(),
        ]);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => $conDocumentos ? now() : null], EstudianteDocumento::REQUERIDOS));

        return $e;
    }

    private function matricular(User $e, Curso $g, array $extra = [])
    {
        return $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $g->id] + $extra);
    }

    private function estado(Matricula $m, array $datos)
    {
        return $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/estado", $datos);
    }

    private function pagar(Matricula $m, array $datos)
    {
        return $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/pagos", $datos + ['fecha_pago' => '2026-10-07']);
    }

    /** Todo lo que debe la matrícula hoy (cuotas futuras incluidas), en pesos enteros. */
    private function debe(Matricula $m): int
    {
        return (int) round(app(PagoService::class)->cobrosPorPagar($m)->sum(fn (Pago $p) => $p->restante()));
    }

    /** Ids de los primeros $n cobros por vencimiento (el derecho de matrícula va primero). */
    private function primerosCobros(Matricula $m, int $n): array
    {
        return $m->pagos()->orderBy('fecha_vencimiento')->orderBy('id')->limit($n)->pluck('id')->all();
    }

    /** Matrícula activa en el grupo A con el plan ya elegido (como lo dejará el panel del estudiante). */
    private function matriculaConPlan(string $plan): Matricula
    {
        $e = $this->estudiante('1070000050');
        $this->matricular($e, $this->grupoA)->assertOk();
        $m = $e->matriculas()->first();
        $m->update(['plan_pago' => $plan]);
        app(PagoService::class)->generarCuotas($m, $this->grupoA->fecha_inicio);

        return $m->fresh();
    }
}
