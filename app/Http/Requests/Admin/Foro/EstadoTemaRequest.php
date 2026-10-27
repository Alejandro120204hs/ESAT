<?php

namespace App\Http\Requests\Admin\Foro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Fijar / quitar de arriba (fijado) o cerrar / reabrir (cerrado) un tema de la sede. Un tema oculto no se
 * fija ni se cierra: primero se restaura.
 */
class EstadoTemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('tema')->sede_id === (int) $this->user()->sede_id;
    }

    public function rules(): array
    {
        return [
            'fijado'  => ['required_without:cerrado', 'boolean'],
            'cerrado' => ['required_without:fijado', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->route('tema')->oculto()) {
                $v->errors()->add('fijado', 'El tema está oculto: restáuralo primero.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'fijado.required_without'  => 'Indica si el tema se fija o se cierra.',
            'cerrado.required_without' => 'Indica si el tema se fija o se cierra.',
            'boolean'                  => 'El valor no es válido. Recarga la página e intenta de nuevo.',
        ];
    }
}
