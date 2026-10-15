<?php

namespace App\Http\Controllers\Admin\Acudientes;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Acudientes\AcudienteRequest;
use App\Http\Requests\Admin\Acudientes\VinculoRequest;
use App\Models\Academico\Curso;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\AcudienteService;
use App\Services\Academico\EstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Panel Admin › Acudientes: registrar a la persona responsable con sus estudiantes a cargo,
 * editarla, vincular/desvincular estudiantes, restablecer su clave y activar/desactivar su cuenta.
 * Programa, grupo, pagos y asistencia de los estudiantes solo se consultan.
 */
class AcudienteController extends Controller
{
    public function __construct(private AcudienteService $acudientes, private EstudianteService $estudiantes)
    {
    }

    public function index(): View
    {
        $sedeId = $this->sedeId();

        $acudientes = User::where('role', RolUsuario::Acudiente)->where('sede_id', $sedeId)
            ->orderBy('apellidos')->get();

        $estudiantes = User::where('role', RolUsuario::Estudiante)->where('sede_id', $sedeId)
            ->with(['acudientes', 'asistencias', 'cursosComoEstudiante', 'matriculas' => fn ($q) => $q->with(['programa', 'pagos', 'recaudos'])])
            ->orderBy('nombres')->get();

        $grupos = Curso::deSede($sedeId)->with('programa.escuela')->orderBy('codigo')->get();

        return view('admin.acudientes.index', [
            'acudientes'  => $acudientes->map(fn (User $a) => $this->acudienteParaVista($a))->values(),
            'estudiantes' => $estudiantes->map(fn (User $e) => $this->estudianteParaVista($e))->values(),
            'grupos'      => $grupos->map(fn (Curso $c) => [
                'id'       => $c->id,
                'codigo'   => $c->codigo,
                'grupo'    => $c->nombre,
                'programa' => $c->programa->nombre,
                'escuela'  => $c->programa->escuela->nombre,
                'jornada'  => $c->jornada,
                'estado'   => $c->estado,
            ])->values(),
            'opciones'    => [
                'tipos'       => AcudienteRequest::TIPOS_DOCUMENTO,
                'parentescos' => EstudianteService::PARENTESCOS,
            ],
        ]);
    }

    public function store(AcudienteRequest $request): JsonResponse
    {
        $a = $this->acudientes->registrar($request->datosAcudiente(), $request->input('estudiantes'), $this->sedeId());

        $nombres = $a->estudiantesACargo()->pluck('nombres')->map(fn ($n) => strtok($n, ' '))->join(', ', ' y ');
        Auditoria::registrar('crear', $a, "Registró al acudiente {$a->name} (a cargo de {$nombres}).");

        return response()->json([
            'ok'      => true,
            'id'      => $a->id,
            'mensaje' => "{$a->name} quedó como acudiente de {$nombres}. Usuario: {$a->email} · contraseña inicial: {$a->numero_documento}.",
        ]);
    }

    public function update(AcudienteRequest $request, User $acudiente): JsonResponse
    {
        $this->acudientes->actualizar($acudiente, $request->datosAcudiente(), $request->input('estudiantes'));

        Auditoria::registrar('editar', $acudiente, "Editó los datos del acudiente {$acudiente->name}.");

        return response()->json(['ok' => true, 'mensaje' => "Se guardaron los cambios de {$acudiente->name}."]);
    }

