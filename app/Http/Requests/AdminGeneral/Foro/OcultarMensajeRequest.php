<?php

namespace App\Http\Requests\AdminGeneral\Foro;

use App\Http\Requests\Admin\Foro\OcultarMensajeRequest as SedeOcultarMensajeRequest;

/** Las mismas reglas de la secretaría; el Admin modera y participa en el foro de cualquier sede. */
class OcultarMensajeRequest extends SedeOcultarMensajeRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
