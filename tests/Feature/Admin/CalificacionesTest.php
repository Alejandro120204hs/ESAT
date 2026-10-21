<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Academico\CalificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Panel Admin › Calificaciones (solo consulta): fechas de los módulos calculadas con el horario,
 * estados de la nota (aprobado, habilitó, por habilitar, perdió la habilitación), módulos sin nota,
 * quien entró tarde, listos para graduar y la página por sede.
 */
class CalificacionesTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        // 3 meses, 3 módulos de 100 / 100 / 200 horas
        $this->programa = $this->programa(['Mañana'], 400, 3);
        $this->programa->modulos()->createMany([
            ['nombre' => 'Fundamentos', 'horas' => 100, 'orden' => 1],
            ['nombre' => 'Normativa', 'horas' => 100, 'orden' => 2],
            ['nombre' => 'Práctica', 'horas' => 200, 'orden' => 3],
        ]);
        $this->docente = $this->docente();
        // Lunes y miércoles desde el lunes 3 de agosto hasta el 3 de noviembre: 27 clases
        $this->grupo = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', [
            $this->sesion('Lunes', '07:00', '11:00'),
            $this->sesion('Miércoles', '07:00', '11:00'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_las_fechas_de_cada_modulo_se_reparten_por_horas_en_las_clases_del_grupo(): void
    {
        $mods = app(CalificacionService::class)->fechasModulos($this->grupo->load('programa.modulos', 'sesiones'));

        // 27 clases: 6 + 7 + 14 (en orden y sin saltarse ninguna)
        $this->assertSame(['2026-08-03', '2026-08-19'], [$mods[0]['ini'], $mods[0]['fin']]);
        $this->assertSame(['2026-08-24', '2026-09-14'], [$mods[1]['ini'], $mods[1]['fin']]);
        $this->assertSame(['2026-09-16', '2026-11-02'], [$mods[2]['ini'], $mods[2]['fin']]);
        $this->assertSame(['terminado', 'terminado', 'en_curso'], array_column($mods, 'estado'));
    }

    public function test_estado_de_la_nota_con_una_sola_habilitacion(): void
    {
        $c = fn ($n, $h = null) => new Calificacion(['nota' => $n, 'habilitacion' => $h]);

        $this->assertSame('aprobado', $c(3.0)->estado());
        $this->assertSame('por_habilitar', $c(2.9)->estado());
        $this->assertSame('habilito', $c(2.5, 3.0)->estado());
        $this->assertSame('reprobado', $c(2.5, 2.9)->estado());
        $this->assertSame(3.0, $c(2.5, 3.0)->notaFinal());
        $this->assertFalse($c(2.5, 2.9)->aprobada());
    }

    public function test_resumen_del_estudiante_y_modulos_sin_nota_del_docente(): void
    {
        $m = $this->matricula('1070000001', '2026-07-20');
        $this->nota($m, 0, 4.0);
        // Normativa terminó y el docente no la registró

        $r = $this->resumen();
        $e = $r['estudiantes']->firstWhere('id', $m->id);
        $g = $r['grupos']->firstWhere('id', $this->grupo->id);

        $this->assertSame([['n' => 4.0, 'h' => null], null, null], $e['notas']);
        $this->assertSame(['aprobado', 'sin_nota', 'en_curso'], $e['estados']);
        $this->assertSame(4.0, $e['prom']);
        $this->assertSame('al_dia', $e['situacion']);
        $this->assertSame([1], $g['sin_nota']);
        $this->assertSame(1, $r['resumen']['mod_sin_nota']);
    }

    public function test_quien_se_matriculo_despues_no_deja_pendiente_al_docente_pero_no_puede_graduarse(): void
    {
        $m = $this->matricula('1070000002', '2026-09-01');

        $e = $this->resumen()['estudiantes']->firstWhere('id', $m->id);

        $this->assertSame(['no_cursado', 'sin_nota', 'en_curso'], $e['estados']);
        $this->assertSame(['Fundamentos', 'Normativa', 'Práctica'], app(CalificacionService::class)->pendientesParaGraduar($m));
    }

    public function test_perder_la_habilitacion_y_listo_para_graduar(): void
    {
        $this->grupo->update(['estado' => 'finalizado']);
        $pierde = $this->matricula('1070000003', '2026-07-20');
        $listo  = $this->matricula('1070000004', '2026-07-20');
        foreach ([0, 1, 2] as $i) {
            $this->nota($listo, $i, $i === 1 ? 2.0 : 4.5, $i === 1 ? 3.5 : null);
            $this->nota($pierde, $i, $i === 2 ? 2.0 : 3.8, $i === 2 ? 2.4 : null);
        }

        $r = $this->resumen();

        $this->assertSame('listo', $r['estudiantes']->firstWhere('id', $listo->id)['situacion']);
        $this->assertSame('reprobado', $r['estudiantes']->firstWhere('id', $pierde->id)['situacion']);
        $this->assertSame(['Práctica'], app(CalificacionService::class)->pendientesParaGraduar($pierde));
        $this->assertSame(1, $r['resumen']['listos']);
        $this->assertSame(1, $r['resumen']['reprobados']);
    }

    public function test_la_pagina_muestra_solo_los_grupos_de_su_sede(): void
    {
        $otra = $this->otraSede();
        $this->programa->sedes()->attach($otra->id);
        $ajeno = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-08-03', [$this->sesion('Lunes', '07:00', '11:00')], $otra);

        $this->actingAs($this->admin)->get('/admin/calificaciones')
            ->assertOk()
            ->assertSee($this->grupo->programa->nombre)
            ->assertViewHas('grupos', fn ($g) => $g->pluck('id')->all() === [$this->grupo->id] && ! in_array($ajeno->id, $g->pluck('id')->all(), true));
    }

    private function matricula(string $documento, string $desde): Matricula
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento]);
        $this->grupo->estudiantes()->attach($e->id);

        return Matricula::create([
            'codigo' => 'MAT-'.$documento, 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id,
            'curso_id' => $this->grupo->id, 'sede_id' => $this->sede->id, 'estado' => 'activo',
            'estado_fecha' => $desde, 'fecha_inicio' => $desde,
        ]);
    }

    private function nota(Matricula $m, int $modulo, float $nota, ?float $hab = null): void
    {
        $m->calificaciones()->create([
            'programa_modulo_id' => $this->programa->modulos[$modulo]->id, 'curso_id' => $this->grupo->id,
            'nota' => $nota, 'fecha_nota' => '2026-10-01', 'habilitacion' => $hab, 'registrado_por' => $this->docente->id,
        ]);
    }

    private function resumen(): array
    {
        return app(CalificacionService::class)->resumenSede($this->sede->id);
    }
}
