<?php

namespace Tests\Feature\Estudiante;

use App\Models\Academico\Actividad;
use App\Models\Academico\Curso;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Material;
use App\Models\Academico\Programa;
use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoTema;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Academico\AulaService;
use App\Services\Academico\MatriculaService;
use App\Services\Finanzas\PagoService;
use App\Services\Sistema\BloqueoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Auditoría del panel Estudiante (27-11-2026, HALLAZGOS §11): una prueba por cada hallazgo alto y medio corregido.
 * Lo que vive solo en el navegador (ES3 Inicio sin horario, ES6–ES8 reloj y marcas) se comprobó en el navegador.
 */
class AuditoriaEstudianteTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles 21 de octubre, 9:00 a. m.
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        // 3 meses, 3 módulos (lunes y miércoles, 8 h a la semana)
        $this->programa = $this->programa(['Mañana'], 400, 3);
        $this->programa->modulos()->createMany([
            ['nombre' => 'Fundamentos', 'horas' => 100, 'orden' => 1],
            ['nombre' => 'Normativa', 'horas' => 100, 'orden' => 2],
            ['nombre' => 'Práctica', 'horas' => 200, 'orden' => 3],
        ]);
        $this->docente = $this->docente();
        $this->grupo = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', $this->horario());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ── ES1 y ES2: las respuestas correctas ── */

    public function test_es1_las_correctas_no_salen_mientras_alguien_todavia_puede_enviar(): void
    {
        $laura = $this->matriculada();
        $ev = $this->publicar(['duracion' => 30, 'cierra' => '2026-10-21 09:20']);
        $this->actingAs($laura)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertOk();

        // Ya cerró, pero está dentro de la gracia de su envío automático: no ve las correctas y su envío se recibe
        Carbon::setTestNow('2026-10-21 09:20:30');
        $this->actingAs($laura)->get('/estudiante/aula')->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['verCorrectas'] === false
            && collect($d['evaluaciones'][0]['preguntas'])->pluck('correcta')->filter()->isEmpty());
        $this->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => [$ev->preguntas[0]->id => $this->opcion($ev, 0, false)]])->assertOk();

        // Pasada la gracia nadie puede enviar: ahora sí salen
        Carbon::setTestNow('2026-10-21 09:21:01');
        $this->actingAs($laura)->get('/estudiante/aula')->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['verCorrectas'] === true
            && collect($d['evaluaciones'][0]['preguntas'])->pluck('correcta')->filter()->count() === 2);
    }

    public function test_es2_una_evaluacion_que_ya_cerro_no_se_reabre(): void
    {
        $ev = $this->publicar(['cierra' => '2026-10-21 09:30']);
        Carbon::setTestNow('2026-10-21 10:00:00');

        $this->actingAs($this->docente)->putJson("/docente/aula/evaluaciones/{$ev->id}", $this->datos(['cierra' => '2026-10-22 18:00']))
            ->assertJsonValidationErrors(['cierra' => 'su cierre no se cambia']);
        $this->assertSame('2026-10-21 09:30', $ev->fresh()->fecha_limite->format('Y-m-d H:i'));
        // Corregirla conservando el cierre sí se puede
        $this->putJson("/docente/aula/evaluaciones/{$ev->id}", $this->datos(['titulo' => 'Quiz corregido', 'cierra' => '2026-10-21 09:30']))->assertOk();
    }

    /* ── ES4: la sesión abierta ── */

    public function test_es4_las_claves_malas_de_otra_persona_no_cierran_la_sesion_abierta(): void
    {
        $laura = $this->matriculada();
        $this->actingAs($laura)->getJson('/estudiante/pagos')->assertOk();
        for ($i = 0; $i < 5; $i++) {
            app(BloqueoService::class)->registrarFallo($laura->email);
        }
        $this->assertNotNull($laura->fresh()->bloqueado_en);

        // Sigue adentro…
        $this->actingAs($laura->fresh())->getJson('/estudiante/pagos')->assertOk();
        // …pero un ingreso nuevo, aun con la clave correcta, espera el bloqueo
        $this->post('/logout');
        $this->post('/login', ['email' => $laura->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /* ── ES5: la deuda de otra matrícula ── */

    public function test_es5_mis_pagos_muestra_lo_que_debe_en_otra_matricula(): void
    {
        $laura = $this->matriculada();
        $vieja = Matricula::where('estudiante_id', $laura->id)->firstOrFail();
        app(PagoService::class)->elegirPlan($vieja, 'mensual');
        Carbon::setTestNow('2026-10-15 09:00:00');
        app(MatriculaService::class)->cambiarEstado($vieja->fresh(), 'retirado', Carbon::parse('2026-10-15'), 'Se fue');
        $debe = app(PagoService::class)->resumen($vieja->fresh()->load('pagos', 'recaudos'))['saldo_vencido'];
        $this->assertGreaterThan(0, $debe);

        // Vuelve con otra matrícula en un grupo nuevo
        Carbon::setTestNow('2026-10-21 09:00:00');
        $nuevo = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-10-26', $this->horario());
        app(MatriculaService::class)->matricular($laura->fresh(), $nuevo, null);

        $this->actingAs($laura)->get('/estudiante/pagos')->assertViewHas('datos', fn (array $d) => count($d['pagos']['otras']) === 1
            && $d['pagos']['otras'][0]['vencido'] === $debe && $d['pagos']['otras'][0]['estado'] === 'retirado'
            && $d['pagos']['otras'][0]['programa'] === $this->programa->nombre);
    }

    /* ── ES9 y ES10: aplazado ── */

    public function test_es9_aplazado_ve_su_aula_como_consulta_y_no_puede_responder(): void
    {
        $laura = $this->matriculada();
        $ev = $this->publicar();
        $this->actividad();
        app(MatriculaService::class)->cambiarEstado(Matricula::where('estudiante_id', $laura->id)->firstOrFail(), 'aplazado', Carbon::parse('2026-10-21'), 'Viaje');

        $this->actingAs($laura)->get('/estudiante/aula')->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['participa'] === false
            && $d['actividades'][0]['participa'] === false && str_contains($d['aulaMotivo'], 'aplazada'));
        // El servidor lo sigue impidiendo (RespuestasRequest exige la matrícula activa en el grupo)
        $this->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $this->marcas($ev)])->assertForbidden();
        $this->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertForbidden();
    }

    public function test_lo_que_estuvo_abierto_solo_mientras_estaba_aplazado_no_le_toco(): void
    {
        $laura = $this->matriculada();
        Carbon::setTestNow('2026-10-20 09:00:00');
        $antes = $this->publicar(['titulo' => 'Antes de aplazarse', 'cierra' => '2026-10-22 18:00']);
        // Se aplaza el 21: desde ese día ya no cuenta en el grupo
        Carbon::setTestNow('2026-10-21 09:00:00');
        app(MatriculaService::class)->cambiarEstado(Matricula::where('estudiante_id', $laura->id)->firstOrFail(), 'aplazado', Carbon::parse('2026-10-21'), 'Viaje');
        Carbon::setTestNow('2026-10-22 09:00:00');
        $despues = $this->publicar(['titulo' => 'Después de aplazarse', 'cierra' => '2026-10-22 18:00']);
        Carbon::setTestNow('2026-10-23 09:00:00');

        // La de antes pudo hacerla (no respondida); la que abrió y cerró con ella aplazada no le tocaba
        $this->actingAs($laura)->get('/estudiante/aula')->assertViewHas('datos', function (array $d) use ($antes, $despues) {
            $ev = collect($d['evaluaciones'])->keyBy('id');

            return $ev[$antes->id]['leToco'] === true && $ev[$despues->id]['leToco'] === false;
        });
    }

    public function test_es10_lo_que_corrio_mientras_estaba_aplazado_no_lo_curso(): void
    {
        $laura = $this->matriculada();
        Carbon::setTestNow('2026-08-20 09:00:00');
        app(MatriculaService::class)->cambiarEstado(Matricula::where('estudiante_id', $laura->id)->firstOrFail(), 'aplazado', Carbon::parse('2026-08-20'), 'Viaje');
        Carbon::setTestNow('2026-10-21 09:00:00');

        $this->actingAs($laura)->get('/estudiante/programa')->assertViewHas('datos', function (array $d) {
            $mods = collect($d['modulos']);

            // El primero terminó con ella adentro: le falta la nota. Los otros dos corrieron (o corren) mientras estaba aplazada
            return $mods[0]['estadoNota'] === 'sin_nota' && $mods[1]['estadoNota'] === 'no_cursado' && $mods[2]['estadoNota'] === 'no_cursado';
        });
    }

    public function test_es10_aplazado_antes_de_que_empiece_el_grupo_no_curso_ningun_modulo(): void
    {
        // Se matricula antes de que el grupo empiece y se aplaza antes de la primera clase (reauditoría, N3)
        $tarde = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', $this->horario());
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        Carbon::setTestNow('2026-07-20 09:00:00');
        $m = app(MatriculaService::class)->matricular($e, $tarde, null);
        Carbon::setTestNow('2026-07-25 09:00:00');
        app(MatriculaService::class)->cambiarEstado($m->fresh(), 'aplazado', Carbon::parse('2026-07-25'), 'Viaje');
        Carbon::setTestNow('2026-10-21 09:00:00');

        $this->actingAs($e)->get('/estudiante/programa')->assertViewHas('datos',
            fn (array $d) => collect($d['modulos'])->pluck('estadoNota')->unique()->values()->all() === ['no_cursado']);
    }

    public function test_n6_una_evaluacion_que_alguien_ya_comenzo_no_se_elimina(): void
    {
        $laura = $this->matriculada();
        $ev = $this->publicar(['duracion' => 30]);
        $this->actingAs($laura)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertOk();

        $this->actingAs($this->docente)->deleteJson("/docente/aula/evaluaciones/{$ev->id}")->assertStatus(422);
        $this->assertNotNull($ev->fresh());
    }

    /* ── ES11: el plan después de un reintegro ── */

    public function test_es11_el_plan_elegido_despues_de_un_reintegro_cobra_igual_que_si_lo_hubiera_elegido_antes(): void
    {
        $antes = $this->matriculada();
        $despues = $this->matriculada();
        $ma = Matricula::where('estudiante_id', $antes->id)->firstOrFail();
        $md = Matricula::where('estudiante_id', $despues->id)->firstOrFail();
        // Una eligió el plan el primer día
        Carbon::setTestNow('2026-08-03 08:00:00');
        app(PagoService::class)->elegirPlan($ma, 'mensual');

        // Las dos se aplazan el 20 de agosto y vuelven el 5 de octubre a un grupo que empieza el 12
        $grupo2 = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-10-12', $this->horario());
        foreach ([$ma, $md] as $m) {
            Carbon::setTestNow('2026-08-20 09:00:00');
            app(MatriculaService::class)->cambiarEstado($m->fresh(), 'aplazado', Carbon::parse('2026-08-20'), 'Viaje');
            Carbon::setTestNow('2026-10-05 09:00:00');
            app(MatriculaService::class)->cambiarEstado($m->fresh(), 'activo', Carbon::parse('2026-10-05'), 'Vuelve', $grupo2);
        }
        // La otra lo elige después de volver
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->actingAs($despues)->get('/estudiante/pagos')->assertViewHas('datos', fn (array $d) => $d['pagos']['vencidasSiElige']['mensual'] === 1);
        $this->postJson('/estudiante/pagos/plan', ['plan' => 'mensual'])->assertOk();

        $fechas = fn (Matricula $m) => $m->fresh()->pagos()->where('tipo', 'mensual')->where('estado', '!=', 'anulado')
            ->orderBy('fecha_vencimiento')->pluck('fecha_vencimiento')->map(fn ($f) => Carbon::parse($f)->toDateString())->all();
        // Agosto (antes de aplazarse) y desde el reintegro: nada de los meses en que estuvo aplazada
        $this->assertSame(['2026-08-10', '2026-11-10', '2026-12-10'], $fechas($md));
        $this->assertSame($fechas($ma), $fechas($md));
    }

    /* ── ES12: el grupo anterior ── */

    public function test_es12_tras_cambiar_de_grupo_conserva_el_aula_y_el_material_del_grupo_anterior(): void
    {
        Storage::fake('local');
        $laura = $this->matriculada();
        $this->publicar();
        Storage::disk('local')->put('aula/guia.pdf', '%PDF-1.4');
        $material = Material::create(['curso_id' => $this->grupo->id, 'docente_id' => $this->docente->id, 'titulo' => 'Guía 1',
            'archivo_path' => 'aula/guia.pdf', 'archivo_nombre' => 'guia.pdf', 'archivo_tamano' => 8]);

        $grupo2 = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', $this->horario());
        app(MatriculaService::class)->cambiarGrupo(Matricula::where('estudiante_id', $laura->id)->firstOrFail(), $grupo2);

        $this->actingAs($laura)->get('/estudiante/aula')->assertViewHas('datos', fn (array $d) => count($d['evaluaciones']) === 1
            && $d['evaluaciones'][0]['participa'] === false && $d['evaluaciones'][0]['grupo'] === $this->grupo->nombre
            && count($d['materiales']) === 1);
        $this->get("/estudiante/aula/materiales/{$material->id}/archivo")->assertOk();
    }

    /* ── ES13: la entrega anterior ── */

    public function test_es13_si_la_peticion_se_deshace_la_entrega_anterior_no_se_pierde(): void
    {
        Storage::fake('local');
        $laura = $this->matriculada();
        $act = $this->actividad();
        $this->actingAs($laura)->post("/estudiante/aula/actividades/{$act->id}/entrega", ['archivo' => UploadedFile::fake()->create('uno.pdf', 20, 'application/pdf')])->assertOk();
        $primero = $act->entregas()->firstOrFail()->archivo_path;

        // La petición del cambio falla después de guardar (se deshace todo): el archivo anterior sigue ahí
        DB::beginTransaction();
        app(AulaService::class)->entregar($laura, $act, UploadedFile::fake()->create('dos.pdf', 20, 'application/pdf'));
        DB::rollBack();
        Storage::disk('local')->assertExists($primero);
        $this->assertSame($primero, $act->entregas()->firstOrFail()->archivo_path);
        // …y el archivo nuevo de la petición deshecha no queda huérfano (reauditoría, N5)
        $this->assertSame([$primero], Storage::disk('local')->allFiles(dirname($primero)));

        // Cuando sí confirma, el anterior se borra
        $this->post("/estudiante/aula/actividades/{$act->id}/entrega", ['archivo' => UploadedFile::fake()->create('tres.pdf', 20, 'application/pdf')])->assertOk();
        Storage::disk('local')->assertMissing($primero);
    }

    /* ── ES14 y ES15: límites y rendimiento ── */

    public function test_es14_publicar_en_el_foro_tiene_limite_de_frecuencia(): void
    {
        $laura = $this->matriculada();
        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($laura)->postJson('/estudiante/foro/temas', ['categoria' => 'vida', 'titulo' => "Pregunta número $i", 'texto' => 'Hola a todos.'])->assertOk();
        }
        $this->postJson('/estudiante/foro/temas', ['categoria' => 'vida', 'titulo' => 'Pregunta número 11', 'texto' => 'Hola a todos.'])->assertStatus(429);
        $this->assertSame(10, ForoTema::count());
    }

    public function test_es15_el_foro_no_hace_una_consulta_por_tema_y_solo_viaja_a_su_pantalla(): void
    {
        $laura = $this->matriculada();
        $consultas = function (int $temas) use ($laura): int {
            while (ForoTema::count() < $temas) {
                $t = ForoTema::create(['sede_id' => $this->sede->id, 'autor_id' => $this->docente->id, 'categoria' => 'vida', 'titulo' => 'Tema '.ForoTema::count()]);
                ForoMensaje::create(['tema_id' => $t->id, 'autor_id' => $this->docente->id, 'texto' => 'Primer mensaje']);
            }
            $n = 0;
            DB::listen(function () use (&$n) { $n++; });
            $this->actingAs($laura)->get('/estudiante/foro')->assertOk();

            return $n;
        };
        $con3 = $consultas(3);
        $con15 = $consultas(15);
        $this->assertLessThanOrEqual($con3 + 2, $con15, "Con 3 temas: $con3 consultas; con 15: $con15");

        $this->actingAs($laura)->get('/estudiante')->assertViewHas('datos', fn (array $d) => $d['foro'] === null);
        $this->get('/estudiante/foro')->assertViewHas('datos', fn (array $d) => count($d['foro']['temas']) === 15);
    }

    /* ── Ayudas ── */

    private function horario(): array
    {
        return [$this->sesion('Lunes', '07:00', '11:00'), $this->sesion('Miércoles', '07:00', '11:00')];
    }

    /** Un estudiante matriculado el primer día del grupo (con su historial de grupos). */
    private function matriculada(): User
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        $this->alIniciar($this->grupo, fn () => app(MatriculaService::class)->matricular($e, $this->grupo, null));

        return $e;
    }

    private function publicar(array $cambios = []): Evaluacion
    {
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", $this->datos($cambios))->assertOk();

        return Evaluacion::with('preguntas.opciones')->latest('id')->firstOrFail();
    }

    private function actividad(): Actividad
    {
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/actividades", [
            'titulo' => 'Informe de práctica', 'instrucciones' => 'Entrega el informe de la práctica en PDF.',
            'modulo_id' => $this->programa->modulos[2]->id, 'cierra' => '2026-10-25 23:59',
        ])->assertOk();

        return Actividad::latest('id')->firstOrFail();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Quiz de prácticas', 'modulo_id' => $this->programa->modulos[2]->id, 'cierra' => '2026-10-25 23:59', 'duracion' => null,
            'preguntas' => [
                ['enunciado' => '¿Primera pregunta?', 'opciones' => [['texto' => 'Correcta', 'correcta' => true], ['texto' => 'Mala', 'correcta' => false]]],
                ['enunciado' => '¿Segunda pregunta?', 'opciones' => [['texto' => 'Correcta', 'correcta' => true], ['texto' => 'Mala', 'correcta' => false]]],
            ],
        ], $cambios);
    }

    private function opcion(Evaluacion $ev, int $i, bool $correcta): int
    {
        return $ev->preguntas[$i]->opciones->firstWhere('es_correcta', $correcta)->id;
    }

    private function marcas(Evaluacion $ev): array
    {
        return $ev->preguntas->mapWithKeys(fn ($p) => [$p->id => $p->opciones->firstWhere('es_correcta', true)->id])->all();
    }
}
