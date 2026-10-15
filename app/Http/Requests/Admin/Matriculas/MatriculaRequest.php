<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\User;
use App\Services\Academico\AcudienteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Nueva matrícula: estudiante + grupo (el programa es el del grupo) y, si lo pagó en el momento,
 * el pago del derecho de matrícula. Exige los documentos requeridos entregados.
 */
class MatriculaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'pagar_matricula' => $this->boolean('pagar_matricula'),
            'referencia'      => trim((string) $this->input('referencia')) ?: null,
            'observacion'     => trim((string) $this->input('observacion')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'estudiante_id'   => ['required', 'integer'],
            'curso_id'        => ['required', 'integer'],
            'pagar_matricula' => ['boolean'],
            'fecha_pago'      => ['exclude_unless:pagar_matricula,true', 'required', 'date', 'before_or_equal:today'],
            'metodo_pago'     => ['exclude_unless:pagar_matricula,true', 'required', Rule::in(Pago::METODOS)],
            'referencia'      => ['exclude_unless:pagar_matricula,true', 'nullable', 'required_unless:metodo_pago,Efectivo', 'string', 'max:60'],
            'observacion'     => ['exclude_unless:pagar_matricula,true', 'nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $sedeId = (int) $this->user()->sede_id;

            $e = $this->estudiante();
            if (! $e) {
                $v->errors()->add('estudiante_id', 'Ese estudiante no está registrado en tu sede.');
                return;
            }
            $vigente = $e->matriculas()->whereIn('estado', Matricula::VIGENTES)->first();
            if ($vigente) {
                $v->errors()->add('estudiante_id', "{$e->name} ya tiene una matrícula vigente ({$vigente->codigo}). Si cambió de programa, primero retírala.");
                return;
            }
            if (AcudienteService::esMenor($e) && ! $e->acudientes()->exists()) {
                $v->errors()->add('estudiante_id', "{$e->name} es menor de edad y no tiene acudiente. Vincúlale uno en Estudiantes antes de matricularlo.");
                return;
            }
            $faltan = EstudianteDocumento::pendientesDe($e);
            if ($faltan) {
                $v->errors()->add('estudiante_id', "A {$e->name} le faltan documentos: ".implode(', ', $faltan).'. Recíbelos en Estudiantes antes de matricularlo.');
                return;
            }

            $g = $this->grupo();
            if (! $g || $g->sede_id !== $sedeId) {
                $v->errors()->add('curso_id', 'Ese grupo no pertenece a tu sede.');
                return;
            }
            $programa = $g->programa;
            if ($programa->estado !== 'activo' || ! $programa->sedes()->whereKey($sedeId)->exists()) {
                $v->errors()->add('curso_id', "El programa {$programa->nombre} no está activo en tu sede.");
                return;
            }
            if ($g->estado === 'finalizado') {
                $v->errors()->add('curso_id', "El {$g->nombre} ({$g->codigo}) ya finalizó.");
            } elseif ($g->cuposLibres() <= 0) {
                $v->errors()->add('curso_id', "El {$g->nombre} ({$g->codigo}) ya no tiene cupos libres.");
            }
        }];
    }

    public function estudiante(): ?User
    {
        return User::where('role', RolUsuario::Estudiante)->where('sede_id', $this->user()->sede_id)->find($this->input('estudiante_id'));
    }

    public function grupo(): ?Curso
    {
        return Curso::with('programa')->find($this->input('curso_id'));
    }

    /** Datos del pago del derecho de matrícula, o null si no lo pagó todavía. */
    public function pagoMatricula(): ?array
    {
        return $this->boolean('pagar_matricula')
            ? $this->safe()->only(['fecha_pago', 'metodo_pago', 'referencia', 'observacion'])
            : null;
    }

    public function attributes(): array
    {
        return ['curso_id' => 'grupo', 'fecha_pago' => 'fecha del pago', 'metodo_pago' => 'medio de pago', 'referencia' => 'referencia'];
    }

    public function messages(): array
    {
        return [
            'estudiante_id.required'      => 'Selecciona el estudiante.',
            'curso_id.required'           => 'Selecciona el grupo.',
            'fecha_pago.before_or_equal'  => 'La fecha del pago no puede ser futura.',
            'referencia.required_unless'  => 'Escribe la referencia o el número de la transacción.',
        ];
    }
}
