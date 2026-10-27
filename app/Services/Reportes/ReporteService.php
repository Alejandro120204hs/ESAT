<?php

namespace App\Services\Reportes;

use App\Models\Academico\Calificacion;
use App\Models\Academico\Curso;
use App\Models\Academico\CursoSesion;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\Institucional\Sede;
use App\Models\User;
use App\Services\Academico\AsistenciaService;
use App\Services\Academico\CalificacionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel Admin › Reportes de una sede: los datos con sus fechas para que reportes.js arme cada documento como estaba
 * al cierre de cualquier mes (matrículas y su cambio de estado, grupos con sus clases y las fechas de cada módulo,
 * notas con su fecha, asistencia por clase y cada cobro con los pagos que recibió). No guarda nada.
 * La asistencia va por matrícula (no por estudiante): quien vuelve con una matrícula nueva no mezcla la del grupo viejo.
 */
class ReporteService
{
    private const LETRA = ['presente' => 'P', 'tarde' => 'T', 'ausente' => 'A', 'excusa' => 'E'];

    public function __construct(
        private AsistenciaService $asistencia,
        private CalificacionService $calificaciones,
    ) {}

    public function datosSede(int $sedeId): array
    {
        $sede = Sede::findOrFail($sedeId);
        $cursos = Curso::where('sede_id', $sedeId)->with(['programa.modulos', 'docentes', 'sesiones'])->orderBy('id')->get();
        $matriculas = Matricula::where('sede_id', $sedeId)->where('estado', '!=', 'anulado')
            ->with(['estudiante.acudientes'])->orderBy('id')->get();

        $grupos = $cursos->map(fn (Curso $c) => $this->grupo($c))->values();

        return [
            'sede'        => ['nombre' => $sede->nombre, 'ciudad' => $sede->ciudad],
            'grupos'      => $grupos,
            'estudiantes' => $matriculas->pluck('estudiante')->unique('id')->map(fn (User $e) => $this->estudiante($e))->values(),
            'matriculas'  => $matriculas->map(fn (Matricula $m) => [
                'id'           => $m->id,
                'estudiante_id' => $m->estudiante_id,
                'programa_id'  => $m->programa_id,
                'grupo_id'     => $m->curso_id,
                'estado'       => $m->estado,
                'fecha'        => $m->fecha_inicio?->toDateString(),
                'estado_fecha' => $m->estado_fecha?->toDateString(),
                'motivo'       => $m->estado_motivo,
            ])->values(),
            'asistencia'  => $this->asistencias($grupos, $matriculas),
            'notas'       => Calificacion::whereIn('matricula_id', $matriculas->pluck('id'))->orderBy('id')->get()->map(fn (Calificacion $k) => [
                'matricula_id' => $k->matricula_id,
                'modulo_id'    => $k->programa_modulo_id,
                'nota'         => (float) $k->nota,
                'fecha'        => $k->fecha_nota->toDateString(),
                'hab'          => $k->habilitacion !== null ? (float) $k->habilitacion : null,
                'fecha_hab'    => $k->fecha_habilitacion?->toDateString(),
            ])->values(),
            'cobros'      => $this->cobros($matriculas),
        ];
    }

    /** Grupo con sus clases dictadas, su horario y las fechas de cada módulo (las mismas de Calificaciones). */
    private function grupo(Curso $c): array
    {
        $tieneHorario = $c->sesiones->isNotEmpty();
        $modulos = $tieneHorario ? collect($this->calificaciones->fechasModulos($c))->keyBy('id') : collect();
        $docente = $c->docentes->first();

        return [
            'id'          => $c->id,
            'nombre'      => $c->nombre,
            'programa_id' => $c->programa_id,
            'programa'    => str_replace('Técnico Laboral en ', '', $c->programa->nombre),
            'jornada'     => $c->jornada,
            'estado'      => $c->estado,
            'inicio'      => $c->fecha_inicio?->toDateString(),
            'fin'         => Curso::finPara($c->fecha_inicio, $c->programa)->toDateString(),
            'horario'     => $tieneHorario ? $this->horario($c) : '',
            'docente'     => $docente ? trim($docente->nombres.' '.$docente->apellidos) : null,
            'fechas'      => $tieneHorario ? $this->asistencia->fechasDictadas($c) : [],
            'modulos'     => $c->programa->modulos->sortBy('orden')->values()->map(fn ($m) => [
                'id'     => $m->id,
                'nombre' => $m->nombre,
                'horas'  => $m->horas,
                'ini'    => $modulos->get($m->id)['ini'] ?? null,
                'fin'    => $modulos->get($m->id)['fin'] ?? null,
            ])->all(),
        ];
    }

