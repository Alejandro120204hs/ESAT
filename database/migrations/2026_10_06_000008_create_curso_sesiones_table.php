<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horario semanal del grupo: una fila por día de clase.
     * Un grupo puede tener horas distintas según el día.
     */
    public function up(): void
    {
        Schema::create('curso_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->enum('dia', ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->index(['dia', 'hora_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_sesiones');
    }
};
