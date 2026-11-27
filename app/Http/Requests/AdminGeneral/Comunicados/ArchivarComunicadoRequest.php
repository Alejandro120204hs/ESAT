<?php

namespace App\Http\Requests\AdminGeneral\Comunicados;

use App\Http\Requests\Admin\Comunicados\ArchivarComunicadoRequest as ArchivarSedeRequest;
use App\Models\Comunicacion\Comunicado;
use App\Services\General\ComunicacionService;
use Illuminate\Validation\Validator;

/** El Admin archiva cualquier comunicado de cualquier sede (los suyos se archivan en todas sus sedes). */
class ArchivarComunicadoRequest extends ArchivarSedeRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Se archiva el envío entero: basta con que siga vivo en alguna sede (una secretaría pudo archivar su copia). */
    public function after(): array
    {
        return [function (Validator $v) {
            $estados = app(ComunicacionService::class)->lote($this->route('comunicado'))->map(fn (Comunicado $f) => $f->estadoActual());
            if ($estados->every(fn ($e) => $e === 'archivado')) {
                $v->errors()->add('motivo', 'Ya estaba archivado.');
            } elseif ($estados->contains('borrador')) {
                $v->errors()->add('motivo', 'Un borrador no se archiva: se elimina.');
            }
        }];
    }
}
