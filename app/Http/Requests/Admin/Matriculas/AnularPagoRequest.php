<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Services\Finanzas\PagoService;
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

        return $m->sede_id === (int) $this->user()->sedeTrabajoId() && $this->route('recaudo')->matricula_id === $m->id;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            // La misma regla que vuelve a revisar PagoService::anularRecaudo con el pago bloqueado
            if ($error = app(PagoService::class)->motivoNoAnulable($this->route('recaudo'))) {
                $v->errors()->add('motivo', $error);
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
