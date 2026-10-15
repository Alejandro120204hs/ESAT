<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista de chequeo de los documentos del estudiante (EstudianteDocumento::REQUERIDOS).
     * Son de la persona, no de cada matrícula: se entregan una vez.
     * entregado_en null = pendiente.
     */
    public function up(): void
    {
        Schema::create('estudiante_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->string('documento', 120);
            $table->timestamp('entregado_en')->nullable();
            $table->timestamps();
            $table->unique(['estudiante_id', 'documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_documentos');
    }
};
