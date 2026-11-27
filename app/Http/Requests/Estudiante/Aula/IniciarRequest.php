<?php

namespace App\Http\Requests\Estudiante\Aula;

/**
 * Comenzar una evaluación con tiempo límite (27-11-2026): el mismo permiso que responderla (matrícula activa en el grupo).
 * Que no haya cerrado ni la haya respondido lo revisa AulaService::iniciar.
 */
class IniciarRequest extends RespuestasRequest
{
    public function rules(): array
    {
        return [];
    }
}
