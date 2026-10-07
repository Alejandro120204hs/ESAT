<?php

namespace App\Http\Requests\Admin\Grupos;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\User;
use App\Services\Academico\HorarioService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'programa_id'  => ['required', 'integer', 'exists:programas,id'],
            'docente_id'   => ['nullable', 'integer'],
            'jornada'      => ['required', Rule::in(Curso::JORNADAS)],
            'fecha_inicio' => ['required', 'date'],
            'estado'       => ['required', Rule::in(array_keys(Curso::ESTADOS))],
        ];
    }

    /** Reglas que cruzan datos con el programa, el docente y los demás grupos. */
    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $sedeId   = (int) $this->user()->sede_id;
            $curso    = $this->route('curso');
            $programa = Programa::find($this->integer('programa_id'));

            if (! $programa->sedes()->where('sedes.id', $sedeId)->exists()) {
                $v->errors()->add('programa_id', 'Ese programa no se ofrece en tu sede.');
                return;
            }
            // Un grupo nuevo solo se abre en un programa activo
            if (! $curso && $programa->estado !== 'activo') {
                $v->errors()->add('programa_id', 'Solo se pueden abrir grupos en programas activos.');
                return;
            }
            if (! in_array($this->input('jornada'), $programa->jornadas ?? [], true)) {
                $v->errors()->add('jornada', 'El programa solo se ofrece en jornada '.mb_strtolower(implode(', ', $programa->jornadas ?? [])).'.');
            }
            // El cupo es el del programa: al cambiar de programa no puede quedar por debajo de los inscritos
            if ($curso && $programa->cupo_grupo < $curso->estudiantes()->count()) {
                $v->errors()->add('programa_id', "El cupo de ese programa ({$programa->cupo_grupo}) es menor que los estudiantes ya inscritos en el grupo.");
            }

            [$inicio, $fin] = $this->fechas($programa);

            // Un grupo activo necesita docente
            if ($this->input('estado') === 'activo' && ! $this->filled('docente_id')) {
                $v->errors()->add('docente_id', 'Asigna un docente para dejar el grupo activo.');
            }

            if ($this->filled('docente_id')) {
                $docente = User::with('docentePerfil')->find($this->integer('docente_id'));
                if (! $docente || $docente->role !== RolUsuario::Docente || $docente->sede_id !== $sedeId) {
                    $v->errors()->add('docente_id', 'Selecciona un docente de tu sede.');
                    return;
                }
                if ($docente->docentePerfil?->estado !== 'activo') {
                    $v->errors()->add('docente_id', 'Solo se pueden asignar docentes en estado activo.');
                    return;
                }
            }

            // Si el grupo ya tiene horario, cambiar docente o fechas no puede romperlo
            if ($curso && $curso->sesiones()->exists()) {
                $horario  = app(HorarioService::class);
                $sesiones = $curso->sesiones->map(fn ($s) => ['dia' => $s->dia, 'hora_inicio' => $s->inicio(), 'hora_fin' => $s->fin()])->all();

                if ($error = $horario->fueraDeJornada($this->input('jornada'), $sesiones)) {
                    $v->errors()->add('jornada', "Su horario actual no cabe en esa jornada. {$error} Ajusta primero el horario en Horarios.");
                }
                if ($error = $horario->excedeTope($programa, $sesiones, $inicio, $fin)) {
                    $v->errors()->add('fecha_inicio', $error);
                }
                if ($this->filled('docente_id') && $error = $horario->cruceDocente($this->integer('docente_id'), $sesiones, $inicio, $fin, $curso->id)) {
                    $v->errors()->add('docente_id', $error);
                }
            }
        }];
    }

    /** Fechas del grupo: la de fin no se escribe, es el inicio más la duración del programa. */
    public function fechas(Programa $programa): array
    {
        $inicio = Carbon::parse($this->input('fecha_inicio'))->startOfDay();

        return [$inicio, Curso::finPara($inicio, $programa)];
    }

    public function attributes(): array
    {
        return [
            'programa_id'  => 'programa',
            'docente_id'   => 'docente',
            'fecha_inicio' => 'fecha de inicio',
        ];
    }
}
