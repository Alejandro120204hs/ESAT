<?php

namespace App\Http\Controllers\Admin\Contabilidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contabilidad\AnularEgresoRequest;
use App\Http\Requests\Admin\Contabilidad\EgresoRequest;
use App\Models\Finanzas\Egreso;
use App\Models\Sistema\Auditoria;
use App\Services\Finanzas\ContabilidadService;
use Illuminate\Http\JsonResponse;

/**
 * Panel Admin › Contabilidad › Egresos: registrar (con comprobante CE-AAAA-NNNNN), editar y anular un
 * egreso de la sede. Todo queda en la auditoría. Responde el egreso en el formato que pinta contabilidad.js.
 */
class EgresoController extends Controller
{
    private const CAMPOS = [
        'fecha' => 'fecha', 'categoria' => 'categoría', 'concepto' => 'concepto', 'docente_id' => 'docente',
        'beneficiario' => 'beneficiario', 'valor' => 'valor', 'metodo_pago' => 'medio de pago',
        'referencia' => 'referencia', 'factura' => 'factura', 'observacion' => 'observación',
    ];

    public function __construct(private ContabilidadService $contabilidad) {}

    public function store(EgresoRequest $request): JsonResponse
    {
        $e = $this->contabilidad->registrar((int) $request->user()->sede_id, $request->datos());

        Auditoria::registrar('crear', $e,
            "Registró el egreso {$e->comprobante} por {$this->pesos($e->valor)} (".Egreso::CATEGORIAS[$e->categoria]."): {$e->concepto}, pagado a {$e->beneficiario} ({$e->metodo_pago}).");

        return response()->json([
            'ok'      => true,
            'egreso'  => $this->contabilidad->egreso($e),
            'mensaje' => "Se registró el egreso {$e->comprobante}.",
        ]);
    }

    public function update(EgresoRequest $request, Egreso $egreso): JsonResponse
    {
        $antes   = $this->pesos($egreso->valor);
        $cambios = $this->contabilidad->editar($egreso, $request->datos());

        if ($cambios) {
            $lista = collect($cambios)->map(fn ($c) => self::CAMPOS[$c] ?? $c)->unique()->join(', ', ' y ');
            $valor = in_array('valor', $cambios, true) ? " (valor: {$antes} → {$this->pesos($egreso->valor)})" : '';
            Auditoria::registrar('editar', $egreso, "Editó el egreso {$egreso->comprobante}: cambió {$lista}{$valor}.");
        }

        return response()->json([
            'ok'      => true,
            'egreso'  => $this->contabilidad->egreso($egreso->load(['registradoPor', 'anuladoPor'])),
            'mensaje' => $cambios ? "Se guardaron los cambios del egreso {$egreso->comprobante}." : 'No había cambios para guardar.',
        ]);
    }

    public function anular(AnularEgresoRequest $request, Egreso $egreso): JsonResponse
    {
        $motivo = trim($request->input('motivo'));
        $e = $this->contabilidad->anular($egreso, $motivo);

        Auditoria::registrar('editar', $e,
            "Anuló el egreso {$e->comprobante} por {$this->pesos($e->valor)} ({$e->concepto}): ".rtrim($motivo, '.').'.');

        return response()->json([
            'ok'      => true,
            'egreso'  => $this->contabilidad->egreso($e),
            'mensaje' => "Se anuló el egreso {$e->comprobante}. Su número no se vuelve a usar.",
        ]);
    }

    private function pesos(float|string $v): string
    {
        return '$ '.number_format((float) $v, 0, ',', '.');
    }
}
