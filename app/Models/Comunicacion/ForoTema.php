<?php

namespace App\Models\Comunicacion;

use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tema del foro general de una sede. El primer mensaje lo abre; si ese mensaje está oculto, el tema entero
 * está oculto. Cerrado = se lee pero nadie más responde.
 */
class ForoTema extends Model
{
    protected $table = 'foro_temas';

    public const CATEGORIAS = [
        'academico' => 'Dudas académicas',
        'practicas' => 'Prácticas y salidas',
        'tramites'  => 'Trámites y pagos',
        'vida'      => 'Vida en la sede',
    ];

    protected $fillable = ['sede_id', 'autor_id', 'categoria', 'titulo', 'fijado', 'cerrado_en', 'cerrado_por'];

    protected function casts(): array
    {
        return [
            'fijado'     => 'boolean',
            'cerrado_en' => 'datetime',
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

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ForoMensaje::class, 'tema_id')->orderBy('id');
    }

    public function primerMensaje(): ?ForoMensaje
    {
        return $this->mensajes()->first();
    }

    public function cerrado(): bool
    {
        return $this->cerrado_en !== null;
    }

    public function oculto(): bool
    {
        return (bool) $this->primerMensaje()?->oculto();
    }
}
