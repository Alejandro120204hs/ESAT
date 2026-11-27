<?php

namespace App\Http\Requests\Docente\Aula;

use App\Models\Academico\Curso;
use App\Models\Academico\Material;
use App\Services\Academico\AulaService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Publicar o editar material de clase en uno de sus grupos sin finalizar: título, descripción y módulo opcionales, y un
 * archivo (PDF, Word, PowerPoint, Excel o imagen, máx. 10 MB), un enlace (http o https) o los dos; `quitar_archivo`
 * quita el que tenía. Que quede al menos uno lo vuelve a revisar AulaService. Llega como FormData.
 */
class MaterialRequest extends AulaRequest
{
    protected function cursoId(): ?int
    {
        $m = $this->route('material');
        if ($m instanceof Material) {
            return $m->curso_id;
        }
        $c = $this->route('curso');

        return $c instanceof Curso ? $c->id : ($c ? (int) $c : null);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'titulo'         => $this->texto('titulo'),
            'descripcion'    => $this->texto('descripcion') ?: null,
            'enlace'         => trim((string) $this->input('enlace')) ?: null,
            'modulo_id'      => $this->input('modulo_id') ?: null,
            'quitar_archivo' => $this->boolean('quitar_archivo'),
        ]);
    }

    public function rules(): array
    {
        $modulos = $this->curso?->programa->modulos->pluck('id')->all() ?? [];

        return [
            'titulo'         => ['required', 'string', 'min:3', 'max:150'],
            'descripcion'    => ['nullable', 'string', 'max:1000'],
            'modulo_id'      => ['nullable', 'integer', Rule::in($modulos)],
            'enlace'         => ['nullable', 'string', 'max:500', 'url:http,https'],
            'archivo'        => ['nullable', 'file', 'mimes:'.implode(',', AulaService::EXTENSIONES_DOCENTE), 'max:'.AulaService::MAX_ARCHIVO_KB],
            'quitar_archivo' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $actual = $this->route('material');
            $tieneArchivo = $this->hasFile('archivo') || ($actual instanceof Material && $actual->archivo_path && ! $this->boolean('quitar_archivo'));
            if (! $tieneArchivo && ! $this->input('enlace')) {
                $v->errors()->add('archivo', 'Sube un archivo o escribe un enlace: el material necesita al menos uno.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Escribe el título del material.',
            'titulo.min'      => 'El título debe tener al menos 3 letras.',
            'titulo.max'      => 'El título puede tener hasta 150 caracteres.',
            'descripcion.max' => 'La descripción puede tener hasta 1.000 caracteres.',
            'modulo_id.in'    => 'Elige un módulo de la malla del grupo.',
            'enlace.url'      => 'El enlace debe ser una dirección completa, que empiece por https://',
            'enlace.max'      => 'El enlace es muy largo (máximo 500 caracteres).',
            'archivo.file'    => 'No se pudo subir el archivo. Intenta de nuevo.',
            'archivo.uploaded' => 'No se pudo subir el archivo: debe pesar máximo 10 MB.',
            'archivo.mimes'   => 'El archivo debe ser PDF, Word, PowerPoint, Excel, JPG o PNG.',
            'archivo.max'     => 'El archivo debe pesar máximo 10 MB.',
        ];
    }
}
