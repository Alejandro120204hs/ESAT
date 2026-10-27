<?php

namespace App\Http\Requests\Admin\Comunicados;

use App\Models\Comunicacion\Comunicado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Crear o editar un comunicado del admin. accion: borrador (solo pide destinatarios y título) | publicar |
 * programar (fecha y hora futuras). Destino: toda la sede o un rol. Adjunto opcional: PDF, JPG o PNG de
 * máximo 5 MB. Un publicado no cambia de destinatarios ni vuelve a borrador o programado; los de los
 * docentes y los archivados no se editan aquí.
 */
class ComunicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $c = $this->route('comunicado');

        return ! $c || $c->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $texto = fn (string $k) => is_string($this->input($k)) ? (trim($this->input($k)) ?: null) : $this->input($k);

        $this->merge([
            'titulo'        => $texto('titulo'),
            'mensaje'       => $texto('mensaje'),
            'publicar_en'   => $texto('publicar_en'),
            'visible_hasta' => $texto('visible_hasta'),
            'fijado'        => $this->boolean('fijado'),
            'quitar_adjunto' => $this->boolean('quitar_adjunto'),
        ]);
    }

    public function rules(): array
    {
        $borrador = $this->input('accion') === 'borrador';

        return [
            'accion'         => ['required', Rule::in(['borrador', 'publicar', 'programar'])],
            'destino'        => ['required', Rule::in(array_keys(Comunicado::DESTINOS_ADMIN))],
            'titulo'         => ['required', 'string', 'min:5', 'max:120'],
            'categoria'      => [$borrador ? 'nullable' : 'required', Rule::in(array_keys(Comunicado::CATEGORIAS))],
            'mensaje'        => [$borrador ? 'nullable' : 'required', 'string', 'min:10', 'max:'.Comunicado::MAX_MENSAJE],
            'adjunto'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.Comunicado::MAX_ADJUNTO_KB],
            'quitar_adjunto' => ['boolean'],
            'publicar_en'    => ['nullable', 'required_if:accion,programar', 'date_format:Y-m-d H:i', 'after:now'],
            'visible_hasta'  => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'fijado'         => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $c = $this->route('comunicado');
            if ($c) {
                if (! $c->esDelAdmin()) {
                    $v->errors()->add('titulo', 'Los comunicados de los docentes no se editan desde aquí: solo se pueden archivar.');
                    return;
                }
                if ($c->estadoActual() === 'archivado') {
                    $v->errors()->add('titulo', 'Un comunicado archivado no se edita.');
                    return;
                }
                if ($c->estadoActual() === 'publicado') {
                    if ($this->input('accion') !== 'publicar') {
                        $v->errors()->add('titulo', 'Un comunicado publicado no vuelve a borrador ni se programa.');
                    } elseif ($this->input('destino') !== $c->destino) {
                        $v->errors()->add('destino', 'Ya está publicado: los destinatarios no se cambian. Duplícalo para enviarlo a otras personas.');
                    }
                }
            }
            // "Visible hasta" no puede ser antes de que se publique
            $desde = $this->input('accion') === 'programar' ? substr((string) $this->input('publicar_en'), 0, 10) : now()->toDateString();
            if ($this->input('visible_hasta') && $this->input('visible_hasta') < $desde) {
                $v->errors()->add('visible_hasta', '"Visible hasta" no puede ser antes de que se publique.');
            }
        }];
    }

    public function datos(): array
    {
        return $this->safe()->only(['destino', 'titulo', 'categoria', 'mensaje', 'publicar_en', 'visible_hasta', 'fijado']);
    }

    public function attributes(): array
    {
        return [
            'destino'       => 'destinatarios',
            'titulo'        => 'título',
            'categoria'     => 'categoría',
            'mensaje'       => 'mensaje',
            'adjunto'       => 'adjunto',
            'publicar_en'   => 'fecha de publicación',
            'visible_hasta' => 'visible hasta',
        ];
    }

    public function messages(): array
    {
        return [
            'accion.*'                 => 'La acción no es válida. Recarga la página e intenta de nuevo.',
            'destino.required'         => 'Elige a quién le llega el comunicado.',
            'destino.in'               => 'Elige a quién le llega el comunicado.',
            'titulo.required'          => 'Escribe un título de al menos 5 letras.',
            'titulo.min'               => 'Escribe un título de al menos 5 letras.',
            'titulo.max'               => 'El título no puede pasar de 120 caracteres.',
            'categoria.required'       => 'Elige la categoría.',
            'categoria.in'             => 'Elige una categoría válida.',
            'mensaje.required'         => 'Escribe el mensaje del comunicado.',
            'mensaje.min'              => 'Escribe el mensaje del comunicado.',
            'mensaje.max'              => 'El mensaje no puede pasar de 2.000 caracteres.',
            'adjunto.file'             => 'No se pudo subir el adjunto. Intenta de nuevo.',
            'adjunto.uploaded'         => 'No se pudo subir el adjunto: debe pesar máximo 5 MB.',
            'adjunto.mimes'            => 'El adjunto debe ser PDF, JPG o PNG.',
            'adjunto.max'              => 'El adjunto debe pesar máximo 5 MB.',
            'publicar_en.required_if'  => 'Elige la fecha y la hora en que se publica.',
            'publicar_en.date_format'  => 'Elige la fecha y la hora en que se publica.',
            'publicar_en.after'        => 'La fecha y hora de publicación deben ser futuras.',
            'visible_hasta.date_format' => 'La fecha de "visible hasta" no es válida.',
            'visible_hasta.after_or_equal' => '"Visible hasta" no puede ser una fecha pasada.',
        ];
    }
}
