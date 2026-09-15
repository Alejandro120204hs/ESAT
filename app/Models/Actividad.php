<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actividad extends Model
{
    protected $table = 'actividades';

    protected $fillable = [
        'curso_id',
        'docente_id',
        'titulo',
        'instrucciones',
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

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }
}
