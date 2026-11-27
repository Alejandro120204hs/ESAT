<?php

namespace App\Http\Requests\AdminGeneral\Estudiantes;

use App\Enums\RolUsuario;
use App\Http\Requests\Admin\Estudiantes\EstudianteRequest as EstudianteSedeRequest;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar o editar a un estudiante desde el panel general: las mismas reglas de la secretaría, en la sede que se elija.
 * Al editar también se puede cambiar su sede, si no tiene matrícula vigente ni deudas.
 */
class EstudianteRequest extends EstudianteSedeRequest
{
    public function authorize(): bool
    {
        $e = $this->route('estudiante');

        return ! $e || $e->role === RolUsuario::Estudiante;
    }

    public function rules(): array
    {
        // Departamento y ciudad (listado de Colombia) ya vienen de las reglas de la secretaría
        return parent::rules() + ['sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')]];
    }

    public function after(): array
    {
        return array_merge(parent::after(), [function (Validator $v) {
            $e = $this->route('estudiante');
            if (! $e || $v->errors()->isNotEmpty()) {
                return;
            }
            // Con matrícula vigente la sede no cambia (la matrícula y sus pagos son de esa sede)
            if ((int) $this->input('sede_id') !== $e->sede_id
                && Matricula::where('estudiante_id', $e->id)->whereIn('estado', Matricula::VIGENTES)->exists()) {
                $v->errors()->add('sede_id', 'Tiene una matrícula vigente en su sede: no se cambia de sede desde aquí.');

                return;
            }
            // Con deudas tampoco: la secretaría de la sede nueva no las vería al matricularlo ni al darle el paz y salvo
            if ((int) $this->input('sede_id') !== $e->sede_id && ($debe = $this->deudaDe($e)) > 0) {
                $v->errors()->add('sede_id', 'Todavía debe $ '.number_format($debe, 0, ',', '.').' en su sede: no se cambia de sede hasta que esté a paz y salvo.');
            }
        }]);
    }

    /** Lo que debe en todas sus matrículas (sin las anuladas). */
    private function deudaDe(User $e): int
    {
        $pagos = app(PagoService::class);

        return (int) Matricula::where('estudiante_id', $e->id)->where('estado', '!=', 'anulado')->with(['pagos', 'recaudos', 'programa'])->get()
            ->sum(fn (Matricula $m) => $pagos->resumen($m)['por_pagar']);
    }

    /** La sede elegida en el formulario. */
    protected function sedeId(): int
    {
        return (int) $this->input('sede_id');
    }

    public function attributes(): array
    {
        return parent::attributes() + ['sede_id' => 'sede'];
    }
}
