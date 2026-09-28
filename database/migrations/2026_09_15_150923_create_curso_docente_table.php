<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un docente puede dictar cursos en varias sedes/programas, y un
        // curso podría tener más de un docente asignado.
        Schema::create('curso_docente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['curso_id', 'docente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_docente');
    }
};
