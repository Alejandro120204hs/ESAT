<?php

namespace App\Services\Inicio;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Comunicacion\Comunicado;
use App\Models\Comunicacion\ForoReporte;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\AsistenciaService;
use App\Services\Academico\CalificacionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panel Admin › Inicio: lo que está pasando hoy en la sede (KPI con su variación frente al mes anterior,
 * matrículas nuevas de los últimos 6 meses, actividad reciente de la auditoría, mejores promedios, ocupación por
 * programa y los pendientes de hoy con su enlace). Todo sale de los datos reales; nada se guarda.
 */
class InicioService
{
    public function __construct(
        private AsistenciaService $asistencia,
        private CalificacionService $calificaciones,
    ) {}

    public function datosSede(int $sedeId): array
    {
        $hoy = now();
        $finAnterior = $hoy->copy()->subMonthNoOverflow()->endOfMonth();
        $matriculas = Matricula::where('sede_id', $sedeId)->where('estado', '!=', 'anulado')->with('programa')->get();

        return [
            'kpis'      => $this->kpis($sedeId, $matriculas, $hoy, $finAnterior),
            'meses'     => $this->inscripciones($matriculas, $hoy),
            'actividad' => $this->actividad($sedeId),
            'top'       => $this->mejores($matriculas),
            'programas' => $this->programas($sedeId),
            'tareas'    => $this->pendientes($sedeId, $hoy),
        ];
    }

    /* ── KPI ── */

