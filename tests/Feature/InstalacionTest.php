<?php

namespace Tests\Feature;

use App\Models\Institucional\Sede;
use App\Models\User;
use App\Services\Academico\MatriculaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Feature\Admin\DatosAcademicos;
use Tests\TestCase;

/**
 * Una instalación nueva arranca (HALLAZGOS A11) y deshacer migraciones no borra dinero (A12).
 * La instalación completa sobre MariaDB vacía (migrar, sembrar, entrar y abrir las 14 pantallas del Admin) se probó el
 * 11-11-2026 (HALLAZGOS.md, A11).
 */
class InstalacionTest extends TestCase
{
    use DatosAcademicos, RefreshDatabase;

    private const CUENTAS = [
        'SUPERADMIN_EMAIL' => 'Super@Esat.test', 'SUPERADMIN_DOCUMENTO' => '10.101.010', 'SUPERADMIN_NOMBRES' => 'Alejandro',
        'ADMIN_GENERAL_EMAIL' => 'admin@esat.test', 'ADMIN_GENERAL_DOCUMENTO' => '20202020',
    ];

    protected function tearDown(): void
    {
        foreach (array_keys(self::CUENTAS) as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        parent::tearDown();
    }

    private function sembrar(array $cuentas = self::CUENTAS): void
    {
        foreach ($cuentas as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
        $this->seed(DatabaseSeeder::class);
    }

    /* ── A11: una instalación nueva arranca ── */

    public function test_una_instalacion_nueva_tiene_sedes_y_quien_entre(): void
    {
        $this->sembrar();

        $this->assertSame(['Bogotá', 'Guaduas', 'La Dorada', 'Sasaima'], Sede::orderBy('nombre')->pluck('nombre')->all());
        $super = User::where('role', 'superadmin')->first();
        $this->assertSame('super@esat.test', $super->email);
        $this->assertSame('10101010', $super->numero_documento);
        $this->assertTrue(Hash::check('10101010', $super->password), 'La clave inicial es el número de documento');

        // El Admin entra con su documento y cae en su panel
        $this->post('/login', ['email' => 'admin@esat.test', 'password' => '20202020'])->assertRedirect(route('general.dashboard'));
        $this->get('/general')->assertOk();
    }

    public function test_sembrar_otra_vez_no_duplica(): void
    {
        $this->sembrar();
        $this->sembrar();

        $this->assertSame(4, Sede::count());
        $this->assertSame(2, User::count());
    }

    public function test_sin_datos_en_el_env_no_crea_cuentas_a_medias(): void
    {
        $this->sembrar([]);

        $this->assertSame(4, Sede::count());
        $this->assertSame(0, User::count());
    }

    /* ── A12: deshacer migraciones no borra dinero ── */

    /** Una matrícula con su cobro de derecho: basta con que la BD tenga dinero. */
    private function unCobro(): void
    {
        $this->prepararSede();
        $this->actingAs($this->admin);
        $grupo = $this->grupo($this->programa(['Mañana']), 'Mañana');
        app(MatriculaService::class)->matricular(User::factory()->create(['role' => 'estudiante', 'sede_id' => $this->sede->id]), $grupo, null);
        $this->assertTrue(DB::table('pagos')->exists());
    }

    private function deshacer(string $archivo): void
    {
        $migracion = require database_path("migrations/{$archivo}.php");
        event(new MigrationStarted($migracion, 'down'));
    }

    public function test_no_se_deshace_una_migracion_con_dinero_en_la_bd(): void
    {
        $this->deshacer('2026_11_11_000003_add_vigencia_to_curso_sesiones_table');   // BD sin datos: se puede

        $this->unCobro();
        $this->expectException(RuntimeException::class);
        $this->deshacer('2026_11_11_000003_add_vigencia_to_curso_sesiones_table');
    }

    public function test_las_migraciones_anteriores_al_07_10_no_se_bloquean(): void
    {
        $this->unCobro();
        $anterior = collect(glob(database_path('migrations/2026_10_06_*.php')))->map(fn ($f) => basename($f, '.php'))->first();
        $this->deshacer($anterior);
        $this->assertNotNull($anterior);
    }
}
