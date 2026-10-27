<?php

namespace App\Http\Requests\Admin\Comunicados;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Archivar un comunicado publicado o programado (también uno de un docente): nadie lo vuelve a ver en su
 * panel y queda con quién lo leyó. El motivo queda en el comunicado y en la auditoría.
 */
class ArchivarComunicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('comunicado')->sede_id === (int) $this->user()->sede_id;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $estado = $this->route('comunicado')->estadoActual();
            if ($estado === 'archivado') {
                $v->errors()->add('motivo', 'Ya estaba archivado.');
            } elseif ($estado === 'borrador') {
                $v->errors()->add('motivo', 'Un borrador no se archiva: se elimina.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe por qué se archiva.',
            'motivo.min'      => 'Escribe el motivo con un poco más de detalle.',
        ];
    }
}
