<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La modalidad y el cupo de un grupo son siempre los de su programa
     * (se editan solo en Programas): no se duplican en cursos.
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['modalidad', 'cupo']);
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->enum('modalidad', ['Presencial', 'Virtual', 'Mixta'])->default('Presencial')->after('nombre');
            $table->unsignedTinyInteger('cupo')->default(20)->after('jornada');
        });
    }
};
