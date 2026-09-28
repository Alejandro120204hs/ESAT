<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un acudiente puede tener varios estudiantes a cargo.
        Schema::create('acudiente_estudiante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acudiente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['acudiente_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acudiente_estudiante');
    }
};
