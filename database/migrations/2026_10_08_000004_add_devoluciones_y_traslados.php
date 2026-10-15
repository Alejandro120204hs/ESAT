<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devoluciones y cambio de programa (panel Admin › Matrículas):
     * - pagos.reembolsado: parte de lo abonado a un cobro que ya no se queda ahí porque se devolvió
     *   al estudiante, se pasó a otra deuda o se trasladó a otra matrícula. Lo que realmente pagó
     *   de ese cobro = abonado - reembolsado.
     * - matriculas.saldo_favor: dinero trasladado (cambio de programa, reintegro) que todavía no se
     *   ha aplicado a ningún cobro; se aplica solo, en orden, cuando aparecen cobros.
     * - matriculas.origen_id: la matrícula de la que viene por un cambio de programa.
     * - devoluciones (+ devolucion_pago): dinero adelantado que se le devuelve al estudiante al
     *   retirarse o aplazarse, con comprobante DV-AAAA-NNNNN.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('reembolsado', 12, 2)->default(0)->after('abonado');
        });

        Schema::table('matriculas', function (Blueprint $table) {
            $table->decimal('saldo_favor', 12, 2)->default(0)->after('descuento_motivo');
            $table->foreignId('origen_id')->nullable()->after('saldo_favor')->constrained('matriculas')->nullOnDelete();
        });

        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->string('comprobante', 20)->unique();
            $table->date('fecha');
            $table->decimal('valor', 12, 2);
            $table->string('metodo_pago', 30);
            $table->string('referencia', 60)->nullable();
            $table->string('motivo', 255);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('devolucion_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_pago');
        Schema::dropIfExists('devoluciones');
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origen_id');
            $table->dropColumn('saldo_favor');
        });
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('reembolsado');
        });
    }
};
