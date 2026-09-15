<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_estudiante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pregunta_id')->constrained('preguntas')->cascadeOnDelete();
            $table->foreignId('opcion_id')->constrained('opciones');
            // Se guarda el acierto al momento de responder, tomado de
            // opciones.es_correcta, para no depender de un join al calificar.
            $table->boolean('es_correcta');
            $table->timestamps();
            $table->unique(['evaluacion_id', 'estudiante_id', 'pregunta_id'], 'respuestas_estudiante_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_estudiante');
    }
};
