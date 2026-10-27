<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una cuenta desactivada no solo no puede ingresar: también pierde la sesión que ya
 * tenía abierta y su cookie de "Recordarme" (HALLAZGOS C5, EnsureAccountIsEnabled).
 */
class CuentaHabilitadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_cuenta_activa_sigue_adentro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_desactivar_la_cuenta_corta_la_sesion_abierta(): void
    {
        $user = User::factory()->create(['remember_token' => 'token-viejo']);
        $this->actingAs($user)->get('/profile')->assertOk();

        $user->update(['activo' => false]);

        $this->actingAs($user)->get('/profile')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Tu cuenta está desactivada. Comunícate con la sede de ESAT para activarla.']);
        $this->assertGuest();
        $this->assertNotSame('token-viejo', $user->fresh()->remember_token, 'La cookie de "Recordarme" deja de servir');
    }

    public function test_las_peticiones_del_panel_reciben_401(): void
    {
        $user = User::factory()->create(['activo' => false]);

        $this->actingAs($user)->patchJson('/profile', [])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Tu cuenta está desactivada. Comunícate con la sede de ESAT para activarla.']);
        $this->assertGuest();
    }
}
