<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocentePerfil extends Model
{
    public const VINCULACIONES = [
        'tiempo_completo' => 'Tiempo completo',
        'medio_tiempo'    => 'Medio tiempo',
        'hora_catedra'    => 'Hora cátedra',
    ];

    public const ESTADOS = [
        'activo'     => 'Activo',
        'en_proceso' => 'En proceso',
        'inactivo'   => 'Inactivo',
    ];

    protected $table = 'docente_perfiles';

    protected $fillable = ['user_id', 'escuela_id', 'vinculacion', 'estado'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }
}
