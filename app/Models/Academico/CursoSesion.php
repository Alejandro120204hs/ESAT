<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un día de clase de un grupo, con su hora de inicio y de fin. */
class CursoSesion extends Model
{
    public const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    /** Para ordenar por día de la semana y no alfabéticamente. */
    public const ORDEN_DIAS = "FIELD(dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado')";

    protected $table = 'curso_sesiones';

    protected $fillable = ['curso_id', 'dia', 'hora_inicio', 'hora_fin'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** "08:00" (la BD guarda "08:00:00"). */
    public function inicio(): string
    {
        return substr($this->hora_inicio, 0, 5);
    }

    public function fin(): string
    {
        return substr($this->hora_fin, 0, 5);
    }

    public function minutos(): int
    {
        return self::aMinutos($this->hora_fin) - self::aMinutos($this->hora_inicio);
    }

    public static function aMinutos(string $hora): int
    {
        [$h, $m] = explode(':', $hora);

        return (int) $h * 60 + (int) $m;
    }
}
