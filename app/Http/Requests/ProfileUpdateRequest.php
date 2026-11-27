<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

        // Cambiar el correo (que es su usuario) pide la contraseña actual: una sesión abierta en otro computador no basta
        // para quedarse con la cuenta (auditoría del panel Estudiante, ES18)
        $u = $this->user();
        $cambiaCorreo = mb_strtolower(trim((string) $this->input('email'))) !== mb_strtolower((string) $u->email);
        $clave = ($u->isEstudiante() || $u->isDocente()) && $cambiaCorreo ? ['clave_actual' => ['required', 'current_password']] : [];

        return $nombre + $clave + [
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

    /** El celular del estudiante no puede ser el de su contacto de emergencia (la misma regla de la secretaría). */
    public function after(): array
    {
        return [function (Validator $v) {
            $u = $this->user();
            if ($u->isEstudiante() && $this->filled('telefono') && $u->contactoEmergencia?->celular === $this->input('telefono')) {
                $v->errors()->add('telefono', 'Tu celular debe ser distinto al de tu contacto de emergencia.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'telefono.regex'               => 'El celular debe tener 10 dígitos y empezar por 3.',
            'clave_actual.required'        => 'Para cambiar tu correo escribe tu contraseña actual.',
            'clave_actual.current_password' => 'La contraseña no es correcta.',
        ];
    }
}
