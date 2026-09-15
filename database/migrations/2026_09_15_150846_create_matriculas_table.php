<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('programa_id')->constrained('programas');
            $table->foreignId('sede_id')->constrained('sedes');
            // Nulo hasta que la matrícula quede activa en un periodo real.
            $table->foreignId('periodo_actual_id')->nullable()->constrained('periodos_academicos')->nullOnDelete();
            $table->enum('plan_pago', ['mensual', 'periodo']);
            $table->enum('estado', ['activo', 'bloqueado', 'finalizado', 'retirado'])->default('activo');
            $table->date('fecha_inicio');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
