<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Oferta: qué programas del catálogo global se dictan en cada sede.
        Schema::create('programa_sede', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['programa_id', 'sede_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_sede');
    }
};
