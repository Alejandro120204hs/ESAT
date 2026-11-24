<?php

namespace App\Http\Requests\Docente\Aula;

use App\Models\Academico\Actividad;
use App\Models\Academico\Evaluacion;

/**
 * Eliminar una evaluación o una actividad de uno de sus grupos sin finalizar. Que nadie la haya respondido o entregado
 * lo revisa AulaService con la fila bloqueada.
 */
class EliminarRequest extends AulaRequest
{
    protected function cursoId(): ?int
    {
        $x = $this->route('evaluacion') ?? $this->route('actividad');

        return $x instanceof Evaluacion || $x instanceof Actividad ? $x->curso_id : null;
    }

    public function rules(): array
    {
        return [];
    }
}
