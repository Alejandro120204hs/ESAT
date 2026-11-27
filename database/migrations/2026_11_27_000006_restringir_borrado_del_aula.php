<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HALLAZGOS ES29 (auditoría del panel Estudiante). Las tablas nuevas del 27-11-2026 y las del Aula de septiembre se
 * borraban en cascada con su grupo, su matrícula o su estudiante: el historial de grupos de una matrícula, los intentos
 * de evaluación y lo que el docente publicó en el Aula. Como en B35, nada de historial se borra: la BD lo rechaza (hoy
 * ninguna pantalla borra grupos, matrículas ni estudiantes con eso; se anulan o se desactivan).
 *
 * Y `evaluacion_intentos.iniciada_en` pasa a DATETIME: en el MariaDB de XAMPP el primer TIMESTAMP NOT NULL de una tabla
 * toma `ON UPDATE CURRENT_TIMESTAMP`, y cualquier cambio de la fila movería la hora en que empezó la evaluación.
 */
return new class extends Migration
{
    /** [tabla, columna, tabla a la que apunta] */
    private const LLAVES = [
        ['matricula_grupos', 'matricula_id', 'matriculas'],
        ['matricula_grupos', 'curso_id', 'cursos'],
        ['evaluacion_intentos', 'estudiante_id', 'users'],
        ['materiales', 'curso_id', 'cursos'],
        ['actividades', 'curso_id', 'cursos'],
        ['evaluaciones', 'curso_id', 'cursos'],
    ];

    public function up(): void
    {
        $this->cambiar('restrict');
        Schema::table('evaluacion_intentos', function (Blueprint $t) {
            $t->dateTime('iniciada_en')->change();
        });
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
