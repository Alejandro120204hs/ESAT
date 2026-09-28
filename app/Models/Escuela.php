<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escuela extends Model
{
    protected $fillable = ['nombre'];

    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }
}
