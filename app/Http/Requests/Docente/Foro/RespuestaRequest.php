<?php

namespace App\Http\Requests\Docente\Foro;

use App\Http\Requests\Admin\Foro\RespuestaRequest as RespuestaSecretariaRequest;

/**
 * Responder un tema del foro de su sede: las reglas de la secretaría (solo su sede; no en temas cerrados ni ocultos;
 * un adjunto PDF, JPG o PNG de máximo 5 MB).
 */
class RespuestaRequest extends RespuestaSecretariaRequest
{
}
