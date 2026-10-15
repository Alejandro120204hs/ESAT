<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Academico\AsistenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Panel Admin › Asistencia (solo consulta): clases dictadas según el horario, clases sin registrar,
 * porcentaje (tarde cuenta como asistió, la excusa no cuenta), aplazados y la página por sede.
 */
class AsistenciaTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles 14 de octubre, 9 de la mañana
        Carbon::setTestNow('2026-10-14 09:00:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana']);
        $this->docente  = $this->docente();
        // Lunes y miércoles de 7 a 10, desde el lunes 5 de octubre: clases el 5, 7, 12 y (hoy) 14
        $this->grupo = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-10-05', [
            $this->sesion('Lunes', '07:00', '10:00'),
            $this->sesion('Miércoles', '07:00', '10:00'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_las_clases_son_los_dias_del_horario_y_la_de_hoy_cuenta_cuando_termina(): void
    {
        $servicio = app(AsistenciaService::class);
        $grupo = $this->grupo->load('sesiones', 'programa');

        $this->assertSame(['2026-10-05', '2026-10-07', '2026-10-12'], $servicio->fechasDictadas($grupo));
        $this->assertSame(['2026-10-05', '2026-10-07', '2026-10-12', '2026-10-14'], $servicio->fechasDictadas($grupo, Carbon::parse('2026-10-14 10:30')));
    }

    public function test_tarde_cuenta_como_asistio_y_la_excusa_no_cuenta_como_falla(): void
    {
        $e = $this->matriculado('1070000001');
        $this->registrar($e, ['2026-10-05' => 'tarde', '2026-10-07' => 'excusa', '2026-10-12' => 'ausente']);

        $fila = $this->resumen()['estudiantes']->firstWhere('id', $e->id);

        $this->assertSame('TEA', $fila['cod']);
        $this->assertSame(50, $fila['pct']);            // 1 de 2: la excusa no entra en la cuenta
        $this->assertSame('fallas', $fila['situacion']);
        $this->assertSame(50, AsistenciaService::porcentaje(1, 1));
    }

    public function test_una_clase_sin_registrar_no_cuenta_y_aparece_como_pendiente_del_docente(): void
    {
        $e = $this->matriculado('1070000001');
        $this->registrar($e, ['2026-10-05' => 'presente', '2026-10-07' => 'presente']);

        $r = $this->resumen();
        $grupo = $r['grupos']->firstWhere('id', $this->grupo->id);

        $this->assertSame(['2026-10-12'], $grupo['sin_tomar']);
        $this->assertSame(2, $grupo['tomadas']);
        $this->assertSame(100, $grupo['pct']);
        $this->assertSame('PP-', $r['estudiantes']->firstWhere('id', $e->id)['cod']);
        $this->assertSame(1, $r['docentes']->firstWhere('id', $this->docente->id)['sin_tomar']);
        $this->assertSame(1, $r['resumen']['sin_tomar']);
        $this->assertSame('pendiente', $grupo['hoy']);  // la clase de hoy todavía no termina
    }

    public function test_de_un_aplazado_no_cuentan_las_clases_despues_de_aplazarse(): void
    {
        $activo   = $this->matriculado('1070000001');
        $aplazado = $this->matriculado('1070000002', 'aplazado', '2026-10-07');
        $this->registrar($activo, ['2026-10-05' => 'presente', '2026-10-07' => 'presente', '2026-10-12' => 'presente']);
        $this->registrar($aplazado, ['2026-10-05' => 'ausente']);

        $r = $this->resumen();
        $fila = $r['estudiantes']->firstWhere('id', $aplazado->id);

        $this->assertSame('A..', $fila['cod']);
        $this->assertSame('aplazado', $fila['estado']);
        // El promedio del grupo y el resumen de la sede solo cuentan a los activos
        $this->assertSame(100, $r['grupos']->firstWhere('id', $this->grupo->id)['pct']);
        $this->assertSame(1, $r['grupos']->firstWhere('id', $this->grupo->id)['activos']);
        $this->assertSame(0, $r['resumen']['bajo_minimo']);
    }

    public function test_cuenta_las_faltas_seguidas_de_las_ultimas_clases(): void
    {
        $e = $this->matriculado('1070000001');
        $this->registrar($e, ['2026-10-05' => 'presente', '2026-10-07' => 'ausente', '2026-10-12' => 'ausente']);

        $fila = $this->resumen()['estudiantes']->firstWhere('id', $e->id);

        $this->assertSame(2, $fila['racha']);
        $this->assertSame('2026-10-12', $fila['ultima_falla']);
    }

    public function test_la_pagina_muestra_solo_los_grupos_vigentes_de_la_sede(): void
    {
        $this->matriculado('1070000001');
        $otraSede = $this->otraSede();
        $ajeno = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-10-05', [$this->sesion('Martes', '07:00', '10:00')], $otraSede);
        $terminado = $this->grupo($this->programa, 'Mañana', null, 'finalizado', '2025-01-13', [$this->sesion('Martes', '07:00', '10:00')]);

        $r = $this->actingAs($this->admin)->get('/admin/asistencia')->assertOk()->assertSee($this->grupo->programa->nombre);
        $ids = collect($r->viewData('grupos'))->pluck('id');

        $this->assertTrue($ids->contains($this->grupo->id));
        $this->assertFalse($ids->contains($ajeno->id));
        $this->assertFalse($ids->contains($terminado->id));
    }

    public function test_solo_el_admin_entra_al_panel(): void
    {
        $this->actingAs($this->docente)->get('/admin/asistencia')->assertForbidden();
    }

    public function test_el_porcentaje_de_la_ficha_usa_la_misma_regla(): void
    {
        $e = $this->matriculado('1070000001');
        $this->registrar($e, ['2026-10-05' => 'tarde', '2026-10-07' => 'excusa', '2026-10-12' => 'ausente']);

        $e->load('asistencias');
        $this->assertSame(50, app(\App\Services\Academico\EstudianteService::class)->porcentajeAsistencia($e, $this->grupo));
    }

    /* ── Ayudas ── */

    private function resumen(): array
    {
        return app(AsistenciaService::class)->resumenSede($this->sede->id);
    }

    private function matriculado(string $documento, string $estado = 'activo', string $desde = '2026-10-01'): User
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento]);
        Matricula::create([
            'codigo'        => 'MAT-'.$documento,
            'estudiante_id' => $e->id,
            'programa_id'   => $this->programa->id,
            'curso_id'      => $this->grupo->id,
            'sede_id'       => $this->sede->id,
            'estado'        => $estado,
            'estado_fecha'  => $estado === 'activo' ? '2026-10-01' : $desde,
            'fecha_inicio'  => '2026-10-01',
        ]);
        $this->grupo->estudiantes()->attach($e->id);

        return $e;
    }

    private function registrar(User $e, array $porFecha): void
    {
        foreach ($porFecha as $fecha => $estado) {
            Asistencia::create([
                'curso_id' => $this->grupo->id, 'estudiante_id' => $e->id, 'fecha' => $fecha,
                'estado' => $estado, 'motivo' => $estado === 'excusa' ? 'Incapacidad médica' : null,
                'registrado_por' => $this->docente->id,
            ]);
        }
    }
}
