<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programa extends Model
{
    protected $fillable = [
        'escuela_id',
        'nombre',
        'precio_total',
        'duracion_meses',
        'tipo_periodo',
        'duracion_periodos',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_total' => 'decimal:2',
            'duracion_meses' => 'integer',
            'duracion_periodos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
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

    /** Cuántos meses dura cada periodo (semestre/trimestre) de este programa. */
    public function mesesPorPeriodo(): float
    {
        return $this->duracion_meses / $this->duracion_periodos;
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

    /** Lo que paga el estudiante de una vez si eligió pagar por periodo completo. */
    public function cuotaPorPeriodo(): float
    {
        $valorPrograma = $this->precio_total / $this->duracion_periodos;
        $cuotaSistema = static::cuotaSistemaMensual() * $this->mesesPorPeriodo();

        return $valorPrograma + $cuotaSistema;
    }
}
