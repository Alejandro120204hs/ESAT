<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Curso;
use App\Models\User;
use App\Services\Academico\AsistenciaService;
use App\Services\Academico\CalificacionService;
use App\Services\Academico\MatriculaService;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lo ya dictado no cambia (HALLAZGOS A7) y un grupo no se finaliza antes de tiempo con estudiantes (A8).
 * Hoy es miércoles 11-11-2026; el grupo empezó el lunes 7-sep con clases lunes y miércoles de 8 a 12.
 */
class HistorialGruposTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private User $docente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 15:00');
        $this->prepararSede();
        $this->docente = $this->docente();
    }

    private function grupoEnClases(string $inicio = '2026-09-07', ?User $docente = null): Curso
    {
        return $this->grupo($this->programa(['Mañana'], 1600, 12), 'Mañana', $docente ?? $this->docente, 'activo', $inicio,
            [$this->sesion('Lunes', '08:00', '12:00'), $this->sesion('Miércoles', '08:00', '12:00')]);
    }

    private function cambiarAMartesYJueves(Curso $g)
    {
        return $this->actingAs($this->admin)->putJson("/admin/horarios/{$g->id}", [
            'docente_id' => $this->docente->id,
            'sesiones'   => [$this->sesion('Martes', '08:00', '12:00'), $this->sesion('Jueves', '08:00', '12:00')],
        ]);
    }

    private function dictadas(Curso $g, string $ahora): array
    {
        return app(AsistenciaService::class)->fechasDictadas(Curso::with(['programa', 'historialSesiones'])->find($g->id), Carbon::parse($ahora));
    }

    /* ── A7: cambiar el horario no reescribe el pasado ── */

    public function test_cambiar_el_horario_conserva_las_clases_ya_dictadas(): void
    {
        $g = $this->grupoEnClases();
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        foreach (['2026-09-07', '2026-09-09', '2026-11-09'] as $f) {
            Asistencia::create(['curso_id' => $g->id, 'estudiante_id' => $e->id, 'fecha' => $f, 'estado' => 'presente', 'registrado_por' => $this->docente->id]);
        }

        $this->cambiarAMartesYJueves($g)->assertOk();

        $antes = $this->dictadas($g, '2026-11-11 15:00');
        $this->assertContains('2026-09-07', $antes, 'Un lunes ya dictado sigue siendo clase');
        $this->assertContains('2026-11-11', $antes, 'La clase de hoy (miércoles) ya se dictó con el horario de siempre');
        $this->assertNotContains('2026-09-08', $antes, 'Un martes del pasado no aparece como clase sin registrar');

        $despues = $this->dictadas($g, '2026-11-20 13:00');
        $this->assertContains('2026-11-12', $despues, 'Desde mañana rige el horario nuevo (jueves)');
        $this->assertContains('2026-11-17', $despues);
        $this->assertNotContains('2026-11-16', $despues, 'Los lunes ya no son clase');

        // Las demás pantallas usan la misma regla
        $panel = app(PanelDocenteService::class);
        $c = Curso::with(['programa', 'historialSesiones'])->find($g->id);
        $this->assertTrue($panel->esDiaDeClase($c, '2026-09-07'));
        $this->assertFalse($panel->esDiaDeClase($c, '2026-09-08'));
        $this->assertTrue($panel->esDiaDeClase($c, '2026-11-12'));
        $modulos = app(CalificacionService::class)->fechasModulos(Curso::with(['programa.modulos', 'historialSesiones'])->find($g->id));
        $this->assertSame([], $modulos);   // sin malla no hay módulos, pero no falla

        // En Horarios se ve el horario nuevo
        $this->assertSame(['Martes', 'Jueves'], $g->fresh()->sesiones()->orderBy('id')->pluck('dia')->all());
    }

    public function test_un_grupo_sin_nada_registrado_reemplaza_su_horario_completo(): void
    {
        $g = $this->grupoEnClases();
        $this->cambiarAMartesYJueves($g)->assertOk();

        $this->assertSame(2, $g->historialSesiones()->count(), 'Sin asistencia ni notas es una corrección: no queda historial');
        $this->assertContains('2026-09-08', $this->dictadas($g, '2026-11-11 15:00'));
    }

    public function test_la_fecha_de_inicio_no_cambia_si_el_grupo_ya_empezo(): void
    {
        $g = $this->grupoEnClases();
        $this->actingAs($this->admin)->putJson("/admin/cursos/{$g->id}", [
            'programa_id' => $g->programa_id, 'docente_id' => $this->docente->id, 'jornada' => 'Mañana', 'fecha_inicio' => '2026-09-14', 'estado' => 'activo',
        ])->assertJsonValidationErrors('fecha_inicio');

        $otroDocente = $this->docente();
        $futuro = $this->grupoEnClases('2027-01-11', $otroDocente);
        $this->putJson("/admin/cursos/{$futuro->id}", [
            'programa_id' => $futuro->programa_id, 'docente_id' => $otroDocente->id, 'jornada' => 'Mañana', 'fecha_inicio' => '2027-01-18', 'estado' => 'activo',
        ])->assertOk();
    }

    /** Uno que sigue en planificación sin asistencia ni notas nunca dictó clases: corre su inicio, pero a hoy o después (26-11-2026). */
    public function test_un_grupo_en_planificacion_atrasado_corre_su_fecha_de_inicio(): void
    {
        $g = $this->grupo($this->programa(['Mañana'], 1600, 12), 'Mañana', null, 'planificacion', '2026-11-02');
        $datos = fn (string $inicio) => ['programa_id' => $g->programa_id, 'jornada' => 'Mañana', 'fecha_inicio' => $inicio, 'estado' => 'planificacion'];

        $this->actingAs($this->admin)->putJson("/admin/cursos/{$g->id}", $datos('2026-11-03'))->assertJsonValidationErrors(['fecha_inicio' => 'hoy o a una fecha futura']);
        $this->putJson("/admin/cursos/{$g->id}", $datos(today()->addWeek()->toDateString()))->assertOk();
        $this->assertSame(today()->addWeek()->toDateString(), $g->fresh()->fecha_inicio->toDateString());
    }

    /**
     * Revisión del Administrativo (27-11-2026): un grupo nuevo, o uno que todavía no empieza, no se pone a empezar en el
     * pasado. Antes se podía mover al 1-jul un grupo del 2-nov con estudiantes ya matriculados: quedaban en un grupo que
     * "empezó" antes que su matrícula y el derecho seguía venciendo el 2-nov.
     */
    public function test_un_grupo_no_se_crea_ni_se_corre_a_una_fecha_pasada(): void
    {
        $p = $this->programa(['Mañana'], 1600, 12);
        $datos = fn (string $inicio, string $estado = 'planificacion') => ['programa_id' => $p->id, 'jornada' => 'Mañana', 'fecha_inicio' => $inicio, 'estado' => $estado];

        $this->actingAs($this->admin)->postJson('/admin/cursos', $datos('2026-01-05'))->assertJsonValidationErrors(['fecha_inicio' => 'anterior a hoy']);
        $this->postJson('/admin/cursos', $datos(today()->toDateString()))->assertOk();

        $g = $this->grupo($p, 'Mañana', null, 'planificacion', '2026-12-07');
        $m = app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $g, null);
        $this->putJson("/admin/cursos/{$g->id}", $datos('2026-07-01'))->assertJsonValidationErrors(['fecha_inicio' => 'anterior a hoy']);
        $this->assertSame('2026-12-07', $g->fresh()->fecha_inicio->toDateString());

        // Correrlo hacia adelante sí, y el derecho de matrícula se va con él
        $this->putJson("/admin/cursos/{$g->id}", $datos('2027-01-11'))->assertOk();
        $this->assertSame('2027-01-11', $m->pagos()->where('tipo', 'matricula')->first()->fecha_vencimiento->toDateString());
    }

    /** Un grupo activo que ya empezó no vuelve a planificación; uno que todavía no empieza, sí (decidido con el usuario el 27-11-2026). */
    public function test_un_grupo_activo_que_ya_empezo_no_vuelve_a_planificacion(): void
    {
        $g = $this->grupoEnClases();
        $datos = fn (Curso $c) => ['programa_id' => $c->programa_id, 'docente_id' => $this->docente->id, 'jornada' => 'Mañana',
            'fecha_inicio' => $c->fecha_inicio->toDateString(), 'estado' => 'planificacion'];

        $this->actingAs($this->admin)->putJson("/admin/cursos/{$g->id}", $datos($g))->assertJsonValidationErrors(['estado' => 'no vuelve a planificación']);
        $this->assertSame('activo', $g->fresh()->estado);

        $futuro = $this->grupoEnClases('2027-01-11', $this->docente());
        $this->putJson("/admin/cursos/{$futuro->id}", ['docente_id' => $futuro->docente()->id] + $datos($futuro))->assertOk();
        $this->assertSame('planificacion', $futuro->fresh()->estado);
    }

    /** Un grupo finalizado es historia (quién lo dictó): no se edita; primero se reabre desde su ficha (27-11-2026). */
    public function test_un_grupo_finalizado_no_se_edita(): void
    {
        $g = $this->grupoEnClases();
        $g->update(['estado' => 'finalizado']);
        $otro = $this->docente();

        $this->actingAs($this->admin)->putJson("/admin/cursos/{$g->id}", [
            'programa_id' => $g->programa_id, 'docente_id' => $otro->id, 'jornada' => 'Mañana', 'fecha_inicio' => '2026-09-07', 'estado' => 'finalizado',
        ])->assertJsonValidationErrors(['estado' => 'reábrelo']);
        $this->assertSame($this->docente->id, $g->fresh()->docente()->id);

        $this->get('/admin/cursos')->assertOk()->assertDontSee('data-action="editar" data-id="'.$g->id.'"', false);
    }

    /* ── A8: finalizar a tiempo ── */

    public function test_no_se_finaliza_antes_de_tiempo_con_estudiantes(): void
    {
        $g = $this->grupoEnClases();
        $this->actingAs($this->admin);
        $this->alIniciar($g, fn () => app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $g, null));

        $this->patchJson("/admin/cursos/{$g->id}/estado")->assertStatus(422);
        $this->putJson("/admin/cursos/{$g->id}", [
            'programa_id' => $g->programa_id, 'docente_id' => $this->docente->id, 'jornada' => 'Mañana', 'fecha_inicio' => '2026-09-07', 'estado' => 'finalizado',
        ])->assertJsonValidationErrors('estado');
        $this->assertSame('activo', $g->fresh()->estado);

        // Cuando termina, sí
        Carbon::setTestNow('2027-09-08 10:00');
        $this->patchJson("/admin/cursos/{$g->id}/estado")->assertOk();
        $this->assertSame('finalizado', $g->fresh()->estado);
    }

    public function test_un_grupo_sin_estudiantes_vigentes_se_puede_cerrar_antes(): void
    {
        $g = $this->grupoEnClases();
        $this->actingAs($this->admin)->patchJson("/admin/cursos/{$g->id}/estado")->assertOk();
        $this->assertSame('finalizado', $g->fresh()->estado);
    }

    public function test_no_se_gradua_antes_de_que_termine_el_grupo_aunque_este_finalizado(): void
    {
        $g = $this->grupoEnClases();
        $this->actingAs($this->admin);
        $m = $this->alIniciar($g, fn () => app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $g, null));
        $g->update(['estado' => 'finalizado']);   // finalizado antes de tiempo (datos viejos)

        $this->patchJson("/admin/matriculas/{$m->id}/estado", ['estado' => 'graduado', 'fecha' => today()->toDateString()])
            ->assertJsonValidationErrors(['estado' => 'termina']);
    }
}
