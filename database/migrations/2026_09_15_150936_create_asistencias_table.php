<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->enum('estado', ['presente', 'ausente', 'tarde', 'excusa']);
            $table->timestamps();
            $table->unique(['curso_id', 'estudiante_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
