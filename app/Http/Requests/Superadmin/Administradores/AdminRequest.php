<?php

namespace App\Http\Requests\Superadmin\Administradores;

use App\Rules\CiudadDeColombia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Cuenta del Admin de ESAT (rol admin_general) que crea o edita el Superadmin: datos personales, identificación y
 * contacto. Sin sede: el Admin trabaja en todas. La clave inicial es su número de documento (no se pide aquí).
 */
class AdminRequest extends FormRequest
{
    public const TIPOS_DOCUMENTO = ['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'PA' => 'Pasaporte', 'PPT' => 'Permiso por Protección Temporal'];

    protected function prepareForValidation(): void
    {
        $limpio = fn (string $k) => preg_replace('/\s+/u', ' ', trim((string) $this->input($k)));
        $doc = (string) $this->input('numero_documento');
        $this->merge([
            'nombres'          => $limpio('nombres'),
            'apellidos'        => $limpio('apellidos'),
            // El pasaporte admite letras; los demás documentos, solo dígitos
            'numero_documento' => $this->input('tipo_documento') === 'PA'
                ? mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $doc))
                : preg_replace('/\D/', '', $doc),
            'telefono'         => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'correo'           => mb_strtolower(trim((string) $this->input('correo'))),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('administrador')?->id;

        return [
            'nombres'                 => ['required', 'string', 'min:2', 'max:100'],
            'apellidos'               => ['required', 'string', 'min:2', 'max:100'],
            'genero'                  => ['required', Rule::in(['femenino', 'masculino', 'otro'])],
            'fecha_nacimiento'        => ['required', 'date', 'before:today'],
            'departamento_nacimiento' => ['required', 'string', 'max:100', new CiudadDeColombia()],
            'ciudad_nacimiento'       => ['required', 'string', 'max:100', new CiudadDeColombia((string) $this->input('departamento_nacimiento'))],
            'tipo_documento'          => ['required', Rule::in(array_keys(self::TIPOS_DOCUMENTO))],
            'numero_documento'        => ['required', $this->input('tipo_documento') === 'PA' ? 'regex:/^[A-Z0-9]{5,15}$/' : 'digits_between:6,12',
                Rule::unique('users', 'numero_documento')->ignore($id)],
            'telefono'                => ['required', 'regex:/^3\d{9}$/'],
            'correo'                  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
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
                $v->errors()->add('fecha_nacimiento', 'El Admin debe ser mayor de edad.');
            } elseif ($edad > 90) {
                $v->errors()->add('fecha_nacimiento', 'Revisa la fecha de nacimiento.');
            }
        }];
    }

    /** Columnas de users. */
    public function datos(): array
    {
        $d = $this->validated();

        return [
            'nombres' => $d['nombres'], 'apellidos' => $d['apellidos'], 'genero' => $d['genero'], 'fecha_nacimiento' => $d['fecha_nacimiento'],
            'departamento_nacimiento' => $d['departamento_nacimiento'], 'lugar_nacimiento' => $d['ciudad_nacimiento'],
            'tipo_documento' => $d['tipo_documento'], 'numero_documento' => $d['numero_documento'], 'telefono' => $d['telefono'], 'email' => $d['correo'],
        ];
    }

    public function attributes(): array
    {
        return [
            'genero' => 'género', 'fecha_nacimiento' => 'fecha de nacimiento', 'departamento_nacimiento' => 'departamento de nacimiento',
            'ciudad_nacimiento' => 'ciudad de nacimiento', 'tipo_documento' => 'tipo de documento', 'numero_documento' => 'número de documento',
            'telefono' => 'celular', 'correo' => 'correo',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_documento.unique'         => 'Ya hay una cuenta con ese número de documento.',
            'numero_documento.digits_between' => 'El número de documento lleva solo números (de 6 a 12).',
            'numero_documento.regex'          => 'El pasaporte lleva solo letras y números (de 5 a 15).',
            'telefono.regex'                  => 'Escribe un celular de 10 dígitos que empiece por 3.',
            'correo.unique'                   => 'Ya hay una cuenta con ese correo.',
            'fecha_nacimiento.before'         => 'La fecha de nacimiento no puede ser hoy ni futura.',
        ];
    }
}
