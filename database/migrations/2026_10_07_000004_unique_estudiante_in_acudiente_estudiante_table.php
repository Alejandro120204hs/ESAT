<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada estudiante tiene un solo acudiente (un acudiente sí puede tener varios estudiantes).
        // El código ya reemplaza al anterior al vincular; esto lo garantiza también en la BD.
        Schema::table('acudiente_estudiante', function (Blueprint $table) {
            $table->unique('estudiante_id');
        });
    }

    public function down(): void
    {
        Schema::table('acudiente_estudiante', function (Blueprint $table) {
            $table->dropUnique(['estudiante_id']);
        });
    }
};
