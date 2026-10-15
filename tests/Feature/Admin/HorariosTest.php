<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas del horario de un grupo (Admin › Horarios) y las que lo protegen
 * desde Grupos, Docentes y Programas: jornada, tope de horas y cruces de docente.
 */
class HorariosTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();
    }

    /* ── Horarios: jornada ── */

    public function test_guarda_un_horario_dentro_de_la_jornada(): void
    {
        $docente = $this->docente();
        $grupo   = $this->grupo($this->programa(), 'Tarde');

        $this->guardarHorario($grupo, $docente, [$this->sesion('Lunes', '14:00', '18:00'), $this->sesion('Miércoles', '14:00', '18:00')])
            ->assertOk();

        $this->assertSame(2, $grupo->sesiones()->count());
        $this->assertSame($docente->id, $grupo->fresh()->docente()->id);
        $this->assertDatabaseHas('auditorias', ['accion' => 'crear', 'entidad_tipo' => 'Curso', 'entidad_id' => $grupo->id]);
    }

    public function test_rechaza_horas_por_fuera_de_la_jornada_del_grupo(): void
    {
        $grupo = $this->grupo($this->programa(), 'Tarde');

        $this->guardarHorario($grupo, $this->docente(), [$this->sesion('Lunes', '08:00', '11:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sesiones' => 'jornada tarde']);

        $this->assertSame(0, $grupo->sesiones()->count());
    }

    public function test_la_jornada_de_fines_de_semana_solo_admite_el_sabado(): void
    {
        $grupo = $this->grupo($this->programa(['Fines de semana']), 'Fines de semana');

        $this->guardarHorario($grupo, $this->docente(), [$this->sesion('Lunes', '08:00', '12:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sesiones' => 'solo puede tener clase el sábado']);

        $this->guardarHorario($grupo, $this->docente(), [$this->sesion('Sábado', '08:00', '14:00')])
            ->assertOk();
    }

    public function test_la_jornada_noche_llega_hasta_la_medianoche(): void
    {
        $grupo = $this->grupo($this->programa(['Noche']), 'Noche');

        $this->guardarHorario($grupo, $this->docente(), [$this->sesion('Martes', '20:00', '24:00')])
            ->assertOk();

        $this->assertSame('24:00', $grupo->sesiones()->first()->fin());
    }

    public function test_rechaza_hora_fin_anterior_a_la_de_inicio(): void
    {
        $grupo = $this->grupo($this->programa(), 'Tarde');

        $this->guardarHorario($grupo, $this->docente(), [$this->sesion('Lunes', '16:00', '14:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sesiones.0.hora_fin']);
    }

    /* ── Horarios: tope de horas del programa ── */

    public function test_rechaza_un_horario_con_mas_horas_que_el_programa(): void
    {
        // 100 horas en 12 meses y se piden 12 h por semana (≈ 600 h)
        $grupo = $this->grupo($this->programa(['Tarde'], horas: 100), 'Tarde');

        $this->guardarHorario($grupo, $this->docente(), [
            $this->sesion('Lunes', '14:00', '18:00'), $this->sesion('Martes', '14:00', '18:00'), $this->sesion('Miércoles', '14:00', '18:00'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sesiones' => 'el programa tiene 100 horas']);
    }

    /* ── Horarios: cruces de docente ── */

    public function test_un_docente_no_puede_tener_dos_clases_a_la_misma_hora(): void
    {
        $docente = $this->docente();
        $otro    = $this->grupo($this->programa(), 'Tarde', docente: $docente, sesiones: [$this->sesion('Lunes', '14:00', '18:00')]);
        $grupo   = $this->grupo($this->programa(), 'Tarde');

        $this->guardarHorario($grupo, $docente, [$this->sesion('Lunes', '16:00', '18:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['docente_id' => $otro->codigo]);
    }

    public function test_el_cruce_aplica_aunque_los_grupos_sean_de_programas_distintos(): void
    {
        $docente = $this->docente();
        $this->grupo($this->programa(), 'Tarde', docente: $docente, sesiones: [$this->sesion('Jueves', '12:00', '16:00')]);
        $grupo = $this->grupo($this->programa(), 'Tarde');

        $this->guardarHorario($grupo, $docente, [$this->sesion('Jueves', '15:00', '18:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['docente_id']);
    }

    public function test_permite_clases_seguidas_del_mismo_docente(): void
    {
        $docente = $this->docente();
        $this->grupo($this->programa(), 'Tarde', docente: $docente, sesiones: [$this->sesion('Lunes', '12:00', '14:00')]);
        $grupo = $this->grupo($this->programa(), 'Tarde');

        // Termina una a las 14:00 y empieza la otra a las 14:00: no se cruzan
        $this->guardarHorario($grupo, $docente, [$this->sesion('Lunes', '14:00', '18:00')])
            ->assertOk();
    }

    public function test_no_hay_cruce_si_las_fechas_de_los_grupos_no_coinciden(): void
    {
        $docente = $this->docente();
        // Grupo de 12 meses que ya terminó antes de que empiece el nuevo
        $this->grupo($this->programa(), 'Tarde', docente: $docente, inicio: '2024-01-15', sesiones: [$this->sesion('Lunes', '14:00', '18:00')]);
        $grupo = $this->grupo($this->programa(), 'Tarde', inicio: '2026-02-02');

        $this->guardarHorario($grupo, $docente, [$this->sesion('Lunes', '14:00', '18:00')])
            ->assertOk();
    }

    /* ── Horarios: docente y grupo válidos ── */

    public function test_no_se_puede_asignar_un_docente_inactivo(): void
    {
        $grupo = $this->grupo($this->programa(), 'Tarde');

        $this->guardarHorario($grupo, $this->docente('inactivo'), [$this->sesion('Lunes', '14:00', '18:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['docente_id' => 'activo']);
    }

    public function test_no_se_puede_asignar_un_docente_de_otra_sede(): void
    {
        $otraSede = $this->otraSede();
        $grupo    = $this->grupo($this->programa(), 'Tarde');

        $this->guardarHorario($grupo, $this->docente(sede: $otraSede), [$this->sesion('Lunes', '14:00', '18:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['docente_id' => 'de tu sede']);
    }

    public function test_no_se_puede_editar_el_horario_de_un_grupo_de_otra_sede(): void
    {
        $otraSede = $this->otraSede();
        $grupo    = $this->grupo($this->programa(), 'Tarde', sede: $otraSede);

        $this->guardarHorario($grupo, $this->docente(), [$this->sesion('Lunes', '14:00', '18:00')])
            ->assertForbidden();
    }

    public function test_no_se_puede_editar_el_horario_de_un_grupo_finalizado(): void
    {
        $grupo = $this->grupo($this->programa(), 'Tarde', estado: 'finalizado');

        $this->guardarHorario($grupo, $this->docente(), [$this->sesion('Lunes', '14:00', '18:00')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sesiones' => 'finalizado']);
    }

    public function test_la_pantalla_de_horarios_carga(): void
    {
        $this->grupo($this->programa(), 'Tarde', docente: $this->docente(), sesiones: [$this->sesion('Viernes', '14:00', '18:00'), $this->sesion('Lunes', '14:00', '18:00')]);

        $this->actingAs($this->admin)->get('/admin/horarios')->assertOk()->assertSee('Horarios');
    }

    /* ── Grupos: lo que cambia en el grupo no puede romper su horario ── */

    public function test_grupos_no_deja_cambiar_la_jornada_si_el_horario_queda_por_fuera(): void
    {
        $docente  = $this->docente();
        $programa = $this->programa(['Mañana', 'Tarde']);
        $grupo    = $this->grupo($programa, 'Tarde', docente: $docente, sesiones: [$this->sesion('Lunes', '14:00', '18:00')]);

        $this->actingAs($this->admin)->putJson("/admin/cursos/{$grupo->id}", [
            'programa_id'  => $programa->id,
            'docente_id'   => $docente->id,
            'jornada'      => 'Mañana',
            'fecha_inicio' => $grupo->fecha_inicio->toDateString(),
            'estado'       => 'activo',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jornada' => 'no cabe en esa jornada']);

        $this->assertSame('Tarde', $grupo->fresh()->jornada);
    }

    public function test_grupos_no_deja_reabrir_un_grupo_cuyo_horario_se_cruza(): void
    {
        $docente = $this->docente();
        $this->grupo($this->programa(), 'Tarde', docente: $docente, sesiones: [$this->sesion('Martes', '14:00', '18:00')]);
        // Mientras estuvo finalizado, el docente quedó con otra clase a esa hora
        $finalizado = $this->grupo($this->programa(), 'Tarde', docente: $docente, estado: 'finalizado', sesiones: [$this->sesion('Martes', '14:00', '18:00')]);

        $this->actingAs($this->admin)->patchJson("/admin/cursos/{$finalizado->id}/estado")
            ->assertUnprocessable()
            ->assertJsonFragment(['ok' => false]);

        $this->assertSame('finalizado', $finalizado->fresh()->estado);
    }

    public function test_grupos_no_deja_activar_un_grupo_con_docente_inactivo(): void
    {
        $grupo = $this->grupo($this->programa(), 'Tarde', docente: $this->docente('inactivo'), estado: 'planificacion');

        $this->actingAs($this->admin)->patchJson("/admin/cursos/{$grupo->id}/estado")
            ->assertUnprocessable();

        $this->assertSame('planificacion', $grupo->fresh()->estado);
    }

    /* ── Docentes: no se desactiva a quien tiene grupos vigentes ── */

    public function test_no_se_puede_desactivar_un_docente_con_grupos_a_cargo(): void
    {
        $docente = $this->docente();
        $grupo   = $this->grupo($this->programa(), 'Tarde', docente: $docente);

        $respuesta = $this->actingAs($this->admin)->patchJson("/admin/docentes/{$docente->id}/estado")
            ->assertUnprocessable()
            ->assertJsonFragment(['ok' => false]);

        // El mensaje dice qué grupos debe reasignar
        $this->assertStringContainsString($grupo->codigo, $respuesta->json('message'));
        $this->assertSame('activo', $docente->docentePerfil()->first()->estado);
    }

    public function test_si_se_puede_desactivar_un_docente_sin_grupos(): void
    {
        $docente = $this->docente();

        $this->actingAs($this->admin)->patchJson("/admin/docentes/{$docente->id}/estado")->assertOk();

        $this->assertSame('inactivo', $docente->docentePerfil()->first()->estado);
    }

    /* ── Programas: cambiar la duración no puede crear cruces ── */

    public function test_programas_no_deja_alargar_la_duracion_si_crea_un_cruce_de_docente(): void
    {
        $docente  = $this->docente();
        $programa = $this->programa(['Tarde'], meses: 6);
        // Grupo de 6 meses (feb–ago) y, del mismo docente y a la misma hora, otro que empieza en septiembre
        $this->grupo($programa, 'Tarde', docente: $docente, inicio: '2026-02-02', sesiones: [$this->sesion('Lunes', '14:00', '18:00')]);
        $this->grupo($this->programa(['Tarde']), 'Tarde', docente: $docente, inicio: '2026-09-07', sesiones: [$this->sesion('Lunes', '14:00', '18:00')]);

        // A 12 meses, el primero llegaría hasta febrero del año siguiente y chocaría con el segundo
        $this->actingAs($this->admin)->putJson("/admin/programas/{$programa->id}", $this->datosPrograma($programa, ['duracion_meses' => 12]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duracion_meses' => 'se cruza']);

        $this->assertSame(6, $programa->fresh()->duracion_meses);
    }

    public function test_programas_no_deja_quitar_una_jornada_que_usa_un_grupo(): void
    {
        $programa = $this->programa(['Mañana', 'Tarde']);
        $this->grupo($programa, 'Tarde');

        $this->actingAs($this->admin)->putJson("/admin/programas/{$programa->id}", $this->datosPrograma($programa, ['jornadas' => ['Mañana']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jornadas']);
    }

    /* ── Ayudantes ── */

    private function guardarHorario(Curso $grupo, User $docente, array $sesiones)
    {
        return $this->actingAs($this->admin)->putJson("/admin/horarios/{$grupo->id}", [
            'docente_id' => $docente->id,
            'sesiones'   => $sesiones,
        ]);
    }

    private function datosPrograma(Programa $p, array $cambios): array
    {
        return array_merge([
            'nombre'         => $p->nombre,
            'codigo'         => $p->codigo,
            'nivel'          => $p->nivel,
            'escuela_id'     => $p->escuela_id,
            'modalidad'      => $p->modalidad,
            'horas'          => $p->horas,
            'jornadas'       => $p->jornadas,
            'cupo_grupo'     => $p->cupo_grupo,
            'duracion_meses' => $p->duracion_meses,
            'tipo_periodo'   => $p->tipo_periodo,
            'estado'         => $p->estado,
            'precio_total'   => (float) $p->precio_total,
            'matricula'      => (float) $p->matricula,
        ], $cambios);
    }
}
