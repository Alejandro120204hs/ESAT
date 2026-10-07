<?php

namespace App\Http\Requests\Admin\Estudiantes;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar (POST) o editar (PUT) los datos de un estudiante. No lo matricula:
 * programa y grupo son de Matrículas. Al registrar se puede incluir el acudiente
 * (obligatorio si es menor); al editar, el acudiente se cambia con su propia acción.
 */
class EstudianteRequest extends FormRequest
{
    use ValidaAcudiente;

    public const GENEROS = ['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'];
    public const TIPOS_DOCUMENTO = [
        'TI'  => 'Tarjeta de identidad',
        'CC'  => 'Cédula de ciudadanía',
        'CE'  => 'Cédula de extranjería',
        'PPT' => 'Permiso por Protección Temporal',
    ];

    /** Al editar: solo estudiantes de la sede del admin. */
    public function authorize(): bool
    {
        $e = $this->route('estudiante');

        return ! $e || ($e->role === RolUsuario::Estudiante && $e->sede_id === (int) $this->user()->sede_id);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres'          => trim((string) $this->input('nombres')),
            'apellidos'        => trim((string) $this->input('apellidos')),
            'numero_documento' => preg_replace('/\D/', '', (string) $this->input('numero_documento')),
            'telefono'         => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'email'            => mb_strtolower(trim((string) $this->input('email'))),
            'direccion'        => trim((string) $this->input('direccion')),
        ]);
        // El acudiente solo va al registrar, y solo si es menor o se pidió registrarlo
        if ($this->editando() || (! $this->esMenor() && ! $this->boolean('con_acudiente'))) {
            $this->merge(['acudiente' => null, 'parentesco' => null]);
        } else {
            $this->limpiarAcudiente();
        }
    }

    public function rules(): array
    {
        $id = $this->route('estudiante')?->id;
        $reglas = [
            'nombres'                 => ['required', 'string', 'min:2', 'max:100'],
            'apellidos'               => ['required', 'string', 'min:2', 'max:100'],
            'fecha_nacimiento'        => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(100)->toDateString()],
            'departamento_nacimiento' => ['required', 'string', 'max:100'],
            'lugar_nacimiento'        => ['required', 'string', 'max:100'],
            'genero'                  => ['required', Rule::in(array_keys(self::GENEROS))],
            'tipo_documento'          => ['required', Rule::in(array_keys(self::TIPOS_DOCUMENTO))],
            'numero_documento'        => ['required', 'digits_between:6,11', Rule::unique('users', 'numero_documento')->ignore($id)],
            'telefono'                => ['required', 'regex:/^3\d{9}$/'],
            'email'                   => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'direccion'               => ['required', 'string', 'min:5', 'max:255'],
            'departamento_residencia' => ['required', 'string', 'max:100'],
            'ciudad_residencia'       => ['required', 'string', 'max:100'],
        ];

        if ($this->editando()) {
            return $reglas;
        }

        return array_merge($reglas, $this->reglasAcudiente($this->esMenor() || $this->boolean('con_acudiente') ? 'required' : 'nullable'));
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // El documento debe corresponder a la edad
            $edad = Carbon::parse($this->input('fecha_nacimiento'))->age;
            if ($this->input('tipo_documento') === 'TI' && $edad >= 18) {
                $v->errors()->add('tipo_documento', "Con {$edad} años el documento es la cédula de ciudadanía, no la tarjeta de identidad.");
            }
            if ($this->input('tipo_documento') === 'CC' && $edad < 18) {
                $v->errors()->add('tipo_documento', 'Un menor de edad se identifica con tarjeta de identidad.');
            }

            if (! $this->editando()) {
                $this->validarAcudiente($v, $this->input('numero_documento'));
            }
        }];
    }

    /** Datos personales para crear o actualizar el usuario. */
    public function datosEstudiante(): array
    {
        return $this->safe()->only([
            'nombres', 'apellidos', 'fecha_nacimiento', 'departamento_nacimiento', 'lugar_nacimiento', 'genero', 'tipo_documento', 'numero_documento',
            'telefono', 'email', 'direccion', 'departamento_residencia', 'ciudad_residencia',
        ]);
    }

    private function editando(): bool
    {
        return (bool) $this->route('estudiante');
    }

    private function esMenor(): bool
    {
        try {
            return $this->filled('fecha_nacimiento') && Carbon::parse($this->input('fecha_nacimiento'))->age < 18;
        } catch (\Throwable) {
            return false;
        }
    }

    public function attributes(): array
    {
        return array_merge([
            'fecha_nacimiento'        => 'fecha de nacimiento',
            'departamento_nacimiento' => 'departamento de nacimiento',
            'lugar_nacimiento'        => 'ciudad de nacimiento',
            'genero'                  => 'género',
            'tipo_documento'          => 'tipo de documento',
            'numero_documento'        => 'número de documento',
            'telefono'                => 'celular',
            'email'                   => 'correo electrónico',
            'direccion'               => 'dirección de residencia',
            'departamento_residencia' => 'departamento de residencia',
            'ciudad_residencia'       => 'ciudad de residencia',
        ], $this->atributosAcudiente());
    }

    public function messages(): array
    {
        return array_merge([
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'fecha_nacimiento.after'           => 'Revisa el año de nacimiento.',
            'numero_documento.digits_between'  => 'El número de documento debe tener entre 6 y 11 dígitos.',
            'numero_documento.unique'          => 'Ya hay un usuario registrado con ese número de documento.',
            'telefono.regex'                   => 'El celular debe tener 10 dígitos y empezar por 3.',
            'email.unique'                     => 'Ese correo ya está registrado por otro usuario.',
        ], $this->mensajesAcudiente());
    }
}
