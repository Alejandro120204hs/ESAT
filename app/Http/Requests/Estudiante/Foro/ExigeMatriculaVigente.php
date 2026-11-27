<?php

namespace App\Http\Requests\Estudiante\Foro;

use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * En el foro de la sede participan (abren temas, responden y reportan) los estudiantes con matrícula vigente: un retirado
 * o graduado conserva su cuenta, pero ya no escribe en el foro. Leer sí puede.
 */
trait ExigeMatriculaVigente
{
    public const SIN_MATRICULA = 'Solo participan en el foro los estudiantes con matrícula vigente.';

    public static function participa(User $estudiante): bool
    {
        return Matricula::where('estudiante_id', $estudiante->id)->whereIn('estado', Matricula::VIGENTES)->exists();
    }

    /** Lo llama authorize() cuando el mensaje o el tema ya es de su sede. */
    protected function exigirMatriculaVigente(): bool
    {
        if (! self::participa($this->user())) {
            throw new AuthorizationException(self::SIN_MATRICULA);
        }

        return true;
    }
}
