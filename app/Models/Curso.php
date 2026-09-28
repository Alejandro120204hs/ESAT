<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    protected $fillable = [
        'programa_id',
        'periodo_academico_id',
        'sede_id',
        'nombre',
    ];

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function periodoAcademico(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_docente', 'curso_id', 'docente_id');
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_estudiante', 'curso_id', 'estudiante_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class);
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }
}
