<?php

namespace App\Http\Requests\AdminGeneral\Acudientes;

use App\Enums\RolUsuario;
use App\Http\Requests\Admin\Acudientes\VinculoRequest as VinculoSedeRequest;

/** Ficha del acudiente › "Vincular estudiante" desde el panel general: un estudiante de la sede del acudiente. */
class VinculoRequest extends VinculoSedeRequest
{
    public function authorize(): bool
    {
        return $this->route('acudiente')->role === RolUsuario::Acudiente;
    }

    protected function sedeId(): int
    {
        return (int) $this->route('acudiente')->sede_id;
    }
}
