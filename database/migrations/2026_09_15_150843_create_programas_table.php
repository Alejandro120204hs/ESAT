<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas');
            $table->string('nombre');
            // Precio total de la carrera completa. El costo mensual o por
            // periodo se calcula a partir de este valor y la duración,
            // sumando la cuota del sistema según los meses que cubra el pago.
            $table->decimal('precio_total', 12, 2);
            $table->unsignedSmallInteger('duracion_meses');
            $table->enum('tipo_periodo', ['semestre', 'trimestre']);
            $table->unsignedTinyInteger('duracion_periodos');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programas');
    }
};
