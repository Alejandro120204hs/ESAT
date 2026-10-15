<?php

namespace App\Http\Requests\Admin\Acudientes;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Academico\AcudienteService;
use App\Services\Academico\EstudianteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar / editar acudiente: sus datos y la lista completa de estudiantes a cargo.
 * Usuario del portal = correo; contraseña inicial = número de documento.
 */
class AcudienteRequest extends FormRequest
{
    /** Un acudiente es mayor de edad: no lleva tarjeta de identidad. */
    public const TIPOS_DOCUMENTO = [
        'CC'  => 'Cédula de ciudadanía',
        'CE'  => 'Cédula de extranjería',
        'PPT' => 'Permiso por Protección Temporal',
    ];

    /** Al editar: solo acudientes de la sede del admin. */
    public function authorize(): bool
    {
        $a = $this->route('acudiente');

        return ! $a || ($a->role === RolUsuario::Acudiente && $a->sede_id === (int) $this->user()->sede_id);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres'          => trim((string) $this->input('nombres')),
            'apellidos'        => trim((string) $this->input('apellidos')),
            'numero_documento' => preg_replace('/\D/', '', (string) $this->input('numero_documento')),
            'telefono'         => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'email'            => mb_strtolower(trim((string) $this->input('email'))),
            'profesion'        => trim((string) $this->input('profesion')) ?: null,
            'direccion'        => trim((string) $this->input('direccion')),
            'estudiantes'      => collect((array) $this->input('estudiantes', []))
                ->map(fn ($v) => ['id' => (int) ($v['id'] ?? 0), 'parentesco' => (string) ($v['parentesco'] ?? '')])
                ->values()->all(),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('acudiente')?->id;

        return [
            'nombres'                  => ['required', 'string', 'min:2', 'max:100'],
            'apellidos'                => ['required', 'string', 'min:2', 'max:100'],
            'tipo_documento'           => ['required', Rule::in(array_keys(self::TIPOS_DOCUMENTO))],
            'numero_documento'         => ['required', 'digits_between:6,11', Rule::unique('users', 'numero_documento')->ignore($id)],
            'profesion'                => ['nullable', 'string', 'max:100'],
            'telefono'                 => ['required', 'regex:/^3\d{9}$/'],
            'email'                    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'direccion'                => ['required', 'string', 'min:5', 'max:255'],
            'departamento_residencia'  => ['required', 'string', 'max:100'],
            'ciudad_residencia'        => ['required', 'string', 'max:100'],
            // Al registrar debe tener al menos un estudiante a cargo
            'estudiantes'              => $id ? ['present', 'array'] : ['required', 'array', 'min:1'],
            'estudiantes.*.id'         => ['required', 'integer', 'distinct'],
            'estudiantes.*.parentesco' => ['required', Rule::in(EstudianteService::PARENTESCOS)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $ids = array_column($this->input('estudiantes'), 'id');

            // Solo estudiantes de la sede
            $validos = User::where('role', RolUsuario::Estudiante)->where('sede_id', $this->user()->sede_id)
                ->whereIn('id', $ids)->count();
            if ($validos !== count($ids)) {
                $v->errors()->add('estudiantes', 'Uno de los estudiantes no está registrado en tu sede.');
                return;
            }

            // Al editar no se puede dejar a un menor sin acudiente
            if ($acudiente = $this->route('acudiente')) {
                $menor = app(AcudienteService::class)->menoresQueQuedarianSolos($acudiente, $ids)->first();
                if ($menor) {
                    $v->errors()->add('estudiantes', "{$menor->name} es menor de edad y quedaría sin acudiente. Para quitarlo, primero vincúlale otro acudiente.");
                }
            }
        }];
    }

    /** Datos del usuario (sin la lista de estudiantes). */
    public function datosAcudiente(): array
    {
        return $this->safe()->except('estudiantes');
    }

    public function attributes(): array
    {
        return [
            'tipo_documento'          => 'tipo de documento',
            'numero_documento'        => 'número de documento',
            'profesion'               => 'ocupación',
            'telefono'                => 'celular',
            'email'                   => 'correo electrónico',
            'direccion'               => 'dirección de residencia',
            'departamento_residencia' => 'departamento de residencia',
            'ciudad_residencia'       => 'ciudad de residencia',
            'estudiantes.*.parentesco' => 'parentesco',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_documento.digits_between' => 'El número de documento debe tener entre 6 y 11 dígitos.',
            'numero_documento.unique'         => 'Ya hay un usuario registrado con ese número de documento.',
            'telefono.regex'                  => 'El celular debe tener 10 dígitos y empezar por 3.',
            'email.unique'                    => 'Ese correo ya está registrado por otro usuario.',
            'estudiantes.required'            => 'Agrega al menos un estudiante a cargo.',
            'estudiantes.min'                 => 'Agrega al menos un estudiante a cargo.',
            'estudiantes.*.id.distinct'       => 'Agregaste el mismo estudiante dos veces.',
            'estudiantes.*.parentesco.*'      => 'Selecciona el parentesco con cada estudiante.',
        ];
    }
}
