<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un documento de la lista de chequeo del estudiante (de la persona, no de una matrícula); entregado_en null = pendiente. */
class EstudianteDocumento extends Model
{
    /** Lo que se le pide a todo estudiante al registrarlo. */
    public const REQUERIDOS = [
        'Copia del documento de identidad',
        'Certificado de estudios',
        'Certificado de afiliación a salud',
    ];

    protected $table = 'estudiante_documentos';

    protected $fillable = ['estudiante_id', 'documento', 'entregado_en'];

    protected function casts(): array
    {
        return [
            'entregado_en' => 'datetime',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
