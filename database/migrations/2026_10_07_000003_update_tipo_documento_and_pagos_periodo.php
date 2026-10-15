<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - users.tipo_documento: se agrega PPT (Permiso por Protección Temporal), que ofrece la inscripción.
     * - pagos.periodo_academico_id: una cuota mensual no pertenece a un periodo académico
     *   (se identifica por mes y año); solo las del plan por periodo lo usan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tipo_documento', ['CC', 'TI', 'CE', 'PA', 'PPT'])->nullable()->change();
        });
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('periodo_academico_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('periodo_academico_id')->nullable(false)->change();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tipo_documento', ['CC', 'TI', 'CE', 'PA'])->nullable()->change();
        });
    }
};
