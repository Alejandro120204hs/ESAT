<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada fila de pagos es un cobro (cuota o derecho de matrícula) y, cuando se paga, su registro:
     * - tipo: + 'matricula' (derecho de matrícula del programa, se cobra al matricular).
     * - estado: + 'anulado' (cuotas que ya no se deben: retiro o aplazamiento).
     * - numero_cuota / fecha_vencimiento: el plan de pagos completo ("cuota 3 de 12, vence el 10 nov").
     * - recibo / registrado_por / observacion: quién recibió el pago y con qué comprobante.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->enum('tipo', ['matricula', 'mensual', 'periodo'])->change();
            $table->enum('estado', ['pendiente', 'pagado', 'vencido', 'anulado'])->default('pendiente')->change();
            $table->unsignedTinyInteger('numero_cuota')->nullable()->after('tipo');
            $table->date('fecha_vencimiento')->nullable()->after('anio');
            $table->string('recibo', 20)->nullable()->index()->after('fecha_pago');
            $table->foreignId('registrado_por')->nullable()->after('metodo_pago')->constrained('users')->nullOnDelete();
            $table->string('observacion', 255)->nullable()->after('registrado_por');
        });

        // Cuotas existentes: vencen el día 10 de su mes y se numeran en orden por matrícula
        $porMatricula = DB::table('pagos')->whereIn('tipo', ['mensual', 'periodo'])
            ->orderBy('matricula_id')->orderBy('anio')->orderBy('mes')->orderBy('id')
            ->get(['id', 'matricula_id', 'mes', 'anio'])->groupBy('matricula_id');
        foreach ($porMatricula as $cuotas) {
            foreach ($cuotas->values() as $i => $c) {
                DB::table('pagos')->where('id', $c->id)->update([
                    'numero_cuota'      => $i + 1,
                    'fecha_vencimiento' => $c->anio && $c->mes ? sprintf('%04d-%02d-10', $c->anio, $c->mes) : null,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pagos')->where('tipo', 'matricula')->delete();
        DB::table('pagos')->where('estado', 'anulado')->update(['estado' => 'pendiente']);

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrado_por');
            $table->dropIndex(['recibo']);
            $table->dropColumn(['numero_cuota', 'fecha_vencimiento', 'recibo', 'observacion']);
            $table->enum('tipo', ['mensual', 'periodo'])->change();
            $table->enum('estado', ['pendiente', 'pagado', 'vencido'])->default('pendiente')->change();
        });
    }
};
