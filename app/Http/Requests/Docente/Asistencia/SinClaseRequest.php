<?php

namespace App\Http\Requests\Docente\Asistencia;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Curso;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * "No hubo clase" (27-11-2026): el docente marca un día del horario de su grupo en que no se dictó clase, con un motivo
 * (también una clase que viene, si ya sabe que no habrá), o lo deshace (con-clase). Solo en grupos suyos sin finalizar y
 * en días que no tengan asistencia tomada (si la tomó, sí hubo clase).
 */
class SinClaseRequest extends FormRequest
{
    private ?Curso $curso = null;

    public function authorize(): bool
    {
        $c = $this->route('curso');
        $this->curso = app(PanelDocenteService::class)->curso($this->user(), (int) ($c instanceof Curso ? $c->id : $c));

        return $this->curso !== null;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Ese grupo no es tuyo.');
    }

    public function curso(): Curso
    {
        return $this->curso;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'fecha'  => ['required', 'date_format:Y-m-d'],
            'motivo' => $this->routeIs('docente.asistencia.sin_clase') ? ['required', 'string', 'min:3', 'max:200'] : ['nullable'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $d = Carbon::parse($this->input('fecha'))->startOfDay();
            if ($this->curso->estado === 'finalizado') {
                $v->errors()->add('fecha', 'El grupo ya terminó: su asistencia solo se consulta.');
            } elseif ($d->lt($this->curso->fecha_inicio->copy()->startOfDay()) || $d->gte($this->curso->fin()) || ! $this->curso->sesionEn($d)) {
                $v->errors()->add('fecha', 'Ese día no hay clase en el horario del grupo.');
            } elseif ($this->routeIs('docente.asistencia.sin_clase') && Asistencia::where('curso_id', $this->curso->id)->whereDate('fecha', $d)->exists()) {
                $v->errors()->add('fecha', 'Ese día ya tiene asistencia tomada: sí hubo clase.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe por qué no hubo clase.',
            'motivo.min'      => 'Escribe por qué no hubo clase.',
            'motivo.max'      => 'El motivo puede tener hasta 200 caracteres.',
        ];
    }
}
