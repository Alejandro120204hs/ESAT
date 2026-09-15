<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('users');
            $table->string('titulo');
            $table->text('instrucciones')->nullable();
            $table->dateTime('fecha_limite');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
