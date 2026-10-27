<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Comunicacion\Comunicado;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Comunicacion\ComunicadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Panel Admin › Comunicados: los del admin (toda la sede o un rol; borrador, programado, publicado), los de los
 * docentes (solo se archivan), el adjunto en el disco privado, quién los recibe y quién los leyó.
 */
class ComunicadosTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private Programa $programa;
    private Curso $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 09:00:00');
        Storage::fake('local');
        $this->prepararSede();
        $this->programa = $this->programa(['Mañana'], 1600, 10);
        $this->grupo    = $this->grupo($this->programa, 'Mañana', null, 'activo', '2026-09-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'accion' => 'publicar', 'destino' => 'sede', 'titulo' => 'Sin clases el lunes festivo', 'categoria' => 'general',
            'mensaje' => 'El lunes no habrá clases por el festivo. Nos vemos el martes.',
        ], $cambios);
    }

    private function crear(array $cambios = []): Comunicado
    {
        $this->actingAs($this->admin)->post('/admin/comunicados', $this->datos($cambios), ['Accept' => 'application/json'])->assertOk();

        return Comunicado::latest('id')->first();
    }

    private function deDocente(?User $docente = null): Comunicado
    {
        $docente ??= $this->docente();

        return Comunicado::create(['sede_id' => $this->sede->id, 'autor_id' => $docente->id, 'titulo' => 'Parcial del módulo', 'mensaje' => 'El parcial es el lunes.',
            'categoria' => 'academico', 'destino' => 'grupo', 'curso_id' => $this->grupo->id, 'estado' => 'publicado', 'publicado_en' => now()->subDay()]);
    }

    public function test_la_pagina_muestra_solo_los_comunicados_de_su_sede(): void
    {
        $propio = $this->crear();
        $otra = $this->otraSede();
        $admin2 = User::factory()->create(['role' => 'admin', 'sede_id' => $otra->id]);
        $ajeno = Comunicado::create(['sede_id' => $otra->id, 'autor_id' => $admin2->id, 'titulo' => 'De otra sede', 'destino' => 'sede', 'estado' => 'borrador']);

        $this->actingAs($this->admin)->get('/admin/comunicados')->assertOk()
            ->assertViewHas('comunicados', fn ($cs) => $cs->pluck('id')->all() === [$propio->id] && ! $cs->contains('id', $ajeno->id));
    }

    public function test_publicar_ahora_deja_la_fecha_y_queda_en_la_auditoria(): void
    {
        $r = $this->actingAs($this->admin)->post('/admin/comunicados', $this->datos(), ['Accept' => 'application/json'])->assertOk();
        $r->assertJsonPath('comunicado.estado', 'publicado')->assertJsonPath('comunicado.publicado_en', '2026-10-21 09:00')
            ->assertJsonPath('comunicado.autor.tipo', 'admin')->assertJsonPath('mensaje', 'Se publicó el comunicado.');

        $c = Comunicado::first();
        $this->assertSame([$this->sede->id, $this->admin->id], [$c->sede_id, $c->autor_id]);
        $this->assertTrue(Auditoria::where('entidad_tipo', 'Comunicado')->where('entidad_id', $c->id)->where('accion', 'crear')->exists());
    }

    public function test_un_borrador_solo_pide_destinatarios_y_titulo(): void
    {
        $c = $this->crear(['accion' => 'borrador', 'categoria' => null, 'mensaje' => null]);

        $this->assertSame(['borrador', null, 'general'], [$c->estado, $c->publicado_en, $c->categoria]);
        $this->actingAs($this->admin)->post('/admin/comunicados', $this->datos(['accion' => 'borrador', 'titulo' => 'Ho']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['titulo' => 'al menos 5 letras']);
    }

    public function test_programado_con_fecha_futura_y_sale_solo_cuando_llega_la_hora(): void
    {
        $url = '/admin/comunicados';
        $this->actingAs($this->admin);
        $this->post($url, $this->datos(['accion' => 'programar']), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('publicar_en');
        $this->post($url, $this->datos(['accion' => 'programar', 'publicar_en' => '2026-10-21 08:00']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['publicar_en' => 'futuras']);
        $this->post($url, $this->datos(['accion' => 'programar', 'publicar_en' => '2026-10-25 07:00', 'visible_hasta' => '2026-10-24']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['visible_hasta' => 'antes de que se publique']);

        $c = $this->crear(['accion' => 'programar', 'publicar_en' => '2026-10-25 07:00', 'visible_hasta' => '2026-10-30']);
        $this->assertSame('programado', $c->estadoActual());

        Carbon::setTestNow('2026-10-25 07:00:00');
        $this->assertSame('publicado', $c->fresh()->estadoActual(), 'Cuando llega la hora cuenta como publicado, sin tareas programadas');
    }

    public function test_validaciones_de_destino_categoria_mensaje_y_adjunto(): void
    {
        $url = '/admin/comunicados';
        $h = ['Accept' => 'application/json'];
        $this->actingAs($this->admin);
        $this->post($url, $this->datos(['destino' => 'grupo']), $h)->assertStatus(422)->assertJsonValidationErrors('destino');
        $this->post($url, $this->datos(['categoria' => 'chismes']), $h)->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->post($url, $this->datos(['mensaje' => 'Corto']), $h)->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->post($url, $this->datos(['mensaje' => str_repeat('a', 2001)]), $h)->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->post($url, $this->datos(['adjunto' => UploadedFile::fake()->create('virus.exe', 10)]), $h)->assertStatus(422)->assertJsonValidationErrors(['adjunto' => 'PDF, JPG o PNG']);
        $this->post($url, $this->datos(['adjunto' => UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf')]), $h)->assertStatus(422)->assertJsonValidationErrors(['adjunto' => '5 MB']);
        $this->post($url, $this->datos(['titulo' => ['x']]), $h)->assertStatus(422)->assertJsonValidationErrors('titulo');
        $this->assertSame(0, Comunicado::count());
    }

    public function test_el_adjunto_se_guarda_en_privado_y_solo_lo_descarga_su_sede(): void
    {
        $c = $this->crear(['adjunto' => UploadedFile::fake()->create('circular.pdf', 120, 'application/pdf')]);

        $this->assertSame(['circular.pdf', 'pdf'], [$c->adjunto_nombre, $c->adjunto_tipo]);
        Storage::disk('local')->assertExists($c->adjunto_ruta);
        $this->actingAs($this->admin)->get("/admin/comunicados/{$c->id}/adjunto")->assertOk();

        $otro = User::factory()->create(['role' => 'admin', 'sede_id' => $this->otraSede()->id]);
        $this->actingAs($otro)->get("/admin/comunicados/{$c->id}/adjunto")->assertNotFound();

        // Quitarlo al editar borra el archivo
        $ruta = $c->adjunto_ruta;
        $this->actingAs($this->admin)->post("/admin/comunicados/{$c->id}", $this->datos(['_method' => 'PUT', 'quitar_adjunto' => '1']), ['Accept' => 'application/json'])->assertOk();
        $this->assertNull($c->fresh()->adjunto_ruta);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_un_publicado_no_cambia_de_destinatarios_ni_vuelve_a_borrador(): void
    {
        $c = $this->crear();
        $h = ['Accept' => 'application/json'];

        $this->actingAs($this->admin)->post("/admin/comunicados/{$c->id}", $this->datos(['_method' => 'PUT', 'destino' => 'docentes']), $h)
            ->assertStatus(422)->assertJsonValidationErrors(['destino' => 'no se cambian']);
        $this->actingAs($this->admin)->post("/admin/comunicados/{$c->id}", $this->datos(['_method' => 'PUT', 'accion' => 'borrador']), $h)
            ->assertStatus(422)->assertJsonValidationErrors('titulo');

        $this->actingAs($this->admin)->post("/admin/comunicados/{$c->id}", $this->datos(['_method' => 'PUT', 'titulo' => 'Sin clases el lunes 2 de noviembre', 'fijado' => '1']), $h)
            ->assertOk()->assertJsonPath('mensaje', 'Se guardaron los cambios.')->assertJsonPath('comunicado.publicado_en', '2026-10-21 09:00');
        $this->assertTrue($c->fresh()->fijado);
        $this->assertTrue(Auditoria::where('entidad_id', $c->id)->where('accion', 'editar')->exists());
    }

    public function test_los_de_los_docentes_no_se_editan_pero_se_archivan(): void
    {
        $c = $this->deDocente();
        $h = ['Accept' => 'application/json'];

        $this->actingAs($this->admin)->post("/admin/comunicados/{$c->id}", $this->datos(['_method' => 'PUT']), $h)
            ->assertStatus(422)->assertJsonValidationErrors(['titulo' => 'solo se pueden archivar']);
        $this->actingAs($this->admin)->patchJson("/admin/comunicados/{$c->id}/archivar", ['motivo' => 'No'])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->actingAs($this->admin)->patchJson("/admin/comunicados/{$c->id}/archivar", ['motivo' => 'No es un tema de la institución'])
            ->assertOk()->assertJsonPath('comunicado.estado', 'archivado')->assertJsonPath('comunicado.autor.tipo', 'docente');

        $this->assertSame($this->admin->id, $c->fresh()->archivado_por);
        $this->assertStringContainsString('del docente', Auditoria::where('entidad_id', $c->id)->where('accion', 'desactivar')->value('descripcion'));
        $this->actingAs($this->admin)->patchJson("/admin/comunicados/{$c->id}/archivar", ['motivo' => 'Otra vez el motivo'])
            ->assertStatus(422)->assertJsonValidationErrors(['motivo' => 'Ya estaba archivado']);
    }

    public function test_solo_se_eliminan_borradores_y_con_su_adjunto(): void
    {
        $pub = $this->crear();
        $this->actingAs($this->admin)->deleteJson("/admin/comunicados/{$pub->id}")->assertStatus(422);
        $this->actingAs($this->admin)->patchJson("/admin/comunicados/{$this->crear(['accion' => 'borrador'])->id}/archivar", ['motivo' => 'Un borrador'])
            ->assertStatus(422)->assertJsonValidationErrors(['motivo' => 'se elimina']);

        $bor = $this->crear(['accion' => 'borrador', 'adjunto' => UploadedFile::fake()->create('foto.png', 50, 'image/png')]);
        $ruta = $bor->adjunto_ruta;
        $this->actingAs($this->admin)->deleteJson("/admin/comunicados/{$bor->id}")->assertOk();
        $this->assertNull(Comunicado::find($bor->id));
        Storage::disk('local')->assertMissing($ruta);
        $this->assertTrue(Auditoria::where('entidad_id', $bor->id)->where('accion', 'eliminar')->exists());
    }

    public function test_no_se_toca_un_comunicado_de_otra_sede(): void
    {
        $otra = $this->otraSede();
        $admin2 = User::factory()->create(['role' => 'admin', 'sede_id' => $otra->id]);
        $ajeno = Comunicado::create(['sede_id' => $otra->id, 'autor_id' => $admin2->id, 'titulo' => 'De otra sede', 'mensaje' => 'Mensaje de otra sede.',
            'destino' => 'sede', 'estado' => 'publicado', 'publicado_en' => now()->subDay()]);

        $this->actingAs($this->admin)->post("/admin/comunicados/{$ajeno->id}", $this->datos(['_method' => 'PUT']), ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->admin)->patchJson("/admin/comunicados/{$ajeno->id}/archivar", ['motivo' => 'No es de mi sede'])->assertForbidden();
        $this->actingAs($this->admin)->deleteJson("/admin/comunicados/{$ajeno->id}")->assertForbidden();
        $this->actingAs($this->docente())->get('/admin/comunicados')->assertForbidden();
    }

    public function test_destinatarios_y_lecturas(): void
    {
        $con = $this->estudiante('1070000001', '2010-03-01');          // menor, con matrícula vigente
        $sin = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]); // sin matrícula: no recibe
        $docente = $this->docente();
        $inactivo = $this->docente();
        $inactivo->update(['activo' => false]);

        $p = app(ComunicadoService::class)->personas($this->sede->id);
        $this->assertSame([$con->id], collect($p['estudiantes'])->pluck('id')->all());
        $this->assertTrue($p['estudiantes'][0]['menor']);
        $this->assertSame($this->grupo->id, $p['estudiantes'][0]['grupo_id']);
        $this->assertSame([$docente->id], collect($p['docentes'])->pluck('id')->all(), 'Los docentes inactivos no reciben');
        $this->assertNotContains($sin->id, collect($p['estudiantes'])->pluck('id'));

        $c = $this->crear();
        $c->lectores()->attach([$con->id => ['leido_en' => '2026-10-21 09:30:00'], $docente->id => ['leido_en' => '2026-10-21 10:05:00']]);
        $this->assertSame(['e'.$con->id => '2026-10-21 09:30', 'd'.$docente->id => '2026-10-21 10:05'],
            app(ComunicadoService::class)->comunicado($c->fresh()->load(['autor', 'archivadoPor', 'lectores']))['lecturas']);
    }

    private function estudiante(string $documento, string $nacimiento): User
    {
        $e = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'numero_documento' => $documento, 'fecha_nacimiento' => $nacimiento]);
        $e->documentos()->createMany(array_map(fn ($d) => ['documento' => $d, 'entregado_en' => now()], EstudianteDocumento::REQUERIDOS));
        $acu = User::factory()->create(['role' => 'acudiente', 'sede_id' => $this->sede->id]);
        $e->acudientes()->attach($acu->id, ['parentesco' => 'Madre']);
        $this->actingAs($this->admin)->postJson('/admin/matriculas', ['estudiante_id' => $e->id, 'curso_id' => $this->grupo->id])->assertOk();

        return $e;
    }
}
