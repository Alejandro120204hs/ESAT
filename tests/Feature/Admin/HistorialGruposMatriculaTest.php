<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Academico\AsistenciaService;
use App\Services\Academico\MatriculaService;
use App\Services\Academico\PanelDocenteService;
use App\Services\Academico\PanelEstudianteService;
use App\Services\Reportes\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Historial de grupos de la matrícula (decidido con el usuario el 27-11-2026, tabla matricula_grupos): un cambio de grupo
 * o un reintegro no borra al estudiante del grupo viejo ni lo pone en clases del grupo nuevo de antes de llegar.
 * Grupos A y B empiezan el lunes 5-oct (lunes y miércoles). Las clases se reparten por horas (CalificacionService):
 * Fundamentos el 5 y 7-oct, Normativa del 12 al 19-oct y luego Práctica. Laura está en A desde el inicio y se cambia a B
 * el lunes 19-oct (último día de Normativa).
 */
class HistorialGruposMatriculaTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docenteA;
    private User $docenteB;
    private Curso $a;
    private Curso $b;
    private Matricula $laura;
    private Matricula $pedro;

    protected function setUp(): void
    {
        parent::setUp();
        // Sus fechas fijas incluyen festivos (12-oct, 2-nov): las reglas de festivos se prueban en FestivosTest
        config(['academico.festivos' => false]);
        Carbon::setTestNow('2026-10-05 07:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana'], 300, 6);
        $this->programa->modulos()->createMany([
            ['nombre' => 'Fundamentos', 'horas' => 16, 'orden' => 1],
            ['nombre' => 'Normativa', 'horas' => 16, 'orden' => 2],
            ['nombre' => 'Práctica', 'horas' => 268, 'orden' => 3],
        ]);
        $horario = [$this->sesion('Lunes', '07:00', '11:00'), $this->sesion('Miércoles', '07:00', '11:00')];
        $this->docenteA = $this->docente();
        $this->docenteB = $this->docente();
        $this->a = $this->grupo($this->programa, 'Mañana', $this->docenteA, 'activo', '2026-10-05', $horario);
        $this->b = $this->grupo($this->programa, 'Mañana', $this->docenteB, 'activo', '2026-10-05', $horario);
        $this->laura = app(MatriculaService::class)->matricular($this->estudiante('Laura'), $this->a, null);
        $this->pedro = app(MatriculaService::class)->matricular($this->estudiante('Pedro'), $this->b, null);
        foreach (['2026-10-05' => 'presente', '2026-10-07' => 'presente', '2026-10-12' => 'ausente', '2026-10-14' => 'presente'] as $f => $estado) {
            Asistencia::create(['curso_id' => $this->a->id, 'estudiante_id' => $this->laura->estudiante_id, 'fecha' => $f, 'estado' => $estado, 'registrado_por' => $this->docenteA->id]);
        }
        Carbon::setTestNow('2026-10-19 12:00');
        app(MatriculaService::class)->cambiarGrupo($this->laura->fresh(), $this->b);
        Carbon::setTestNow('2026-10-22 12:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_el_cambio_de_grupo_queda_en_el_historial(): void
    {
        $this->assertSame([
            [$this->a->id, '2026-10-05', '2026-10-19'],
            [$this->b->id, '2026-10-19', null],
        ], $this->laura->grupos()->get()->map(fn ($g) => [$g->curso_id, $g->desde->toDateString(), $g->hasta?->toDateString()])->all());
    }

    public function test_el_docente_del_grupo_viejo_conserva_su_paso_y_el_nuevo_la_ve_desde_que_llego(): void
    {
        $enA = $this->filaDocente($this->docenteA, $this->laura->estudiante_id);
        $this->assertSame('trasladado', $enA['matricula']['estado']);
        $this->assertSame([['desde' => '2026-10-05', 'hasta' => '2026-10-19']], $enA['ventanas']);

        $enB = $this->filaDocente($this->docenteB, $this->laura->estudiante_id);
        $this->assertSame('activo', $enB['matricula']['estado']);
        $this->assertSame('2026-10-19', $enB['desde']);

        // B toma la clase del 12-oct (antes de que llegara) solo con Pedro; Laura ya no va en esa lista
        $this->actingAs($this->docenteB)->postJson("/docente/asistencia/{$this->b->id}", ['fecha' => '2026-10-12', 'registros' => [
            $this->pedro->estudiante_id => ['estado' => 'presente'],
        ]])->assertOk();
        $this->postJson("/docente/asistencia/{$this->b->id}", ['fecha' => '2026-10-21', 'registros' => [
            $this->pedro->estudiante_id => ['estado' => 'presente'],
        ]])->assertJsonValidationErrors(['registros' => 'Falta marcar a Laura']);
        // A ya no la puede marcar después de que se fue
        $this->actingAs($this->docenteA)->postJson("/docente/asistencia/{$this->a->id}", ['fecha' => '2026-10-21', 'registros' => [
            $this->laura->estudiante_id => ['estado' => 'presente'],
        ]])->assertJsonValidationErrors('registros');
    }

    public function test_las_notas_las_pone_el_docente_del_grupo_donde_curso_el_modulo(): void
    {
        [$fund, $norm] = [$this->programa->modulos[0], $this->programa->modulos[1]];
        $id = $this->laura->estudiante_id;

        // Fundamentos terminó en A antes del cambio (7-oct): lo califica el docente A, no el B
        $this->actingAs($this->docenteB)->postJson("/docente/notas/{$this->b->id}/{$fund->id}", ['notas' => [$id => ['n' => '4,0', 'h' => null]]])
            ->assertJsonValidationErrors(['notas' => 'no cursó este módulo']);
        $this->actingAs($this->docenteA)->postJson("/docente/notas/{$this->a->id}/{$fund->id}", ['notas' => [$id => ['n' => '4,0', 'h' => null]]])->assertOk();

        // Normativa terminó el mismo día del cambio, en B: la califica B y no A
        $this->postJson("/docente/notas/{$this->a->id}/{$norm->id}", ['notas' => [$id => ['n' => '3,5', 'h' => null]]])
            ->assertJsonValidationErrors(['notas' => 'se cambió de grupo']);
        $this->actingAs($this->docenteB)->postJson("/docente/notas/{$this->b->id}/{$norm->id}", ['notas' => [$id => ['n' => '3,5', 'h' => null]]])->assertOk();

        // La nota de Fundamentos (de A) sigue a la matrícula: B la ve
        $this->assertSame(4.0, (float) $this->filaNotasDocente($this->docenteB, $fund->id, $id)['n']);
    }

    public function test_la_asistencia_del_administrativo_y_del_estudiante_juntan_los_dos_grupos(): void
    {
        $res = app(AsistenciaService::class)->resumenSede($this->sede->id);
        $filas = collect($res['estudiantes'])->where('id', $this->laura->estudiante_id)->keyBy('grupo_id');
        $this->assertSame('trasladado', $filas[$this->a->id]['estado']);
        $this->assertSame('PPAP..', $filas[$this->a->id]['cod']);   // 4 clases en A; las del 19 y el 21 ya son de B
        $this->assertSame('....--', $filas[$this->b->id]['cod']);  // en B desde el 19 (sin registrar)

        // Su panel: las 4 clases de A y las de B desde que llegó; 3 de 4 = 75 %
        $d = app(PanelEstudianteService::class)->datos($this->laura->estudiante);
        $this->assertSame(['2026-10-05', '2026-10-07', '2026-10-12', '2026-10-14', '2026-10-19', '2026-10-21'], array_column(array_filter($d['clases'], fn ($c) => $c['fecha'] <= '2026-10-21'), 'fecha'));
        $this->assertSame(75, $d['asistencia']['pct']);
        // La ficha del Administrativo usa la misma regla
        $this->assertSame(75, app(\App\Services\Academico\EstudianteService::class)->porcentajeAsistencia($this->laura->estudiante->load('asistencias'), $this->b, $this->laura->fresh()));

        // Reportes: sus dos pasos y la asistencia que tuvo en A
        $rep = app(ReporteService::class)->datosSede($this->sede->id);
        $m = collect($rep['matriculas'])->firstWhere('id', $this->laura->id);
        $this->assertCount(2, $m['pasos']);
        $this->assertTrue(collect($rep['asistencia'])->contains(fn ($x) => $x['matricula_id'] === $this->laura->id && $x['grupo_id'] === $this->a->id));
    }

    public function test_un_reintegro_no_cuenta_las_clases_mientras_estuvo_aplazado(): void
    {
        $m = $this->pedro->fresh();
        // Se aplaza el 7-oct; aprueba Fundamentos y vuelve el 12-oct, cuando el grupo empieza Normativa (la regla de grupo en curso)
        Carbon::setTestNow('2026-10-12 06:00');
        app(MatriculaService::class)->cambiarEstado($m, 'aplazado', Carbon::parse('2026-10-07'), 'Viaje');
        \App\Models\Academico\Calificacion::create(['matricula_id' => $m->id, 'programa_modulo_id' => $this->programa->modulos[0]->id, 'curso_id' => $this->b->id, 'nota' => 4.0, 'fecha_nota' => '2026-10-08']);
        app(MatriculaService::class)->cambiarEstado($m->fresh(), 'activo', Carbon::parse('2026-10-12'), 'Vuelve');
        Carbon::setTestNow('2026-10-22 12:00');

        $fila = $this->filaDocente($this->docenteB, $m->estudiante_id);
        $this->assertSame([['desde' => '2026-10-05', 'hasta' => '2026-10-07'], ['desde' => '2026-10-12', 'hasta' => null]], $fila['ventanas']);
        $planilla = fn (string $f) => app(PanelDocenteService::class)->planilla(app(PanelDocenteService::class)->curso($this->docenteB, $this->b->id), $f)->pluck('id')->all();
        $this->assertContains($m->estudiante_id, $planilla('2026-10-05'));
        $this->assertNotContains($m->estudiante_id, $planilla('2026-10-07'));
        $this->assertContains($m->estudiante_id, $planilla('2026-10-21'));
    }

    /* ───────────── Apoyo ───────────── */

    private function estudiante(string $nombre): User
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'nombres' => $nombre]);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));

        return $e;
    }

    private function filaDocente(User $docente, int $estudianteId): array
    {
        $d = app(PanelDocenteService::class)->datos($docente);

        return collect($d['grupos'][0]['estudiantes'])->firstWhere('id', $estudianteId);
    }

    private function filaNotasDocente(User $docente, int $moduloId, int $estudianteId): array
    {
        $d = app(PanelDocenteService::class)->datos($docente);

        $delGrupo = (array) ((array) $d['notas'])[$d['grupos'][0]['id']];

        return $delGrupo[$moduloId][$estudianteId];
    }
}
