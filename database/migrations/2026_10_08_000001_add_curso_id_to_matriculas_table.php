<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La matrícula guarda su grupo. Antes se deducía cruzando curso_estudiante con el programa,
     * y si el estudiante volvía al mismo programa (tras retirarse) quedaba en dos grupos a la vez.
     * curso_estudiante sigue siendo la lista del grupo (la usan Grupos, Horarios y Asistencia).
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreignId('curso_id')->nullable()->after('programa_id')->constrained('cursos')->restrictOnDelete();
        });

        // Las existentes: el grupo del estudiante en el programa de la matrícula (SQL válido en MySQL y SQLite)
        DB::statement('
            UPDATE matriculas SET curso_id = (
                SELECT ce.curso_id FROM curso_estudiante ce
                JOIN cursos c ON c.id = ce.curso_id
                WHERE ce.estudiante_id = matriculas.estudiante_id
                  AND c.programa_id = matriculas.programa_id
                  AND c.sede_id = matriculas.sede_id
                ORDER BY ce.id DESC LIMIT 1
            ) WHERE curso_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curso_id');
        });
    }
};
