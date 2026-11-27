<?php

namespace Tests\Feature\Superadmin;

use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Panel Superadmin › Admin de ESAT: el Superadmin crea y maneja la cuenta del Admin (rol admin_general, una sola, sin
 * sede); las secretarías las crea el Admin. Editar, activar/desactivar y restablecer la clave solo tocan esa cuenta,
 * aunque la ruta reciba cualquier id de users (HALLAZGOS C4).
 */
class AdministradoresTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;
    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede       = Sede::create(['nombre' => 'Sede de prueba', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Calle 1 # 2-3']);
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    public function test_crea_la_cuenta_del_admin_sin_sede_y_con_la_clave_de_su_documento(): void
    {
        $this->actingAs($this->superadmin)->get(route('superadmin.administradores'))->assertOk()->assertSee('data-open-wizard', false);

        $this->postJson(route('superadmin.administradores.store'), $this->datos())->assertOk();

        $admin = User::where('email', 'marta.rojas@esat.edu.co')->firstOrFail();
        $this->assertSame('admin_general', $admin->role->value);
        $this->assertNull($admin->sede_id);
        $this->assertTrue(Hash::check('52123456', $admin->password));
        $this->assertSame('Sasaima', $admin->lugar_nacimiento);
        $this->assertDatabaseHas('auditorias', ['accion' => 'crear', 'entidad_tipo' => 'User', 'entidad_id' => $admin->id]);
        $this->get(route('superadmin.administradores'))->assertSee('Marta Lucía')->assertDontSee('data-open-wizard', false);
    }

    public function test_solo_hay_un_admin(): void
    {
        User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);

        $this->actingAs($this->superadmin)->postJson(route('superadmin.administradores.store'), $this->datos())
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Ya existe'));
        $this->assertSame(1, User::where('role', 'admin_general')->count());
    }

    public function test_valida_mayor_de_edad_celular_y_documento_unico(): void
    {
        User::factory()->create(['numero_documento' => '52123456']);

        $this->actingAs($this->superadmin)->postJson(route('superadmin.administradores.store'), $this->datos([
            'fecha_nacimiento' => now()->subYears(16)->toDateString(), 'telefono' => '6011234567',
        ]))->assertJsonValidationErrors(['fecha_nacimiento', 'telefono', 'numero_documento']);
    }

    public function test_edita_desactiva_y_restablece_la_clave_del_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin_general', 'sede_id' => null, 'numero_documento' => '52123456']);
        $this->actingAs($this->superadmin);

        $this->putJson(route('superadmin.administradores.update', $admin), $this->datos(['correo' => 'otro@esat.edu.co']))->assertOk();
        $this->assertSame('Marta Lucía', $admin->fresh()->nombres);
        $this->assertNull($admin->fresh()->sede_id);

        $this->patchJson(route('superadmin.administradores.toggle', $admin))->assertOk()->assertJson(['activo' => false]);
        $this->assertFalse($admin->fresh()->activo);

        $admin->forceFill(['bloqueado_en' => now(), 'motivo_bloqueo' => 'manual', 'password' => Hash::make('otra')])->save();
        $this->patchJson(route('superadmin.administradores.clave', $admin))->assertOk();
        $this->assertTrue(Hash::check('52123456', $admin->fresh()->password));
        $this->assertNull($admin->fresh()->bloqueado_en);
    }

    public function test_no_toca_cuentas_que_no_son_del_admin(): void
    {
        $secretaria = User::factory()->create(['role' => 'admin', 'sede_id' => $this->sede->id, 'nombres' => 'Original']);
        $estudiante = User::factory()->create(['sede_id' => $this->sede->id]);
        $this->actingAs($this->superadmin);

        foreach ([$secretaria, $estudiante, $this->superadmin] as $usuario) {
            $this->patchJson(route('superadmin.administradores.toggle', $usuario))->assertNotFound();
            $this->patchJson(route('superadmin.administradores.clave', $usuario))->assertNotFound();
            $this->assertTrue($usuario->fresh()->activo, "{$usuario->role->value} sigue activo");
        }
        $this->putJson(route('superadmin.administradores.update', $secretaria), $this->datos())->assertNotFound();
        $this->assertSame('Original', $secretaria->fresh()->nombres);
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombres'                 => 'Marta Lucía',
            'apellidos'               => 'Rojas Díaz',
            'genero'                  => 'femenino',
            'fecha_nacimiento'        => '1985-04-12',
            'departamento_nacimiento' => 'Cundinamarca',
            'ciudad_nacimiento'       => 'Sasaima',
            'tipo_documento'          => 'CC',
            'numero_documento'        => '52.123.456',
            'telefono'                => '310 123 4567',
            'correo'                  => 'Marta.Rojas@esat.edu.co',
        ], $cambios);
    }
}
