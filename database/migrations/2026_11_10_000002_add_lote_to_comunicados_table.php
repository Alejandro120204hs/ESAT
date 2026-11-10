<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un comunicado que el Admin de ESAT envía a varias sedes se guarda como una fila por sede (cada secretaría ve el suyo
 * y cuenta sus lecturas); `lote` las agrupa para que el Admin lo vea, edite y archive como uno solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comunicados', function (Blueprint $table) {
            $table->uuid('lote')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('comunicados', function (Blueprint $table) {
            $table->dropIndex(['lote']);
            $table->dropColumn('lote');
        });
    }
};
