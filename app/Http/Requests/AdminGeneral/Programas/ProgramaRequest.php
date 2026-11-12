<?php

namespace App\Http\Requests\AdminGeneral\Programas;

use App\Http\Requests\Admin\Programas\ProgramaRequest as ProgramaSedeRequest;
use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Institucional\Sede;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Programa del catálogo (Admin): las mismas reglas de la secretaría —no puede invalidar sus grupos vigentes
 * (cupo, jornadas, horas, cruces de docente)— más la malla de módulos. Las horas del programa son la suma de
 * los módulos, y la malla no se cambia cuando ya hay notas puestas en algún módulo.
 */
class ProgramaRequest extends ProgramaSedeRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $modulos = collect((array) $this->input('modulos', []))->map(fn ($m) => [
            'nombre' => preg_replace('/\s+/u', ' ', trim((string) ($m['nombre'] ?? ''))),
            'horas'  => is_numeric($m['horas'] ?? null) ? (int) $m['horas'] : null,
        ])->values();
        $this->merge([
            'nombre'     => preg_replace('/\s+/u', ' ', trim((string) $this->input('nombre'))),
            'resolucion' => trim((string) $this->input('resolucion')),
            'modulos'    => $modulos->all(),
            'horas'      => $modulos->sum('horas'),
        ]);
        // Sedes donde se ofrece (paso 4); si no llegan, al crear no se ofrece en ninguna y al editar no cambian
        if ($this->has('sedes')) {
            $this->merge(['sedes' => array_values(array_unique(array_map('intval', (array) $this->input('sedes'))))]);
        }
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'nombre'           => array_merge(['min:10'], parent::rules()['nombre']),
            'codigo'           => ['required', 'string', 'regex:/^[A-Z0-9-]{4,20}$/', ...array_slice(parent::rules()['codigo'], 2)],
            'resolucion'       => ['required', 'string', 'min:5', 'max:255'],
            'cupo_grupo'       => ['required', 'integer', 'min:5', 'max:60'],
            'duracion_meses'   => ['required', 'integer', 'min:3', 'max:36'],
            'precio_total'     => ['required', 'integer', 'min:100000', 'max:999999999', 'multiple_of:100'],
            'matricula'        => ['required', 'integer', 'min:0', 'multiple_of:100', 'lte:precio_total'],
            'modulos'          => ['required', 'array', 'min:1', 'max:30'],
            'modulos.*.nombre' => ['required', 'string', 'min:3', 'max:100'],
            'modulos.*.horas'  => ['required', 'integer', 'min:8', 'max:2000'],
            'sedes'            => ['sometimes', 'array'],
            'sedes.*'          => ['integer', Rule::exists('sedes', 'id')],
        ]);
    }

    public function after(): array
    {
        return array_merge(parent::after(), [fn (Validator $v) => $this->validarSedes($v), function (Validator $v) {
            $programa = $this->route('programa');
            if (! $programa || $v->errors()->isNotEmpty()) {
                return;
            }
            // Las cuotas, el fin del grupo y el grado de quien está matriculado salen de la duración
            $cambiaDuracion = $this->integer('duracion_meses') !== (int) $programa->duracion_meses
                || $this->input('tipo_periodo') !== $programa->tipo_periodo;
            if ($cambiaDuracion && ($n = self::vigentes($programa))) {
                $v->errors()->add('duracion_meses', "Tiene {$n} ".($n === 1 ? 'estudiante con matrícula vigente' : 'estudiantes con matrícula vigente')
                    .': la duración no se cambia hasta que terminen, porque sus cuotas y su grado dependen de ella.');
            }
            if (! $this->tieneNotas($programa)) {
                return;
            }
            $antes = $programa->modulos->map(fn ($m) => [$m->nombre, (int) $m->horas])->all();
            $ahora = collect($this->input('modulos'))->map(fn ($m) => [$m['nombre'], (int) $m['horas']])->all();
            if ($antes !== $ahora) {
                $v->errors()->add('modulos', 'La malla ya no se puede cambiar: hay notas puestas en sus módulos.');
            }
        }]);
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'modulos' => 'malla', 'modulos.*.nombre' => 'nombre del módulo', 'modulos.*.horas' => 'horas del módulo', 'sedes' => 'sedes',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'codigo.regex'          => 'El código lleva solo letras, números y guiones (de 4 a 20).',
            'sedes.*.exists'        => 'Una de las sedes ya no existe.',
            'precio_total.multiple_of' => 'Usa un valor redondo (múltiplo de $ 100).',
            'matricula.multiple_of' => 'Usa un valor redondo (múltiplo de $ 100).',
            'modulos.required'      => 'Agrega al menos un módulo.',
            'modulos.min'           => 'Agrega al menos un módulo.',
        ];
    }

    /**
     * Las mismas reglas del interruptor de la ficha (ProgramaController::oferta): no se quita de una sede con grupos
     * vigentes, y un programa inactivo no se ofrece en sedes nuevas.
     */
    private function validarSedes(Validator $v): void
    {
        if (! $this->has('sedes') || $v->errors()->hasAny(['sedes', 'sedes.*'])) {
            return;
        }
        $programa = $this->route('programa');
        $nuevas = $this->input('sedes');
        $actuales = $programa ? $programa->sedes()->pluck('sedes.id')->all() : [];

        $quitadas = array_diff($actuales, $nuevas);
        $conGrupos = $quitadas ? Curso::where('programa_id', $programa->id)->whereIn('sede_id', $quitadas)->where('estado', '!=', 'finalizado')
            ->selectRaw('sede_id, count(*) as n')->groupBy('sede_id')->pluck('n', 'sede_id') : collect();
        if ($conGrupos->isNotEmpty()) {
            $sede = Sede::find($conGrupos->keys()->first());
            $n = $conGrupos->first();
            $v->errors()->add('sedes', "No se puede quitar de {$sede->nombre}: tiene {$n} ".($n === 1 ? 'grupo vigente' : 'grupos vigentes').'. Se quita cuando terminen.');

            return;
        }
        $estado = $programa?->estado === 'inactivo' ? 'inactivo' : $this->input('estado');
        if ($estado === 'inactivo' && array_diff($nuevas, $actuales)) {
            $v->errors()->add('sedes', 'El programa está inactivo: actívalo antes de ofrecerlo en más sedes.');
        }
    }

    /** Estudiantes con matrícula activa o aplazada en el programa. */
    public static function vigentes(Programa $programa): int
    {
        return Matricula::where('programa_id', $programa->id)->whereIn('estado', Matricula::VIGENTES)->count();
    }

    private function tieneNotas(Programa $programa): bool
    {
        return DB::table('calificaciones')->whereIn('programa_modulo_id', $programa->modulos->pluck('id'))->exists();
    }
}
