<?php

namespace Tests\Feature\Estudiante;

use App\Models\Academico\Actividad;
use App\Models\Academico\Asistencia;
use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\Entrega;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Programa;
use App\Models\Comunicacion\Comunicado;
use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoReporte;
use App\Models\Comunicacion\ForoTema;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Panel Estudiante con datos reales (24-11-2026): lo que ve (PanelEstudianteService), el plan de pago que elige él o la
 * secretaría (desde el inicio de clases), los comunicados que lee, el foro de su sede y el Aula virtual (el docente
 * publica y califica, el estudiante responde una vez y entrega hasta la fecha límite).
 */
class BackendEstudianteTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupo;
    private Matricula $laura;
    private Matricula $juan;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles 21 de octubre, 9:00 a. m.
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        // 3 meses, 3 módulos: el 1 termina el 19 ago, el 2 el 14 sep, el 3 va en curso (como en RegistroDocenteTest)
        $this->programa = $this->programa(['Mañana'], 400, 3);
        $this->programa->modulos()->createMany([
            ['nombre' => 'Fundamentos', 'horas' => 100, 'orden' => 1],
            ['nombre' => 'Normativa', 'horas' => 100, 'orden' => 2],
            ['nombre' => 'Práctica', 'horas' => 200, 'orden' => 3],
        ]);
        $this->docente = $this->docente();
        $this->grupo = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', [
            $this->sesion('Lunes', '07:00', '11:00'),
            $this->sesion('Miércoles', '07:00', '11:00'),
        ]);
        $this->laura = $this->matricula('Laura', 'Ruiz');
        $this->juan  = $this->matricula('Juan', 'Gómez');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ───────────── Lo que ve ───────────── */

    public function test_ve_su_programa_sus_notas_y_su_asistencia_con_las_reglas_de_siempre(): void
    {
        $m1 = $this->programa->modulos[0];
        Calificacion::create(['matricula_id' => $this->laura->id, 'programa_modulo_id' => $m1->id, 'curso_id' => $this->grupo->id, 'nota' => 4.5, 'fecha_nota' => '2026-08-20', 'registrado_por' => $this->docente->id]);
        foreach (['2026-10-12' => 'presente', '2026-10-14' => 'ausente', '2026-10-19' => 'excusa'] as $f => $estado) {
            Asistencia::create(['curso_id' => $this->grupo->id, 'estudiante_id' => $this->laura->estudiante_id, 'fecha' => $f, 'estado' => $estado, 'motivo' => $estado === 'excusa' ? 'Cita médica' : null, 'registrado_por' => $this->docente->id]);
        }

        $this->actingAs($this->laura->estudiante)->get('/estudiante/programa')->assertOk()
            ->assertViewHas('datos', function (array $d) {
                $mods = collect($d['modulos']);
                $clases = collect($d['clases'])->keyBy('fecha');

                return $d['matricula']['grupo']['docente'] === trim($this->docente->nombres.' '.$this->docente->apellidos)
                    && $mods->pluck('estado')->all() === ['terminado', 'terminado', 'en_curso']
                    && $mods[0]['estadoNota'] === 'aprobado' && $mods[0]['nota'] === 4.5
                    && $mods[1]['estadoNota'] === 'sin_nota'
                    && $d['promedio'] === 4.5 && $d['aprobados'] === 1
                    && $clases['2026-10-12']['estado'] === 'P' && $clases['2026-10-14']['estado'] === 'A'
                    && $clases['2026-10-19']['estado'] === 'E' && $clases['2026-10-19']['motivo'] === 'Cita médica'
                    && $clases['2026-10-07']['estado'] === 'sin_registrar'
                    && $clases['2026-10-21']['estado'] === 'hoy' && $clases['2026-10-26']['estado'] === 'futura'
                    // La excusa no cuenta como falla y las clases sin registro no cuentan
                    && $d['asistencia']['pct'] === 50 && $d['asistencia']['E'] === 1;
            });
    }

    public function test_sin_matricula_ve_su_panel_vacio_sin_errores(): void
    {
        $nuevo = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);

        foreach (['/estudiante', '/estudiante/programa', '/estudiante/aula', '/estudiante/pagos', '/estudiante/comunicados', '/estudiante/foro'] as $url) {
            $this->actingAs($nuevo)->get($url)->assertOk()
                ->assertViewHas('datos', fn (array $d) => $d['matricula'] === null && $d['pagos'] === null && $d['comunicados'] === []);
        }
    }

    /* ───────────── Plan de pago ───────────── */

    public function test_elige_su_plan_una_sola_vez_y_las_cuotas_cuentan_desde_el_inicio_de_clases(): void
    {
        $this->actingAs($this->laura->estudiante)->get('/estudiante/pagos')
            ->assertViewHas('datos', fn (array $d) => $d['pagos']['puedeElegir'] === true && $d['pagos']['vencidasSiElige']['mensual'] === 3);

        $this->actingAs($this->laura->estudiante)->postJson('/estudiante/pagos/plan', ['plan' => 'mensual'])
            ->assertOk()->assertJson(['ok' => true])
            ->assertJsonPath('datos.pagos.plan', 'mensual');

        $cuotas = Pago::where('matricula_id', $this->laura->id)->where('tipo', 'mensual')->orderBy('numero_cuota')->get();
        // Clases desde el lunes 3 de agosto: la primera vence el 10 de agosto; agosto, septiembre y octubre ya vencieron
        $this->assertSame(['2026-08-10', '2026-09-10', '2026-10-10'], $cuotas->map(fn (Pago $p) => $p->fecha_vencimiento->toDateString())->all());
        $this->assertSame(['vencido', 'vencido', 'vencido'], $cuotas->pluck('estado')->all());
        $this->assertSame('mensual', $this->laura->fresh()->plan_pago);
        $this->assertStringStartsWith('Eligió su plan de pago mensual: 3 cuotas', Auditoria::latest('id')->first()->descripcion);

        // No se cambia
        $this->actingAs($this->laura->estudiante)->postJson('/estudiante/pagos/plan', ['plan' => 'periodo'])
            ->assertStatus(422)->assertJsonPath('errors.plan.0', 'Ya elegiste tu plan de pago y no se puede cambiar. Si necesitas ayuda, habla con el administrativo de tu sede.');
        $this->assertSame(3, Pago::where('matricula_id', $this->laura->id)->where('tipo', '!=', 'matricula')->count());
    }

    public function test_la_secretaria_elige_el_plan_por_el_estudiante_que_no_lo_ha_elegido(): void
    {
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$this->juan->id}/plan", ['plan' => 'periodo'])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('periodo', $this->juan->fresh()->plan_pago);
        $this->assertSame(1, Pago::where('matricula_id', $this->juan->id)->where('tipo', 'periodo')->count(), '3 meses en semestres = una cuota');
        $this->assertStringStartsWith('Eligió por el estudiante el plan de pago semestral', Auditoria::latest('id')->first()->descripcion);

        // Ya no lo puede elegir nadie más
        $this->actingAs($this->juan->estudiante)->postJson('/estudiante/pagos/plan', ['plan' => 'mensual'])->assertStatus(422);
        $this->actingAs($this->admin)->patchJson("/admin/matriculas/{$this->juan->id}/plan", ['plan' => 'mensual'])->assertStatus(422);
    }

    /* ───────────── Comunicados ───────────── */

    public function test_lee_los_comunicados_que_le_llegan_y_queda_la_lectura(): void
    {
        $paraEstudiantes = $this->comunicado('estudiantes');
        $paraDocentes = $this->comunicado('docentes');
        $otroGrupo = $this->comunicado('grupo', $this->grupo($this->programa, 'Mañana', $this->docente)->id);
        $miGrupo = $this->comunicado('grupo', $this->grupo->id);

        $this->actingAs($this->laura->estudiante)->get('/estudiante/comunicados')
            ->assertViewHas('datos', fn (array $d) => collect($d['comunicados'])->pluck('id')->sort()->values()->all() === collect([$paraEstudiantes->id, $miGrupo->id])->sort()->values()->all());

        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/comunicados/{$paraEstudiantes->id}/leido")->assertOk();
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/comunicados/{$paraEstudiantes->id}/leido")->assertOk();   // dos veces: una lectura
        $this->assertDatabaseCount('comunicado_lecturas', 1);
        $this->assertNotNull($paraEstudiantes->lectores()->where('users.id', $this->laura->estudiante_id)->first());

        // Lo que no le llega no se marca (ni se revela)
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/comunicados/{$paraDocentes->id}/leido")->assertNotFound();
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/comunicados/{$otroGrupo->id}/leido")->assertNotFound();
    }

    /* ───────────── Foro ───────────── */

    public function test_participa_en_el_foro_de_su_sede_sin_ver_lo_oculto(): void
    {
        Storage::fake('local');
        $e = $this->laura->estudiante;

        $r = $this->actingAs($e)->post('/estudiante/foro/temas', [
            'categoria' => 'academico', 'titulo' => '¿Cuándo es la habilitación?', 'texto' => 'Perdí el módulo y quiero saber.', 'fijado' => '1',
            'adjunto' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $tema = ForoTema::findOrFail($r->json('tema'));
        $this->assertFalse($tema->fijado, 'El estudiante no fija temas');
        $this->assertNotNull($tema->mensajes()->first()->adjunto_ruta);

        $this->actingAs($this->juan->estudiante)->postJson("/estudiante/foro/temas/{$tema->id}/mensajes", ['texto' => 'Yo también.'])->assertOk();
        $deJuan = ForoMensaje::where('autor_id', $this->juan->estudiante_id)->first();

        // Reporta lo ajeno, una vez; lo propio no
        $this->actingAs($e)->postJson("/estudiante/foro/mensajes/{$deJuan->id}/reportes", ['motivo' => 'No tiene que ver'])->assertOk();
        $this->assertSame(1, ForoReporte::count());
        $this->actingAs($e)->postJson("/estudiante/foro/mensajes/{$deJuan->id}/reportes", ['motivo' => 'Otra vez'])->assertStatus(422);
        $propio = $tema->mensajes()->orderBy('id')->first();
        $this->actingAs($e)->postJson("/estudiante/foro/mensajes/{$propio->id}/reportes", ['motivo' => 'Prueba de reporte'])->assertStatus(422);

        // La secretaría oculta el mensaje de Juan: ya no se ve
        $deJuan->update(['oculto_en' => now(), 'oculto_por' => $this->admin->id, 'motivo_oculto' => 'Fuera de tema']);
        $this->actingAs($e)->get('/estudiante/foro')
            ->assertViewHas('datos', fn (array $d) => count($d['foro']['temas']) === 1 && count($d['foro']['temas'][0]['mensajes']) === 1);

        // Un tema cerrado no recibe respuestas
        $tema->update(['cerrado_en' => now(), 'cerrado_por' => $this->admin->id]);
        $this->actingAs($e)->postJson("/estudiante/foro/temas/{$tema->id}/mensajes", ['texto' => 'Hola'])->assertStatus(422);
    }

    public function test_no_participa_en_el_foro_de_otra_sede(): void
    {
        $otra = $this->otraSede();
        $tema = ForoTema::create(['sede_id' => $otra->id, 'autor_id' => $this->admin->id, 'categoria' => 'vida', 'titulo' => 'De otra sede']);
        $m = ForoMensaje::create(['tema_id' => $tema->id, 'autor_id' => $this->admin->id, 'texto' => 'Hola']);

        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/foro/temas/{$tema->id}/mensajes", ['texto' => 'Hola'])->assertForbidden();
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/foro/mensajes/{$m->id}/reportes", ['motivo' => 'No es de aquí'])->assertForbidden();
    }

    /* ───────────── Aula virtual ───────────── */

    public function test_el_docente_publica_una_evaluacion_y_el_estudiante_la_responde_una_sola_vez(): void
    {
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", $this->evaluacion())
            ->assertOk()->assertJson(['ok' => true]);
        $ev = Evaluacion::with('preguntas.opciones')->firstOrFail();
        $this->actingAs($this->docente)->get('/docente/aula')->assertOk()->assertSee('window.DAU_SERVIDOR', false)
            ->assertViewHas('datos', fn (array $d) => $d['grupos'][0]['evaluaciones'][0]['titulo'] === 'Quiz de prácticas');

        // Antes del cierre no viajan las correctas
        $this->actingAs($this->laura->estudiante)->get('/estudiante/aula')
            ->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['preguntas'][0]['correcta'] === null && $d['evaluaciones'][0]['respuesta'] === null);

        $correctas = $ev->preguntas->mapWithKeys(fn ($p) => [$p->id => $p->opciones->firstWhere('es_correcta', true)->id])->all();
        $marcadas = $correctas;
        $marcadas[$ev->preguntas[1]->id] = $ev->preguntas[1]->opciones->firstWhere('es_correcta', false)->id;   // una mala
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $marcadas])
            ->assertOk()->assertJsonPath('mensaje', 'Enviada. Acertaste 1 de 2: tu nota es 2,5.')
            ->assertJsonPath('datos.evaluaciones.0.respuesta.nota', 2.5);

        // Una sola vez; el docente ya no la elimina ni le agrega preguntas, pero sí corrige textos (27-11-2026)
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $correctas])->assertStatus(422);
        $mas = $this->evaluacion();
        $mas['preguntas'][] = ['enunciado' => '¿Tercera?', 'opciones' => [['texto' => 'Sí', 'correcta' => true], ['texto' => 'No', 'correcta' => false]]];
        $this->actingAs($this->docente)->putJson("/docente/aula/evaluaciones/{$ev->id}", $mas)->assertJsonValidationErrors(['preguntas' => 'no agregar ni quitar']);
        $this->actingAs($this->docente)->putJson("/docente/aula/evaluaciones/{$ev->id}", $this->evaluacion())->assertOk();
        $this->actingAs($this->docente)->deleteJson("/docente/aula/evaluaciones/{$ev->id}")->assertStatus(422);

        // Le falta una pregunta: no se guarda nada
        $this->actingAs($this->juan->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => array_slice($correctas, 0, 1, true)])
            ->assertStatus(422);
        $this->assertSame(0, $ev->respuestasEstudiante()->where('estudiante_id', $this->juan->estudiante_id)->count());

        // Después del cierre: ve las correctas y ya no se responde
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->actingAs($this->laura->estudiante)->get('/estudiante/aula')
            ->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['preguntas'][0]['correcta'] === $correctas[$ev->preguntas[0]->id]);
        $this->actingAs($this->juan->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $correctas])->assertStatus(422);
    }

    public function test_el_estudiante_entrega_cambia_el_archivo_y_el_docente_califica(): void
    {
        Storage::fake('local');
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/actividades", [
            'titulo' => 'Informe de la práctica', 'instrucciones' => 'Entrega el informe en PDF con fotos.', 'modulo_id' => $this->programa->modulos[2]->id, 'cierra' => '2026-10-30T23:59',
        ])->assertOk();
        $a = Actividad::firstOrFail();

        $url = "/estudiante/aula/actividades/{$a->id}/entrega";
        $this->actingAs($this->laura->estudiante)->post($url, ['archivo' => UploadedFile::fake()->create('informe.exe', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($this->laura->estudiante)->post($url, ['archivo' => UploadedFile::fake()->create('informe.pdf', 300, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('mensaje', 'Entregada. Puedes cambiar el archivo hasta la fecha límite.');
        $viejo = Entrega::firstOrFail()->archivo_path;
        $this->actingAs($this->laura->estudiante)->post($url, ['archivo' => UploadedFile::fake()->create('informe-v2.pdf', 300, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('mensaje', 'Cambiaste tu archivo. Tu docente verá la versión nueva.');
        $e = Entrega::firstOrFail();
        $this->assertSame('informe-v2.pdf', $e->archivo_nombre);
        Storage::disk('local')->assertMissing($viejo);
        Storage::disk('local')->assertExists($e->archivo_path);

        // Solo ella descarga su archivo (y su docente)
        $this->actingAs($this->laura->estudiante)->get("/estudiante/aula/entregas/{$e->id}/archivo")->assertOk();
        $this->actingAs($this->juan->estudiante)->get("/estudiante/aula/entregas/{$e->id}/archivo")->assertNotFound();
        $this->actingAs($this->docente)->get("/docente/aula/entregas/{$e->id}/archivo")->assertOk();

        // El docente califica; ya no se cambia el archivo ni se elimina la actividad
        $this->actingAs($this->docente)->patchJson("/docente/aula/entregas/{$e->id}", ['nota' => '6'])->assertStatus(422);
        $this->actingAs($this->docente)->patchJson("/docente/aula/entregas/{$e->id}", ['nota' => '4,3', 'comentario' => 'Buen informe.'])->assertOk();
        $this->assertSame('4.30', $e->fresh()->calificacion);
        $this->actingAs($this->laura->estudiante)->post($url, ['archivo' => UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($this->docente)->deleteJson("/docente/aula/actividades/{$a->id}")->assertStatus(422);

        // Después de la fecha límite nadie entrega
        Carbon::setTestNow('2026-10-31 08:00:00');
        $this->actingAs($this->juan->estudiante)->post($url, ['archivo' => UploadedFile::fake()->create('tarde.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_el_aula_es_solo_de_su_grupo(): void
    {
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", $this->evaluacion())->assertOk();
        $ev = Evaluacion::firstOrFail();

        // Un estudiante de otro grupo no responde; otro docente no publica en este grupo
        $otro = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        $this->actingAs($otro)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => [1 => 1]])->assertForbidden();
        $this->actingAs($this->docente('activo'))->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", $this->evaluacion())->assertForbidden();

        // Una pregunta sin respuesta correcta no se publica
        $mala = $this->evaluacion();
        $mala['preguntas'][0]['opciones'][0]['correcta'] = false;
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", $mala)
            ->assertStatus(422)->assertJson(['errors' => ['preguntas.0' => ['Marca una sola respuesta correcta en la pregunta 1.']]]);
    }

    /* ───────────── Apoyo ───────────── */

    private function matricula(string $nombres, string $apellidos): Matricula
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'nombres' => $nombres, 'apellidos' => $apellidos]);
        $this->grupo->estudiantes()->attach($e->id);

        return Matricula::create([
            'codigo' => 'MAT-'.$e->id, 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id, 'curso_id' => $this->grupo->id,
            'sede_id' => $this->sede->id, 'estado' => 'activo', 'estado_fecha' => '2026-08-01', 'fecha_inicio' => '2026-08-01',
            'precio_programa' => $this->programa->precio_total,
        ]);
    }

    private function comunicado(string $destino, ?int $cursoId = null): Comunicado
    {
        return Comunicado::create([
            'sede_id' => $this->sede->id, 'autor_id' => $destino === 'grupo' ? $this->docente->id : $this->admin->id,
            'titulo' => 'Aviso para '.$destino, 'mensaje' => 'Texto del aviso.', 'categoria' => 'general', 'destino' => $destino,
            'curso_id' => $cursoId, 'estado' => 'publicado', 'publicado_en' => now()->subDay(),
        ]);
    }

    private function evaluacion(): array
    {
        return [
            'titulo' => 'Quiz de prácticas', 'descripcion' => 'Dos preguntas.', 'modulo_id' => $this->programa->modulos[2]->id, 'cierra' => '2026-10-25 23:59',
            'preguntas' => [
                ['enunciado' => '¿Primera pregunta?', 'opciones' => [['texto' => 'Correcta', 'correcta' => true], ['texto' => 'Mala', 'correcta' => false]]],
                ['enunciado' => '¿Segunda pregunta?', 'opciones' => [['texto' => 'Mala', 'correcta' => false], ['texto' => 'Correcta', 'correcta' => true], ['texto' => 'Otra', 'correcta' => false]]],
            ],
        ];
    }
}
