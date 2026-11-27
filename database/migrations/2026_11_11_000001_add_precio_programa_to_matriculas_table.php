<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor del programa pactado al matricular: las cuotas se calculan con este, no con el precio vigente del programa,
 * para que un cambio de precio en el catálogo no le cambie lo que paga a quien ya está matriculado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->decimal('precio_programa', 12, 2)->nullable()->after('programa_id');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn('precio_programa');
        });
    }
};
