<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    // La tabla solo tiene created_at (ver migración), sin updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'accion',
        'descripcion',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
