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

            $sedeId   = (int) $this->user()->sedeTrabajoId();
            $curso    = $this->route('curso');
            // Un grupo finalizado es historia (quién lo dictó, en qué fechas): solo se consulta o se reabre desde su ficha
            if ($curso && $curso->estado === 'finalizado') {
                $v->errors()->add('estado', 'El grupo está finalizado: para cambiarlo, primero reábrelo con «Reabrir grupo» en su ficha.');
                return;
            }
            $programa = Programa::find($this->integer('programa_id'));

            if (! $programa->sedes()->where('sedes.id', $sedeId)->exists()) {
                $v->errors()->add('programa_id', 'Esa carrera no se ofrece en tu sede.');
                return;
            }
            // Un grupo nuevo solo se abre en un programa activo
            if (! $curso && $programa->estado !== 'activo') {
                $v->errors()->add('programa_id', 'Solo se pueden abrir grupos en carreras activas.');
                return;
            }
            if (! in_array($this->input('jornada'), $programa->jornadas ?? [], true)) {
                $v->errors()->add('jornada', 'La carrera solo se ofrece en jornada '.mb_strtolower(implode(', ', $programa->jornadas ?? [])).'.');
            }
            // Sus estudiantes están matriculados en el programa del grupo: con inscritos no se cambia de programa
            if ($curso && $programa->id !== $curso->programa_id && $curso->estudiantes()->exists()) {
                $v->errors()->add('programa_id', 'El grupo ya tiene estudiantes inscritos en su carrera: no se puede pasar a otra carrera.');
            }

            [$inicio, $fin] = $this->fechas($programa);

            // Un grupo activo que ya empezó no vuelve a planificación (decidido con el usuario el 27-11-2026): así no se corre su
            // inicio y se borran las clases que ya dictó aunque el docente no haya tomado asistencia. Si aún no empieza, sí vuelve
            if ($curso && $curso->estado === 'activo' && $this->input('estado') === 'planificacion' && $curso->fecha_inicio->lte(today())) {
                $v->errors()->add('estado', "El grupo ya empezó clases el {$curso->fecha_inicio->format('d/m/Y')}: no vuelve a planificación.");
                return;
            }
            // Un grupo que ya empezó no mueve su inicio: cambiaría sus clases, su asistencia y sus cobros (HALLAZGOS A7). Uno que
            // sigue en planificación sin asistencia ni notas nunca dictó clases: corre su inicio a hoy o después (auditoría 26-11-2026)
            if ($curso && $curso->fecha_inicio->lte(today()) && ! $inicio->equalTo($curso->fecha_inicio->copy()->startOfDay())) {
                $sinClases = $curso->estado === 'planificacion' && ! $curso->conservaHorarioAnterior();
                if (! $sinClases) {
                    $v->errors()->add('fecha_inicio', "El grupo ya empezó el {$curso->fecha_inicio->format('d/m/Y')}: la fecha de inicio no se cambia.");
                    return;
                }
                if ($inicio->lt(today())) {
                    $v->errors()->add('fecha_inicio', 'El grupo no ha empezado clases: corre su fecha de inicio a hoy o a una fecha futura.');
                    return;
                }
            }
            // Un grupo nuevo, o uno que corre su inicio, no empieza en el pasado: nadie se matricula en un grupo que ya empezó, y
            // sus estudiantes quedarían en un grupo que arrancó antes que su matrícula, con el derecho y las cuotas descuadrados
            if ((! $curso || ! $inicio->equalTo($curso->fecha_inicio->copy()->startOfDay())) && $inicio->lt(today())) {
                $v->errors()->add('fecha_inicio', 'La fecha de inicio no puede ser anterior a hoy.');
                return;
            }
            // Finalizar a tiempo (HALLAZGOS A8): la misma regla del botón de estado
            if ($curso && $this->input('estado') === 'finalizado' && $curso->estado !== 'finalizado' && $error = $curso->impedimentoParaFinalizar()) {
                $v->errors()->add('estado', $error);
                return;
            }

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
