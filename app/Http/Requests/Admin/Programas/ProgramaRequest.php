<?php

namespace App\Http\Requests\Admin\Programas;

use App\Models\Academico\Programa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => $this->filled('codigo') ? mb_strtoupper(trim($this->input('codigo'))) : null,
        ]);
    }

    public function rules(): array
    {
        $programa = $this->route('programa');

        return [
            'nombre'        => ['required', 'string', 'max:150', Rule::unique('programas')->ignore($programa)],
            'codigo'        => ['nullable', 'string', 'max:20', Rule::unique('programas')->ignore($programa)],
            'resolucion'    => ['nullable', 'string', 'max:255'],
            'nivel'         => ['required', Rule::in(Programa::NIVELES)],
            'escuela_id'    => ['required', 'exists:escuelas,id'],
            'modalidad'     => ['required', Rule::in(Programa::MODALIDADES)],
            'horas'         => ['required', 'integer', 'min:1', 'max:5000'],
            'descripcion'   => ['nullable', 'string', 'max:2000'],
            'perfil_egreso' => ['nullable', 'string', 'max:2000'],
            'jornadas'      => ['required', 'array', 'min:1'],
            'jornadas.*'    => [Rule::in(Programa::JORNADAS)],
            'cupo_grupo'    => ['required', 'integer', 'min:1', 'max:60'],
            'fecha_inicio'  => ['nullable', 'date'],
            'duracion_meses'=> ['required', 'integer', 'min:1', 'max:60'],
            'tipo_periodo'  => ['required', Rule::in(array_keys(Programa::PERIODOS))],
            'estado'        => ['required', Rule::in(Programa::ESTADOS)],
            'precio_total'  => ['required', 'numeric', 'min:0', 'max:999999999'],
            'matricula'     => ['required', 'numeric', 'min:0', 'lte:precio_total'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre'         => 'nombre del programa',
            'codigo'         => 'código interno',
            'resolucion'     => 'resolución de aprobación',
            'escuela_id'     => 'escuela',
            'horas'          => 'duración en horas',
            'perfil_egreso'  => 'perfil del egresado',
            'cupo_grupo'     => 'cupo máximo por grupo',
            'fecha_inicio'   => 'fecha de inicio',
            'duracion_meses' => 'duración en meses',
            'tipo_periodo'   => 'tipo de periodo',
            'precio_total'   => 'valor total del programa',
            'matricula'      => 'matrícula',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique'    => 'Ya existe un programa con ese nombre.',
            'codigo.unique'    => 'Ya existe un programa con ese código.',
            'jornadas.required'=> 'Selecciona al menos una jornada.',
            'jornadas.min'     => 'Selecciona al menos una jornada.',
            'matricula.lte'    => 'La matrícula no puede ser mayor que el valor total del programa.',
        ];
    }
}