    private function kpis(int $sedeId, Collection $matriculas, CarbonInterface $hoy, CarbonInterface $finAnterior): array
    {
        // Estudiantes activos hoy y al cierre del mes anterior (el estado solo cuenta desde su fecha)
        $activosAl = fn (CarbonInterface $d) => $matriculas->filter(fn (Matricula $m) => $m->fecha_inicio && $m->fecha_inicio->lte($d)
            && ($m->estado_fecha && $m->estado_fecha->lte($d) ? $m->estado : 'activo') === 'activo')->count();
        $activos = $activosAl($hoy);
        $antes = $activosAl($finAnterior);
        $nuevos = $matriculas->filter(fn (Matricula $m) => $m->fecha_inicio && $m->fecha_inicio->isSameMonth($hoy))->count();

        // Ingresos: lo recaudado este mes (sin recibos anulados) frente al mes anterior completo
        $recaudo = fn (CarbonInterface $desde, CarbonInterface $hasta) => (int) round((float) Recaudo::whereNull('anulado_en')
            ->whereHas('matricula', fn ($q) => $q->where('sede_id', $sedeId))
            ->whereBetween('fecha_pago', [$desde->toDateString(), $hasta->toDateString()])->sum('valor'));
        $ingresos = $recaudo($hoy->copy()->startOfMonth(), $hoy);
        $ingresosAntes = $recaudo($finAnterior->copy()->startOfMonth(), $finAnterior);
        $cambio = $ingresosAntes ? (int) round(($ingresos - $ingresosAntes) * 100 / $ingresosAntes) : null;

        // Grupos dictando clases hoy y los que están por empezar
        $cursos = Curso::where('sede_id', $sedeId)->with('programa')->get();
        $enCurso = $cursos->filter(fn (Curso $c) => $c->estado === 'activo' && $c->fecha_inicio?->lte($hoy) && Curso::finPara($c->fecha_inicio, $c->programa)->gt($hoy));
        $porIniciar = $cursos->filter(fn (Curso $c) => $c->estado === 'planificacion' || ($c->estado === 'activo' && $c->fecha_inicio?->gt($hoy)))->count();

        // Estudiantes con algún cobro vencido hoy y al cierre del mes anterior
        $mora = $this->mora($matriculas, $hoy);
        $moraAntes = $this->mora($matriculas, $finAnterior);

        $signo = fn (int $n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')).abs($n);

        return [
            ['label' => 'Estudiantes activos', 'value' => $activos, 'delta' => $signo($activos - $antes), 'dir' => $activos >= $antes ? 'up' : 'down',
                'foot' => $nuevos === 1 ? '1 matrícula nueva este mes' : "{$nuevos} matrículas nuevas este mes", 'icon' => 'users', 'color' => 'blue',
                'title' => 'Frente al cierre de '.$finAnterior->locale('es')->translatedFormat('F')],
            ['label' => 'Ingresos del mes', 'value' => $ingresos, 'prefix' => '$ ', 'delta' => $cambio === null ? '—' : $signo($cambio).' %', 'dir' => ($cambio ?? 0) >= 0 ? 'up' : 'down',
                'foot' => 'Recaudado en '.$hoy->locale('es')->translatedFormat('F').' · '.$finAnterior->locale('es')->translatedFormat('F').' cerró en $ '.number_format($ingresosAntes, 0, ',', '.'),
                'icon' => 'money', 'color' => 'green', 'title' => 'Frente al mes anterior completo'],
            ['label' => 'Grupos en clases', 'value' => $enCurso->count(), 'delta' => '+'.$porIniciar, 'dir' => 'up',
                'foot' => 'En '.$enCurso->pluck('programa_id')->unique()->count().' programas · '.$porIniciar.' por iniciar', 'icon' => 'book', 'color' => 'orange',
                'title' => 'Grupos por iniciar'],
            ['label' => 'Estudiantes en mora', 'value' => $mora['estudiantes'], 'delta' => $signo($mora['estudiantes'] - $moraAntes['estudiantes']),
                'dir' => $mora['estudiantes'] <= $moraAntes['estudiantes'] ? 'down' : 'up', 'tono' => $mora['estudiantes'] <= $moraAntes['estudiantes'] ? 'up' : 'down',
                'foot' => '$ '.number_format($mora['valor'], 0, ',', '.').' vencido · requieren seguimiento', 'icon' => 'alert', 'color' => 'warn',
                'title' => 'Frente al cierre de '.$finAnterior->locale('es')->translatedFormat('F')],
        ];
    }

    /** Cuántos estudiantes tenían un cobro vencido con saldo a la fecha, y cuánto (pagos recibidos hasta ese día). */
    private function mora(Collection $matriculas, CarbonInterface $d): array
    {
        $cobros = Pago::whereIn('matricula_id', $matriculas->pluck('id'))->where('estado', '!=', 'anulado')
            ->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', $d->toDateString())
            ->with(['recaudos' => fn ($q) => $q->whereNull('anulado_en')])->get();
        $porMat = [];
        foreach ($cobros as $p) {
            $m = $matriculas->firstWhere('id', $p->matricula_id);
            if (! $m || ! $m->fecha_inicio || $m->fecha_inicio->gt($d)) {
                continue;
            }
            $pagado = $p->recaudos->filter(fn ($r) => $r->fecha_pago->lte($d))->sum(fn ($r) => (float) $r->pivot->monto);
            $saldo = (float) $p->monto_total - $pagado;
            if ($saldo > 0.5) {
                $porMat[$m->estudiante_id] = ($porMat[$m->estudiante_id] ?? 0) + $saldo;
            }
        }

        return ['estudiantes' => count($porMat), 'valor' => (int) round(array_sum($porMat))];
    }

    /* ── Matrículas nuevas por mes (últimos 6) ── */

    private function inscripciones(Collection $matriculas, CarbonInterface $hoy): array
    {
        $meses = collect(range(5, 0))->map(function ($k) use ($matriculas, $hoy) {
            $mes = $hoy->copy()->startOfMonth()->subMonthsNoOverflow($k);

            return [
                'mes'    => rtrim(mb_convert_case($mes->locale('es')->translatedFormat('M'), MB_CASE_TITLE), '.'),
                'nombre' => $mes->locale('es')->translatedFormat('F'),
                'val'    => $matriculas->filter(fn (Matricula $m) => $m->fecha_inicio && $m->fecha_inicio->isSameMonth($mes))->count(),
                'active' => $k === 0,
            ];
        });
        $max = max(1, $meses->max('val'));
        $actual = $meses->last();
        $anterior = $meses->slice(-2, 1)->first();
        $semestreDesde = $hoy->month <= 6 ? $hoy->copy()->startOfYear() : $hoy->copy()->month(7)->startOfMonth();

        return [
            'barras'   => $meses->map(fn ($m) => $m + ['pct' => $m['val'] ? max(4, (int) round($m['val'] * 100 / $max)) : 0])->values()->all(),
            'actual'   => $actual,
            'anterior' => $anterior,
            'cambio'   => $anterior['val'] ? (int) round(($actual['val'] - $anterior['val']) * 100 / $anterior['val']) : null,
            'semestre' => $matriculas->filter(fn (Matricula $m) => $m->fecha_inicio && $m->fecha_inicio->gte($semestreDesde) && $m->fecha_inicio->lte($hoy))->count(),
        ];
    }

    /* ── Actividad reciente: la auditoría de la sede ── */

    private function actividad(int $sedeId): array
    {
        $usuarios = User::where('sede_id', $sedeId)->pluck('id');

        return Auditoria::whereIn('user_id', $usuarios)->with('usuario')->latest('created_at')->latest('id')->limit(6)->get()
            ->map(function (Auditoria $a) {
                $u = $a->usuario;
                $ini = mb_strtoupper(mb_substr($u->nombres ?? '', 0, 1).mb_substr($u->apellidos ?? '', 0, 1));

                return [
                    'dot'   => ['crear' => 'green', 'activar' => 'green', 'editar' => 'blue', 'desactivar' => 'red', 'eliminar' => 'red'][$a->accion] ?? 'orange',
                    'ini'   => $ini ?: 'A',
                    'quien' => trim(($u->nombres ?? '').' '.($u->apellidos ?? '')) ?: 'Administración',
                    'texto' => $a->descripcion,
                    'time'  => $this->mayuscula(Carbon::parse($a->created_at)->locale('es')->diffForHumans()),
                    'fecha' => Carbon::parse($a->created_at)->locale('es')->translatedFormat('j \d\e F \d\e Y, g:i a'),
                ];
            })->all();
    }

    private function mayuscula(string $t): string
    {
        return mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
    }

    /* ── Mejores promedios (matrículas vigentes con al menos dos notas) ── */

    private function mejores(Collection $matriculas): array
    {
        $vigentes = $matriculas->whereIn('estado', ['activo', 'aplazado'])->keyBy('id');
        $notas = Calificacion::whereIn('matricula_id', $vigentes->keys())->get()->groupBy('matricula_id');

        return $notas->filter(fn ($ns) => $ns->count() >= 2)
            ->map(fn ($ns, $matId) => ['m' => $vigentes[$matId], 'prom' => round($ns->avg(fn (Calificacion $k) => $k->notaFinal()), 1), 'n' => $ns->count()])
            ->sortBy([['prom', 'desc'], ['n', 'desc']])->take(5)->values()
            ->map(function ($x, $i) {
                $e = User::find($x['m']->estudiante_id);

                return [
                    'rank'     => $i + 1,
                    'ini'      => mb_strtoupper(mb_substr($e->nombres, 0, 1).mb_substr((string) $e->apellidos, 0, 1)),
                    'nombre'   => trim($e->nombres.' '.$e->apellidos),
                    'programa' => str_replace('Técnico Laboral en ', '', $x['m']->programa->nombre),
                    'nota'     => number_format($x['prom'], 1),
                    'modulos'  => $x['n'],
                ];
            })->all();
    }

    /* ── Ocupación de cupos por programa (grupos vigentes) ── */

    private function programas(int $sedeId): array
    {
        $cursos = Curso::where('sede_id', $sedeId)->whereIn('estado', ['activo', 'planificacion'])->with('programa')->get();
        $colores = ['blue', 'orange', 'green', 'blue', 'orange'];

        return $cursos->groupBy('programa_id')->map(function ($cs) {
            $p = $cs->first()->programa;
            $activos = $cs->sum(fn (Curso $c) => $c->estudiantesActivos()->count());
            $cupo = $cs->count() * (int) $p->cupo_grupo;

            return ['nombre' => str_replace('Técnico Laboral en ', '', $p->nombre), 'activos' => $activos, 'grupos' => $cs->count(),
                'pct' => $cupo ? (int) round($activos * 100 / $cupo) : 0];
        })->sortByDesc('activos')->take(5)->values()
            ->map(fn ($p, $i) => $p + ['color' => $colores[$i], 'sub' => $p['activos'].($p['activos'] === 1 ? ' estudiante' : ' estudiantes').' · '.$p['grupos'].($p['grupos'] === 1 ? ' grupo' : ' grupos')])
            ->all();
    }

    /* ── Pendientes de hoy, cada uno con su enlace ── */

    private function pendientes(int $sedeId, CarbonInterface $hoy): array
    {
        $tareas = [];
        $asis = $this->asistencia->resumenSede($sedeId, $hoy)['resumen'];
        $cal = $this->calificaciones->resumenSede($sedeId, $hoy)['resumen'];

        $reportes = ForoReporte::where('estado', 'pendiente')
            ->whereHas('mensaje', fn ($q) => $q->whereNull('oculto_en')->whereHas('tema', fn ($t) => $t->where('sede_id', $sedeId)))
            ->distinct('mensaje_id')->count('mensaje_id');
        if ($reportes) {
            $tareas[] = ['titulo' => $reportes === 1 ? '1 mensaje reportado en el foro' : "{$reportes} mensajes reportados en el foro", 'meta' => 'Revisar y ocultar o descartar',
                'tag' => 'Foro', 'color' => 'orange', 'url' => route('admin.foro')];
        }
        $venceSemana = Pago::whereHas('matricula', fn ($q) => $q->where('sede_id', $sedeId)->whereIn('estado', ['activo', 'aplazado']))
            ->whereIn('estado', ['pendiente', 'vencido'])->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $hoy->copy()->addDays(7)->toDateString()])
            ->get()->filter(fn (Pago $p) => $p->restante() > 0);
        if ($venceSemana->isNotEmpty()) {
            $tareas[] = ['titulo' => $venceSemana->count().($venceSemana->count() === 1 ? ' cobro vence' : ' cobros vencen').' esta semana',
                'meta' => '$ '.number_format($venceSemana->sum(fn (Pago $p) => $p->restante()), 0, ',', '.').' por recibir', 'tag' => 'Pagos', 'color' => 'blue', 'url' => route('admin.pagos')];
        }
        if ($asis['sin_tomar']) {
            $tareas[] = ['titulo' => $asis['sin_tomar'].' clases sin asistencia', 'meta' => $asis['docentes_sin'].($asis['docentes_sin'] === 1 ? ' docente sin registrar' : ' docentes sin registrar'),
                'tag' => 'Asistencia', 'color' => 'orange', 'url' => route('admin.asistencia')];
        }
        if ($cal['mod_sin_nota']) {
            $tareas[] = ['titulo' => $cal['mod_sin_nota'].($cal['mod_sin_nota'] === 1 ? ' módulo terminado sin notas' : ' módulos terminados sin notas'),
                'meta' => $cal['doc_sin_nota'].($cal['doc_sin_nota'] === 1 ? ' docente debe calificar' : ' docentes deben calificar'), 'tag' => 'Notas', 'color' => 'green', 'url' => route('admin.calificaciones')];
        }
        if ($cal['por_habilitar']) {
            $tareas[] = ['titulo' => $cal['por_habilitar'].($cal['por_habilitar'] === 1 ? ' estudiante por habilitar' : ' estudiantes por habilitar'),
                'meta' => 'Módulos perdidos con la habilitación pendiente', 'tag' => 'Académico', 'color' => 'green', 'url' => route('admin.calificaciones')];
        }
        $prog = Comunicado::where('sede_id', $sedeId)->where('estado', 'programado')->where('publicado_en', '>', $hoy)->orderBy('publicado_en')->first();
        if ($prog) {
            $tareas[] = ['titulo' => 'Comunicado programado', 'meta' => '«'.$prog->titulo.'» · sale el '.$prog->publicado_en->locale('es')->translatedFormat('j \d\e F'),
                'tag' => 'Comunicados', 'color' => 'blue', 'url' => route('admin.comunicados')];
        }

        return array_slice($tareas, 0, 5);
    }
}
