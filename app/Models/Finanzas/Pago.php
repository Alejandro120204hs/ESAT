<?php

namespace App\Models\Finanzas;

use App\Models\Academico\PeriodoAcademico;
use App\Models\Institucional\Configuracion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un cobro de la matrícula (derecho de matrícula o cuota del plan). Lo que se le ha pagado está en
 * "abonado" y sale de los recaudos (cada pago con su recibo); queda "pagado" cuando abonado cubre
 * monto_total (= monto_programa - descuento + monto_sistema). fecha_pago = día en que se completó.
 * reembolsado = parte de lo abonado que salió de aquí (devuelta al estudiante, usada para otra deuda
 * o trasladada a otra matrícula); el cobro queda anulado cuando sale todo.
 * Las cuotas vencen el día DIA_VENCIMIENTO de su mes. "vencido" lo guarda el comando
 * pagos:vencer, pero estadoActual() lo calcula siempre por fecha para no depender de él.
 * Un derecho de matrícula pendiente sin fecha_vencimiento está "en espera": el estudiante se
 * aplazó antes de empezar clases y vence cuando se reintegre (PagoService::programarDerecho).
 */
class Pago extends Model
{
    public const DIA_VENCIMIENTO = 10;

    public const TIPOS = ['matricula' => 'Derecho de matrícula', 'mensual' => 'Cuota mensual', 'periodo' => 'Cuota por periodo'];

    public const METODOS = ['Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta'];

    /** Lo que todavía se debe. */
    public const POR_PAGAR = ['pendiente', 'vencido'];

    protected $fillable = [
        'matricula_id',
        'periodo_academico_id',
        'tipo',
        'numero_cuota',
        'mes',
        'anio',
        'fecha_vencimiento',
        'monto_programa',
        'descuento',
        'monto_sistema',
        'monto_total',
        'abonado',
        'reembolsado',
        'estado',
        'fecha_pago',
        'referencia_pasarela',
        'metodo_pago',
    ];

    protected function casts(): array
    {
        return [
            'monto_programa' => 'decimal:2',
            'descuento' => 'decimal:2',
            'monto_sistema' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'abonado' => 'decimal:2',
            'reembolsado' => 'decimal:2',
            'fecha_pago' => 'datetime',
            'fecha_vencimiento' => 'date',
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

    /** Pagos (con recibo) que abonaron a este cobro y cuánto puso cada uno (pivot->monto). */
    public function recaudos(): BelongsToMany
    {
        return $this->belongsToMany(Recaudo::class, 'recaudo_pago', 'pago_id', 'recaudo_id')->withPivot('monto');
    }

    /** Un pendiente que ya pasó su fecha es vencido, aunque el comando aún no lo haya marcado. */
    public function estadoActual(): string
    {
        if ($this->estado === 'pendiente' && $this->fecha_vencimiento && $this->fecha_vencimiento->lt(today())) {
            return 'vencido';
        }

        return $this->estado;
    }

    public function porPagar(): bool
    {
        return in_array($this->estado, self::POR_PAGAR, true);
    }

    public function esCuota(): bool
    {
        return $this->tipo !== 'matricula';
    }

    /** Lo pagado que todavía está en este cobro (lo abonado menos lo devuelto o trasladado). */
    public function disponible(): float
    {
        return max(0, round((float) $this->abonado - (float) $this->reembolsado, 2));
    }

    /** Lo que falta por pagar de este cobro (0 si ya está pagado o anulado). */
    public function restante(): float
    {
        return $this->porPagar() ? max(0, round((float) $this->monto_total - $this->disponible(), 2)) : 0.0;
    }

    /**
     * Siguiente recibo del año: RC-2026-00001… (un recibo por cada pago que recibe la sede).
     * El consecutivo vive en configuraciones ('ultimo_recibo_AAAA') y nunca retrocede: un recibo
     * anulado no se vuelve a entregar. Llamar dentro de una transacción (bloquea el contador).
     */
    public static function siguienteRecibo(): string
    {
        return static::siguienteConsecutivo('RC', 'ultimo_recibo', Recaudo::class, 'recibo');
    }

    /**
     * Consecutivo anual PREFIJO-AAAA-NNNNN guardado en configuraciones ("{clave}_AAAA"); nunca
     * retrocede. La primera vez del año arranca después del mayor que ya exista en $modelo.$columna.
     */
    public static function siguienteConsecutivo(string $tipo, string $clave, string $modelo, string $columna): string
    {
        $anio    = now()->year;
        $prefijo = $tipo.'-'.$anio.'-';
        $clave   = $clave.'_'.$anio;

        $contador = Configuracion::where('clave', $clave)->lockForUpdate()->first();
        if (! $contador) {
            $ultimo   = $modelo::where($columna, 'like', $prefijo.'%')->orderByDesc($columna)->value($columna);
            $contador = Configuracion::create(['clave' => $clave, 'valor' => $ultimo ? (string) (int) substr($ultimo, strlen($prefijo)) : '0']);
        }
        $n = (int) $contador->valor + 1;
        $contador->update(['valor' => (string) $n]);

        return $prefijo.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
