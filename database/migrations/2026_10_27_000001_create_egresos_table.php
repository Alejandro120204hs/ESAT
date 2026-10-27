<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Egresos de la sede (panel Admin › Contabilidad), con comprobante CE-AAAA-NNNNN (consecutivo en
     * configuraciones 'ultimo_egreso_AAAA', nunca retrocede). Categorías: nomina (pago a un docente de la
     * sede, docente_id), arriendo (arriendo y servicios) y materiales (materiales e insumos). Las
     * devoluciones a estudiantes no van aquí: Contabilidad las toma de la tabla devoluciones.
     * Un egreso no se borra: se anula con motivo (anulado_en / anulado_por / motivo_anulacion).
     */
    public function up(): void
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('comprobante', 20)->unique();
            $table->date('fecha');
            $table->enum('categoria', ['nomina', 'arriendo', 'materiales']);
            $table->string('concepto', 160);
            $table->foreignId('docente_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('beneficiario', 120);
            $table->decimal('valor', 12, 2);
            $table->string('metodo_pago', 30);
            $table->string('referencia', 60)->nullable();
            $table->string('factura', 60)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
            $table->index(['sede_id', 'fecha']);
        });
    }

    /** Sin rollback con datos: borraría la contabilidad de la sede (ver HALLAZGOS A12). */
    public function down(): void
    {
        if (Schema::hasTable('egresos') && DB::table('egresos')->exists()) {
            throw new RuntimeException('La tabla egresos tiene datos: deshacer esta migración borraría la contabilidad de las sedes.');
        }
        Schema::dropIfExists('egresos');
    }
};
