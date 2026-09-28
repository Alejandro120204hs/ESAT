<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $fillable = [
        'matricula_id',
        'periodo_academico_id',
        'tipo',
        'mes',
        'anio',
        'monto_programa',
        'monto_sistema',
        'monto_total',
        'estado',
        'fecha_pago',
        'referencia_pasarela',
        'metodo_pago',
    ];

    protected function casts(): array
    {
        return [
            'monto_programa' => 'decimal:2',
            'monto_sistema' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function periodoAcademico(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }
}
