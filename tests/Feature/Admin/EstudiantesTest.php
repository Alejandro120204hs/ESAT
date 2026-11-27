<?php

namespace Tests\Feature\Admin;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Panel Admin › Estudiantes: registrar y editar a la persona (sin matricularla),
 * su contacto de emergencia (obligatorio), su clave y su lista de documentos. Más la regla de cupos que
 * comparten Grupos y Matrículas (solo las matrículas activas ocupan cupo).
 */
class EstudiantesTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();
    }

    /* ── Registrar ── */

    public function test_registra_al_estudiante_sin_matricularlo(): void
    {
        $this->registrar()->assertOk()->assertJsonFragment(['ok' => true]);

        $e = User::where('numero_documento', '1070123456')->first();
        $this->assertSame(RolUsuario::Estudiante, $e->role);
        $this->assertSame($this->sede->id, $e->sede_id);
        $this->assertTrue(Hash::check('1070123456', $e->password), 'La clave inicial es el documento');
        $this->assertSame(0, $e->matriculas()->count(), 'Matricular es de Matrículas');
        $this->assertSame(0, $e->cursosComoEstudiante()->count());
        $this->assertSame(EstudianteDocumento::REQUERIDOS, $e->documentos->pluck('documento')->all());
        $this->assertDatabaseHas('auditorias', ['accion' => 'crear', 'entidad_tipo' => 'User', 'entidad_id' => $e->id]);
    }

    public function test_el_contacto_de_emergencia_es_obligatorio_para_todos(): void
    {
        $this->registrar(['contacto' => null])->assertUnprocessable()->assertJsonValidationErrors(['contacto.nombre', 'contacto.celular', 'contacto.parentesco']);
        $this->registrar($this->menor() + ['contacto' => ['nombre' => '', 'celular' => '', 'parentesco' => '']])
            ->assertUnprocessable()->assertJsonValidationErrors(['contacto.nombre', 'contacto.celular', 'contacto.parentesco']);

        $this->assertDatabaseCount('contactos_emergencia', 0);
        $this->assertDatabaseMissing('users', ['numero_documento' => '1072000111']);
    }

    public function test_registra_al_estudiante_con_su_contacto_de_emergencia(): void
    {
        $this->registrar($this->menor() + ['contacto' => ['nombre' => '  Patricia   Ortiz Rubio ', 'celular' => '310 445 2211', 'parentesco' => 'Madre']])->assertOk();

        $c = User::where('numero_documento', '1072000111')->first()->contactoEmergencia;
        $this->assertSame(['Patricia Ortiz Rubio', '3104452211', 'Madre'], [$c->nombre, $c->celular, $c->parentesco]);
        $this->assertSame(1, User::where('role', 'estudiante')->count(), 'El contacto no tiene cuenta propia');
    }

    public function test_el_contacto_no_puede_tener_el_celular_del_estudiante_ni_un_parentesco_inventado(): void
    {
        $this->registrar(['contacto' => ['nombre' => 'Rosa Patiño', 'celular' => '3157720419', 'parentesco' => 'Madre']])
            ->assertUnprocessable()->assertJsonValidationErrors(['contacto.celular' => 'distinto']);
        $this->registrar(['contacto' => ['nombre' => 'Rosa Patiño', 'celular' => '2104445566', 'parentesco' => 'Vecina']])
            ->assertUnprocessable()->assertJsonValidationErrors(['contacto.celular', 'contacto.parentesco']);
    }

    public function test_el_documento_debe_corresponder_a_la_edad(): void
    {
        $this->registrar(['tipo_documento' => 'TI'])
            ->assertUnprocessable()->assertJsonValidationErrors(['tipo_documento' => 'cédula de ciudadanía']);
    }

    public function test_el_correo_es_obligatorio_y_unico(): void
    {
        $this->registrar(['email' => ''])->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->registrar(['email' => $this->admin->email])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'ya está registrado']);
    }

    public function test_no_se_registra_dos_veces_el_mismo_documento(): void
    {
        $this->registrar()->assertOk();

        $this->registrar(['email' => 'otro@example.com'])->assertUnprocessable()->assertJsonValidationErrors(['numero_documento']);
    }

    /** El servidor revisa el listado de Colombia, como el panel general (antes solo lo revisaba el combobox; 27-11-2026). */
    public function test_departamento_y_ciudad_salen_del_listado_de_colombia(): void
    {
        $this->registrar(['departamento_nacimiento' => 'Narnia', 'ciudad_residencia' => 'Medellín'])
            ->assertUnprocessable()->assertJsonValidationErrors(['departamento_nacimiento', 'ciudad_residencia']);
        $this->registrar(['lugar_nacimiento' => 'Villeta'])->assertJsonValidationErrors(['lugar_nacimiento' => 'departamento elegido']);
        $this->assertSame(0, User::where('role', 'estudiante')->count());
    }

    /**
     * Nacido en otro país (decidido con el usuario el 27-11-2026): solo con cédula de extranjería o PPT; país de la lista y
     * ciudad escrita, sin departamento. Volver a Colombia quita el país.
     */
    public function test_un_extranjero_registra_su_pais_y_ciudad_de_nacimiento(): void
    {
        $fuera = ['tipo_documento' => 'PPT', 'pais_nacimiento' => 'Venezuela', 'departamento_nacimiento' => '', 'lugar_nacimiento' => 'Maracaibo'];

        $this->registrar(array_merge($fuera, ['tipo_documento' => 'CC']))->assertJsonValidationErrors(['pais_nacimiento' => 'cédula de extranjería o PPT']);
        $this->registrar(array_merge($fuera, ['pais_nacimiento' => 'Narnia']))->assertJsonValidationErrors('pais_nacimiento');
        $this->registrar(array_merge($fuera, ['pais_nacimiento' => 'Colombia']))->assertJsonValidationErrors('pais_nacimiento');
        $this->registrar(array_merge($fuera, ['lugar_nacimiento' => '']))->assertJsonValidationErrors('lugar_nacimiento');

        $this->registrar(array_merge($fuera, ['departamento_nacimiento' => 'Tolima']))->assertOk();
        $e = User::where('role', 'estudiante')->firstOrFail();
        $this->assertSame(['Venezuela', 'Maracaibo', null], [$e->pais_nacimiento, $e->lugar_nacimiento, $e->departamento_nacimiento]);
        $this->get('/admin/estudiantes')->assertOk()->assertViewHas('estudiantes', fn ($l) => $l->firstWhere('id', $e->id)['pais_nac'] === 'Venezuela');

        $this->editar($e, ['tipo_documento' => 'PPT'])->assertOk();
        $this->assertSame([null, 'Tolima', 'Honda'], [$e->fresh()->pais_nacimiento, $e->fresh()->departamento_nacimiento, $e->fresh()->lugar_nacimiento]);
    }

    public function test_el_lugar_de_nacimiento_es_obligatorio_y_se_guarda_aparte_de_la_residencia(): void
    {
        $this->registrar(['departamento_nacimiento' => '', 'lugar_nacimiento' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['departamento_nacimiento', 'lugar_nacimiento']);

        $this->registrar()->assertOk();
        $e = User::where('numero_documento', '1070123456')->first();
        $this->assertSame('Tolima', $e->departamento_nacimiento);
        $this->assertSame('Honda', $e->lugar_nacimiento);
        $this->assertSame('Villeta', $e->ciudad_residencia);
    }

    /* ── Editar ── */

    public function test_edita_los_datos_del_estudiante(): void
    {
        $this->registrar()->assertOk();
        $e = User::where('numero_documento', '1070123456')->first();

        $this->editar($e, ['apellidos' => 'Gómez Prieto', 'telefono' => '3001112233', 'ciudad_residencia' => 'Sasaima'])->assertOk();

        $e->refresh();
        $this->assertSame('Gómez Prieto', $e->apellidos);
        $this->assertSame('3001112233', $e->telefono);
        $this->assertSame('Sasaima', $e->ciudad_residencia);
        $this->assertTrue(Hash::check('1070123456', $e->password), 'Editar no cambia la contraseña');
        $this->assertDatabaseHas('auditorias', ['accion' => 'editar', 'entidad_tipo' => 'User', 'entidad_id' => $e->id]);
    }

    public function test_al_editar_puede_conservar_su_documento_y_correo_pero_no_tomar_los_de_otro(): void
    {
        $this->registrar()->assertOk();
        $this->registrar(['numero_documento' => '1070999888', 'email' => 'otra@example.com'])->assertOk();
        $e = User::where('numero_documento', '1070123456')->first();

        $this->editar($e)->assertOk();   // los suyos no chocan consigo mismo
        $this->editar($e, ['numero_documento' => '1070999888'])->assertUnprocessable()->assertJsonValidationErrors(['numero_documento']);
        $this->editar($e, ['email' => 'otra@example.com'])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_al_editar_se_valida_el_documento_contra_la_edad(): void
    {
        $this->registrar()->assertOk();
        $e = User::where('numero_documento', '1070123456')->first();

        $this->editar($e, ['tipo_documento' => 'TI'])->assertUnprocessable()->assertJsonValidationErrors(['tipo_documento']);
    }

    public function test_no_se_editan_estudiantes_de_otra_sede_ni_otros_usuarios(): void
    {
        $ajeno = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->otraSede()->id]);

        $this->editar($ajeno)->assertForbidden();
        $this->editar($this->docente())->assertForbidden();
    }

    /* ── Contacto de emergencia, clave y documentos ── */

    public function test_al_editar_cambia_su_contacto_de_emergencia(): void
    {
        $this->registrar()->assertOk();
        $e = User::where('numero_documento', '1070123456')->first();

        $this->editar($e, ['contacto' => ['nombre' => 'Jorge Gómez', 'celular' => '3201112233', 'parentesco' => 'Padre']])->assertOk();

        $this->assertSame(['Jorge Gómez', 'Padre'], [$e->fresh()->contactoEmergencia->nombre, $e->fresh()->contactoEmergencia->parentesco]);
        $this->assertDatabaseCount('contactos_emergencia', 1);
        $this->editar($e, ['contacto' => null])->assertUnprocessable()->assertJsonValidationErrors(['contacto.nombre']);
    }

    public function test_restablece_la_clave_al_numero_de_documento(): void
    {
        $e = $this->estudiante();
        $e->update(['password' => Hash::make('otra-clave')]);

        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e->id}/clave")->assertOk();

        $this->assertTrue(Hash::check($e->numero_documento, $e->fresh()->password));
    }

    public function test_marca_y_desmarca_un_documento_entregado(): void
    {
        $e = $this->estudiante();
        $doc = $e->documentos()->first();

        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e->id}/documentos/{$doc->id}", ['entregado' => true])->assertOk()->assertJson(['entregado' => true]);
        $this->assertNotNull($doc->fresh()->entregado_en);

        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e->id}/documentos/{$doc->id}", ['entregado' => false])->assertOk()->assertJson(['entregado' => false]);
        $this->assertNull($doc->fresh()->entregado_en);
    }

    public function test_no_toca_documentos_de_otro_estudiante(): void
    {
        $e1 = $this->estudiante();
        $e2 = $this->estudiante('1070999003');

        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$e1->id}/documentos/{$e2->documentos()->first()->id}")->assertNotFound();
    }

    public function test_no_se_gestionan_estudiantes_de_otra_sede(): void
    {
        $ajeno = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->otraSede()->id]);

        $this->actingAs($this->admin)->patchJson("/admin/estudiantes/{$ajeno->id}/clave")->assertNotFound();
    }

    /* ── Pantalla ── */

    public function test_la_pantalla_carga_con_estudiantes_con_y_sin_matricula(): void
    {
        $this->estudiante();
        $this->matricular($this->grupo($this->programa(), 'Tarde', sesiones: [$this->sesion('Lunes', '14:00', '18:00')]), '1070999004');

        $this->actingAs($this->admin)->get('/admin/estudiantes')
            ->assertOk()
            ->assertSee('sin_matricula', false)
            ->assertSee('EST-'.now()->year.'-0001', false);
    }

    /* ── Cupos: solo las matrículas activas los ocupan (Grupos, Programas, Matrículas) ── */

    public function test_un_aplazado_o_retirado_libera_el_cupo(): void
    {
        $grupo = $this->grupo($this->programa(cupo: 2), 'Tarde');
        $m1 = $this->matricular($grupo, '1070999005');
        $this->matricular($grupo, '1070999006');
        $this->assertSame(0, $grupo->cuposLibres());

        $m1->update(['estado' => 'aplazado']);
        $this->assertSame(1, $grupo->fresh()->cuposLibres());
    }

    public function test_grupos_no_deja_cambiar_de_programa_un_grupo_con_estudiantes(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($this->programa(), 'Tarde', docente: $docente);
        $this->matricular($grupo, '1070999007');

        $this->actingAs($this->admin)->putJson("/admin/cursos/{$grupo->id}", [
            'programa_id' => $this->programa()->id, 'docente_id' => $docente->id, 'jornada' => 'Tarde',
            'fecha_inicio' => $grupo->fecha_inicio->toDateString(), 'estado' => 'activo',
        ])->assertUnprocessable()->assertJsonValidationErrors(['programa_id' => 'estudiantes inscritos']);
    }

    /* ── Ayudantes ── */

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombres'                 => 'Laura Daniela',
            'apellidos'               => 'Gómez Patiño',
            'fecha_nacimiento'        => '2002-11-05',
            'departamento_nacimiento' => 'Tolima',
            'lugar_nacimiento'        => 'Honda',
            'genero'                  => 'femenino',
            'tipo_documento'          => 'CC',
            'numero_documento'        => '1070123456',
            'telefono'                => '3157720419',
            'email'                   => 'laura.gomez@example.com',
            'direccion'               => 'Calle 4 # 6-21',
            'departamento_residencia' => 'Cundinamarca',
            'ciudad_residencia'       => 'Villeta',
            'contacto'                => ['nombre' => 'Rosa Patiño', 'celular' => '3104445566', 'parentesco' => 'Madre'],
        ], $cambios);
    }

    private function registrar(array $cambios = [])
    {
        return $this->actingAs($this->admin)->postJson('/admin/estudiantes', $this->datos($cambios));
    }

    private function editar(User $e, array $cambios = [])
    {
        return $this->actingAs($this->admin)->putJson("/admin/estudiantes/{$e->id}", $this->datos($cambios));
    }

    private function menor(): array
    {
        return [
            'fecha_nacimiento' => now()->subYears(16)->toDateString(), 'tipo_documento' => 'TI',
            'numero_documento' => '1072000111', 'email' => 'menor@example.com',
        ];
    }

    /** Estudiante registrado directo en BD, con su lista de documentos. */
    private function estudiante(string $documento = '1070999000'): User
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento]);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d], EstudianteDocumento::REQUERIDOS));

        return $e;
    }

    /** Estudiante con matrícula activa en $grupo (como la dejará Matrículas). */
    private function matricular(Curso $grupo, string $documento): Matricula
    {
        $e = $this->estudiante($documento);
        $m = Matricula::create([
            'codigo' => Matricula::siguienteCodigo(), 'estudiante_id' => $e->id, 'programa_id' => $grupo->programa_id,
            'curso_id' => $grupo->id, 'sede_id' => $this->sede->id, 'estado' => 'activo', 'fecha_inicio' => now()->toDateString(),
        ]);
        $e->cursosComoEstudiante()->attach($grupo->id);

        return $m;
    }
}
