<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Academico\ProgramaModulo;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use App\Services\Reportes\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Panel Admin › Reportes: los datos con sus fechas que necesita reportes.js para armar cada documento al cierre de
 * cualquier mes (matrículas y su estado, clases y módulos del grupo, notas con fecha, asistencia por clase y cobros
 * con sus pagos). Solo de la sede del admin; la asistencia va por matrícula.
 */
class ReportesTest extends TestCase
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
        ProgramaModulo::create(['programa_id' => $this->programa->id, 'orden' => 1, 'nombre' => 'Módulo uno', 'horas' => 800]);
        ProgramaModulo::create(['programa_id' => $this->programa->id, 'orden' => 2, 'nombre' => 'Módulo dos', 'horas' => 800]);
        $this->grupo = $this->grupo($this->programa, 'Mañana', $this->docente(), 'activo', '2026-09-01',
            [$this->sesion('Lunes', '08:00', '12:00'), $this->sesion('Miércoles', '08:00', '12:00')]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function matricular(string $documento, ?Curso $grupo = null): Matricula
    {
        $grupo ??= $this->grupo;
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento,
            'fecha_nacimiento' => '1998-05-10', 'genero' => 'femenino', 'ciudad_residencia' => 'Villeta']);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));
        $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $grupo->id])->assertOk();
        $m = $e->matriculas()->latest('id')->first();
        $m->update(['plan_pago' => 'mensual']);
        app(PagoService::class)->generarCuotas($m, $grupo->fecha_inicio);

        return $m->fresh();
    }

    private function datos(): array
    {
        return app(ReporteService::class)->datosSede($this->sede->id);
    }

    public function test_la_pagina_trae_solo_los_datos_de_su_sede(): void
    {
        $m = $this->matricular('1070000001');
        $otra = $this->otraSede();
        $this->programa->sedes()->attach($otra->id);
        $ajeno = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-09-01', [], $otra);

        $this->actingAs($this->admin)->get('/admin/reportes')->assertOk()
            ->assertViewHas('datos', fn ($d) => collect($d['matriculas'])->pluck('id')->all() === [$m->id]
                && collect($d['grupos'])->pluck('id')->all() === [$this->grupo->id]
                && ! collect($d['grupos'])->contains('id', $ajeno->id)
                && $d['sede']['nombre'] === $this->sede->nombre);
    }

    public function test_grupo_con_sus_clases_horario_docente_y_fechas_de_modulos(): void
    {
        $g = collect($this->datos()['grupos'])->firstWhere('id', $this->grupo->id);

        $this->assertSame('Lun · Mié  08:00 – 12:00', $g['horario']);
        $this->assertNotNull($g['docente']);
        // Del 1 de septiembre al 21 de octubre: lunes y miércoles dictados (la clase de hoy cuenta cuando termina)
        $this->assertSame('2026-09-02', $g['fechas'][0], 'El 1 de septiembre es martes: la primera clase es el miércoles 2');
        $this->assertTrue(collect($g['fechas'])->every(fn ($f) => $f < '2026-10-21'));
        $this->assertSame(['Módulo uno', 'Módulo dos'], array_column($g['modulos'], 'nombre'));
        $this->assertSame('2026-09-02', $g['modulos'][0]['ini']);
        $this->assertLessThan($g['modulos'][1]['ini'], $g['modulos'][0]['fin']);
    }

    public function test_notas_con_su_fecha_y_cobros_con_sus_pagos_fechados(): void
    {
        $m = $this->matricular('1070000001');
        $modulo = $this->programa->modulos()->orderBy('orden')->first();
        Calificacion::create(['matricula_id' => $m->id, 'programa_modulo_id' => $modulo->id, 'curso_id' => $this->grupo->id, 'nota' => 2.5,
            'fecha_nota' => '2026-10-05', 'habilitacion' => 3.4, 'fecha_habilitacion' => '2026-10-15']);
        app(PagoService::class)->registrarPago($m, 150000, ['fecha_pago' => '2026-09-20', 'metodo_pago' => 'Efectivo']);

        $d = $this->datos();
        $this->assertSame(['matricula_id' => $m->id, 'modulo_id' => $modulo->id, 'nota' => 2.5, 'fecha' => '2026-10-05', 'hab' => 3.4, 'fecha_hab' => '2026-10-15'], $d['notas'][0]);

        $cuota1 = collect($d['cobros'])->firstWhere('concepto', 'Cuota 1');
        $this->assertSame('2026-09-10', $cuota1['vence']);
        $this->assertSame([['2026-09-20', 150000]], $cuota1['pagos'], 'El pago con su fecha: así se sabe cuánto se debía al cierre de cada mes');
    }

    public function test_un_recibo_anulado_no_cuenta_como_pago(): void
    {
        $m = $this->matricular('1070000001');
        $pagos = app(PagoService::class);
        $r = $pagos->registrarPago($m, 150000, ['fecha_pago' => '2026-09-20', 'metodo_pago' => 'Efectivo']);
        $pagos->anularRecaudo($r, 'Se registró por error');

        $this->assertSame([], collect($this->datos()['cobros'])->firstWhere('concepto', 'Cuota 1')['pagos']);
    }

    public function test_asistencia_por_clase_y_por_matricula(): void
    {
        $m = $this->matricular('1070000001');
        $g = collect($this->datos()['grupos'])->firstWhere('id', $this->grupo->id);
        DB::table('asistencias')->insert([
            ['curso_id' => $this->grupo->id, 'estudiante_id' => $m->estudiante_id, 'fecha' => $g['fechas'][0], 'estado' => 'presente', 'motivo' => null, 'created_at' => now(), 'updated_at' => now()],
            ['curso_id' => $this->grupo->id, 'estudiante_id' => $m->estudiante_id, 'fecha' => $g['fechas'][1], 'estado' => 'excusa', 'motivo' => 'Cita médica', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $a = collect($this->datos()['asistencia'])->firstWhere('matricula_id', $m->id);
        $this->assertSame(count($g['fechas']), strlen($a['cod']), 'Un código por cada clase del grupo');
        $this->assertSame('PE', substr($a['cod'], 0, 2));
        $this->assertSame('-', $a['cod'][2], 'Clase sin registrar');
        $this->assertSame([1 => 'Cita médica'], (array) $a['motivos']);
    }

    public function test_quien_vuelve_con_otra_matricula_no_mezcla_la_asistencia_del_grupo_viejo(): void
    {
        $vieja = $this->matricular('1070000001');
        $g = collect($this->datos()['grupos'])->firstWhere('id', $this->grupo->id);
        DB::table('asistencias')->insert(['curso_id' => $this->grupo->id, 'estudiante_id' => $vieja->estudiante_id, 'fecha' => $g['fechas'][0],
            'estado' => 'ausente', 'motivo' => null, 'created_at' => now(), 'updated_at' => now()]);
        $vieja->update(['estado' => 'retirado', 'estado_fecha' => '2026-10-01', 'estado_motivo' => 'Cambio de jornada']);
        $nuevo = $this->grupo($this->programa, 'Mañana', null, 'planificacion', '2026-11-03', [$this->sesion('Martes', '08:00', '12:00')]);
        $nueva = Matricula::create(['codigo' => 'EST-2', 'estudiante_id' => $vieja->estudiante_id, 'programa_id' => $this->programa->id, 'curso_id' => $nuevo->id,
            'sede_id' => $this->sede->id, 'estado' => 'activo', 'fecha_inicio' => '2026-10-15']);

        $d = $this->datos();
        $this->assertNotNull(collect($d['asistencia'])->firstWhere('matricula_id', $vieja->id));
        $this->assertNull(collect($d['asistencia'])->firstWhere('matricula_id', $nueva->id), 'El grupo nuevo no tiene clases: no hereda la asistencia del viejo');
        $this->assertSame(['retirado', '2026-10-01', 'Cambio de jornada'], [
            collect($d['matriculas'])->firstWhere('id', $vieja->id)['estado'],
            collect($d['matriculas'])->firstWhere('id', $vieja->id)['estado_fecha'],
            collect($d['matriculas'])->firstWhere('id', $vieja->id)['motivo'],
        ]);
    }

    public function test_solo_el_admin_entra(): void
    {
        $this->get('/admin/reportes')->assertRedirect(route('login', absolute: false));
        $this->actingAs($this->docente())->get('/admin/reportes')->assertForbidden();
    }
}
