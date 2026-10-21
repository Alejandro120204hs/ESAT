<?php

namespace App\Models\Academico;

use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota de un módulo de la malla en una matrícula. La pone el docente; el Admin solo la consulta.
 * Escala de 1.0 a 5.0, aprueba con 3.0. Quien pierde un módulo tiene una sola habilitación:
 * si la aprueba, el módulo queda aprobado; si la pierde, repite el módulo.
 */
class Calificacion extends Model
{
    public const MINIMA = 1.0;
    public const MAXIMA = 5.0;
    public const APRUEBA = 3.0;

    protected $table = 'calificaciones';

    protected $fillable = [
        'matricula_id',
        'programa_modulo_id',
        'curso_id',
        'nota',
        'fecha_nota',
        'habilitacion',
        'fecha_habilitacion',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'nota'               => 'float',
            'habilitacion'       => 'float',
            'fecha_nota'         => 'date',
            'fecha_habilitacion' => 'date',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(ProgramaModulo::class, 'programa_modulo_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** El docente que la registró. */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** La que cuenta: la de la habilitación si la presentó, si no la nota. */
    public function notaFinal(): float
    {
        return $this->habilitacion ?? $this->nota;
    }

    /** aprobado · habilito (aprobó en habilitación) · por_habilitar · reprobado (perdió la habilitación). */
    public function estado(): string
    {
        return match (true) {
            $this->nota >= self::APRUEBA        => 'aprobado',
            $this->habilitacion === null        => 'por_habilitar',
            $this->habilitacion >= self::APRUEBA => 'habilito',
            default                             => 'reprobado',
        };
    }

    public function aprobada(): bool
    {
        return in_array($this->estado(), ['aprobado', 'habilito'], true);
    }
}
