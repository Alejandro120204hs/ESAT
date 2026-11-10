<?php

namespace App\Http\Requests\Docente\Notas;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Services\Academico\PanelDocenteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Notas de un módulo (panel Docente): solo las que cambiaron, {estudiante: {n, h}}. Nota de 1.0 a 5.0 con
 * un decimal (acepta coma); sin nota (null) la borra. Habilitación solo si la nota es menor a 3.0 y el módulo
 * ya terminó. No en grupos finalizados, módulos por iniciar, matrículas que no están activas ni módulos que
 * terminaron antes de que el estudiante se matriculara. Las mismas reglas que valida notas.js.
 */
class NotasRequest extends FormRequest
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
        $notas = [];
        foreach ((array) $this->input('notas', []) as $id => $x) {
            $notas[(int) $id] = ['n' => $this->numero($x['n'] ?? null), 'h' => $this->numero($x['h'] ?? null)];
        }
        $this->merge(['notas' => $notas]);
    }

    public function rules(): array
    {
        return [
            'notas' => ['required', 'array', 'min:1'],
        ];
    }

    /** Un error a la vez, con el nombre del estudiante. */
    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $panel = app(PanelDocenteService::class);
            $modulo = $this->route('modulo');

            if ($this->curso->estado === 'finalizado') {
                $v->errors()->add('notas', 'El grupo ya terminó: sus notas solo se consultan.');
                return;
            }
            $m = $modulo->programa_id === $this->curso->programa_id ? $panel->moduloDelGrupo($this->curso, $modulo->id) : null;
            if (! $m) {
                $v->errors()->add('notas', 'Ese módulo no es de este grupo.');
                return;
            }
            if ($m['estado'] === 'por_iniciar') {
                $v->errors()->add('notas', 'Ese módulo todavía no empieza.');
                return;
            }

            $estudiantes = $panel->estudiantes($this->curso)->keyBy('id');
            foreach ($this->input('notas') as $id => $x) {
                $e = $estudiantes->get($id);
                if (! $e) {
                    $v->errors()->add('notas', 'Ese estudiante no es de este grupo.');
                    return;
                }
                $nombre = $e['nombres'];
                [$n, $h] = [$x['n'], $x['h']];
                $error = match (true) {
                    $e['matricula']['estado'] !== 'activo'      => "La matrícula de {$nombre} no está activa: sus notas solo se consultan.",
                    $m['fin'] < $e['matricula']['fecha']        => "{$nombre} no cursó este módulo en el grupo.",
                    $n === false                                 => "La nota de {$nombre} debe ir de 1.0 a 5.0, con un decimal.",
                    $h === false                                 => "La habilitación de {$nombre} debe ir de 1.0 a 5.0, con un decimal.",
                    $h !== null && $m['estado'] !== 'terminado' => 'La habilitación se presenta cuando el módulo termina.',
                    $h !== null && ($n === null || $n >= Calificacion::APRUEBA) => "{$nombre} no perdió el módulo: no lleva habilitación.",
                    default                                      => null,
                };
                if ($error) {
                    $v->errors()->add('notas', $error);
                    return;
                }
            }
        }];
    }

    public function curso(): Curso
    {
        return $this->curso;
    }

    /** Notas ya validadas: {estudiante: {n: float|null, h: float|null}}. */
    public function notas(): array
    {
        return $this->input('notas');
    }

    /** null si viene vacía; false si no es una nota válida (1.0 a 5.0, un decimal); si no, el número. */
    private function numero(mixed $valor): float|false|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $texto = str_replace(',', '.', trim((string) $valor));
        if (! preg_match('/^\d(\.\d)?0*$/', $texto)) {
            return false;
        }
        $n = round((float) $texto, 1);

        return $n >= Calificacion::MINIMA && $n <= Calificacion::MAXIMA ? $n : false;
    }
}
