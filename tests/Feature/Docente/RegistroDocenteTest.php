<?php

namespace Tests\Feature\Docente;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\AsistenciaService;
use App\Services\Academico\CalificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Panel Docente con datos reales: ve solo sus grupos, toma y corrige la asistencia y pone las notas con las
 * reglas de AsistenciaService y CalificacionService, y lo que guarda es lo mismo que consulta el Admin.
 */
class RegistroDocenteTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupo;
    private Matricula $laura;
    private Matricula $juan;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles 21 de octubre, 9:00 a. m.
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        // 3 meses, 3 módulos de 100 / 100 / 200 horas: el 1 termina el 19 ago, el 2 el 14 sep, el 3 va en curso
        $this->programa = $this->programa(['Mañana'], 400, 3);
        $this->programa->modulos()->createMany([
            ['nombre' => 'Fundamentos', 'horas' => 100, 'orden' => 1],
            ['nombre' => 'Normativa', 'horas' => 100, 'orden' => 2],
            ['nombre' => 'Práctica', 'horas' => 200, 'orden' => 3],
        ]);
        $this->docente = $this->docente();
        // Lunes y miércoles de 7 a 11 desde el lunes 3 de agosto
        $this->grupo = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', [
            $this->sesion('Lunes', '07:00', '11:00'),
            $this->sesion('Miércoles', '07:00', '11:00'),
        ]);
        $this->laura = $this->matricula('Laura', 'Ruiz', '2026-08-01');
        $this->juan  = $this->matricula('Juan', 'Gómez', '2026-08-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ───────────── Lo que ve ───────────── */

    public function test_ve_solo_sus_grupos_con_sus_estudiantes(): void
    {
        $ajeno = $this->grupo($this->programa, 'Mañana', $this->docente('activo'), 'activo', '2026-08-03', [$this->sesion('Martes', '07:00', '11:00')]);

        $this->actingAs($this->docente)->get('/docente/asistencia')
            ->assertOk()
            ->assertViewHas('datos', function (array $d) use ($ajeno) {
                $ids = collect($d['grupos'])->pluck('id')->all();
                $grupo = $d['grupos'][0];

                return $ids === [$this->grupo->id]
                    && ! in_array($ajeno->id, $ids, true)
                    && $d['hoy'] === '2026-10-21'
                    && collect($grupo['estudiantes'])->pluck('nombres')->sort()->values()->all() === ['Juan', 'Laura']
                    && collect($grupo['modulos'])->pluck('estado')->all() === ['terminado', 'terminado', 'en_curso'];
            });
    }

    /* ───────────── Asistencia ───────────── */

    public function test_toma_la_asistencia_de_una_clase_y_la_corrige(): void
    {
        $this->asistencia('2026-10-19', ['presente', 'tarde'])
            ->assertOk()->assertJson(['ok' => true, 'mensaje' => 'Guardaste la asistencia del lun 19 oct.']);

        $this->assertSame(2, Asistencia::where('curso_id', $this->grupo->id)->whereDate('fecha', '2026-10-19')->where('registrado_por', $this->docente->id)->count());
        $this->assertSame('crear', Auditoria::latest('id')->first()->accion);
        $this->assertStringEndsWith('del lun 19 oct: 1 presente y 1 llegó tarde.', Auditoria::latest('id')->first()->descripcion);

        // Corrige: Juan faltó con excusa
        $this->asistencia('2026-10-19', ['presente', 'excusa'], 'Cita médica')
            ->assertOk()->assertJson(['mensaje' => 'Corregiste la asistencia del lun 19 oct.']);
        $this->assertSame('excusa', Asistencia::where('estudiante_id', $this->juan->estudiante_id)->value('estado'));
        $this->assertSame('Cita médica', Asistencia::where('estudiante_id', $this->juan->estudiante_id)->value('motivo'));
        $this->assertSame('editar', Auditoria::latest('id')->first()->accion);
        $this->assertSame(2, Asistencia::count());
    }

    public function test_la_clase_de_hoy_se_toma_aunque_no_haya_terminado(): void
    {
        $this->asistencia('2026-10-21', ['presente', 'presente'])->assertOk();
    }

    public function test_toda_la_lista_marcada_y_la_excusa_con_motivo(): void
    {
        $this->actingAs($this->docente)->postJson($this->urlAsistencia(), [
            'fecha'     => '2026-10-19',
            'registros' => [$this->laura->estudiante_id => ['estado' => 'presente']],
        ])->assertStatus(422)->assertJsonPath('errors.registros.0', 'Falta marcar a Juan Gómez.');

        $this->asistencia('2026-10-19', ['presente', 'excusa'], ' ')
            ->assertStatus(422)->assertJsonPath('errors.registros.0', 'Escribe el motivo de la excusa de Juan.');
    }

    public function test_solo_dias_de_clase_que_ya_llegaron(): void
    {
        $this->asistencia('2026-10-20', ['presente', 'presente'])
            ->assertStatus(422)->assertJsonPath('errors.fecha.0', 'Ese día no hay clase en el horario del grupo.');
        $this->asistencia('2026-10-26', ['presente', 'presente'])
            ->assertStatus(422)->assertJsonPath('errors.fecha.0', 'Esa clase todavía no ha llegado.');
        $this->assertSame(0, Asistencia::count());
    }

    public function test_quien_se_matriculo_despues_no_va_en_la_lista_de_esa_clase(): void
    {
        $nuevo = $this->matricula('Pedro', 'Rojas', '2026-10-15');

        $this->actingAs($this->docente)->postJson($this->urlAsistencia(), [
            'fecha'     => '2026-10-12',
            'registros' => [
                $this->laura->estudiante_id => ['estado' => 'presente'],
                $this->juan->estudiante_id  => ['estado' => 'presente'],
                $nuevo->estudiante_id       => ['estado' => 'presente'],
            ],
        ])->assertStatus(422)->assertJsonPath('errors.registros.0', 'Uno de los estudiantes no está en la lista de esa clase.');
    }

    public function test_grupo_ajeno_o_finalizado(): void
    {
        $otro = $this->docente();
        $this->actingAs($otro)->postJson($this->urlAsistencia(), ['fecha' => '2026-10-19', 'registros' => []])
            ->assertForbidden()->assertJson(['message' => 'Ese grupo no es tuyo.']);

        $this->grupo->update(['estado' => 'finalizado']);
        $this->asistencia('2026-10-19', ['presente', 'presente'])
            ->assertStatus(422)->assertJsonPath('errors.fecha.0', 'El grupo ya terminó: su asistencia solo se consulta.');
    }

    public function test_lo_que_registra_el_docente_es_lo_que_ve_el_admin(): void
    {
        $this->asistencia('2026-10-19', ['presente', 'ausente'])->assertOk();

        $admin = app(AsistenciaService::class)->resumenSede($this->sede->id);
        $grupo = $admin['grupos']->firstWhere('id', $this->grupo->id);
        $this->assertNotContains('2026-10-19', $grupo['sin_tomar']);
        $juan = $admin['estudiantes']->firstWhere('id', $this->juan->estudiante_id);
        $this->assertSame(1, $juan['A']);
    }

    /* ───────────── Notas ───────────── */

    public function test_pone_nota_y_habilitacion_y_el_admin_la_ve(): void
    {
        $this->notas(0, [$this->laura->estudiante_id => ['n' => 2.5, 'h' => 3.4], $this->juan->estudiante_id => ['n' => '4,2', 'h' => null]])
            ->assertOk()->assertJson(['ok' => true, 'mensaje' => 'Guardaste las notas de Fundamentos.']);

        $c = Calificacion::where('matricula_id', $this->laura->id)->first();
        $this->assertSame([2.5, 3.4, 'habilito', $this->docente->id, $this->grupo->id], [$c->nota, $c->habilitacion, $c->estado(), $c->registrado_por, $c->curso_id]);
        $this->assertSame(4.2, Calificacion::where('matricula_id', $this->juan->id)->value('nota'));

        $admin = app(CalificacionService::class)->resumenSede($this->sede->id);
        $laura = $admin['estudiantes']->firstWhere('id', $this->laura->id);
        $this->assertSame('habilito', $laura['estados'][0]);
    }

    public function test_sin_nota_borra_la_que_tenia(): void
    {
        $this->notas(0, [$this->laura->estudiante_id => ['n' => 3.5, 'h' => null]])->assertOk();
        $this->notas(0, [$this->laura->estudiante_id => ['n' => null, 'h' => null]])->assertOk();

        $this->assertSame(0, Calificacion::count());
    }

    public function test_la_nota_va_de_1_a_5_con_un_decimal(): void
    {
        foreach (['3.25', 5.5, 0.9, 'tres'] as $mala) {
            $this->notas(0, [$this->laura->estudiante_id => ['n' => $mala, 'h' => null]])
                ->assertStatus(422)->assertJsonPath('errors.notas.0', 'La nota de Laura debe ir de 1.0 a 5.0, con un decimal.');
        }
        $this->assertSame(0, Calificacion::count());
    }

    public function test_habilitacion_solo_si_perdio_un_modulo_terminado(): void
    {
        $this->notas(0, [$this->laura->estudiante_id => ['n' => 3.0, 'h' => 3.5]])
            ->assertStatus(422)->assertJsonPath('errors.notas.0', 'Laura no perdió el módulo: no lleva habilitación.');
        $this->notas(2, [$this->laura->estudiante_id => ['n' => 2.0, 'h' => 3.5]])
            ->assertStatus(422)->assertJsonPath('errors.notas.0', 'La habilitación se presenta cuando el módulo termina.');
        // Sin habilitación, el módulo en curso sí recibe nota
        $this->notas(2, [$this->laura->estudiante_id => ['n' => 2.0, 'h' => null]])->assertOk();
    }

    public function test_no_en_modulos_por_iniciar_ni_de_otro_programa(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $this->notas(1, [$this->laura->estudiante_id => ['n' => 4.0, 'h' => null]])
            ->assertStatus(422)->assertJsonPath('errors.notas.0', 'Ese módulo todavía no empieza.');

        $otro = $this->programa(['Mañana'], 100, 3);
        $ajeno = $otro->modulos()->create(['nombre' => 'Ajeno', 'horas' => 100, 'orden' => 1]);
        $this->actingAs($this->docente)->postJson("/docente/notas/{$this->grupo->id}/{$ajeno->id}", ['notas' => [$this->laura->estudiante_id => ['n' => 4.0, 'h' => null]]])
            ->assertStatus(422)->assertJsonPath('errors.notas.0', 'Ese módulo no es de este grupo.');
    }

    public function test_solo_matriculas_activas_y_modulos_que_curso_en_el_grupo(): void
    {
        $tarde = $this->matricula('Pedro', 'Rojas', '2026-09-01');
        $this->notas(0, [$tarde->estudiante_id => ['n' => 4.0, 'h' => null]])
            ->assertStatus(422)->assertJsonPath('errors.notas.0', 'Pedro no cursó este módulo en el grupo.');

        $this->juan->update(['estado' => 'retirado', 'estado_fecha' => '2026-10-01']);
        $this->notas(1, [$this->juan->estudiante_id => ['n' => 4.0, 'h' => null]])
            ->assertStatus(422)->assertJsonPath('errors.notas.0', 'La matrícula de Juan no está activa: sus notas solo se consultan.');
    }

    public function test_notas_de_grupo_ajeno_o_finalizado(): void
    {
        $this->actingAs($this->docente())->postJson($this->urlNotas(0), ['notas' => [$this->laura->estudiante_id => ['n' => 4.0]]])
            ->assertForbidden();

        $this->grupo->update(['estado' => 'finalizado']);
        $this->notas(0, [$this->laura->estudiante_id => ['n' => 4.0, 'h' => null]])
            ->assertStatus(422)->assertJsonPath('errors.notas.0', 'El grupo ya terminó: sus notas solo se consultan.');
    }

    /* ───────────── Apoyo ───────────── */

    private function matricula(string $nombres, string $apellidos, string $desde): Matricula
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'nombres' => $nombres, 'apellidos' => $apellidos]);
        $this->grupo->estudiantes()->attach($e->id);

        return Matricula::create([
            'codigo' => 'MAT-'.$e->id, 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id,
            'curso_id' => $this->grupo->id, 'sede_id' => $this->sede->id, 'estado' => 'activo',
            'estado_fecha' => $desde, 'fecha_inicio' => $desde,
        ]);
    }

    private function urlAsistencia(): string
    {
        return "/docente/asistencia/{$this->grupo->id}";
    }

    /** Laura y Juan, en ese orden. */
    private function asistencia(string $fecha, array $estados, ?string $motivo = null)
    {
        $registros = [];
        foreach ([$this->laura, $this->juan] as $i => $m) {
            $registros[$m->estudiante_id] = ['estado' => $estados[$i], 'motivo' => $estados[$i] === 'excusa' ? $motivo : null];
        }

        return $this->actingAs($this->docente)->postJson($this->urlAsistencia(), ['fecha' => $fecha, 'registros' => $registros]);
    }

    private function urlNotas(int $modulo): string
    {
        return "/docente/notas/{$this->grupo->id}/{$this->programa->modulos[$modulo]->id}";
    }

    private function notas(int $modulo, array $notas)
    {
        return $this->actingAs($this->docente)->postJson($this->urlNotas($modulo), ['notas' => $notas]);
    }
}
