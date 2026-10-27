<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comunicados de una sede (panel Admin › Comunicados):
     * - El admin publica a toda la sede o a un rol (destino sede | estudiantes | docentes | acudientes); el docente
     *   publica a uno de sus grupos (destino grupo + curso_id). Los destinatarios se calculan al leer (quien hoy
     *   pertenece a ese rol o grupo), no se copian.
     * - estado: borrador | programado (sale solo cuando llega publicado_en) | publicado | archivado (con motivo).
     * - El adjunto (PDF, JPG o PNG) se guarda en el disco privado; solo se descarga por la app.
     * - comunicado_lecturas: quién lo abrió en su panel y cuándo (una vez por persona).
     */
    public function up(): void
    {
        Schema::create('comunicados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();
            $table->string('titulo', 120);
            $table->text('mensaje')->nullable();
            $table->enum('categoria', ['general', 'academico', 'pagos', 'evento', 'urgente'])->default('general');
            $table->enum('destino', ['sede', 'estudiantes', 'docentes', 'acudientes', 'grupo']);
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->restrictOnDelete();
            $table->enum('estado', ['borrador', 'programado', 'publicado', 'archivado'])->default('borrador');
            $table->timestamp('publicado_en')->nullable();
            $table->date('visible_hasta')->nullable();
            $table->boolean('fijado')->default(false);
            $table->string('adjunto_ruta')->nullable();
            $table->string('adjunto_nombre', 150)->nullable();
            $table->string('adjunto_tipo', 10)->nullable();
            $table->unsignedInteger('adjunto_peso')->nullable();
            $table->timestamp('archivado_en')->nullable();
            $table->foreignId('archivado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_archivo', 255)->nullable();
            $table->timestamps();
            $table->index(['sede_id', 'estado']);
        });

        Schema::create('comunicado_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunicado_id')->constrained('comunicados')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('leido_en');
            $table->unique(['comunicado_id', 'user_id']);
        });
    }

    /** Sin rollback con datos: borraría los comunicados de las sedes y quién los leyó (ver HALLAZGOS A12). */
    public function down(): void
    {
        if (Schema::hasTable('comunicados') && DB::table('comunicados')->exists()) {
            throw new RuntimeException('La tabla comunicados tiene datos: deshacer esta migración borraría los comunicados de las sedes.');
        }
        Schema::dropIfExists('comunicado_lecturas');
        Schema::dropIfExists('comunicados');
    }
};
