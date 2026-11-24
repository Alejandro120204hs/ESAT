<?php

namespace App\Http\Requests\Estudiante\Foro;

use App\Http\Requests\Admin\Foro\TemaRequest as TemaSecretariaRequest;

/**
 * Abrir un tema en el foro de su sede: las reglas de la secretaría (categoría fija, título, mensaje y un adjunto PDF,
 * JPG o PNG de máximo 5 MB), sin poder fijarlo arriba.
 */
class TemaRequest extends TemaSecretariaRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['fijado' => false]);
    }
}
