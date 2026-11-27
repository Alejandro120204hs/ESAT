<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * País de nacimiento de quien nació fuera de Colombia (decidido con el usuario el 27-11-2026): null = Colombia, con
 * departamento_nacimiento + lugar_nacimiento del listado; con país, lugar_nacimiento es la ciudad escrita y no hay
 * departamento. Solo lo registra un estudiante con cédula de extranjería o PPT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pais_nacimiento', 80)->nullable()->after('departamento_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pais_nacimiento');
        });
    }
};
