<?php

namespace App\Models\Institucional;

use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $fillable = ['nombre', 'departamento', 'ciudad', 'direccion'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /** Programas que se ofrecen en la sede. */
    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'programa_sede')->withTimestamps();
    }
}
