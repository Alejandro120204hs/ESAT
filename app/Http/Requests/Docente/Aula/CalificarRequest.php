<?php

namespace App\Http\Requests\Docente\Aula;

use App\Models\Academico\Entrega;

/**
 * Calificar (o corregir la calificación de) una entrega: nota de 1,0 a 5,0 con un decimal (acepta coma) y un
 * comentario opcional para el estudiante. Esta nota es aparte de la del módulo.
 */
class CalificarRequest extends AulaRequest
{
    protected function cursoId(): ?int
    {
        $e = $this->route('entrega');
        $e?->loadMissing('actividad');

        return $e instanceof Entrega ? $e->actividad?->curso_id : null;
    }

    protected function prepareForValidation(): void
    {
        $n = $this->input('nota');
        $this->merge([
            'nota'       => is_string($n) ? str_replace(',', '.', trim($n)) : $n,
            'comentario' => $this->texto('comentario'),
        ]);
    }

    public function rules(): array
    {
        return [
            'nota'       => ['required', 'numeric', 'between:1,5', 'regex:/^\d(\.\d)?$/'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nota.required'  => 'Escribe la nota, de 1,0 a 5,0.',
            'nota.numeric'   => 'La nota debe ser un número de 1,0 a 5,0.',
            'nota.between'   => 'La nota debe estar entre 1,0 y 5,0.',
            'nota.regex'     => 'La nota lleva un solo decimal (por ejemplo 4,5).',
            'comentario.max' => 'El comentario puede tener hasta 1.000 caracteres.',
        ];
    }
}
