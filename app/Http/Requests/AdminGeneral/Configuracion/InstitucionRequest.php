<?php

namespace App\Http\Requests\AdminGeneral\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

/** Datos de la institución que salen en recibos y documentos de todas las sedes. */
class InstitucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $limpio = fn (string $k) => preg_replace('/\s+/u', ' ', trim((string) $this->input($k)));
        $this->merge([
            'nombre'    => $limpio('nombre'),
            'sigla'     => mb_strtoupper($limpio('sigla')),
            'nit'       => $limpio('nit'),
            'licencia'  => $limpio('licencia'),
            'entidad'   => $limpio('entidad'),
            'telefono'  => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'telefono2' => preg_replace('/\D/', '', (string) $this->input('telefono2')),
            'correo'    => mb_strtolower(trim((string) $this->input('correo'))),
        ]);
    }

    public function rules(): array
    {
        $tel = 'regex:/^(3\d{9}|60\d{8})$/';

        return [
            'nombre'         => ['required', 'string', 'min:5', 'max:150'],
            'sigla'          => ['required', 'string', 'max:20'],
            'nit'            => ['required', 'regex:/^\d{9}-\d$/'],
            'licencia'       => ['required', 'regex:/^\d{1,10}$/'],
            'fecha_licencia' => ['required', 'date', 'before_or_equal:today'],
            'entidad'        => ['required', 'string', 'min:5', 'max:150'],
            'telefono'       => ['required', $tel],
            'telefono2'      => ['nullable', $tel],
            'correo'         => ['nullable', 'email', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return ['nit' => 'NIT', 'fecha_licencia' => 'fecha de la licencia', 'telefono' => 'teléfono', 'telefono2' => 'otro teléfono'];
    }

    public function messages(): array
    {
        return [
            'nit.regex'       => 'Escribe el NIT así: 900820764-1.',
            'licencia.regex'  => 'Escribe el número de la licencia (solo números).',
            'telefono.regex'  => 'Celular de 10 dígitos que empiece por 3, o fijo que empiece por 60.',
            'telefono2.regex' => 'Revisa el número o déjalo vacío.',
        ];
    }
}
