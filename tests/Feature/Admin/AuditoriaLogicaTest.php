<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Comunicacion\Comunicado;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Institucional\Configuracion;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use App\Services\General\ComunicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Auditoría de lógica de la Secretaría y el Admin (24-11-2026): grupos terminados por fecha, cuotas que siguen al inicio
 * de clases, sacar un programa de "activo", bloqueo de cuentas, cuenta del estudiante y foro, malla con Aula virtual,
 * beca completa, paz y salvo de todas las sedes, envíos del panel general, pasaporte, TI al cumplir 18 y documentos.
 */
class AuditoriaLogicaTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupoA;
    private int $doc = 1070000100;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana', 'Tarde'], 1600, 12);
        $this->docente  = $this->docente();
        $this->grupoA   = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-11-03');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ── 1. Grupo que ya terminó por fecha ── */

    public function test_no_matricula_ni_reintegra_en_un_grupo_que_ya_termino_por_fecha(): void
    {
        $viejo = $this->grupo($this->programa, 'Tarde', $this->docente, 'activo', '2025-09-01');   // terminó el 01/09/2026
        $this->assertTrue($viejo->cerrado(), 'Sigue "activo", pero sus clases ya terminaron');

        $this->matricular($this->estudiante(), $viejo)->assertJsonValidationErrors(['curso_id' => 'ya terminó sus clases']);

        $m = $this->matriculado();
        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaje familiar'])->assertOk();
        $this->estado($m, ['estado' => 'activo', 'fecha' => '2026-10-07', 'curso_id' => $viejo->id])
            ->assertJsonValidationErrors(['curso_id' => 'ya terminó sus clases']);
    }

    /* ── 2. Las cuotas siguen al inicio de clases ── */

    public function test_correr_el_inicio_del_grupo_mueve_las_cuotas_que_debe(): void
    {
        $m = $this->conPlan('mensual');
        $this->assertSame('2026-11-10', $this->cuota($m, 1)->fecha_vencimiento->toDateString());

        $this->actingAs($this->admin)->putJson("/admin/cursos/{$this->grupoA->id}", [
            'programa_id' => $this->programa->id, 'docente_id' => $this->docente->id, 'jornada' => 'Mañana',
            'fecha_inicio' => '2027-01-11', 'estado' => 'activo',
        ])->assertOk();

        // Empieza después del 10: la primera cuota vence el 10 del mes siguiente
        $this->assertSame('2027-02-10', $this->cuota($m, 1)->fecha_vencimiento->toDateString());
        $this->assertSame('2027-03-10', $this->cuota($m, 2)->fecha_vencimiento->toDateString());
        $this->assertSame('2027-01-11', $m->pagos()->where('tipo', 'matricula')->first()->fecha_vencimiento->toDateString());
    }

    public function test_al_cambiar_de_grupo_las_cuotas_que_debe_cuentan_desde_el_grupo_nuevo(): void
    {
        $m = $this->conPlan('mensual');
        $grupoB = $this->grupo($this->programa, 'Tarde', $this->docente(), 'activo', '2027-02-01');
        // Paga el derecho y la primera cuota: esa conserva su fecha
        $valor = (int) round((float) $m->pagos()->where('tipo', 'matricula')->value('monto_total') + (float) $this->cuota($m, 1)->monto_total);
        $this->actingAs($this->admin)->postJson("/admin/matriculas/{$m->id}/pagos", ['valor' => $valor, 'fecha_pago' => '2026-10-07', 'metodo_pago' => 'Efectivo'])->assertOk();

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/grupo", ['curso_id' => $grupoB->id])->assertOk();

        $this->assertSame('pagado', $this->cuota($m, 1)->estado);
        $this->assertSame('2026-11-10', $this->cuota($m, 1)->fecha_vencimiento->toDateString());
        $this->assertSame('2027-03-10', $this->cuota($m, 2)->fecha_vencimiento->toDateString(), 'Febrero + 1 mes');
    }

    public function test_al_reintegrarse_en_un_grupo_que_empieza_despues_las_cuotas_cuentan_desde_ese_grupo(): void
    {
        $m = $this->conPlan('mensual');
        $grupoC = $this->grupo($this->programa, 'Tarde', $this->docente(), 'activo', '2027-03-01');
        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaje familiar'])->assertOk();

        $this->estado($m, ['estado' => 'activo', 'fecha' => '2026-10-07', 'curso_id' => $grupoC->id])->assertOk();

        $primera = $m->pagos()->where('tipo', 'mensual')->where('estado', '!=', 'anulado')->orderBy('numero_cuota')->first();
        $this->assertSame('2027-03-10', $primera->fecha_vencimiento->toDateString());
    }

    /* ── 3. Sacar un programa de "activo" ── */

    public function test_el_formulario_no_saca_de_activo_un_programa_con_grupos_vigentes(): void
    {
        $general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);

        $this->actingAs($general)->putJson("/general/programas/{$this->programa->id}", $this->datosPrograma(['estado' => 'en_aprobacion']))
            ->assertJsonValidationErrors(['estado' => 'grupo vigente']);
        $this->putJson("/general/programas/{$this->programa->id}", $this->datosPrograma(['estado' => 'inactivo']))
            ->assertJsonValidationErrors('estado');
        $this->assertSame('activo', $this->programa->fresh()->estado);
    }

    public function test_con_estudiantes_aplazados_no_se_desactiva_ni_se_deja_de_ofrecer(): void
    {
        $m = $this->matriculado();
        $this->estado($m, ['estado' => 'aplazado', 'fecha' => '2026-10-07', 'motivo' => 'Viaje familiar'])->assertOk();
        Curso::whereKey($this->grupoA->id)->update(['estado' => 'finalizado']);
        $general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);

        $this->actingAs($general)->patchJson("/general/programas/{$this->programa->id}/estado")
            ->assertStatus(422)->assertJsonPath('message', fn ($t) => str_contains($t, 'aplazado'));
        $this->patchJson("/general/programas/{$this->programa->id}/sedes/{$this->sede->id}")->assertStatus(422);
        $this->actingAs($this->admin)->patchJson("/admin/programas/{$this->programa->id}/oferta")
            ->assertStatus(422)->assertJsonPath('message', fn ($t) => str_contains($t, 'aplazado'));
        $this->assertTrue($this->programa->sedes()->whereKey($this->sede->id)->exists());
    }

    /* ── 4. Bloqueo de cuentas ── */

    public function test_el_bloqueo_por_intentos_fallidos_se_levanta_solo(): void
    {
        Configuracion::guardar('seguridad_intentos_maximos', 3);
        $u = User::factory()->create(['role' => 'admin', 'sede_id' => $this->sede->id, 'password' => Hash::make('clave-buena')]);
        foreach (range(1, 3) as $i) {
            $this->post('/login', ['email' => $u->email, 'password' => 'mala'.$i]);
        }
        $this->post('/login', ['email' => $u->email, 'password' => 'clave-buena'])->assertSessionHasErrors('email');
        $this->assertGuest();

        Carbon::setTestNow(now()->addMinutes(16));
        $this->post('/login', ['email' => $u->email, 'password' => 'clave-buena']);
        $this->assertAuthenticatedAs($u);
        $this->assertNull($u->fresh()->bloqueado_en);
    }

    public function test_restablecer_la_clave_desbloquea_la_cuenta(): void
    {
        $e = $this->estudiante();
        $e->forceFill(['bloqueado_en' => now(), 'motivo_bloqueo' => 'inactividad'])->save();

        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e->id}/clave")->assertOk();

        $this->assertNull($e->fresh()->bloqueado_en);
        $this->assertNotNull($e->fresh()->ultimo_acceso, 'Si no, volvería a bloquearse por inactividad al entrar');
    }

    /* ── 5. Cuenta del estudiante y foro ── */

    public function test_la_cuenta_del_estudiante_se_desactiva_solo_sin_matricula_vigente(): void
    {
        $m = $this->matriculado();
        $e = $m->estudiante;
        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e->id}/estado")
            ->assertStatus(422)->assertJsonPath('message', fn ($t) => str_contains($t, 'vigente'));

        $this->estado($m, ['estado' => 'retirado', 'fecha' => '2026-10-07', 'motivo' => 'Cambio de ciudad'])->assertOk();
        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e->id}/estado")->assertOk();
        $this->assertFalse((bool) $e->fresh()->activo);
        $this->assertDatabaseHas('auditorias', ['accion' => 'desactivar', 'entidad_tipo' => 'User', 'entidad_id' => $e->id]);

        // Vuelve a estudiar: al matricularlo su cuenta se activa sola
        $this->matricular($e->fresh(), $this->grupoA)->assertOk();
        $this->assertTrue((bool) $e->fresh()->activo);
    }

    public function test_sin_matricula_vigente_no_participa_en_el_foro(): void
    {
        $m = $this->matriculado();
        $e = $m->estudiante;
        $tema = ['categoria' => 'academico', 'titulo' => 'Horario de práctica', 'texto' => '¿A qué hora es la práctica?'];
        $this->actingAs($e)->postJson('/estudiante/foro/temas', $tema)->assertOk();

        $this->estado($m, ['estado' => 'retirado', 'fecha' => '2026-10-07', 'motivo' => 'Cambio de ciudad'])->assertOk();
        $this->actingAs($e->fresh())->postJson('/estudiante/foro/temas', $tema)
            ->assertForbidden()->assertJsonPath('message', 'Solo participan en el foro los estudiantes con matrícula vigente.');
        $this->getJson('/estudiante/foro')->assertOk();   // leer sí puede
    }

    /* ── 6. Malla con contenido del Aula virtual ── */

    public function test_la_malla_no_cambia_si_sus_modulos_tienen_evaluaciones(): void
    {
        $modulo = $this->programa->modulos()->create(['nombre' => 'Fundamentos', 'horas' => 800, 'orden' => 1]);
        $this->programa->modulos()->create(['nombre' => 'Práctica', 'horas' => 800, 'orden' => 2]);
        Evaluacion::create(['curso_id' => $this->grupoA->id, 'docente_id' => $this->docente->id, 'programa_modulo_id' => $modulo->id,
            'titulo' => 'Quiz 1', 'fecha_limite' => '2026-12-01 18:00']);
        $general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);

        $this->actingAs($general)->putJson("/general/programas/{$this->programa->id}", $this->datosPrograma([
            'modulos' => [['nombre' => 'Práctica', 'horas' => 800], ['nombre' => 'Fundamentos', 'horas' => 800]],   // reordenada
        ]))->assertJsonValidationErrors(['modulos' => 'Aula virtual']);
        $this->assertSame($modulo->id, Evaluacion::first()->programa_modulo_id);
    }

    /* ── 7. Beca completa ── */

    public function test_una_cuota_en_cero_por_beca_completa_queda_pagada_y_vuelve_a_deberse_al_quitarla(): void
    {
        Configuracion::guardar('cuota_sistema_mensual', 0);
        $m = $this->conPlan('mensual');

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 100, 'motivo' => 'Beca completa'])->assertOk();
        $this->assertSame(['pagado'], $m->pagos()->where('tipo', 'mensual')->pluck('estado')->unique()->values()->all());
        $this->assertSame(0, (int) app(PagoService::class)->resumen($m->fresh()->load('pagos', 'recaudos', 'programa'))['por_pagar'] - 200000, 'Solo debe el derecho');

        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 0])->assertOk();
        $this->assertSame(['pendiente'], $m->pagos()->where('tipo', 'mensual')->pluck('estado')->unique()->values()->all());
    }

    public function test_con_la_beca_antes_del_plan_las_cuotas_nacen_pagadas(): void
    {
        Configuracion::guardar('cuota_sistema_mensual', 0);
        $m = $this->matriculado();
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/descuento", ['porcentaje' => 100, 'motivo' => 'Beca completa'])->assertOk();
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/plan", ['plan' => 'mensual'])->assertOk();

        $this->assertSame(['pagado'], $m->pagos()->where('tipo', 'mensual')->pluck('estado')->unique()->values()->all());
    }

    /* ── 8. Paz y salvo de todas las sedes ── */

    public function test_el_paz_y_salvo_mira_las_deudas_de_otra_sede(): void
    {
        $e = $this->estudiante();
        $aqui = Matricula::create(['codigo' => 'MAT-A', 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id, 'sede_id' => $this->sede->id,
            'estado' => 'graduado', 'fecha_inicio' => '2025-01-01', 'plan_pago' => 'mensual']);
        $otra = Matricula::create(['codigo' => 'MAT-B', 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id, 'sede_id' => $this->otraSede()->id,
            'estado' => 'retirado', 'fecha_inicio' => '2024-01-01', 'plan_pago' => 'mensual']);
        $cuota = $otra->pagos()->create(['tipo' => 'mensual', 'numero_cuota' => 1, 'fecha_vencimiento' => '2024-02-10',
            'monto_programa' => 100000, 'monto_sistema' => 0, 'monto_total' => 100000, 'estado' => 'vencido']);

        $this->actingAs($this->admin)->get("/admin/matriculas/{$aqui->id}/paz-y-salvo")->assertNotFound();
        $cuota->update(['estado' => 'pagado', 'abonado' => 100000]);
        $this->get("/admin/matriculas/{$aqui->id}/paz-y-salvo")->assertOk();
    }

    /* ── 9. Envíos del panel general ── */

    public function test_un_envio_del_panel_general_no_se_edita_ni_elimina_desde_la_sede(): void
    {
        $general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
        $this->actingAs($general);
        $publicado = app(ComunicacionService::class)->crear([$this->sede->id], ['destino' => 'sede', 'titulo' => 'Horario de diciembre',
            'categoria' => 'general', 'mensaje' => 'Las sedes atienden de 8 a 12.', 'publicar_en' => null, 'visible_hasta' => null, 'fijado' => false], 'publicar');
        $borrador = app(ComunicacionService::class)->crear([$this->sede->id], ['destino' => 'sede', 'titulo' => 'Borrador de enero',
            'categoria' => 'general', 'mensaje' => null, 'publicar_en' => null, 'visible_hasta' => null, 'fijado' => false], 'borrador');
        $datos = ['accion' => 'publicar', 'destino' => 'sede', 'titulo' => 'Horario cambiado', 'categoria' => 'general', 'mensaje' => 'Otro texto para la sede.'];

        $this->actingAs($this->admin)->putJson("/admin/comunicados/{$publicado->id}", $datos)->assertJsonValidationErrors(['titulo' => 'Admin de ESAT']);
        // Tampoco el Admin cuando entra a la sede: lo edita desde el panel general, entero
        $this->actingAs($general)->post("/general/sedes/{$this->sede->id}/entrar");
        $this->putJson("/admin/comunicados/{$publicado->id}", $datos)->assertJsonValidationErrors(['titulo' => 'panel general']);
        $this->deleteJson("/admin/comunicados/{$borrador->id}")->assertStatus(422);
        $this->assertSame('Horario de diciembre', $publicado->fresh()->titulo);
        $this->assertNotNull(Comunicado::find($borrador->id));
    }

    /* ── Menores ── */

    public function test_una_secretaria_se_registra_con_pasaporte(): void
    {
        $general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
        $this->actingAs($general)->postJson('/general/secretarias', [
            'nombres' => 'Ana', 'apellidos' => 'Ruiz', 'genero' => 'femenino', 'fecha_nacimiento' => '1990-05-04', 'tipo_documento' => 'PA',
            'numero_documento' => 'ab-123456', 'telefono' => '3101234567', 'email' => 'ana.ruiz@correo.com', 'sede_id' => $this->sede->id,
        ])->assertCreated();
        $this->assertSame('AB123456', User::where('email', 'ana.ruiz@correo.com')->value('numero_documento'));
    }

    public function test_quien_se_registro_con_ti_y_cumplio_18_se_puede_seguir_editando(): void
    {
        $e = $this->estudiante(19);
        $e->update(['tipo_documento' => 'TI']);
        $datos = [
            'nombres' => $e->nombres, 'apellidos' => $e->apellidos, 'fecha_nacimiento' => $e->fecha_nacimiento->toDateString(),
            'departamento_nacimiento' => 'Cundinamarca', 'lugar_nacimiento' => 'Sasaima', 'genero' => 'femenino', 'tipo_documento' => 'TI',
            'numero_documento' => $e->numero_documento, 'telefono' => '3109998877', 'email' => $e->email, 'direccion' => 'Calle 1 # 2-3',
            'departamento_residencia' => 'Cundinamarca', 'ciudad_residencia' => 'Sasaima',
            'contacto' => ['nombre' => 'Rosa Patiño', 'celular' => '3104445566', 'parentesco' => 'Madre'],
        ];
        $this->actingAs($this->admin)->putJson("/admin/estudiantes/{$e->id}", $datos)->assertOk();
        $this->assertSame('3109998877', $e->fresh()->telefono);

        // Registrar a un adulto con TI sigue sin poderse
        $this->postJson('/admin/estudiantes', ['numero_documento' => '1070999888', 'email' => 'otro@correo.com'] + $datos)
            ->assertJsonValidationErrors('tipo_documento');
    }

    public function test_marcar_un_documento_dos_veces_lo_deja_marcado(): void
    {
        $e = $this->estudiante(25, false);
        $doc = $e->documentos()->first();
        foreach ([1, 2] as $_) {
            $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e->id}/documentos/{$doc->id}", ['entregado' => true])->assertOk();
        }
        $this->assertNotNull($doc->fresh()->entregado_en);
        $this->assertSame(1, \App\Models\Sistema\Auditoria::where('entidad_tipo', 'User')->where('entidad_id', $e->id)->count());
    }

    /* ── Revisión del Administrativo (27-11-2026) ── */

    /** La residencia del docente, si se escribe, sale del listado de Colombia como en el panel general. */
    public function test_la_residencia_del_docente_sale_del_listado_de_colombia(): void
    {
        $datos = fn (array $c = []) => array_merge([
            'nombres' => 'Carlos Andrés', 'apellidos' => 'Rojas Díaz', 'tipo_documento' => 'CC', 'numero_documento' => '80123456',
            'email' => 'carlos.rojas@example.com', 'telefono' => '3125550101', 'profesion' => 'Administrador de empresas',
            'titulo_academico' => 'Especialista en gerencia', 'escuela_id' => $this->escuela->id, 'vinculacion' => 'hora_catedra', 'estado' => 'activo',
        ], $c);

        $this->actingAs($this->admin)->postJson('/admin/docentes', $datos(['departamento_residencia' => 'Cundinamarca', 'ciudad_residencia' => 'Medellín']))
            ->assertJsonValidationErrors(['ciudad_residencia' => 'departamento elegido']);
        $this->postJson('/admin/docentes', $datos(['departamento_residencia' => 'Cundinamarca', 'ciudad_residencia' => 'Villeta']))->assertOk();
        // Sigue siendo opcional
        $this->postJson('/admin/docentes', $datos(['numero_documento' => '80123457', 'email' => 'otro.docente@example.com']))->assertOk();
    }

    /* ── Auxiliares ── */

    private function estudiante(int $edad = 25, bool $conDocumentos = true): User
    {
        $e = User::factory()->create([
            'role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => (string) $this->doc++,
            'fecha_nacimiento' => now()->subYears($edad)->subDays(10)->toDateString(),
        ]);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => $conDocumentos ? now() : null], EstudianteDocumento::REQUERIDOS));

        return $e;
    }

    private function matricular(User $e, Curso $g)
    {
        return $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $g->id]);
    }

    private function matriculado(): Matricula
    {
        $e = $this->estudiante();
        $this->matricular($e, $this->grupoA)->assertOk();

        return $e->matriculas()->first();
    }

    /** Matriculado en el grupo A con el plan elegido por la secretaría (cuotas desde el inicio de clases). */
    private function conPlan(string $plan): Matricula
    {
        $m = $this->matriculado();
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/plan", ['plan' => $plan])->assertOk();

        return $m->fresh();
    }

    private function cuota(Matricula $m, int $n): Pago
    {
        return $m->pagos()->where('tipo', $m->plan_pago)->where('numero_cuota', $n)->where('estado', '!=', 'anulado')->firstOrFail();
    }

    private function estado(Matricula $m, array $datos)
    {
        return $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$m->id}/estado", $datos);
    }

    /** Formulario del programa en el panel general, como está hoy (con su malla de 1.600 horas). */
    private function datosPrograma(array $cambios = []): array
    {
        $p = $this->programa;

        return array_merge([
            'nombre' => $p->nombre, 'codigo' => $p->codigo, 'escuela_id' => $p->escuela_id, 'nivel' => $p->nivel, 'modalidad' => $p->modalidad,
            'estado' => 'activo', 'resolucion' => 'Resolución 123 de 2025', 'jornadas' => $p->jornadas, 'cupo_grupo' => $p->cupo_grupo,
            'duracion_meses' => $p->duracion_meses, 'tipo_periodo' => $p->tipo_periodo, 'precio_total' => 3000000, 'matricula' => 200000,
            'modulos' => [['nombre' => 'Fundamentos', 'horas' => 800], ['nombre' => 'Práctica', 'horas' => 800]],
        ], $cambios);
    }
}
