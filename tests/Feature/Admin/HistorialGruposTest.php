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

    /* ── A8: finalizar a tiempo ── */

    public function test_no_se_finaliza_antes_de_tiempo_con_estudiantes(): void
    {
        $g = $this->grupoEnClases();
        $this->actingAs($this->admin);
        app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $g, null);

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
        $m = app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $g, null);
        $g->update(['estado' => 'finalizado']);   // finalizado antes de tiempo (datos viejos)

        $this->patchJson("/admin/matriculas/{$m->id}/estado", ['estado' => 'graduado', 'fecha' => today()->toDateString()])
            ->assertJsonValidationErrors(['estado' => 'termina']);
    }
}
