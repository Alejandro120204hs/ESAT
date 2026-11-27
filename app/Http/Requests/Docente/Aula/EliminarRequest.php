<?php

namespace App\Http\Requests\Docente\Aula;

use App\Models\Academico\Actividad;
use App\Models\Academico\Evaluacion;
use App\Models\Academico\Material;

/**
 * Eliminar una evaluación, una actividad o un material de uno de sus grupos sin finalizar. Que nadie haya respondido o
 * entregado lo revisa AulaService con la fila bloqueada (el material se elimina siempre).
 */
class EliminarRequest extends AulaRequest
{
    protected function cursoId(): ?int
    {
        $x = $this->route('evaluacion') ?? $this->route('actividad') ?? $this->route('material');

        return $x instanceof Evaluacion || $x instanceof Actividad || $x instanceof Material ? $x->curso_id : null;
    }

    public function rules(): array
    {
        return [];
    }
}
