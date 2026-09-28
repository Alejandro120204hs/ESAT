<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Valores globales editables por el Superadmin (ej. la cuota
        // mensual del sistema) sin quedar fijos en el código.
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('valor');
            $table->timestamps();
        });

        DB::table('configuraciones')->insert([
            'clave' => 'cuota_sistema_mensual',
            'valor' => '2000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
