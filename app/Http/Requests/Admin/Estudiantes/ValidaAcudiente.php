<?php

namespace App\Http\Requests\Admin\Estudiantes;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Academico\EstudianteService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas del acudiente, compartidas por la inscripción y por "Cambiar acudiente".
 * Llega como acudiente.id (uno ya registrado) o con sus datos para registrarlo.
 */
trait ValidaAcudiente
{
    protected function limpiarAcudiente(): void
    {
        $a = (array) $this->input('acudiente', []);
        if (! $a) {
            return;
        }
        $this->merge(['acudiente' => [
            'id'        => isset($a['id']) && $a['id'] !== '' ? (int) $a['id'] : null,
            'documento' => preg_replace('/\D/', '', (string) ($a['documento'] ?? '')),
            'nombres'   => trim((string) ($a['nombres'] ?? '')),
            'apellidos' => trim((string) ($a['apellidos'] ?? '')),
            'telefono'  => preg_replace('/\D/', '', (string) ($a['telefono'] ?? '')),
            'email'     => mb_strtolower(trim((string) ($a['email'] ?? ''))),
        ]]);
    }

    /** $requerido: 'required' (menor de edad, o se pidió registrarlo) o 'nullable'. */
    protected function reglasAcudiente(string $requerido): array
    {
        // Sin acudiente no se validan sus datos (solo si era obligatorio)
        if (! $this->input('acudiente')) {
            return ['acudiente' => [$requerido, 'array'], 'parentesco' => ['nullable']];
        }
        $nuevo = 'required_without:acudiente.id';

        return [
            'acudiente'           => [$requerido, 'array'],
            'acudiente.id'        => ['nullable', 'integer'],
            'acudiente.documento' => ['nullable', $nuevo, 'digits_between:6,11'],
            'acudiente.nombres'   => ['nullable', $nuevo, 'string', 'min:2', 'max:100'],
            'acudiente.apellidos' => ['nullable', $nuevo, 'string', 'min:2', 'max:100'],
            'acudiente.telefono'  => ['nullable', $nuevo, 'regex:/^3\d{9}$/'],
            'acudiente.email'     => ['nullable', $nuevo, 'email', 'max:255', Rule::unique('users', 'email')],
            'parentesco'          => ['required_with:acudiente', 'nullable', Rule::in(EstudianteService::PARENTESCOS)],
        ];
    }

    /** Lo que cruza datos: que el acudiente exista como acudiente y no sea el mismo estudiante. */
    protected function validarAcudiente(Validator $v, string $documentoEstudiante): void
    {
        $a = $this->input('acudiente');
        if (! $a) {
            return;
        }

        if ($a['id']) {
            $acudiente = User::find($a['id']);
            if (! $acudiente || $acudiente->role !== RolUsuario::Acudiente) {
                $v->errors()->add('acudiente.id', 'Ese acudiente no está registrado.');
                return;
            }
            $documento = $acudiente->numero_documento;
        } else {
            $documento = $a['documento'];
            if (User::where('numero_documento', $documento)->exists()) {
                $v->errors()->add('acudiente.documento', 'Ya hay un usuario registrado con esa cédula. Búscalo para vincularlo.');
                return;
            }
        }

        if ($documento === $documentoEstudiante) {
            $v->errors()->add('acudiente.documento', 'El acudiente no puede ser el mismo estudiante.');
        }
    }

    protected function atributosAcudiente(): array
    {
        return [
            'acudiente.documento' => 'cédula del acudiente',
            'acudiente.nombres'   => 'nombres del acudiente',
            'acudiente.apellidos' => 'apellidos del acudiente',
            'acudiente.telefono'  => 'celular del acudiente',
            'acudiente.email'     => 'correo del acudiente',
        ];
    }

    protected function mensajesAcudiente(): array
    {
        return [
            'acudiente.required'                   => 'Registra al acudiente: el estudiante es menor de edad.',
            'acudiente.*.required_without'         => 'Completa los datos del acudiente.',
            'acudiente.documento.digits_between'   => 'La cédula del acudiente debe tener entre 6 y 11 dígitos.',
            'acudiente.telefono.regex'             => 'El celular del acudiente debe tener 10 dígitos y empezar por 3.',
            'acudiente.email.unique'               => 'Ese correo ya está registrado por otro usuario.',
            'parentesco.required_with'             => 'Selecciona el parentesco con el estudiante.',
        ];
    }
}
