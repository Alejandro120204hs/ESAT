<?php

namespace App\Models\Finanzas;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\MatriculaGrupo;
use App\Models\Academico\PeriodoAcademico;
use App\Models\Academico\Programa;
use App\Models\Institucional\Configuracion;
use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

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
        'precio_programa',
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
            'precio_programa' => 'decimal:2',
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

    /** Valor del programa pactado al matricular (las anteriores a guardarlo usan el del catálogo). */
    public function precioPrograma(): float
    {
        return (float) ($this->precio_programa ?? $this->programa->precio_total);
    }

    /**
     * Cuántas cuotas tiene su plan: la última que se le generó (anuladas incluidas, porque al reintegrarse las nuevas
     * reusan los números), no la duración de hoy del programa (HALLAZGOS B29). Sin cuotas todavía, la del programa;
     * sin plan, null. $todos: todos sus cobros ya cargados (con los anulados), para no volver a consultar.
     */
    public function totalCuotas(?Collection $todos = null): ?int
    {
        if (! $this->plan_pago) {
            return null;
        }
        // Sin $todos, a la BD: varias pantallas cargan `pagos` sin los anulados, y ahí el máximo saldría corto
        $ultima = $todos ? $todos->where('tipo', $this->plan_pago)->max('numero_cuota')
            : $this->pagos()->where('tipo', $this->plan_pago)->max('numero_cuota');

        return (int) ($ultima ?: ($this->plan_pago === 'mensual' ? $this->programa->duracion_meses : $this->programa->duracion_periodos));
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

    /** Los grupos por los que ha pasado, en orden (MatriculaService los abre y cierra; 27-11-2026). */
    public function grupos(): HasMany
    {
        return $this->hasMany(MatriculaGrupo::class)->orderBy('desde')->orderBy('id');
    }

    /**
     * Los pasos por grupos como [curso_id, desde, hasta]. Una matrícula sin historial (anterior a la tabla) cuenta como un
     * solo paso por su grupo, desde su inicio.
     */
    public function periodos(): Collection
    {
        $p = $this->relationLoaded('grupos') ? $this->grupos : $this->grupos()->get();
        if ($p->isEmpty()) {
            return $this->curso_id ? collect([['curso_id' => $this->curso_id, 'desde' => $this->fecha_inicio->toDateString(), 'hasta' => null]]) : collect();
        }

        return $p->map(fn (MatriculaGrupo $g) => ['curso_id' => $g->curso_id, 'desde' => $g->desde->toDateString(), 'hasta' => $g->hasta?->toDateString()]);
    }

    /**
     * Fechas en que el estudiante cuenta en ese grupo: [[desde, hasta|null], ...] con desde incluido y hasta sin incluir.
     * Desde = cuando entró al grupo (no antes de su inicio); hasta = cuando salió, o cuando se aplazó, retiró o graduó. Es la
     * regla de la lista de cada clase, del % de asistencia y de los módulos que le tocan, en todos los paneles.
     */
    public function ventanasEn(Curso $curso): array
    {
        $inicio = $curso->fecha_inicio->toDateString();
        $corte = ! in_array($this->estado, ['activo'], true) ? $this->estado_fecha?->toDateString() : null;
        $out = [];
        foreach ($this->periodos()->where('curso_id', $curso->id) as $p) {
            $desde = max($p['desde'], $inicio);
            $hasta = $p['hasta'];
            if ($corte && (! $hasta || $corte < $hasta)) {
                $hasta = $corte;
            }
            if (! $hasta || $hasta > $desde) {
                $out[] = ['desde' => $desde, 'hasta' => $hasta];
            }
        }

        return $out;
    }

    /** ¿Ese día contaba en ese grupo? */
    public static function enVentanas(array $ventanas, string $fecha): bool
    {
        foreach ($ventanas as $v) {
            if ($fecha >= $v['desde'] && (! $v['hasta'] || $fecha < $v['hasta'])) {
                return true;
            }
        }

        return false;
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
        Configuracion::bloquear('codigo_matricula');   // dos matrículas a la vez no sacan el mismo código
        $prefijo = 'EST-'.now()->year.'-';
        $ultimo  = static::where('codigo', 'like', $prefijo.'%')->orderByDesc('codigo')->value('codigo');
        $n = $ultimo ? (int) substr($ultimo, strlen($prefijo)) + 1 : 1;

        return $prefijo.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
