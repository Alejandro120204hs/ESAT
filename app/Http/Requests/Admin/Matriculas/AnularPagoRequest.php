<?php

namespace App\Http\Requests\Admin\Matriculas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Anular un pago mal registrado (el recaudo entero, con su recibo): lo que abonó vuelve a deberse.
 * El motivo queda en el recaudo y en el historial. Una matrícula graduada ya no anula pagos.
 */
class AnularPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $m = $this->route('matricula');

        return $m->sede_id === (int) $this->user()->sede_id && $this->route('recaudo')->matricula_id === $m->id;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->route('recaudo')->anulado()) {
                $v->errors()->add('motivo', 'Ese pago ya está anulado.');
            } elseif ($this->route('matricula')->estado === 'graduado') {
                // Se graduó a paz y salvo: anular un pago la dejaría graduada con deuda
                $v->errors()->add('motivo', 'La matrícula está graduada: sus pagos ya no se pueden anular.');
            } elseif ($this->route('recaudo')->cobros()->where('reembolsado', '>', 0)->exists()) {
                // Ese dinero ya salió de aquí (se devolvió, cubrió otra deuda o pasó a otra matrícula)
                $v->errors()->add('motivo', 'Parte de este pago ya se devolvió o se trasladó a otra matrícula: no se puede anular.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe por qué se anula el pago.',
            'motivo.min'      => 'Describe el motivo con un poco más de detalle.',
        ];
    }
}
