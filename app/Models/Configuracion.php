<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor'];

    /** Lee un valor de configuración por su clave (ej. 'cuota_sistema_mensual'). */
    public static function valor(string $clave): ?string
    {
        return static::where('clave', $clave)->value('valor');
    }
}
