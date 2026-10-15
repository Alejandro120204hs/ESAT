<?php

namespace App\Http\Controllers\Admin\Matriculas;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Matriculas\CambioGrupoRequest;
use App\Http\Requests\Admin\Matriculas\CambioProgramaRequest;
use App\Http\Requests\Admin\Matriculas\DescuentoRequest;
use App\Http\Requests\Admin\Matriculas\EstadoRequest;
use App\Http\Requests\Admin\Matriculas\MatriculaRequest;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Devolucion;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\EstudianteService;
use App\Services\Academico\MatriculaService;
use App\Services\Finanzas\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Panel Admin › Matrículas: matricular a un estudiante en un grupo, cambiarlo de grupo,
 * cambiar el estado de su matrícula y consultar su plan de pagos e historial.
 * Registrar/anular pagos está en PagoController. Los datos de la persona son de Estudiantes.
 */
class MatriculaController extends Controller
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function __construct(
        private MatriculaService $matriculas,
        private PagoService $pagos,
        private EstudianteService $estudiantes,
    ) {
    }

    public function index(): View
    {
        $sedeId = $this->sedeId();

        $matriculas = Matricula::where('sede_id', $sedeId)
            ->with([
                'programa.escuela', 'curso',
                'pagos' => fn ($q) => $q->orderByRaw('fecha_vencimiento IS NULL')->orderBy('fecha_vencimiento')->orderBy('id'),
                'recaudos' => fn ($q) => $q->with(['registradoPor', 'anuladoPor', 'cobros'])->latest('id'),
                'devoluciones' => fn ($q) => $q->with(['registradoPor', 'cobros'])->latest('id'),
                'origen', 'siguiente',
                'estudiante' => fn ($q) => $q->with(['acudientes', 'documentos', 'asistencias', 'cursosComoEstudiante']),
            ])
            ->latest('id')->get();

        $historial = Auditoria::with('usuario')->where('entidad_tipo', 'Matricula')
            ->whereIn('entidad_id', $matriculas->pluck('id'))->latest('id')->get()->groupBy('entidad_id');

        $grupos = Curso::deSede($sedeId)->with(['programa.escuela', 'sesiones', 'docentes'])
            ->withCount('estudiantesActivos')->orderBy('codigo')->get();

        // Registrados que no tienen matrícula vigente (nunca matriculados, o retirados/graduados)
        $porMatricular = User::where('role', RolUsuario::Estudiante)->where('sede_id', $sedeId)
            ->whereDoesntHave('matriculas', fn ($q) => $q->whereIn('estado', Matricula::VIGENTES))
            ->with(['acudientes', 'documentos', 'matriculas' => fn ($q) => $q->where('estado', '!=', 'anulado')->with('programa')])
            ->orderBy('nombres')->get();

        $programas = Programa::deSede($sedeId)->where('estado', 'activo')->with('escuela')->orderBy('nombre')->get();

        $vista = $matriculas->map(fn (Matricula $m) => $this->matriculaParaVista($m, $historial->get($m->id, collect())))->values();
        // Paz y salvo: se mira lo que debe la persona en todas sus matrículas, no solo en una
        $deudas  = $vista->groupBy('estudiante_id')->map(fn ($ms) => $ms->sum('por_pagar'));
        $sinPlan = $vista->filter(fn ($m) => in_array($m['estado'], Matricula::VIGENTES, true) && ! $m['plan_pago'])->pluck('estudiante_id')->flip();
        $vista   = $vista->map(fn ($m) => $m + [
            'deuda_estudiante' => (int) $deudas[$m['estudiante_id']],
            'sin_plan_vigente' => $sinPlan->has($m['estudiante_id']),
        ]);

        return view('admin.matriculas.index', [
            'matriculas'    => $vista,
            'porMatricular' => $porMatricular->map(fn (User $e) => $this->estudianteParaVista($e))->values(),
            'grupos'        => $grupos->map(fn (Curso $c) => $this->grupoParaVista($c))->values(),
            'programas'     => $programas->map(fn (Programa $p) => $this->programaParaVista($p))->values(),
            'opciones'      => [
                'estados'         => Matricula::ESTADOS,
                'transiciones'    => Matricula::TRANSICIONES,
                'con_motivo'      => Matricula::CON_MOTIVO,
                'metodos'         => Pago::METODOS,
                'dia_vencimiento' => Pago::DIA_VENCIMIENTO,
                'hoy'             => today()->toDateString(),
                'sede'            => auth()->user()->sede?->nombre,
            ],
        ]);
    }

    public function store(MatriculaRequest $request): JsonResponse
    {
        $estudiante = $request->estudiante();
        $grupo      = $request->grupo();
        $m = $this->matriculas->matricular($estudiante, $grupo, $request->pagoMatricula());

        $pagada = $request->pagoMatricula() ? ' y pagó el derecho de matrícula' : '';
        Auditoria::registrar('crear', $m, "Matriculó a {$estudiante->name} en {$grupo->programa->nombre}, {$grupo->nombre} ({$grupo->codigo}){$pagada}.");

        return response()->json([
            'ok'      => true,
            'id'      => $m->id,
            'mensaje' => "{$estudiante->name} quedó matriculado en {$grupo->nombre} ({$grupo->codigo}) con el código {$m->codigo}.",
        ]);
    }

    public function cambiarGrupo(CambioGrupoRequest $request, Matricula $matricula): JsonResponse
    {
        $anterior = $matricula->curso;
        $nuevo    = $request->grupo();
        $this->matriculas->cambiarGrupo($matricula, $nuevo);

        $nombre = $matricula->estudiante->name;
        Auditoria::registrar('editar', $matricula, "Pasó a {$nombre} del {$anterior->nombre} ({$anterior->codigo}, {$anterior->jornada}) al {$nuevo->nombre} ({$nuevo->codigo}, {$nuevo->jornada}).");

        return response()->json(['ok' => true, 'mensaje' => "{$nombre} quedó en el {$nuevo->nombre} ({$nuevo->codigo})."]);
    }

    public function cambiarEstado(EstadoRequest $request, Matricula $matricula): JsonResponse
    {
        $estado = $request->input('estado');
        $fecha  = Carbon::parse($request->input('fecha'));
        $grupo  = $request->grupo();
        $this->matriculas->cambiarEstado($matricula, $estado, $fecha, $request->input('motivo'), $grupo);

        $nombre = $matricula->estudiante->name;
        $texto  = match ($estado) {
            'aplazado' => "Aplazó la matrícula de {$nombre}",
            'retirado' => "Retiró a {$nombre} del programa",
            'graduado' => "Graduó a {$nombre}",
            'activo'   => "Reintegró a {$nombre} en el {$matricula->fresh()->curso->nombre}",
            'anulado'  => "Anuló la matrícula {$matricula->codigo} de {$nombre} (hecha por error)",
        };
        $motivo = $request->input('motivo') ? ': '.$request->input('motivo') : '';
        $accion = match ($estado) {
            'activo'   => 'activar',
            'graduado' => 'editar',
            'anulado'  => 'eliminar',
            default    => 'desactivar',
        };
        Auditoria::registrar($accion, $matricula, "{$texto} el {$fecha->format('d/m/Y')}{$motivo}.");

        return response()->json(['ok' => true, 'mensaje' => "{$texto}."]);
    }

    /** Retira la matrícula vigente y crea otra en un grupo de otro programa (lo adelantado pasa como abono). */
    public function cambiarPrograma(CambioProgramaRequest $request, Matricula $matricula): JsonResponse
    {
        $grupo = $request->grupo();
        $nueva = $this->matriculas->cambiarPrograma($matricula, $grupo, $request->input('motivo'));
        $nombre = $matricula->estudiante->name;
        $trasladado = (int) round($nueva->pagos()->sum('abonado') + (float) $nueva->saldo_favor);
        $abono = $trasladado > 0 ? ' Se trasladaron $ '.number_format($trasladado, 0, ',', '.').' pagados por adelantado.' : '';

        Auditoria::registrar('desactivar', $matricula,
            "Cambió de programa a {$nombre}: retiró {$matricula->codigo} ({$matricula->programa->nombre}) y lo matriculó en {$grupo->programa->nombre} con {$nueva->codigo}: {$request->input('motivo')}.{$abono}");
        Auditoria::registrar('crear', $nueva,
            "Matriculó a {$nombre} en {$grupo->programa->nombre}, {$grupo->nombre} ({$grupo->codigo}) por cambio de programa desde {$matricula->codigo}.{$abono}");

        return response()->json([
            'ok'      => true,
            'id'      => $nueva->id,
            'mensaje' => "{$nombre} quedó en {$grupo->programa->nombre}, {$grupo->nombre} ({$grupo->codigo}), con el código {$nueva->codigo}.{$abono}",
        ]);
    }

    /** Beca o convenio: porcentaje sobre el valor del programa (0 lo quita). */
    public function descuento(DescuentoRequest $request, Matricula $matricula): JsonResponse
    {
        $pct    = round((float) $request->input('porcentaje'), 2);
        $antes  = (float) $matricula->descuento_pct;
        $cuotas = $this->pagos->aplicarDescuento($matricula, $pct, $request->input('motivo'));

        $nombre = $matricula->estudiante->name;
        $texto  = $pct > 0
            ? "Aplicó un descuento del {$this->porcentaje($pct)} a {$nombre}".($antes > 0 ? " (antes {$this->porcentaje($antes)})" : '').": {$request->input('motivo')}"
            : "Quitó el descuento del {$this->porcentaje($antes)} a {$nombre}";
        Auditoria::registrar('editar', $matricula, "{$texto}.");

        $detalle = $matricula->plan_pago
            ? ($cuotas === 1 ? ' Se recalculó 1 cuota.' : " Se recalcularon {$cuotas} cuotas.")
            : ' Se aplicará a sus cuotas cuando elija el plan de pago.';

        return response()->json(['ok' => true, 'mensaje' => ($pct > 0 ? "Descuento del {$this->porcentaje($pct)} guardado." : 'Se quitó el descuento.').$detalle]);
    }

    /* ── Auxiliares ── */

    /** 20 → "20 %", 12.5 → "12,5 %". */
    private function porcentaje(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',').' %';
    }

    private function concepto(Pago $p, ?int $total): string
    {
        return $p->tipo === 'matricula' ? 'Derecho de matrícula' : "Cuota {$p->numero_cuota} de {$total}";
    }

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }

    /** Forma que espera matriculas.js. */
    private function matriculaParaVista(Matricula $m, Collection $historial): array
    {
        $e     = $m->estudiante;
        $acu   = $e->acudientes->first();
        $total = $m->plan_pago === 'mensual' ? $m->programa->duracion_meses : $m->programa->duracion_periodos;

        return array_merge([
            'id'            => $m->id,
            'codigo'        => $m->codigo,
            'estudiante_id' => $e->id,
            'nombres'       => $e->nombres,
            'apellidos'     => $e->apellidos,
            'tipo_doc'      => $e->tipo_documento,
            'documento'     => $e->numero_documento,
            'fecha_nac'     => $e->fecha_nacimiento?->format('Y-m-d'),
            'telefono'      => $e->telefono,
            'email'         => $e->email,
            'acudiente'     => $acu ? ['nombre' => $acu->name, 'telefono' => $acu->telefono, 'parentesco' => $acu->pivot->parentesco] : null,
            'programa_id'   => $m->programa_id,
            'tipo_periodo'  => $m->programa->tipo_periodo,
            'grupo_id'      => $m->curso_id,
            'estado'        => $m->estado,
            'estado_fecha'  => $m->estado_fecha?->format('Y-m-d'),
            'estado_motivo' => $m->estado_motivo,
            'fecha'         => $m->fecha_inicio->format('Y-m-d'),
            'asistencia'    => $this->estudiantes->porcentajeAsistencia($e, $m->curso),
            'documentos_pendientes' => EstudianteDocumento::pendientesDe($e),
            'descuento_pct'    => (float) $m->descuento_pct,
            'descuento_motivo' => $m->descuento_motivo,
            'cobros'        => $m->pagos->map(fn (Pago $p) => [
                'id'          => $p->id,
                'tipo'        => $p->tipo,
                'concepto'    => $this->concepto($p, $total),
                'periodo'     => $p->tipo === 'matricula' ? 'Pago único' : self::MESES[$p->fecha_vencimiento->month - 1].' '.$p->fecha_vencimiento->year,
                'vence'       => $p->fecha_vencimiento?->format('Y-m-d'),
                'valor'       => (int) round($p->monto_total),
                'valor_programa' => (int) round($p->monto_programa),
                'descuento'   => (int) round($p->descuento),
                'valor_sistema'  => (int) round($p->monto_sistema),
                'abonado'     => (int) round($p->disponible()),
                'reembolsado' => (int) round($p->reembolsado),
                'restante'    => (int) round($p->restante()),
                'estado'      => $p->estadoActual(),
                'fecha_pago'  => $p->fecha_pago?->format('Y-m-d'),
            ])->values(),
            // Pagos recibidos (cada uno con su recibo), del más reciente al más viejo
            'recaudos'      => $m->recaudos->map(fn (Recaudo $r) => [
                'id'          => $r->id,
                'recibo'      => $r->recibo,
                'fecha'       => $r->fecha_pago->format('Y-m-d'),
                'valor'       => (int) round($r->valor),
                'metodo'      => $r->metodo_pago,
                'referencia'  => $r->referencia,
                'observacion' => $r->observacion,
                'registro'    => $r->registradoPor?->name,
                'cubre'       => $r->cobros->map(fn (Pago $p) => [
                    'concepto' => $this->concepto($p, $total),
                    'monto'    => (int) round($p->pivot->monto),
                ])->values(),
                'anulado'     => $r->anulado(),
                'anulado_en'  => $r->anulado_en?->format('Y-m-d'),
                'anulado_por' => $r->anuladoPor?->name,
                'motivo_anulacion' => $r->motivo_anulacion,
                'url'         => route('admin.matriculas.recibo', $r),
            ])->values(),
            // Dinero adelantado de una matrícula retirada o aplazada: cuánto cubre deuda y cuánto se devolvería
            'adelantado'    => in_array($m->estado, ['retirado', 'aplazado'], true)
                ? $this->pagos->repartoAdelantado($m)
                : ['total' => 0, 'cubre' => 0, 'resto' => 0],
            'saldo_favor'   => (int) round($m->saldo_favor),
            'devoluciones'  => $m->devoluciones->map(fn (Devolucion $d) => [
                'id'          => $d->id,
                'comprobante' => $d->comprobante,
                'fecha'       => $d->fecha->format('Y-m-d'),
                'valor'       => (int) round($d->valor),
                'metodo'      => $d->metodo_pago,
                'referencia'  => $d->referencia,
                'motivo'      => $d->motivo,
                'registro'    => $d->registradoPor?->name,
                'url'         => route('admin.matriculas.devolucion', $d),
            ])->values(),
            // Cambio de programa: de cuál viene y cuál la reemplazó
            'origen'        => $m->origen ? ['id' => $m->origen->id, 'codigo' => $m->origen->codigo] : null,
            'siguiente'     => $m->siguiente ? ['id' => $m->siguiente->id, 'codigo' => $m->siguiente->codigo] : null,
            'documentos_url' => [
                'constancia'  => route('admin.matriculas.constancia', $m),
                'paz_y_salvo' => route('admin.matriculas.paz-y-salvo', $m),
            ],
            'historial'     => $historial->map(fn (Auditoria $a) => [
                'fecha'  => $a->created_at->format('Y-m-d H:i'),
                'accion' => $a->accion,
                'texto'  => $a->descripcion,
                'quien'  => $a->usuario?->name ?? 'Sistema',
            ])->values(),
        ], $this->pagos->resumen($m));
    }

    private function estudianteParaVista(User $e): array
    {
        $acu    = $e->acudientes->first();
        $ultima = $e->matriculas->sortByDesc('id')->first();

        return [
            'id'          => $e->id,
            'nombres'     => $e->nombres,
            'apellidos'   => $e->apellidos,
            'tipo_doc'    => $e->tipo_documento,
            'documento'   => $e->numero_documento,
            'fecha_nac'   => $e->fecha_nacimiento?->format('Y-m-d'),
            'acudiente'   => $acu ? ['nombre' => $acu->name, 'parentesco' => $acu->pivot->parentesco] : null,
            'documentos_pendientes' => EstudianteDocumento::pendientesDe($e),
            'anterior'    => $ultima ? ['codigo' => $ultima->codigo, 'estado' => $ultima->estado, 'programa' => $ultima->programa->nombre] : null,
            'registrado'  => $e->created_at->format('Y-m-d'),
        ];
    }

    private function grupoParaVista(Curso $c): array
    {
        return [
            'id'          => $c->id,
            'codigo'      => $c->codigo,
            'grupo'       => $c->nombre,
            'programa_id' => $c->programa_id,
            'programa'    => $c->programa->nombre,
            'escuela'     => $c->programa->escuela->nombre,
            'jornada'     => $c->jornada,
            'horario'     => $c->sesiones->isEmpty() ? ['Sin horario asignado'] : $c->horarioTexto(),
            'docente'     => $c->docente()?->name ?? 'Sin docente asignado',
            'inicio'      => $c->fecha_inicio->format('Y-m-d'),
            'fin'         => $c->fecha_fin->format('Y-m-d'),
            'estado'      => $c->estado,
            'cupo'        => $c->programa->cupo_grupo,
            'inscritos'   => $c->estudiantes_activos_count,
        ];
    }

    private function programaParaVista(Programa $p): array
    {
        return [
            'id'          => $p->id,
            'nombre'      => $p->nombre,
            'escuela'     => $p->escuela->nombre,
            'matricula'   => (int) round($p->matricula),
            'precio'      => (int) round($p->precio_total),
            'meses'       => $p->duracion_meses,
            'periodos'    => $p->duracion_periodos,
            'tipo_periodo'=> $p->tipo_periodo,
            'cuota_mensual' => (int) round($p->cuotaMensual()),
            'cuota_periodo' => (int) round($p->cuotaPorPeriodo()),
        ];
    }
}
