<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaModulo extends Model
{
    protected $table = 'programa_modulos';

    protected $fillable = ['programa_id', 'nombre', 'horas', 'orden'];

    protected function casts(): array
    {
        return [
            'horas' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }
}
