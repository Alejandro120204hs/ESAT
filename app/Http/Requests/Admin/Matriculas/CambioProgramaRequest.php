<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Models\Academico\Curso;
use App\Models\Finanzas\Matricula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Cambio de programa: la matrícula vigente queda retirada y se crea otra en un grupo de OTRO
 * programa de la sede (activo, sin terminar y con cupo). Para el mismo programa, Cambiar de grupo.
 */
class CambioProgramaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('matricula')->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'curso_id' => ['required', 'integer'],
            'motivo'   => ['required', 'string', 'min:5', 'max:200'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $m = $this->route('matricula');
            if (! in_array($m->estado, Matricula::VIGENTES, true)) {
                $v->errors()->add('curso_id', 'Solo una matrícula activa o aplazada puede cambiar de programa.');
                return;
            }
            $g = $this->grupo();
            if (! $g || $g->sede_id !== $m->sede_id) {
                $v->errors()->add('curso_id', 'Ese grupo no pertenece a tu sede.');
                return;
            }
            if ($g->programa_id === $m->programa_id) {
                $v->errors()->add('curso_id', 'Ese grupo es del mismo programa: usa «Cambiar de grupo».');
                return;
            }
            $programa = $g->programa;
            if ($programa->estado !== 'activo' || ! $programa->sedes()->whereKey($m->sede_id)->exists()) {
                $v->errors()->add('curso_id', "El programa {$programa->nombre} no está activo en tu sede.");
            } elseif ($g->estado === 'finalizado') {
                $v->errors()->add('curso_id', "El {$g->nombre} ({$g->codigo}) ya finalizó.");
            } elseif ($g->cuposLibres() <= 0) {
                $v->errors()->add('curso_id', "El {$g->nombre} ({$g->codigo}) ya no tiene cupos libres.");
            }
        }];
    }

    public function grupo(): ?Curso
    {
        return Curso::with('programa')->find($this->input('curso_id'));
    }

    public function messages(): array
    {
        return [
            'curso_id.required' => 'Selecciona el grupo del programa nuevo.',
            'motivo.required'   => 'Escribe por qué cambia de programa.',
            'motivo.min'        => 'Describe el motivo con un poco más de detalle.',
        ];
    }
}
