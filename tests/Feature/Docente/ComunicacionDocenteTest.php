<?php

namespace Tests\Feature\Docente;

use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Comunicacion\Comunicado;
use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoReporte;
use App\Models\Comunicacion\ForoTema;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Panel Docente › Comunicados, Foro y Mi perfil (24-11-2026): lee lo que le mandan la secretaría y el Admin, publica a uno
 * o varios de sus grupos (publicar, programar o borrador, con adjunto; editar el texto, archivar, eliminar borradores),
 * participa en el foro de su sede sin moderar y cambia solo su correo, celular y dirección.
 */
class ComunicacionDocenteTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private User $docente;
    private Curso $grupoA;
    private Curso $grupoB;
    private Matricula $laura;
    private Matricula $juan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        Storage::fake('local');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana', 'Tarde'], 400, 3);
        $this->docente = $this->docente();
        $this->grupoA = $this->grupo($this->programa, 'Mañana', $this->docente, 'activo', '2026-08-03', [$this->sesion('Lunes', '07:00', '11:00')]);
        $this->grupoB = $this->grupo($this->programa, 'Tarde', $this->docente, 'activo', '2026-08-03', [$this->sesion('Martes', '13:00', '17:00')]);
        $this->laura = $this->matricula('Laura', 'Ruiz', $this->grupoA);
        $this->juan  = $this->matricula('Juan', 'Gómez', $this->grupoB);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ───────────── Mi perfil ───────────── */

    public function test_su_perfil_cambia_contacto_pero_no_su_nombre(): void
    {
        $this->actingAs($this->docente)->get('/profile')->assertOk()->assertViewIs('docente.perfil');

        $nombre = $this->docente->nombres;
        $this->actingAs($this->docente)->patch('/profile', [
            'nombres' => 'Otro', 'apellidos' => 'Nombre', 'email' => 'nuevo.correo@esat.test', 'telefono' => '310 555 1234', 'direccion' => 'Calle 5 # 3-20',
        ])->assertRedirect('/profile');

        $d = $this->docente->fresh();
        $this->assertSame($nombre, $d->nombres);
        $this->assertSame('nuevo.correo@esat.test', $d->email);
        $this->assertSame('3105551234', $d->telefono);
    }

    /* ───────────── Foro ───────────── */

    public function test_participa_en_el_foro_de_su_sede_sin_moderar(): void
    {
        $r = $this->actingAs($this->docente)->post('/docente/foro/temas', [
            'categoria' => array_key_first(ForoTema::CATEGORIAS), 'titulo' => 'Material para la práctica', 'texto' => 'Les dejo la guía del sábado.',
            'fijado' => true, 'adjunto' => UploadedFile::fake()->create('guia.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $tema = ForoTema::findOrFail($r->json('tema'));
        $this->assertFalse((bool) $tema->fijado);   // fijar es de la secretaría
        $this->assertSame($this->sede->id, $tema->sede_id);

        $deLaura = ForoMensaje::create(['tema_id' => $tema->id, 'autor_id' => $this->laura->estudiante_id, 'texto' => 'Gracias, profe.']);
        $this->actingAs($this->docente)->postJson("/docente/foro/temas/{$tema->id}/mensajes", ['texto' => 'Con gusto.'])->assertOk()
            ->assertJsonPath('ok', true);
        $this->actingAs($this->docente)->postJson("/docente/foro/mensajes/{$deLaura->id}/reportes", ['motivo' => 'No va en este tema'])->assertOk();
        $this->assertSame(1, ForoReporte::where('user_id', $this->docente->id)->count());
        $this->actingAs($this->docente)->postJson("/docente/foro/mensajes/{$deLaura->id}/reportes", ['motivo' => 'Otra vez'])->assertStatus(422);
        $primero = $tema->mensajes()->orderBy('id')->first();
        $this->actingAs($this->docente)->postJson("/docente/foro/mensajes/{$primero->id}/reportes", ['motivo' => 'El mío'])->assertStatus(422);
        $this->actingAs($this->docente)->get("/docente/foro/mensajes/{$primero->id}/adjunto")->assertOk();

        // Lo oculto no se ve; el foro de otra sede no se toca
        $deLaura->update(['oculto_en' => now(), 'oculto_por' => $this->admin->id, 'motivo_oculto' => 'Fuera de tema']);
        $this->actingAs($this->docente)->get('/docente/foro')->assertOk()
            ->assertViewHas('datos', fn (array $d) => count($d['foro']['temas']) === 1 && count($d['foro']['temas'][0]['mensajes']) === 2);
        $ajeno = ForoTema::create(['sede_id' => $this->otraSede()->id, 'autor_id' => $this->admin->id, 'categoria' => array_key_first(ForoTema::CATEGORIAS), 'titulo' => 'Otra sede']);
        $this->actingAs($this->docente)->postJson("/docente/foro/temas/{$ajeno->id}/mensajes", ['texto' => 'Hola'])->assertForbidden();
        // No modera: las rutas de la secretaría no son suyas
        $this->actingAs($this->docente)->patchJson("/admin/foro/mensajes/{$deLaura->id}/restaurar")->assertForbidden();
    }

    /* ───────────── Comunicados recibidos ───────────── */

    public function test_lee_los_que_le_mandan_a_los_docentes_y_a_toda_la_sede(): void
    {
        $paraDocentes = $this->delAdmin('docentes');
        $paraSede = $this->delAdmin('sede');
        $paraEstudiantes = $this->delAdmin('estudiantes');
        $programado = $this->delAdmin('docentes', ['estado' => 'programado', 'publicado_en' => now()->addDay()]);

        $this->actingAs($this->docente)->get('/docente/comunicados')->assertOk()
            ->assertViewHas('datos', fn (array $d) => collect($d['recibidos'])->pluck('id')->sort()->values()->all() === collect([$paraDocentes->id, $paraSede->id])->sort()->values()->all());

        $this->actingAs($this->docente)->postJson("/docente/comunicados/{$paraDocentes->id}/leido")->assertOk();
        $this->actingAs($this->docente)->postJson("/docente/comunicados/{$paraDocentes->id}/leido")->assertOk();
        $this->assertDatabaseCount('comunicado_lecturas', 1);
        $this->actingAs($this->docente)->postJson("/docente/comunicados/{$paraEstudiantes->id}/leido")->assertNotFound();
        $this->actingAs($this->docente)->postJson("/docente/comunicados/{$programado->id}/leido")->assertNotFound();
    }

    /* ───────────── Mis publicaciones ───────────── */

    public function test_publica_a_dos_grupos_y_cada_estudiante_lo_ve_en_su_panel(): void
    {
        $r = $this->actingAs($this->docente)->post('/docente/comunicados', $this->envio([
            'grupos' => [$this->grupoA->id, $this->grupoB->id], 'adjunto' => UploadedFile::fake()->create('horario.pdf', 200, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

        $filas = Comunicado::where('autor_id', $this->docente->id)->orderBy('id')->get();
        $this->assertCount(2, $filas);
        $this->assertNotNull($filas[0]->lote);
        $this->assertSame($filas[0]->lote, $filas[1]->lote);
        $this->assertSame([$this->grupoA->id, $this->grupoB->id], $filas->pluck('curso_id')->all());
        $this->assertNotSame($filas[0]->adjunto_ruta, $filas[1]->adjunto_ruta);   // cada grupo con su copia
        Storage::disk('local')->assertExists([$filas[0]->adjunto_ruta, $filas[1]->adjunto_ruta]);
        $this->assertTrue(Auditoria::where('entidad_tipo', 'Comunicado')->where('accion', 'crear')->exists());

        // Un solo envío en su lista, con los dos grupos y sus estudiantes
        $mio = collect($r->json('datos.mios'))->first();
        $this->assertSame('publicado', $mio['estado']);
        $this->assertSame([['Laura'], ['Juan']], collect($mio['grupos'])->map(fn ($g) => collect($g['personas'])->map(fn ($p) => explode(' ', $p['nombre'])[0])->all())->all());

        $this->actingAs($this->laura->estudiante)->get('/estudiante/comunicados')
            ->assertViewHas('datos', fn (array $d) => collect($d['comunicados'])->pluck('id')->all() === [$filas[0]->id]);
        $this->actingAs($this->juan->estudiante)->get('/estudiante/comunicados')
            ->assertViewHas('datos', fn (array $d) => collect($d['comunicados'])->pluck('id')->all() === [$filas[1]->id]);
        $this->actingAs($this->juan->estudiante)->get("/estudiante/comunicados/{$filas[1]->id}/adjunto")->assertOk();
        $this->actingAs($this->juan->estudiante)->get("/estudiante/comunicados/{$filas[0]->id}/adjunto")->assertNotFound();

        // Laura lo lee: el docente lo ve
        $this->actingAs($this->laura->estudiante)->postJson("/estudiante/comunicados/{$filas[0]->id}/leido")->assertOk();
        $this->actingAs($this->docente)->get('/docente/comunicados')->assertViewHas('datos', function (array $d) {
            $g = collect($d['mios'][0]['grupos']);

            return $g[0]['personas'][0]['leido'] !== null && $g[1]['personas'][0]['leido'] === null;
        });

        // La secretaría lo ve como del docente (solo se archiva allá)
        $this->actingAs($this->admin)->get('/admin/comunicados')->assertOk()
            ->assertViewHas('comunicados', fn ($cs) => collect($cs)->where('autor.tipo', 'docente')->count() === 2
                && collect($cs)->where('autor.tipo', 'docente')->every(fn ($c) => $c['solo_general'] === false));
    }

    public function test_solo_publica_a_sus_grupos_sin_finalizar(): void
    {
        $ajeno = $this->grupo($this->programa, 'Mañana', $this->docente(), 'activo', '2026-08-03', [$this->sesion('Jueves', '07:00', '11:00')]);
        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['grupos' => [$ajeno->id]]))
            ->assertStatus(422)->assertJsonValidationErrors('grupos');

        $this->grupoB->update(['estado' => 'finalizado']);
        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['grupos' => [$this->grupoA->id, $this->grupoB->id]]))
            ->assertStatus(422)->assertJsonValidationErrors('grupos');

        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['grupos' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('grupos');
        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['accion' => 'programar', 'publicar_en' => '2026-10-20 08:00']))
            ->assertStatus(422)->assertJsonValidationErrors('publicar_en');
        $this->assertDatabaseCount('comunicados', 0);
    }

    public function test_el_borrador_es_solo_suyo_cambia_de_grupos_y_se_elimina_entero(): void
    {
        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['accion' => 'borrador', 'grupos' => [$this->grupoA->id], 'mensaje' => '']))->assertOk();
        $c = Comunicado::firstOrFail();
        $this->assertSame('borrador', $c->estado);

        // Ni los estudiantes ni la secretaría ven un borrador del docente
        $this->actingAs($this->laura->estudiante)->get('/estudiante/comunicados')->assertViewHas('datos', fn (array $d) => $d['comunicados'] === []);
        $this->actingAs($this->admin)->get('/admin/comunicados')->assertViewHas('comunicados', fn ($cs) => collect($cs)->isEmpty());
        $this->actingAs($this->admin)->deleteJson("/admin/comunicados/{$c->id}")->assertForbidden();

        // Sin publicar, cambia de grupos: sale del A y entra en los dos
        $this->actingAs($this->docente)->putJson("/docente/comunicados/{$c->id}", $this->envio(['accion' => 'borrador', 'grupos' => [$this->grupoB->id, $this->grupoA->id]]))->assertOk();
        $this->assertSame(2, Comunicado::where('autor_id', $this->docente->id)->count());
        $this->actingAs($this->docente)->putJson("/docente/comunicados/{$c->id}", $this->envio(['accion' => 'borrador', 'grupos' => [$this->grupoB->id]]))->assertOk();
        $this->assertSame([$this->grupoB->id], Comunicado::pluck('curso_id')->all());

        $this->actingAs($this->docente)->deleteJson('/docente/comunicados/'.Comunicado::firstOrFail()->id)->assertOk();
        $this->assertDatabaseCount('comunicados', 0);
    }

    public function test_publicado_cambia_su_texto_pero_no_sus_grupos_y_se_archiva_en_todos(): void
    {
        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['grupos' => [$this->grupoA->id, $this->grupoB->id]]))->assertOk();
        $c = Comunicado::orderBy('id')->firstOrFail();

        $this->actingAs($this->docente)->putJson("/docente/comunicados/{$c->id}", $this->envio(['grupos' => [$this->grupoA->id]]))
            ->assertStatus(422)->assertJsonValidationErrors('grupos');
        $this->actingAs($this->docente)->putJson("/docente/comunicados/{$c->id}", $this->envio(['accion' => 'borrador', 'grupos' => [$this->grupoA->id, $this->grupoB->id]]))
            ->assertStatus(422);
        $this->actingAs($this->docente)->putJson("/docente/comunicados/{$c->id}", $this->envio(['titulo' => 'Cambio de salón corregido', 'grupos' => [$this->grupoB->id, $this->grupoA->id]]))->assertOk();
        $this->assertSame(['Cambio de salón corregido'], Comunicado::pluck('titulo')->unique()->values()->all());
        $this->actingAs($this->docente)->deleteJson("/docente/comunicados/{$c->id}")->assertStatus(422);

        // La secretaría archiva la copia del grupo B; el docente archiva el envío: la del B conserva su motivo
        $copiaB = Comunicado::where('curso_id', $this->grupoB->id)->firstOrFail();
        $this->actingAs($this->admin)->patchJson("/admin/comunicados/{$copiaB->id}/archivar", ['motivo' => 'No corresponde al grupo'])->assertOk();
        $this->actingAs($this->docente)->get('/docente/comunicados')->assertViewHas('datos', fn (array $d) => $d['mios'][0]['estado'] === 'publicado'
            && $d['mios'][0]['grupos'][1]['archivado']['motivo'] === 'No corresponde al grupo');

        $this->actingAs($this->docente)->patchJson("/docente/comunicados/{$c->id}/archivar", ['motivo' => 'Ya pasó'])->assertOk();
        $this->assertSame(['Ya pasó', 'No corresponde al grupo'], Comunicado::orderBy('curso_id')->get()->sortBy(fn ($f) => $f->curso_id === $this->grupoA->id ? 0 : 1)->pluck('motivo_archivo')->values()->all());
        $this->actingAs($this->laura->estudiante)->get('/estudiante/comunicados')->assertViewHas('datos', fn (array $d) => $d['comunicados'] === []);
        $this->actingAs($this->docente)->putJson("/docente/comunicados/{$c->id}", $this->envio(['grupos' => [$this->grupoA->id, $this->grupoB->id]]))->assertStatus(422);
        $this->actingAs($this->docente)->patchJson("/docente/comunicados/{$c->id}/archivar", ['motivo' => 'Otra vez'])->assertStatus(422);
    }

    public function test_otro_docente_no_toca_sus_comunicados(): void
    {
        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['grupos' => [$this->grupoA->id]]))->assertOk();
        $c = Comunicado::firstOrFail();
        $otro = $this->docente();

        $this->actingAs($otro)->putJson("/docente/comunicados/{$c->id}", $this->envio(['grupos' => [$this->grupoA->id]]))->assertForbidden();
        $this->actingAs($otro)->patchJson("/docente/comunicados/{$c->id}/archivar", ['motivo' => 'No es mío'])->assertForbidden();
        $this->actingAs($otro)->deleteJson("/docente/comunicados/{$c->id}")->assertForbidden();
        $this->actingAs($otro)->get('/docente/comunicados')->assertViewHas('datos', fn (array $d) => $d['mios'] === []);
        // Tampoco edita uno de la secretaría
        $delAdmin = $this->delAdmin('docentes');
        $this->actingAs($this->docente)->putJson("/docente/comunicados/{$delAdmin->id}", $this->envio(['grupos' => [$this->grupoA->id]]))->assertForbidden();
    }

    public function test_programado_no_le_llega_a_nadie_hasta_su_hora(): void
    {
        $this->actingAs($this->docente)->postJson('/docente/comunicados', $this->envio(['accion' => 'programar', 'publicar_en' => '2026-10-22 07:00', 'grupos' => [$this->grupoA->id]]))->assertOk();
        $this->actingAs($this->laura->estudiante)->get('/estudiante/comunicados')->assertViewHas('datos', fn (array $d) => $d['comunicados'] === []);

        Carbon::setTestNow('2026-10-22 07:30:00');
        $this->actingAs($this->laura->estudiante)->get('/estudiante/comunicados')->assertViewHas('datos', fn (array $d) => count($d['comunicados']) === 1);
        $this->actingAs($this->docente)->get('/docente/comunicados')->assertViewHas('datos', fn (array $d) => $d['mios'][0]['estado'] === 'publicado');
    }

    /* ───────────── Apoyo ───────────── */

    private function matricula(string $nombres, string $apellidos, Curso $grupo): Matricula
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'nombres' => $nombres, 'apellidos' => $apellidos]);
        $grupo->estudiantes()->attach($e->id);

        return Matricula::create([
            'codigo' => 'MAT-'.$e->id, 'estudiante_id' => $e->id, 'programa_id' => $this->programa->id, 'curso_id' => $grupo->id,
            'sede_id' => $this->sede->id, 'estado' => 'activo', 'estado_fecha' => '2026-08-01', 'fecha_inicio' => '2026-08-01',
            'precio_programa' => $this->programa->precio_total,
        ]);
    }

    private function delAdmin(string $destino, array $mas = []): Comunicado
    {
        return Comunicado::create($mas + [
            'sede_id' => $this->sede->id, 'autor_id' => $this->admin->id, 'titulo' => 'Aviso para '.$destino, 'mensaje' => 'Texto del aviso.',
            'categoria' => 'general', 'destino' => $destino, 'estado' => 'publicado', 'publicado_en' => now()->subDay(),
        ]);
    }

    private function envio(array $mas = []): array
    {
        return $mas + [
            'accion' => 'publicar', 'grupos' => [$this->grupoA->id], 'titulo' => 'Cambio de salón', 'categoria' => 'academico',
            'mensaje' => 'El jueves la clase es en el taller 2.',
        ];
    }
}
