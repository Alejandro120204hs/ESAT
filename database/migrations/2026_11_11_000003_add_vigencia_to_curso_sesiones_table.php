<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desde cuándo y hasta cuándo rige cada sesión del horario (HALLAZGOS A7). Al cambiar el horario de un grupo que ya
 * dictó clases con algo registrado, el horario de antes queda con `hasta` = el día del cambio y el nuevo empieza al día
 * siguiente: la asistencia, las fechas de los módulos y los reportes del pasado no cambian. null = desde el inicio del
 * grupo / hasta su fin. El horario vigente es el de `hasta` null (Curso::sesiones()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curso_sesiones', function (Blueprint $table) {
            $table->date('desde')->nullable()->after('hora_fin');
            $table->date('hasta')->nullable()->after('desde');
        });
    }

    public function down(): void
    {
        Schema::table('curso_sesiones', function (Blueprint $table) {
            $table->dropColumn(['desde', 'hasta']);
        });
    }
};
