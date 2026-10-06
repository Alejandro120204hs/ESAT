<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos propios del rol docente (1 a 1 con users). Los datos personales,
        // de contacto, título (profesion) y especialidad (titulo_academico) siguen en users.
        Schema::create('docente_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('escuela_id')->constrained('escuelas');
            $table->enum('vinculacion', ['tiempo_completo', 'medio_tiempo', 'hora_catedra']);
            // en_proceso = contratación en trámite; solo "activo" puede ingresar al sistema
            $table->enum('estado', ['activo', 'en_proceso', 'inactivo'])->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_perfiles');
    }
};
