<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Lo mínimo para que una instalación nueva arranque (HALLAZGOS A11): las 4 sedes reales de ESAT y las dos cuentas
 * iniciales, el Superadmin (quien instala) y el Admin de toda ESAT, con los datos del .env (SUPERADMIN_* y
 * ADMIN_GENERAL_*). La clave inicial es el número de documento, como en toda cuenta. Se puede correr otra vez sin
 * duplicar nada. No trae datos de prueba: el resto (programas, secretarías, estudiantes) se crea desde los paneles.
 */
class DatabaseSeeder extends Seeder
{
    /** Las sedes reales (las mismas direcciones de config/sitio.php). */
    private const SEDES = [
        ['nombre' => 'Bogotá', 'departamento' => 'Cundinamarca', 'ciudad' => 'Bogotá', 'direccion' => 'Cra 52 #71-18'],
        ['nombre' => 'Sasaima', 'departamento' => 'Cundinamarca', 'ciudad' => 'Sasaima', 'direccion' => 'Urbanización San José, Cra 2 #8-84'],
        ['nombre' => 'Guaduas', 'departamento' => 'Cundinamarca', 'ciudad' => 'Guaduas', 'direccion' => 'Cra 2 No. 1-83, Calle de la Pola'],
        ['nombre' => 'La Dorada', 'departamento' => 'Caldas', 'ciudad' => 'La Dorada', 'direccion' => 'Calle 9 No. 7-55, Barrio Magdalena'],
    ];

    public function run(): void
    {
        foreach (self::SEDES as $sede) {
            Sede::firstOrCreate(['nombre' => $sede['nombre']], $sede);
        }

        $this->cuenta('SUPERADMIN', RolUsuario::Superadmin);
        $this->cuenta('ADMIN_GENERAL', RolUsuario::AdminGeneral);
    }

    /** Crea la cuenta con los datos de {$prefijo}_EMAIL, _DOCUMENTO, _NOMBRES y _APELLIDOS, si no existe ya. */
    private function cuenta(string $prefijo, RolUsuario $rol): void
    {
        $email = mb_strtolower(trim((string) env("{$prefijo}_EMAIL")));
        $documento = preg_replace('/\D/', '', (string) env("{$prefijo}_DOCUMENTO"));
        if ($email === '' || $documento === '') {
            $this->command?->warn("Falta {$prefijo}_EMAIL o {$prefijo}_DOCUMENTO en el .env: no se creó la cuenta {$rol->label()}.");

            return;
        }
        if (User::where('email', $email)->orWhere('numero_documento', $documento)->exists()) {
            $this->command?->info("La cuenta {$rol->label()} ({$email}) ya existía.");

            return;
        }

        User::create([
            'role'             => $rol,
            'sede_id'          => null,
            'nombres'          => (string) (env("{$prefijo}_NOMBRES") ?: $rol->label()),
            'apellidos'        => (string) (env("{$prefijo}_APELLIDOS") ?: 'ESAT'),
            'tipo_documento'   => 'CC',
            'numero_documento' => $documento,
            'email'            => $email,
            'password'         => Hash::make($documento),   // la clave inicial es el número de documento
            'activo'           => true,
        ]);
        $this->command?->info("Se creó la cuenta {$rol->label()} ({$email}). Su clave inicial es su número de documento.");
    }
}
