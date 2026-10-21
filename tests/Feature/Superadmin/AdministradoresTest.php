<?php

namespace Tests\Feature\Superadmin;

use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel Superadmin › Administradores: editar y activar/desactivar solo toca cuentas de
 * administrador, aunque la ruta reciba cualquier id de users (HALLAZGOS C4).
 */
class AdministradoresTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;
    private User $superadmin;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede       = Sede::create(['nombre' => 'Sede de prueba', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Calle 1 # 2-3']);
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->admin      = User::factory()->create(['role' => 'admin', 'sede_id' => $this->sede->id]);
    }

    public function test_desactiva_y_activa_a_un_administrador(): void
    {
        $this->actingAs($this->superadmin)->patchJson(route('superadmin.administradores.toggle', $this->admin))
            ->assertOk()->assertJson(['ok' => true, 'activo' => false]);
        $this->assertFalse($this->admin->fresh()->activo);

        $this->actingAs($this->superadmin)->patchJson(route('superadmin.administradores.toggle', $this->admin))
            ->assertOk()->assertJson(['activo' => true]);
    }

    public function test_no_desactiva_cuentas_que_no_son_de_administrador(): void
    {
        $estudiante = User::factory()->create(['sede_id' => $this->sede->id]);

        foreach ([$estudiante, $this->superadmin] as $usuario) {
            $this->actingAs($this->superadmin)->patchJson(route('superadmin.administradores.toggle', $usuario))
                ->assertNotFound();
            $this->assertTrue($usuario->fresh()->activo, "{$usuario->role->value} sigue activo");
        }
    }

    public function test_edita_a_un_administrador(): void
    {
        $this->actingAs($this->superadmin)->putJson(route('superadmin.administradores.update', $this->admin), $this->datos())
            ->assertOk();

        $this->assertSame('Marta Lucía', $this->admin->fresh()->nombres);
    }

    public function test_no_edita_cuentas_que_no_son_de_administrador(): void
    {
        $estudiante = User::factory()->create(['sede_id' => $this->sede->id, 'nombres' => 'Original']);

        $this->actingAs($this->superadmin)->putJson(route('superadmin.administradores.update', $estudiante), $this->datos())
            ->assertNotFound();

        $this->assertSame('Original', $estudiante->fresh()->nombres);
    }

    private function datos(): array
    {
        return [
            'nombres'                 => 'Marta Lucía',
            'apellidos'               => 'Rojas Díaz',
            'genero'                  => 'femenino',
            'fecha_nacimiento'        => '1985-04-12',
            'departamento_nacimiento' => 'Cundinamarca',
            'ciudad_nacimiento'       => 'Sasaima',
            'tipo_documento'          => 'CC',
            'numero_documento'        => '52123456',
            'telefono'                => '3101234567',
            'correo'                  => 'marta.rojas@example.com',
            'sede_id'                 => $this->sede->id,
        ];
    }
}
