<?php

namespace App\Models\Academico;

use App\Models\Finanzas\Matricula;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un paso de la matrícula por un grupo: desde (incluido) hasta (sin incluir; null = sigue ahí). 27-11-2026. */
class MatriculaGrupo extends Model
{
    protected $table = 'matricula_grupos';

    protected $fillable = ['matricula_id', 'curso_id', 'desde', 'hasta'];

    protected function casts(): array
    {
        return ['desde' => 'date', 'hasta' => 'date'];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
