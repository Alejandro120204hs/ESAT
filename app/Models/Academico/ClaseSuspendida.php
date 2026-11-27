<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un día con horario en que no hubo clase (lo marca el docente con un motivo; 27-11-2026). */
class ClaseSuspendida extends Model
{
    protected $table = 'clases_suspendidas';

    protected $fillable = ['curso_id', 'fecha', 'motivo', 'registrado_por'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
