<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lugar donde vive (distinto del lugar de nacimiento). Aplica a cualquier rol.
        Schema::table('users', function (Blueprint $table) {
            $table->string('departamento_residencia')->nullable()->after('direccion');
            $table->string('ciudad_residencia')->nullable()->after('departamento_residencia');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['departamento_residencia', 'ciudad_residencia']);
        });
    }
};
