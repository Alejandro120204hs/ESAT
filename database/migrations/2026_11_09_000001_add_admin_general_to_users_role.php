<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rol admin_general: el Admin de toda ESAT (ve y actúa en todas las sedes, sin sede propia).
 * El rol 'admin' es la Secretaría de cada sede.
 */
return new class extends Migration
{
    private const CON = ['superadmin', 'admin_general', 'admin', 'estudiante', 'docente', 'acudiente'];
    private const SIN = ['superadmin', 'admin', 'estudiante', 'docente', 'acudiente'];

    public function up(): void
    {
        $this->roles(self::CON);
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'admin_general')->exists()) {
            throw new RuntimeException('Hay cuentas con rol admin_general: cámbiales el rol antes de revertir.');
        }

        $this->roles(self::SIN);
    }

    private function roles(array $roles): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $lista = implode(',', array_map(fn ($r) => "'{$r}'", $roles));
            DB::statement("ALTER TABLE users MODIFY role ENUM({$lista}) NOT NULL DEFAULT 'estudiante'");

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->default('estudiante')->change();
        });
    }
};
