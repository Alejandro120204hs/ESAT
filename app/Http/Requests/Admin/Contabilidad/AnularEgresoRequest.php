<?php

namespace App\Http\Requests\Admin\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Anular un egreso mal registrado: deja de contar en el saldo del mes y su comprobante no se
 * vuelve a usar. El motivo queda en el egreso y en la auditoría.
 */
class AnularEgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('egreso')->sede_id === (int) $this->user()->sede_id;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->route('egreso')->anulado()) {
                $v->errors()->add('motivo', 'Ese egreso ya estaba anulado.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe por qué se anula el egreso.',
            'motivo.min'      => 'Escribe el motivo con un poco más de detalle.',
        ];
    }
}
