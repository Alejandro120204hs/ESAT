<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /** El teléfono se guarda solo con dígitos, igual que en los formularios del Admin (lo pintan sus paneles). */
    protected function prepareForValidation(): void
    {
        if ($this->has('telefono')) {
            $telefono = preg_replace('/\D/', '', (string) $this->input('telefono'));
            $this->merge(['telefono' => $telefono === '' ? null : $telefono]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // El estudiante y el docente no cambian su nombre: sale en certificados, planillas y en la nómina, y lo corrige la
        // secretaría. Sin estas reglas, validated() no los trae y ProfileController no los toca aunque lleguen en la petición.
        $nombre = $this->user()->isEstudiante() || $this->user()->isDocente() ? [] : [
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
        ];

        return $nombre + [
            'telefono' => ['nullable', 'regex:/^3\d{9}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return ['telefono.regex' => 'El celular debe tener 10 dígitos y empezar por 3.'];
    }
}
