<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = [
        'curso_id',
        'docente_id',
        'titulo',
        'fecha_limite',
    ];

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'datetime',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class)->orderBy('orden');
    }

    public function respuestasEstudiante(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }
}
