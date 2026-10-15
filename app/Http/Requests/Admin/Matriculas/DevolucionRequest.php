<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Models\Finanzas\Pago;
use App\Services\Finanzas\PagoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Devolver lo pagado por adelantado de una matrícula retirada o aplazada. El valor no se escribe:
 * lo calcula PagoService (lo adelantado menos lo que se usa para cubrir lo vencido).
 */
class DevolucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('matricula')->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'referencia' => trim((string) $this->input('referencia')) ?: null,
            'motivo'     => trim((string) $this->input('motivo')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $m = $this->route('matricula');

        return [
            'fecha'       => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.($m->estado_fecha ?? $m->fecha_inicio)->toDateString()],
            'metodo_pago' => ['required', Rule::in(Pago::METODOS)],
            'referencia'  => ['nullable', 'required_unless:metodo_pago,Efectivo', 'string', 'max:60'],
            'motivo'      => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $m = $this->route('matricula');
            if (! in_array($m->estado, ['retirado', 'aplazado'], true)) {
                $v->errors()->add('motivo', 'Solo se devuelve lo adelantado de una matrícula retirada o aplazada.');
            } elseif (app(PagoService::class)->totalAdelantado($m) <= 0) {
                $v->errors()->add('motivo', 'Esta matrícula no tiene dinero adelantado para devolver.');
            }
        }];
    }

    public function datos(): array
    {
        return $this->safe()->only(['fecha', 'metodo_pago', 'referencia', 'motivo']);
    }

    public function attributes(): array
    {
        return ['metodo_pago' => 'medio de devolución'];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal'      => 'La fecha no puede ser futura.',
            'fecha.after_or_equal'       => 'La fecha no puede ser anterior al retiro o aplazamiento.',
            'referencia.required_unless' => 'Escribe la referencia o el número de la transacción.',
            'motivo.required'            => 'Escribe el motivo de la devolución.',
            'motivo.min'                 => 'Describe el motivo con un poco más de detalle.',
        ];
    }
}
