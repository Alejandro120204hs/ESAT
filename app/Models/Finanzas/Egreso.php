<?php

namespace App\Models\Finanzas;

use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gasto de una sede (panel Admin › Contabilidad) con comprobante CE-AAAA-NNNNN. En nómina el
 * beneficiario es un docente de la sede (docente_id). No se borra: se anula con motivo y deja de
 * contar en el saldo del mes.
 */
class Egreso extends Model
{
    /** Categorías que registra la admin (las devoluciones se toman de la tabla devoluciones). */
    public const CATEGORIAS = [
        'nomina'     => 'Nómina docentes',
        'arriendo'   => 'Arriendo y servicios',
        'materiales' => 'Materiales e insumos',
    ];

    /** Tope de un solo egreso y antigüedad máxima de su fecha (la misma regla al editar). */
    public const VALOR_MAXIMO = 100000000;
    public const DIAS_ATRAS = 365;

    protected $fillable = [
        'sede_id',
        'comprobante',
        'fecha',
        'categoria',
        'concepto',
        'docente_id',
        'beneficiario',
        'valor',
        'metodo_pago',
        'referencia',
        'factura',
        'observacion',
        'registrado_por',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'      => 'date',
            'valor'      => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
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

    /** Siguiente comprobante del año: CE-2026-00001… (consecutivo en configuraciones, nunca retrocede). */
    public static function siguienteComprobante(): string
    {
        return Pago::siguienteConsecutivo('CE', 'ultimo_egreso', static::class, 'comprobante');
    }
}
