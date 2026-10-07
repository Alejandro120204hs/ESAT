<?php

namespace App\Http\Requests\Admin\Estudiantes;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Ficha › "Vincular / Cambiar acudiente". */
class AcudienteRequest extends FormRequest
{
    use ValidaAcudiente;

    /** Solo estudiantes de la sede del admin. */
    public function authorize(): bool
    {
        $e = $this->route('estudiante');

        return $e->role === RolUsuario::Estudiante && $e->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiarAcudiente();
    }

    public function rules(): array
    {
        return $this->reglasAcudiente('required');
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $this->validarAcudiente($v, $this->route('estudiante')->numero_documento);
        }];
    }

    public function attributes(): array
    {
        return $this->atributosAcudiente();
    }

    public function messages(): array
    {
        return array_merge($this->mensajesAcudiente(), [
            'acudiente.required' => 'Busca al acudiente por su cédula.',
        ]);
    }
}
