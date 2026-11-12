<?php

namespace App\Services\General;

use App\Enums\RolUsuario;
use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Finanzas\Pago;
use App\Models\Finanzas\Recaudo;
use App\Models\User;
use App\Services\Academico\EstudianteService;
use App\Services\Finanzas\PagoService;

/**
 * Personas de todas las sedes para el panel del Admin: estudiantes (con su matrícula, pagos y rendimiento solo para
 * consultar), acudientes y docentes. La forma es la que leen las pantallas de admin_general (window.GEN_DATOS).
 */
class PersonasService
{
    public function __construct(private EstudianteService $estudiantes, private PagoService $pagos) {}

    /** @return array{0: array, 1: array} estudiantes y sus recibos (recaudos) */
    public function estudiantes(?int $sedeId = null): array
    {
        $lista = User::where('role', RolUsuario::Estudiante)->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->with(['acudientes', 'asistencias', 'cursosComoEstudiante',
                'matriculas' => fn ($q) => $q->with(['programa', 'pagos.recaudos', 'recaudos', 'curso'])])
            ->orderBy('nombres')->orderBy('apellidos')->get();

        $vigentes = $lista->map(fn (User $e) => $this->estudiantes->matriculaVigente($e))->filter();
        $promedios = Calificacion::whereIn('matricula_id', $vigentes->pluck('id'))->get()->groupBy('matricula_id')
            ->map(fn ($ns) => round($ns->avg(fn (Calificacion $k) => $k->notaFinal()), 1));

        $estudiantes = $lista->map(function (User $e) use ($promedios) {
            $m = $this->estudiantes->matriculaVigente($e);
            $grupo = $this->estudiantes->grupoDeMatricula($e, $m);
            $a = $e->acudientes->first();
            $base = $this->persona($e) + [
                'edad'                    => $e->fecha_nacimiento?->age,
                'departamento_nacimiento' => (string) $e->departamento_nacimiento,
                'lugar_nacimiento'        => (string) $e->lugar_nacimiento,
                'direccion'               => (string) $e->direccion,
                'departamento'            => (string) $e->departamento_residencia,
                'ciudad'                  => (string) $e->ciudad_residencia,
                'activo'                  => (bool) $e->activo,
                'acudiente_id'            => $a?->id,
                'parentesco'              => $a?->pivot->parentesco,
                'acudiente'               => $a ? ['id' => $a->id, 'nombre' => trim($a->nombres.' '.$a->apellidos), 'parentesco' => $a->pivot->parentesco, 'telefono' => $a->telefono] : null,
            ];
            if (! $m) {
                return $base + ['estado' => 'sin_matricula', 'programa' => null, 'programa_id' => null, 'grupo' => null, 'pago' => 'sin_matricula',
                    'saldo_vencido' => 0, 'por_pagar_mes' => 0, 'cuotas_vencidas' => []];
            }
            $r = $this->pagos->resumen($m);
            $vencidas = $m->pagos->filter(fn (Pago $p) => $p->porPagar() && $p->estadoActual() === 'vencido')->sortBy('fecha_vencimiento')
                ->map(fn (Pago $p) => ['valor' => (int) round($p->restante()), 'vence' => $p->fecha_vencimiento?->toDateString()])->values();

            return $base + [
                'estado'          => $m->estado,
                'estado_fecha'    => $m->estado_fecha?->toDateString(),
                'estado_motivo'   => $m->estado_motivo,
                'programa_id'     => $m->programa_id,
                'programa'        => $m->programa->nombre,
                'grupo_id'        => $grupo?->id,
                'grupo'           => $grupo?->nombre,
                'fecha_matricula' => $m->fecha_inicio?->toDateString(),
                'plan'            => $m->plan_pago,
                // Sin plan elegido no hay cuotas: el estudiante debe elegirlo desde su panel
                'pago'            => $m->plan_pago ? $r['pago'] : ($r['pago'] === 'vencido' ? 'vencido' : 'sin_plan'),
                'saldo_vencido'   => $r['saldo_vencido'],
                'por_pagar_mes'   => $r['saldo'],
                'cuotas_vencidas' => $vencidas->all(),
                'asistencia'      => $this->estudiantes->porcentajeAsistencia($e, $grupo),
                'promedio'        => $promedios->get($m->id),
            ];
        })->values()->all();

        $recibos = Recaudo::whereIn('matricula_id', $lista->flatMap(fn (User $e) => $e->matriculas->pluck('id')))
            ->with(['matricula:id,estudiante_id,sede_id', 'cobros', 'registradoPor:id,nombres,apellidos,role'])->orderByDesc('fecha_pago')->get()
            ->map(fn (Recaudo $r) => $this->recibo($r))->all();

        return [$estudiantes, $recibos];
    }

