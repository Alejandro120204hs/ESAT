<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use App\Services\Inicio\InicioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Panel Admin › Inicio: KPI reales con su variación frente al mes anterior, matrículas nuevas por mes, actividad de la
 * auditoría de la sede (siempre escapada), mejores promedios, ocupación por programa y pendientes con su enlace.
 */
class InicioTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private Curso $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana'], 1600, 10);
        $this->grupo = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-09-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function matricular(string $documento, string $fecha = '2026-08-20'): Matricula
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento, 'fecha_nacimiento' => '1998-05-10']);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));
        $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $this->grupo->id])->assertOk();
        $m = $e->matriculas()->first();
        $m->update(['plan_pago' => 'mensual', 'fecha_inicio' => $fecha]);
        app(PagoService::class)->generarCuotas($m, $this->grupo->fecha_inicio);

        return $m->fresh();
    }

    private function datos(): array
    {
        return app(InicioService::class)->datosSede($this->sede->id);
    }

    public function test_kpis_reales_con_su_variacion(): void
    {
        $a = $this->matricular('1070000001', '2026-08-20');
        $this->matricular('1070000002', '2026-10-05');
        $pagos = app(PagoService::class);
        $pagos->registrarPago($a, 100000, ['fecha_pago' => '2026-09-15', 'metodo_pago' => 'Efectivo']);
        $pagos->registrarPago($a, 150000, ['fecha_pago' => '2026-10-03', 'metodo_pago' => 'Efectivo']);

        [$activos, $ingresos, $grupos, $mora] = $this->datos()['kpis'];

        $this->assertSame([2, '+1', '1 matrícula nueva este mes'], [$activos['value'], $activos['delta'], $activos['foot']], 'Al cierre de septiembre había 1 activo');
        $this->assertSame([150000, '+50 %'], [$ingresos['value'], $ingresos['delta']]);
        $this->assertSame(1, $grupos['value']);
        // Las cuotas de septiembre y octubre ya vencieron para los dos (solo se abonó parte)
        $this->assertSame(2, $mora['value']);
    }

    public function test_matriculas_nuevas_por_mes(): void
    {
        $this->matricular('1070000001', '2026-08-20');
        $this->matricular('1070000002', '2026-10-05');
        $this->matricular('1070000003', '2026-10-12');

        $m = $this->datos()['meses'];
        $this->assertSame(['May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct'], array_column($m['barras'], 'mes'));
        $this->assertSame([0, 0, 0, 1, 0, 2], array_column($m['barras'], 'val'));
        $this->assertSame([2, 3], [$m['actual']['val'], $m['semestre']]);
        $this->assertTrue(end($m['barras'])['active']);
    }

    public function test_la_actividad_es_de_la_sede_y_se_pinta_escapada(): void
    {
        $this->actingAs($this->admin);
        Auditoria::registrar('editar', $this->programa, 'Editó <script>alert(1)</script> el programa');
        $otro = User::factory()->create(['role' => 'admin', 'sede_id' => $this->otraSede()->id]);
        $this->actingAs($otro);
        Auditoria::registrar('crear', $this->programa, 'Acción de otra sede');

        $act = $this->datos()['actividad'];
        $this->assertCount(1, $act);
        $this->assertSame('blue', $act[0]['dot']);

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('Acción de otra sede');
    }

    public function test_pendientes_con_enlace_y_accesos_rapidos_sin_numeral(): void
    {
        $this->matricular('1070000001');

        $tareas = $this->datos()['tareas'];
        $this->assertNotEmpty($tareas);
        foreach ($tareas as $t) {
            $this->assertStringStartsWith('http', $t['url']);
        }

        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringNotContainsString('Inscribir y asignar programa', $html, 'Estudiantes no matricula (HALLAZGOS B33)');
    }

    public function test_solo_el_admin_entra(): void
    {
        $this->get('/admin')->assertRedirect(route('login', absolute: false));
        $this->actingAs($this->docente())->get('/admin')->assertForbidden();
    }
}
