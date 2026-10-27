<?php

namespace App\Http\Requests\Admin\Foro;

use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoTema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Abrir un tema como administración: categoría fija, título, mensaje y un adjunto opcional (PDF, JPG o PNG
 * de máximo 5 MB). Se puede fijar arriba desde que se crea.
 */
class TemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $texto = fn (string $k) => is_string($this->input($k)) ? (trim($this->input($k)) ?: null) : $this->input($k);
        $this->merge(['titulo' => $texto('titulo'), 'texto' => $texto('texto'), 'fijado' => $this->boolean('fijado')]);
    }

    public function rules(): array
    {
        return [
            'categoria' => ['required', Rule::in(array_keys(ForoTema::CATEGORIAS))],
            'titulo'    => ['required', 'string', 'min:5', 'max:120'],
            'texto'     => ['required', 'string', 'min:2', 'max:'.ForoMensaje::MAX_TEXTO],
            'adjunto'   => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.ForoMensaje::MAX_ADJUNTO_KB],
            'fijado'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'categoria.required' => 'Elige la categoría del tema.',
            'categoria.in'       => 'Elige una categoría válida.',
            'titulo.required'    => 'Escribe un título de al menos 5 letras.',
            'titulo.min'         => 'Escribe un título de al menos 5 letras.',
            'titulo.max'         => 'El título no puede pasar de 120 caracteres.',
            'texto.required'     => 'Escribe el mensaje.',
            'texto.min'          => 'Escribe el mensaje.',
            'texto.max'          => 'El mensaje no puede pasar de 3.000 caracteres.',
            'adjunto.file'       => 'No se pudo subir el adjunto. Intenta de nuevo.',
            'adjunto.uploaded'   => 'No se pudo subir el adjunto: debe pesar máximo 5 MB.',
            'adjunto.mimes'      => 'El adjunto debe ser PDF, JPG o PNG.',
            'adjunto.max'        => 'El adjunto debe pesar máximo 5 MB.',
        ];
    }
}
