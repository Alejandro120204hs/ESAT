<?php

namespace App\Http\Controllers\Admin\Programas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Programas\ProgramaRequest;
use App\Models\Academico\Curso;
use App\Models\Academico\Escuela;
use App\Models\Academico\Programa;
use App\Models\Sistema\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProgramaController extends Controller
{
    public function index(): View
    {
        $sedeId = $this->sedeId();

        $programas = Programa::deSede($sedeId)
            ->with([
                'escuela', 'modulos',
                // Grupos vigentes del programa en esta sede
                'cursos' => fn ($q) => $q->where('sede_id', $sedeId)->where('estado', '!=', 'finalizado')
                    ->withCount('estudiantes')->orderBy('nombre'),
            ])
            ->withCount(['matriculas as estudiantes' => fn ($q) => $q->where('sede_id', $sedeId)])
            ->orderBy('nombre')
            ->get();

        $escuelas = Escuela::withCount(['programas as programas_sede' => fn ($q) => $q->deSede($sedeId)])
            ->orderBy('nombre')
            ->get();

        return view('admin.programas.index', [
            'programas'   => $programas->map(fn (Programa $p) => $this->paraVista($p))->values(),
            'escuelas'    => $escuelas,
            'cuotaSistema'=> Programa::cuotaSistemaMensual(),
            'opciones'    => [
                'niveles'     => Programa::NIVELES,
                'modalidades' => Programa::MODALIDADES,
                'jornadas'    => Programa::JORNADAS,
            ],
        ]);
    }

    public function store(ProgramaRequest $request): JsonResponse
    {
        $programa = DB::transaction(function () use ($request) {
            $datos = $this->datos($request);
            $datos['codigo'] ??= $this->siguienteCodigo((int) $datos['escuela_id']);

            $programa = Programa::create($datos);
            // Lo que crea el admin queda ofertado en su sede
            $programa->sedes()->attach($this->sedeId());

            return $programa;
        });

        Auditoria::registrar('crear', $programa, "Creó el programa {$programa->nombre} ({$programa->codigo}).");

        return response()->json(['ok' => true, 'mensaje' => "Se creó el programa {$programa->nombre}."]);
    }

    public function update(ProgramaRequest $request, Programa $programa): JsonResponse
    {
        $this->asegurarSede($programa);

        $datos = $this->datos($request);
        $datos['codigo'] ??= $programa->codigo;

        DB::transaction(function () use ($programa, $datos) {
            $programa->update($datos);
            // La fecha de fin de cada grupo es su inicio + la duración del programa
            if ($programa->wasChanged('duracion_meses')) {
                $programa->cursos->each(fn (Curso $c) => $c->update(['fecha_fin' => Curso::finPara($c->fecha_inicio, $programa)]));
            }
        });

        Auditoria::registrar('editar', $programa, "Editó el programa {$programa->nombre} ({$programa->codigo}).");

        return response()->json(['ok' => true, 'mensaje' => "Se guardaron los cambios de {$programa->nombre}."]);
    }

    /** Activo ⇄ inactivo (un programa en aprobación pasa a activo). */
    public function cambiarEstado(Programa $programa): JsonResponse
    {
        $this->asegurarSede($programa);

        $nuevo = $programa->estado === 'activo' ? 'inactivo' : 'activo';
        $programa->update(['estado' => $nuevo]);

        $accion = $nuevo === 'activo' ? 'activar' : 'desactivar';
        $verbo  = $nuevo === 'activo' ? 'Activó' : 'Desactivó';
        Auditoria::registrar($accion, $programa, "{$verbo} el programa {$programa->nombre} ({$programa->codigo}).");

        return response()->json([
            'ok'      => true,
            'estado'  => $nuevo,
            'mensaje' => $nuevo === 'activo' ? "{$programa->nombre} quedó activo." : "{$programa->nombre} quedó inactivo.",
        ]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }

    /** El admin solo puede modificar programas ofertados en su sede. */
    private function asegurarSede(Programa $programa): void
    {
        abort_unless($programa->sedes()->where('sedes.id', $this->sedeId())->exists(), 404);
    }

    private function datos(ProgramaRequest $request): array
    {
        $datos = $request->validated();
        $datos['duracion_periodos'] = Programa::calcularPeriodos($datos['duracion_meses'], $datos['tipo_periodo']);
        $datos['jornadas'] = array_values(array_intersect(Programa::JORNADAS, $datos['jornadas']));

        return $datos;
    }

    /** ESA-{SIGLA}-NNN con el siguiente consecutivo libre de la escuela. */
    private function siguienteCodigo(int $escuelaId): string
    {
        $sigla = Escuela::findOrFail($escuelaId)->sigla;
        $prefijo = "ESA-{$sigla}-";
        $n = Programa::where('codigo', 'like', $prefijo.'%')->count() + 1;

        while (Programa::where('codigo', $prefijo.str_pad((string) $n, 3, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $prefijo.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /** Forma que espera programas.js. */
    private function paraVista(Programa $p): array
    {
        return [
            'id'             => $p->id,
            'codigo'         => $p->codigo,
            'nombre'         => $p->nombre,
            'escuela_id'     => $p->escuela_id,
            'escuela'        => $p->escuela->nombre,
            'sigla'          => mb_strtolower($p->escuela->sigla),
            'nivel'          => $p->nivel,
            'modalidad'      => $p->modalidad,
            'horas'          => $p->horas,
            'meses'          => $p->duracion_meses,
            'tipo_periodo'   => $p->tipo_periodo,
            'periodos'       => $p->duracion_periodos,
            'resolucion'     => $p->resolucion,
            'descripcion'    => $p->descripcion,
            'perfil_egreso'  => $p->perfil_egreso,
            'jornadas'       => $p->jornadas ?? [],
            'cupo'           => $p->cupo_grupo,
            'valor_programa' => (float) $p->precio_total,
            'matricula'      => (float) $p->matricula,
            'estado'         => $p->estado,
            'estudiantes'    => $p->estudiantes,
            'modulos'        => $p->modulos->map(fn ($m) => [
                'nombre'  => $m->nombre,
                'horas'   => $m->horas,
                // Se asigna cuando exista el módulo de Docentes/Cursos
                'docente' => 'Sin asignar',
            ])->values(),
            'grupos'         => $p->cursos->map(fn ($c) => [
                'nombre'    => "{$c->nombre} — {$c->jornada}",
                'jornada'   => $c->jornada,
                'cupo'      => $p->cupo_grupo,
                'inscritos' => $c->estudiantes_count,
                'inicio'    => $c->fecha_inicio->format('d/m/Y'),
                'fin'       => $c->fecha_fin->format('d/m/Y'),
            ])->values(),
        ];
    }
}
