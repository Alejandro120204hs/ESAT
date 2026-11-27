<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sin rol acudiente (decidido con el usuario el 25-11-2026): el estudiante tiene un contacto de emergencia
 * (contactos_emergencia). Se va la tabla acudiente_estudiante y el rol del ENUM. Las cuentas de acudiente se pasan a
 * contacto de emergencia y se borran ANTES (por SQL, con respaldo): esta migración no toca datos.
 */
return new class extends Migration
{
    private const SIN = ['superadmin', 'admin_general', 'admin', 'estudiante', 'docente'];
    private const CON = ['superadmin', 'admin_general', 'admin', 'estudiante', 'docente', 'acudiente'];

    public function up(): void
    {
        if (DB::table('users')->where('role', 'acudiente')->exists()) {
            throw new RuntimeException('Todavía hay cuentas de acudiente: pásalas a contacto de emergencia y bórralas antes de migrar.');
        }

        Schema::dropIfExists('acudiente_estudiante');
        $this->roles(self::SIN);
    }

    public function down(): void
    {
        $this->roles(self::CON);
        Schema::create('acudiente_estudiante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acudiente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('parentesco', 50)->nullable();
            $table->timestamps();
        });
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
