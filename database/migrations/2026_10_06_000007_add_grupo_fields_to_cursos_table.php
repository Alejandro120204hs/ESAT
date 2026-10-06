<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un "curso" es un grupo de un programa en una sede (panel Admin › Grupos).
     * "nombre" guarda el nombre del grupo: Grupo A, Grupo B...
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // Un grupo dura todo el programa: no depende de un periodo puntual
            $table->foreignId('periodo_academico_id')->nullable()->change();

            $table->string('codigo', 30)->unique()->after('sede_id');
            $table->enum('modalidad', ['Presencial', 'Virtual', 'Mixta'])->default('Presencial')->after('nombre');
            $table->enum('jornada', ['Mañana', 'Tarde', 'Noche', 'Fines de semana'])->after('modalidad');
            $table->unsignedTinyInteger('cupo')->after('jornada');
            $table->date('fecha_inicio')->after('cupo');
            $table->date('fecha_fin')->after('fecha_inicio');
            $table->enum('estado', ['planificacion', 'activo', 'finalizado'])->default('planificacion')->after('fecha_fin');

            $table->unique(['programa_id', 'sede_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropUnique(['programa_id', 'sede_id', 'nombre']);
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'modalidad', 'jornada', 'cupo', 'fecha_inicio', 'fecha_fin', 'estado']);
            $table->foreignId('periodo_academico_id')->nullable(false)->change();
        });
    }
};
