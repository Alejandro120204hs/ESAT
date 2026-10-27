<?php

namespace App\Models\Comunicacion;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mensaje de un tema del foro (el primero abre el tema). No se borra: se oculta con motivo y se puede
 * restaurar. Un adjunto opcional en el disco privado.
 */
class ForoMensaje extends Model
{
    protected $table = 'foro_mensajes';

    public const MAX_TEXTO = 3000;
    public const MAX_ADJUNTO_KB = 5120;
    public const DISCO = 'local';

    protected $fillable = [
        'tema_id',
        'autor_id',
        'texto',
        'adjunto_ruta',
        'adjunto_nombre',
        'adjunto_tipo',
        'adjunto_peso',
        'oculto_en',
        'oculto_por',
        'motivo_oculto',
    ];

    protected function casts(): array
    {
        return ['oculto_en' => 'datetime'];
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(ForoTema::class, 'tema_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function ocultoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oculto_por');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(ForoReporte::class, 'mensaje_id')->orderBy('created_at');
    }

    public function oculto(): bool
    {
        return $this->oculto_en !== null;
    }

    /** El que abre el tema: el primero que se creó (los mensajes se guardan en orden). */
    public function esPrimero(): bool
    {
        return (int) static::where('tema_id', $this->tema_id)->min('id') === $this->id;
    }
}
