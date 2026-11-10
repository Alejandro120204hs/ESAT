<?php

namespace App\Http\Requests\AdminGeneral\Estudiantes;

use App\Enums\RolUsuario;
use App\Http\Requests\Admin\Estudiantes\EstudianteRequest as EstudianteSedeRequest;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Rules\CiudadDeColombia;
use App\Services\Academico\EstudianteService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar o editar a un estudiante desde el panel general: las mismas reglas de la secretaría, en la sede que se elija.
 * Al editar también se puede cambiar su acudiente (de la misma sede) o su sede, si no tiene matrícula vigente.
 */
class EstudianteRequest extends EstudianteSedeRequest
{
    public function authorize(): bool
    {
        $e = $this->route('estudiante');

        return ! $e || $e->role === RolUsuario::Estudiante;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->route('estudiante')) {
            $this->merge(['acudiente_id' => $this->filled('acudiente_id') ? (int) $this->input('acudiente_id') : null]);
        }
    }

    public function rules(): array
    {
        $reglas = parent::rules() + ['sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')]];
        $reglas['departamento_nacimiento'][] = new CiudadDeColombia();
        $reglas['lugar_nacimiento'][] = new CiudadDeColombia((string) $this->input('departamento_nacimiento'));
        $reglas['departamento_residencia'][] = new CiudadDeColombia();
        $reglas['ciudad_residencia'][] = new CiudadDeColombia((string) $this->input('departamento_residencia'));
        if ($this->route('estudiante')) {
            $reglas['acudiente_id'] = ['nullable', 'integer'];
            $reglas['parentesco'] = ['nullable', 'required_with:acudiente_id', Rule::in(EstudianteService::PARENTESCOS)];
        }

        return $reglas;
    }

    public function after(): array
    {
        return array_merge(parent::after(), [function (Validator $v) {
            $e = $this->route('estudiante');
            if (! $e || $v->errors()->isNotEmpty()) {
                return;
            }
            // Con matrícula vigente la sede no cambia (la matrícula y sus pagos son de esa sede)
            if ((int) $this->input('sede_id') !== $e->sede_id
                && Matricula::where('estudiante_id', $e->id)->whereIn('estado', Matricula::VIGENTES)->exists()) {
                $v->errors()->add('sede_id', 'Tiene una matrícula vigente en su sede: no se cambia de sede desde aquí.');

                return;
            }
            $id = $this->input('acudiente_id');
            if (! $id) {
                if (Carbon::parse($this->input('fecha_nacimiento'))->age < 18) {
                    $v->errors()->add('acudiente_id', 'Es menor de edad: necesita un acudiente.');
                }

                return;
            }
            $a = User::find($id);
            if (! $a || $a->role !== RolUsuario::Acudiente || $a->sede_id !== $this->sedeId()) {
                $v->errors()->add('acudiente_id', 'Ese acudiente no está registrado en la sede elegida.');
            }
        }]);
    }

    /** La sede elegida en el formulario. */
    protected function sedeId(): int
    {
        return (int) $this->input('sede_id');
    }

    public function attributes(): array
    {
        return parent::attributes() + ['sede_id' => 'sede', 'acudiente_id' => 'acudiente'];
    }
}
