<?php

namespace App\Http\Requests\AdminGeneral\Docentes;

use App\Http\Requests\Admin\Docentes\DocenteRequest as DocenteSedeRequest;
use App\Rules\CiudadDeColombia;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar o editar un docente desde el panel general: las reglas de la secretaría (un docente que deja de estar activo no
 * se queda con grupos vigentes) más su sede, y desde aquí se piden completos los datos personales y de residencia.
 * La sede solo cambia si no tiene grupos vigentes en la que tiene hoy.
 */
class DocenteRequest extends DocenteSedeRequest
{
    public function rules(): array
    {
        $reglas = parent::rules();
        $reglas['fecha_nacimiento'][0] = 'required';
        $reglas['direccion'] = ['required', 'string', 'min:5', 'max:255'];
        $reglas['departamento_residencia'] = ['required', 'string', new CiudadDeColombia()];
        $reglas['ciudad_residencia'] = ['required', 'string', new CiudadDeColombia((string) $this->input('departamento_residencia'))];

        return $reglas + [
            'genero'  => ['required', Rule::in(['femenino', 'masculino', 'otro'])],
            'sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')],
        ];
    }

    public function after(): array
    {
        return array_merge(parent::after(), [function (Validator $v) {
            $d = $this->route('docente');
            if (! $d || $v->errors()->has('sede_id') || (int) $this->input('sede_id') === $d->sede_id) {
                return;
            }
            $n = $d->cursosComoDocente()->where('cursos.sede_id', $d->sede_id)->where('estado', '!=', 'finalizado')->count();
            if ($n) {
                $v->errors()->add('sede_id', "Tiene {$n} ".($n === 1 ? 'grupo vigente' : 'grupos vigentes').' en su sede: no se puede cambiar de sede todavía.');
            }
        }]);
    }

    public function attributes(): array
    {
        return parent::attributes() + ['sede_id' => 'sede', 'genero' => 'género', 'direccion' => 'dirección'];
    }
}
