<?php

namespace App\Http\Requests\Estudiante\Pagos;

use App\Models\Finanzas\Matricula;
use App\Services\Academico\PanelEstudianteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Elegir el plan de pago desde el panel: mensual o por periodo, una sola vez, de la matrícula activa. PagoService::
 * elegirPlan lo vuelve a revisar con la matrícula bloqueada (la secretaría pudo elegirlo al mismo tiempo).
 */
class PlanRequest extends FormRequest
{
    private ?Matricula $matricula = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['plan' => ['required', 'in:mensual,periodo']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $m = $this->matricula();
            if (! $m || $m->estado !== 'activo') {
                $v->errors()->add('plan', 'Solo se elige el plan con una matrícula activa. Si crees que es un error, habla con la secretaría de tu sede.');
            } elseif ($m->plan_pago) {
                $v->errors()->add('plan', 'Ya elegiste tu plan de pago y no se puede cambiar. Si necesitas ayuda, habla con la secretaría de tu sede.');
            } elseif (! $m->curso_id) {
                $v->errors()->add('plan', 'Tu matrícula todavía no tiene grupo. La secretaría te ayudará a elegir el plan.');
            }
        }];
    }

    public function matricula(): ?Matricula
    {
        return $this->matricula ??= app(PanelEstudianteService::class)->matricula($this->user());
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'Elige uno de los dos planes.',
            'plan.in'       => 'Elige uno de los dos planes.',
        ];
    }
}
