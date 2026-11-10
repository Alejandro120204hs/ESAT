<?php

namespace App\Http\Requests\AdminGeneral\Programas;

use App\Http\Requests\Admin\Programas\ProgramaRequest as ProgramaSedeRequest;
use App\Models\Academico\Programa;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    public function after(): array
    {
        return array_merge(parent::after(), [function (Validator $v) {
            $programa = $this->route('programa');
            if (! $programa || $v->errors()->isNotEmpty() || ! $this->tieneNotas($programa)) {
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
            'modulos' => 'malla', 'modulos.*.nombre' => 'nombre del módulo', 'modulos.*.horas' => 'horas del módulo',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'codigo.regex'          => 'El código lleva solo letras, números y guiones (de 4 a 20).',
            'precio_total.multiple_of' => 'Usa un valor redondo (múltiplo de $ 100).',
            'matricula.multiple_of' => 'Usa un valor redondo (múltiplo de $ 100).',
            'modulos.required'      => 'Agrega al menos un módulo.',
            'modulos.min'           => 'Agrega al menos un módulo.',
        ];
    }

    private function tieneNotas(Programa $programa): bool
    {
        return DB::table('calificaciones')->whereIn('programa_modulo_id', $programa->modulos->pluck('id'))->exists();
    }
}
