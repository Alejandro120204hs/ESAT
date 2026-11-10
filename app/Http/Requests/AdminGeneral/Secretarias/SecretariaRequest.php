<?php

namespace App\Http\Requests\AdminGeneral\Secretarias;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Cuenta de secretaría (rol admin) de una sede: datos personales, identificación, contacto y sede. */
class SecretariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $limpio = fn (string $k) => preg_replace('/\s+/u', ' ', trim((string) $this->input($k)));
        $this->merge([
            'nombres'          => $limpio('nombres'),
            'apellidos'        => $limpio('apellidos'),
            'numero_documento' => preg_replace('/\D/', '', (string) $this->input('numero_documento')),
            'telefono'         => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'email'            => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('secretaria')?->id;

        return [
            'nombres'          => ['required', 'string', 'min:2', 'max:60'],
            'apellidos'        => ['required', 'string', 'min:2', 'max:60'],
            'genero'           => ['required', Rule::in(['femenino', 'masculino', 'otro'])],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'tipo_documento'   => ['required', Rule::in(['CC', 'CE', 'PA', 'PPT'])],
            'numero_documento' => ['required', 'digits_between:6,12', Rule::unique('users')->ignore($id)],
            'telefono'         => ['required', 'regex:/^3\d{9}$/'],
            'email'            => ['required', 'email', 'max:120', Rule::unique('users')->ignore($id)],
            'sede_id'          => ['required', 'integer', Rule::exists('sedes', 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->has('fecha_nacimiento')) {
                return;
            }
            $edad = Carbon::parse($this->input('fecha_nacimiento'))->age;
            if ($edad < 18) {
                $v->errors()->add('fecha_nacimiento', 'La secretaría debe ser mayor de edad.');
            } elseif ($edad > 90) {
                $v->errors()->add('fecha_nacimiento', 'Revisa la fecha de nacimiento.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'genero' => 'género', 'fecha_nacimiento' => 'fecha de nacimiento', 'tipo_documento' => 'tipo de documento',
            'numero_documento' => 'número de documento', 'telefono' => 'celular', 'email' => 'correo', 'sede_id' => 'sede',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_documento.unique' => 'Ya hay una cuenta con ese número de documento.',
            'email.unique'            => 'Ya hay una cuenta con ese correo.',
            'telefono.regex'          => 'Escribe un celular de 10 dígitos que empiece por 3.',
        ];
    }
}
