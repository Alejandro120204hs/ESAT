<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calificaciones (la nota la pone el docente; el Admin solo la consulta):
     * una nota por matrícula y módulo de la malla (programa_modulos), de 1.0 a 5.0, aprueba 3.0.
     * - habilitacion: la única nota de recuperación de un módulo perdido (solo si nota < 3.0).
     * - curso_id: grupo en el que se calificó (la nota sigue a la matrícula si cambia de grupo).
     * - registrado_por: el docente que la puso.
     * El módulo no se puede borrar si tiene notas (restrict): las notas no desaparecen solas.
     */
    public function up(): void
    {
        Schema::create('calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('programa_modulo_id')->constrained('programa_modulos')->restrictOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->decimal('nota', 2, 1);
            $table->date('fecha_nota');
            $table->decimal('habilitacion', 2, 1)->nullable();
            $table->date('fecha_habilitacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['matricula_id', 'programa_modulo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calificaciones');
    }
};
