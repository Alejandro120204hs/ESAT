<?php

namespace App\Models\Finanzas;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\PeriodoAcademico;
use App\Models\Academico\Programa;
use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Matrícula de un estudiante en un programa de una sede (panel Admin › Matrículas).
 * El estado académico vive aquí: solo una matrícula activa ocupa cupo en su grupo.
 * plan_pago lo elige el estudiante desde su panel; null = todavía no lo ha elegido.
 * descuento_pct: beca o convenio sobre el valor del programa (rebaja las cuotas que no ha empezado a pagar).
 * saldo_favor: dinero trasladado que espera cobros para aplicarse; origen_id: viene de un cambio de programa.
 * "anulado" = se hizo por error: no ocupa cupo, no cobra nada y no cuenta en la trayectoria.
 */
class Matricula extends Model
{
    public const ESTADOS = [
        'activo'   => 'Activo',
        'aplazado' => 'Aplazado',
        'retirado' => 'Retirado',
        'graduado' => 'Graduado',
        'anulado'  => 'Anulado',
    ];

    /** Estados que exigen escribir el motivo. */
    public const CON_MOTIVO = ['aplazado', 'retirado', 'anulado'];

    /** Vigente = sigue en ESAT con esta matrícula; un estudiante solo tiene una vigente a la vez. */
    public const VIGENTES = ['activo', 'aplazado'];

    /**
     * A qué estados puede pasar cada uno. Retirado, graduado y anulado son finales (para volver,
     * matrícula nueva). Anular es para una matrícula hecha por error y sin pagos.
     */
    public const TRANSICIONES = [
        'activo'   => ['aplazado', 'retirado', 'graduado', 'anulado'],
        'aplazado' => ['activo', 'retirado', 'anulado'],
        'retirado' => [],
        'graduado' => [],
        'anulado'  => [],
    ];

    protected $fillable = [
        'codigo',
        'estudiante_id',
        'programa_id',
        'curso_id',
        'sede_id',
        'periodo_actual_id',
        'plan_pago',
        'descuento_pct',
        'descuento_motivo',
        'saldo_favor',
        'origen_id',
        'estado',
        'estado_fecha',
        'estado_motivo',
        'fecha_inicio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'estado_fecha' => 'date',
            'descuento_pct' => 'decimal:2',
            'saldo_favor' => 'decimal:2',
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

    /** Pagos recibidos (cada uno con su recibo), incluidos los anulados. */
    /** Dinero adelantado que se le devolvió al estudiante. */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class);
    }

    /** Matrícula de la que viene por un cambio de programa (y la que la reemplazó). */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origen_id');
    }

    public function siguiente(): HasOne
    {
        return $this->hasOne(self::class, 'origen_id');
    }

    public function recaudos(): HasMany
    {
        return $this->hasMany(Recaudo::class);
    }

    /** Notas por módulo de la malla (las pone el docente; ver Calificacion). */
    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class);
    }

    /** Grupo de la matrícula (en curso_estudiante está además la lista del grupo). */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** Grupo en el que está inscrito para este programa. */
    public function grupo(): ?Curso
    {
        return $this->curso;
    }

    public function vigente(): bool
    {
        return in_array($this->estado, self::VIGENTES, true);
    }

    public function puedePasarA(string $estado): bool
    {
        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    public function ocupaCupo(): bool
    {
        return $this->estado === 'activo';
    }

    /** Siguiente código libre del año: EST-2026-0001, EST-2026-0002... */
    public static function siguienteCodigo(): string
    {
        $prefijo = 'EST-'.now()->year.'-';
        $ultimo  = static::where('codigo', 'like', $prefijo.'%')->orderByDesc('codigo')->value('codigo');
        $n = $ultimo ? (int) substr($ultimo, strlen($prefijo)) + 1 : 1;

        return $prefijo.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
