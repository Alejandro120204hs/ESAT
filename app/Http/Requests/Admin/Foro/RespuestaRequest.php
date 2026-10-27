<?php

namespace App\Http\Requests\Admin\Foro;

use App\Models\Comunicacion\ForoMensaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Responder un tema como administración: no se responde un tema cerrado ni uno oculto.
 */
class RespuestaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('tema')->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['texto' => is_string($this->input('texto')) ? (trim($this->input('texto')) ?: null) : $this->input('texto')]);
    }

    public function rules(): array
    {
        return [
            'texto'   => ['required', 'string', 'min:2', 'max:'.ForoMensaje::MAX_TEXTO],
            'adjunto' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.ForoMensaje::MAX_ADJUNTO_KB],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $t = $this->route('tema');
            if ($t->cerrado()) {
                $v->errors()->add('texto', 'El tema está cerrado: nadie puede responder.');
            } elseif ($t->oculto()) {
                $v->errors()->add('texto', 'El tema está oculto: no recibe respuestas.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'texto.required'   => 'Escribe la respuesta.',
            'texto.min'        => 'Escribe la respuesta.',
            'texto.max'        => 'La respuesta no puede pasar de 3.000 caracteres.',
            'adjunto.file'     => 'No se pudo subir el adjunto. Intenta de nuevo.',
            'adjunto.uploaded' => 'No se pudo subir el adjunto: debe pesar máximo 5 MB.',
            'adjunto.mimes'    => 'El adjunto debe ser PDF, JPG o PNG.',
            'adjunto.max'      => 'El adjunto debe pesar máximo 5 MB.',
        ];
    }
}
