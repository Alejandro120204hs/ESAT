<?php

namespace App\Models\Finanzas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Dinero pagado por adelantado que se le devuelve al estudiante al retirarse o aplazarse
 * (comprobante DV-AAAA-NNNNN). El pivote devolucion_pago.monto dice de qué cobro salió cada peso.
 */
class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'matricula_id',
        'comprobante',
        'fecha',
        'valor',
        'metodo_pago',
        'referencia',
        'motivo',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'decimal:2',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function cobros(): BelongsToMany
    {
        return $this->belongsToMany(Pago::class, 'devolucion_pago', 'devolucion_id', 'pago_id')->withPivot('monto');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Siguiente comprobante del año: DV-2026-00001… (consecutivo en configuraciones, nunca retrocede). */
    public static function siguienteComprobante(): string
    {
        return Pago::siguienteConsecutivo('DV', 'ultima_devolucion', static::class, 'comprobante');
    }
}
