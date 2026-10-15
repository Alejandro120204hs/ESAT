<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asistencia (la toma el docente; el Admin solo la consulta):
     * - motivo: por qué se excusó (incapacidad, cita médica...). Solo aplica a estado = excusa.
     * - registrado_por: el docente que tomó la lista. Una clase "sin registrar" es un día de clase
     *   ya pasado del horario del grupo sin ningún registro.
     */
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->string('motivo', 255)->nullable()->after('estado');
            $table->foreignId('registrado_por')->nullable()->after('motivo')->constrained('users')->nullOnDelete();
            $table->index(['curso_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrado_por');
            $table->dropIndex(['curso_id', 'fecha']);
            $table->dropColumn('motivo');
        });
    }
};
