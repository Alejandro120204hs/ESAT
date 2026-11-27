<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Material de clase del Aula virtual: un archivo o un enlace que el docente publica para su grupo (27-11-2026). */
class Material extends Model
{
    protected $table = 'materiales';

    protected $fillable = [
        'curso_id',
        'docente_id',
        'programa_modulo_id',
        'titulo',
        'descripcion',
        'archivo_path',
        'archivo_nombre',
        'archivo_tamano',
        'enlace',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    /** Módulo de la malla al que pertenece (null = es de todo el grupo). */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(ProgramaModulo::class, 'programa_modulo_id');
    }
}
