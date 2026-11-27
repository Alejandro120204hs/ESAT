<?php

namespace Tests\Feature\Estudiante;

use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel Estudiante (solo frontend por ahora): el estudiante entra a su propio panel, solo él ve sus
 * pantallas, y su perfil es el del panel (real: guarda con ProfileController).
 */
class PanelEstudianteTest extends TestCase
{
    use RefreshDatabase;

    private const PANTALLAS = ['/estudiante', '/estudiante/programa', '/estudiante/aula', '/estudiante/pagos', '/estudiante/comunicados', '/estudiante/foro'];

    private function estudiante(): User
    {
        $sede = Sede::create(['nombre' => 'Sede de prueba', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Calle 1 # 2-3']);

        return User::factory()->create(['role' => 'estudiante', 'sede_id' => $sede->id, 'nombres' => 'Valentina', 'apellidos' => 'Ruiz Moreno']);
    }

    public function test_al_ingresar_el_estudiante_llega_a_su_panel(): void
    {
        $estudiante = $this->estudiante();

        $this->post('/login', ['email' => $estudiante->email, 'password' => 'password'])
            ->assertRedirect(route('estudiante.dashboard', absolute: false));
    }

    public function test_si_antes_se_pidio_una_pagina_de_su_panel_vuelve_a_ella(): void
    {
        $estudiante = $this->estudiante();

        $this->get('/estudiante/aula?id=104')->assertRedirect(route('login', absolute: false));

        $this->post('/login', ['email' => $estudiante->email, 'password' => 'password'])
            ->assertRedirect('/estudiante/aula?id=104');
    }

    public function test_el_estudiante_abre_sus_pantallas_con_su_nombre_y_su_sede(): void
    {
        $estudiante = $this->estudiante();

        foreach (self::PANTALLAS as $url) {
            $this->actingAs($estudiante)->get($url)->assertOk()
                ->assertSee('ESAT Estudiante')
                ->assertSee('window.EST_SERVIDOR', false)
                ->assertSee('assets/js/estudiante/datos.js', false)
                ->assertSee('Sede de prueba');
        }
        $this->actingAs($estudiante)->get('/estudiante')->assertSee('Hola, Valentina');
    }

    public function test_otros_roles_no_entran_al_panel_estudiante(): void
    {
        foreach (['admin', 'admin_general', 'docente', 'superadmin'] as $rol) {
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

    public function test_su_perfil_es_el_del_panel_y_guarda_su_contacto_sin_cambiar_su_nombre(): void
    {
        $estudiante = $this->estudiante();

        $this->actingAs($estudiante)->get('/profile')->assertOk()
            ->assertSee('ESAT Estudiante')
            ->assertSee('Datos de contacto')
            ->assertDontSee("You're logged in!");

        // Aunque mande otro nombre (alterando el formulario), su nombre no cambia: sale en sus certificados
        $this->actingAs($estudiante)->patch('/profile', [
            'nombres'   => 'Otro',
            'apellidos' => 'Nombre',
            'email'     => $estudiante->email,
            'telefono'  => '310 456 7890',
            'direccion' => 'Calle 5 # 3-20',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $estudiante->refresh();
        $this->assertSame('3104567890', $estudiante->telefono);
        $this->assertSame('Calle 5 # 3-20', $estudiante->direccion);
        $this->assertSame('Valentina', $estudiante->nombres);
        $this->assertSame('Ruiz Moreno', $estudiante->apellidos);
    }
}
