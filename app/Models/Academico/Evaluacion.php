<?php

namespace App\Models\Academico;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = [
        'curso_id',
        'docente_id',
        'programa_modulo_id',
        'titulo',
        'descripcion',
        'fecha_limite',
        'duracion_minutos',
    ];

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'datetime',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    /** Módulo de la malla al que pertenece (su nota es aparte de la del módulo). */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(ProgramaModulo::class, 'programa_modulo_id');
    }

    /**
     * Ya pasó la fecha límite: no recibe respuestas (salvo la gracia del envío automático de quien estaba dentro de su
     * tiempo) y su cierre ya no se cambia (las correctas pudieron verse).
     */
    public function cerrada(): bool
    {
        return $this->fecha_limite->lte(now());
    }

    /**
     * Las respuestas correctas se pueden mostrar: ya cerró y pasó la gracia, así que nadie puede enviar todavía con ellas
     * a la vista (auditoría del panel Estudiante, ES1). La usan el servidor y el texto de aula.js (verCorrectas).
     */
    public function correctasVisibles(?DateTimeInterface $ahora = null): bool
    {
        return Carbon::instance($ahora ?? now())->gt($this->fecha_limite->copy()->addSeconds(self::GRACIA_SEGUNDOS));
    }

    /** Nota de una evaluación de selección múltiple: 5,0 × aciertos ÷ preguntas, con un decimal y mínimo 1,0 (24-11-2026). */
    public static function nota(int $aciertos, int $preguntas): ?float
    {
        return $preguntas ? max(1.0, round(5 * $aciertos / $preguntas, 1)) : null;
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class)->orderBy('orden');
    }

    /** Minutos que puede escoger el docente (null = sin límite). */
    public const DURACIONES = [10, 15, 20, 30, 45, 60, 90, 120];

    /** Por qué no se cambia el cierre de una evaluación que ya cerró (lo dicen la Form Request y el servicio). */
    public const NO_REABRIR = 'Esta evaluación ya cerró y sus estudiantes pueden ver las respuestas correctas: su cierre no se cambia. Para dar más tiempo publica una nueva.';

    /** Segundos de más que se aceptan al enviar cuando se acabó el tiempo (lo que tarda en llegar el envío automático). */
    public const GRACIA_SEGUNDOS = 60;

    /** Hasta cuándo puede responder quien la comenzó en $inicio: su tiempo o el cierre, lo que llegue primero. */
    public function terminaPara(DateTimeInterface $inicio): Carbon
    {
        $fin = Carbon::instance($inicio)->addMinutes((int) $this->duracion_minutos);

        return $fin->lt($this->fecha_limite) ? $fin : $this->fecha_limite->copy();
    }

    public function intentos(): HasMany
    {
        return $this->hasMany(EvaluacionIntento::class);
    }

    public function respuestasEstudiante(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }
}
