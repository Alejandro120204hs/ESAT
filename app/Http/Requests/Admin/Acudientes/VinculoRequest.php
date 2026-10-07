<?php

namespace App\Http\Requests\Admin\Acudientes;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Academico\EstudianteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Ficha del acudiente › "Vincular estudiante". Si el estudiante ya tenía acudiente, lo reemplaza. */
class VinculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $a = $this->route('acudiente');

        return $a->role === RolUsuario::Acudiente && $a->sede_id === (int) $this->user()->sede_id;
    }

    public function rules(): array
    {
        return [
            'estudiante_id' => ['required', 'integer'],
            'parentesco'    => ['required', Rule::in(EstudianteService::PARENTESCOS)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if (! $this->estudiante()) {
                $v->errors()->add('estudiante_id', 'Ese estudiante no está registrado en tu sede.');
            }
        }];
    }

    public function estudiante(): ?User
    {
        return User::where('role', RolUsuario::Estudiante)->where('sede_id', $this->user()->sede_id)
            ->find($this->input('estudiante_id'));
    }

    public function messages(): array
    {
        return [
            'estudiante_id.required' => 'Selecciona el estudiante.',
            'parentesco.required'    => 'Selecciona el parentesco con el estudiante.',
            'parentesco.in'          => 'Selecciona el parentesco con el estudiante.',
        ];
    }
}
