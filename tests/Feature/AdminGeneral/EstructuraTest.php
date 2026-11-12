<?php

namespace Tests\Feature\AdminGeneral;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Escuela;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/** Admin de ESAT › Sedes, Secretarías, Programas y Escuelas (backend real del panel general). */
class EstructuraTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private User $general;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();
        $this->general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
    }

    /* ── Sedes ── */

    public function test_crea_y_edita_una_sede_y_queda_en_la_auditoria_de_esa_sede(): void
    {
        $this->actingAs($this->general)->postJson('/general/sedes', [
            'nombre' => 'Villeta', 'departamento' => 'Cundinamarca', 'ciudad' => 'Villeta', 'direccion' => 'Calle 5 # 4-20',
        ])->assertCreated();
        $sede = Sede::where('nombre', 'Villeta')->firstOrFail();
        $this->assertSame($sede->id, Auditoria::latest('id')->first()->sede_id);

        $this->putJson("/general/sedes/{$sede->id}", ['nombre' => 'Villeta Centro', 'departamento' => 'Cundinamarca', 'ciudad' => 'Villeta', 'direccion' => 'Calle 5 # 4-20'])->assertOk();
        $this->assertSame('Villeta Centro', $sede->fresh()->nombre);
    }

    public function test_la_sede_no_repite_nombre_y_la_ciudad_es_del_departamento(): void
    {
        $this->actingAs($this->general)->postJson('/general/sedes', [
            'nombre' => 'Sede de prueba', 'departamento' => 'Cundinamarca', 'ciudad' => 'Medellín', 'direccion' => 'Calle 5 # 4-20',
        ])->assertJsonValidationErrors(['nombre', 'ciudad']);
    }

    public function test_la_secretaria_no_entra_a_las_rutas_del_panel_general(): void
    {
        $this->actingAs($this->admin)->postJson('/general/sedes', ['nombre' => 'X'])->assertForbidden();
        $this->actingAs($this->admin)->get('/general/sedes')->assertForbidden();
    }

    /* ── Secretarías ── */

    private function datosSecretaria(array $cambios = []): array
    {
        return array_merge([
            'nombres' => 'Andrea', 'apellidos' => 'Ramírez Soto', 'genero' => 'femenino', 'fecha_nacimiento' => '1990-05-10',
            'tipo_documento' => 'CC', 'numero_documento' => '1012345678', 'telefono' => '3001234567',
            'email' => 'andrea@esat.edu.co', 'sede_id' => $this->sede->id,
        ], $cambios);
    }

    public function test_crea_una_secretaria_con_la_clave_igual_al_documento(): void
    {
        $this->actingAs($this->general)->postJson('/general/secretarias', $this->datosSecretaria())->assertCreated();
        $u = User::where('email', 'andrea@esat.edu.co')->firstOrFail();
        $this->assertSame('admin', $u->role->value);
        $this->assertTrue(Hash::check('1012345678', $u->password));
    }

    public function test_la_secretaria_es_mayor_de_edad_y_no_repite_documento_ni_correo(): void
    {
        $this->actingAs($this->general)->postJson('/general/secretarias', $this->datosSecretaria([
            'fecha_nacimiento' => now()->subYears(16)->toDateString(), 'email' => $this->admin->email, 'numero_documento' => $this->admin->numero_documento,
        ]))->assertJsonValidationErrors(['fecha_nacimiento', 'email', 'numero_documento']);
    }

    public function test_desactiva_y_restablece_la_clave_cerrando_sus_sesiones(): void
    {
        config(['session.driver' => 'database']);   // como en el .env: las sesiones viven en la BD
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $this->admin->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->general)->patchJson("/general/secretarias/{$this->admin->id}/estado")->assertOk();
        $this->assertFalse($this->admin->fresh()->activo);

        $this->patchJson("/general/secretarias/{$this->admin->id}/clave")->assertOk();
        $this->assertTrue(Hash::check($this->admin->numero_documento, $this->admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
    }

    public function test_las_rutas_de_secretarias_no_tocan_otras_cuentas(): void
    {
        $docente = $this->docente();
        $this->actingAs($this->general)->patchJson("/general/secretarias/{$docente->id}/estado")->assertNotFound();
    }

    /* ── Programas y escuelas ── */

    private function datosPrograma(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Técnico Laboral en Logística', 'codigo' => 'ESA-ADM-050', 'escuela_id' => $this->escuela->id, 'nivel' => 'Técnico Laboral',
            'modalidad' => 'Presencial', 'estado' => 'activo', 'resolucion' => 'En trámite', 'jornadas' => ['Mañana'], 'cupo_grupo' => 25,
            'duracion_meses' => 10, 'tipo_periodo' => 'trimestre', 'precio_total' => 1500000, 'matricula' => 300000,
            'modulos' => [['nombre' => 'Fundamentos', 'horas' => 200], ['nombre' => 'Práctica', 'horas' => 600]],
        ], $cambios);
    }

    public function test_crea_un_programa_con_su_malla_y_las_horas_son_la_suma(): void
    {
        $this->actingAs($this->general)->postJson('/general/programas', $this->datosPrograma())->assertCreated();
        $p = Programa::where('codigo', 'ESA-ADM-050')->firstOrFail();
        $this->assertSame(800, $p->horas);
        $this->assertSame(['Fundamentos', 'Práctica'], $p->modulos->pluck('nombre')->all());
        $this->assertSame(4, $p->duracion_periodos);
    }

    public function test_la_matricula_no_supera_el_precio_y_el_precio_es_redondo(): void
    {
        $this->actingAs($this->general)->postJson('/general/programas', $this->datosPrograma(['precio_total' => 1500050, 'matricula' => 2000000]))
            ->assertJsonValidationErrors(['precio_total', 'matricula']);
    }

    public function test_editar_no_deja_el_cupo_por_debajo_de_un_grupo_vigente(): void
    {
        $p = $this->programa(['Mañana'], 800, 10, 25);
        $g = $this->grupo($p, 'Mañana', null, 'activo', now()->subMonth()->toDateString());
        foreach (range(1, 3) as $i) {
            $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
            $g->estudiantes()->attach($e->id);
            Matricula::create(['codigo' => "MAT-{$i}", 'estudiante_id' => $e->id, 'programa_id' => $p->id, 'curso_id' => $g->id, 'sede_id' => $this->sede->id, 'estado' => 'activo', 'fecha_inicio' => now()->subMonth()]);
        }
        $this->actingAs($this->general)->putJson("/general/programas/{$p->id}", $this->datosPrograma(['nombre' => $p->nombre, 'codigo' => $p->codigo, 'cupo_grupo' => 2]))
            ->assertJsonValidationErrors('cupo_grupo');
    }

    public function test_con_notas_la_malla_ya_no_cambia(): void
    {
        $p = $this->programa(['Mañana'], 800, 10);
        $m = $p->modulos()->create(['nombre' => 'Fundamentos', 'horas' => 800, 'orden' => 1]);
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        $mat = Matricula::create(['codigo' => 'MAT-9', 'estudiante_id' => $e->id, 'programa_id' => $p->id, 'sede_id' => $this->sede->id, 'estado' => 'activo', 'fecha_inicio' => now()]);
        Calificacion::create(['matricula_id' => $mat->id, 'programa_modulo_id' => $m->id, 'nota' => 4.0, 'fecha_nota' => now()]);

        $this->actingAs($this->general)->putJson("/general/programas/{$p->id}", $this->datosPrograma([
            'nombre' => $p->nombre, 'codigo' => $p->codigo, 'modulos' => [['nombre' => 'Otro módulo', 'horas' => 800]],
        ]))->assertJsonValidationErrors('modulos');
    }

    public function test_no_se_desactiva_ni_se_quita_de_una_sede_con_grupos_vigentes(): void
    {
        $p = $this->programa();
        $this->grupo($p, 'Mañana');
        $this->actingAs($this->general)->patchJson("/general/programas/{$p->id}/estado")->assertStatus(422);
        $this->patchJson("/general/programas/{$p->id}/sedes/{$this->sede->id}")->assertStatus(422);
        $this->assertTrue($p->sedes()->where('sedes.id', $this->sede->id)->exists());
    }

    public function test_ofrece_el_programa_en_otra_sede(): void
    {
        $p = $this->programa();
        $otra = $this->otraSede();
        $this->actingAs($this->general)->patchJson("/general/programas/{$p->id}/sedes/{$otra->id}")->assertOk();
        $this->assertTrue($p->sedes()->where('sedes.id', $otra->id)->exists());
        $this->assertSame($otra->id, Auditoria::latest('id')->first()->sede_id);
    }

    public function test_al_crear_elige_en_que_sedes_se_ofrece(): void
    {
        $otra = $this->otraSede();
        $this->otraSede('Tercera sede', 'Guaduas');
        $this->actingAs($this->general)->postJson('/general/programas', $this->datosPrograma(['sedes' => [$this->sede->id, $otra->id]]))->assertCreated();
        $p = Programa::where('codigo', 'ESA-ADM-050')->first();
        $this->assertEqualsCanonicalizing([$this->sede->id, $otra->id], $p->sedes()->pluck('sedes.id')->all());
        // Cada sede ve en su actividad que se le asignó
        $this->assertSame(1, Auditoria::where('sede_id', $otra->id)->where('descripcion', 'like', 'Asignó el programa%')->count());

        // Sin sedes queda solo en el catálogo
        $this->postJson('/general/programas', $this->datosPrograma(['nombre' => 'Técnico Laboral en Bodegaje', 'codigo' => 'ESA-ADM-051', 'sedes' => []]))->assertCreated();
        $this->assertSame(0, Programa::where('codigo', 'ESA-ADM-051')->first()->sedes()->count());
    }

    public function test_al_editar_cambia_sus_sedes_sin_quitar_una_con_grupos_vigentes(): void
    {
        $otra = $this->otraSede();
        $p = $this->programa(['Mañana'], 800, 10);
        $p->modulos()->create(['nombre' => 'Fundamentos', 'horas' => 800, 'orden' => 1]);
        $p->sedes()->attach($otra->id);
        $this->grupo($p, 'Mañana');   // grupo vigente en $this->sede
        $datos = fn (array $sedes) => $this->datosPrograma(['nombre' => $p->nombre.' editado', 'codigo' => $p->codigo, 'duracion_meses' => 10, 'tipo_periodo' => 'semestre',
            'modulos' => [['nombre' => 'Fundamentos', 'horas' => 800]], 'sedes' => $sedes]);

        $this->actingAs($this->general)->putJson("/general/programas/{$p->id}", $datos([$otra->id]))->assertJsonValidationErrors('sedes');
        $this->putJson("/general/programas/{$p->id}", $datos([$this->sede->id]))->assertOk();
        $this->assertSame([$this->sede->id], $p->sedes()->pluck('sedes.id')->all());
        $this->assertSame(1, Auditoria::where('sede_id', $otra->id)->where('descripcion', 'like', 'Quitó el programa%')->count());

        // Inactivo: no se ofrece en sedes nuevas (sí puede quedarse donde estaba)
        $p->update(['estado' => 'inactivo']);
        $this->putJson("/general/programas/{$p->id}", $datos([$this->sede->id, $otra->id]) + ['estado' => 'inactivo'])->assertJsonValidationErrors('sedes');
    }

    public function test_la_secretaria_solo_elige_que_ofrece_su_sede(): void
    {
        $p = $this->programa();
        $p->sedes()->detach();
        $this->actingAs($this->admin)->patchJson("/admin/programas/{$p->id}/oferta")->assertOk();
        $this->assertTrue($p->sedes()->where('sedes.id', $this->sede->id)->exists());

        $this->grupo($p, 'Mañana');
        $this->patchJson("/admin/programas/{$p->id}/oferta")->assertStatus(422);

        // Crear o editar programas y escuelas ya no está en la sede (es del Admin de ESAT)
        $this->postJson('/admin/programas', $this->datosPrograma())->assertStatus(405);
        $this->postJson('/admin/escuelas', ['nombre' => 'Belleza'])->assertNotFound();
    }

    public function test_escuela_con_sigla_automatica_y_no_se_elimina_con_programas(): void
    {
        $this->actingAs($this->general)->postJson('/general/escuelas', ['nombre' => 'Belleza'])->assertCreated();
        $this->assertSame('BEL', Escuela::where('nombre', 'Belleza')->value('sigla'));

        $this->programa();
        $this->deleteJson("/general/escuelas/{$this->escuela->id}")->assertStatus(422);
    }
}
