<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HALLAZGOS B35. Borrar un usuario (desde phpMyAdmin, tinker o un futuro "eliminar") se llevaba en cascada sus matrículas
 * y con ellas cobros, recibos, devoluciones y notas. Ninguna pantalla borra nada de eso (las matrículas se anulan, los
 * recibos se anulan), así que la BD ahora lo rechaza: quien intente borrarlo recibe un error y no se pierde el historial.
 */
return new class extends Migration
{
    /** [tabla, columna, tabla a la que apunta] */
    private const LLAVES = [
        ['matriculas', 'estudiante_id', 'users'],
        ['pagos', 'matricula_id', 'matriculas'],
        ['recaudos', 'matricula_id', 'matriculas'],
        ['devoluciones', 'matricula_id', 'matriculas'],
        ['recaudo_pago', 'recaudo_id', 'recaudos'],
        ['recaudo_pago', 'pago_id', 'pagos'],
        ['devolucion_pago', 'devolucion_id', 'devoluciones'],
        ['devolucion_pago', 'pago_id', 'pagos'],
        ['calificaciones', 'matricula_id', 'matriculas'],
    ];

    public function up(): void
    {
        $this->cambiar('restrict');
    }

    public function down(): void
    {
        $this->cambiar('cascade');
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
