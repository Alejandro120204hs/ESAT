<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nombres' => 'Laura Andrea',
                'apellidos' => 'Gómez Pérez',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Laura Andrea', $user->nombres);
        $this->assertSame('Gómez Pérez', $user->apellidos);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    /** El teléfono se pinta en los paneles del Admin: desde el perfil solo entra un celular (HALLAZGOS C1). */
    public function test_el_telefono_del_perfil_solo_acepta_un_celular(): void
    {
        $user  = User::factory()->create(['telefono' => '3001112233']);
        $datos = ['nombres' => $user->nombres, 'apellidos' => $user->apellidos, 'email' => $user->email];

        $this->actingAs($user)->from('/profile')
            ->patch('/profile', $datos + ['telefono' => '"><img src=x onerror=alert(1)>'])
            ->assertSessionHasErrors('telefono');
        $this->assertSame('3001112233', $user->fresh()->telefono);

        $this->actingAs($user)
            ->patch('/profile', $datos + ['telefono' => '310 123 4567'])
            ->assertSessionHasNoErrors();
        $this->assertSame('3101234567', $user->fresh()->telefono);

        $this->actingAs($user)
            ->patch('/profile', $datos + ['telefono' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->telefono);
    }

    /** Nadie borra su propia cuenta: se llevaría en cascada matrículas, pagos y recibos (HALLAZGOS C3). */
    public function test_el_usuario_no_puede_eliminar_su_cuenta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
        $this->actingAs($user)->get('/profile')->assertOk()->assertDontSee('Delete Account');
    }
}
