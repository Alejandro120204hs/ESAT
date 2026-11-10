<?php

namespace App\Models\Institucional;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor'];

    /**
     * Datos de la institución que salen en recibos y documentos (se editan en Admin › Configuración).
     * Mientras no se guarden otros, son los reales de ESAT (los mismos del sitio público).
     */
    public const INSTITUCION = [
        'nombre'         => 'Escuela Nacional de Educación ESAT',
        'sigla'          => 'ESAT',
        'nit'            => '900820764-1',
        'licencia'       => '5850',
        'fecha_licencia' => '2019-09-23',
        'entidad'        => 'Secretaría de Educación de Cundinamarca',
        'telefono'       => '3152957588',
        'telefono2'      => '3123773144',
        'correo'         => '',
    ];

    /** Reglas de acceso: intentos fallidos antes de bloquear una cuenta y días sin entrar para bloquearla. */
    public const SEGURIDAD = ['intentos_maximos' => 5, 'dias_inactividad' => 90];

    /** Lee un valor de configuración por su clave (ej. 'cuota_sistema_mensual'). */
    public static function valor(string $clave): ?string
    {
        return static::where('clave', $clave)->value('valor');
    }

    /** Guarda (o crea) un valor y devuelve la fila. */
    public static function guardar(string $clave, mixed $valor): self
    {
        return static::updateOrCreate(['clave' => $clave], ['valor' => (string) $valor]);
    }

    /** @return array<string, string> datos de la institución (claves institucion_*), con los reales por defecto */
    public static function institucion(): array
    {
        $guardados = static::whereIn('clave', array_map(fn ($k) => 'institucion_'.$k, array_keys(self::INSTITUCION)))->pluck('valor', 'clave');

        return collect(self::INSTITUCION)->mapWithKeys(fn ($defecto, $k) => [$k => $guardados->get('institucion_'.$k, $defecto) ?? ''])->all();
    }

    /** @return array{intentos_maximos:int, dias_inactividad:int} */
    public static function seguridad(): array
    {
        $guardados = static::whereIn('clave', array_map(fn ($k) => 'seguridad_'.$k, array_keys(self::SEGURIDAD)))->pluck('valor', 'clave');

        return collect(self::SEGURIDAD)->mapWithKeys(fn ($defecto, $k) => [$k => (int) $guardados->get('seguridad_'.$k, $defecto)])->all();
    }
}
