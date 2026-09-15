<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->string('archivo_path')->nullable();
            $table->text('contenido')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->decimal('calificacion', 5, 2)->nullable();
            $table->text('comentario_docente')->nullable();
            $table->timestamps();
            $table->unique(['actividad_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
