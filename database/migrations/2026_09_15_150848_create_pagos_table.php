<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('periodo_academico_id')->constrained('periodos_academicos');
            $table->enum('tipo', ['mensual', 'periodo']);
            // Solo aplica cuando tipo = mensual; identifica qué mes del
            // periodo cubre este pago específico.
            $table->unsignedTinyInteger('mes')->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            // Valor de la carrera (proporcional al periodo o al mes) y
            // valor de la cuota del sistema ($/mes, tomado de
            // configuraciones al momento del pago), sumados en monto_total.
            $table->decimal('monto_programa', 12, 2);
            $table->decimal('monto_sistema', 12, 2);
            $table->decimal('monto_total', 12, 2);
            $table->enum('estado', ['pendiente', 'pagado', 'vencido'])->default('pendiente');
            $table->timestamp('fecha_pago')->nullable();
            $table->string('referencia_pasarela')->nullable();
            $table->string('metodo_pago')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
