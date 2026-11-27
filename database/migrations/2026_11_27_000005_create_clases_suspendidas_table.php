<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clases que no se dictaron (decidido con el usuario el 27-11-2026): el docente marca "No hubo clase" con un motivo (un
 * paro, una calamidad, una salida). Ese día no cuenta como clase en ninguna parte. Los festivos de Colombia no necesitan
 * fila: los calcula App\Support\Festivos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clases_suspendidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('motivo', 200);
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['curso_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clases_suspendidas');
    }
};
