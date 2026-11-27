<?php

namespace App\Http\Controllers\Admin\Estudiantes;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Estudiantes\EstudianteRequest;
use App\Models\Academico\ContactoEmergencia;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\EstudianteService;
use App\Services\Sistema\SesionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Panel Admin › Estudiantes: registrar y editar a la persona (datos, contacto de emergencia),
 * restablecer su clave y su lista de documentos. NO matricula: programa, grupo, estado
 * de la matrícula y pagos son de Matrículas; aquí solo se consultan.
 */
class EstudianteController extends Controller
{
    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function __construct(private EstudianteService $estudiantes)
    {
    }

    public function index(): View
    {
        $sedeId = $this->sedeId();

        $grupos = Curso::deSede($sedeId)
            ->with(['programa.escuela', 'sesiones', 'docentes'])
            ->withCount('estudiantesActivos')
            ->orderBy('codigo')
            ->get();

        $estudiantes = User::where('role', RolUsuario::Estudiante)->where('sede_id', $sedeId)
            ->with(['documentos', 'contactoEmergencia', 'asistencias', 'cursosComoEstudiante', 'matriculas' => fn ($q) => $q->with(['programa', 'pagos', 'recaudos', 'curso'])])
            ->latest('id')
            ->get();

        return view('admin.estudiantes.index', [
            'estudiantes' => $estudiantes->map(fn (User $e) => $this->paraVista($e))->values(),
            'grupos'      => $grupos->map(fn (Curso $c) => $this->grupoParaVista($c))->values(),
            'opciones'    => [
                'generos'     => EstudianteRequest::GENEROS,
                'tipos'       => EstudianteRequest::TIPOS_DOCUMENTO,
                'parentescos' => ContactoEmergencia::PARENTESCOS,
                'extranjero'  => EstudianteRequest::DOCUMENTOS_EXTRANJERO,
            ],
        ]);
    }

    public function store(EstudianteRequest $request): JsonResponse
    {
        $e = $this->estudiantes->registrar($request->datosEstudiante(), $request->datosContacto(), $this->sedeId());

        Auditoria::registrar('crear', $e, "Registró al estudiante {$e->name}.");

        $verbo = $e->genero === 'femenino' ? 'registrada' : 'registrado';

        return response()->json([
            'ok'      => true,
            'id'      => $e->id,
            'mensaje' => "{$e->name} quedó {$verbo}. Usuario: {$e->email} · contraseña inicial: {$e->numero_documento}. Para darle carrera y grupo, matricúlalo en Matrículas.",
        ]);
    }

