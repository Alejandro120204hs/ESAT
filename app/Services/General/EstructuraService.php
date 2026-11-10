<?php

namespace App\Services\General;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Academico\Escuela;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Recaudo;
use App\Models\Institucional\Sede;
use App\Models\User;
use App\Services\Inicio\InicioService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estructura de ESAT para el panel del Admin: sedes con sus cifras, secretarías, escuelas y programas.
 * Lo que devuelve tiene la misma forma que leen las pantallas de admin_general (window.GEN_DATOS).
 */
class EstructuraService
{
    public function __construct(private InicioService $inicio) {}

    /** Sedes con sus cifras de hoy y del cierre del mes anterior (Inicio, Sedes y demás pantallas). */
    public function sedes(): array
    {
        $hoy = now();
        $finAnterior = $hoy->copy()->subMonthNoOverflow()->endOfMonth();

        $matriculas = Matricula::where('estado', '!=', 'anulado')->get()->groupBy('sede_id');
        $cursos = Curso::with('programa')->withCount('estudiantesActivos')->get()->groupBy('sede_id');
        $ofertas = Sede::with('programas:id')->get()->mapWithKeys(fn (Sede $s) => [$s->id => $s->programas->pluck('id')->all()]);
        $ingresos = $this->ingresosPorSede($hoy);

        return Sede::orderBy('nombre')->get()->map(function (Sede $s) use ($hoy, $finAnterior, $matriculas, $cursos, $ofertas, $ingresos) {
            $mats = $matriculas->get($s->id, collect());
            $grupos = $cursos->get($s->id, collect());
            $vigentes = $grupos->filter(fn (Curso $c) => $c->estado !== 'finalizado' && ! $this->termino($c, $hoy));

            return [
                'id'                 => $s->id,
                'nombre'             => $s->nombre,
                'departamento'       => $s->departamento,
                'ciudad'             => $s->ciudad,
                'direccion'          => $s->direccion,
                'creada'             => $s->created_at?->toDateString(),
                'programas'          => $ofertas->get($s->id, []),
                'estudiantes'        => $this->activosAl($mats, $hoy),
                'estudiantesAnt'     => $this->activosAl($mats, $finAnterior),
                'grupos'             => $grupos->filter(fn (Curso $c) => $this->enClases($c, $hoy))->count(),
                'gruposAnt'          => $grupos->filter(fn (Curso $c) => $this->enClases($c, $finAnterior))->count(),
                'gruposPorIniciar'   => $grupos->filter(fn (Curso $c) => $c->estado === 'planificacion' || ($c->estado === 'activo' && $c->fecha_inicio?->gt($hoy)))->count(),
                'cupos'              => $vigentes->sum(fn (Curso $c) => $c->programa->cupo_grupo),
                'ocupados'           => $vigentes->sum('estudiantes_activos_count'),
                'ingresos'           => $ingresos['meses'][$s->id] ?? [0, 0, 0, 0, 0, 0],
                'ingresoMesAntAlDia' => $ingresos['alDia'][$s->id] ?? 0,
                'mora'               => $this->inicio->mora($mats, $hoy),
                'moraAnt'            => $this->inicio->mora($mats, $finAnterior),
            ];
        })->all();
    }

    /** Cuentas de secretaría (rol admin) de todas las sedes. */
    public function secretarias(): array
    {
        return User::where('role', RolUsuario::Admin->value)->orderBy('nombres')->orderBy('apellidos')->get()
            ->map(fn (User $u) => [
                'id'               => $u->id,
                'sede_id'          => $u->sede_id,
                'nombres'          => (string) $u->nombres,
                'apellidos'        => (string) $u->apellidos,
                'genero'           => $u->genero,
                'fecha_nacimiento' => $u->fecha_nacimiento?->toDateString(),
                'tipo_documento'   => $u->tipo_documento,
                'numero_documento' => $u->numero_documento,
                'telefono'         => $u->telefono,
                'email'            => $u->email,
                'activo'           => (bool) $u->activo,
                'ultimo_acceso'    => $u->ultimo_acceso?->toIso8601String(),
                'creada'           => $u->created_at?->toIso8601String(),
            ])->all();
    }

    public function escuelas(): array
    {
        return Escuela::withCount('programas')->orderBy('nombre')->get()
            ->map(fn (Escuela $e) => ['id' => $e->id, 'nombre' => $e->nombre, 'sigla' => $e->sigla, 'programas' => $e->programas_count])->all();
    }

