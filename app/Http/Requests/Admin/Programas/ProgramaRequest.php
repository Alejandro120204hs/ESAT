<?php

namespace App\Http\Requests\Admin\Programas;

use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Services\Academico\HorarioService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProgramaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => $this->filled('codigo') ? mb_strtoupper(trim($this->input('codigo'))) : null,
        ]);
    }

    public function rules(): array
    {
        $programa = $this->route('programa');

        return [
            'nombre'        => ['required', 'string', 'max:150', Rule::unique('programas')->ignore($programa)],
            'codigo'        => ['nullable', 'string', 'max:20', Rule::unique('programas')->ignore($programa)],
            'resolucion'    => ['nullable', 'string', 'max:255'],
            'nivel'         => ['required', Rule::in(Programa::NIVELES)],
            'escuela_id'    => ['required', 'exists:escuelas,id'],
            'modalidad'     => ['required', Rule::in(Programa::MODALIDADES)],
            'horas'         => ['required', 'integer', 'min:1', 'max:5000'],
            'descripcion'   => ['nullable', 'string', 'max:2000'],
            'perfil_egreso' => ['nullable', 'string', 'max:2000'],
            'jornadas'      => ['required', 'array', 'min:1'],
            'jornadas.*'    => [Rule::in(Programa::JORNADAS)],
            'cupo_grupo'    => ['required', 'integer', 'min:1', 'max:60'],
            'duracion_meses'=> ['required', 'integer', 'min:1', 'max:60'],
            'tipo_periodo'  => ['required', Rule::in(array_keys(Programa::PERIODOS))],
            'estado'        => ['required', Rule::in(Programa::ESTADOS)],
            'precio_total'  => ['required', 'numeric', 'min:0', 'max:999999999'],
            'matricula'     => ['required', 'numeric', 'min:0', 'lte:precio_total'],
        ];
    }

    /**
     * Al editar, el programa no puede dejar inválidos sus grupos vigentes: el cupo,
     * las jornadas, la duración y las horas son las de cada grupo.
     */
    public function after(): array
    {
        return [function (Validator $v) {
            $programa = $this->route('programa');
            if (! $programa || $v->errors()->isNotEmpty()) {
                return;
            }
            $grupos = $programa->cursos()->where('estado', '!=', 'finalizado')
                ->with('sesiones')->withCount('estudiantes')->get();

            $max = $grupos->max('estudiantes_count') ?? 0;
            if ($this->integer('cupo_grupo') < $max) {
                $v->errors()->add('cupo_grupo', "Uno de los grupos de este programa ya tiene {$max} estudiantes inscritos: el cupo no puede ser menor.");
            }

            $jornadas = (array) $this->input('jornadas', []);
            if ($sinJornada = $grupos->first(fn ($g) => ! in_array($g->jornada, $jornadas, true))) {
                $v->errors()->add('jornadas', "El {$sinJornada->nombre} ({$sinJornada->codigo}) es de jornada ".mb_strtolower($sinJornada->jornada).': no puedes quitarla.');
            }

            // Con las nuevas horas y meses, los horarios ya armados deben seguir cabiendo
            $nuevo   = new Programa(['horas' => $this->integer('horas'), 'duracion_meses' => $this->integer('duracion_meses')]);
            $horario = app(HorarioService::class);
            foreach ($grupos->filter(fn ($g) => $g->sesiones->isNotEmpty()) as $g) {
                $sesiones = $g->sesiones->map(fn ($s) => ['dia' => $s->dia, 'hora_inicio' => $s->inicio(), 'hora_fin' => $s->fin()]);
                if ($error = $horario->excedeTope($nuevo, $sesiones, $g->fecha_inicio, Curso::finPara($g->fecha_inicio, $nuevo))) {
                    $v->errors()->add('horas', "Con esos cambios el horario del {$g->nombre} ({$g->codigo}) ya no cabe. {$error}");
                    break;
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'nombre'         => 'nombre del programa',
            'codigo'         => 'código interno',
            'resolucion'     => 'resolución de aprobación',
            'escuela_id'     => 'escuela',
            'horas'          => 'duración en horas',
            'perfil_egreso'  => 'perfil del egresado',
            'cupo_grupo'     => 'cupo máximo por grupo',
            'duracion_meses' => 'duración en meses',
            'tipo_periodo'   => 'tipo de periodo',
            'precio_total'   => 'valor total del programa',
            'matricula'      => 'matrícula',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique'    => 'Ya existe un programa con ese nombre.',
            'codigo.unique'    => 'Ya existe un programa con ese código.',
            'jornadas.required'=> 'Selecciona al menos una jornada.',
            'jornadas.min'     => 'Selecciona al menos una jornada.',
            'matricula.lte'    => 'La matrícula no puede ser mayor que el valor total del programa.',
        ];
    }
}
