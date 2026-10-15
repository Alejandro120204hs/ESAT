<?php

namespace App\Models\Finanzas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un pago que recibe la sede, con su recibo (RC-AAAA-NNNNN). El valor se reparte entre los cobros
 * de la matrícula del más viejo al más nuevo (pivote recaudo_pago.monto), así que puede completar
 * cuotas o dejarlas abonadas. Anular un recaudo devuelve lo abonado; el recibo queda como anulado.
 */
class Recaudo extends Model
{
    protected $fillable = [
        'matricula_id',
        'recibo',
        'fecha_pago',
        'valor',
        'metodo_pago',
        'referencia',
        'observacion',
        'registrado_por',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'valor'      => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    /** Cobros que cubrió y cuánto fue a cada uno (pivot->monto). */
    public function cobros(): BelongsToMany
    {
        return $this->belongsToMany(Pago::class, 'recaudo_pago', 'recaudo_id', 'pago_id')->withPivot('monto');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function anulado(): bool
    {
        return $this->anulado_en !== null;
    }
}