    /** Catálogo de programas con su malla, precio (igual en todas las sedes) y en qué sedes se ofrece. */
    public function programas(): array
    {
        $conNotas = DB::table('calificaciones')->join('programa_modulos', 'programa_modulos.id', '=', 'calificaciones.programa_modulo_id')
            ->distinct()->pluck('programa_modulos.programa_id')->flip();

        return Programa::with(['modulos', 'sedes:id'])->orderBy('nombre')->get()
            ->map(fn (Programa $p) => [
                'id'                => $p->id,
                'codigo'            => $p->codigo,
                'nombre'            => $p->nombre,
                'escuela_id'        => $p->escuela_id,
                'nivel'             => $p->nivel,
                'modalidad'         => $p->modalidad,
                'horas'             => $p->horas,
                'resolucion'        => $p->resolucion,
                'descripcion'       => $p->descripcion,
                'perfil_egreso'     => $p->perfil_egreso,
                'jornadas'          => $p->jornadas ?? [],
                'cupo'              => $p->cupo_grupo,
                'precio_total'      => (int) round((float) $p->precio_total),
                'matricula'         => (int) round((float) $p->matricula),
                'duracion_meses'    => $p->duracion_meses,
                'tipo_periodo'      => $p->tipo_periodo,
                'duracion_periodos' => $p->duracion_periodos,
                'estado'            => $p->estado,
                'sedes'             => $p->sedes->pluck('id')->all(),
                'con_notas'         => $conNotas->has($p->id),   // la malla ya no se puede cambiar
                'modulos'           => $p->modulos->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'horas' => $m->horas])->values()->all(),
            ])->all();
    }

    /* ── Apoyo ── */

    /** Grupo dictando clases en esa fecha (la misma regla del Inicio de la secretaría). */
    public function enClases(Curso $c, CarbonInterface $d): bool
    {
        return $c->estado === 'activo' && $c->fecha_inicio?->lte($d) && Curso::finPara($c->fecha_inicio, $c->programa)->gt($d);
    }

    private function termino(Curso $c, CarbonInterface $d): bool
    {
        return $c->fecha_inicio && Curso::finPara($c->fecha_inicio, $c->programa)->lte($d);
    }

    /** Estudiantes con matrícula activa en esa fecha (el estado solo cuenta desde su fecha). */
    private function activosAl(Collection $matriculas, CarbonInterface $d): int
    {
        return $matriculas->filter(fn (Matricula $m) => $m->fecha_inicio && $m->fecha_inicio->lte($d)
            && ($m->estado_fecha && $m->estado_fecha->lte($d) ? $m->estado : 'activo') === 'activo')
            ->pluck('estudiante_id')->unique()->count();
    }

    /** Recaudado (sin recibos anulados) por sede en los últimos 6 meses, y el mes anterior hasta el mismo día de hoy. */
    private function ingresosPorSede(CarbonInterface $hoy): array
    {
        $desde = $hoy->copy()->startOfMonth()->subMonthsNoOverflow(5);
        $recaudos = Recaudo::whereNull('anulado_en')->whereBetween('fecha_pago', [$desde->toDateString(), $hoy->toDateString()])
            ->join('matriculas', 'matriculas.id', '=', 'recaudos.matricula_id')
            ->get(['matriculas.sede_id', 'recaudos.fecha_pago', 'recaudos.valor']);

        $meses = [];
        $alDia = [];
        $mesAnterior = $hoy->copy()->startOfMonth()->subMonthNoOverflow();
        $topeAnterior = $mesAnterior->copy()->day(min($hoy->day, $mesAnterior->daysInMonth))->endOfDay();
        foreach ($recaudos as $r) {
            $k = 5 - (int) $r->fecha_pago->copy()->startOfMonth()->diffInMonths($hoy->copy()->startOfMonth());
            if ($k < 0 || $k > 5) {
                continue;
            }
            $meses[$r->sede_id] ??= [0, 0, 0, 0, 0, 0];
            $meses[$r->sede_id][$k] += (int) round((float) $r->valor);
            if ($k === 4 && $r->fecha_pago->lte($topeAnterior)) {
                $alDia[$r->sede_id] = ($alDia[$r->sede_id] ?? 0) + (int) round((float) $r->valor);
            }
        }

        return ['meses' => $meses, 'alDia' => $alDia];
    }
}
