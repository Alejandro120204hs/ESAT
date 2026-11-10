<?php

namespace Tests\Feature\AdminGeneral;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * "Entrar a la sede": el Admin de ESAT trabaja en una sede con las pantallas reales de la secretaría (mismas reglas),
 * solo en la sede que eligió, y lo que hace queda en la auditoría de esa sede.
 */
class EntrarSedeTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private User $general;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();   // $this->sede con su secretaría ($this->admin)
        $this->general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
    }

    private function entrar($sede = null)
    {
        return $this->actingAs($this->general)->post('/general/sedes/'.($sede ?? $this->sede)->id.'/entrar');
    }

    public function test_sin_elegir_sede_vuelve_a_su_panel(): void
    {
        $this->actingAs($this->general)->get('/admin/estudiantes')->assertRedirect(route('general.dashboard', absolute: false));
        $this->actingAs($this->general)->getJson('/admin/estudiantes')->assertStatus(409);
    }

    public function test_al_entrar_trabaja_con_las_pantallas_de_la_secretaria_y_ve_la_franja(): void
    {
        $this->entrar()->assertRedirect(route('admin.dashboard', absolute: false));

        $this->actingAs($this->general)->get('/admin')->assertOk()
            ->assertSee('Estás trabajando en la sede')->assertSee($this->sede->nombre)->assertSee('Volver al panel general');
        $this->actingAs($this->general)->get('/admin/matriculas')->assertOk();
    }

    public function test_puede_llegar_directo_a_una_pantalla_y_no_a_cualquier_ruta(): void
    {
        $this->actingAs($this->general)->post('/general/sedes/'.$this->sede->id.'/entrar', ['destino' => 'matriculas'])
            ->assertRedirect(route('admin.matriculas', absolute: false));
        $this->actingAs($this->general)->post('/general/sedes/'.$this->sede->id.'/entrar', ['destino' => '../superadmin'])
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_solo_ve_y_toca_la_sede_que_eligio(): void
    {
        $otra = $this->otraSede('Sede lejana', 'La Dorada');
        $aqui = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'nombres' => 'Estudiante Cercano']);
        $alla = User::factory()->create(['role' => 'estudiante', 'sede_id' => $otra->id, 'nombres' => 'Estudiante Lejano']);

        $this->entrar();
        $this->actingAs($this->general)->get('/admin/estudiantes')->assertOk()
            ->assertSee('Estudiante Cercano')->assertDontSee('Estudiante Lejano');

        $this->actingAs($this->general)->putJson("/admin/estudiantes/{$alla->id}", ['nombres' => 'X'])->assertForbidden();

        /* Al cambiar de sede, cambia lo que ve */
        $this->entrar($otra);
        $this->actingAs($this->general)->get('/admin/estudiantes')->assertOk()
            ->assertSee('Estudiante Lejano')->assertDontSee('Estudiante Cercano');
    }

    public function test_lo_que_registra_queda_en_la_sede_elegida_y_en_su_auditoria(): void
    {
        $this->entrar();
        $this->actingAs($this->general)->postJson('/admin/estudiantes', [
            'nombres' => 'Laura Daniela', 'apellidos' => 'Gómez Patiño', 'fecha_nacimiento' => '2002-11-05',
            'departamento_nacimiento' => 'Tolima', 'lugar_nacimiento' => 'Honda', 'genero' => 'femenino',
            'tipo_documento' => 'CC', 'numero_documento' => '1070123456', 'telefono' => '3157720419',
            'email' => 'laura.gomez@example.com', 'direccion' => 'Calle 4 # 6-21',
            'departamento_residencia' => 'Cundinamarca', 'ciudad_residencia' => 'Villeta', 'con_acudiente' => false,
        ])->assertOk();

        $e = User::where('numero_documento', '1070123456')->firstOrFail();
        $this->assertSame($this->sede->id, $e->sede_id);
        $this->assertDatabaseHas('auditorias', ['user_id' => $this->general->id, 'sede_id' => $this->sede->id, 'entidad_id' => $e->id]);

        /* La secretaría de la sede lo ve en su actividad reciente */
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee($this->general->nombres);
    }

    public function test_al_salir_vuelve_al_panel_general(): void
    {
        $this->entrar();
        $this->actingAs($this->general)->post('/general/salir-de-sede')->assertRedirect(route('general.dashboard', absolute: false));
        $this->actingAs($this->general)->get('/admin')->assertRedirect(route('general.dashboard', absolute: false));
    }

    public function test_la_secretaria_no_cambia_de_sede_ni_ve_la_franja(): void
    {
        $otra = $this->otraSede('Sede lejana', 'La Dorada');

        $this->actingAs($this->admin)->post('/general/sedes/'.$otra->id.'/entrar')->assertForbidden();
        $this->actingAs($this->admin)->withSession([User::SESION_SEDE => $otra->id])->get('/admin')->assertOk()
            ->assertDontSee('Estás trabajando en la sede')->assertSee($this->sede->nombre);
        $this->assertSame($this->sede->id, $this->admin->sedeTrabajoId());
    }

    public function test_otros_roles_no_entran(): void
    {
        foreach (['estudiante', 'docente', 'acudiente', 'superadmin'] as $rol) {
            $u = User::factory()->create(['role' => $rol]);
            $this->actingAs($u)->post('/general/sedes/'.$this->sede->id.'/entrar')->assertForbidden();
            $this->actingAs($u)->get('/admin')->assertForbidden();
        }
    }
}
