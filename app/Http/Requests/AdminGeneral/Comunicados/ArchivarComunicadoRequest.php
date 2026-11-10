<?php

namespace App\Http\Requests\AdminGeneral\Comunicados;

use App\Http\Requests\Admin\Comunicados\ArchivarComunicadoRequest as ArchivarSedeRequest;

/** El Admin archiva cualquier comunicado de cualquier sede (los suyos se archivan en todas sus sedes). */
class ArchivarComunicadoRequest extends ArchivarSedeRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
