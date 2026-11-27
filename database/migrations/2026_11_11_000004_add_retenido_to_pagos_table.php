<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parte de lo abonado a un cobro que se queda la institución aunque el cobro se anule: hoy, el derecho de matrícula de
 * quien se retira antes de empezar clases (se le devuelve el porcentaje de Configuración, `devolucion_derecho_pct`; el
 * resto queda retenido). Lo que se puede devolver o trasladar de un cobro es abonado − reembolsado − retenido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('retenido', 12, 2)->default(0)->after('reembolsado');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('retenido');
        });
    }
};
