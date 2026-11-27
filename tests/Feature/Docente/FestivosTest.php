<?php

namespace Tests\Feature\Docente;

use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Academico\AsistenciaService;
use App\Services\Academico\MatriculaService;
use App\Services\Academico\PanelDocenteService;
use App\Support\Festivos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Festivos de Colombia y "No hubo clase" (decidido con el usuario el 27-11-2026). Grupo de lunes y miércoles desde el
 * lunes 5-oct-2026; el lunes 12-oct es festivo (Día de la Raza). Hoy es el jueves 22-oct.
 */
class FestivosTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private User $docente;
    private Curso $grupo;
    private Matricula $laura;

    protected function setUp(): void
    {
        parent::setUp();
        config(['academico.festivos' => true]);
        Carbon::setTestNow('2026-10-05 06:00');
        $this->prepararSede();
        $p = $this->programa(['Mañana'], 300, 6);
        $p->modulos()->createMany([['nombre' => 'Fundamentos', 'horas' => 150, 'orden' => 1], ['nombre' => 'Práctica', 'horas' => 150, 'orden' => 2]]);
        $this->docente = $this->docente();
        $this->grupo = $this->grupo($p, 'Mañana', $this->docente, 'activo', '2026-10-05', [$this->sesion('Lunes', '07:00', '11:00'), $this->sesion('Miércoles', '07:00', '11:00')]);
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'nombres' => 'Laura']);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));
        $this->laura = app(MatriculaService::class)->matricular($e, $this->grupo, null);
        Carbon::setTestNow('2026-10-22 12:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_los_festivos_de_colombia_se_calculan_por_la_ley_emiliani(): void
    {
        $f = Festivos::delAnio(2026);
        $this->assertCount(18, $f);
        $this->assertSame('Día de la Raza', $f['2026-10-12']);                 // 12-oct cae lunes
        $this->assertSame('Todos los Santos', $f['2026-11-02']);               // 1-nov domingo → lunes 2
        $this->assertSame('Independencia de Cartagena', $f['2026-11-16']);     // 11-nov miércoles → lunes 16
        $this->assertSame(['2026-04-02', '2026-04-03'], array_keys(array_intersect($f, ['Jueves Santo', 'Viernes Santo'])));
        $this->assertArrayHasKey('2027-07-05', Festivos::delAnio(2027));       // 29-jun-2027 martes → lunes 5-jul
    }

    public function test_un_festivo_no_es_clase_ni_queda_pendiente(): void
    {
        $fechas = app(AsistenciaService::class)->fechasDictadas($this->cargado());
        $this->assertSame(['2026-10-05', '2026-10-07', '2026-10-14', '2026-10-19', '2026-10-21'], $fechas);

        $g = collect(app(AsistenciaService::class)->resumenSede($this->sede->id)['grupos'])->firstWhere('id', $this->grupo->id);
        $this->assertNotContains('2026-10-12', $g['sin_tomar']);

        $d = app(PanelDocenteService::class)->datos($this->docente);
        $this->assertSame('Día de la Raza', ((array) $d['festivos'])['2026-10-12']);
    }

    public function test_si_el_docente_da_clase_en_un_festivo_toma_asistencia_y_cuenta(): void
    {
        $this->actingAs($this->docente)->postJson("/docente/asistencia/{$this->grupo->id}", ['fecha' => '2026-10-12', 'registros' => [
            $this->laura->estudiante_id => ['estado' => 'presente'],
        ]])->assertOk();
        $this->assertContains('2026-10-12', app(AsistenciaService::class)->fechasDictadas($this->cargado()));
    }

    public function test_no_hubo_clase_saca_el_dia_y_se_puede_deshacer(): void
    {
        $url = "/docente/asistencia/{$this->grupo->id}";
        $this->actingAs($this->docente)->postJson("$url/sin-clase", ['fecha' => '2026-10-14'])->assertJsonValidationErrors(['motivo' => 'por qué']);
        $this->postJson("$url/sin-clase", ['fecha' => '2026-10-15', 'motivo' => 'Paro'])->assertJsonValidationErrors(['fecha' => 'no hay clase']);
        $this->postJson("$url/sin-clase", ['fecha' => '2026-10-14', 'motivo' => 'Paro de transporte'])->assertOk();

        $this->assertNotContains('2026-10-14', app(AsistenciaService::class)->fechasDictadas($this->cargado()));
        $this->assertSame('Paro de transporte', ((array) app(PanelDocenteService::class)->datos($this->docente)['grupos'][0]['sinClase'])['2026-10-14']);
        // Ese día ya no se toma asistencia
        $this->postJson($url, ['fecha' => '2026-10-14', 'registros' => [$this->laura->estudiante_id => ['estado' => 'presente']]])
            ->assertJsonValidationErrors(['fecha' => 'no hay clase']);

        // Se deshace y vuelve a ser clase por registrar
        $this->postJson("$url/con-clase", ['fecha' => '2026-10-14'])->assertOk();
        $this->assertContains('2026-10-14', app(AsistenciaService::class)->fechasDictadas($this->cargado()));

        // Un día con asistencia tomada no se marca; otro docente no marca en este grupo; también se puede marcar una que viene
        $this->postJson($url, ['fecha' => '2026-10-19', 'registros' => [$this->laura->estudiante_id => ['estado' => 'presente']]])->assertOk();
        $this->postJson("$url/sin-clase", ['fecha' => '2026-10-19', 'motivo' => 'Paro'])->assertJsonValidationErrors(['fecha' => 'sí hubo clase']);
        $this->postJson("$url/sin-clase", ['fecha' => '2026-10-26', 'motivo' => 'Salida de la sede'])->assertOk();
        $this->actingAs($this->docente('activo'))->postJson("$url/sin-clase", ['fecha' => '2026-10-28', 'motivo' => 'Paro'])->assertForbidden();
    }

    private function cargado(): Curso
    {
        return Curso::with(['historialSesiones', 'sesiones', 'programa.modulos'])->findOrFail($this->grupo->id);
    }
}
