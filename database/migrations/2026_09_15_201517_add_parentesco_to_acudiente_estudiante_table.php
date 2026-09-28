<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El parentesco varía por relación (un acudiente puede ser
        // "padre" de un estudiante y "tutor" de otro), por eso vive
        // aquí y no como un dato fijo del acudiente en users.
        Schema::table('acudiente_estudiante', function (Blueprint $table) {
            $table->string('parentesco')->nullable()->after('estudiante_id');
        });
    }

    public function down(): void
    {
        Schema::table('acudiente_estudiante', function (Blueprint $table) {
            $table->dropColumn('parentesco');
        });
    }
};
