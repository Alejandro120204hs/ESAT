<?php

namespace App\Models\Academico;

use App\Models\Finanzas\Matricula;
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
 * fecha_fin no se escribe: siempre es fecha_inicio + duración del programa (ver finPara()). Un grupo finalizado
 * conserva la suya aunque después cambie la duración del programa (ver fin()).
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
     * Solo los que ocupan cupo: inscritos en el grupo cuya matrícula EN ESTE GRUPO está activa
     * (aplazados, retirados y graduados liberan el cupo; una matrícula vieja del mismo
     * programa en otro grupo no cuenta). Sirve para $curso->estudiantesActivos y withCount.
     */
    public function estudiantesActivos(): BelongsToMany
    {
        return $this->estudiantes()->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('matriculas')
            ->whereColumn('matriculas.curso_id', 'curso_estudiante.curso_id')
            ->whereColumn('matriculas.estudiante_id', 'curso_estudiante.estudiante_id')
            ->where('matriculas.estado', 'activo'));
    }

    public const DIA_CORTO = ['Lunes' => 'Lun', 'Martes' => 'Mar', 'Miércoles' => 'Mié', 'Jueves' => 'Jue', 'Viernes' => 'Vie', 'Sábado' => 'Sáb'];

    /** "Lun · Mié · Vie  08:00 – 12:00", una línea por cada rango de horas distinto (espera cargadas las sesiones). */
    public function horarioTexto(): array
    {
        return $this->sesiones
            ->groupBy(fn ($s) => $s->inicio().'–'.$s->fin())
            ->map(fn ($ses) => $ses->map(fn ($s) => self::DIA_CORTO[$s->dia])->implode(' · ').'  '.$ses->first()->inicio().' – '.$ses->first()->fin())
            ->values()
            ->all();
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /** Se puede matricular (o pasar) a alguien: no ha terminado y le quedan cupos. */
    public function recibeEstudiantes(): bool
    {
        return $this->estado !== 'finalizado' && $this->cuposLibres() > 0;
    }

    /** Cupos que quedan: el cupo es siempre el del programa. */
    public function cuposLibres(): int
    {
        return max(0, $this->programa->cupo_grupo - $this->estudiantesActivos()->count());
    }

    /** Horario semanal vigente: una sesión por día de clase (el de antes de un cambio queda en historialSesiones). */
    public function sesiones(): HasMany
    {
        return $this->hasMany(CursoSesion::class)->whereNull('hasta')->orderByRaw(CursoSesion::ORDEN_DIAS)->orderBy('hora_inicio');
    }

    /**
     * Todos los horarios que ha tenido el grupo, con desde/hasta (HALLAZGOS A7). Lo que calcula fechas de clase
     * (asistencia, módulos, panel del docente) usa este, con sesionEn(): lo ya dictado no cambia si cambia el horario.
     */
    public function historialSesiones(): HasMany
    {
        return $this->hasMany(CursoSesion::class)->orderByRaw(CursoSesion::ORDEN_DIAS)->orderBy('hora_inicio');
    }

    /**
     * ¿Un cambio de horario debe conservar el de antes? Solo si el grupo ya empezó y tiene algo registrado (asistencia o
     * notas): si no, cambiarlo es corregirlo y se reemplaza completo.
     */
    public function conservaHorarioAnterior(): bool
    {
        return $this->fecha_inicio && $this->fecha_inicio->lte(today())
            && (Asistencia::where('curso_id', $this->id)->exists() || Calificacion::where('curso_id', $this->id)->exists());
    }

    /** La sesión que regía ese día (null si ese día no había clase). */
    public function sesionEn(CarbonInterface $d): ?CursoSesion
    {
        $dia = CursoSesion::DIAS[$d->dayOfWeekIso - 1] ?? null;

        return $this->historialSesiones->first(fn (CursoSesion $s) => $s->dia === $dia && $s->rigeEn($d));
    }

    /**
     * Por qué no se puede finalizar todavía (null si se puede): antes de su fin, solo si no le quedan estudiantes con
     * matrícula vigente (un grupo que se cierra porque no arrancó). HALLAZGOS A8.
     */
    public function impedimentoParaFinalizar(): ?string
    {
        if (! $this->fecha_inicio || $this->fin()->lte(today())) {
            return null;
        }
        $n = $this->matriculas()->whereIn('estado', Matricula::VIGENTES)->count();

        return $n ? "El {$this->nombre} ({$this->codigo}) termina el {$this->fin()->format('d/m/Y')} y tiene {$n} "
            .($n === 1 ? 'estudiante con matrícula vigente' : 'estudiantes con matrícula vigente').': se finaliza desde ese día.' : null;
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

    /** Fin del grupo: el guardado si ya finalizó (su historial no cambia), si no inicio + duración del programa. */
    public function fin(): CarbonInterface
    {
        return $this->estado === 'finalizado' && $this->fecha_fin
            ? $this->fecha_fin->copy()
            : static::finPara($this->fecha_inicio, $this->programa);
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
