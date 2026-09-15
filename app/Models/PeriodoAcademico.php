<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoAcademico extends Model
{
    protected $table = 'periodos_academicos';

    protected $fillable = [
        'programa_id',
        'numero_periodo',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'periodo_actual_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}
