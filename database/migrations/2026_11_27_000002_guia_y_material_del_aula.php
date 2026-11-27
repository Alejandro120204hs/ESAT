<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aula virtual del docente (pedido por el usuario el 27-11-2026): una actividad puede llevar un archivo de apoyo (la guía,
 * el caso, la rúbrica) y cada grupo tiene material de clase (archivo o enlace, opcionalmente de un módulo) que sus
 * estudiantes ven y descargan. Quien publicó material no se puede borrar (como toda autoría, B35).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->string('adjunto_path')->nullable()->after('instrucciones');
            $table->string('adjunto_nombre', 150)->nullable()->after('adjunto_path');
            $table->unsignedInteger('adjunto_tamano')->nullable()->after('adjunto_nombre');
        });

        Schema::create('materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('programa_modulo_id')->nullable()->constrained('programa_modulos')->nullOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('archivo_nombre', 150)->nullable();
            $table->unsignedInteger('archivo_tamano')->nullable();
            $table->string('enlace', 500)->nullable();
            $table->timestamps();
            $table->index(['curso_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiales');
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn(['adjunto_path', 'adjunto_nombre', 'adjunto_tamano']);
        });
    }
};
