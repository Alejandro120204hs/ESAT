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
        return [
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
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
