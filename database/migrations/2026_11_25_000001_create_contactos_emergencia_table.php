<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacto de emergencia del estudiante (decidido con el usuario el 25-11-2026): reemplaza al acudiente, que ya no tiene
 * cuenta ni panel. Uno por estudiante, obligatorio: nombre, celular y parentesco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos_emergencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('celular', 10);
            $table->string('parentesco', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos_emergencia');
    }
};