    /** Recibo de caja para las fichas y Finanzas. */
    public function recibo(Recaudo $r): array
    {
        $conceptos = $r->cobros->map(fn (Pago $p) => $p->tipo === 'matricula' ? 'Derecho de matrícula' : ($p->tipo === 'periodo' ? 'Cuota del periodo' : 'Cuota mensual'))->unique();

        return [
            'id'         => $r->id,
            'numero'     => $r->recibo,
            'sede_id'    => $r->matricula?->sede_id,
            'est_id'     => $r->matricula?->estudiante_id,
            'fecha'      => $r->fecha_pago?->toDateString(),
            'valor'      => (int) round((float) $r->valor),
            'medio'      => $r->metodo_pago,
            'referencia' => (string) $r->referencia,
            'concepto'   => $conceptos->join(' + ') ?: 'Pago',
            'quien'      => self::quien($r->registradoPor),
            'anulado'    => (bool) $r->anulado_en,
            'motivo'     => $r->motivo_anulacion,
        ];
    }

    /** Quién registró algo: su nombre y si fue el Admin (entrando a la sede) o la secretaría. */
    public static function quien(?User $u): array
    {
        return $u ? ['nombre' => trim($u->nombres.' '.$u->apellidos), 'rol' => $u->role === RolUsuario::AdminGeneral ? 'admin' : 'secretaria']
            : ['nombre' => 'Sin registro', 'rol' => 'secretaria'];
    }

    public function acudientes(): array
    {
        return User::where('role', RolUsuario::Acudiente)->orderBy('nombres')->orderBy('apellidos')->get()
            ->map(fn (User $a) => $this->persona($a) + [
                'profesion'    => (string) $a->profesion,
                'direccion'    => (string) $a->direccion,
                'departamento' => (string) $a->departamento_residencia,
                'ciudad'       => (string) $a->ciudad_residencia,
                'activo'       => (bool) $a->activo,
                'ultimo_acceso'=> $a->ultimo_acceso?->toIso8601String(),
                'creada'       => $a->created_at?->toIso8601String(),
            ])->all();
    }

    /** @return array{0: array, 1: array} docentes y grupos (con su docente) */
    public function docentes(): array
    {
        $grupos = Curso::with(['programa', 'docentes:id'])->withCount('estudiantesActivos')->orderBy('fecha_inicio')->get();
        $porDocente = [];
        $listaGrupos = $grupos->map(function (Curso $c) use (&$porDocente) {
            $doc = $c->docentes->first()?->id;
            if ($doc) {
                $porDocente[$doc][] = $c->sede_id;
            }

            return [
                'id' => $c->id, 'sede_id' => $c->sede_id, 'programa_id' => $c->programa_id, 'nombre' => $c->nombre, 'codigo' => $c->codigo,
                'jornada' => $c->jornada, 'inicio' => $c->fecha_inicio?->toDateString(),
                'fin' => $c->fecha_inicio ? $c->fin()->toDateString() : null,
                'estado' => $this->estadoGrupo($c), 'docente_id' => $doc, 'estudiantes' => $c->estudiantes_activos_count, 'cupo' => $c->programa->cupo_grupo,
            ];
        })->all();

        $docentes = User::where('role', RolUsuario::Docente)->with('docentePerfil')->orderBy('nombres')->orderBy('apellidos')->get()
            ->filter(fn (User $d) => $d->docentePerfil)
            ->map(fn (User $d) => $this->persona($d) + [
                'profesion'    => (string) $d->profesion,
                'especialidad' => (string) $d->titulo_academico,
                'direccion'    => (string) $d->direccion,
                'departamento' => (string) $d->departamento_residencia,
                'ciudad'       => (string) $d->ciudad_residencia,
                'escuela_id'   => $d->docentePerfil->escuela_id,
                'vinculacion'  => $d->docentePerfil->vinculacion,
                'estado'       => $d->docentePerfil->estado,
                'sede_id'      => $d->sede_id,
                // Su sede y las sedes donde tiene grupos
                'sedes'        => collect([$d->sede_id])->merge($porDocente[$d->id] ?? [])->filter()->unique()->values()->all(),
                'ultimo_acceso'=> $d->ultimo_acceso?->toIso8601String(),
            ])->values()->all();

        return [$docentes, $listaGrupos];
    }

    /** en_clases · por_iniciar · finalizado (lo que muestran Docentes y Académico). */
    public function estadoGrupo(Curso $c): string
    {
        if ($c->estado === 'finalizado' || ($c->fecha_inicio && $c->fin()->lte(now()))) {
            return 'finalizado';
        }

        return $c->estado === 'activo' && $c->fecha_inicio?->lte(now()) ? 'en_clases' : 'por_iniciar';
    }

    /** Datos comunes de una persona. */
    private function persona(User $u): array
    {
        return [
            'id'               => $u->id,
            'sede_id'          => $u->sede_id,
            'nombres'          => (string) $u->nombres,
            'apellidos'        => (string) $u->apellidos,
            'genero'           => $u->genero,
            'fecha_nacimiento' => $u->fecha_nacimiento?->toDateString(),
            'tipo_documento'   => $u->tipo_documento,
            'numero_documento' => (string) $u->numero_documento,
            'telefono'         => (string) $u->telefono,
            'email'            => $u->email,
        ];
    }
}
