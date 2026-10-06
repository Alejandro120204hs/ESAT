<?php

namespace App\Http\Controllers\Admin\Grupos;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Grupos\GrupoRequest;
use App\Models\Academico\Curso;
use App\Models\Academico\Escuela;
use App\Models\Academico\Programa;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\HorarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GrupoController extends Controller
{
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function __construct(private HorarioService $horario)
    {
    }

    public function index(): View
    {
        $sedeId = $this->sedeId();

        $grupos = Curso::deSede($sedeId)
            ->with(['programa.escuela', 'sesiones', 'docentes', 'estudiantes'])
            ->orderBy('codigo')
            ->get();

        // Para el asistente: escuelas con sus programas ofertados en la sede
        $programas = Programa::deSede($sedeId)->with('escuela')->orderBy('nombre')->get();
        $escuelas  = Escuela::orderBy('nombre')->get()->map(fn ($e) => [
            'id'        => $e->id,
            'nombre'    => $e->nombre,
            'programas' => $programas->where('escuela_id', $e->id)->map(fn ($p) => [
                'id'        => $p->id,
                'nombre'    => $p->nombre,
                'estado'    => $p->estado,
                'modalidad' => $p->modalidad,
                'jornadas'  => $p->jornadas ?? [],
                'cupo'      => $p->cupo_grupo,
                'meses'     => $p->duracion_meses,
                'horas'     => $p->horas,
            ])->values(),
        ])->filter(fn ($e) => $e['programas']->isNotEmpty())->values();

        $docentes = User::where('role', RolUsuario::Docente)->where('sede_id', $sedeId)
            ->whereHas('docentePerfil', fn ($q) => $q->where('estado', 'activo'))
            ->with('docentePerfil.escuela')
            ->orderBy('nombres')->get()
            ->map(fn ($d) => ['id' => $d->id, 'nombre' => $d->name, 'escuela' => $d->docentePerfil->escuela->nombre]);

        return view('admin.cursos.index', [
            'grupos'   => $grupos->map(fn (Curso $c) => $this->paraVista($c))->values(),
            'escuelas' => $escuelas,
            'docentes' => $docentes,
        ]);
    }

    public function store(GrupoRequest $request): JsonResponse
    {
        $programa = Programa::with('escuela')->findOrFail($request->integer('programa_id'));
        [$inicio, $fin] = $request->fechas($programa);

        $curso = DB::transaction(function () use ($request, $programa, $inicio, $fin) {
            $nombre = $request->input('nombre') ?: $this->siguienteNombre($programa->id);
            $curso = Curso::create([
                'programa_id'  => $programa->id,
                'sede_id'      => $this->sedeId(),
                'codigo'       => $request->input('codigo') ?: $this->codigoPara($programa, $nombre),
                'nombre'       => $nombre,
                'jornada'      => $request->input('jornada'),
                'fecha_inicio' => $inicio,
                'fecha_fin'    => $fin,
                'estado'       => $request->input('estado'),
            ]);
            $curso->docentes()->sync($request->filled('docente_id') ? [$request->integer('docente_id')] : []);

            return $curso;
        });

        Auditoria::registrar('crear', $curso, "Creó el {$curso->nombre} ({$curso->codigo}) de {$programa->nombre}.");

        return response()->json(['ok' => true, 'mensaje' => "Se creó el {$curso->nombre} ({$curso->codigo}). Asígnale los días y horas de clase en Horarios."]);
    }

    public function update(GrupoRequest $request, Curso $curso): JsonResponse
    {
        $this->asegurarSede($curso);
        $programa = Programa::findOrFail($request->integer('programa_id'));
        [$inicio, $fin] = $request->fechas($programa);

        DB::transaction(function () use ($request, $curso, $programa, $inicio, $fin) {
            $curso->update([
                'programa_id'  => $programa->id,
                'codigo'       => $request->input('codigo') ?: $curso->codigo,
                'nombre'       => $request->input('nombre') ?: $curso->nombre,
                'jornada'      => $request->input('jornada'),
                'fecha_inicio' => $inicio,
                'fecha_fin'    => $fin,
                'estado'       => $request->input('estado'),
            ]);
            $curso->docentes()->sync($request->filled('docente_id') ? [$request->integer('docente_id')] : []);
        });

        Auditoria::registrar('editar', $curso, "Editó el {$curso->nombre} ({$curso->codigo}).");

        return response()->json(['ok' => true, 'mensaje' => "Se guardaron los cambios del {$curso->nombre} ({$curso->codigo})."]);
    }

    /** En planificación → activo → finalizado; un grupo finalizado se puede reabrir. */
    public function cambiarEstado(Curso $curso): JsonResponse
    {
        $this->asegurarSede($curso);

        $nuevo = $curso->estado === 'activo' ? 'finalizado' : 'activo';
        if ($nuevo === 'activo' && ! $curso->docentes()->exists()) {
            return response()->json(['ok' => false, 'message' => 'Asigna un docente antes de activar el grupo.'], 422);
        }
        $curso->update(['estado' => $nuevo]);

        $accion = $nuevo === 'activo' ? 'activar' : 'desactivar';
        $texto  = $nuevo === 'activo' ? 'Activó' : 'Finalizó';
        Auditoria::registrar($accion, $curso, "{$texto} el {$curso->nombre} ({$curso->codigo}).");

        return response()->json([
            'ok'      => true,
            'estado'  => $nuevo,
            'mensaje' => $nuevo === 'activo' ? "El {$curso->nombre} ({$curso->codigo}) quedó activo." : "El {$curso->nombre} ({$curso->codigo}) quedó finalizado.",
        ]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }

    private function asegurarSede(Curso $curso): void
    {
        abort_unless($curso->sede_id === $this->sedeId(), 404);
    }

    /** Siguiente letra libre del programa en la sede: Grupo A, Grupo B... */
    private function siguienteNombre(int $programaId): string
    {
        $usados = Curso::where('programa_id', $programaId)->where('sede_id', $this->sedeId())->pluck('nombre')->all();
        foreach (range('A', 'Z') as $letra) {
            if (! in_array("Grupo {$letra}", $usados, true)) {
                return "Grupo {$letra}";
            }
        }

        return 'Grupo '.(count($usados) + 1);
    }

    /** CUR-{SIGLA}-{consecutivo del programa}-{letra}, p. ej. CUR-SAL-001-A. */
    private function codigoPara(Programa $programa, string $nombre): string
    {
        $num = preg_match('/-(\d{3})$/', $programa->codigo, $m) ? $m[1] : str_pad((string) $programa->id, 3, '0', STR_PAD_LEFT);
        $letra = mb_strtoupper(trim(str_replace('Grupo', '', $nombre))) ?: 'A';
        $base = "CUR-{$programa->escuela->sigla}-{$num}-{$letra}";

        $codigo = $base;
        for ($n = 2; Curso::where('codigo', $codigo)->exists(); $n++) {
            $codigo = "{$base}{$n}";
        }

        return $codigo;
    }

    private function fecha($f): string
    {
        return $f ? $f->format('d').' '.self::MESES[$f->month - 1].' '.$f->format('Y') : '—';
    }

    /** Forma que espera cursos.js. */
    private function paraVista(Curso $c): array
    {
        $docente  = $c->docente();
        $sesiones = $c->sesiones->map(fn ($s) => ['dia' => $s->dia, 'hora_inicio' => $s->inicio(), 'hora_fin' => $s->fin()])->values();
        $minSemana = $this->horario->minutosSemanales($sesiones);

        return [
            'id'               => $c->id,
            'codigo'           => $c->codigo,
            'grupo'            => $c->nombre,
            'programa_id'      => $c->programa_id,
            'programa'         => $c->programa->nombre,
            'escuela_id'       => $c->programa->escuela_id,
            'escuela'          => $c->programa->escuela->nombre,
            'sigla'            => mb_strtolower($c->programa->escuela->sigla),
            'docente_id'       => $docente?->id,
            'docente'          => $docente?->name ?? 'Sin asignar',
            // Modalidad y cupo vienen del programa (se editan en Programas)
            'modalidad'        => $c->programa->modalidad,
            'jornada'          => $c->jornada,
            'sesiones'         => $sesiones,
            'horas_semana'     => round($minSemana / 60, 1),
            'horas_programa'   => $c->programa->horas,
            'horas_programadas'=> round($this->horario->horasProgramadas($sesiones, $c->fecha_inicio, $c->fecha_fin)),
            'fecha_inicio_iso' => $c->fecha_inicio->format('Y-m-d'),
            'fecha_fin_iso'    => $c->fecha_fin->format('Y-m-d'),
            'fecha_inicio'     => $this->fecha($c->fecha_inicio),
            'fecha_fin'        => $this->fecha($c->fecha_fin),
            'cupo_max'         => $c->programa->cupo_grupo,
            'inscritos'        => $c->estudiantes->count(),
            'estado'           => $c->estado,
            'estudiantes'      => $c->estudiantes->map(fn ($e) => [
                'nombre' => $e->name,
                'cedula' => ctype_digit((string) $e->numero_documento) ? number_format((int) $e->numero_documento, 0, ',', '.') : $e->numero_documento,
            ])->values(),
        ];
    }
}
