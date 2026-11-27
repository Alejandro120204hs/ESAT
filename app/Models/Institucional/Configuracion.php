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

    /** Porcentaje del derecho de matrícula que se devuelve a quien se retira antes de empezar clases (si no se ha guardado otro). */
    public const DEVOLUCION_DERECHO_PCT = 50;

    /** Reglas de acceso: intentos fallidos antes de bloquear una cuenta y días sin entrar para bloquearla. */
    public const SEGURIDAD = ['intentos_maximos' => 5, 'dias_inactividad' => 90];

    /** Lee un valor de configuración por su clave (ej. 'cuota_sistema_mensual'). */
    public static function valor(string $clave): ?string
    {
        return static::where('clave', $clave)->value('valor');
    }

    /**
     * Toma (y si no existe, crea) la fila $clave bloqueada hasta el final de la transacción: dos peticiones que la piden a
     * la vez van de a una. Sirve para generar códigos y consecutivos sin repetirlos.
     */
    public static function bloquear(string $clave, string $inicial = '0'): self
    {
        static::query()->insertOrIgnore(['clave' => $clave, 'valor' => $inicial, 'created_at' => now(), 'updated_at' => now()]);

        return static::where('clave', $clave)->lockForUpdate()->firstOrFail();
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

    /** Porcentaje (0–100) de lo pagado del derecho que se devuelve al retirarse antes de clases (Admin › Configuración). */
    public static function devolucionDerechoPct(): int
    {
        return (int) (static::valor('devolucion_derecho_pct') ?? self::DEVOLUCION_DERECHO_PCT);
    }

    /** @return array{intentos_maximos:int, dias_inactividad:int} */
    public static function seguridad(): array
    {
        $guardados = static::whereIn('clave', array_map(fn ($k) => 'seguridad_'.$k, array_keys(self::SEGURIDAD)))->pluck('valor', 'clave');

        return collect(self::SEGURIDAD)->mapWithKeys(fn ($defecto, $k) => [$k => (int) $guardados->get('seguridad_'.$k, $defecto)])->all();
    }
}
