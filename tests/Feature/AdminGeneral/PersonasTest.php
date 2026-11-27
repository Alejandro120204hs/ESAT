<?php

namespace Tests\Feature\AdminGeneral;

use App\Models\Academico\DocentePerfil;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\General\PersonasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/** Admin de ESAT › Estudiantes (con su contacto de emergencia) y Docentes: las reglas de la secretaría en la sede que se elija. */
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
            'contacto' => ['nombre' => 'Marta Díaz', 'celular' => '3109876543', 'parentesco' => 'Madre'],
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

    /** Nacido en otro país: las mismas reglas de la secretaría (27-11-2026). */
    public function test_registra_a_un_extranjero_con_su_pais_de_nacimiento(): void
    {
        $fuera = ['tipo_documento' => 'CE', 'pais_nacimiento' => 'Ecuador', 'departamento_nacimiento' => '', 'lugar_nacimiento' => 'Quito'];
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante(array_merge($fuera, ['tipo_documento' => 'CC'])))
            ->assertJsonValidationErrors('pais_nacimiento');
        $this->postJson('/general/estudiantes', $this->estudiante($fuera))->assertCreated();

        $e = User::where('numero_documento', $this->estudiante()['numero_documento'])->firstOrFail();
        $this->assertSame(['Ecuador', 'Quito', null], [$e->pais_nacimiento, $e->lugar_nacimiento, $e->departamento_nacimiento]);
    }

    public function test_registra_y_cambia_el_contacto_de_emergencia(): void
    {
        $sin = ['contacto' => ['nombre' => '', 'celular' => '', 'parentesco' => '']];
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante($sin))
            ->assertJsonValidationErrors(['contacto.nombre', 'contacto.celular', 'contacto.parentesco']);

        $this->postJson('/general/estudiantes', $this->estudiante())->assertCreated();
        $e = User::where('email', 'laura@correo.com')->firstOrFail();
        $this->assertSame('Marta Díaz', $e->contactoEmergencia->nombre);

        $this->putJson("/general/estudiantes/{$e->id}", $this->estudiante(['contacto' => ['nombre' => 'Pedro Gómez', 'celular' => '3001239876', 'parentesco' => 'Padre']]))->assertOk();
        $this->assertSame(['Pedro Gómez', 'Padre'], [$e->fresh()->contactoEmergencia->nombre, $e->fresh()->contactoEmergencia->parentesco]);
        $this->assertDatabaseCount('contactos_emergencia', 1);
    }

    public function test_con_matricula_vigente_no_cambia_de_sede(): void
    {
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante())->assertCreated();
        $e = User::where('email', 'laura@correo.com')->firstOrFail();
        Matricula::create(['codigo' => 'MAT-1', 'estudiante_id' => $e->id, 'programa_id' => $this->programa()->id, 'sede_id' => $this->sede->id, 'estado' => 'activo', 'fecha_inicio' => now()]);
        $otra = $this->otraSede();
        $this->putJson("/general/estudiantes/{$e->id}", $this->estudiante(['sede_id' => $otra->id]))->assertJsonValidationErrors('sede_id');
    }

    public function test_con_deudas_en_su_sede_no_cambia_de_sede(): void
    {
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante())->assertCreated();
        $e = User::where('email', 'laura@correo.com')->firstOrFail();
        // Se retiró debiendo una cuota vencida: la sede nueva no la vería
        $m = Matricula::create(['codigo' => 'MAT-1', 'estudiante_id' => $e->id, 'programa_id' => $this->programa()->id, 'sede_id' => $this->sede->id, 'estado' => 'retirado', 'fecha_inicio' => now()->subMonths(3)]);
        $cuota = $m->pagos()->create(['tipo' => 'mensual', 'numero_cuota' => 1, 'fecha_vencimiento' => now()->subMonth()->toDateString(),
            'monto_programa' => 150000, 'monto_sistema' => 0, 'monto_total' => 150000, 'estado' => 'vencido']);
        $otra = $this->otraSede();

        $this->putJson("/general/estudiantes/{$e->id}", $this->estudiante(['sede_id' => $otra->id]))
            ->assertJsonValidationErrors(['sede_id' => 'Todavía debe $ 150.000']);

        $cuota->update(['estado' => 'pagado', 'abonado' => 150000]);
        $this->putJson("/general/estudiantes/{$e->id}", $this->estudiante(['sede_id' => $otra->id]))->assertOk();
        $this->assertSame($otra->id, $e->fresh()->sede_id);
    }

    /**
     * La cartera del Admin es la de la persona, como en Pagos de la secretaría y en el Inicio: cuenta lo vencido de un aplazado
     * y lo que quedó debiendo en una matrícula anterior aunque la de hoy esté al día (auditoría 26-11-2026).
     */
    public function test_la_cartera_cuenta_lo_vencido_de_todas_sus_matriculas(): void
    {
        $this->actingAs($this->general)->postJson('/general/estudiantes', $this->estudiante())->assertCreated();
        $e = User::where('email', 'laura@correo.com')->firstOrFail();
        $vieja = Matricula::create(['codigo' => 'MAT-1', 'estudiante_id' => $e->id, 'programa_id' => $this->programa()->id, 'sede_id' => $this->sede->id, 'estado' => 'retirado', 'fecha_inicio' => now()->subMonths(8)]);
        $vieja->pagos()->create(['tipo' => 'mensual', 'numero_cuota' => 1, 'fecha_vencimiento' => now()->subMonths(6)->toDateString(),
            'monto_programa' => 150000, 'monto_sistema' => 0, 'monto_total' => 150000, 'estado' => 'pendiente']);
        Matricula::create(['codigo' => 'MAT-2', 'estudiante_id' => $e->id, 'programa_id' => $this->programa()->id, 'sede_id' => $this->sede->id, 'estado' => 'activo', 'fecha_inicio' => now()->subMonth(), 'plan_pago' => 'mensual']);

        $this->postJson('/general/estudiantes', $this->estudiante(['numero_documento' => '1070333444', 'email' => 'aplazada@correo.com', 'telefono' => '3111234567']))->assertCreated();
        $aplazada = User::where('email', 'aplazada@correo.com')->firstOrFail();
        $m = Matricula::create(['codigo' => 'MAT-3', 'estudiante_id' => $aplazada->id, 'programa_id' => $this->programa()->id, 'sede_id' => $this->sede->id, 'estado' => 'aplazado', 'fecha_inicio' => now()->subMonths(3)]);
        $m->pagos()->create(['tipo' => 'mensual', 'numero_cuota' => 1, 'fecha_vencimiento' => now()->subMonth()->toDateString(),
            'monto_programa' => 90000, 'monto_sistema' => 0, 'monto_total' => 90000, 'estado' => 'pendiente', 'abonado' => 20000]);

        $lista = collect(app(PersonasService::class)->estudiantes()[0])->keyBy('id');
        $this->assertSame(['vencido', 150000, 1], [$lista[$e->id]['pago'], $lista[$e->id]['saldo_vencido'], count($lista[$e->id]['cuotas_vencidas'])]);
        $this->assertSame(['aplazado', 'vencido', 70000], [$lista[$aplazada->id]['estado'], $lista[$aplazada->id]['pago'], $lista[$aplazada->id]['saldo_vencido']]);
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

    /** El formulario ofrece el permiso por protección temporal: el servidor lo acepta, como en Estudiantes (auditoría 26-11-2026). */
    public function test_registra_un_docente_con_permiso_por_proteccion_temporal(): void
    {
        $this->actingAs($this->general)->postJson('/general/docentes', $this->docenteDatos(['tipo_documento' => 'PPT', 'numero_documento' => '5123456']))->assertCreated();
        $this->assertSame('PPT', User::where('email', 'hernan@esat.edu.co')->value('tipo_documento'));
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
        foreach (['estudiantes', 'docentes', 'academico', 'finanzas', 'reportes', 'auditoria', ''] as $p) {
            $this->actingAs($this->general)->get('/general/'.$p)->assertOk()->assertSee('window.GEN_DATOS', false);
        }
    }
}
