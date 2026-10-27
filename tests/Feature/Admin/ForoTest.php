<?php

namespace Tests\Feature\Admin;

use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoReporte;
use App\Models\Comunicacion\ForoTema;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Comunicacion\ForoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Panel Admin › Foro: el foro general de la sede y su moderación (abrir temas, responder, fijar, cerrar,
 * ocultar y restaurar mensajes, descartar reportes), con el adjunto en el disco privado y todo en la auditoría.
 */
class ForoTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private array $h = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        Storage::fake('local');
        $this->prepararSede();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Un tema abierto por otra persona, con su primer mensaje (y opcionalmente una respuesta reportada). */
    private function temaDe(User $autor, array $opc = []): ForoTema
    {
        $t = ForoTema::create(['sede_id' => $autor->sede_id, 'autor_id' => $autor->id, 'categoria' => 'academico', 'titulo' => 'Duda del parcial'] + $opc);
        ForoMensaje::create(['tema_id' => $t->id, 'autor_id' => $autor->id, 'texto' => '¿Qué entra en el parcial?']);

        return $t;
    }

    private function estudiante(): User
    {
        return User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]);
    }

    public function test_la_pagina_muestra_solo_el_foro_de_su_sede_con_quien_es_cada_persona(): void
    {
        $e = $this->estudiante();
        $propio = $this->temaDe($e);
        $otra = $this->otraSede();
        $ajeno = $this->temaDe(User::factory()->create(['role' => 'estudiante', 'sede_id' => $otra->id]));

        $this->actingAs($this->admin)->get('/admin/foro')->assertOk()
            ->assertViewHas('temas', fn ($ts) => $ts->pluck('id')->all() === [$propio->id] && ! $ts->contains('id', $ajeno->id))
            ->assertViewHas('personas', fn ($ps) => isset($ps['e'.$e->id], $ps['x'.$this->admin->id]) && $ps['x'.$this->admin->id][1] === 'Administración');
    }

    public function test_abrir_un_tema_con_adjunto_y_fijado(): void
    {
        $r = $this->actingAs($this->admin)->post('/admin/foro/temas', [
            'categoria' => 'vida', 'titulo' => 'Horario de la biblioteca', 'texto' => 'En vacaciones la biblioteca estará cerrada.',
            'fijado' => '1', 'adjunto' => UploadedFile::fake()->create('horario.pdf', 80, 'application/pdf'),
        ], $this->h)->assertOk();

        $r->assertJsonPath('tema.fijado', true)->assertJsonPath('tema.mensajes.0.autor', 'x'.$this->admin->id)
            ->assertJsonPath('tema.mensajes.0.adjunto.nombre', 'horario.pdf')->assertJsonPath('mensaje', 'Se publicó el tema.');
        $m = ForoMensaje::first();
        Storage::disk('local')->assertExists($m->adjunto_ruta);
        $this->actingAs($this->admin)->get("/admin/foro/mensajes/{$m->id}/adjunto")->assertOk();
        $this->assertTrue(Auditoria::where('entidad_tipo', 'ForoTema')->where('accion', 'crear')->exists());
    }

    public function test_validaciones_al_abrir_un_tema(): void
    {
        $url = '/admin/foro/temas';
        $base = ['categoria' => 'academico', 'titulo' => 'Título válido', 'texto' => 'Mensaje'];
        $this->actingAs($this->admin);
        $this->post($url, ['categoria' => 'chismes'] + $base, $this->h)->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->post($url, ['titulo' => 'Hola'] + $base, $this->h)->assertStatus(422)->assertJsonValidationErrors(['titulo' => 'al menos 5 letras']);
        $this->post($url, ['texto' => str_repeat('a', 3001)] + $base, $this->h)->assertStatus(422)->assertJsonValidationErrors('texto');
        $this->post($url, ['adjunto' => UploadedFile::fake()->create('virus.exe', 5)] + $base, $this->h)->assertStatus(422)->assertJsonValidationErrors(['adjunto' => 'PDF, JPG o PNG']);
        $this->post($url, ['texto' => ['x']] + $base, $this->h)->assertStatus(422)->assertJsonValidationErrors('texto');
        $this->assertSame(0, ForoTema::count());
    }

    public function test_responder_y_no_en_temas_cerrados_ni_ocultos(): void
    {
        $t = $this->temaDe($this->estudiante());
        $this->actingAs($this->admin)->post("/admin/foro/temas/{$t->id}/mensajes", ['texto' => 'Entra todo lo visto hasta hoy.'], $this->h)
            ->assertOk()->assertJsonCount(2, 'tema.mensajes')->assertJsonPath('mensaje', 'Se publicó tu respuesta.');

        $this->actingAs($this->admin)->patchJson("/admin/foro/temas/{$t->id}", ['cerrado' => true])->assertOk()->assertJsonPath('tema.cerrado.por', 'x'.$this->admin->id);
        $this->actingAs($this->admin)->post("/admin/foro/temas/{$t->id}/mensajes", ['texto' => 'Otra respuesta'], $this->h)
            ->assertStatus(422)->assertJsonValidationErrors(['texto' => 'cerrado']);

        $this->actingAs($this->admin)->patchJson("/admin/foro/temas/{$t->id}", ['cerrado' => false])->assertOk()->assertJsonPath('tema.cerrado', null);
        $this->actingAs($this->admin)->patchJson('/admin/foro/mensajes/'.$t->primerMensaje()->id.'/ocultar', ['motivo' => 'Tema repetido en el foro'])->assertOk();
        $this->actingAs($this->admin)->post("/admin/foro/temas/{$t->id}/mensajes", ['texto' => 'Otra respuesta'], $this->h)
            ->assertStatus(422)->assertJsonValidationErrors(['texto' => 'oculto']);
        $this->actingAs($this->admin)->patchJson("/admin/foro/temas/{$t->id}", ['fijado' => true])->assertStatus(422)->assertJsonValidationErrors('fijado');
    }

    public function test_ocultar_atiende_los_reportes_y_restaurar_lo_devuelve(): void
    {
        $t = $this->temaDe($this->estudiante());
        $m = ForoMensaje::create(['tema_id' => $t->id, 'autor_id' => $this->estudiante()->id, 'texto' => 'Mensaje ofensivo']);
        $quien = $this->estudiante();
        ForoReporte::create(['mensaje_id' => $m->id, 'user_id' => $quien->id, 'motivo' => 'Ofensivo']);

        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/ocultar", ['motivo' => 'No'])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/ocultar", ['motivo' => 'Lenguaje irrespetuoso'])
            ->assertOk()->assertJsonPath('tema.mensajes.1.oculto.motivo', 'Lenguaje irrespetuoso')->assertJsonPath('tema.mensajes.1.reportes.0.estado', 'atendido');
        $this->assertSame($this->admin->id, ForoReporte::first()->revisado_por);
        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/ocultar", ['motivo' => 'Otra vez el motivo'])
            ->assertStatus(422)->assertJsonValidationErrors(['motivo' => 'ya estaba oculto']);

        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/restaurar")->assertOk()->assertJsonPath('tema.mensajes.1.oculto', null);
        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/restaurar")->assertStatus(422);
        $this->assertTrue(Auditoria::where('entidad_tipo', 'ForoMensaje')->where('accion', 'desactivar')->exists());
        $this->assertTrue(Auditoria::where('entidad_tipo', 'ForoMensaje')->where('accion', 'activar')->exists());
    }

    public function test_descartar_reportes_deja_el_mensaje_visible(): void
    {
        $t = $this->temaDe($this->estudiante());
        $m = $t->primerMensaje();
        ForoReporte::create(['mensaje_id' => $m->id, 'user_id' => $this->estudiante()->id, 'motivo' => 'No me gustó']);

        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/descartar")
            ->assertOk()->assertJsonPath('tema.mensajes.0.reportes.0.estado', 'descartado')->assertJsonPath('tema.mensajes.0.oculto', null);
        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/descartar")->assertStatus(422);
    }

    public function test_fijar_y_quitar_de_arriba(): void
    {
        $t = $this->temaDe($this->estudiante());
        $this->actingAs($this->admin)->patchJson("/admin/foro/temas/{$t->id}", ['fijado' => true])->assertOk()->assertJsonPath('tema.fijado', true);
        $this->actingAs($this->admin)->patchJson("/admin/foro/temas/{$t->id}", ['fijado' => false])->assertOk()->assertJsonPath('tema.fijado', false);
        $this->actingAs($this->admin)->patchJson("/admin/foro/temas/{$t->id}", [])->assertStatus(422);
        $this->assertSame(2, Auditoria::where('entidad_tipo', 'ForoTema')->where('accion', 'editar')->count());
    }

    public function test_no_se_toca_el_foro_de_otra_sede(): void
    {
        $otra = $this->otraSede();
        $t = $this->temaDe(User::factory()->create(['role' => 'estudiante', 'sede_id' => $otra->id]));
        $m = $t->primerMensaje();

        $this->actingAs($this->admin)->post("/admin/foro/temas/{$t->id}/mensajes", ['texto' => 'Hola'], $this->h)->assertForbidden();
        $this->actingAs($this->admin)->patchJson("/admin/foro/temas/{$t->id}", ['cerrado' => true])->assertForbidden();
        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/ocultar", ['motivo' => 'No es de mi sede'])->assertForbidden();
        $this->actingAs($this->admin)->patchJson("/admin/foro/mensajes/{$m->id}/descartar")->assertForbidden();
        $this->actingAs($this->admin)->get("/admin/foro/mensajes/{$m->id}/adjunto")->assertNotFound();
        $this->actingAs($this->docente())->get('/admin/foro')->assertForbidden();
    }

    public function test_el_primer_mensaje_es_el_que_abre_el_tema(): void
    {
        $t = $this->temaDe($this->estudiante());
        $r = ForoMensaje::create(['tema_id' => $t->id, 'autor_id' => $this->admin->id, 'texto' => 'Respuesta']);

        $this->assertTrue($t->primerMensaje()->esPrimero());
        $this->assertFalse($r->esPrimero());
        $this->assertFalse($t->oculto());
        $t->primerMensaje()->update(['oculto_en' => now()]);
        $this->assertTrue($t->fresh()->oculto(), 'Ocultar el primer mensaje oculta el tema');
        $this->assertSame('x'.$this->admin->id, app(ForoService::class)->tema(app(ForoService::class)->cargar($t))['mensajes'][1]['autor']);
    }
}
