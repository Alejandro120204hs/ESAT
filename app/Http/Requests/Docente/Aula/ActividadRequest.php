<?php

namespace App\Http\Requests\Docente\Aula;

use App\Models\Academico\Actividad;
use App\Models\Academico\Curso;
use Illuminate\Validation\Rule;

/**
 * Crear o editar una actividad: título, instrucciones, un módulo de la malla del grupo y la fecha y hora límite de
 * entrega. Al crearla, o al cambiar la fecha, debe quedar en el futuro (editar el texto de una que ya cerró sí se puede).
 */
class ActividadRequest extends AulaRequest
{
    protected function cursoId(): ?int
    {
        $a = $this->route('actividad');
        if ($a instanceof Actividad) {
            return $a->curso_id;
        }
        $c = $this->route('curso');

        return $c instanceof Curso ? $c->id : ($c ? (int) $c : null);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'titulo'        => $this->texto('titulo'),
            'instrucciones' => $this->texto('instrucciones'),
            'cierra'        => $this->fechaHora($this->input('cierra')),
        ]);
    }

    public function rules(): array
    {
        $modulos = $this->curso?->programa->modulos->pluck('id')->all() ?? [];
        $actual = $this->route('actividad');
        $mismaFecha = $actual instanceof Actividad && $this->input('cierra') === $actual->fecha_limite->format('Y-m-d H:i');

        return [
            'titulo'        => ['required', 'string', 'min:5', 'max:150'],
            'instrucciones' => ['required', 'string', 'min:10', 'max:3000'],
            'modulo_id'     => ['required', 'integer', Rule::in($modulos)],
            'cierra'        => array_merge(['required', 'date_format:Y-m-d H:i'], $mismaFecha ? [] : ['after:now']),
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required'        => 'Escribe el título de la actividad.',
            'titulo.min'             => 'El título debe tener al menos 5 letras.',
            'titulo.max'             => 'El título puede tener hasta 150 caracteres.',
            'instrucciones.required' => 'Escribe las instrucciones: qué deben hacer y entregar.',
            'instrucciones.min'      => 'Escribe las instrucciones: qué deben hacer y entregar.',
            'instrucciones.max'      => 'Las instrucciones pueden tener hasta 3.000 caracteres.',
            'modulo_id.required'     => 'Elige el módulo de la actividad.',
            'modulo_id.in'           => 'Elige un módulo de la malla del grupo.',
            'cierra.required'        => 'Elige la fecha y la hora límite de entrega.',
            'cierra.date_format'     => 'Elige la fecha y la hora límite de entrega.',
            'cierra.after'           => 'La fecha límite debe quedar después de ahora.',
        ];
    }
}
