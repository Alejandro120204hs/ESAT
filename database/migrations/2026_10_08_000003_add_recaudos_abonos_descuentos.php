<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abonos, recibos, descuentos y anulación de matrículas (panel Admin › Matrículas):
     * - recaudos: cada pago que recibe la sede (un recibo RC-AAAA-NNNNN). Se reparte entre los cobros
     *   de la matrícula del más viejo al más nuevo (recaudo_pago guarda cuánto fue a cada uno), así una
     *   cuota puede quedar abonada. Anular un pago anula el recaudo entero; su recibo no se reutiliza.
     * - pagos.abonado: lo que ya se ha pagado de ese cobro; pagos.descuento: lo que se rebaja del valor
     *   del programa (monto_total = monto_programa - descuento + monto_sistema).
     * - matriculas.descuento_pct / descuento_motivo: beca o convenio, en porcentaje sobre el programa.
     * - matriculas.estado: + 'anulado' (matrícula hecha por error; no cuenta para nada).
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->enum('estado', ['activo', 'aplazado', 'retirado', 'graduado', 'anulado'])->default('activo')->change();
            $table->decimal('descuento_pct', 5, 2)->default(0)->after('plan_pago');
            $table->string('descuento_motivo', 255)->nullable()->after('descuento_pct');
        });

        Schema::create('recaudos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->string('recibo', 20)->unique();
            $table->date('fecha_pago');
            $table->decimal('valor', 12, 2);
            $table->string('metodo_pago', 30);
            $table->string('referencia', 60)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('recaudo_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recaudo_id')->constrained('recaudos')->cascadeOnDelete();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('descuento', 12, 2)->default(0)->after('monto_programa');
            $table->decimal('abonado', 12, 2)->default(0)->after('monto_total');
        });

        // Los pagos ya registrados pasan a ser recaudos (un recaudo por recibo)
        $pagados = DB::table('pagos')->where('estado', 'pagado')->whereNotNull('recibo')->orderBy('id')->get()->groupBy('recibo');
        foreach ($pagados as $recibo => $cobros) {
            $primero = $cobros->first();
            $fecha   = substr((string) $primero->fecha_pago, 0, 10) ?: now()->toDateString();
            $id = DB::table('recaudos')->insertGetId([
                'matricula_id'   => $primero->matricula_id,
                'recibo'         => $recibo,
                'fecha_pago'     => $fecha,
                'valor'          => $cobros->sum('monto_total'),
                'metodo_pago'    => $primero->metodo_pago ?: 'Efectivo',
                'referencia'     => $primero->referencia_pasarela,
                'observacion'    => $primero->observacion,
                'registrado_por' => $primero->registrado_por,
                'created_at'     => $primero->fecha_pago ?? now(),
                'updated_at'     => $primero->fecha_pago ?? now(),
            ]);
            foreach ($cobros as $c) {
                DB::table('recaudo_pago')->insert(['recaudo_id' => $id, 'pago_id' => $c->id, 'monto' => $c->monto_total]);
                DB::table('pagos')->where('id', $c->id)->update(['abonado' => $c->monto_total]);
            }
        }

        // Los datos del pago ahora viven en recaudos
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrado_por');
            $table->dropIndex(['recibo']);
            $table->dropColumn(['recibo', 'observacion']);
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('recibo', 20)->nullable()->index()->after('fecha_pago');
            $table->foreignId('registrado_por')->nullable()->after('metodo_pago')->constrained('users')->nullOnDelete();
            $table->string('observacion', 255)->nullable()->after('registrado_por');
        });

        // Cada cobro pagado recupera el recibo y los datos de su último recaudo vigente
        $filas = DB::table('recaudo_pago')->join('recaudos', 'recaudos.id', '=', 'recaudo_pago.recaudo_id')
            ->whereNull('recaudos.anulado_en')->orderBy('recaudos.id')
            ->get(['recaudo_pago.pago_id', 'recaudos.recibo', 'recaudos.registrado_por', 'recaudos.observacion']);
        foreach ($filas as $f) {
            DB::table('pagos')->where('id', $f->pago_id)->where('estado', 'pagado')
                ->update(['recibo' => $f->recibo, 'registrado_por' => $f->registrado_por, 'observacion' => $f->observacion]);
        }

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['descuento', 'abonado']);
        });
        Schema::dropIfExists('recaudo_pago');
        Schema::dropIfExists('recaudos');

        DB::table('matriculas')->where('estado', 'anulado')->update(['estado' => 'retirado']);
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn(['descuento_pct', 'descuento_motivo']);
            $table->enum('estado', ['activo', 'aplazado', 'retirado', 'graduado'])->default('activo')->change();
        });
    }
};
