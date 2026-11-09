<?php

namespace Tests\Feature\AdminGeneral;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel Admin de ESAT general (rol admin_general, por ahora solo frontend con datos de ejemplo):
 * entra a su propio panel, sin sede, y solo él ve sus pantallas.
 */
class PanelGeneralTest extends TestCase
{
    use RefreshDatabase;

    private const PANTALLAS = [
        '/general', '/general/sedes', '/general/secretarias', '/general/programas', '/general/estudiantes', '/general/acudientes', '/general/docentes', '/general/academico',
        '/general/finanzas', '/general/comunicados', '/general/foro', '/general/reportes', '/general/auditoria', '/general/configuracion',
    ];

    private function adminGeneral(): User
    {
        return User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
    }

    public function test_al_ingresar_llega_a_su_panel(): void
    {
        $admin = $this->adminGeneral();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('general.dashboard', absolute: false));
    }

    public function test_abre_sus_pantallas_y_se_presenta_como_admin(): void
    {
        $admin = $this->adminGeneral();

        foreach (self::PANTALLAS as $url) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('datos-ejemplo.js', false)
                ->assertSee('ESAT · Panel general');
        }
    }

    public function test_otros_roles_no_entran(): void
    {
        foreach (['superadmin', 'admin', 'estudiante', 'docente', 'acudiente'] as $rol) {
            $usuario = User::factory()->create(['role' => $rol]);
            foreach (self::PANTALLAS as $url) {
                $this->actingAs($usuario)->get($url)->assertForbidden();
            }
        }
    }

    public function test_no_entra_a_los_paneles_de_sede(): void
    {
        $admin = $this->adminGeneral();

        foreach (['/admin', '/superadmin', '/docente'] as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }
    }

    public function test_su_perfil_usa_el_diseno_del_panel_y_guarda(): void
    {
        $admin = $this->adminGeneral();

        $this->actingAs($admin)->get('/profile')->assertOk()
            ->assertSee('assets/css/admin_general/profile.css', false)
            ->assertSee('Todas las sedes');

        $this->actingAs($admin)->patch('/profile', [
            'nombres' => 'Diana', 'apellidos' => 'Castillo', 'email' => 'diana@esat.edu.co', 'telefono' => '310 555 1234',
        ])->assertRedirect('/profile')->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('diana@esat.edu.co', $admin->email);
        $this->assertSame('3105551234', $admin->telefono);

        $this->actingAs($admin)->patch('/profile', ['nombres' => 'Diana', 'apellidos' => 'Castillo', 'email' => 'diana@esat.edu.co', 'telefono' => '123'])
            ->assertSessionHasErrors('telefono');
    }

    public function test_cambia_su_clave(): void
    {
        $admin = $this->adminGeneral();

        $this->actingAs($admin)->from('/profile')->put('/password', [
            'current_password' => 'password', 'password' => 'NuevaClave2026!', 'password_confirmation' => 'NuevaClave2026!',
        ])->assertRedirect('/profile')->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NuevaClave2026!', $admin->refresh()->password));
    }

    public function test_sin_sesion_lleva_al_login(): void
    {
        foreach (self::PANTALLAS as $url) {
            $this->get($url)->assertRedirect(route('login', absolute: false));
        }
    }
}
