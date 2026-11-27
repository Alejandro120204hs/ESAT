<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una sola matrícula vigente (activa o aplazada) por estudiante, garantizado por la BD: `vigente_de` es el estudiante
 * mientras la matrícula está vigente y null cuando no (el índice único permite muchos null). MatriculaService ya lo
 * revisa con el estudiante bloqueado; esto cubre cualquier otro camino (dos peticiones a la vez, un insert directo).
 * Columna calculada: no se escribe nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->unsignedBigInteger('vigente_de')->nullable()
                ->virtualAs("case when estado in ('activo', 'aplazado') then estudiante_id end")
                ->after('estudiante_id');
            $table->unique('vigente_de');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropUnique(['vigente_de']);
            $table->dropColumn('vigente_de');
        });
    }
};
