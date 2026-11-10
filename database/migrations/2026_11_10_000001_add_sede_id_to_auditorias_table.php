<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sede en la que se hizo cada acción. La secretaría actúa en su sede; el Admin de ESAT no tiene sede propia y actúa en
 * la que eligió al "Entrar a la sede", así que su actividad también debe salir en la de esa sede.
 * Las filas viejas quedan en null (se siguen encontrando por la sede de quien las hizo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->after('user_id')->constrained('sedes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });
    }
};
