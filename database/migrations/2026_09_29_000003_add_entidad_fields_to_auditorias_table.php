<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->string('entidad_tipo')->nullable()->after('accion');
            $table->unsignedBigInteger('entidad_id')->nullable()->after('entidad_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropColumn(['entidad_tipo', 'entidad_id']);
        });
    }
};
