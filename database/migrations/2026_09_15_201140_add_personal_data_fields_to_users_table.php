<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "name" se conserva para compatibilidad con Breeze (login,
            // panel de perfil) y se mantiene sincronizado automáticamente
            // a partir de nombres + apellidos (ver App\Models\User).
            $table->string('nombres')->nullable()->after('name');
            $table->string('apellidos')->nullable()->after('nombres');
            $table->enum('tipo_documento', ['CC', 'TI', 'CE', 'PA'])->nullable()->after('apellidos');
            $table->string('numero_documento')->nullable()->unique()->after('tipo_documento');
            $table->date('fecha_nacimiento')->nullable()->after('numero_documento');
            $table->string('lugar_nacimiento')->nullable()->after('fecha_nacimiento');
            $table->enum('genero', ['masculino', 'femenino', 'otro'])->nullable()->after('lugar_nacimiento');
            $table->string('direccion')->nullable()->after('genero');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['numero_documento']);
            $table->dropColumn([
                'nombres',
                'apellidos',
                'tipo_documento',
                'numero_documento',
                'fecha_nacimiento',
                'lugar_nacimiento',
                'genero',
                'direccion',
            ]);
        });
    }
};
