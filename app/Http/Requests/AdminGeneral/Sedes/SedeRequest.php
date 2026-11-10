<?php

namespace App\Http\Requests\AdminGeneral\Sedes;

use App\Rules\CiudadDeColombia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SedeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $limpio = fn (string $k) => preg_replace('/\s+/u', ' ', trim((string) $this->input($k)));
        $this->merge(['nombre' => $limpio('nombre'), 'direccion' => $limpio('direccion')]);
    }

    public function rules(): array
    {
        return [
            'nombre'       => ['required', 'string', 'min:3', 'max:80', Rule::unique('sedes')->ignore($this->route('sede'))],
            'departamento' => ['required', 'string', new CiudadDeColombia()],
            'ciudad'       => ['required', 'string', new CiudadDeColombia((string) $this->input('departamento'))],
            'direccion'    => ['required', 'string', 'min:5', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return ['nombre' => 'nombre de la sede', 'ciudad' => 'ciudad o municipio', 'direccion' => 'dirección'];
    }

    public function messages(): array
    {
        return ['nombre.unique' => 'Ya existe una sede con ese nombre.'];
    }
}
