<?php

namespace Tests\Feature\AdminGeneral;

use App\Models\Comunicacion\Comunicado;
use App\Models\Finanzas\Pago;
use App\Models\User;
use App\Services\Academico\MatriculaService;
use App\Services\Finanzas\PagoService;
use App\Services\General\ComunicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Lo que encontró la auditoría del panel Admin (11-11-2026) con varias sedes trabajando a la vez: cambios del catálogo
 * que no pueden tocar a quien ya está matriculado, envíos a varias sedes que una secretaría archiva en la suya,
 * pestañas abiertas en sedes distintas y permisos de todas las rutas.
 */
class AuditoriaPanelTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private User $general;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 10:00');
        $this->prepararSede();
        $this->general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
    }

    /* ── Comunicados a varias sedes ── */

    private function comunicado(array $cambios = []): array
    {
        return array_merge(['sedes' => 'todas', 'destino' => 'todos', 'titulo' => 'Horario de diciembre', 'mensaje' => 'Las sedes atienden de 8 a 12 en diciembre.', 'accion' => 'publicar'], $cambios);
    }

    public function test_editar_un_envio_no_revive_la_copia_archivada_por_una_secretaria(): void
    {
        $otra = $this->otraSede();
        $secretariaOtra = User::factory()->create(['role' => 'admin', 'sede_id' => $otra->id]);
        $this->actingAs($this->general)->postJson('/general/comunicados', $this->comunicado())->assertCreated();
        $copiaOtra = Comunicado::where('sede_id', $otra->id)->first();
        $this->actingAs($secretariaOtra)->patchJson("/admin/comunicados/{$copiaOtra->id}/archivar", ['motivo' => 'No aplica en esta sede'])->assertOk();

        $primera = Comunicado::orderBy('id')->first();
        $this->actingAs($this->general)->putJson("/general/comunicados/{$primera->id}", $this->comunicado(['titulo' => 'Horario de diciembre (corregido)']))->assertOk();

        $this->assertSame('archivado', $copiaOtra->fresh()->estado);
        $this->assertSame('Horario de diciembre (corregido)', $primera->fresh()->titulo);
    }

    public function test_si_la_primera_sede_archiva_su_copia_el_envio_sigue_publicado_y_el_admin_lo_archiva_en_las_demas(): void
    {
        $otra = $this->otraSede();
        $this->actingAs($this->general)->postJson('/general/comunicados', $this->comunicado())->assertCreated();
        $primera = Comunicado::orderBy('id')->first();
        $this->actingAs($this->admin)->patchJson("/admin/comunicados/{$primera->id}/archivar", ['motivo' => 'No aplica en esta sede'])->assertOk();

        // En la lista del Admin sigue publicado y dice dónde lo archivó la secretaría
        $envio = app(ComunicacionService::class)->comunicados()[0];
        $this->assertSame('publicado', $envio['estado']);
        $this->assertSame([$primera->sede_id], $envio['archivado_en_sedes']);

        $this->actingAs($this->general)->patchJson("/general/comunicados/{$primera->id}/archivar", ['motivo' => 'Ya pasó diciembre'])->assertOk();
        $this->assertSame('archivado', Comunicado::where('sede_id', $otra->id)->first()->estado);
        $this->assertSame('No aplica en esta sede', $primera->fresh()->motivo_archivo, 'La copia de la secretaría conserva su motivo');
        $this->patchJson("/general/comunicados/{$primera->id}/archivar", ['motivo' => 'Otra vez'])->assertJsonValidationErrors('motivo');
    }

    /* ── Precio y duración del programa ── */

    public function test_el_precio_queda_pactado_al_matricular(): void
    {
        $programa = $this->programa(['Mañana'], 1600, 10);
        $grupo = $this->grupo($programa, 'Mañana', null, 'activo', '2026-09-07');
        $this->actingAs($this->admin);
        $antes = app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $grupo, null);

        // El Admin sube el precio de 3.000.000 a 4.000.000; luego los dos eligen su plan
        $programa->update(['precio_total' => 4000000]);
        $despues = app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $grupo, null);
        foreach ([$antes, $despues] as $m) {
            $m->update(['plan_pago' => 'mensual']);
            app(PagoService::class)->generarCuotas($m->fresh(), $grupo->fecha_inicio);
        }

        $valor = fn ($m) => (int) Pago::where('matricula_id', $m->id)->where('tipo', 'mensual')->sum('monto_programa');
        $this->assertSame(3000000, $valor($antes));
        $this->assertSame(4000000, $valor($despues));
    }

    private function editarPrograma($programa, array $cambios = [])
    {
        return $this->actingAs($this->general)->putJson("/general/programas/{$programa->id}", array_merge([
            'nombre' => 'Técnico Laboral en '.$programa->nombre, 'codigo' => $programa->codigo, 'escuela_id' => $this->escuela->id, 'nivel' => 'Técnico Laboral',
            'modalidad' => 'Presencial', 'estado' => 'activo', 'resolucion' => 'En trámite', 'jornadas' => ['Mañana'], 'cupo_grupo' => 25,
            'duracion_meses' => 12, 'tipo_periodo' => 'semestre', 'precio_total' => 3000000, 'matricula' => 200000,
            'modulos' => [['nombre' => 'Fundamentos', 'horas' => 800], ['nombre' => 'Práctica', 'horas' => 800]],
        ], $cambios));
    }

    public function test_la_duracion_no_cambia_con_matriculas_vigentes(): void
    {
        $programa = $this->programa(['Mañana'], 1600, 10);
        $grupo = $this->grupo($programa, 'Mañana', null, 'activo', '2026-09-07');
        $this->actingAs($this->admin);
        $m = app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $grupo, null);

        $this->editarPrograma($programa)->assertJsonValidationErrors('duracion_meses');
        $this->editarPrograma($programa, ['duracion_meses' => 10, 'tipo_periodo' => 'trimestre'])->assertJsonValidationErrors('duracion_meses');
        $this->editarPrograma($programa, ['duracion_meses' => 10, 'precio_total' => 3500000])->assertOk();   // lo demás sí se edita

        // Aplazada también es vigente; retirada ya no
        app(MatriculaService::class)->cambiarEstado($m->fresh(), 'aplazado', today(), 'Viaje');
        $this->editarPrograma($programa)->assertJsonValidationErrors('duracion_meses');
        app(MatriculaService::class)->cambiarEstado($m->fresh(), 'retirado', today(), 'Cambio de ciudad');
        $this->editarPrograma($programa)->assertOk();
        $this->assertSame(12, $programa->fresh()->duracion_meses);
    }

    public function test_cambiar_la_duracion_no_reescribe_grupos_finalizados(): void
    {
        $programa = $this->programa(['Mañana'], 1600, 10);
        $viejo = $this->grupo($programa, 'Mañana', null, 'finalizado', '2025-01-13');
        $nuevo = $this->grupo($programa, 'Mañana', null, 'planificacion', '2027-01-11');

        $this->editarPrograma($programa)->assertOk();

        $this->assertSame('2025-11-13', $viejo->fresh()->fecha_fin->toDateString());
        $this->assertSame('2025-11-13', $viejo->fresh()->fin()->toDateString(), 'Los cálculos del grupo finalizado usan su fin guardado');
        $this->assertSame('2028-01-11', $nuevo->fresh()->fecha_fin->toDateString());
    }

    public function test_un_programa_sin_malla_no_gradua(): void
    {
        $programa = $this->programa(['Mañana'], 1600, 10);   // sin módulos
        $grupo = $this->grupo($programa, 'Mañana', null, 'activo', '2025-09-08');
        $this->actingAs($this->admin);
        $m = app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $grupo, null);
        $m->update(['fecha_inicio' => '2025-09-01', 'estado_fecha' => '2025-09-01']);
        $grupo->update(['estado' => 'finalizado']);

        $this->patchJson("/admin/matriculas/{$m->id}/estado", ['estado' => 'graduado', 'fecha' => today()->toDateString()])
            ->assertJsonValidationErrors(['estado' => 'no tiene malla']);
    }

    /* ── Pestañas en sedes distintas ── */

    public function test_una_pestana_de_otra_sede_no_actua_sobre_la_sede_nueva(): void
    {
        $otra = $this->otraSede();
        $this->actingAs($this->general)->post("/general/sedes/{$otra->id}/entrar");   // en otra pestaña entró a otra sede

        $this->getJson('/admin/estudiantes', ['X-Sede-Trabajo' => (string) $this->sede->id])->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Recarga la página'));
        $this->getJson('/admin/estudiantes', ['X-Sede-Trabajo' => (string) $otra->id])->assertOk();
        $this->getJson('/admin/estudiantes')->assertOk();   // sin el encabezado (formularios normales) sigue igual
    }

    /* ── Permisos ── */

    public function test_ninguna_ruta_del_panel_general_es_de_otro_rol(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'general'));
        $this->assertGreaterThan(40, $rutas->count());
        foreach (['admin', 'docente', 'estudiante', 'acudiente', 'superadmin'] as $rol) {
            $u = User::factory()->create(['role' => $rol, 'sede_id' => $this->sede->id]);
            foreach ($rutas as $r) {
                $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $r->uri());
                foreach (array_diff($r->methods(), ['HEAD']) as $metodo) {
                    $s = $this->actingAs($u)->json($metodo, $uri)->status();
                    $this->assertContains($s, [403, 404], "{$rol} {$metodo} {$uri} respondió {$s}");
                }
            }
        }
    }
}
