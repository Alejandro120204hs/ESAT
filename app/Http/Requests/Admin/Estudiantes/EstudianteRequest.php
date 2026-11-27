<?php

namespace App\Http\Requests\Admin\Estudiantes;

use App\Enums\RolUsuario;
use App\Rules\CiudadDeColombia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar (POST) o editar (PUT) los datos de un estudiante. No lo matricula:
 * programa y grupo son de Matrículas. El contacto de emergencia (nombre, celular y parentesco) es obligatorio
 * para todos y va en el mismo formulario, al registrar y al editar.
 */
class EstudianteRequest extends FormRequest
{
    use ValidaContacto;

    public const GENEROS = ['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'];
    public const TIPOS_DOCUMENTO = [
        'TI'  => 'Tarjeta de identidad',
        'CC'  => 'Cédula de ciudadanía',
        'CE'  => 'Cédula de extranjería',
        'PPT' => 'Permiso por Protección Temporal',
    ];
    /** Documentos con los que se puede registrar que nació en otro país (decidido con el usuario el 27-11-2026). */
    public const DOCUMENTOS_EXTRANJERO = ['CE', 'PPT'];

    /** Países (sin Colombia) del mismo listado que usa el formulario: public/assets/data/paises.json. */
    public static function paises(): array
    {
        return once(fn () => json_decode((string) file_get_contents(public_path('assets/data/paises.json')), true) ?: []);
    }

    /** Al editar: solo estudiantes de la sede del admin. */
    public function authorize(): bool
    {
        $e = $this->route('estudiante');

        return ! $e || ($e->role === RolUsuario::Estudiante && $e->sede_id === $this->sedeId());
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
            'pais_nacimiento'  => trim((string) $this->input('pais_nacimiento')) ?: null,
            'lugar_nacimiento' => trim(preg_replace('/\s+/', ' ', (string) $this->input('lugar_nacimiento'))),
        ]);
        $this->limpiarContacto();
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

        // Nacido en otro país: el país de la lista y la ciudad escrita, sin departamento. En Colombia: el mismo listado del
        // combobox (antes solo lo revisaba el panel general)
        $reglas['pais_nacimiento'] = ['nullable', 'string', Rule::in(self::paises())];
        if ($this->nacioFuera()) {
            $reglas['departamento_nacimiento'] = ['exclude'];
            $reglas['lugar_nacimiento'] = ['required', 'string', 'min:2', 'max:100'];
        } else {
            $reglas['departamento_nacimiento'][] = new CiudadDeColombia();
            $reglas['lugar_nacimiento'][] = new CiudadDeColombia((string) $this->input('departamento_nacimiento'));
        }
        $reglas['departamento_residencia'][] = new CiudadDeColombia();
        $reglas['ciudad_residencia'][] = new CiudadDeColombia((string) $this->input('departamento_residencia'));

        return array_merge($reglas, $this->reglasContacto());
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // El documento debe corresponder a la edad
            $edad = Carbon::parse($this->input('fecha_nacimiento'))->age;
            // Quien se registró menor con TI y ya cumplió 18 se puede seguir editando (p. ej. su celular) mientras trae la
            // cédula: la regla vale al registrar o al pasarlo a TI
            $yaTeniaTi = $this->editando() && $this->route('estudiante')->tipo_documento === 'TI';
            if ($this->input('tipo_documento') === 'TI' && $edad >= 18 && ! $yaTeniaTi) {
                $v->errors()->add('tipo_documento', "Con {$edad} años el documento es la cédula de ciudadanía, no la tarjeta de identidad.");
            }
            if ($this->input('tipo_documento') === 'CC' && $edad < 18) {
                $v->errors()->add('tipo_documento', 'Un menor de edad se identifica con tarjeta de identidad.');
            }

            if ($this->nacioFuera() && ! in_array($this->input('tipo_documento'), self::DOCUMENTOS_EXTRANJERO, true)) {
                $v->errors()->add('pais_nacimiento', 'Solo quien se identifica con cédula de extranjería o PPT se registra como nacido en otro país.');
            }

            $this->validarContacto($v, $this->input('telefono'));
        }];
    }

    /** Datos personales para crear o actualizar el usuario. */
    public function datosEstudiante(): array
    {
        $datos = $this->safe()->only([
            'nombres', 'apellidos', 'fecha_nacimiento', 'departamento_nacimiento', 'lugar_nacimiento', 'genero', 'tipo_documento', 'numero_documento',
            'telefono', 'email', 'direccion', 'departamento_residencia', 'ciudad_residencia',
        ]);

        // Nacido en otro país: sin departamento; en Colombia: sin país
        return $this->nacioFuera()
            ? ['departamento_nacimiento' => null, 'pais_nacimiento' => $this->input('pais_nacimiento')] + $datos
            : $datos + ['pais_nacimiento' => null];
    }

    private function nacioFuera(): bool
    {
        return $this->filled('pais_nacimiento');
    }

    private function editando(): bool
    {
        return (bool) $this->route('estudiante');
    }

    public function attributes(): array
    {
        return array_merge([
            'fecha_nacimiento'        => 'fecha de nacimiento',
            'departamento_nacimiento' => 'departamento de nacimiento',
            'lugar_nacimiento'        => 'ciudad de nacimiento',
            'pais_nacimiento'         => 'país de nacimiento',
            'genero'                  => 'género',
            'tipo_documento'          => 'tipo de documento',
            'numero_documento'        => 'número de documento',
            'telefono'                => 'celular',
            'email'                   => 'correo electrónico',
            'direccion'               => 'dirección de residencia',
            'departamento_residencia' => 'departamento de residencia',
            'ciudad_residencia'       => 'ciudad de residencia',
        ], $this->atributosContacto());
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
        ], $this->mensajesContacto());
    }

    /** Sede en la que se valida: la de trabajo (secretaría o Admin dentro de una sede). El panel general la sobrescribe. */
    protected function sedeId(): int
    {
        return (int) $this->user()->sedeTrabajoId();
    }
}
