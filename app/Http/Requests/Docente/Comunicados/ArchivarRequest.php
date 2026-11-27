<?php

namespace App\Http\Requests\Docente\Comunicados;

use App\Http\Requests\Admin\Comunicados\ArchivarComunicadoRequest;
use App\Models\Comunicacion\Comunicado;
use App\Services\Comunicacion\ComunicadoDocenteService;
use Illuminate\Validation\Validator;

/**
 * Archivar un envío propio (publicado o programado), en todos sus grupos, con el motivo: ya nadie lo ve y queda con
 * quién lo leyó. Las reglas de la secretaría: un borrador no se archiva (se elimina).
 */
class ArchivarRequest extends ArchivarComunicadoRequest
{
    public function authorize(): bool
    {
        $c = $this->route('comunicado');

        return $c->autor_id === $this->user()->id && $c->destino === 'grupo';
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $vivas = app(ComunicadoDocenteService::class)->lote($this->route('comunicado'))
                ->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado');
            if ($vivas->isEmpty()) {
                $v->errors()->add('motivo', 'Ya estaba archivado.');
            } elseif ($vivas->every(fn (Comunicado $f) => $f->estadoActual() === 'borrador')) {
                $v->errors()->add('motivo', 'Un borrador no se archiva: se elimina.');
            }
        }];
    }
}
