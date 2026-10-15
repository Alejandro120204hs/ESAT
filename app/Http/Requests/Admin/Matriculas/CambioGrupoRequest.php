<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Models\Academico\Curso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Pasar una matrícula activa a otro grupo del mismo programa (con cupo y sin terminar). */
class CambioGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('matricula')->sede_id === (int) $this->user()->sede_id;
    }

    public function rules(): array
    {
        return ['curso_id' => ['required', 'integer']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $m = $this->route('matricula');
            if ($m->estado !== 'activo') {
                $v->errors()->add('curso_id', 'Solo una matrícula activa puede cambiar de grupo.');
                return;
            }
            $g = $this->grupo();
            if (! $g || $g->programa_id !== $m->programa_id || $g->sede_id !== $m->sede_id) {
                $v->errors()->add('curso_id', 'El grupo debe ser del mismo programa y de tu sede.');
            } elseif ($g->id === $m->curso_id) {
                $v->errors()->add('curso_id', 'Ya está en ese grupo.');
            } elseif ($g->estado === 'finalizado') {
                $v->errors()->add('curso_id', "El {$g->nombre} ({$g->codigo}) ya finalizó.");
            } elseif ($g->cuposLibres() <= 0) {
                $v->errors()->add('curso_id', "El {$g->nombre} ({$g->codigo}) ya no tiene cupos libres.");
            }
        }];
    }

    public function grupo(): ?Curso
    {
        return Curso::find($this->input('curso_id'));
    }

    public function messages(): array
    {
        return ['curso_id.required' => 'Selecciona el grupo nuevo.'];
    }
}
