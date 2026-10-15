<?php

namespace App\Http\Controllers\Admin\Horarios;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Horarios\HorarioRequest;
use App\Models\Academico\Curso;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\HorarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Panel Admin › Horarios: días y horas de clase de cada grupo (curso_sesiones)
 * y su docente. Las reglas (tope de horas, cruces) viven en HorarioService.
 */
class HorarioController extends Controller
{
    public function __construct(private HorarioService $horario)
    {
    }

    public function index(): View
    {
        $sedeId = $this->sedeId();

        // Los grupos finalizados ya no tienen clases: no se muestran
        $grupos = Curso::deSede($sedeId)
            ->where('estado', '!=', 'finalizado')
            ->with(['programa.escuela', 'sesiones', 'docentes'])
            ->orderBy('codigo')
            ->get();

        $docentes = User::where('role', RolUsuario::Docente)->where('sede_id', $sedeId)
            ->whereHas('docentePerfil', fn ($q) => $q->where('estado', 'activo'))
            ->with('docentePerfil.escuela')
            ->orderBy('nombres')->orderBy('apellidos')->get()
            ->map(fn ($d) => ['id' => $d->id, 'nombre' => $d->name, 'escuela' => $d->docentePerfil->escuela->nombre]);

        return view('admin.horarios.index', [
            'grupos'   => $grupos->map(fn (Curso $c) => $this->paraVista($c))->values(),
            'docentes' => $docentes,
            'horaMin'  => HorarioRequest::HORA_MIN,
            'horaMax'  => HorarioRequest::HORA_MAX,
            'rangosJornada' => Curso::RANGOS_JORNADA,
        ]);
    }

    /** Asigna o reemplaza el horario semanal del grupo y su docente. */
    public function update(HorarioRequest $request, Curso $curso): JsonResponse
    {
        $nuevo = ! $curso->sesiones()->exists();

        DB::transaction(function () use ($request, $curso) {
            $curso->sesiones()->delete();
            $curso->sesiones()->createMany($request->sesiones());
            $curso->docentes()->sync([$request->integer('docente_id')]);
        });

        $texto = $nuevo ? 'Asignó el horario' : 'Editó el horario';
        Auditoria::registrar($nuevo ? 'crear' : 'editar', $curso, "{$texto} del {$curso->nombre} ({$curso->codigo}).");

        return response()->json(['ok' => true, 'mensaje' => "Se guardó el horario del {$curso->nombre} ({$curso->codigo})."]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }

    /** Forma que espera horarios.js. */
    private function paraVista(Curso $c): array
    {
        $docente  = $c->docente();
        $sesiones = $c->sesiones->map(fn ($s) => ['dia' => $s->dia, 'hora_inicio' => $s->inicio(), 'hora_fin' => $s->fin()])->values();

        return [
            'id'               => $c->id,
            'codigo'           => $c->codigo,
            'grupo'            => $c->nombre,
            'programa'         => $c->programa->nombre,
            'escuela'          => $c->programa->escuela->nombre,
            'sigla'            => mb_strtolower($c->programa->escuela->sigla),
            'docente_id'       => $docente?->id,
            'docente'          => $docente?->name ?? 'Sin asignar',
            'jornada'          => $c->jornada,
            'estado'           => $c->estado,
            'sesiones'         => $sesiones,
            // Para el tope: el horario no puede sumar más horas que las del programa
            'horas_programa'   => $c->programa->horas,
            'horas_programadas'=> round($this->horario->horasProgramadas($sesiones, $c->fecha_inicio, $c->fecha_fin)),
            'fecha_inicio_iso' => $c->fecha_inicio->format('Y-m-d'),
            'fecha_fin_iso'    => $c->fecha_fin->format('Y-m-d'),
        ];
    }
}
