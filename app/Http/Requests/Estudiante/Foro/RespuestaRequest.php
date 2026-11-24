<?php

namespace App\Http\Requests\Estudiante\Foro;

use App\Http\Requests\Admin\Foro\RespuestaRequest as RespuestaSecretariaRequest;

/**
 * Responder un tema del foro de su sede: las reglas de la secretaría (no en temas cerrados ni ocultos; un adjunto
 * PDF, JPG o PNG de máximo 5 MB).
 */
class RespuestaRequest extends RespuestaSecretariaRequest
{
    public function authorize(): bool
    {
        return $this->route('tema')->sede_id === (int) $this->user()->sede_id;
    }
}
