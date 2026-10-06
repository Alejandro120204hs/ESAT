<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan de estudios: módulos/asignaturas del programa, en orden.
        Schema::create('programa_modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedSmallInteger('horas');
            $table->unsignedTinyInteger('orden');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_modulos');
    }
};
