<?php

namespace App\Models\Academico;

use App\Models\Finanzas\Matricula;
use App\Models\Institucional\Configuracion;
use App\Models\Institucional\Sede;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programa extends Model
{
    public const ESTADOS     = ['activo', 'en_aprobacion', 'inactivo'];
    public const NIVELES     = ['Técnico Laboral', 'Técnico Laboral por Competencias', 'Auxiliar'];
    public const MODALIDADES = ['Presencial', 'Virtual', 'Mixta'];
    public const JORNADAS    = ['Mañana', 'Tarde', 'Noche', 'Fines de semana'];
    public const PERIODOS    = ['semestre' => 6, 'trimestre' => 3];

    protected $fillable = [
        'escuela_id',
        'codigo',
        'nombre',
        'nivel',
        'modalidad',
        'horas',
        'resolucion',
        'descripcion',
        'perfil_egreso',
        'jornadas',
        'cupo_grupo',
        'precio_total',
        'matricula',
        'duracion_meses',
        'tipo_periodo',
        'duracion_periodos',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'precio_total' => 'decimal:2',
            'matricula' => 'decimal:2',
            'duracion_meses' => 'integer',
            'duracion_periodos' => 'integer',
            'horas' => 'integer',
            'cupo_grupo' => 'integer',
            'jornadas' => 'array',
        ];
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    /** Sedes donde se ofrece el programa. */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'programa_sede')->withTimestamps();
    }

    /** Plan de estudios, en orden. */
    public function modulos(): HasMany
    {
        return $this->hasMany(ProgramaModulo::class)->orderBy('orden');
    }

    /** Programas ofertados en una sede. */
    public function scopeDeSede(Builder $query, int $sedeId): Builder
    {
        return $query->whereHas('sedes', fn (Builder $q) => $q->where('sedes.id', $sedeId));
    }

    /** Número de periodos según la duración en meses (semestre = 6, trimestre = 3). */
    public static function calcularPeriodos(int $meses, string $tipoPeriodo): int
    {
        return max(1, (int) ceil($meses / self::PERIODOS[$tipoPeriodo]));
    }

    public function periodosAcademicos(): HasMany
    {
        return $this->hasMany(PeriodoAcademico::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /**
     * Meses que dura el periodo $i (desde 0): cada uno dura lo de su tipo (trimestre 3, semestre 6) y el último lo que
     * quede. Así 8 meses por trimestre son 3 + 3 + 2 y la cuota del sistema suma justo los 8 meses (HALLAZGOS B28).
     */
    public function mesesDelPeriodo(int $i): int
    {
        $largo = $this->largoPeriodo();

        return max(0, min($largo, $this->duracion_meses - $i * $largo));
    }

    /** Mes (desde 0, contado desde el inicio) en que empieza el periodo $i. */
    public function inicioDelPeriodo(int $i): int
    {
        return $i * $this->largoPeriodo();
    }

    private function largoPeriodo(): int
    {
        return self::PERIODOS[$this->tipo_periodo] ?? max(1, (int) $this->duracion_meses);
    }

    /** Cuota del sistema vigente, tomada de configuraciones (ver Configuracion). */
    public static function cuotaSistemaMensual(): float
    {
        return (float) (Configuracion::valor('cuota_sistema_mensual') ?? 0);
    }

    /** Lo que paga el estudiante cada mes si eligió el plan mensual. */
    public function cuotaMensual(): float
    {
        return ($this->precio_total / $this->duracion_meses) + static::cuotaSistemaMensual();
    }

    /** Lo que paga el estudiante de una vez si eligió pagar por periodo completo (un periodo completo; el último puede ser más corto). */
    public function cuotaPorPeriodo(): float
    {
        $valorPrograma = $this->precio_total / $this->duracion_periodos;
        $cuotaSistema = static::cuotaSistemaMensual() * $this->mesesDelPeriodo(0);

        return $valorPrograma + $cuotaSistema;
    }
}
