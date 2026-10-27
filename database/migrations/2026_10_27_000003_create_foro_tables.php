<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foro general de cada sede (panel Admin › Foro): estudiantes, docentes, acudientes y administración abren
     * temas y responden.
     * - foro_temas: categoría fija, fijado arriba y cerrado (nadie más responde).
     * - foro_mensajes: el primero abre el tema; un adjunto opcional (PDF, JPG o PNG) en el disco privado. Un mensaje
     *   no se borra: se oculta con motivo (ocultar el primero oculta el tema) y se puede restaurar.
     * - foro_reportes: una persona reporta un mensaje una vez; el admin lo atiende (al ocultarlo) o lo descarta.
     */
    public function up(): void
    {
        Schema::create('foro_temas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();
            $table->enum('categoria', ['academico', 'practicas', 'tramites', 'vida']);
            $table->string('titulo', 120);
            $table->boolean('fijado')->default(false);
            $table->timestamp('cerrado_en')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sede_id', 'categoria']);
        });

        Schema::create('foro_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tema_id')->constrained('foro_temas')->cascadeOnDelete();
            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();
            $table->text('texto');
            $table->string('adjunto_ruta')->nullable();
            $table->string('adjunto_nombre', 150)->nullable();
            $table->string('adjunto_tipo', 10)->nullable();
            $table->unsignedInteger('adjunto_peso')->nullable();
            $table->timestamp('oculto_en')->nullable();
            $table->foreignId('oculto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_oculto', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('foro_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensaje_id')->constrained('foro_mensajes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('motivo', 255);
            $table->enum('estado', ['pendiente', 'atendido', 'descartado'])->default('pendiente');
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['mensaje_id', 'user_id']);
        });
    }

    /** Sin rollback con datos: borraría las conversaciones de las sedes (ver HALLAZGOS A12). */
    public function down(): void
    {
        if (Schema::hasTable('foro_temas') && DB::table('foro_temas')->exists()) {
            throw new RuntimeException('El foro tiene datos: deshacer esta migración borraría las conversaciones de las sedes.');
        }
        Schema::dropIfExists('foro_reportes');
        Schema::dropIfExists('foro_mensajes');
        Schema::dropIfExists('foro_temas');
    }
};
