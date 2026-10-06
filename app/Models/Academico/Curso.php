<?php

namespace App\Models\Academico;

use App\Models\Institucional\Sede;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un grupo de un programa en una sede (panel Admin › Grupos).
 * "nombre" es el nombre del grupo: Grupo A, Grupo B...
 * La modalidad y el cupo son los del programa (programa->modalidad / programa->cupo_grupo).
 * fecha_fin no se escribe: siempre es fecha_inicio + duración del programa (ver finPara()).
 */
class Curso extends Model
{
    public const ESTADOS = [
        'planificacion' => 'En planificación',
        'activo'        => 'Activo',
        'finalizado'    => 'Finalizado',
    ];

    public const JORNADAS = ['Mañana', 'Tarde', 'Noche', 'Fines de semana'];

    protected $fillable = [
        'programa_id',
        'periodo_academico_id',
        'sede_id',
        'codigo',
        'nombre',
        'jornada',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function periodoAcademico(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /** En la práctica un grupo tiene un solo docente (ver docente()). */
    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_docente', 'curso_id', 'docente_id')->withTimestamps();
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_estudiante', 'curso_id', 'estudiante_id')->withTimestamps();
    }

    /** Horario semanal: una sesión por día de clase. */
    public function sesiones(): HasMany
    {
        return $this->hasMany(CursoSesion::class)->orderByRaw(CursoSesion::ORDEN_DIAS)->orderBy('hora_inicio');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class);
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /** El grupo termina cuando se cumple la duración del programa. */
    public static function finPara(CarbonInterface $inicio, Programa $programa): CarbonInterface
    {
        return $inicio->copy()->addMonthsNoOverflow($programa->duracion_meses);
    }

    public function docente(): ?User
    {
        return $this->docentes->first();
    }

    public function scopeDeSede(Builder $query, int $sedeId): Builder
    {
        return $query->where('sede_id', $sedeId);
    }
}
