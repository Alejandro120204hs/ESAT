<?php

namespace App\Http\Requests\Estudiante\Aula;

use App\Services\Academico\AulaService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Responder una evaluación del Aula virtual: {pregunta_id: opcion_id} de todas las preguntas. Solo quien tiene la
 * matrícula activa en el grupo; que no haya cerrado, que no la haya respondido y que estén todas lo vuelve a revisar
 * AulaService::responder con la evaluación bloqueada.
 */
class RespuestasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ev = $this->route('evaluacion');

        return $ev && app(AulaService::class)->matriculaActivaEn($this->user(), $ev->curso) !== null;
    }

    public function rules(): array
    {
        return [
            'respuestas'   => ['required', 'array'],
            'respuestas.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'respuestas.required'   => 'Responde las preguntas antes de enviar.',
            'respuestas.array'      => 'Responde las preguntas antes de enviar.',
            'respuestas.*.required' => 'Te falta responder una pregunta.',
            'respuestas.*.integer'  => 'Una de las respuestas no es válida. Recarga la página.',
        ];
    }
}
