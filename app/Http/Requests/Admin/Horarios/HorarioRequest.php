<?php

namespace App\Http\Requests\Admin\Horarios;

use App\Enums\RolUsuario;
use App\Models\Academico\CursoSesion;
use App\Models\User;
use App\Services\Academico\HorarioService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HorarioRequest extends FormRequest
{
    /** Las clases van entre estas horas (de 6 a. m. a medianoche; "24:00" = medianoche). */
    public const HORA_MIN = '06:00';
    public const HORA_MAX = '24:00';

    /** HH:MM de 00:00 a 23:59, más 24:00 para las clases que terminan a medianoche. */
    private const FORMATO_HORA = '/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/';

    /** Solo los grupos de la sede del admin. */
    public function authorize(): bool
    {
        return $this->route('curso')->sede_id === (int) $this->user()->sede_id;
    }

    public function rules(): array
    {
        return [
            'docente_id'              => ['required', 'integer'],
            'sesiones'                => ['required', 'array', 'min:1', 'max:6'],
            'sesiones.*.dia'          => ['required', 'distinct', Rule::in(CursoSesion::DIAS)],
            'sesiones.*.hora_inicio'  => ['required', 'regex:'.self::FORMATO_HORA],
            'sesiones.*.hora_fin'     => ['required', 'regex:'.self::FORMATO_HORA],
        ];
    }

    /** Reglas que cruzan datos con el grupo, el programa, el docente y los demás grupos. */
    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $curso = $this->route('curso');
            if ($curso->estado === 'finalizado') {
                $v->errors()->add('sesiones', 'El grupo está finalizado; reábrelo en Grupos para cambiar su horario.');
                return;
            }

            $min = CursoSesion::aMinutos(self::HORA_MIN);
            $max = CursoSesion::aMinutos(self::HORA_MAX);
            foreach ($this->sesiones() as $i => $s) {
                $ini = CursoSesion::aMinutos($s['hora_inicio']);
                $fin = CursoSesion::aMinutos($s['hora_fin']);
                $dia = mb_strtolower($s['dia']);
                if ($fin <= $ini) {
                    $v->errors()->add("sesiones.{$i}.hora_fin", "El {$dia}: la hora fin debe ser posterior a la hora inicio.");
                    return;
                }
                if ($ini < $min || $fin > $max) {
                    $v->errors()->add("sesiones.{$i}.hora_inicio", "El {$dia}: las clases deben estar entre las ".self::HORA_MIN.' y las '.self::HORA_MAX.'.');
                    return;
                }
            }

            $docente = User::with('docentePerfil')->find($this->integer('docente_id'));
            if (! $docente || $docente->role !== RolUsuario::Docente || $docente->sede_id !== (int) $this->user()->sede_id) {
                $v->errors()->add('docente_id', 'Selecciona un docente de tu sede.');
                return;
            }
            if ($docente->docentePerfil?->estado !== 'activo') {
                $v->errors()->add('docente_id', 'Solo se pueden asignar docentes en estado activo.');
                return;
            }

            $horario = app(HorarioService::class);
            if ($error = $horario->fueraDeJornada($curso->jornada, $this->sesiones())) {
                $v->errors()->add('sesiones', $error);
                return;
            }
            if ($error = $horario->excedeTope($curso->programa, $this->sesiones(), $curso->fecha_inicio, $curso->fecha_fin)) {
                $v->errors()->add('sesiones', $error);
                return;
            }
            if ($error = $horario->cruceDocente($docente->id, $this->sesiones(), $curso->fecha_inicio, $curso->fecha_fin, $curso->id)) {
                $v->errors()->add('docente_id', $error);
            }
        }];
    }

    /** Sesiones ordenadas por día de la semana. */
    public function sesiones(): array
    {
        return collect($this->input('sesiones', []))
            ->map(fn ($s) => ['dia' => $s['dia'], 'hora_inicio' => $s['hora_inicio'], 'hora_fin' => $s['hora_fin']])
            ->sortBy(fn ($s) => array_search($s['dia'], CursoSesion::DIAS, true))
            ->values()
            ->all();
    }

    public function attributes(): array
    {
        return [
            'docente_id'             => 'docente',
            'sesiones'               => 'días de clase',
            'sesiones.*.dia'         => 'día',
            'sesiones.*.hora_inicio' => 'hora inicio',
            'sesiones.*.hora_fin'    => 'hora fin',
        ];
    }

    public function messages(): array
    {
        return [
            'docente_id.required'          => 'Selecciona el docente.',
            'sesiones.required'            => 'Marca al menos un día de clase.',
            'sesiones.min'                 => 'Marca al menos un día de clase.',
            'sesiones.*.dia.distinct'      => 'Cada día solo puede tener una sesión.',
            'sesiones.*.hora_inicio.regex' => 'Revisa las horas: usa el formato HH:MM.',
            'sesiones.*.hora_fin.regex'    => 'Revisa las horas: usa el formato HH:MM.',
        ];
    }
}
