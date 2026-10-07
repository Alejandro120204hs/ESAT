<?php

namespace App\Http\Controllers\Admin\Estudiantes;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Estudiantes\AcudienteRequest;
use App\Http\Requests\Admin\Estudiantes\EstudianteRequest;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\EstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Panel Admin › Estudiantes: registrar y editar a la persona (datos, contacto, acudiente),
 * restablecer su clave y su lista de documentos. NO matricula: programa, grupo, estado
 * de la matrícula y pagos son de Matrículas; aquí solo se consultan.
 */
class EstudianteController extends Controller
{
    private const DIA_CORTO = ['Lunes' => 'Lun', 'Martes' => 'Mar', 'Miércoles' => 'Mié', 'Jueves' => 'Jue', 'Viernes' => 'Vie', 'Sábado' => 'Sáb'];
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
            ->with(['documentos', 'acudientes', 'asistencias', 'cursosComoEstudiante', 'matriculas' => fn ($q) => $q->with(['programa', 'pagos'])])
            ->latest('id')
            ->get();

        $acudientes = User::where('role', RolUsuario::Acudiente)->where('sede_id', $sedeId)
            ->orderBy('nombres')->get()
            ->map(fn (User $a) => [
                'id'        => $a->id,
                'nombres'   => $a->nombres,
                'apellidos' => $a->apellidos,
                'documento' => $a->numero_documento,
                'telefono'  => $a->telefono,
                'email'     => $a->email,
            ]);

        return view('admin.estudiantes.index', [
            'estudiantes' => $estudiantes->map(fn (User $e) => $this->paraVista($e))->values(),
            'grupos'      => $grupos->map(fn (Curso $c) => $this->grupoParaVista($c))->values(),
            'acudientes'  => $acudientes,
            'opciones'    => [
                'generos'     => EstudianteRequest::GENEROS,
                'tipos'       => EstudianteRequest::TIPOS_DOCUMENTO,
                'parentescos' => EstudianteService::PARENTESCOS,
            ],
        ]);
    }

    public function store(EstudianteRequest $request): JsonResponse
    {
        $e = $this->estudiantes->registrar($request->datosEstudiante(), $request->input('acudiente'), $request->input('parentesco'), $this->sedeId());

        Auditoria::registrar('crear', $e, "Registró al estudiante {$e->name}.");

        $verbo = $e->genero === 'femenino' ? 'registrada' : 'registrado';

        return response()->json([
            'ok'      => true,
            'id'      => $e->id,
            'mensaje' => "{$e->name} quedó {$verbo}. Usuario: {$e->email} · contraseña inicial: {$e->numero_documento}. Para darle programa y grupo, matricúlalo en Matrículas.",
        ]);
    }

    public function update(EstudianteRequest $request, User $estudiante): JsonResponse
    {
        $estudiante->update($request->datosEstudiante());

        Auditoria::registrar('editar', $estudiante, "Editó los datos del estudiante {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => "Se guardaron los cambios de {$estudiante->name}."]);
    }

    /** La clave vuelve a ser el número de documento. */
    public function restablecerClave(User $estudiante): JsonResponse
    {
        $this->asegurarEstudiante($estudiante);
        $estudiante->update(['password' => Hash::make($estudiante->numero_documento)]);

        Auditoria::registrar('editar', $estudiante, "Restableció la contraseña de {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => "La contraseña de {$estudiante->name} volvió a ser su número de documento."]);
    }

    public function acudiente(AcudienteRequest $request, User $estudiante): JsonResponse
    {
        $acudiente = $this->estudiantes->vincularAcudiente($estudiante, $request->input('acudiente'), $request->input('parentesco'), $this->sedeId());

        Auditoria::registrar('editar', $estudiante, "Dejó a {$acudiente->name} como acudiente de {$estudiante->name}.");

        return response()->json(['ok' => true, 'mensaje' => "{$acudiente->name} quedó como acudiente de {$estudiante->nombres}."]);
    }

    /** Marca o desmarca un documento de la lista de chequeo. */
    public function documento(User $estudiante, EstudianteDocumento $documento): JsonResponse
    {
        $this->asegurarEstudiante($estudiante);
        abort_unless($documento->estudiante_id === $estudiante->id, 404);

        $documento->update(['entregado_en' => $documento->entregado_en ? null : now()]);
        $entregado = (bool) $documento->entregado_en;

        Auditoria::registrar('editar', $estudiante, ($entregado ? 'Recibió' : 'Marcó como pendiente')." «{$documento->documento}» de {$estudiante->name}.");

        return response()->json(['ok' => true, 'entregado' => $entregado]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
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
        $acudiente = $e->acudientes->first();

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
            'depto_nac'     => $e->departamento_nacimiento,
            'ciudad_nac'    => $e->lugar_nacimiento,
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
            'estado_fecha'  => $m?->estado_fecha?->format('Y-m-d'),
            'estado_motivo' => $m?->estado_motivo,
            'fecha_ingreso' => $e->created_at->format('Y-m-d'),
            'acudiente_id'  => $acudiente?->id,
            'parentesco'    => $acudiente?->pivot->parentesco,
            'asistencia'    => $this->estudiantes->porcentajeAsistencia($e, $grupo),
            'documentos'    => $e->documentos->map(fn ($d) => ['id' => $d->id, 'nombre' => $d->documento, 'entregado' => (bool) $d->entregado_en])->values(),
            'documentos_pendientes' => $e->documentos->whereNull('entregado_en')->pluck('documento')->values(),
        ], $pagos);
    }

    private function grupoParaVista(Curso $c): array
    {
        $horario = $c->estado === 'finalizado'
            ? ['Finalizado en '.self::MESES[$c->fecha_fin->month - 1].' de '.$c->fecha_fin->year]
            : ($c->sesiones->isEmpty() ? ['Sin horario asignado'] : $this->horarioTexto($c));

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

    /** "Lun · Mié · Vie  08:00 – 12:00", una línea por cada rango de horas distinto. */
    private function horarioTexto(Curso $c): array
    {
        return $c->sesiones
            ->groupBy(fn ($s) => $s->inicio().'–'.$s->fin())
            ->map(fn ($ses) => $ses->map(fn ($s) => self::DIA_CORTO[$s->dia])->implode(' · ').'  '.$ses->first()->inicio().' – '.$ses->first()->fin())
            ->values()
            ->all();
    }
}
