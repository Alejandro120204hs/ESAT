<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escuela extends Model
{
    protected $fillable = ['nombre', 'sigla'];

    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }
}
