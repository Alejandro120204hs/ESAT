<?php

namespace Tests\Feature\Docente;

use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel Docente (por ahora solo frontend con datos de ejemplo): el docente entra a su propio
 * panel y solo él ve sus pantallas.
 */
class PanelDocenteTest extends TestCase
{
    use RefreshDatabase;

    private const PANTALLAS = ['/docente', '/docente/grupos', '/docente/asistencia', '/docente/notas'];

    private function docente(): User
    {
        $sede = Sede::create(['nombre' => 'Sede de prueba', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Calle 1 # 2-3']);

        return User::factory()->create(['role' => 'docente', 'sede_id' => $sede->id]);
    }

    public function test_al_ingresar_el_docente_llega_a_su_panel(): void
    {
        $docente = $this->docente();

        $this->post('/login', ['email' => $docente->email, 'password' => 'password'])
            ->assertRedirect(route('docente.dashboard', absolute: false));
    }

    public function test_el_docente_abre_sus_pantallas(): void
    {
        $docente = $this->docente();

        foreach (self::PANTALLAS as $url) {
            $this->actingAs($docente)->get($url)->assertOk()->assertSee('datos-ejemplo.js', false);
        }
    }

    public function test_otros_roles_no_entran_al_panel_docente(): void
    {
        foreach (['admin', 'estudiante', 'acudiente', 'superadmin'] as $rol) {
            $usuario = User::factory()->create(['role' => $rol]);
            foreach (self::PANTALLAS as $url) {
                $this->actingAs($usuario)->get($url)->assertForbidden();
            }
        }
    }

    public function test_sin_sesion_lleva_al_login(): void
    {
        foreach (self::PANTALLAS as $url) {
            $this->get($url)->assertRedirect(route('login', absolute: false));
        }
    }
}
