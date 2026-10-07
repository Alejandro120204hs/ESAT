<?php

namespace App\Models\Finanzas;

use App\Models\Academico\Curso;
use App\Models\Academico\PeriodoAcademico;
use App\Models\Academico\Programa;
use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Matrícula de un estudiante en un programa de una sede (panel Admin › Matrículas).
 * El estado académico vive aquí: solo una matrícula activa ocupa cupo en su grupo.
 * plan_pago lo elige el estudiante desde su panel; null = todavía no lo ha elegido.
 */
class Matricula extends Model
{
    public const ESTADOS = [
        'activo'   => 'Activo',
        'aplazado' => 'Aplazado',
        'retirado' => 'Retirado',
        'graduado' => 'Graduado',
    ];

    /** Estados que exigen escribir el motivo. */
    public const CON_MOTIVO = ['aplazado', 'retirado'];

    protected $fillable = [
        'codigo',
        'estudiante_id',
        'programa_id',
        'sede_id',
        'periodo_actual_id',
        'plan_pago',
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

    /** Grupo en el que está inscrito para este programa (uno a la vez). */
    public function grupo(): ?Curso
    {
        return $this->estudiante->cursosComoEstudiante()
            ->where('cursos.programa_id', $this->programa_id)
            ->where('cursos.sede_id', $this->sede_id)
            ->first();
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
