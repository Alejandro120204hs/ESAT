<?php

namespace App\Http\Requests\Docente\Aula;

use App\Models\Academico\Curso;
use App\Models\Academico\Evaluacion;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Crear o editar una evaluación de selección múltiple: título, descripción opcional, un módulo de la malla del grupo,
 * fecha y hora de cierre futura, y de 1 a 30 preguntas con 2 a 6 opciones cada una y exactamente una correcta. Las
 * mismas reglas que valida docente/aula.js. Tiempo límite opcional (Evaluacion::DURACIONES minutos). Lo que se puede
 * cambiar cuando alguien ya la comenzó o la respondió lo revisa AulaService con la fila bloqueada. Al editar se puede
 * dejar el mismo cierre aunque ya haya pasado (para corregir un texto).
 */
class EvaluacionRequest extends AulaRequest
{
    protected function cursoId(): ?int
    {
        $ev = $this->route('evaluacion');
        if ($ev instanceof Evaluacion) {
            return $ev->curso_id;
        }
        $c = $this->route('curso');

        return $c instanceof Curso ? $c->id : ($c ? (int) $c : null);
    }

    protected function prepareForValidation(): void
    {
        $preguntas = [];
        foreach (array_values((array) $this->input('preguntas', [])) as $p) {
            $preguntas[] = [
                'enunciado' => is_string($p['enunciado'] ?? null) ? trim($p['enunciado']) : null,
                'opciones'  => array_map(fn ($o) => [
                    'texto'    => is_string($o['texto'] ?? null) ? trim($o['texto']) : null,
                    'correcta' => filter_var($o['correcta'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ], array_values((array) ($p['opciones'] ?? []))),
            ];
        }
        $this->merge([
            'duracion'    => $this->input('duracion') ?: null,
            'titulo'      => $this->texto('titulo'),
            'descripcion' => $this->texto('descripcion'),
            'cierra'      => $this->fechaHora($this->input('cierra')),
            'preguntas'   => $preguntas,
        ]);
    }

    public function rules(): array
    {
        $modulos = $this->curso?->programa->modulos->pluck('id')->all() ?? [];

        return [
            'titulo'                         => ['required', 'string', 'min:5', 'max:150'],
            'descripcion'                    => ['nullable', 'string', 'max:1000'],
            'modulo_id'                      => ['required', 'integer', Rule::in($modulos)],
            'cierra'                         => array_merge(['required', 'date_format:Y-m-d H:i'], $this->mismoCierre() ? [] : ['after:now']),
            'duracion'                       => ['nullable', 'integer', Rule::in(Evaluacion::DURACIONES)],
            'preguntas'                      => ['required', 'array', 'min:1', 'max:30'],
            'preguntas.*.enunciado'          => ['required', 'string', 'min:3', 'max:500'],
            'preguntas.*.opciones'           => ['required', 'array', 'min:2', 'max:6'],
            'preguntas.*.opciones.*.texto'   => ['required', 'string', 'max:300'],
            'preguntas.*.opciones.*.correcta' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            // Una evaluación que ya cerró no se reabre: sus estudiantes ya pudieron ver las correctas (ES2)
            $actual = $this->route('evaluacion');
            if ($actual instanceof Evaluacion && $actual->cerrada() && ! $this->mismoCierre()) {
                $v->errors()->add('cierra', Evaluacion::NO_REABRIR);

                return;
            }
            foreach ($this->input('preguntas') as $i => $p) {
                $correctas = count(array_filter($p['opciones'], fn ($o) => $o['correcta']));
                if ($correctas !== 1) {
                    $v->errors()->add("preguntas.$i", 'Marca una sola respuesta correcta en la pregunta '.($i + 1).'.');

                    return;
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'titulo.required'                     => 'Escribe el título de la evaluación.',
            'titulo.min'                          => 'El título debe tener al menos 5 letras.',
            'titulo.max'                          => 'El título puede tener hasta 150 caracteres.',
            'descripcion.max'                     => 'La descripción puede tener hasta 1.000 caracteres.',
            'modulo_id.required'                  => 'Elige el módulo de la evaluación.',
            'modulo_id.in'                        => 'Elige un módulo de la malla del grupo.',
            'cierra.required'                     => 'Elige la fecha y la hora en que cierra.',
            'cierra.date_format'                  => 'Elige la fecha y la hora en que cierra.',
            'cierra.after'                        => 'La evaluación debe cerrar después de ahora.',
            'duracion.in'                         => 'Elige un tiempo de la lista.',
            'preguntas.required'                  => 'Agrega al menos una pregunta.',
            'preguntas.min'                       => 'Agrega al menos una pregunta.',
            'preguntas.max'                       => 'Una evaluación puede tener hasta 30 preguntas.',
            'preguntas.*.enunciado.required'      => 'Escribe el enunciado de cada pregunta.',
            'preguntas.*.enunciado.min'           => 'Escribe el enunciado de cada pregunta.',
            'preguntas.*.enunciado.max'           => 'Un enunciado puede tener hasta 500 caracteres.',
            'preguntas.*.opciones.min'            => 'Cada pregunta necesita al menos 2 opciones.',
            'preguntas.*.opciones.max'            => 'Cada pregunta puede tener hasta 6 opciones.',
            'preguntas.*.opciones.*.texto.required' => 'Escribe el texto de cada opción.',
            'preguntas.*.opciones.*.texto.max'    => 'Una opción puede tener hasta 300 caracteres.',
        ];
    }

    /** Al editar, el cierre que ya tenía (aunque haya pasado) se puede conservar. */
    private function mismoCierre(): bool
    {
        $actual = $this->route('evaluacion');

        return $actual instanceof Evaluacion && $this->input('cierra') === $actual->fecha_limite->format('Y-m-d H:i');
    }
}
