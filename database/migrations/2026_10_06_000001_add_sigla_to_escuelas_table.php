<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            // Sigla corta (SAL, TUR, ADM...): arma el código de los programas
            // (ESA-SAL-001) y define el color de la escuela en los paneles.
            $table->string('sigla', 5)->unique()->after('nombre');
            $table->unique('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
            $table->dropUnique(['sigla']);
            $table->dropColumn('sigla');
        });
    }
};
