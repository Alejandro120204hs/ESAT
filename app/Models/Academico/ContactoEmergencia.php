<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contacto de emergencia del estudiante (uno, obligatorio): a quién llamar si pasa algo, y a quién le llegan los
 * WhatsApp de cobros y comunicados si el estudiante es menor de edad. No tiene cuenta ni panel.
 */
class ContactoEmergencia extends Model
{
    /** Parentescos que se pueden registrar. */
    public const PARENTESCOS = ['Madre', 'Padre', 'Abuelo(a)', 'Tío(a)', 'Hermano(a)', 'Pareja', 'Hijo(a)', 'Otro'];

    protected $table = 'contactos_emergencia';

    protected $fillable = ['estudiante_id', 'nombre', 'celular', 'parentesco'];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** Forma en que lo leen las pantallas (fichas, WhatsApp). */
    public function paraVista(): array
    {
        return ['nombre' => $this->nombre, 'celular' => $this->celular, 'parentesco' => $this->parentesco];
    }
}
