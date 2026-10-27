<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Egreso;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Finanzas\ContabilidadService;
use App\Services\Finanzas\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Panel Admin › Contabilidad (Finanzas): egresos de la sede (registrar, editar, anular, con comprobante
 * CE-AAAA-NNNNN y auditoría) e ingresos del mes armados con los recaudos de Pagos.
 */
class ContabilidadTest extends TestCase
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

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'categoria' => 'arriendo', 'fecha' => '2026-10-05', 'concepto' => 'Arriendo de la sede · octubre 2026',
            'beneficiario' => 'Inmobiliaria de prueba', 'valor' => 1500000, 'metodo_pago' => 'Transferencia', 'referencia' => '123456',
        ], $cambios);
    }

    private function registrar(array $cambios = []): Egreso
    {
        $this->actingAs($this->admin)->postJson('/admin/contabilidad/egresos', $this->datos($cambios))->assertOk();

        return Egreso::latest('id')->first();
    }

    public function test_la_pagina_muestra_solo_los_egresos_y_docentes_de_su_sede(): void
    {
        $propio = $this->registrar();
        $otra = $this->otraSede();
        $ajeno = Egreso::create(['sede_id' => $otra->id, 'comprobante' => 'CE-2026-09999', 'fecha' => '2026-10-01', 'categoria' => 'arriendo',
            'concepto' => 'Arriendo de otra sede', 'beneficiario' => 'Alguien', 'valor' => 900000, 'metodo_pago' => 'Efectivo']);
        $docente = $this->docente();
        $deOtra = $this->docente('activo', $otra);

        $this->actingAs($this->admin)->get('/admin/contabilidad')
            ->assertOk()
            ->assertViewHas('egresos', fn ($es) => $es->pluck('id')->all() === [$propio->id] && ! $es->contains('id', $ajeno->id))
            ->assertViewHas('docentes', fn ($ds) => $ds->pluck('id')->all() === [$docente->id] && ! $ds->contains('id', $deOtra->id));
    }

    public function test_registrar_un_egreso_da_comprobante_consecutivo_y_queda_en_la_auditoria(): void
    {
        $r = $this->actingAs($this->admin)->postJson('/admin/contabilidad/egresos', $this->datos())->assertOk();
        $r->assertJsonPath('egreso.comprobante', 'CE-2026-00001')->assertJsonPath('egreso.valor', 1500000)->assertJsonPath('egreso.anulado', null);

        $e = Egreso::first();
        $this->assertSame([$this->sede->id, $this->admin->id, 'Inmobiliaria de prueba'], [$e->sede_id, $e->registrado_por, $e->beneficiario]);
        $this->assertTrue(Auditoria::where('entidad_tipo', 'Egreso')->where('entidad_id', $e->id)->where('accion', 'crear')->exists());

        $this->assertSame('CE-2026-00002', $this->registrar(['concepto' => 'Energía eléctrica · octubre'])->comprobante);
    }

    public function test_la_nomina_se_paga_a_un_docente_de_la_sede_y_toma_su_nombre(): void
    {
        $docente = $this->docente();
        $deOtra = $this->docente('activo', $this->otraSede());

        $this->actingAs($this->admin)->postJson('/admin/contabilidad/egresos', $this->datos(['categoria' => 'nomina', 'beneficiario' => null]))
            ->assertStatus(422)->assertJsonValidationErrors(['docente_id' => 'Elige el docente']);
        $this->actingAs($this->admin)->postJson('/admin/contabilidad/egresos', $this->datos(['categoria' => 'nomina', 'docente_id' => $deOtra->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['docente_id' => 'no pertenece a tu sede']);

        $e = $this->registrar(['categoria' => 'nomina', 'docente_id' => $docente->id, 'beneficiario' => 'Otro nombre', 'concepto' => 'Nómina de octubre 2026 · hora cátedra']);
        $this->assertSame([$docente->id, trim($docente->nombres.' '.$docente->apellidos)], [$e->docente_id, $e->beneficiario]);
    }

    public function test_validaciones_de_fecha_valor_y_referencia(): void
    {
        $url = '/admin/contabilidad/egresos';
        $this->actingAs($this->admin);
        $this->postJson($url, $this->datos(['fecha' => '2026-10-22']))->assertStatus(422)->assertJsonValidationErrors(['fecha' => 'no puede ser futura']);
        $this->postJson($url, $this->datos(['fecha' => '2025-10-01']))->assertStatus(422)->assertJsonValidationErrors(['fecha' => 'más de un año']);
        $this->postJson($url, $this->datos(['valor' => 1500.5]))->assertStatus(422)->assertJsonValidationErrors('valor');
        $this->postJson($url, $this->datos(['valor' => 0]))->assertStatus(422)->assertJsonValidationErrors('valor');
        $this->postJson($url, $this->datos(['referencia' => '  ']))->assertStatus(422)->assertJsonValidationErrors(['referencia' => 'obligatoria']);
        $this->postJson($url, $this->datos(['categoria' => 'devolucion']))->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->postJson($url, $this->datos(['beneficiario' => '']))->assertStatus(422)->assertJsonValidationErrors(['beneficiario' => 'a quién se le pagó']);
        $this->postJson($url, $this->datos(['concepto' => ['x']]))->assertStatus(422)->assertJsonValidationErrors('concepto');

        // En efectivo la referencia es opcional
        $this->postJson($url, $this->datos(['metodo_pago' => 'Efectivo', 'referencia' => null]))->assertOk();
        $this->assertSame(1, Egreso::count());
    }

    public function test_editar_conserva_el_comprobante_y_anotar_los_cambios(): void
    {
        $e = $this->registrar();

        $this->actingAs($this->admin)->putJson("/admin/contabilidad/egresos/{$e->id}", $this->datos(['valor' => 1450000, 'factura' => 'CC-10-2026']))
            ->assertOk()->assertJsonPath('egreso.comprobante', 'CE-2026-00001')->assertJsonPath('egreso.valor', 1450000);

        $this->assertSame(['1450000.00', 'CC-10-2026'], [$e->fresh()->valor, $e->fresh()->factura]);
        $audit = Auditoria::where('entidad_id', $e->id)->where('accion', 'editar')->value('descripcion');
        $this->assertStringContainsString('valor: $ 1.500.000 → $ 1.450.000', $audit);
        $this->assertStringContainsString('factura', $audit);
    }

    public function test_anular_saca_el_egreso_del_saldo_y_ya_no_se_edita(): void
    {
        $e = $this->registrar();

        $this->actingAs($this->admin)->patchJson("/admin/contabilidad/egresos/{$e->id}/anular", ['motivo' => 'ok'])
            ->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->actingAs($this->admin)->patchJson("/admin/contabilidad/egresos/{$e->id}/anular", ['motivo' => 'Se registró dos veces'])
            ->assertOk()->assertJsonPath('egreso.anulado.motivo', 'Se registró dos veces');

        $this->assertNotNull($e->fresh()->anulado_en);
        $this->actingAs($this->admin)->patchJson("/admin/contabilidad/egresos/{$e->id}/anular", ['motivo' => 'Otra vez el motivo'])
            ->assertStatus(422)->assertJsonValidationErrors(['motivo' => 'ya estaba anulado']);
        $this->actingAs($this->admin)->putJson("/admin/contabilidad/egresos/{$e->id}", $this->datos(['valor' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors(['categoria' => 'anulado no se puede editar']);

        // El número no se vuelve a usar
        $this->assertSame('CE-2026-00002', $this->registrar()->comprobante);
    }

    public function test_no_se_toca_un_egreso_de_otra_sede(): void
    {
        $otra = $this->otraSede();
        $ajeno = Egreso::create(['sede_id' => $otra->id, 'comprobante' => 'CE-2026-09999', 'fecha' => '2026-10-01', 'categoria' => 'arriendo',
            'concepto' => 'Arriendo de otra sede', 'beneficiario' => 'Alguien', 'valor' => 900000, 'metodo_pago' => 'Efectivo']);

        $this->actingAs($this->admin)->putJson("/admin/contabilidad/egresos/{$ajeno->id}", $this->datos())->assertForbidden();
        $this->actingAs($this->admin)->patchJson("/admin/contabilidad/egresos/{$ajeno->id}/anular", ['motivo' => 'No es mío'])->assertForbidden();
        $this->assertNull($ajeno->fresh()->anulado_en);
    }

    public function test_solo_el_admin_entra(): void
    {
        $docente = $this->docente();
        $this->actingAs($docente)->get('/admin/contabilidad')->assertForbidden();
        $this->actingAs($docente)->postJson('/admin/contabilidad/egresos', $this->datos())->assertForbidden();
    }

    public function test_ingresos_del_mes_separados_por_concepto_sin_recibos_anulados(): void
    {
        // Grupo que empieza el 3 de noviembre: el derecho vence ese día y la cuota 1 el 10, así que el derecho se paga primero
        $m = $this->matriculaConPlan('1070000001', $this->grupo($this->programa, 'Mañana', null, 'planificacion', '2026-11-03'));
        $pagos = app(PagoService::class);
        $pagos->registrarPago($m, 200000, ['fecha_pago' => '2026-09-05', 'metodo_pago' => 'Efectivo']);
        $nulo = $pagos->registrarPago($m->fresh(), 302000, ['fecha_pago' => '2026-10-02', 'metodo_pago' => 'Nequi', 'referencia' => 'N1']);
        $pagos->anularRecaudo($nulo, 'Se registró por error');
        // Cuota 1 completa ($ 300.000 del programa + $ 2.000 del sistema) y medio abono a la cuota 2
        $pagos->registrarPago($m->fresh(), 453000, ['fecha_pago' => '2026-10-10', 'metodo_pago' => 'Nequi', 'referencia' => 'N2']);

        $ingresos = app(ContabilidadService::class)->ingresosPorMes($this->sede->id)->keyBy('mes');

        $sep = $ingresos['2026-09'];
        $this->assertSame([200000, 0, 0, 1, ['Efectivo' => 200000]], [$sep['derecho'], $sep['programa'], $sep['sistema'], $sep['pagos'], $sep['metodos']]);
        // El abono se reparte en la proporción del cobro (300.000 / 2.000); el recibo anulado no cuenta
        $oct = $ingresos['2026-10'];
        $this->assertSame([0, 450000, 3000, 1, ['Nequi' => 453000]], [$oct['derecho'], $oct['programa'], $oct['sistema'], $oct['pagos'], $oct['metodos']]);
    }

    private function matriculaConPlan(string $documento, ?Curso $grupo = null): Matricula
    {
        $grupo ??= $this->grupo;
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento, 'fecha_nacimiento' => '1998-05-10']);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));

        $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $grupo->id])->assertOk();
        $m = $e->matriculas()->first();
        $m->update(['plan_pago' => 'mensual']);
        app(PagoService::class)->generarCuotas($m, $grupo->fecha_inicio);

        return $m->fresh();
    }
}
