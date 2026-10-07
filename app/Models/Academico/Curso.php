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

    /**
     * Horas y días que admite cada jornada: el horario de un grupo no puede salirse
     * de la jornada que se le eligió en Grupos. 'dias' null = cualquier día.
     */
    public const RANGOS_JORNADA = [
        'Mañana'          => ['desde' => '06:00', 'hasta' => '12:00', 'dias' => null],
        'Tarde'           => ['desde' => '12:00', 'hasta' => '18:00', 'dias' => null],
        'Noche'           => ['desde' => '18:00', 'hasta' => '24:00', 'dias' => null],
        'Fines de semana' => ['desde' => '06:00', 'hasta' => '24:00', 'dias' => ['Sábado']],
    ];

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

    /**
     * Solo los que ocupan cupo: inscritos en el grupo cuya matrícula en el programa
     * del grupo está activa (aplazados, retirados y graduados liberan el cupo).
     * Sirve igual para $curso->estudiantesActivos y para withCount('estudiantesActivos').
     */
    public function estudiantesActivos(): BelongsToMany
    {
        return $this->estudiantes()->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('matriculas')
            ->join('cursos as curso_cupo', 'curso_cupo.programa_id', '=', 'matriculas.programa_id')
            ->whereColumn('curso_cupo.id', 'curso_estudiante.curso_id')
            ->whereColumn('matriculas.estudiante_id', 'curso_estudiante.estudiante_id')
            ->where('matriculas.estado', 'activo'));
    }

    /** Cupos que quedan: el cupo es siempre el del programa. */
    public function cuposLibres(): int
    {
        return max(0, $this->programa->cupo_grupo - $this->estudiantesActivos()->count());
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