    public function update(EstudianteRequest $request, User $estudiante): JsonResponse
    {
        DB::transaction(function () use ($request, $estudiante) {
            $estudiante->update($request->datosEstudiante());
            $this->estudiantes->guardarContacto($estudiante, $request->datosContacto());
        });

        Auditoria::registrar('editar', $estudiante, "Editó los datos del estudiante {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => "Se guardaron los cambios de {$estudiante->name}."]);
    }

    /** La clave vuelve a ser el número de documento y se cierran sus sesiones abiertas (HALLAZGOS B21). */
    public function restablecerClave(User $estudiante, SesionesService $sesiones): JsonResponse
    {
        $this->asegurarEstudiante($estudiante);
        $sesiones->restablecerClave($estudiante);

        Auditoria::registrar('editar', $estudiante, "Restableció la contraseña de {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => "La contraseña de {$estudiante->name} volvió a ser su número de documento."]);
    }

    /**
     * Activa o desactiva la cuenta (una desactivada pierde la sesión en su siguiente petición: EnsureAccountIsEnabled).
     * Con matrícula vigente no se desactiva: mientras estudia necesita su panel. Matricularlo otra vez la reactiva.
     */
    public function cambiarEstado(User $estudiante): JsonResponse
    {
        $this->asegurarEstudiante($estudiante);
        if ($estudiante->activo && $vigente = $estudiante->matriculas()->whereIn('estado', Matricula::VIGENTES)->first()) {
            return response()->json(['ok' => false, 'message' => "{$estudiante->name} tiene la matrícula {$vigente->codigo} vigente: su cuenta sigue activa mientras estudia. Si se va, primero retíralo en Matrículas."], 422);
        }
        $estudiante->update(['activo' => ! $estudiante->activo]);
        $verbo = $estudiante->activo ? 'Activó' : 'Desactivó';
        Auditoria::registrar($estudiante->activo ? 'activar' : 'desactivar', $estudiante, "{$verbo} la cuenta del estudiante {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => $estudiante->activo
            ? "La cuenta de {$estudiante->name} quedó activa: ya puede ingresar."
            : "La cuenta de {$estudiante->name} quedó inactiva: ya no puede ingresar."]);
    }

    /**
     * Marca o desmarca un documento de la lista de chequeo. Llega cómo debe quedar (`entregado`), no "cambiar": dos
     * envíos a la vez lo dejan igual en vez de devolverlo a como estaba. Si ya estaba así, no se toca ni se audita.
     */
    public function documento(Request $request, User $estudiante, EstudianteDocumento $documento): JsonResponse
    {
        $this->asegurarEstudiante($estudiante);
        abort_unless($documento->estudiante_id === $estudiante->id, 404);
        $entregado = $request->validate(['entregado' => ['required', 'boolean']], ['entregado.*' => 'Recarga la página e intenta de nuevo.'])['entregado'];
        $entregado = filter_var($entregado, FILTER_VALIDATE_BOOLEAN);

        if ($entregado === (bool) $documento->entregado_en) {
            return response()->json(['ok' => true, 'entregado' => $entregado]);
        }
        $documento->update(['entregado_en' => $entregado ? now() : null]);

        Auditoria::registrar('editar', $estudiante, ($entregado ? 'Recibió' : 'Marcó como pendiente')." «{$documento->documento}» de {$estudiante->name}.");

        return response()->json(['ok' => true, 'entregado' => $entregado]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sedeTrabajoId();
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }

    /** Solo estudiantes de la sede del admin. */
    private function asegurarEstudiante(User $u): void
    {
        abort_unless($u->role === RolUsuario::Estudiante && $u->sede_id === $this->sedeId(), 404);
    }

    /** Forma que espera estudiantes.js (un estudiante = una persona). */
    private function paraVista(User $e): array
    {
        $m     = $this->estudiantes->matriculaVigente($e);
        $grupo = $this->estudiantes->grupoDeMatricula($e, $m);

        $pagos = $m ? $this->estudiantes->resumenPagos($m)
            : ['plan_pago' => null, 'pago' => 'al_dia', 'cuotas_total' => null, 'cuotas_pagadas' => 0, 'saldo' => 0, 'ultimo_pago' => null];

        return array_merge([
            'id'            => $e->id,
            'codigo'        => $m?->codigo ?? '',
            'nombres'       => $e->nombres,
            'apellidos'     => $e->apellidos,
            'tipo_doc'      => $e->tipo_documento,
            'documento'     => $e->numero_documento,
            'fecha_nac'     => $e->fecha_nacimiento?->format('Y-m-d'),
            'tipo_periodo'  => $m?->programa->tipo_periodo,
            'depto_nac'     => $e->departamento_nacimiento,
            'ciudad_nac'    => $e->lugar_nacimiento,
            'pais_nac'      => $e->pais_nacimiento,
            'genero'        => EstudianteRequest::GENEROS[$e->genero] ?? 'Otro',
            'genero_valor'  => $e->genero,
            'telefono'      => $e->telefono,
            'email'         => $e->email,
            'direccion'     => $e->direccion,
            'departamento'  => $e->departamento_residencia,
            'ciudad'        => $e->ciudad_residencia,
            // De su matrícula (solo consulta): sin matrícula el estado es 'sin_matricula'
            'grupo_id'      => $grupo?->id,
            'estado'        => $m?->estado ?? 'sin_matricula',
            'cuenta_activa' => (bool) $e->activo,
            'estado_fecha'  => $m?->estado_fecha?->format('Y-m-d'),
            'estado_motivo' => $m?->estado_motivo,
            'fecha_ingreso' => $e->created_at->format('Y-m-d'),
            'contacto'      => $e->contactoEmergencia?->paraVista(),
            'asistencia'    => $this->estudiantes->porcentajeAsistencia($e, $grupo, $m),
            'documentos'    => $e->documentos->map(fn ($d) => ['id' => $d->id, 'nombre' => $d->documento, 'entregado' => (bool) $d->entregado_en])->values(),
            'documentos_pendientes' => $e->documentos->whereNull('entregado_en')->pluck('documento')->values(),
            'trayectoria'   => $this->estudiantes->trayectoria($e),
        ], $pagos);
    }

    private function grupoParaVista(Curso $c): array
    {
        $horario = $c->estado === 'finalizado'
            ? ['Finalizado en '.self::MESES[$c->fecha_fin->month - 1].' de '.$c->fecha_fin->year]
            : ($c->sesiones->isEmpty() ? ['Sin horario asignado'] : $c->horarioTexto());

        return [
            'id'        => $c->id,
            'codigo'    => $c->codigo,
            'grupo'     => $c->nombre,
            'programa'  => $c->programa->nombre,
            'escuela'   => $c->programa->escuela->nombre,
            'docente'   => $c->docente()?->name ?? 'Sin docente asignado',
            'jornada'   => $c->jornada,
            'horario'   => $horario,
            'cupo'      => $c->programa->cupo_grupo,
            'inscritos' => $c->estudiantes_activos_count,
            'estado'    => $c->estado,
        ];
    }
}
