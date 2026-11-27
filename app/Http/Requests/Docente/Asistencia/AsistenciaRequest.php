<?php

namespace App\Http\Requests\Docente\Asistencia;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Curso;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Asistencia de una clase (panel Docente): toda la lista de ese día, cada estudiante con presente, tarde,
 * ausente o excusa (la excusa con motivo). Solo en grupos del docente que no hayan terminado, en un día de
 * clase del horario y nunca en una clase futura. Las mismas reglas que valida asistencia.js antes de enviar.
 */
class AsistenciaRequest extends FormRequest
{
    private ?Curso $curso = null;

    public function authorize(): bool
    {
        $this->curso = app(PanelDocenteService::class)->curso($this->user(), $this->cursoId());

        return $this->curso !== null;
    }

    /** El id del grupo de la ruta (con o sin enlace al modelo). */
    private function cursoId(): int
    {
        $c = $this->route('curso');

        return (int) ($c instanceof Curso ? $c->id : $c);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Ese grupo no es tuyo.');
    }

    protected function prepareForValidation(): void
    {
        $registros = [];
        foreach ((array) $this->input('registros', []) as $id => $x) {
            $motivo = trim((string) ($x['motivo'] ?? ''));
            $registros[(int) $id] = ['estado' => (string) ($x['estado'] ?? ''), 'motivo' => $motivo === '' ? null : $motivo];
        }
        $this->merge(['registros' => $registros]);
    }

    public function rules(): array
    {
        return [
            'fecha'               => ['required', 'date_format:Y-m-d'],
            'registros'           => ['required', 'array'],
            'registros.*.estado'  => ['required', Rule::in(Asistencia::ESTADOS)],
            'registros.*.motivo'  => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Un error a la vez, con el nombre del estudiante (asistencia.js lo usa para señalar su fila). */
    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $panel = app(PanelDocenteService::class);
            $fecha = $this->input('fecha');

            if ($this->curso->estado === 'finalizado') {
                $v->errors()->add('fecha', 'El grupo ya terminó: su asistencia solo se consulta.');
                return;
            }
            if (! $panel->esDiaDeClase($this->curso, $fecha)) {
                $v->errors()->add('fecha', 'Ese día no hay clase en el horario del grupo.');
                return;
            }
            if ($fecha > now()->toDateString()) {
                $v->errors()->add('fecha', 'Esa clase todavía no ha llegado.');
                return;
            }

            $registros = $this->input('registros');
            $lista = $panel->planilla($this->curso, $fecha);
            foreach ($lista as $e) {
                $x = $registros[$e['id']] ?? null;
                if (! $x) {
                    $v->errors()->add('registros', "Falta marcar a {$e['nombres']} {$e['apellidos']}.");
                    return;
                }
                if ($x['estado'] === 'excusa' && mb_strlen((string) $x['motivo']) < 3) {
                    $v->errors()->add('registros', "Escribe el motivo de la excusa de {$e['nombres']}.");
                    return;
                }
            }
            if (array_diff(array_keys($registros), $lista->pluck('id')->all())) {
                $v->errors()->add('registros', 'Uno de los estudiantes no está en la lista de esa clase.');
            }
        }];
    }

    public function curso(): Curso
    {
        return $this->curso;
    }

    public function attributes(): array
    {
        return [
            'registros'          => 'lista de asistencia',
            'registros.*.estado' => 'asistencia',
            'registros.*.motivo' => 'motivo de la excusa',
        ];
    }
}
