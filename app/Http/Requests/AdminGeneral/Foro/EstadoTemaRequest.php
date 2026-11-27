<?php

namespace App\Http\Requests\AdminGeneral\Foro;

use App\Http\Requests\Admin\Foro\EstadoTemaRequest as SedeEstadoTemaRequest;

/** Las mismas reglas de la secretaría; el Admin modera y participa en el foro de cualquier sede. */
class EstadoTemaRequest extends SedeEstadoTemaRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
