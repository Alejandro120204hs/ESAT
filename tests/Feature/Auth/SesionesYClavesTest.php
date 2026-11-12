<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Restablecer la clave desde la secretaría cierra las sesiones (B21), entrar con "Recordarme" cuenta como ingreso (B22) y
 * una cuenta desactivada no recupera su clave por correo (B23).
 */
class SesionesYClavesTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Cookie "Recordarme" tal como la arma Laravel: id|token|hash de la clave. */
    private function conCookieRecordarme(User $u): static
    {
        return $this->withCookie(Auth::guard('web')->getRecallerName(), $u->id.'|'.$u->remember_token.'|'.$u->getAuthPassword());
    }

    public function test_restablecer_la_clave_desde_la_secretaria_cierra_las_sesiones(): void
    {
        foreach (['estudiante' => 'estudiantes', 'acudiente' => 'acudientes'] as $rol => $ruta) {
            $u = User::factory()->create(['role' => $rol, 'sede_id' => $this->sede->id, 'remember_token' => 'token-viejo', 'numero_documento' => '10'.random_int(10000000, 99999999)]);

            $this->actingAs($this->admin)->patchJson("/admin/{$ruta}/{$u->id}/clave")->assertOk();

            $this->assertNotSame('token-viejo', $u->fresh()->remember_token, "La cookie \"Recordarme\" del {$rol} deja de servir");
            $this->assertTrue(password_verify($u->numero_documento, $u->fresh()->password));
        }
    }

    public function test_entrar_con_recordarme_guarda_el_ultimo_acceso(): void
    {
        $u = User::factory()->create(['remember_token' => 'token-recordarme', 'ultimo_acceso' => '2026-10-01 08:00:00']);
        Carbon::setTestNow('2026-11-11 09:30:00');

        $this->conCookieRecordarme($u)->get('/profile')->assertOk();

        $this->assertAuthenticatedAs($u);
        $this->assertSame('2026-11-11 09:30:00', $u->fresh()->ultimo_acceso->toDateTimeString());
    }

    public function test_recordarme_no_salta_el_bloqueo_por_inactividad(): void
    {
        $u = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'remember_token' => 'token-recordarme', 'ultimo_acceso' => now()->subDays(400)]);

        $this->conCookieRecordarme($u)->get('/profile')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('inactividad', $u->fresh()->motivo_bloqueo);
    }

    public function test_una_cuenta_desactivada_no_recibe_el_enlace_para_cambiar_la_clave(): void
    {
        Notification::fake();
        $u = User::factory()->create(['activo' => false]);

        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_un_enlace_viejo_no_cambia_la_clave_de_una_cuenta_desactivada(): void
    {
        $u = User::factory()->create();
        $token = Password::createToken($u);
        $u->update(['activo' => false]);
        $clave = $u->password;

        $this->post('/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'NuevaClave123*', 'password_confirmation' => 'NuevaClave123*'])
            ->assertSessionHasErrors(['email' => 'Tu cuenta está desactivada. Comunícate con la sede de ESAT para activarla.']);

        $this->assertSame($clave, $u->fresh()->password);
    }

    public function test_una_cuenta_activa_si_recibe_el_enlace(): void
    {
        Notification::fake();
        $u = User::factory()->create();

        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status');

        Notification::assertSentTo($u, ResetPassword::class);
    }
}
