<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pregunta extends Model
{
    protected $fillable = [
        'evaluacion_id',
        'enunciado',
        'orden',
    ];

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(Opcion::class);
    }

    public function respuestasEstudiante(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }
}
