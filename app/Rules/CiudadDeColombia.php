<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La ciudad o municipio pertenece al departamento elegido, según el mismo listado de Colombia que usa el
 * combobox de los formularios (public/assets/data/co-departamentos-ciudades.json).
 * Sin departamento valida solo que el departamento exista (úsese new CiudadDeColombia() sobre el departamento).
 */
class CiudadDeColombia implements ValidationRule
{
    public function __construct(private ?string $departamento = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $lista = self::listado();
        if ($this->departamento === null) {
            if (! array_key_exists((string) $value, $lista)) {
                $fail('Elige un departamento de la lista.');
            }

            return;
        }
        if (! in_array((string) $value, $lista[$this->departamento] ?? [], true)) {
            $fail('Elige una ciudad o municipio del departamento elegido.');
        }
    }

    /** @return array<string, string[]> departamento => ciudades */
    public static function listado(): array
    {
        return once(function () {
            $datos = json_decode((string) file_get_contents(public_path('assets/data/co-departamentos-ciudades.json')), true) ?: [];

            return collect($datos)->mapWithKeys(fn ($d) => [$d['departamento'] => $d['ciudades']])->all();
        });
    }
}
