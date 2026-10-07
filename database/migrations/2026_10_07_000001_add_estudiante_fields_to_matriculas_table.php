<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La matrícula es la inscripción del estudiante en un programa (panel Admin › Estudiantes):
     * - codigo: EST-AAAA-NNNN, el número que identifica la inscripción.
     * - estado: activo (ocupa cupo), aplazado, retirado o graduado; con la fecha y el motivo del cambio.
     * - plan_pago: lo elige el estudiante desde su panel; mientras tanto queda en null ("por elegir").
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->string('codigo', 20)->unique()->after('id');
            $table->enum('plan_pago', ['mensual', 'periodo'])->nullable()->change();
            $table->enum('estado', ['activo', 'aplazado', 'retirado', 'graduado'])->default('activo')->change();
            $table->date('estado_fecha')->nullable()->after('estado');
            $table->string('estado_motivo', 255)->nullable()->after('estado_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'estado_fecha', 'estado_motivo']);
            $table->enum('plan_pago', ['mensual', 'periodo'])->nullable(false)->change();
            $table->enum('estado', ['activo', 'bloqueado', 'finalizado', 'retirado'])->default('activo')->change();
        });
    }
};
