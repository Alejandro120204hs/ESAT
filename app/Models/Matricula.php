<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matricula extends Model
{
    protected $fillable = [
        'estudiante_id',
        'programa_id',
        'sede_id',
        'periodo_actual_id',
        'plan_pago',
        'estado',
        'fecha_inicio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function periodoActual(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'periodo_actual_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}
