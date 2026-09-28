<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entrega extends Model
{
    protected $fillable = [
        'actividad_id',
        'estudiante_id',
        'archivo_path',
        'contenido',
        'fecha_entrega',
        'calificacion',
        'comentario_docente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'calificacion' => 'decimal:2',
        ];
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
