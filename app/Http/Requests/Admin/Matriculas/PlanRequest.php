<?php

namespace App\Http\Requests\Admin\Matriculas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Elegir el plan de pago por el estudiante (decidido con el usuario el 24-11-2026: lo elige él desde su panel y, si
 * todavía no lo eligió, la secretaría). Solo de una matrícula activa, con grupo y sin plan; PagoService::elegirPlan lo
 * vuelve a revisar con la matrícula bloqueada.
 */
class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('matricula')->sede_id === (int) $this->user()->sedeTrabajoId();
    }

    public function rules(): array
    {
        return ['plan' => ['required', 'in:mensual,periodo']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $m = $this->route('matricula');
            if ($m->plan_pago) {
                $v->errors()->add('plan', 'El plan de pago ya se eligió y no se puede cambiar.');
            } elseif ($m->estado !== 'activo') {
                $v->errors()->add('plan', 'Solo se elige el plan de una matrícula activa.');
            } elseif (! $m->curso_id) {
                $v->errors()->add('plan', 'La matrícula no tiene grupo: no se pueden calcular las cuotas.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'Selecciona el plan de pago.',
            'plan.in'       => 'Selecciona el plan mensual o por periodo.',
        ];
    }
}
