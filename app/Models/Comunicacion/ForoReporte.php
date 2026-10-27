<?php

namespace App\Models\Comunicacion;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte de un mensaje del foro por una persona (una vez por mensaje). pendiente → atendido (el admin
 * ocultó el mensaje) o descartado (el mensaje sigue visible).
 */
class ForoReporte extends Model
{
    protected $table = 'foro_reportes';

    const UPDATED_AT = null;

    protected $fillable = ['mensaje_id', 'user_id', 'motivo', 'estado', 'revisado_por', 'revisado_en'];

    protected function casts(): array
    {
        return ['revisado_en' => 'datetime'];
    }

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(ForoMensaje::class, 'mensaje_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
