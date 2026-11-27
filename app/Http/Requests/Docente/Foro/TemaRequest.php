<?php

namespace App\Http\Requests\Docente\Foro;

use App\Http\Requests\Admin\Foro\TemaRequest as TemaSecretariaRequest;

/**
 * Abrir un tema en el foro de su sede: las reglas de la secretaría (categoría fija, título, mensaje y un adjunto PDF,
 * JPG o PNG de máximo 5 MB), sin poder fijarlo arriba. El docente solo participa: moderar es de la secretaría.
 */
class TemaRequest extends TemaSecretariaRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['fijado' => false]);
    }
}
