<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HALLAZGOS B35 (2.ª parte). Borrar una cuenta dejaba en null quién registró o anuló un recibo, una devolución, un egreso,
 * una nota o una asistencia, y de quién era cada línea de la auditoría. Las cuentas no se borran (se desactivan), así que
 * la BD ahora lo rechaza mientras la cuenta tenga algo a su nombre. Foro y comunicados siguen en null: no son historial de
 * dinero ni académico.
 */
return new class extends Migration
{
    /** [tabla, columna, tabla a la que apunta] */
    private const LLAVES = [
        ['auditorias', 'user_id', 'users'],
        ['recaudos', 'registrado_por', 'users'],
        ['recaudos', 'anulado_por', 'users'],
        ['devoluciones', 'registrado_por', 'users'],
        ['egresos', 'registrado_por', 'users'],
        ['egresos', 'anulado_por', 'users'],
        ['asistencias', 'registrado_por', 'users'],
        ['calificaciones', 'registrado_por', 'users'],
        ['matriculas', 'origen_id', 'matriculas'],
    ];

    public function up(): void
    {
        $this->cambiar('restrict');
    }

    public function down(): void
    {
        $this->cambiar('set null');
    }

    private function cambiar(string $alBorrar): void
    {
        foreach (self::LLAVES as [$tabla, $columna, $destino]) {
            Schema::table($tabla, function (Blueprint $t) use ($columna, $destino, $alBorrar) {
                $t->dropForeign([$columna]);
                $t->foreign($columna)->references('id')->on($destino)->onDelete($alBorrar);
            });
        }
    }
};
