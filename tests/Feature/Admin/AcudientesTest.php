<?php

namespace Tests\Feature\Admin;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Panel Admin › Acudientes: registrar al acudiente con sus estudiantes a cargo, editarlo,
 * vincular/desvincular, clave y cuenta. Reglas: un solo acudiente por estudiante y
 * ningún menor de edad queda sin acudiente. Más el login: una cuenta inactiva no entra.
 */
class AcudientesTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();
    }

    /* ── Registrar ── */

    public function test_registra_al_acudiente_con_sus_estudiantes(): void
    {
        $a = $this->estudiante('1070000001');
        $b = $this->estudiante('1070000002');

        $this->registrar(['estudiantes' => [['id' => $a->id, 'parentesco' => 'Madre'], ['id' => $b->id, 'parentesco' => 'Tío(a)']]])
            ->assertOk()->assertJsonFragment(['ok' => true]);

        $acu = User::where('numero_documento', '39645218')->first();
        $this->assertSame(RolUsuario::Acudiente, $acu->role);
        $this->assertSame($this->sede->id, $acu->sede_id);
        $this->assertSame('Comerciante', $acu->profesion);
        $this->assertTrue(Hash::check('39645218', $acu->password), 'La clave inicial es el documento');
        $this->assertSame(['Madre', 'Tío(a)'], $acu->estudiantesACargo()->orderBy('users.id')->get()->pluck('pivot.parentesco')->all());
        $this->assertDatabaseHas('auditorias', ['accion' => 'crear', 'entidad_tipo' => 'User', 'entidad_id' => $acu->id]);
    }

    public function test_al_registrar_necesita_al_menos_un_estudiante(): void
    {
        $this->registrar(['estudiantes' => []])->assertUnprocessable()->assertJsonValidationErrors(['estudiantes']);
    }

    public function test_el_correo_es_obligatorio_y_el_documento_unico(): void
    {
        $e = $this->estudiante('1070000001');
        $vinculo = ['estudiantes' => [['id' => $e->id, 'parentesco' => 'Madre']]];

        $this->registrar(['email' => ''] + $vinculo)->assertUnprocessable()->assertJsonValidationErrors(['email']);
        // El documento de un estudiante (o de cualquier usuario) no se puede repetir
        $this->registrar(['numero_documento' => '1070000001'] + $vinculo)->assertUnprocessable()->assertJsonValidationErrors(['numero_documento']);
    }

    public function test_solo_vincula_estudiantes_de_su_sede(): void
    {
        $ajeno = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->otraSede()->id]);

        $this->registrar(['estudiantes' => [['id' => $ajeno->id, 'parentesco' => 'Padre']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['estudiantes']);
    }

    public function test_cada_estudiante_tiene_un_solo_acudiente(): void
    {
        $e = $this->estudiante('1070000001');
        $anterior = $this->acudiente([$e->id => 'Padre']);

        $this->registrar(['estudiantes' => [['id' => $e->id, 'parentesco' => 'Madre']]])->assertOk();

        $this->assertSame(1, $e->acudientes()->count());
        $this->assertSame('39645218', $e->acudientes()->first()->numero_documento);
        $this->assertSame(0, $anterior->estudiantesACargo()->count());
    }

    public function test_la_bd_no_permite_dos_acudientes_para_un_estudiante(): void
    {
        $e = $this->estudiante('1070000001');
        $this->acudiente([$e->id => 'Padre']);
        $otro = $this->acudiente([], '52310477');

        $this->expectException(\Illuminate\Database\QueryException::class);
        \DB::table('acudiente_estudiante')->insert(['acudiente_id' => $otro->id, 'estudiante_id' => $e->id, 'parentesco' => 'Madre']);
    }

    public function test_desde_estudiantes_no_se_vincula_un_acudiente_de_otra_sede(): void
    {
        $e = $this->estudiante('1070000001', 16);
        $ajeno = User::factory()->create(['role' => 'acudiente', 'sede_id' => $this->otraSede()->id]);

        $this->actingAs($this->admin)->putJson("/admin/estudiantes/{$e->id}/acudiente", ['acudiente' => ['id' => $ajeno->id], 'parentesco' => 'Madre'])
            ->assertUnprocessable()->assertJsonValidationErrors(['acudiente.id']);
        $this->assertSame(0, $e->acudientes()->count());
    }

    public function test_corregir_la_fecha_no_deja_a_un_menor_sin_acudiente(): void
    {
        $e = $this->estudiante('1070000001');   // adulto sin acudiente
        $datos = [
            'nombres' => $e->nombres, 'apellidos' => $e->apellidos, 'genero' => 'femenino',
            'fecha_nacimiento' => now()->subYears(16)->toDateString(), 'departamento_nacimiento' => 'Tolima', 'lugar_nacimiento' => 'Honda',
            'tipo_documento' => 'TI', 'numero_documento' => '1070000001', 'telefono' => '3157720419', 'email' => $e->email,
            'direccion' => 'Calle 4 # 6-21', 'departamento_residencia' => 'Cundinamarca', 'ciudad_residencia' => 'Villeta',
        ];

        $this->actingAs($this->admin)->putJson("/admin/estudiantes/{$e->id}", $datos)
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha_nacimiento']);

        // Con acudiente sí se puede corregir
        $this->acudiente([$e->id => 'Madre']);
        $this->actingAs($this->admin)->putJson("/admin/estudiantes/{$e->id}", $datos)->assertOk();
    }

    /* ── Editar ── */

    public function test_edita_datos_y_estudiantes_a_cargo(): void
    {
        $adulto = $this->estudiante('1070000001');
        $otro   = $this->estudiante('1070000002');
        $acu = $this->acudiente([$adulto->id => 'Madre']);

        $this->editar($acu, ['telefono' => '3001112233', 'estudiantes' => [['id' => $otro->id, 'parentesco' => 'Abuelo(a)']]])->assertOk();

        $acu->refresh();
        $this->assertSame('3001112233', $acu->telefono);
        $this->assertSame([$otro->id], $acu->estudiantesACargo()->pluck('users.id')->all(), 'El adulto que se quitó queda sin acudiente');
        $this->assertDatabaseHas('auditorias', ['accion' => 'editar', 'entidad_id' => $acu->id]);
    }

    public function test_al_editar_no_deja_a_un_menor_sin_acudiente(): void
    {
        $menor = $this->estudiante('1070000001', 16);
        $acu = $this->acudiente([$menor->id => 'Madre']);

        $this->editar($acu, ['estudiantes' => []])->assertUnprocessable()->assertJsonValidationErrors(['estudiantes']);
        $this->assertSame(1, $acu->estudiantesACargo()->count());
    }

    public function test_no_edita_acudientes_de_otra_sede(): void
    {
        $ajeno = User::factory()->create(['role' => 'acudiente', 'sede_id' => $this->otraSede()->id]);

        $this->editar($ajeno)->assertForbidden();
    }

    /* ── Vincular / desvincular ── */

    public function test_vincula_un_estudiante_reemplazando_al_acudiente_anterior(): void
    {
        $e = $this->estudiante('1070000001', 15);
        $anterior = $this->acudiente([$e->id => 'Padre']);
        $nuevo = $this->acudiente([], '52310477');

        $this->actingAs($this->admin)->postJson("/admin/acudientes/{$nuevo->id}/estudiantes", ['estudiante_id' => $e->id, 'parentesco' => 'Madre'])->assertOk();

        $this->assertSame([$nuevo->id], $e->acudientes()->pluck('users.id')->all());
        $this->assertSame(0, $anterior->estudiantesACargo()->count());
    }

    public function test_desvincula_a_un_adulto_pero_no_a_un_menor(): void
    {
        $adulto = $this->estudiante('1070000001');
        $menor  = $this->estudiante('1070000002', 17);
        $acu = $this->acudiente([$adulto->id => 'Madre', $menor->id => 'Madre']);

        $this->actingAs($this->admin)->deleteJson("/admin/acudientes/{$acu->id}/estudiantes/{$menor->id}")->assertUnprocessable();
        $this->actingAs($this->admin)->deleteJson("/admin/acudientes/{$acu->id}/estudiantes/{$adulto->id}")->assertOk();

        $this->assertSame([$menor->id], $acu->estudiantesACargo()->pluck('users.id')->all());
    }

    /* ── Clave y cuenta ── */

    public function test_restablece_la_clave_al_documento(): void
    {
        $acu = $this->acudiente();
        $acu->update(['password' => Hash::make('otra-clave')]);

        $this->actingAs($this->admin)->patchJson("/admin/acudientes/{$acu->id}/clave")->assertOk();

        $this->assertTrue(Hash::check($acu->numero_documento, $acu->fresh()->password));
    }

    public function test_una_cuenta_desactivada_no_puede_ingresar(): void
    {
        $acu = $this->acudiente();

        $this->actingAs($this->admin)->patchJson("/admin/acudientes/{$acu->id}/estado")->assertOk();
        $this->assertFalse($acu->fresh()->activo);
        auth()->logout();

        $this->post('/login', ['email' => $acu->email, 'password' => $acu->numero_documento])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Al activarla de nuevo entra con la misma clave y queda registrado su ingreso
        $acu->update(['activo' => true]);
        $this->post('/login', ['email' => $acu->email, 'password' => $acu->numero_documento]);
        $this->assertAuthenticatedAs($acu);
        $this->assertNotNull($acu->fresh()->ultimo_acceso);
    }

    public function test_la_pantalla_carga_con_estudiantes_sin_matricula(): void
    {
        $this->acudiente([$this->estudiante('1070000001')->id => 'Madre']);

        $this->actingAs($this->admin)->get('/admin/acudientes')->assertOk()->assertSee('sin_matricula');
    }

    /* ── Auxiliares ── */

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombres'                 => 'Gloria Inés',
            'apellidos'               => 'Cárdenas Mora',
            'tipo_documento'          => 'CC',
            'numero_documento'        => '39645218',
            'profesion'               => 'Comerciante',
            'telefono'                => '3125550141',
            'email'                   => 'gloria.cardenas@example.com',
            'direccion'               => 'Calle 4 # 6-21',
            'departamento_residencia' => 'Cundinamarca',
            'ciudad_residencia'       => 'Villeta',
            'estudiantes'             => [],
        ], $cambios);
    }

    private function registrar(array $cambios = [])
    {
        return $this->actingAs($this->admin)->postJson('/admin/acudientes', $this->datos($cambios));
    }

    private function editar(User $a, array $cambios = [])
    {
        return $this->actingAs($this->admin)->putJson("/admin/acudientes/{$a->id}", $this->datos(['numero_documento' => $a->numero_documento, 'email' => $a->email] + $cambios));
    }

    private function estudiante(string $documento, int $edad = 25): User
    {
        return User::factory()->create([
            'role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento,
            'fecha_nacimiento' => now()->subYears($edad)->subDays(10)->toDateString(),
        ]);
    }

    /** Acudiente en BD con sus estudiantes ([id => parentesco]). */
    private function acudiente(array $aCargo = [], string $documento = '80765432'): User
    {
        $a = User::factory()->create(['role' => 'acudiente', 'sede_id' => $this->sede->id, 'numero_documento' => $documento, 'password' => Hash::make($documento)]);
        foreach ($aCargo as $id => $parentesco) {
            User::find($id)->acudientes()->sync([$a->id => ['parentesco' => $parentesco]]);
        }

        return $a;
    }
}
