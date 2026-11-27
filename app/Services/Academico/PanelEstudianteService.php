<?php

namespace App\Services\Academico;

use App\Http\Requests\Estudiante\Foro\ExigeMatriculaVigente;
use App\Enums\RolUsuario;
use App\Models\Academico\Asistencia;
use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\CursoSesion;
use App\Models\Academico\Programa;
use App\Models\Comunicacion\Comunicado;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\Institucional\Configuracion;
use App\Models\User;
use App\Services\Comunicacion\ForoService;
use App\Services\Finanzas\PagoService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panel Estudiante: todo lo que leen sus pantallas, en la forma que documenta public/assets/js/estudiante/datos.js
 * (window.EST_SERVIDOR.datos → EST_DATOS). Usa las reglas de siempre, así lo que ve el estudiante cuadra con lo que ven
 * la Secretaría y el Docente: módulos con CalificacionService::fechasModulos, asistencia con las reglas de
 * AsistenciaService, cobros con PagoService::resumen, comunicados con los destinatarios de ComunicadoService y el foro
 * de su sede sin lo oculto. La matrícula que se muestra es la de EstudianteService::matriculaVigente (la activa o la
 * más reciente). Sin matrícula, las partes académicas y de pagos van vacías.
 */
class PanelEstudianteService
{
    private const ESTADO_ASISTENCIA = ['presente' => 'P', 'tarde' => 'T', 'ausente' => 'A', 'excusa' => 'E'];

