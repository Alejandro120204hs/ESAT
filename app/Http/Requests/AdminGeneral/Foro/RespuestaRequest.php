<?php

namespace App\Http\Requests\AdminGeneral\Foro;

use App\Http\Requests\Admin\Foro\RespuestaRequest as SedeRespuestaRequest;

/** Las mismas reglas de la secretaría; el Admin modera y participa en el foro de cualquier sede. */
class RespuestaRequest extends SedeRespuestaRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
