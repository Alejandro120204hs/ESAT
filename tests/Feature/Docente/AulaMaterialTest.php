<?php

namespace Tests\Feature\Docente;

use App\Models\Academico\Actividad;
use App\Models\Academico\Curso;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Material;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Lo que se agregó al panel Docente el 27-11-2026 (pedido por el usuario): la guía de una actividad, el material de clase
 * del grupo (archivo, enlace o los dos), lo calificado en el Aula como referencia en Notas, y la lista de clase y la
 * planilla de notas para imprimir. Mismo grupo de BackendEstudianteTest: hoy es el 21-oct y el módulo 3 va en curso.
 */
class AulaMaterialTest extends TestCase
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
        Storage::fake('local');
        $this->prepararSede();
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
        $this->laura = $this->matricula($this->grupo);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_actividad_lleva_una_guia_que_solo_descargan_su_grupo_y_su_docente(): void
    {
        $this->actingAs($this->docente)->post("/docente/aula/{$this->grupo->id}/actividades", $this->actividad([
            'adjunto' => UploadedFile::fake()->create('caso-clinico.pptx', 500),
        ]), ['Accept' => 'application/json'])->assertOk();
        $a = Actividad::firstOrFail();
        $this->assertSame('caso-clinico.pptx', $a->adjunto_nombre);
        Storage::disk('local')->assertExists($a->adjunto_path);

        // El estudiante la ve en su Aula con el enlace de descarga
        $this->actingAs($this->laura->estudiante)->get('/estudiante/aula')->assertOk()
            ->assertViewHas('datos', fn (array $d) => $d['actividades'][0]['adjunto']['nombre'] === 'caso-clinico.pptx');
        $this->get("/estudiante/aula/actividades/{$a->id}/guia")->assertOk();
        $this->actingAs($this->docente)->get("/docente/aula/actividades/{$a->id}/guia")->assertOk();

        // Nadie de otro grupo
        $otroGrupo = $this->grupo($this->programa, 'Mañana', $this->docente('activo'), 'activo', '2026-08-03');
        $this->actingAs($this->matricula($otroGrupo)->estudiante)->get("/estudiante/aula/actividades/{$a->id}/guia")->assertNotFound();
        $this->actingAs($this->docente('activo'))->get("/docente/aula/actividades/{$a->id}/guia")->assertNotFound();

        // Un archivo que no es de los permitidos no se sube
        $this->actingAs($this->docente)->post("/docente/aula/{$this->grupo->id}/actividades", $this->actividad([
            'titulo' => 'Otra actividad', 'adjunto' => UploadedFile::fake()->create('virus.exe', 10),
        ]), ['Accept' => 'application/json'])->assertJsonValidationErrors(['adjunto' => 'PDF, Word, PowerPoint']);
    }

    public function test_cambiar_o_quitar_la_guia_borra_el_archivo_anterior(): void
    {
        $this->actingAs($this->docente)->post("/docente/aula/{$this->grupo->id}/actividades", $this->actividad([
            'adjunto' => UploadedFile::fake()->create('guia-v1.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();
        $a = Actividad::firstOrFail();
        $v1 = $a->adjunto_path;

        // Editar sin archivo conserva la guía; con otro la reemplaza (el PUT con archivo llega como POST + _method)
        $this->post("/docente/aula/actividades/{$a->id}", $this->actividad(['_method' => 'PUT', 'titulo' => 'Informe del caso']), ['Accept' => 'application/json'])->assertOk();
        $this->assertSame($v1, $a->fresh()->adjunto_path);
        $this->post("/docente/aula/actividades/{$a->id}", $this->actividad(['_method' => 'PUT', 'adjunto' => UploadedFile::fake()->create('guia-v2.pdf', 100, 'application/pdf')]), ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('guia-v2.pdf', $a->fresh()->adjunto_nombre);
        Storage::disk('local')->assertMissing($v1);

        $v2 = $a->fresh()->adjunto_path;
        $this->post("/docente/aula/actividades/{$a->id}", $this->actividad(['_method' => 'PUT', 'quitar_adjunto' => '1']), ['Accept' => 'application/json'])->assertOk();
        $this->assertNull($a->fresh()->adjunto_path);
        Storage::disk('local')->assertMissing($v2);
    }

    public function test_el_docente_publica_material_con_archivo_enlace_o_los_dos(): void
    {
        $url = "/docente/aula/{$this->grupo->id}/materiales";
        $this->actingAs($this->docente)->post($url, ['titulo' => 'Sin nada'], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['archivo' => 'al menos uno']);
        $this->post($url, ['titulo' => 'Video', 'enlace' => 'no es un enlace'], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['enlace' => 'https://']);
        $this->post($url, ['titulo' => 'Mal módulo', 'enlace' => 'https://youtu.be/abc', 'modulo_id' => 9999], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('modulo_id');

        $this->post($url, ['titulo' => 'Presentación de la clase 3', 'modulo_id' => $this->programa->modulos[2]->id,
            'archivo' => UploadedFile::fake()->create('clase-3.pptx', 800)], ['Accept' => 'application/json'])->assertOk();
        $this->post($url, ['titulo' => 'Video de primeros auxilios', 'enlace' => 'https://www.youtube.com/watch?v=abc', 'descripcion' => 'Véanlo antes del lunes.'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(2, 'datos.grupos.0.materiales');

        // El estudiante ve los dos y descarga el archivo
        $this->actingAs($this->laura->estudiante)->get('/estudiante/aula')->assertOk()->assertViewHas('datos', function (array $d) {
            $porTitulo = collect($d['materiales'])->keyBy('titulo');

            return $porTitulo['Video de primeros auxilios']['enlace'] === 'https://www.youtube.com/watch?v=abc'
                && $porTitulo['Video de primeros auxilios']['moduloId'] === null
                && $porTitulo['Presentación de la clase 3']['archivo']['nombre'] === 'clase-3.pptx';
        });
        $m = Material::where('titulo', 'Presentación de la clase 3')->firstOrFail();
        $this->get("/estudiante/aula/materiales/{$m->id}/archivo")->assertOk();

        // Quitar el archivo de un material que no tiene enlace lo dejaría vacío
        $this->actingAs($this->docente)->post("/docente/aula/materiales/{$m->id}", ['_method' => 'PUT', 'titulo' => $m->titulo, 'quitar_archivo' => '1'], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('archivo');
        // Con enlace sí; el archivo se borra
        $this->post("/docente/aula/materiales/{$m->id}", ['_method' => 'PUT', 'titulo' => $m->titulo, 'quitar_archivo' => '1', 'enlace' => 'https://drive.google.com/x'], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($m->archivo_path);
        $this->assertNull($m->fresh()->archivo_path);
    }

    public function test_el_material_es_solo_de_su_grupo_y_se_elimina_con_su_archivo(): void
    {
        $this->actingAs($this->docente)->post("/docente/aula/{$this->grupo->id}/materiales", ['titulo' => 'Lectura 1',
            'archivo' => UploadedFile::fake()->create('lectura.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $m = Material::firstOrFail();

        // Otro docente no publica ni borra; un estudiante de otro grupo no lo descarga
        $otro = $this->docente('activo');
        $this->actingAs($otro)->post("/docente/aula/{$this->grupo->id}/materiales", ['titulo' => 'Intruso', 'enlace' => 'https://x.co'], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($otro)->deleteJson("/docente/aula/materiales/{$m->id}")->assertForbidden();
        $ajeno = $this->matricula($this->grupo($this->programa, 'Mañana', $otro, 'activo', '2026-08-03'))->estudiante;
        $this->actingAs($ajeno)->get("/estudiante/aula/materiales/{$m->id}/archivo")->assertNotFound();

        $this->actingAs($this->docente)->deleteJson("/docente/aula/materiales/{$m->id}")->assertOk();
        $this->assertSame(0, Material::count());
        Storage::disk('local')->assertMissing($m->archivo_path);

        // Un grupo finalizado es solo consulta
        $this->grupo->update(['estado' => 'finalizado']);
        $this->post("/docente/aula/{$this->grupo->id}/materiales", ['titulo' => 'Tarde', 'enlace' => 'https://x.co'], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_notas_muestra_lo_calificado_en_el_aula_de_cada_modulo(): void
    {
        $modulo = $this->programa->modulos[2];
        $this->actingAs($this->docente)->postJson("/docente/aula/{$this->grupo->id}/evaluaciones", [
            'titulo' => 'Quiz de prácticas', 'modulo_id' => $modulo->id, 'cierra' => '2026-10-25 23:59',
            'preguntas' => [
                ['enunciado' => '¿Primera?', 'opciones' => [['texto' => 'Sí', 'correcta' => true], ['texto' => 'No', 'correcta' => false]]],
                ['enunciado' => '¿Segunda?', 'opciones' => [['texto' => 'Sí', 'correcta' => true], ['texto' => 'No', 'correcta' => false]]],
            ],
        ])->assertOk();
        $ev = Evaluacion::with('preguntas.opciones')->firstOrFail();
        // Una bien y una mal: 2,5
        $marcadas = [$ev->preguntas[0]->id => $ev->preguntas[0]->opciones->firstWhere('es_correcta', true)->id,
                     $ev->preguntas[1]->id => $ev->preguntas[1]->opciones->firstWhere('es_correcta', false)->id];
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/aula/evaluaciones/{$ev->id}/respuestas", ['respuestas' => $marcadas])->assertOk();

        $this->actingAs($this->docente)->get('/docente/notas')->assertOk()->assertSee('not-imprimir', false)
            ->assertViewHas('datos', function (array $d) use ($modulo) {
                $aula = (array) $d['aula'];
                $x = $aula[$this->grupo->id][$this->laura->estudiante_id][$modulo->id][0];

                return $x['tipo'] === 'evaluacion' && $x['titulo'] === 'Quiz de prácticas' && $x['nota'] === 2.5;
            });
        // La lista de clase se imprime desde Mis grupos
        $this->get('/docente/grupos')->assertOk()->assertSee('grp-dw-imprimir', false);
    }

    /* ───────────── Apoyo ───────────── */

    private function actividad(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Informe de la práctica', 'instrucciones' => 'Lee el caso y entrega el informe en PDF.',
            'modulo_id' => $this->programa->modulos[2]->id, 'cierra' => '2026-10-30T23:59',
        ], $cambios);
    }

    private function matricula(Curso $grupo): Matricula
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
        $grupo->estudiantes()->attach($e->id);

        return Matricula::create([
            'codigo' => 'MAT-'.$e->id, 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id, 'curso_id' => $grupo->id,
            'sede_id' => $this->sede->id, 'estado' => 'activo', 'estado_fecha' => '2026-08-01', 'fecha_inicio' => '2026-08-01',
            'precio_programa' => $this->programa->precio_total,
        ]);
    }
}
