<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // superadmin: dueño/creador del sistema, puede haber varios.
            // admin: Admin ESAT, gestiona la operación diaria.
            // estudiante, docente, acudiente: los 3 roles académicos.
            $table->enum('role', ['superadmin', 'admin', 'estudiante', 'docente', 'acudiente'])
                ->default('estudiante')
                ->after('name');
            $table->foreignId('sede_id')->nullable()->after('role')->constrained('sedes')->nullOnDelete();
            $table->string('telefono')->nullable()->after('sede_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
            $table->dropColumn(['role', 'telefono']);
        });
    }
};
