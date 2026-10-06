<?php

namespace App\Http\Requests\Admin\Docentes;

use App\Models\Academico\DocentePerfil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Documento y teléfono se guardan solo con dígitos (el pasaporte admite letras). */
    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo_documento');
        $doc  = (string) $this->input('numero_documento');

        $this->merge([
            'numero_documento' => $tipo === 'PA'
                ? mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $doc))
                : preg_replace('/\D/', '', $doc),
            'telefono' => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'email'    => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $docente = $this->route('docente');

        return [
            'nombres'                 => ['required', 'string', 'min:2', 'max:100'],
            'apellidos'               => ['required', 'string', 'min:2', 'max:100'],
            'tipo_documento'          => ['required', Rule::in(['CC', 'CE', 'PA'])],
            'numero_documento'        => ['required', 'string', 'min:5', 'max:15', Rule::unique('users')->ignore($docente)],
            'email'                   => ['required', 'email', 'max:255', Rule::unique('users')->ignore($docente)],
            'telefono'                => ['required', 'regex:/^3\d{9}$/'],
            'fecha_nacimiento'        => ['nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'direccion'               => ['nullable', 'string', 'max:255'],
            'departamento_residencia' => ['nullable', 'string', 'max:100'],
            'ciudad_residencia'       => ['nullable', 'required_with:departamento_residencia', 'string', 'max:100'],
            'profesion'               => ['required', 'string', 'min:3', 'max:150'],
            'titulo_academico'        => ['required', 'string', 'min:3', 'max:150'],
            'escuela_id'              => ['required', 'exists:escuelas,id'],
            'vinculacion'             => ['required', Rule::in(array_keys(DocentePerfil::VINCULACIONES))],
            'estado'                  => ['required', Rule::in(array_keys(DocentePerfil::ESTADOS))],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_documento'          => 'tipo de documento',
            'numero_documento'        => 'número de documento',
            'email'                   => 'correo electrónico',
            'telefono'                => 'teléfono',
            'fecha_nacimiento'        => 'fecha de nacimiento',
            'departamento_residencia' => 'departamento de residencia',
            'ciudad_residencia'       => 'ciudad de residencia',
            'profesion'               => 'título profesional',
            'titulo_academico'        => 'especialidad',
            'escuela_id'              => 'escuela',
            'vinculacion'             => 'tipo de vinculación',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_documento.unique'          => 'Ya hay un usuario registrado con ese número de documento.',
            'email.unique'                     => 'Ese correo ya está registrado por otro usuario.',
            'telefono.regex'                   => 'El teléfono debe ser un celular de 10 dígitos que empiece por 3.',
            'fecha_nacimiento.before_or_equal' => 'El docente debe ser mayor de edad.',
            'ciudad_residencia.required_with'  => 'Selecciona la ciudad de residencia.',
        ];
    }
}
