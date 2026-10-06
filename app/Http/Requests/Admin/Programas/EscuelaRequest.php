<?php

namespace App\Http\Requests\Admin\Programas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EscuelaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:80', Rule::unique('escuelas')->ignore($this->route('escuela'))],
        ];
    }

    public function attributes(): array
    {
        return ['nombre' => 'nombre de la escuela'];
    }

    public function messages(): array
    {
        return ['nombre.unique' => 'Ya existe una escuela con ese nombre.'];
    }
}
