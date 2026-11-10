<?php

namespace App\Http\Controllers\Admin\Programas;

use App\Http\Controllers\Controller;
use App\Models\Academico\Escuela;
use App\Models\Academico\Programa;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Secretaría › Programas: los programas que ofrece la sede (consulta) y elegir cuáles del catálogo ofrece. Decidido con
 * el usuario: el catálogo —programas, malla, precios y escuelas— lo maneja el Admin de ESAT (AdminGeneral\Programas).
 */
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
                    ->withCount('estudiantesActivos')->orderBy('nombre'),
            ])
            ->withCount(['matriculas as estudiantes' => fn ($q) => $q->where('sede_id', $sedeId)->where('estado', 'activo')])
            ->orderBy('nombre')
            ->get();

        $escuelas = Escuela::withCount(['programas as programas_sede' => fn ($q) => $q->deSede($sedeId)])
            ->orderBy('nombre')
            ->get();

        // Lo que la sede todavía no ofrece del catálogo (los inactivos no se ofrecen)
        $catalogo = Programa::where('estado', '!=', 'inactivo')->whereNotIn('id', $programas->pluck('id'))
            ->with('escuela')->orderBy('nombre')->get()
            ->map(fn (Programa $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'codigo' => $p->codigo, 'escuela' => $p->escuela->nombre,
                'meses' => $p->duracion_meses, 'precio_total' => (float) $p->precio_total]);

        return view('admin.programas.index', [
            'programas'    => $programas->map(fn (Programa $p) => $this->paraVista($p))->values(),
            'escuelas'     => $escuelas,
            'catalogo'     => $catalogo,
            'cuotaSistema' => Programa::cuotaSistemaMensual(),
        ]);
    }

    /** Ofrece o deja de ofrecer un programa del catálogo en la sede (no se deja con grupos vigentes; no se ofrece uno inactivo). */
    public function oferta(Programa $programa): JsonResponse
    {
        $sede = Sede::findOrFail($this->sedeId());
        $corto = str_replace('Técnico Laboral en ', '', $programa->nombre);
        if ($programa->sedes()->where('sedes.id', $sede->id)->exists()) {
            $n = $programa->cursos()->where('sede_id', $sede->id)->where('estado', '!=', 'finalizado')->count();
            if ($n) {
                return response()->json(['message' => "No se puede dejar de ofrecer: tiene {$n} ".($n === 1 ? 'grupo vigente' : 'grupos vigentes').' en la sede. Se quita cuando terminen.'], 422);
            }
            $programa->sedes()->detach($sede->id);
            Auditoria::registrar('editar', $programa, "Dejó de ofrecer el programa {$programa->nombre} en la sede {$sede->nombre}.");

            return response()->json(['ok' => true, 'mensaje' => "La sede ya no ofrece {$corto}."]);
        }
        if ($programa->estado === 'inactivo') {
            return response()->json(['message' => 'Ese programa está inactivo en el catálogo: no se puede ofrecer.'], 422);
        }
        $programa->sedes()->attach($sede->id);
        Auditoria::registrar('editar', $programa, "Empezó a ofrecer el programa {$programa->nombre} en la sede {$sede->nombre}.");

        return response()->json(['ok' => true, 'mensaje' => "La sede ya ofrece {$corto}: puedes abrirle grupos."]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sedeTrabajoId();
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
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
                'inscritos' => $c->estudiantes_activos_count,
                'inicio'    => $c->fecha_inicio->format('d/m/Y'),
                'fin'       => $c->fecha_fin->format('d/m/Y'),
            ])->values(),
        ];
    }
}
