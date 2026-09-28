<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solo aplican a docentes; quedan nulos para los demás roles.
        Schema::table('users', function (Blueprint $table) {
            $table->string('profesion')->nullable()->after('direccion');
            $table->string('titulo_academico')->nullable()->after('profesion');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profesion', 'titulo_academico']);
        });
    }
};
