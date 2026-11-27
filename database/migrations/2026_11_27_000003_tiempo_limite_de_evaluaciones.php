<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiempo límite de una evaluación del Aula virtual (decidido con el usuario el 27-11-2026): el docente elige los minutos
 * (o sin límite). El tiempo de cada estudiante cuenta desde que la comienza (evaluacion_intentos) y, si se acaba, se
 * envía lo que haya marcado: lo que no respondió queda sin opción (respuestas_estudiante.opcion_id null) y cuenta como malo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('duracion_minutos')->nullable()->after('fecha_limite');
        });

        Schema::create('evaluacion_intentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('iniciada_en');
            $table->timestamps();
            $table->unique(['evaluacion_id', 'estudiante_id']);
        });

        Schema::table('respuestas_estudiante', function (Blueprint $table) {
            $table->foreignId('opcion_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('respuestas_estudiante', function (Blueprint $table) {
            $table->foreignId('opcion_id')->nullable(false)->change();
        });
        Schema::dropIfExists('evaluacion_intentos');
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropColumn('duracion_minutos');
        });
    }
};
