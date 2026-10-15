<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistencia de un estudiante a una clase de su grupo. La toma el docente; el Admin solo la consulta.
 * Tarde cuenta como asistió y la excusa no cuenta como falla (ver AsistenciaService::porcentaje()).
 */
class Asistencia extends Model
{
    public const ESTADOS = ['presente', 'ausente', 'tarde', 'excusa'];

    protected $fillable = [
        'curso_id',
        'estudiante_id',
        'fecha',
        'estado',
        'motivo',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** El docente que tomó la lista. */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
