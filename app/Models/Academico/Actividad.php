<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actividad extends Model
{
    protected $table = 'actividades';

    protected $fillable = [
        'curso_id',
        'docente_id',
        'programa_modulo_id',
        'titulo',
        'instrucciones',
        'adjunto_path',
        'adjunto_nombre',
        'adjunto_tamano',
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

    /** Módulo de la malla al que pertenece (su nota es aparte de la del módulo). */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(ProgramaModulo::class, 'programa_modulo_id');
    }

    /** Ya pasó la fecha límite: no recibe entregas ni cambios de archivo. */
    public function cerrada(): bool
    {
        return $this->fecha_limite->lte(now());
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }
}
