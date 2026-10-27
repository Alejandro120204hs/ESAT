<?php

namespace App\Http\Requests\Admin\Contabilidad;

use App\Models\Finanzas\Egreso;
use App\Models\Finanzas\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar o editar un egreso de la sede. En nómina se elige un docente de la misma sede (el nombre
 * se toma de su cuenta); en las demás categorías se escribe a quién se le pagó. Con un medio distinto
 * de efectivo la referencia es obligatoria (la misma regla de los pagos). Un egreso anulado no se edita.
 */
class EgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $egreso = $this->route('egreso');

        return ! $egreso || $egreso->sede_id === (int) $this->user()->sede_id;
    }

    protected function prepareForValidation(): void
    {
        $texto = fn (string $k) => is_string($this->input($k)) ? (trim($this->input($k)) ?: null) : $this->input($k);

        $this->merge([
            'concepto'     => $texto('concepto'),
            'beneficiario' => $texto('beneficiario'),
            'referencia'   => $texto('referencia'),
            'factura'      => $texto('factura'),
            'observacion'  => $texto('observacion'),
        ]);
    }

    public function rules(): array
    {
        $esNomina = $this->input('categoria') === 'nomina';

        return [
            'categoria'    => ['required', Rule::in(array_keys(Egreso::CATEGORIAS))],
            'fecha'        => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(Egreso::DIAS_ATRAS)->toDateString()],
            'concepto'     => ['required', 'string', 'min:5', 'max:160'],
            'docente_id'   => $esNomina
                ? ['required', 'integer', Rule::exists('users', 'id')->where('role', 'docente')->where('sede_id', $this->user()->sede_id)]
                : ['nullable'],
            'beneficiario' => $esNomina ? ['nullable'] : ['required', 'string', 'min:3', 'max:120'],
            'valor'        => ['required', 'integer', 'min:1', 'max:'.Egreso::VALOR_MAXIMO],
            'metodo_pago'  => ['required', Rule::in(Pago::METODOS)],
            'referencia'   => ['nullable', 'required_unless:metodo_pago,Efectivo', 'string', 'max:60'],
            'factura'      => ['nullable', 'string', 'max:60'],
            'observacion'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $egreso = $this->route('egreso');
            if ($egreso?->anulado()) {
                $v->errors()->add('categoria', 'Un egreso anulado no se puede editar.');
            }
        }];
    }

    public function datos(): array
    {
        return $this->safe()->only(['categoria', 'fecha', 'concepto', 'docente_id', 'beneficiario', 'valor', 'metodo_pago', 'referencia', 'factura', 'observacion']);
    }

    public function attributes(): array
    {
        return [
            'categoria'    => 'categoría',
            'fecha'        => 'fecha',
            'concepto'     => 'concepto',
            'docente_id'   => 'docente',
            'beneficiario' => 'a quién se le pagó',
            'valor'        => 'valor',
            'metodo_pago'  => 'medio de pago',
            'referencia'   => 'referencia',
            'factura'      => 'factura o soporte',
            'observacion'  => 'observación',
        ];
    }

    public function messages(): array
    {
        return [
            'categoria.required'          => 'Elige la categoría del egreso.',
            'categoria.in'                => 'Elige una categoría válida.',
            'fecha.required'              => 'Escribe la fecha del egreso.',
            'fecha.before_or_equal'       => 'La fecha del egreso no puede ser futura.',
            'fecha.after_or_equal'        => 'La fecha no puede ser de hace más de un año.',
            'concepto.required'           => 'Escribe el concepto del egreso.',
            'concepto.min'                => 'Escribe el concepto con un poco más de detalle.',
            'docente_id.required'         => 'Elige el docente al que se le pagó.',
            'docente_id.exists'           => 'Ese docente no pertenece a tu sede.',
            'beneficiario.required'       => 'Escribe a quién se le pagó.',
            'beneficiario.min'            => 'Escribe a quién se le pagó.',
            'valor.required'              => 'Escribe el valor que se pagó.',
            'valor.integer'               => 'Escribe el valor en pesos, sin centavos.',
            'valor.min'                   => 'El valor debe ser mayor que cero.',
            'valor.max'                   => 'El valor no puede pasar de $ 100.000.000.',
            'metodo_pago.required'        => 'Elige el medio de pago.',
            'referencia.required_unless'  => 'Escribe la referencia: si no es en efectivo, es obligatoria para poder verificar el pago.',
        ];
    }
}
