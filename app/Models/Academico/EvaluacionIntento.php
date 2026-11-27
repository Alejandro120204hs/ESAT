<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cuándo comenzó un estudiante una evaluación con tiempo límite (su tiempo cuenta desde aquí; 27-11-2026). */
class EvaluacionIntento extends Model
{
    protected $table = 'evaluacion_intentos';

    protected $fillable = ['evaluacion_id', 'estudiante_id', 'iniciada_en'];

    protected function casts(): array
    {
        return ['iniciada_en' => 'datetime'];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
