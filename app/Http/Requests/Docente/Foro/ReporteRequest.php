<?php

namespace App\Http\Requests\Docente\Foro;

use App\Http\Requests\Estudiante\Foro\ReporteRequest as ReporteEstudianteRequest;

/**
 * Reportar un mensaje del foro de su sede: las mismas reglas del estudiante (una vez por mensaje, con el motivo; ni los
 * propios ni los ocultos), sin pedir matrícula.
 */
class ReporteRequest extends ReporteEstudianteRequest
{
    public function authorize(): bool
    {
        $m = $this->route('mensaje');
        $m?->loadMissing('tema');

        return $m && $m->tema->sede_id === (int) $this->user()->sede_id;
    }
}
