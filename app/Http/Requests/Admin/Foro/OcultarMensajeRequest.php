<?php

namespace App\Http\Requests\Admin\Foro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ocultar un mensaje del foro (el primero oculta el tema entero): nadie más lo ve y sus reportes quedan
 * atendidos. El motivo queda en el mensaje y en la auditoría; se puede restaurar.
 */
class OcultarMensajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('mensaje')->tema->sede_id === (int) $this->user()->sede_id;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->route('mensaje')->oculto()) {
                $v->errors()->add('motivo', 'Ese mensaje ya estaba oculto.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe por qué se oculta.',
            'motivo.min'      => 'Escribe el motivo con un poco más de detalle.',
        ];
    }
}
