<?php

namespace App\Services\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\CursoSesion;
use App\Models\Academico\ProgramaModulo;
use App\Models\Finanzas\Matricula;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel Docente: los grupos que el docente tiene a cargo (curso_docente) con lo que leen sus cuatro
 * pantallas, y lo único que escribe: la asistencia de una clase y las notas de un módulo.
 * Usa las mismas reglas de AsistenciaService y CalificacionService, así lo que ve el docente cuadra con
 * Admin › Asistencia y Admin › Calificaciones.
 * - Registra y corrige mientras el grupo no esté finalizado; un grupo finalizado es solo consulta.
 * - Asistencia: solo días de clase del horario, entre el inicio y el fin del grupo, y nunca futuros. La lista
 *   de una clase son las matrículas del grupo vigentes ese día (desde que se matriculó hasta que se aplazó,
 *   retiró o graduó).
 * - Notas: por matrícula y módulo, de 1.0 a 5.0 con un decimal; no en módulos por iniciar; habilitación solo en
 *   un módulo terminado y con nota menor a 3.0; solo matrículas activas y módulos que cursó en el grupo.
 */
class PanelDocenteService
{
    public const DIA_ISO = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];

    private const DIA_CORTO = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];

    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function __construct(private CalificacionService $calificaciones) {}

    /** Grupos del docente con horario, cargados para el panel: vigentes primero. */
    public function cursos(User $docente): Collection
    {
        return $docente->cursosComoDocente()
            ->whereHas('sesiones')
            ->with([
                'programa.escuela', 'programa.modulos', 'sesiones', 'historialSesiones',
                'matriculas' => fn ($q) => $q->where('estado', '!=', 'anulado')->with('estudiante.acudientes'),
            ])
            ->get()
            ->sortBy(fn (Curso $c) => [$c->estado === 'finalizado' ? 1 : 0, $c->programa->nombre, $c->nombre])
            ->values();
    }

    /** Un grupo del docente, cargado igual que en cursos(); null si no es suyo o no tiene horario. */
    public function curso(User $docente, int $cursoId): ?Curso
    {
        return $this->cursos($docente)->firstWhere('id', $cursoId);
    }

    /**
     * Lo que leen las cuatro pantallas (window.DOC_SERVIDOR; panel docente › datos.js arma DOC_DATOS):
     * hoy, grupos[] con horario, módulos (fechas calculadas) y estudiantes, registros de asistencia
     * {grupo: {fecha: {estudiante: {estado, motivo}}}} y notas {grupo: {módulo: {estudiante: {n, h}}}}.
     */
    public function datos(User $docente, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();
        $cursos = $this->cursos($docente);

        $registros = [];
        Asistencia::whereIn('curso_id', $cursos->pluck('id'))
            ->get(['curso_id', 'estudiante_id', 'fecha', 'estado', 'motivo'])
            ->each(function (Asistencia $a) use (&$registros) {
                $registros[$a->curso_id][$a->fecha->toDateString()][$a->estudiante_id] = ['estado' => $a->estado, 'motivo' => $a->motivo];
            });

        $grupos = [];
        $notas = [];
        foreach ($cursos as $curso) {
            $estudiantes = $this->estudiantes($curso);
            $grupos[] = $this->grupo($curso, $estudiantes, $ahora);
            $notas[$curso->id] = $this->notasDelGrupo($estudiantes);
        }

        return [
            'hoy'       => $ahora->toDateString(),
            'grupos'    => $grupos,
            'registros' => (object) $registros,
            'notas'     => (object) $notas,
        ];
    }

    /**
     * Estudiantes del grupo: una fila por estudiante con su matrícula en el grupo (la más reciente si volvió).
     * desde = cuando empezó a contar (su matrícula o el inicio del grupo); hasta = cuando se aplazó, retiró o
     * graduó. Quien salió antes de empezar no aparece (como en Admin › Asistencia).
     */
    public function estudiantes(Curso $curso): Collection
    {
        $inicio = $curso->fecha_inicio->toDateString();

        return $curso->matriculas->sortByDesc('id')->unique('estudiante_id')
            ->map(function (Matricula $m) use ($inicio) {
                $e = $m->estudiante;
                $desde = max($m->fecha_inicio->toDateString(), $inicio);
                $hasta = $m->estado !== 'activo' ? $m->estado_fecha?->toDateString() : null;
                if ($hasta && $hasta <= $desde) {
                    return null;
                }
                $edad = $e->fecha_nacimiento?->age;
                $acudiente = $e->acudientes->first();

                return [
                    'id'          => $e->id,
                    'matriculaId' => $m->id,
                    'nombres'     => $e->nombres,
                    'apellidos'   => $e->apellidos,
                    'tipoDoc'     => $e->tipo_documento,
                    'documento'   => $e->numero_documento,
                    'edad'        => $edad,
                    'menor'       => $edad !== null && $edad < 18,
                    'telefono'    => preg_replace('/\D/', '', (string) $e->telefono),
                    'acudiente'   => $acudiente ? [
                        'nombre'     => trim($acudiente->nombres.' '.$acudiente->apellidos),
                        'parentesco' => $acudiente->pivot->parentesco,
                        'telefono'   => preg_replace('/\D/', '', (string) $acudiente->telefono),
                    ] : null,
                    'matricula'   => [
                        'codigo'      => $m->codigo,
                        'fecha'       => $m->fecha_inicio->toDateString(),
                        'estado'      => $m->estado,
                        'estadoFecha' => $hasta,
                    ],
                    'desde'       => $desde,
                    'hasta'       => $hasta,
                ];
            })
            ->filter()
            ->sortBy(fn (array $e) => [$e['apellidos'], $e['nombres']])
            ->values();
    }

    /** Estudiantes que van en la lista de esa clase (vigentes ese día). */
    public function planilla(Curso $curso, string $fecha): Collection
    {
        return $this->estudiantes($curso)
            ->filter(fn (array $e) => $fecha >= $e['desde'] && ! ($e['hasta'] && $fecha >= $e['hasta']))
            ->values();
    }

    /** ¿Ese día hay clase en el horario del grupo, entre su inicio y su fin? */
    public function esDiaDeClase(Curso $curso, string $fecha): bool
    {
        $d = Carbon::parse($fecha)->startOfDay();
        $fin = $curso->fin();

        return $d->gte($curso->fecha_inicio->copy()->startOfDay()) && $d->lt($fin) && $curso->sesionEn($d) !== null;
    }

    /** El módulo de la malla del grupo con sus fechas y estado (terminado · en_curso · por_iniciar), o null. */
    public function moduloDelGrupo(Curso $curso, int $moduloId, ?CarbonInterface $ahora = null): ?array
    {
        return collect($this->calificaciones->fechasModulos($curso, $ahora))->firstWhere('id', $moduloId);
    }

    /** registros: {estudiante_id: {estado, motivo}} ya validados (AsistenciaRequest). Devuelve el mensaje para el docente. */
    public function guardarAsistencia(User $docente, Curso $curso, string $fecha, array $registros): string
    {
        $corrige = false;
        DB::transaction(function () use ($docente, $curso, $fecha, $registros, &$corrige) {
            $existentes = Asistencia::where('curso_id', $curso->id)->whereDate('fecha', $fecha)->get()->keyBy('estudiante_id');
            $corrige = $existentes->isNotEmpty();

            foreach ($registros as $estudianteId => $x) {
                $a = $existentes->get($estudianteId) ?? new Asistencia(['curso_id' => $curso->id, 'estudiante_id' => $estudianteId, 'fecha' => $fecha]);
                $a->estado = $x['estado'];
                $a->motivo = $x['estado'] === 'excusa' ? $x['motivo'] : null;
                if (! $a->exists || $a->isDirty()) {
                    $a->registrado_por = $docente->id;
                    $a->save();
                }
            }

            $cuenta = collect($registros)->countBy('estado');
            $partes = array_filter([
                $this->cuantos($cuenta['presente'] ?? 0, 'presente', 'presentes'),
                $this->cuantos($cuenta['tarde'] ?? 0, 'llegó tarde', 'llegaron tarde'),
                $this->cuantos($cuenta['ausente'] ?? 0, 'ausente', 'ausentes'),
                $this->cuantos($cuenta['excusa'] ?? 0, 'con excusa', 'con excusa'),
            ]);
            Auditoria::registrar($corrige ? 'editar' : 'crear', $curso, sprintf(
                '%s la asistencia de %s · %s del %s: %s.',
                $corrige ? 'Corrigió' : 'Registró', $curso->programa->nombre, $curso->nombre, $this->fechaTexto($fecha),
                $this->lista($partes),
            ));
        });

        return ($corrige ? 'Corregiste' : 'Guardaste').' la asistencia del '.$this->fechaTexto($fecha).'.';
    }

    /**
     * notas: {estudiante_id: {n, h}} ya validadas (NotasRequest). n null borra la nota (y su habilitación).
     * La fecha de la nota y la de la habilitación son las del día en que cambian.
     */
    public function guardarNotas(User $docente, Curso $curso, ProgramaModulo $modulo, array $notas): string
    {
        $matriculas = $this->estudiantes($curso)->pluck('matriculaId', 'id');
        $hoy = now()->toDateString();

        DB::transaction(function () use ($docente, $curso, $modulo, $notas, $matriculas, $hoy) {
            $existentes = Calificacion::where('programa_modulo_id', $modulo->id)
                ->whereIn('matricula_id', $matriculas->values())->get()->keyBy('matricula_id');
            $puestas = 0;
            $borradas = 0;

            foreach ($notas as $estudianteId => $x) {
                $matriculaId = $matriculas[$estudianteId];
                $c = $existentes->get($matriculaId);
                if ($x['n'] === null) {
                    if ($c) {
                        $c->delete();
                        $borradas++;
                    }
                    continue;
                }
                $c ??= new Calificacion(['matricula_id' => $matriculaId, 'programa_modulo_id' => $modulo->id]);
                if ($c->nota === null || round($c->nota, 1) !== $x['n']) {
                    $c->nota = $x['n'];
                    $c->fecha_nota = $hoy;
                }
                $hAntes = $c->habilitacion === null ? null : round($c->habilitacion, 1);
                if ($hAntes !== $x['h']) {
                    $c->habilitacion = $x['h'];
                    $c->fecha_habilitacion = $x['h'] === null ? null : $hoy;
                }
                if (! $c->exists || $c->isDirty()) {
                    $c->curso_id = $curso->id;
                    $c->registrado_por = $docente->id;
                    $c->save();
                    $puestas++;
                }
            }

            if ($puestas || $borradas) {
                $partes = array_filter([
                    $puestas ? 'puso o corrigió '.$this->cuantos($puestas, 'nota', 'notas') : null,
                    $borradas ? 'borró '.$this->cuantos($borradas, 'nota', 'notas') : null,
                ]);
                Auditoria::registrar('editar', $curso, sprintf(
                    'Notas de %s en %s · %s: %s.',
                    $modulo->nombre, $curso->programa->nombre, $curso->nombre, $this->lista($partes),
                ));
            }
        });

        return 'Guardaste las notas de '.$modulo->nombre.'.';
    }

    private function grupo(Curso $curso, Collection $estudiantes, CarbonInterface $ahora): array
    {
        $fin = $curso->fin();

        return [
            'id'           => $curso->id,
            'codigo'       => $curso->codigo,
            'nombre'       => $curso->nombre,
            'programa'     => $curso->programa->nombre,
            'escuela'      => $curso->programa->escuela?->nombre,
            // Color de la escuela en el horario semanal (el mismo de Admin › Horarios)
            'sigla'        => mb_strtolower($curso->programa->escuela?->sigla ?? ''),
            'jornada'      => $curso->jornada,
            'estado'       => $curso->estado,
            'soloConsulta' => $curso->estado === 'finalizado',
            'inicio'       => $curso->fecha_inicio->toDateString(),
            'fin'          => $fin->toDateString(),
            'cupo'         => $curso->programa->cupo_grupo,
            'duracion'     => $curso->programa->duracion_meses,
            'horario'      => $curso->sesiones->map(fn (CursoSesion $s) => [
                'dia' => $s->dia, 'diaIso' => self::DIA_ISO[$s->dia], 'ini' => $s->inicio(), 'fin' => $s->fin(),
            ])->sortBy('diaIso')->values(),
            // Todos los horarios que ha tenido, con desde/hasta: datos.js arma las clases con el que regía cada día
            'historial'    => $curso->historialSesiones->map(fn (CursoSesion $s) => [
                'dia' => $s->dia, 'diaIso' => self::DIA_ISO[$s->dia], 'ini' => $s->inicio(), 'fin' => $s->fin(),
                'desde' => $s->desde?->toDateString(), 'hasta' => $s->hasta?->toDateString(),
            ])->values(),
            'modulos'      => collect($this->calificaciones->fechasModulos($curso, $ahora))
                ->map(fn (array $m) => array_intersect_key($m, array_flip(['id', 'nombre', 'horas', 'ini', 'fin', 'estado'])))
                ->values(),
            'estudiantes'  => $estudiantes,
        ];
    }

    /** {módulo: {estudiante: {n, h}}} con las notas de las matrículas del grupo (la nota sigue a la matrícula). */
    private function notasDelGrupo(Collection $estudiantes): object
    {
        $deMatricula = $estudiantes->pluck('id', 'matriculaId');
        $out = [];
        Calificacion::whereIn('matricula_id', $deMatricula->keys())
            ->get(['matricula_id', 'programa_modulo_id', 'nota', 'habilitacion'])
            ->each(function (Calificacion $c) use (&$out, $deMatricula) {
                $out[$c->programa_modulo_id][$deMatricula[$c->matricula_id]] = ['n' => $c->nota, 'h' => $c->habilitacion];
            });

        return (object) $out;
    }

    /** "1 presente", "3 ausentes"; null si es 0 (no se nombra). */
    private function cuantos(int $n, string $uno, string $varios): ?string
    {
        return $n ? $n.' '.($n === 1 ? $uno : $varios) : null;
    }

    /** "a, b y c". */
    private function lista(array $partes): string
    {
        $partes = array_values($partes);
        $ultima = array_pop($partes);

        return $partes ? implode(', ', $partes).' y '.$ultima : (string) $ultima;
    }

    /** "lun 9 nov" (con el año si no es el actual). */
    private function fechaTexto(string $fecha): string
    {
        $f = Carbon::parse($fecha);

        return self::DIA_CORTO[$f->dayOfWeekIso].' '.$f->day.' '.self::MESES[$f->month - 1].($f->year !== now()->year ? ' '.$f->year : '');
    }
}