    private const DIA_ISO = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];

    private const ROL = ['admin' => 'Administrativo', 'admin_general' => 'Admin', 'docente' => 'Docente', 'superadmin' => 'Administrativo'];

    public function __construct(
        private CalificacionService $calificaciones,
        private EstudianteService $estudiantes,
        private PagoService $pagos,
        private AulaService $aula,
        private ForoService $foro,
    ) {}

    /**
     * La matrícula que se muestra del estudiante, cargada con lo que necesita el panel. Siempre se vuelve a leer: después
     * de elegir el plan, responder o entregar, la misma petición necesita lo recién guardado.
     */
    public function matricula(User $estudiante): ?Matricula
    {
        $estudiante->load([
            'matriculas.programa.escuela', 'matriculas.programa.modulos', 'matriculas.curso.programa.modulos',
            'matriculas.curso.sesiones', 'matriculas.curso.historialSesiones', 'matriculas.curso.docentes',
            'matriculas.pagos', 'matriculas.recaudos.cobros', 'matriculas.calificaciones', 'matriculas.grupos',
        ]);

        return $this->estudiantes->matriculaVigente($estudiante);
    }

    public function datos(User $estudiante, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();
        $estudiante->loadMissing('sede');
        $m = $this->matricula($estudiante);
        $curso = $m?->curso;

        $modulos = $m ? $this->modulos($m, $curso, $ahora) : [];
        $clases = $m && $curso ? $this->clases($estudiante, $m, $curso, $modulos, $ahora) : [];
        $a = ['P' => 0, 'T' => 0, 'A' => 0, 'E' => 0, 'sinRegistrar' => 0];
        foreach ($clases as $c) {
            if (isset($a[$c['estado']])) {
                $a[$c['estado']]++;
            } elseif ($c['estado'] === 'sin_registrar') {
                $a['sinRegistrar']++;
            }
        }
        $a['pct'] = AsistenciaService::porcentaje($a['P'] + $a['T'], $a['A']);
        $a['situacion'] = AsistenciaService::situacion($a['pct']);

        $conNota = collect($modulos)->whereNotNull('nota');
        $aula = $m && $curso && $m->estado !== 'anulado' ? $this->aula->paraEstudiante($estudiante, $curso, $ahora) : ['evaluaciones' => [], 'actividades' => [], 'materiales' => []];

        return [
            'hoy'          => $ahora->toDateString(),
            'estudiante'   => [
                'nombres'   => $estudiante->nombres,
                'apellidos' => $estudiante->apellidos,
                'sede'      => $estudiante->sede?->nombre,
                'documento' => trim(($estudiante->tipo_documento ?? '').' '.($estudiante->numero_documento ?? '')),
            ],
            'matricula'    => $m ? $this->datosMatricula($m, $curso) : null,
            'modulos'      => $modulos,
            'moduloActual' => collect($modulos)->firstWhere('estado', 'en_curso'),
            'promedio'     => $conNota->isNotEmpty() ? round($conNota->avg('nota'), 1) : null,
            'aprobados'    => collect($modulos)->whereIn('estadoNota', ['aprobado', 'habilito'])->count(),
            'clases'       => $clases,
            'asistencia'   => $a,
            'evaluaciones' => $aula['evaluaciones'],
            'actividades'  => $aula['actividades'],
            'materiales'   => $aula['materiales'],
            'pagos'        => $m ? $this->pagosDe($m, $curso) : null,
            'comunicados'  => $this->comunicados($estudiante, $m),
            'foro'         => $this->foro->panel($estudiante, 'estudiante.foro.adjunto'),
            // Abrir temas, responder y reportar: solo con matrícula vigente (los Form Requests del foro lo exigen)
            'participaForo' => ExigeMatriculaVigente::participa($estudiante),
        ];
    }

    /* ── Matrícula, módulos y clases ───────────────────────────────────── */

    private function datosMatricula(Matricula $m, ?Curso $curso): array
    {
        $p = $m->programa;

        return [
            'id'            => $m->id,
            'codigo'        => $m->codigo,
            'fecha'         => $m->fecha_inicio->toDateString(),
            'estado'        => $m->estado,
            'estadoFecha'   => $m->estado_fecha?->toDateString(),
            'programa'      => $p->nombre,
            'escuela'       => $p->escuela?->nombre,
            'nivel'         => $p->nivel,
            'modalidad'     => $p->modalidad,
            'duracionMeses' => (int) $p->duracion_meses,
            'tipoPeriodo'   => $p->tipo_periodo,
            'grupo'         => $curso ? [
                'nombre'  => $curso->nombre,
                'codigo'  => $curso->codigo,
                'jornada' => $curso->jornada,
                'estado'  => $curso->estado,
                'inicio'  => $curso->fecha_inicio->toDateString(),
                'fin'     => $curso->fin()->toDateString(),
                'docente' => ($d = $curso->docentes->first()) ? trim($d->nombres.' '.$d->apellidos) : null,
            ] : null,
            'horario'       => $curso ? $curso->sesiones->map(fn (CursoSesion $s) => [
                'dia' => $s->dia, 'diaIso' => self::DIA_ISO[$s->dia] ?? 0, 'ini' => $s->inicio(), 'fin' => $s->fin(),
            ])->sortBy('diaIso')->values()->all() : [],
        ];
    }

    /** Módulos de la malla con sus fechas (las del grupo) y la nota de esta matrícula. */
    private function modulos(Matricula $m, ?Curso $curso, CarbonInterface $ahora): array
    {
        $notas = $m->calificaciones->keyBy('programa_modulo_id');
        $fechas = $curso ? collect($this->calificaciones->fechasModulos($curso, $ahora))->keyBy('id') : collect();
        // Desde que llegó a su grupo (historial de grupos): lo que terminó allí antes y no tiene nota no lo cursó en él
        $ventanas = $curso ? $m->ventanasEn($curso) : [];
        $desde = $ventanas ? $ventanas[0]['desde'] : $m->fecha_inicio->toDateString();

        return $m->programa->modulos->values()->map(function ($mod, int $i) use ($notas, $fechas, $desde) {
            $f = $fechas->get($mod->id);
            /** @var Calificacion|null $c */
            $c = $notas->get($mod->id);
            $estado = $f['estado'] ?? 'por_iniciar';
            $n = $c?->nota !== null ? round((float) $c->nota, 1) : null;
            $h = $c?->habilitacion !== null ? round((float) $c->habilitacion, 1) : null;
            $estadoNota = $n !== null ? $c->estado()
                : ($estado === 'terminado' ? ($f && $f['fin'] < $desde ? 'no_cursado' : 'sin_nota') : $estado);

            return [
                'id'         => $mod->id,
                'numero'     => $i + 1,
                'nombre'     => $mod->nombre,
                'horas'      => (int) $mod->horas,
                'ini'        => $f['ini'] ?? null,
                'fin'        => $f['fin'] ?? null,
                'estado'     => $estado,
                'n'          => $n,
                'h'          => $h,
                'fechaNota'  => $c?->fecha_nota?->toDateString(),
                'fechaHab'   => $c?->fecha_habilitacion?->toDateString(),
                'nota'       => $n !== null ? ($h ?? $n) : null,
                'estadoNota' => $estadoNota,
            ];
        })->all();
    }

    /**
     * Clases que le tocan al estudiante en cada grupo por el que ha pasado con esta matrícula (historial de grupos): en
     * cada uno, solo mientras contaba allí (Matricula::ventanasEn: desde que llegó hasta que salió, se aplazó, retiró o
     * graduó), con su registro. P/T/A/E · sin_registrar (ya dictada y sin registro: no cuenta en el %) · hoy (todavía no
     * termina) · futura. Cada día con el horario que regía entonces (Curso::sesionEn).
     */
    private function clases(User $e, Matricula $m, Curso $curso, array $modulos, CarbonInterface $ahora): array
    {
        $hoy = $ahora->copy()->startOfDay();
        $ids = $m->periodos()->pluck('curso_id')->push($curso->id)->unique()->values();
        $cursos = $ids->count() === 1 ? collect([$curso])
            : Curso::with(['sesiones', 'historialSesiones', 'programa.modulos'])->whereIn('id', $ids)->get();
        $registros = Asistencia::whereIn('curso_id', $ids)->where('estudiante_id', $e->id)
            ->get(['curso_id', 'fecha', 'estado', 'motivo'])->keyBy(fn (Asistencia $a) => $a->curso_id.'|'.$a->fecha->toDateString());

        $out = [];
        foreach ($cursos as $c) {
            $ventanas = $m->ventanasEn($c);
            if (! $ventanas) {
                continue;
            }
            $mods = $c->id === $curso->id ? $modulos : collect($this->calificaciones->fechasModulos($c, $ahora))->all();
            $fin = $c->fin();
            for ($d = $c->fecha_inicio->copy()->startOfDay(); $d->lt($fin); $d->addDay()) {
                $f = $d->toDateString();
                if (! Matricula::enVentanas($ventanas, $f)) {
                    continue;
                }
                $s = $c->claseEn($d);
                if (! $s) {
                    continue;
                }
                // Después de aplazarse o retirarse no hay clases por venir
                if ($m->estado !== 'activo' && $d->gt($hoy)) {
                    break;
                }
                $dictada = $d->lt($hoy) || ($d->equalTo($hoy) && $ahora->gte($hoy->copy()->setTimeFromTimeString($s->hora_fin)));
                $r = $registros->get($c->id.'|'.$f);
                $estado = ! $dictada ? ($d->equalTo($hoy) ? 'hoy' : 'futura')
                    : ($r ? self::ESTADO_ASISTENCIA[$r->estado] ?? 'sin_registrar' : 'sin_registrar');
                $out[] = [
                    'fecha'    => $f,
                    'dia'      => $s->dia,
                    'ini'      => $s->inicio(),
                    'fin'      => $s->fin(),
                    'moduloId' => collect($mods)->first(fn ($x) => $x['ini'] && $x['ini'] <= $f && $f <= $x['fin'])['id'] ?? null,
                    'estado'   => $estado,
                    'motivo'   => $estado === 'E' ? $r->motivo : null,
                ];
            }
        }

        return collect($out)->sortBy('fecha')->values()->all();
    }

    /* ── Pagos ─────────────────────────────────────────────────────────── */

    private function pagosDe(Matricula $m, ?Curso $curso): array
    {
        $programa = $m->programa;
        $resumen = $this->pagos->resumen($m);
        $total = $m->totalCuotas($m->pagos);
        $cobros = $m->pagos->where('estado', '!=', 'anulado')
            ->sortBy(fn (Pago $p) => [$p->tipo === 'matricula' ? 0 : 1, $p->numero_cuota ?? 0])
            ->values()->map(function (Pago $p) use ($total) {
                $estado = $p->estadoActual();

                return [
                    'id'            => $p->id,
                    'tipo'          => $p->tipo,
                    'numero'        => $p->numero_cuota,
                    'total'         => $p->esCuota() ? $total : null,
                    'vence'         => $p->fecha_vencimiento?->toDateString(),
                    'valor'         => (int) round((float) $p->monto_total),
                    'valorPrograma' => $p->esCuota() ? (int) round((float) $p->monto_programa - (float) $p->descuento) : null,
                    'sistema'       => $p->esCuota() ? (int) round((float) $p->monto_sistema) : null,
                    'abonado'       => (int) round($p->disponible()),
                    'restante'      => (int) round($p->restante()),
                    'estado'        => $estado === 'pagado' ? 'pagado' : ($estado === 'vencido' ? 'vencido' : 'pendiente'),
                    'concepto'      => $p->tipo === 'matricula' ? 'Derecho de matrícula' : 'Cuota '.$p->numero_cuota.' de '.$total,
                ];
            });

        $hoy = today()->toDateString();
        $proxima = $cobros->filter(fn ($c) => $c['restante'] > 0 && $c['vence'] && $c['vence'] >= $hoy)->sortBy('vence')->first();
        $derecho = $m->pagos->firstWhere('tipo', 'matricula');

        return [
            'plan'          => $m->plan_pago,
            'puedeElegir'   => ! $m->plan_pago && $m->estado === 'activo' && $curso !== null,
            'precio'        => (int) round($m->precioPrograma()),
            'derecho'       => $derecho ? (int) round((float) $derecho->monto_total) : (int) round((float) $programa->matricula),
            'cuotaSistema'  => (int) round(Programa::cuotaSistemaMensual()),
            'descuentoPct'  => (float) $m->descuento_pct,
            'opciones'      => $curso ? $this->opcionesPlan($m, $curso) : null,
            'vencidasSiElige' => $curso ? [
                'mensual' => $this->cuotasVencidasSiElige($m, $curso, 'mensual'),
                'periodo' => $this->cuotasVencidasSiElige($m, $curso, 'periodo'),
            ] : ['mensual' => 0, 'periodo' => 0],
            'cobros'        => $cobros->all(),
            'recibos'       => $m->recaudos->sortByDesc(fn (Recaudo $r) => [$r->fecha_pago->timestamp, $r->id])->values()
                ->map(fn (Recaudo $r) => [
                    'numero'     => $r->recibo,
                    'fecha'      => $r->fecha_pago->toDateString(),
                    'medio'      => $r->metodo_pago,
                    'referencia' => $r->referencia,
                    'total'      => (int) round((float) $r->valor),
                    'aplicado'   => $r->cobros->map(fn (Pago $p) => [
                        'concepto' => $p->tipo === 'matricula' ? 'Derecho de matrícula' : 'Cuota '.$p->numero_cuota.' de '.$total,
                        'monto'    => (int) round((float) $p->pivot->monto),
                    ])->values()->all(),
                    'anulado'    => $r->anulado(),
                    'motivo'     => $r->motivo_anulacion,
                ])->all(),
            'resumen'       => [
                'vencido'  => $resumen['saldo_vencido'],
                'aPagar'   => $resumen['saldo'],
                'pagado'   => $resumen['pagado'],
                'porPagar' => $resumen['por_pagar'],
                'proxima'  => $proxima,
            ],
        ];
    }

    /** Lo que costaría cada plan (las cuotas las genera PagoService::generarCuotas con estos mismos valores). */
    private function opcionesPlan(Matricula $m, Curso $curso): array
    {
        $programa = $m->programa;
        $precio = $m->precioPrograma();
        $primera = $this->pagos->primeraCuota($curso)->toDateString();
        $op = function (string $plan) use ($programa, $precio, $primera, $m) {
            [$base, $sistema] = $this->pagos->valoresCuota($programa, $plan, $precio);
            $base -= round($base * min((float) $m->descuento_pct, 100) / 100);
            $cuotas = $plan === 'mensual' ? (int) $programa->duracion_meses : (int) $programa->duracion_periodos;

            return [
                'plan'          => $plan,
                'nombre'        => $plan === 'mensual' ? 'Mensual' : ($programa->tipo_periodo === 'semestre' ? 'Semestral' : 'Trimestral'),
                'cuotas'        => $cuotas,
                'valorPrograma' => (int) $base,
                'sistema'       => (int) $sistema,
                'meses'         => $plan === 'mensual' ? 1 : $programa->mesesDelPeriodo(0),
                'cuota'         => (int) ($base + $sistema),
                'cada'          => $plan === 'mensual' ? 'mes' : $programa->tipo_periodo,
                'primera'       => $primera,
            ];
        };

        return ['mensual' => $op('mensual'), 'periodo' => $op('periodo')];
    }

    /** Cuántas cuotas quedarían vencidas de una vez si elige ese plan hoy (cuentan desde el inicio de clases). */
    private function cuotasVencidasSiElige(Matricula $m, Curso $curso, string $plan): int
    {
        $programa = $m->programa;
        $primera = $this->pagos->primeraCuota($curso);
        $cuotas = $plan === 'mensual' ? (int) $programa->duracion_meses : (int) $programa->duracion_periodos;
        $n = 0;
        for ($i = 0; $i < $cuotas; $i++) {
            $meses = $plan === 'mensual' ? $i : $programa->inicioDelPeriodo($i);
            if ($primera->copy()->addMonthsNoOverflow($meses)->lt(today())) {
                $n++;
            }
        }

        return $n;
    }

    /* ── Comunicados y foro ─────────────────────────────────────────────── */

    /**
     * Comunicados que le llegan (los mismos destinatarios de ComunicadoService): publicados y sin archivar de su sede,
     * para toda la sede o para estudiantes, y los de su docente a su grupo; solo con matrícula vigente y cuenta activa.
     */
    public function comunicadosVisibles(User $estudiante, ?Matricula $m): Collection
    {
        if (! $m || ! in_array($m->estado, Matricula::VIGENTES, true) || ! $estudiante->activo || ! $estudiante->sede_id) {
            return collect();
        }
        $hoy = today()->toDateString();

        return Comunicado::where('sede_id', $estudiante->sede_id)
            ->whereNull('archivado_en')
            ->whereIn('estado', ['publicado', 'programado'])
            ->where(fn ($q) => $q->whereIn('destino', ['sede', 'estudiantes'])
                ->orWhere(fn ($q2) => $q2->where('destino', 'grupo')->where('curso_id', $m->curso_id)))
            ->where(fn ($q) => $q->whereNull('visible_hasta')->orWhereDate('visible_hasta', '>=', $hoy))
            ->with(['autor', 'curso.programa', 'lectores' => fn ($q) => $q->where('users.id', $estudiante->id)])
            ->get()
            ->filter(fn (Comunicado $c) => $c->estadoActual() === 'publicado')
            ->values();
    }

    private function comunicados(User $estudiante, ?Matricula $m): array
    {
        return $this->comunicadosVisibles($estudiante, $m)
            ->sortByDesc(fn (Comunicado $c) => [(int) $c->fijado, ($c->publicado_en ?? $c->created_at)->timestamp])
            ->values()
            ->map(function (Comunicado $c) {
                $a = $c->autor;
                $para = match ($c->destino) {
                    'grupo'       => ($c->curso?->nombre ?? 'Tu grupo').' · '.str_replace('Técnico Laboral en ', '', $c->curso?->programa?->nombre ?? ''),
                    'estudiantes' => 'Estudiantes de la sede',
                    default       => 'Toda la sede',
                };

                return [
                    'id'      => $c->id,
                    'titulo'  => $c->titulo,
                    'cuerpo'  => (string) $c->mensaje,
                    'de'      => ['nombre' => $a ? (trim($a->nombres.' '.$a->apellidos) ?: 'Administración de la sede') : 'Administración de la sede', 'rol' => self::ROL[$a?->role->value ?? 'admin'] ?? 'Administrativo'],
                    'para'    => $para,
                    'fecha'   => ($c->publicado_en ?? $c->created_at)->format('Y-m-d H:i'),
                    'fijado'  => (bool) $c->fijado,
                    'urgente' => $c->categoria === 'urgente',
                    'leido'   => $c->lectores->isNotEmpty(),
                    'adjunto' => $c->adjunto_ruta ? [
                        'nombre' => $c->adjunto_nombre,
                        'tamano' => (int) $c->adjunto_peso,
                        'url'    => route('estudiante.comunicados.adjunto', $c),
                    ] : null,
                ];
            })->all();
    }

    /** Datos legales de ESAT y la sede para los documentos imprimibles (Admin › Configuración). */
    public function institucion(User $estudiante): array
    {
        return [
            'institucion' => Configuracion::institucion(),
            'sede'        => $estudiante->sede?->only(['nombre', 'direccion', 'ciudad']),
        ];
    }
}
