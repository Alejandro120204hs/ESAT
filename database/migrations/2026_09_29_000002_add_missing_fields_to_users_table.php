<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Separar departamento de nacimiento (lugar_nacimiento queda como ciudad)
            $table->string('departamento_nacimiento')->nullable()->after('lugar_nacimiento');

            // Control de acceso y bloqueo
            $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('password');
            $table->timestamp('bloqueado_en')->nullable()->after('intentos_fallidos');
            $table->enum('motivo_bloqueo', ['intentos_fallidos', 'inactividad', 'manual'])
                  ->nullable()->after('bloqueado_en');
            $table->timestamp('ultimo_acceso')->nullable()->after('motivo_bloqueo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'departamento_nacimiento',
                'intentos_fallidos',
                'bloqueado_en',
                'motivo_bloqueo',
                'ultimo_acceso',
            ]);
        });
    }
};
