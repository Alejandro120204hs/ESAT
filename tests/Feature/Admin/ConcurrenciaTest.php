<?php

namespace Tests\Feature\Admin;

use App\Models\Finanzas\Matricula;
use App\Models\Institucional\Configuracion;
use App\Models\Institucional\Sede;
use App\Models\User;
use App\Services\Academico\MatriculaService;
use App\Services\Finanzas\PagoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M13: dos personas (o dos clics) haciendo lo mismo a la vez. SQLite no corre en paralelo, así que aquí se prueba lo que
 * lo hace seguro: cada servicio vuelve a revisar con la fila bloqueada (un segundo envío o un objeto desactualizado se
 * rechazan), la BD no admite dos matrículas vigentes, cada petición que escribe es una transacción y un choque de
 * índice único responde 409. Las carreras reales (dos procesos contra MariaDB) se corrieron el 11-11-2026: HALLAZGOS.md §10.
 */
class ConcurrenciaTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 10:00');
        $this->prepararSede();
        $this->actingAs($this->admin);
    }

    private function matricula(): Matricula
    {
        $grupo = $this->grupo($this->programa(['Mañana'], 1600, 10), 'Mañana', null, 'activo', '2026-09-07');

        return app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $grupo, null);
    }

    public function test_un_segundo_envio_no_crea_otra_matricula_vigente(): void
    {
        $m = $this->matricula();
        $this->expectException(ValidationException::class);
        app(MatriculaService::class)->matricular($m->estudiante, $m->curso, null);
    }

    public function test_la_bd_no_admite_dos_matriculas_vigentes_del_mismo_estudiante(): void
    {
        $m = $this->matricula();
        $this->expectException(UniqueConstraintViolationException::class);
        Matricula::create(['codigo' => 'EST-2026-9999', 'estudiante_id' => $m->estudiante_id, 'programa_id' => $m->programa_id, 'curso_id' => $m->curso_id,
            'sede_id' => $m->sede_id, 'estado' => 'aplazado', 'fecha_inicio' => today()]);
    }

    public function test_una_matricula_que_ya_no_esta_vigente_no_cuenta(): void
    {
        $m = $this->matricula();
        app(MatriculaService::class)->cambiarEstado($m, 'retirado', today(), 'Cambio de ciudad');
        $nueva = app(MatriculaService::class)->matricular($m->estudiante, $m->curso, null);   // vuelve con otra matrícula
        $this->assertSame('activo', $nueva->estado);
    }

    public function test_un_cambio_de_estado_sobre_datos_viejos_se_rechaza(): void
    {
        $m = $this->matricula();
        $vieja = Matricula::find($m->id);                                   // la pantalla de otra persona
        app(MatriculaService::class)->cambiarEstado($m, 'retirado', today(), 'Cambio de ciudad');

        $this->expectException(ValidationException::class);
        app(MatriculaService::class)->cambiarEstado($vieja, 'aplazado', today(), 'Viaje');
    }

    public function test_el_mismo_cambio_de_programa_dos_veces_crea_una_sola_matricula(): void
    {
        $m = $this->matricula();
        $otro = $this->grupo($this->programa(['Mañana'], 1600, 10), 'Mañana', null, 'activo', '2026-09-07');
        $vieja = Matricula::find($m->id);
        app(MatriculaService::class)->cambiarPrograma($m, $otro, 'Prefiere el otro programa');

        try {
            app(MatriculaService::class)->cambiarPrograma($vieja, $otro, 'Prefiere el otro programa');
            $this->fail('El segundo cambio de programa debía rechazarse');
        } catch (ValidationException) {
        }
        $this->assertSame(1, Matricula::where('estudiante_id', $m->estudiante_id)->where('estado', 'activo')->count());
    }

    public function test_el_mismo_pago_no_se_anula_dos_veces(): void
    {
        $m = $this->matricula();
        $pagos = app(PagoService::class);
        $r = $pagos->registrarPago($m, 100000, ['fecha_pago' => '2026-11-10', 'metodo_pago' => 'Efectivo']);
        $vieja = $r->fresh();
        $pagos->anularRecaudo($r, 'Error de digitación');

        $this->expectException(ValidationException::class);
        $pagos->anularRecaudo($vieja, 'Error de digitación');
    }

    public function test_cada_peticion_que_escribe_es_una_transaccion(): void
    {
        Route::middleware(['web', 'auth', 'transaccion'])->group(function () {
            Route::post('/_prueba/escribe-y-falla', function () {
                Sede::create(['nombre' => 'A medias', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Calle 1 # 2-3']);

                return response()->json(['message' => 'Algo salió mal después de escribir'], 422);
            });
            Route::post('/_prueba/escribe-y-revienta', function () {
                Sede::create(['nombre' => 'Rota', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Calle 1 # 2-3']);
                throw new \RuntimeException('falla');
            });
            Route::post('/_prueba/escribe-bien', function () {
                Sede::create(['nombre' => 'Completa', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Calle 1 # 2-3']);

                return response()->json(['mensaje' => 'ok']);
            });
        });

        $this->postJson('/_prueba/escribe-y-falla')->assertStatus(422);
        $this->postJson('/_prueba/escribe-y-revienta')->assertStatus(500);
        $this->postJson('/_prueba/escribe-bien')->assertOk();
        $this->assertSame(['Completa'], Sede::whereIn('nombre', ['A medias', 'Rota', 'Completa'])->pluck('nombre')->all());
    }

    public function test_un_choque_de_indice_unico_avisa_en_vez_de_error_500(): void
    {
        Configuracion::guardar('ya_existe', 1);
        Route::middleware(['web', 'auth'])->post('/_prueba/duplica', fn () => Configuracion::create(['clave' => 'ya_existe', 'valor' => '2']));

        $this->postJson('/_prueba/duplica')->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'Recarga la página'));
    }

    public function test_los_consecutivos_no_se_repiten(): void
    {
        $codigos = collect(range(1, 3))->map(fn () => $this->matricula()->codigo);
        $this->assertSame(['EST-2026-0001', 'EST-2026-0002', 'EST-2026-0003'], $codigos->all());
        $this->assertSame(1, Configuracion::where('clave', 'codigo_matricula')->count());
    }
}
