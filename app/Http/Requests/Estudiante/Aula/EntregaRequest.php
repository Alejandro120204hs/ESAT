<?php

namespace App\Http\Requests\Estudiante\Aula;

use App\Services\Academico\AulaService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entregar o cambiar el archivo de una actividad: PDF, Word, JPG o PNG de máximo 10 MB. Solo quien tiene la matrícula
 * activa en el grupo; la fecha límite y que no esté calificada lo revisa AulaService::entregar con la fila bloqueada.
 */
class EntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $a = $this->route('actividad');

        return $a && app(AulaService::class)->matriculaActivaEn($this->user(), $a->curso) !== null;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:'.implode(',', AulaService::EXTENSIONES), 'max:'.AulaService::MAX_ARCHIVO_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Elige el archivo que vas a entregar.',
            'archivo.file'     => 'No se pudo subir el archivo. Intenta de nuevo.',
            'archivo.uploaded' => 'No se pudo subir el archivo: debe pesar máximo 10 MB.',
            'archivo.mimes'    => 'El archivo debe ser PDF, Word (DOC o DOCX), JPG o PNG.',
            'archivo.max'      => 'El archivo pesa más de 10 MB. Redúcelo o guárdalo en PDF.',
        ];
    }
}
