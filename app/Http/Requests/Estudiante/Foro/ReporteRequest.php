<?php

namespace App\Http\Requests\Estudiante\Foro;

use App\Models\Comunicacion\ForoReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Reportar un mensaje del foro de su sede (una vez por mensaje, con el motivo): la secretaría lo revisa y decide si lo
 * oculta. No se reporta un mensaje propio ni uno ya oculto.
 */
class ReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $m = $this->route('mensaje');
        $m?->loadMissing('tema');

        return $m && $m->tema->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => is_string($this->input('motivo')) ? (trim($this->input('motivo')) ?: null) : $this->input('motivo')]);
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $m = $this->route('mensaje');
            if ($m->autor_id === $this->user()->id) {
                $v->errors()->add('motivo', 'No puedes reportar tu propio mensaje.');
            } elseif ($m->oculto() || $m->tema->oculto()) {
                $v->errors()->add('motivo', 'La secretaría ya ocultó este mensaje.');
            } elseif (ForoReporte::where('mensaje_id', $m->id)->where('user_id', $this->user()->id)->exists()) {
                $v->errors()->add('motivo', 'Ya reportaste este mensaje.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Cuéntale a la secretaría por qué lo reportas.',
            'motivo.min'      => 'Cuéntale a la secretaría por qué lo reportas (al menos 5 letras).',
            'motivo.max'      => 'El motivo puede tener hasta 255 caracteres.',
        ];
    }
}
