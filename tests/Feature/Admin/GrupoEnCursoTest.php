<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Academico\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Nadie empieza una carrera en un grupo que ya empezó; quien vuelve de un aplazamiento o se cambia de grupo solo entra a
 * uno que no le haga perder módulos (decidido con el usuario el 26-11-2026, MatriculaService::noAlcanza).
 * Carrera de 6 meses con 3 módulos iguales, lunes y miércoles. Hoy es el 21 de octubre de 2026:
 * - Grupo de agosto (empezó el 3-ago): va en el módulo 2, que empezó a finales de septiembre.
 * - Grupo de octubre (empezó el 5-oct): va en el módulo 1; su módulo 2 empieza en diciembre.
 * - Grupo de noviembre: todavía no empieza.
 */
class GrupoEnCursoTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private Curso $agosto;
    private Curso $octubre;
    private Curso $noviembre;
    private MatriculaService $matriculas;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 10:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana'], 300, 6);
        $this->programa->modulos()->createMany([
            ['nombre' => 'Fundamentos', 'horas' => 100, 'orden' => 1],
            ['nombre' => 'Normativa', 'horas' => 100, 'orden' => 2],
            ['nombre' => 'Práctica', 'horas' => 100, 'orden' => 3],
        ]);
        $horario = [$this->sesion('Lunes', '07:00', '11:00'), $this->sesion('Miércoles', '07:00', '11:00')];
        $this->agosto = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-08-03', $horario);
        $this->octubre = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-10-05', $horario);
        $this->noviembre = $this->grupo($this->programa, 'Mañana', null, 'planificacion', '2026-11-02', $horario);
        $this->matriculas = app(MatriculaService::class);
    }

    private function estudiante(): User
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'fecha_nacimiento' => '1998-05-10']);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));

        return $e;
    }

    /** Matriculada en el grupo de agosto desde que empezó, con el módulo 1 aprobado, y aplazada el 1 de octubre. */
    private function aplazadaConModulo1(): Matricula
    {
        $m = $this->alIniciar($this->agosto, fn () => $this->matriculas->matricular($this->estudiante(), $this->agosto, null));
        $modulo1 = $this->programa->modulos()->where('orden', 1)->first();
        Calificacion::create(['matricula_id' => $m->id, 'programa_modulo_id' => $modulo1->id, 'curso_id' => $this->agosto->id, 'nota' => 4.0, 'fecha_nota' => '2026-09-28']);
        $this->matriculas->cambiarEstado($m->fresh(), 'aplazado', Carbon::parse('2026-10-01'), 'Viaje');

        return $m->fresh();
    }

    public function test_nadie_se_matricula_en_un_grupo_que_ya_empezo(): void
    {
        $e = $this->estudiante();
        $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $this->octubre->id])
            ->assertJsonValidationErrors(['curso_id' => 'ya empezó clases']);
        $this->assertSame(0, Matricula::count());

        $this->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $this->noviembre->id])->assertOk();
        $this->assertSame($this->noviembre->id, Matricula::first()->curso_id);
    }

    public function test_el_dia_en_que_empieza_el_grupo_todavia_se_matricula(): void
    {
        Carbon::setTestNow('2026-11-02 08:00');
        $m = $this->matriculas->matricular($this->estudiante(), $this->noviembre, null);
        $this->assertSame($this->noviembre->id, $m->curso_id);
    }

    public function test_una_carrera_nueva_tampoco_se_empieza_en_un_grupo_en_curso(): void
    {
        $m = $this->alIniciar($this->agosto, fn () => $this->matriculas->matricular($this->estudiante(), $this->agosto, null));
        $otra = $this->programa(['Mañana'], 300, 6);
        $enCurso = $this->grupo($otra, 'Mañana', null, 'activo', '2026-10-05');

        $this->expectException(ValidationException::class);
        $this->matriculas->cambiarPrograma($m->fresh(), $enCurso, 'Prefiere la otra carrera');
    }

    public function test_el_reintegro_no_va_a_un_grupo_que_ya_empezo_el_modulo_que_le_falta(): void
    {
        $m = $this->aplazadaConModulo1();

        // Su grupo siguió sin ella: ya va en el módulo 2, que ella no vio
        try {
            $this->matriculas->cambiarEstado($m, 'activo', today(), 'Vuelve', $this->agosto);
            $this->fail('No debía volver a un grupo que ya empezó el módulo 2');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Normativa', $e->getMessage());
        }

        // El de octubre va en el módulo 1 (que ella ya aprobó): su módulo 2 empieza después, así que no pierde nada
        $this->matriculas->cambiarEstado($m->fresh(), 'activo', today(), 'Vuelve', $this->octubre);
        $this->assertSame(['activo', $this->octubre->id], [$m->fresh()->estado, $m->fresh()->curso_id]);
    }

    public function test_el_cambio_de_grupo_solo_va_a_uno_que_no_vaya_mas_adelantado(): void
    {
        $m = $this->alIniciar($this->octubre, fn () => $this->matriculas->matricular($this->estudiante(), $this->octubre, null));

        // El de agosto ya dio el módulo 1, que ella está viendo: perdería clases
        try {
            $this->matriculas->cambiarGrupo($m->fresh(), $this->agosto);
            $this->fail('No debía pasarse a un grupo más adelantado');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Fundamentos', $e->getMessage());
        }

        // Otro grupo que empezó el mismo día y va al mismo ritmo sí sirve
        $paralelo = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-10-05', [$this->sesion('Lunes', '07:00', '11:00'), $this->sesion('Miércoles', '07:00', '11:00')]);
        $this->matriculas->cambiarGrupo($m->fresh(), $paralelo);
        $this->assertSame($paralelo->id, $m->fresh()->curso_id);
    }

    public function test_matriculas_dice_que_grupos_no_le_sirven_y_por_que(): void
    {
        $m = $this->aplazadaConModulo1();

        $this->actingAs($this->admin)->get('/admin/matriculas')->assertOk()
            ->assertViewHas('matriculas', function ($ms) use ($m) {
                $bloqueados = (array) $ms->firstWhere('id', $m->id)['no_alcanza'];

                return array_keys($bloqueados) === [$this->agosto->id] && str_contains($bloqueados[$this->agosto->id], 'Normativa');
            });
    }
}
