<?php

namespace App\Http\Requests\Admin\Matriculas;

use App\Models\Academico\Curso;
use App\Models\Finanzas\Matricula;
use App\Services\Academico\CalificacionService;
use App\Services\Finanzas\PagoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Cambiar el estado de la matrícula siguiendo Matricula::TRANSICIONES:
 * aplazar/retirar (con motivo), graduar (grupo terminado, todos los módulos aprobados, plan pagado
 * completo y a paz y salvo) o
 * reintegrar (con cupo). La fecha no puede ser anterior a la matrícula ni al último cambio de estado.
 */
class EstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('matricula')->sede_id === (int) $this->user()->sedeTrabajoId();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo')) ?: null]);
    }

    public function rules(): array
    {
        $m = $this->route('matricula');

        return [
            'estado'   => ['required', Rule::in(Matricula::TRANSICIONES[$m->estado] ?? [])],
            'fecha'    => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$this->fechaMinima()->toDateString()],
            'motivo'   => [Rule::requiredIf(in_array($this->input('estado'), Matricula::CON_MOTIVO, true)), 'nullable', 'string', 'min:5', 'max:255'],
            'curso_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $m = $this->route('matricula');

            if ($this->input('estado') === 'graduado') {
                // Por fecha, aunque el grupo esté finalizado: uno cerrado antes de tiempo no adelanta el grado (HALLAZGOS A8)
                $g = $m->curso;
                if ($g && $g->fin()->gt(today())) {
                    $v->errors()->add('estado', "Solo se gradúa cuando su grupo termina ({$g->fin()->format('d/m/Y')}).");
                    return;
                }
                // Sin malla no hay módulos que aprobar: no se gradúa hasta que el Admin se la cargue
                if ($m->programa->modulos()->doesntExist()) {
                    $v->errors()->add('estado', 'El programa todavía no tiene malla: el Admin debe cargarle los módulos antes de graduar a alguien.');
                    return;
                }
                // Todos los módulos de la malla aprobados (con nota o en habilitación)
                $pendientes = app(CalificacionService::class)->pendientesParaGraduar($m);
                if ($pendientes) {
                    $n = count($pendientes);
                    $v->errors()->add('estado', 'Para graduarse debe tener todos los módulos aprobados: '
                        .($n === 1 ? 'le falta ' : "le faltan {$n}: ").implode(', ', $pendientes).'.');
                    return;
                }
                $m->load('pagos', 'programa');
                $debe = app(PagoService::class)->resumen($m)['por_pagar'];
                if ($debe > 0) {
                    $v->errors()->add('estado', 'Para graduarse debe estar a paz y salvo: todavía debe $ '.number_format($debe, 0, ',', '.').'.');
                    return;
                }
                // Sin plan no hay cuotas: "no debe nada" no significa que haya pagado el programa
                if (! $m->plan_pago) {
                    $v->errors()->add('estado', 'No ha elegido su plan de pago, así que no ha pagado el programa. No se puede graduar.');
                    return;
                }
                $total   = $m->totalCuotas();
                $pagadas = $m->pagos->where('tipo', $m->plan_pago)->where('estado', 'pagado')->count();
                if ($pagadas < $total) {
                    $v->errors()->add('estado', "Solo ha pagado {$pagadas} de {$total} cuotas del programa. No se puede graduar.");
                }
            }

            // Anular es solo para una matrícula hecha por error: si ya recibió pagos, primero se anulan esos pagos
            if ($this->input('estado') === 'anulado') {
                $recibos = $m->recaudos()->whereNull('anulado_en')->pluck('recibo');
                if ($recibos->isNotEmpty()) {
                    $v->errors()->add('estado', 'Tiene pagos registrados ('.$recibos->join(', ').'). Anúlalos primero si fueron un error; si el estudiante se va, usa Retirar.');
                } elseif (($dinero = app(PagoService::class)->dineroEn($m)) > 0) {
                    // Plata que llegó de otra matrícula (cambio de programa): anulada, desaparecería de todas las vistas
                    $v->errors()->add('estado', 'Tiene $ '.number_format($dinero, 0, ',', '.').' que llegaron de otra matrícula. Si el estudiante se va, usa Retirar: así ese dinero se puede devolver o trasladar.');
                }
            }

            if ($this->input('estado') === 'activo') {
                $g = $this->grupo() ?? $m->curso;
                if (! $g || $g->programa_id !== $m->programa_id || $g->sede_id !== $m->sede_id) {
                    $v->errors()->add('curso_id', 'El grupo debe ser del mismo programa y de tu sede.');
                } elseif ($g->estado === 'finalizado') {
                    $v->errors()->add('curso_id', "El {$g->nombre} ya finalizó: elige otro grupo para reintegrarlo.");
                } elseif ($g->cuposLibres() <= 0) {
                    $v->errors()->add('curso_id', "El {$g->nombre} no tiene cupos libres: elige otro grupo para reintegrarlo.");
                }
            }
        }];
    }

    public function grupo(): ?Curso
    {
        return $this->filled('curso_id') ? Curso::find($this->input('curso_id')) : null;
    }

    /** Un cambio de estado no puede quedar antes de la matrícula ni del cambio anterior (ej. reintegro antes del aplazamiento). */
    private function fechaMinima(): Carbon
    {
        $m = $this->route('matricula');

        return $m->estado_fecha && $m->estado_fecha->gt($m->fecha_inicio) ? $m->estado_fecha : $m->fecha_inicio;
    }

    public function attributes(): array
    {
        return ['curso_id' => 'grupo'];
    }

    public function messages(): array
    {
        return [
            'estado.required'        => 'Selecciona el nuevo estado.',
            'estado.in'              => 'Ese cambio de estado no está permitido para esta matrícula.',
            'fecha.before_or_equal'  => 'La fecha no puede ser futura.',
            'fecha.after_or_equal'   => 'La fecha no puede ser anterior al '.$this->fechaMinima()->format('d/m/Y').' (la matrícula o su último cambio de estado).',
            'motivo.required'        => 'Escribe el motivo.',
            'motivo.min'             => 'Describe el motivo con un poco más de detalle.',
        ];
    }
}
