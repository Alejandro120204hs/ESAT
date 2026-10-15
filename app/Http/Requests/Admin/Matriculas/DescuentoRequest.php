<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Models\Finanzas\Matricula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Beca o convenio: porcentaje de descuento sobre el valor del programa (0 lo quita), con su motivo.
 * Solo para matrículas vigentes (activas o aplazadas).
 */
class DescuentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('matricula')->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'motivo'     => [Rule::requiredIf(fn () => (float) $this->input('porcentaje') > 0), 'nullable', 'string', 'min:5', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (! in_array($this->route('matricula')->estado, Matricula::VIGENTES, true)) {
                $v->errors()->add('porcentaje', 'Solo se da descuento a una matrícula activa o aplazada.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'porcentaje.required'    => 'Escribe el porcentaje de descuento.',
            'porcentaje.max'         => 'El descuento no puede pasar del 100 %.',
            'porcentaje.min'         => 'El descuento no puede ser negativo.',
            'motivo.required'        => 'Escribe el motivo o el convenio del descuento.',
            'motivo.min'             => 'Describe el motivo con un poco más de detalle.',
        ];
    }
}
