<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aula virtual (panel Docente crea, panel Estudiante responde y entrega; 24-11-2026). Las tablas de septiembre no se
 * habían usado: cada evaluación y actividad va con un módulo de la malla del grupo (su nota es aparte de la del módulo),
 * la evaluación lleva una descripción, y la entrega guarda el nombre y el tamaño del archivo y quién la calificó y cuándo.
 * Quien calificó una entrega no se puede borrar (como quien registró notas, B35).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->foreignId('programa_modulo_id')->nullable()->after('docente_id')->constrained('programa_modulos')->nullOnDelete();
            $table->text('descripcion')->nullable()->after('titulo');
        });

        Schema::table('actividades', function (Blueprint $table) {
            $table->foreignId('programa_modulo_id')->nullable()->after('docente_id')->constrained('programa_modulos')->nullOnDelete();
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->string('archivo_nombre', 150)->nullable()->after('archivo_path');
            $table->unsignedInteger('archivo_tamano')->nullable()->after('archivo_nombre');
            $table->timestamp('calificado_en')->nullable()->after('comentario_docente');
            $table->foreignId('calificado_por')->nullable()->after('calificado_en')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calificado_por');
            $table->dropColumn(['archivo_nombre', 'archivo_tamano', 'calificado_en']);
        });
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programa_modulo_id');
        });
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programa_modulo_id');
            $table->dropColumn('descripcion');
        });
    }
};
