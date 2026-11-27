<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de grupos de cada matrícula (decidido con el usuario el 27-11-2026): en qué grupo estuvo y desde cuándo hasta
 * cuándo (hasta = el día en que dejó de contar, sin incluirlo; null = sigue). Así un cambio de grupo o un reintegro no
 * borra al estudiante del grupo viejo ni lo pone en clases del grupo nuevo de antes de llegar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matricula_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->timestamps();
            $table->index(['curso_id', 'matricula_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricula_grupos');
    }
};
