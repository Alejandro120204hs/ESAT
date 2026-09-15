<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaEstudiante extends Model
{
    protected $table = 'respuestas_estudiante';

    protected $fillable = [
        'evaluacion_id',
        'estudiante_id',
        'pregunta_id',
        'opcion_id',
        'es_correcta',
    ];

    protected function casts(): array
    {
        return [
            'es_correcta' => 'boolean',
        ];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(Opcion::class);
    }
}