    /** "Lun · Mié · Vie  08:00 – 12:00", igual que en Asistencia. */
    private function horario(Curso $c): string
    {
        $dias = $c->sesiones->map(fn (CursoSesion $s) => Curso::DIA_CORTO[$s->dia])->implode(' · ');
        $horas = $c->sesiones->map(fn (CursoSesion $s) => $s->inicio().' – '.$s->fin())->unique()->implode(' / ');

        return $dias.'  '.$horas;
    }

    private function estudiante(User $e): array
    {
        $a = $e->acudientes->first();

        return [
            'id'         => $e->id,
            'tipo_doc'   => $e->tipo_documento,
            'documento'  => $e->numero_documento,
            'nombres'    => $e->nombres,
            'apellidos'  => $e->apellidos,
            'genero'     => $e->genero,
            'nacimiento' => $e->fecha_nacimiento?->toDateString(),
            'municipio'  => $e->ciudad_residencia,
            'celular'    => $this->digitos($e->telefono),
            'acudiente'  => $a ? ['nombre' => trim($a->nombres.' '.$a->apellidos), 'parentesco' => $a->pivot->parentesco, 'celular' => $this->digitos($a->telefono)] : null,
        ];
    }

    /**
     * Un código por clase del grupo de cada matrícula (P, T, A, E o - sin registrar), alineado con grupos[].fechas,
     * con el motivo de cada excusa. También de los grupos finalizados.
     */
    private function asistencias(Collection $grupos, Collection $matriculas): Collection
    {
        $regs = DB::table('asistencias')->whereIn('curso_id', $grupos->pluck('id'))
            ->get(['curso_id', 'estudiante_id', 'fecha', 'estado', 'motivo'])
            ->groupBy(fn ($r) => $r->curso_id.'-'.$r->estudiante_id);

        $out = collect();
        foreach ($grupos as $g) {
            $idx = array_flip($g['fechas']);
            foreach ($matriculas->where('curso_id', $g['id']) as $m) {
                $rs = $regs->get($g['id'].'-'.$m->estudiante_id);
                if (! $rs) {
                    continue;
                }
                $cod = str_repeat('-', count($g['fechas']));
                $motivos = [];
                foreach ($rs as $r) {
                    $f = substr((string) $r->fecha, 0, 10);
                    if (! isset($idx[$f])) {
                        continue;
                    }
                    $cod[$idx[$f]] = self::LETRA[$r->estado];
                    if ($r->estado === 'excusa' && $r->motivo) {
                        $motivos[$idx[$f]] = $r->motivo;
                    }
                }
                $out->push(['matricula_id' => $m->id, 'estudiante_id' => $m->estudiante_id, 'grupo_id' => $g['id'], 'cod' => $cod, 'motivos' => (object) $motivos]);
            }
        }

        return $out->values();
    }

    /** Cobros vigentes (sin los anulados) con cada pago que recibieron y su fecha (recaudos sin anular). */
    private function cobros(Collection $matriculas): Collection
    {
        return Pago::whereIn('matricula_id', $matriculas->pluck('id'))->where('estado', '!=', 'anulado')->whereNotNull('fecha_vencimiento')
            ->with(['recaudos' => fn ($q) => $q->whereNull('anulado_en')])->orderBy('id')->get()
            ->map(fn (Pago $p) => [
                'matricula_id' => $p->matricula_id,
                'concepto'     => $p->tipo === 'matricula' ? 'Derecho de matrícula' : 'Cuota '.$p->numero_cuota,
                'vence'        => $p->fecha_vencimiento->toDateString(),
                'valor'        => (int) round((float) $p->monto_total),
                'pagos'        => $p->recaudos->map(fn ($r) => [$r->fecha_pago->toDateString(), (int) round((float) $r->pivot->monto)])->values()->all(),
            ])->values();
    }

    private function digitos(?string $tel): string
    {
        return preg_replace('/\D/', '', (string) $tel);
    }
}
