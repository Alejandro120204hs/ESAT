<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $fillable = ['nombre', 'ciudad', 'direccion'];

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
}
