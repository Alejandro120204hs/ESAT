<?php

namespace Tests\Feature\Docente;

use App\Models\Academico\Curso;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Evaluaciones del Aula virtual (decidido con el usuario el 27-11-2026): el docente elige un tiempo límite (o ninguno) y
 * corrige una evaluación ya respondida —textos, cierre y respuesta correcta, recalculando las notas— sin agregar ni quitar
 * preguntas u opciones. Hoy es el 21-oct a las 9:00 y la evaluación cierra el 25-oct.
 */
class EvaluacionesTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupo;
    private Matricula $laura;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana'], 400, 3);
        $this->programa->modulos()->createMany([
            ['nombre' => 'Fundamentos', 'horas' => 100, 'orden' => 1],
            ['nombre' => 'Normativa', 'horas' => 100, 'orden' => 2],
            ['nombre' => 'Práctica', 'horas' => 200, 'orden' => 3],
        ]);
        $this->docente = $this->docente();
        $this->grupo = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', [$this->sesion('Lunes', '07:00', '11:00')]);
        $this->laura = $this->matricula();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_con_tiempo_las_preguntas_salen_al_comenzar_y_el_reloj_no_se_reinicia(): void
    {
        $ev = $this->publicar(['duracion' => 30]);
        $est = $this->laura->estudiante;

        // Antes de comenzar no ve las preguntas ni puede responder
        $this->actingAs($est)->get('/estudiante/aula')->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['preguntas'] === []
            && $d['evaluaciones'][0]['numPreguntas'] === 2 && $d['evaluaciones'][0]['duracion'] === 30);
        $this->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $this->marcas($ev, [true, true])])
            ->assertJsonValidationErrors(['respuestas' => 'Primero comienza']);

        $this->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertOk()
            ->assertJsonPath('datos.evaluaciones.0.intento.restante', 1800)
            ->assertJsonCount(2, 'datos.evaluaciones.0.preguntas');

        // Diez minutos después, abrirla otra vez no reinicia el reloj
        Carbon::setTestNow('2026-10-21 09:10:00');
        $this->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertOk()->assertJsonPath('datos.evaluaciones.0.intento.restante', 1200);

        // Envía solo una (bien): la otra cuenta como mala
        $this->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => [$ev->preguntas[0]->id => $this->correcta($ev, 0)]])
            ->assertOk()->assertJsonPath('mensaje', 'Enviada. Acertaste 1 de 2: tu nota es 2,5.');
        $this->assertSame(2, $ev->respuestasEstudiante()->count());
        $this->assertNull($ev->respuestasEstudiante()->where('pregunta_id', $ev->preguntas[1]->id)->value('opcion_id'));
    }

    public function test_cuando_se_acaba_el_tiempo_no_recibe_mas_respuestas(): void
    {
        $ev = $this->publicar(['duracion' => 10]);
        $est = $this->laura->estudiante;
        $this->actingAs($est)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertOk();

        // El envío automático puede llegar unos segundos tarde (gracia de 60 s)
        Carbon::setTestNow('2026-10-21 09:10:40');
        $otra = $this->matricula();
        $this->actingAs($otra->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertOk();

        Carbon::setTestNow('2026-10-21 09:11:30');
        $this->actingAs($est)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $this->marcas($ev, [true, true])])
            ->assertJsonValidationErrors(['respuestas' => 'Se acabó tu tiempo']);
        $this->get('/estudiante/aula')->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['intento']['expirada'] === true
            && $d['evaluaciones'][0]['preguntas'] === []);

        // El docente ve quién la comenzó y no envió
        $this->actingAs($this->docente)->get('/docente/aula')->assertViewHas('datos', function (array $d) use ($est, $otra) {
            $c = (array) $d['grupos'][0]['evaluaciones'][0]['comenzadas'];

            return $c[$est->id]['vencido'] === true && $c[$otra->estudiante_id]['vencido'] === false;
        });
    }

    public function test_el_tiempo_no_pasa_del_cierre_y_sin_tiempo_no_se_comienza(): void
    {
        // Cierra en 10 minutos aunque el tiempo sea de 30
        $ev = $this->publicar(['duracion' => 30, 'cierra' => '2026-10-21 09:10']);
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/iniciar")->assertOk()
            ->assertJsonPath('datos.evaluaciones.0.intento.restante', 600);

        $sin = $this->publicar(['titulo' => 'Sin tiempo']);
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/aula/evaluaciones/{$sin->id}/iniciar")->assertJsonValidationErrors(['evaluacion' => 'no tiene tiempo límite']);
        // Sin tiempo, le falta una: no se guarda
        $this->postJson("/estudiante/aula/evaluaciones/{$sin->id}/respuestas", ['respuestas' => [$sin->preguntas[0]->id => $this->correcta($sin, 0)]])
            ->assertJsonValidationErrors(['respuestas' => 'Te falta responder la pregunta 2']);

        // Un tiempo que no está en la lista no se acepta
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", $this->datos(['duracion' => 7]))->assertJsonValidationErrors('duracion');
    }

    public function test_corregir_una_evaluacion_ya_respondida_y_recalcular_si_cambia_la_correcta(): void
    {
        $ev = $this->publicar();
        // Laura acierta la 1 y falla la 2 (marca la segunda opción): 2,5
        $marcas = [$ev->preguntas[0]->id => $this->correcta($ev, 0), $ev->preguntas[1]->id => $ev->preguntas[1]->opciones->sortBy('id')->values()[1]->id];
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $marcas])->assertOk();

        // Corrige un texto y deja el mismo cierre
        $datos = $this->datos(['titulo' => 'Quiz de prácticas (corregido)']);
        $datos['preguntas'][0]['enunciado'] = '¿Primera pregunta, corregida?';
        $this->actingAs($this->docente)->putJson("/docente/aula/evaluaciones/{$ev->id}", $datos)->assertOk();
        $this->assertSame('¿Primera pregunta, corregida?', $ev->preguntas()->first()->enunciado);

        // La correcta de la pregunta 2 era otra: al cambiarla, Laura sube a 5,0
        $datos['preguntas'][1]['opciones'][0]['correcta'] = false;
        $datos['preguntas'][1]['opciones'][1]['correcta'] = true;
        $this->putJson("/docente/aula/evaluaciones/{$ev->id}", $datos)->assertOk()->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'se recalcularon'));
        $this->actingAs($this->laura->estudiante)->get('/estudiante/aula')->assertViewHas('datos', fn (array $d) => $d['evaluaciones'][0]['respuesta']['nota'] === 5.0);

        // No se agregan opciones, no se cambia el tiempo y no se elimina
        $mas = $datos;
        $mas['preguntas'][0]['opciones'][] = ['texto' => 'Otra', 'correcta' => false];
        $this->actingAs($this->docente)->putJson("/docente/aula/evaluaciones/{$ev->id}", $mas)->assertJsonValidationErrors(['preguntas' => 'no agregar ni quitar']);
        $this->putJson("/docente/aula/evaluaciones/{$ev->id}", array_merge($datos, ['duracion' => 20]))->assertJsonValidationErrors(['duracion' => 'no se cambia']);
        $this->deleteJson("/docente/aula/evaluaciones/{$ev->id}")->assertStatus(422);

        // Ya cerrada: se puede corregir un texto conservando el cierre, pero no ponerle otro cierre pasado
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->putJson("/docente/aula/evaluaciones/{$ev->id}", array_merge($datos, ['titulo' => 'Quiz de prácticas (final)']))->assertOk();
        $this->putJson("/docente/aula/evaluaciones/{$ev->id}", array_merge($datos, ['cierra' => '2026-10-24 23:59']))->assertJsonValidationErrors('cierra');
    }

    /* ───────────── Apoyo ───────────── */

    private function publicar(array $cambios = []): Evaluacion
    {
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", $this->datos($cambios))->assertOk();

        return Evaluacion::with('preguntas.opciones')->latest('id')->firstOrFail();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Quiz de prácticas', 'modulo_id' => $this->programa->modulos[2]->id, 'cierra' => '2026-10-25 23:59', 'duracion' => null,
            'preguntas' => [
                ['enunciado' => '¿Primera pregunta?', 'opciones' => [['texto' => 'Correcta', 'correcta' => true], ['texto' => 'Mala', 'correcta' => false]]],
                ['enunciado' => '¿Segunda pregunta?', 'opciones' => [['texto' => 'Correcta', 'correcta' => true], ['texto' => 'Mala', 'correcta' => false], ['texto' => 'Otra', 'correcta' => false]]],
            ],
        ], $cambios);
    }

    private function correcta(Evaluacion $ev, int $i): int
    {
        return $ev->preguntas[$i]->opciones->firstWhere('es_correcta', true)->id;
    }

    /** {pregunta: opción} marcando bien (true) o mal (false) cada pregunta. */
    private function marcas(Evaluacion $ev, array $bien): array
    {
        return $ev->preguntas->mapWithKeys(fn ($p, $i) => [$p->id => $p->opciones->firstWhere('es_correcta', $bien[$i])->id])->all();
    }

    private function matricula(): Matricula
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        $this->grupo->estudiantes()->attach($e->id);

        return Matricula::create([
            'codigo' => 'MAT-'.$e->id, 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id, 'curso_id' => $this->grupo->id,
            'sede_id' => $this->sede->id, 'estado' => 'activo', 'estado_fecha' => '2026-08-01', 'fecha_inicio' => '2026-08-01',
            'precio_programa' => $this->programa->precio_total,
        ]);
    }
}
