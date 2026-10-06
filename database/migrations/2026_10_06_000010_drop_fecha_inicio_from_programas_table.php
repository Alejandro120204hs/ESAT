<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un programa no "empieza": empiezan sus grupos. La fecha de inicio vive en
     * cursos.fecha_inicio y la de fin se calcula con la duración del programa.
     */
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropColumn('fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable()->after('cupo_grupo');
        });
    }
};
