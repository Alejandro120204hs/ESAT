<?php

namespace App\Http\Requests\Docente\Aula;

use App\Models\Academico\Curso;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de lo que escribe el docente en el Aula virtual: solo en sus grupos (curso_docente, con horario) y mientras el
 * grupo no esté finalizado (un grupo finalizado es solo consulta, como en Asistencia y Notas).
 */
abstract class AulaRequest extends FormRequest
{
    protected ?Curso $curso = null;

    /** El grupo sobre el que actúa (de la ruta, o el de la evaluación, actividad o entrega). */
    abstract protected function cursoId(): ?int;

    public function authorize(): bool
    {
        $id = $this->cursoId();
        $this->curso = $id ? app(PanelDocenteService::class)->curso($this->user(), $id) : null;

        return $this->curso !== null && $this->curso->estado !== 'finalizado';
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException($this->curso?->estado === 'finalizado'
            ? 'El grupo ya finalizó: su Aula virtual es solo consulta.' : 'Ese grupo no es tuyo.');
    }

    public function curso(): Curso
    {
        return $this->curso;
    }

    /** "2026-12-03 23:59" o "2026-12-03T23:59" (lo que manda el campo de fecha y hora). */
    protected function fechaHora(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? str_replace('T', ' ', trim($v)) : null;
    }

    protected function texto(string $k): ?string
    {
        $v = $this->input($k);

        return is_string($v) ? (trim($v) ?: null) : $v;
    }
}
