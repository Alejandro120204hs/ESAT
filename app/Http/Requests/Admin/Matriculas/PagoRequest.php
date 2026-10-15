<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Models\Finanzas\Pago;
use App\Services\Finanzas\PagoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar un pago (con su recibo): un monto libre que se aplica en orden, lo más viejo primero.
 * Puede completar varios cobros o dejar uno abonado; no puede pasar de lo que debe.
 */
class PagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('matricula')->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'referencia'  => trim((string) $this->input('referencia')) ?: null,
            'observacion' => trim((string) $this->input('observacion')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'valor'       => ['required', 'integer', 'min:1'],
            'fecha_pago'  => ['required', 'date', 'before_or_equal:today'],
            'metodo_pago' => ['required', Rule::in(Pago::METODOS)],
            'referencia'  => ['nullable', 'required_unless:metodo_pago,Efectivo', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $m = $this->route('matricula');
            if ($m->estado === 'anulado') {
                $v->errors()->add('valor', 'La matrícula está anulada: no recibe pagos.');
                return;
            }
            $debe = (int) round(app(PagoService::class)->cobrosPorPagar($m)->sum(fn (Pago $p) => $p->restante()));
            if ($debe <= 0) {
                $v->errors()->add('valor', 'Esta matrícula no tiene nada pendiente por pagar.');
            } elseif ($this->integer('valor') > $debe) {
                $v->errors()->add('valor', 'El valor supera lo que debe en total ($ '.number_format($debe, 0, ',', '.').').');
            }
        }];
    }

    public function datosPago(): array
    {
        return $this->safe()->only(['fecha_pago', 'metodo_pago', 'referencia', 'observacion']);
    }

    public function attributes(): array
    {
        return ['valor' => 'valor', 'fecha_pago' => 'fecha del pago', 'metodo_pago' => 'medio de pago'];
    }

    public function messages(): array
    {
        return [
            'valor.required'             => 'Escribe cuánto pagó.',
            'valor.min'                  => 'El valor debe ser mayor que cero.',
            'fecha_pago.before_or_equal' => 'La fecha del pago no puede ser futura.',
            'referencia.required_unless' => 'Escribe la referencia o el número de la transacción.',
        ];
    }
}
