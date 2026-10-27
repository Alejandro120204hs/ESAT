<?php

namespace App\Models\Comunicacion;

use App\Models\Academico\Curso;
use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Comunicado de una sede. El admin lo publica a toda la sede o a un rol; el docente, a uno de sus grupos.
 * Un programado sale solo cuando llega publicado_en (estadoActual). El adjunto vive en el disco privado.
 * lectores = quién lo abrió en su panel (comunicado_lecturas.leido_en).
 */
class Comunicado extends Model
{
    public const CATEGORIAS = [
        'general'   => 'General',
        'academico' => 'Académico',
        'pagos'     => 'Pagos',
        'evento'    => 'Evento',
        'urgente'   => 'Urgente',
    ];

    /** Destinos que elige el admin (el docente publica a 'grupo'). */
    public const DESTINOS_ADMIN = [
        'sede'        => 'Toda la sede',
        'estudiantes' => 'Estudiantes',
        'docentes'    => 'Docentes',
        'acudientes'  => 'Acudientes',
    ];

    public const MAX_MENSAJE = 2000;
    public const MAX_ADJUNTO_KB = 5120;
    public const DISCO = 'local';

    protected $fillable = [
        'sede_id',
        'autor_id',
        'titulo',
        'mensaje',
        'categoria',
        'destino',
        'curso_id',
        'estado',
        'publicado_en',
        'visible_hasta',
        'fijado',
        'adjunto_ruta',
        'adjunto_nombre',
        'adjunto_tipo',
        'adjunto_peso',
        'archivado_en',
        'archivado_por',
        'motivo_archivo',
    ];

    protected function casts(): array
    {
        return [
            'publicado_en'  => 'datetime',
            'visible_hasta' => 'date',
            'fijado'        => 'boolean',
            'archivado_en'  => 'datetime',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function archivadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archivado_por');
    }

    public function lectores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comunicado_lecturas')->withPivot('leido_en');
    }

    /** Un programado cuya hora ya llegó cuenta como publicado (no hace falta que corra ninguna tarea). */
    public function estadoActual(): string
    {
        return $this->estado === 'programado' && $this->publicado_en?->lte(now()) ? 'publicado' : $this->estado;
    }

    public function esDelAdmin(): bool
    {
        return $this->destino !== 'grupo';
    }
}
