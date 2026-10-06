<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->string('codigo', 20)->unique()->after('escuela_id');
            $table->unique('nombre');
            $table->string('nivel', 60)->after('nombre');
            $table->enum('modalidad', ['Presencial', 'Virtual', 'Mixta'])->default('Presencial')->after('nivel');
            $table->unsignedSmallInteger('horas')->after('modalidad');
            $table->string('resolucion')->nullable()->after('horas');
            $table->text('descripcion')->nullable()->after('resolucion');
            $table->text('perfil_egreso')->nullable()->after('descripcion');
            // Jornadas en que se ofrece: ["Mañana","Tarde","Noche","Fines de semana"]
            $table->json('jornadas')->after('perfil_egreso');
            $table->unsignedTinyInteger('cupo_grupo')->after('jornadas');
            $table->date('fecha_inicio')->nullable()->after('cupo_grupo');
            // Pago inicial; el valor total sigue en precio_total y la cuota del
            // sistema es global (configuraciones.cuota_sistema_mensual).
            $table->decimal('matricula', 12, 2)->default(0)->after('precio_total');
            $table->enum('estado', ['activo', 'en_aprobacion', 'inactivo'])->default('activo')->after('duracion_periodos');
            $table->dropColumn('activo');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->boolean('activo')->default(true);
            $table->dropUnique(['codigo']);
            $table->dropUnique(['nombre']);
            $table->dropColumn([
                'codigo', 'nivel', 'modalidad', 'horas', 'resolucion', 'descripcion', 'perfil_egreso',
                'jornadas', 'cupo_grupo', 'fecha_inicio', 'matricula', 'estado',
            ]);
        });
    }
};