    public function vincular(VinculoRequest $request, User $acudiente): JsonResponse
    {
        $estudiante = $request->estudiante();
        $this->acudientes->vincular($acudiente, $estudiante, $request->input('parentesco'));

        Auditoria::registrar('editar', $acudiente, "Dejó a {$acudiente->name} como acudiente de {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => "{$acudiente->name} quedó como acudiente de {$estudiante->name}."]);
    }

    /** Un menor de edad no puede quedar sin acudiente. */
    public function desvincular(User $acudiente, User $estudiante): JsonResponse
    {
        $this->asegurarAcudiente($acudiente);
        abort_unless($acudiente->estudiantesACargo()->whereKey($estudiante->id)->exists(), 404);

        if (AcudienteService::esMenor($estudiante)) {
            return response()->json([
                'ok'      => false,
                'message' => "{$estudiante->name} es menor de edad y quedaría sin acudiente. Para cambiarlo, vincúlale otro acudiente.",
            ], 422);
        }

        $this->acudientes->desvincular($acudiente, $estudiante);

        Auditoria::registrar('editar', $acudiente, "{$acudiente->name} dejó de ser acudiente de {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => "{$acudiente->name} ya no es acudiente de {$estudiante->name}."]);
    }

    /** La clave vuelve a ser el número de documento. */
    public function restablecerClave(User $acudiente): JsonResponse
    {
        $this->asegurarAcudiente($acudiente);
        $acudiente->update(['password' => Hash::make($acudiente->numero_documento)]);

        Auditoria::registrar('editar', $acudiente, "Restableció la contraseña de {$acudiente->name}.");

        return response()->json(['ok' => true, 'mensaje' => "La contraseña de {$acudiente->name} volvió a ser su número de documento."]);
    }

    /** Una cuenta inactiva no puede ingresar al portal; sigue vinculada a sus estudiantes. */
    public function cambiarEstado(User $acudiente): JsonResponse
    {
        $this->asegurarAcudiente($acudiente);
        $acudiente->update(['activo' => ! $acudiente->activo]);

        $accion = $acudiente->activo ? 'activar' : 'desactivar';
        Auditoria::registrar($accion, $acudiente, ($acudiente->activo ? 'Activó' : 'Desactivó')." la cuenta del acudiente {$acudiente->name}.");

        return response()->json([
            'ok'      => true,
            'mensaje' => $acudiente->activo ? "La cuenta de {$acudiente->name} está activa de nuevo." : "La cuenta de {$acudiente->name} quedó desactivada.",
        ]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }

    /** Solo acudientes de la sede del admin. */
    private function asegurarAcudiente(User $u): void
    {
        abort_unless($u->role === RolUsuario::Acudiente && $u->sede_id === $this->sedeId(), 404);
    }

    /** Forma que espera acudientes.js. */
    private function acudienteParaVista(User $a): array
    {
        return [
            'id'             => $a->id,
            'nombres'        => $a->nombres,
            'apellidos'      => $a->apellidos,
            'tipo_doc'       => $a->tipo_documento,
            'documento'      => $a->numero_documento,
            'telefono'       => $a->telefono,
            'email'          => $a->email,
            'ocupacion'      => $a->profesion,
            'direccion'      => $a->direccion,
            'departamento'   => $a->departamento_residencia,
            'ciudad'         => $a->ciudad_residencia,
            'cuenta'         => AcudienteService::estadoCuenta($a),
            'ultimo_ingreso' => $a->ultimo_acceso?->format('Y-m-d'),
            'fecha_registro' => $a->created_at->format('Y-m-d'),
        ];
    }

    /** Lo que se consulta del estudiante desde la ficha del acudiente. */
    private function estudianteParaVista(User $e): array
    {
        $m         = $this->estudiantes->matriculaVigente($e);
        $grupo     = $this->estudiantes->grupoDeMatricula($e, $m);
        $acudiente = $e->acudientes->first();
        $pagos     = $m ? $this->estudiantes->resumenPagos($m) : ['pago' => 'al_dia', 'saldo' => 0];

        return [
            'id'           => $e->id,
            'nombres'      => $e->nombres,
            'apellidos'    => $e->apellidos,
            'tipo_doc'     => $e->tipo_documento,
            'documento'    => $e->numero_documento,
            'fecha_nac'    => $e->fecha_nacimiento?->format('Y-m-d'),
            'grupo_id'     => $grupo?->id,
            'estado'       => $m?->estado ?? 'sin_matricula',
            'acudiente_id' => $acudiente?->id,
            'parentesco'   => $acudiente?->pivot->parentesco,
            'pago'         => $pagos['pago'],
            'saldo'        => $pagos['saldo'],
            'asistencia'   => $this->estudiantes->porcentajeAsistencia($e, $grupo),
        ];
    }
}
