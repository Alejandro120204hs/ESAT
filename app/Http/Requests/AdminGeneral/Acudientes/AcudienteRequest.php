<?php

namespace App\Http\Requests\AdminGeneral\Acudientes;

use App\Enums\RolUsuario;
use App\Http\Requests\Admin\Acudientes\AcudienteRequest as AcudienteSedeRequest;
use App\Rules\CiudadDeColombia;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar o editar un acudiente desde el panel general: las mismas reglas de la secretaría en la sede elegida
 * (al registrar, al menos un estudiante a cargo de esa sede; ningún menor queda sin acudiente). Al editar, la lista
 * de estudiantes llega completa (la que tiene hoy) y la sede solo cambia si no tiene estudiantes a cargo.
 */
class AcudienteRequest extends AcudienteSedeRequest
{
    public function authorize(): bool
    {
        $a = $this->route('acudiente');

        return ! $a || $a->role === RolUsuario::Acudiente;
    }

    public function rules(): array
    {
        $reglas = parent::rules() + [
            'sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')],
            'genero'  => ['nullable', Rule::in(['femenino', 'masculino', 'otro'])],
        ];
        $reglas['departamento_residencia'][] = new CiudadDeColombia();
        $reglas['ciudad_residencia'][] = new CiudadDeColombia((string) $this->input('departamento_residencia'));

        return $reglas;
    }

    public function after(): array
    {
        return array_merge([function (Validator $v) {
            $a = $this->route('acudiente');
            if ($a && ! $v->errors()->has('sede_id') && (int) $this->input('sede_id') !== $a->sede_id && $a->estudiantesACargo()->exists()) {
                $v->errors()->add('sede_id', 'Tiene estudiantes a cargo en su sede: desvincúlalos o pásalos a otro acudiente antes de cambiarlo de sede.');
            }
        }], parent::after());
    }

    /** Datos de la cuenta, sin la lista de estudiantes ni la sede (la sede se guarda aparte). */
    public function datosAcudiente(): array
    {
        return collect(parent::datosAcudiente())->except('sede_id')->all();
    }

    protected function sedeId(): int
    {
        return (int) $this->input('sede_id');
    }

    public function attributes(): array
    {
        return parent::attributes() + ['sede_id' => 'sede', 'genero' => 'género'];
    }
}
