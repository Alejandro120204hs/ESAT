<?php

namespace Tests\Feature\AdminGeneral;

use App\Models\Comunicacion\Comunicado;
use App\Models\Comunicacion\ForoTema;
use App\Models\Institucional\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/** Admin de ESAT › Comunicados (a varias sedes), Foro (moderar y participar) y Configuración (cuota y bloqueo de cuentas). */
class ComunicacionConfiguracionTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private User $general;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSede();
        $this->general = User::factory()->create(['role' => 'admin_general', 'sede_id' => null]);
    }

    /* ── Comunicados ── */

    private function comunicado(array $cambios = []): array
    {
        return array_merge(['sedes' => 'todas', 'destino' => 'todos', 'titulo' => 'Horario de diciembre', 'mensaje' => 'Las sedes atienden de 8 a 12 en diciembre.', 'accion' => 'publicar'], $cambios);
    }

    public function test_publica_a_todas_las_sedes_como_un_solo_envio(): void
    {
        $this->otraSede();
        $this->actingAs($this->general)->postJson('/general/comunicados', $this->comunicado())->assertCreated();
        $filas = Comunicado::all();
        $this->assertCount(2, $filas);
        $this->assertCount(1, $filas->pluck('lote')->unique());
        $this->assertSame(['sede'], $filas->pluck('destino')->unique()->values()->all());
        $this->assertSame(['publicado'], $filas->pluck('estado')->unique()->values()->all());
    }

    public function test_un_publicado_no_cambia_de_sedes_y_archivar_lo_archiva_en_todas(): void
    {
        $otra = $this->otraSede();
        $this->actingAs($this->general)->postJson('/general/comunicados', $this->comunicado())->assertCreated();
        $c = Comunicado::orderBy('id')->first();
        $this->putJson("/general/comunicados/{$c->id}", $this->comunicado(['sedes' => [$otra->id]]))->assertJsonValidationErrors('sedes');

        $this->patchJson("/general/comunicados/{$c->id}/archivar", ['motivo' => 'Ya pasó diciembre'])->assertOk();
        $this->assertSame(['archivado'], Comunicado::pluck('estado')->unique()->values()->all());
    }

    public function test_un_borrador_cambia_de_sedes_y_se_elimina_en_todas(): void
    {
        $otra = $this->otraSede();
        $this->actingAs($this->general)->postJson('/general/comunicados', $this->comunicado(['accion' => 'borrador', 'sedes' => [$this->sede->id]]))->assertCreated();
        $c = Comunicado::first();
        $this->putJson("/general/comunicados/{$c->id}", $this->comunicado(['accion' => 'borrador', 'sedes' => [$otra->id]]))->assertOk();
        $this->assertSame([$otra->id], Comunicado::pluck('sede_id')->all());

        $this->deleteJson('/general/comunicados/'.Comunicado::first()->id)->assertOk();
        $this->assertSame(0, Comunicado::count());
    }

    public function test_la_secretaria_no_edita_un_comunicado_del_admin(): void
    {
        $this->actingAs($this->general)->postJson('/general/comunicados', $this->comunicado(['sedes' => [$this->sede->id]]))->assertCreated();
        $c = Comunicado::first();
        $this->actingAs($this->admin)->putJson("/admin/comunicados/{$c->id}", [
            'accion' => 'publicar', 'destino' => 'sede', 'titulo' => 'Cambiado por la secretaría', 'categoria' => 'general', 'mensaje' => 'Un mensaje distinto al original.',
        ])->assertJsonValidationErrors('titulo');
    }

    /* ── Foro ── */

    public function test_abre_un_tema_y_responde_en_el_foro_de_cualquier_sede(): void
    {
        $this->actingAs($this->general)->postJson('/general/foro/temas', [
            'sede_id' => $this->sede->id, 'categoria' => 'Trámites y pagos', 'titulo' => 'Pagos en diciembre', 'texto' => 'Las sedes reciben pagos hasta el 20.',
        ])->assertCreated();
        $t = ForoTema::firstOrFail();
        $this->assertSame('tramites', $t->categoria);

        $this->postJson("/general/foro/temas/{$t->id}/responder", ['texto' => 'Por transferencia también.'])->assertOk();
        $this->patchJson("/general/foro/temas/{$t->id}/estado", ['cerrado' => true])->assertOk();
        $this->postJson("/general/foro/temas/{$t->id}/responder", ['texto' => 'Otra respuesta'])->assertJsonValidationErrors('texto');
    }

    /* ── Configuración y bloqueo de cuentas ── */

    public function test_cambia_la_cuota_del_sistema(): void
    {
        $this->actingAs($this->general)->putJson('/general/configuracion/cobros', ['cuota_sistema' => 2500])->assertOk();
        $this->assertSame('2500', Configuracion::valor('cuota_sistema_mensual'));
        $this->putJson('/general/configuracion/cobros', ['cuota_sistema' => 2550])->assertJsonValidationErrors('cuota_sistema');
    }

    public function test_bloquea_tras_los_intentos_fallidos_y_el_admin_desbloquea(): void
    {
        Configuracion::guardar('seguridad_intentos_maximos', 3);
        $u = User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id, 'password' => Hash::make('clave-buena')]);
        foreach (range(1, 3) as $i) {
            $this->post('/login', ['email' => $u->email, 'password' => 'mala'.$i]);
        }
        $this->assertNotNull($u->fresh()->bloqueado_en);
        $this->assertSame('intentos_fallidos', $u->fresh()->motivo_bloqueo);

        // Con la clave correcta tampoco entra mientras siga bloqueada
        $this->post('/login', ['email' => $u->email, 'password' => 'clave-buena'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->general)->patchJson("/general/configuracion/bloqueadas/{$u->id}")->assertOk();
        $this->assertNull($u->fresh()->bloqueado_en);
        $this->assertSame(0, $u->fresh()->intentos_fallidos);
    }

    public function test_bloquea_por_inactividad_pero_nunca_al_admin(): void
    {
        $u = User::factory()->create(['role' => 'docente', 'sede_id' => $this->sede->id, 'password' => Hash::make('clave'), 'ultimo_acceso' => now()->subDays(120)]);
        $this->post('/login', ['email' => $u->email, 'password' => 'clave'])->assertSessionHasErrors('email');
        $this->assertSame('inactividad', $u->fresh()->motivo_bloqueo);

        $this->general->forceFill(['password' => Hash::make('clave'), 'ultimo_acceso' => now()->subDays(400)])->save();
        $this->post('/login', ['email' => $this->general->email, 'password' => 'clave']);
        $this->assertAuthenticatedAs($this->general->fresh());
    }

    public function test_los_datos_de_la_institucion_salen_en_los_documentos(): void
    {
        $this->actingAs($this->general)->putJson('/general/configuracion/institucion', array_merge(Configuracion::INSTITUCION, ['correo' => 'contacto@esat.edu.co']))->assertOk();
        $this->assertSame('contacto@esat.edu.co', Configuracion::institucion()['correo']);
        $this->putJson('/general/configuracion/institucion', array_merge(Configuracion::INSTITUCION, ['nit' => '123']))->assertJsonValidationErrors('nit');
    }

    public function test_cambia_el_porcentaje_de_devolucion_del_derecho(): void
    {
        $this->assertSame(Configuracion::DEVOLUCION_DERECHO_PCT, Configuracion::devolucionDerechoPct());
        $this->actingAs($this->general)->putJson('/general/configuracion/devolucion', ['devolucion_derecho' => 70])->assertOk();
        $this->assertSame(70, Configuracion::devolucionDerechoPct());
        $this->assertDatabaseHas('auditorias', ['user_id' => $this->general->id, 'accion' => 'editar']);

        $this->putJson('/general/configuracion/devolucion', ['devolucion_derecho' => 101])->assertJsonValidationErrors('devolucion_derecho');
        $this->putJson('/general/configuracion/devolucion', ['devolucion_derecho' => 'mitad'])->assertJsonValidationErrors('devolucion_derecho');
        $this->actingAs($this->admin)->putJson('/general/configuracion/devolucion', ['devolucion_derecho' => 0])->assertForbidden();
        $this->assertSame(70, Configuracion::devolucionDerechoPct());
    }
}
