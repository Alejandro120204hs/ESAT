<?php

namespace Tests\Feature\AdminGeneral;

use App\Models\Academico\DocentePerfil;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/** Admin de ESAT › Estudiantes, Acudientes y Docentes: las reglas de la secretaría en la sede que se elija. */
class PersonasTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private User $general;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();
        $this->general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
    }

    private function estudiante(array $cambios = []): array
    {
        return array_merge([
            'sede_id' => $this->sede->id, 'nombres' => 'Laura', 'apellidos' => 'Gómez Díaz', 'tipo_documento' => 'CC', 'numero_documento' => '1070111222',
            'fecha_nacimiento' => '2000-03-04', 'genero' => 'femenino', 'departamento_nacimiento' => 'Cundinamarca', 'lugar_nacimiento' => 'Villeta',
            'telefono' => '3101234567', 'email' => 'laura@correo.com', 'direccion' => 'Calle 3 # 2-10', 'departamento_residencia' => 'Cundinamarca',
            'ciudad_residencia' => 'Sasaima',
        ], $cambios);
    }

    /* ── Estudiantes ── */

    public function test_registra_un_estudiante_en_la_sede_elegida_sin_matricularlo(): void
    {
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante())->assertCreated();
        $e = User::where('email', 'laura@correo.com')->firstOrFail();
        $this->assertSame($this->sede->id, $e->sede_id);
        $this->assertSame(0, Matricula::where('estudiante_id', $e->id)->count());
        $this->assertGreaterThan(0, $e->documentos()->count());
    }

    public function test_un_menor_necesita_acudiente_y_se_puede_registrar_uno_nuevo_con_el(): void
    {
        $menor = ['tipo_documento' => 'TI', 'fecha_nacimiento' => now()->subYears(15)->toDateString()];
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante($menor))->assertJsonValidationErrors('acudiente');

        $this->postJson('/general/estudiantes', $this->estudiante($menor + [
            'con_acudiente' => true, 'parentesco' => 'Madre',
            'acudiente' => ['documento' => '52111222', 'nombres' => 'Marta', 'apellidos' => 'Díaz', 'telefono' => '3109876543', 'email' => 'marta@correo.com'],
        ]))->assertCreated();
        $e = User::where('email', 'laura@correo.com')->firstOrFail();
        $this->assertSame('marta@correo.com', $e->acudientes()->first()->email);
        $this->assertSame($this->sede->id, $e->acudientes()->first()->sede_id);
    }

    public function test_el_acudiente_debe_ser_de_la_sede_elegida(): void
    {
        $otra = $this->otraSede();
        $acudiente = User::factory()->create(['role' => 'acudiente', 'sede_id' => $otra->id]);
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante([
            'tipo_documento' => 'TI', 'fecha_nacimiento' => now()->subYears(15)->toDateString(), 'acudiente' => ['id' => $acudiente->id], 'parentesco' => 'Padre',
        ]))->assertJsonValidationErrors('acudiente.id');
    }

    public function test_con_matricula_vigente_no_cambia_de_sede(): void
    {
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante())->assertCreated();
        $e = User::where('email', 'laura@correo.com')->firstOrFail();
        Matricula::create(['codigo' => 'MAT-1', 'estudiante_id' => $e->id, 'programa_id' => $this->programa()->id, 'sede_id' => $this->sede->id, 'estado' => 'activo', 'fecha_inicio' => now()]);
        $otra = $this->otraSede();
        $this->putJson("/general/estudiantes/{$e->id}", $this->estudiante(['sede_id' => $otra->id]))->assertJsonValidationErrors('sede_id');
    }

    /* ── Acudientes ── */

    private function acudiente(array $cambios = []): array
    {
        return array_merge([
            'sede_id' => $this->sede->id, 'nombres' => 'Rosa', 'apellidos' => 'Peña', 'tipo_documento' => 'CC', 'numero_documento' => '52333444',
            'telefono' => '3201112233', 'email' => 'rosa@correo.com', 'direccion' => 'Carrera 2 # 5-10',
            'departamento_residencia' => 'Cundinamarca', 'ciudad_residencia' => 'Sasaima', 'estudiantes' => [],
        ], $cambios);
    }

    public function test_el_acudiente_se_registra_con_al_menos_un_estudiante_de_su_sede(): void
    {
        $this->actingAs($this->general)->postJson('/general/acudientes', $this->acudiente())->assertJsonValidationErrors('estudiantes');

        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        $this->postJson('/general/acudientes', $this->acudiente(['estudiantes' => [['id' => $e->id, 'parentesco' => 'Madre']]]))->assertCreated();
        $this->assertSame('rosa@correo.com', $e->acudientes()->first()->email);
    }

    public function test_no_desvincula_a_un_menor(): void
    {
        $a = User::factory()->create(['role' => 'acudiente', 'sede_id' => $this->sede->id]);
        $menor = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'fecha_nacimiento' => now()->subYears(14)]);
        $menor->acudientes()->attach($a->id, ['parentesco' => 'Madre']);
        $this->actingAs($this->general)->deleteJson("/general/acudientes/{$a->id}/estudiantes/{$menor->id}")->assertStatus(422);
        $this->assertTrue($menor->acudientes()->exists());
    }

    /* ── Docentes ── */

    private function docenteDatos(array $cambios = []): array
    {
        return array_merge([
            'sede_id' => $this->sede->id, 'nombres' => 'Hernán', 'apellidos' => 'Murillo', 'tipo_documento' => 'CC', 'numero_documento' => '79111222',
            'fecha_nacimiento' => '1980-01-01', 'genero' => 'masculino', 'telefono' => '3151234567', 'email' => 'hernan@esat.edu.co',
            'direccion' => 'Calle 8 # 6-33', 'departamento_residencia' => 'Cundinamarca', 'ciudad_residencia' => 'Sasaima',
            'escuela_id' => $this->escuela->id, 'profesion' => 'Contador público', 'titulo_academico' => 'Docencia contable',
            'vinculacion' => 'hora_catedra', 'estado' => 'activo',
        ], $cambios);
    }

    public function test_registra_un_docente_en_la_sede_elegida(): void
    {
        $this->actingAs($this->general)->postJson('/general/docentes', $this->docenteDatos())->assertCreated();
        $d = User::where('email', 'hernan@esat.edu.co')->firstOrFail();
        $this->assertSame($this->sede->id, $d->sede_id);
        $this->assertSame('hora_catedra', DocentePerfil::where('user_id', $d->id)->value('vinculacion'));
    }

    public function test_no_desactiva_ni_cambia_de_sede_a_un_docente_con_grupos_vigentes(): void
    {
        $d = $this->docente();
        $this->grupo($this->programa(), 'Mañana', $d);
        $this->actingAs($this->general)->patchJson("/general/docentes/{$d->id}/estado")->assertStatus(422);
        $this->putJson("/general/docentes/{$d->id}", $this->docenteDatos(['sede_id' => $this->otraSede()->id, 'numero_documento' => $d->numero_documento, 'email' => $d->email]))
            ->assertJsonValidationErrors('sede_id');
    }

    /* ── Pantallas con datos ── */

    public function test_las_pantallas_de_personas_cargan_con_datos(): void
    {
        $this->grupo($this->programa(), 'Mañana', $this->docente());
        foreach (['estudiantes', 'acudientes', 'docentes', 'academico', 'finanzas', 'reportes', 'auditoria', ''] as $p) {
            $this->actingAs($this->general)->get('/general/'.$p)->assertOk()->assertSee('window.GEN_DATOS', false);
        }
    }
}
