<?php

namespace App\Models\Sistema;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    // La tabla solo tiene created_at (ver migración), sin updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'accion',
        'entidad_tipo',
        'entidad_id',
        'descripcion',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Deja constancia de una acción del usuario autenticado.
     * $accion: crear | editar | eliminar | activar | desactivar
     */
    public static function registrar(string $accion, Model $entidad, string $descripcion): self
    {
        return static::create([
            'user_id'      => auth()->id(),
            'accion'       => $accion,
            'entidad_tipo' => class_basename($entidad),
            'entidad_id'   => $entidad->getKey(),
            'descripcion'  => $descripcion,
        ]);
    }
}
