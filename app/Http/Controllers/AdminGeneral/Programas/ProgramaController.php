<?php

namespace App\Http\Controllers\AdminGeneral\Programas;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Programas\ProgramaRequest;
use App\Models\Academico\Curso;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Services\General\EstructuraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Catálogo de ESAT (Admin): crea y edita programas con su malla y su precio (igual en todas las sedes),
 * los activa o desactiva y decide en qué sedes se ofrecen. La secretaría solo elige cuáles ofrece su sede.
 */
class ProgramaController extends Controller
{
    public function index(EstructuraService $estructura): View
    {
        // Estudiantes activos por programa y sede
        $activos = Matricula::where('estado', 'activo')->selectRaw('programa_id, sede_id, count(distinct estudiante_id) as n')
            ->groupBy('programa_id', 'sede_id')->get()->groupBy('programa_id')
            ->map(fn ($filas) => (object) $filas->pluck('n', 'sede_id')->map(fn ($n) => (int) $n)->all());

        // Grupos vigentes (no finalizados): cupo y jornadas no se pueden dejar por debajo de ellos
        $grupos = Curso::where('estado', '!=', 'finalizado')->withCount('estudiantesActivos')->get()
            ->map(fn (Curso $c) => ['id' => $c->id, 'programa_id' => $c->programa_id, 'sede_id' => $c->sede_id, 'nombre' => $c->nombre,
                'jornada' => $c->jornada, 'estado' => $c->estado, 'estudiantes' => $c->estudiantes_activos_count])->all();

        $docentes = DB::table('docente_perfiles')->selectRaw('escuela_id, count(*) as n')->groupBy('escuela_id')->pluck('n', 'escuela_id');

        return view('admin_general.programas.index', ['datos' => [
            'programas'     => collect($estructura->programas())->map(fn ($p) => $p + ['activos' => $activos->get($p['id'], (object) [])])->all(),
            'escuelas'      => collect($estructura->escuelas())->map(fn ($e) => $e + ['docentes' => (int) ($docentes[$e['id']] ?? 0)])->all(),
            'sedes'         => collect($estructura->sedes())->map(fn ($s) => collect($s)->only(['id', 'nombre', 'ciudad', 'programas']))->all(),
            'grupos'        => $grupos,
            'configuracion' => ['cuota_sistema' => (int) round(Programa::cuotaSistemaMensual())],
            'opciones'      => ['niveles' => Programa::NIVELES, 'modalidades' => Programa::MODALIDADES],
        ]]);
    }

    public function store(ProgramaRequest $request): JsonResponse
    {
        $programa = DB::transaction(function () use ($request) {
            $programa = Programa::create($this->datos($request));
            $this->guardarMalla($programa, $request->validated('modulos'));

            return $programa;
        });
        Auditoria::registrar('crear', $programa, "Creó el programa {$programa->nombre} ({$programa->codigo}) por $ ".$this->plata($programa->precio_total).'.');

        return response()->json(['mensaje' => 'Se creó el programa. Ahora asígnalo a las sedes o deja que cada secretaría lo ofrezca.', 'id' => $programa->id], 201);
    }

    public function update(ProgramaRequest $request, Programa $programa): JsonResponse
    {
        $datos = $this->datos($request);
        $cambios = [];
        if ((int) $programa->precio_total !== (int) $datos['precio_total']) {
            $cambios[] = 'precio de $ '.$this->plata($programa->precio_total).' a $ '.$this->plata($datos['precio_total']);
        }
        if ((int) $programa->matricula !== (int) $datos['matricula']) {
            $cambios[] = 'matrícula de $ '.$this->plata($programa->matricula).' a $ '.$this->plata($datos['matricula']);
        }
        if ($programa->duracion_meses !== (int) $datos['duracion_meses']) {
            $cambios[] = "duración de {$programa->duracion_meses} a {$datos['duracion_meses']} meses (se recalculó el fin de sus grupos)";
        }

        DB::transaction(function () use ($programa, $datos, $request) {
            $programa->update($datos);
            $this->guardarMalla($programa, $request->validated('modulos'));
            // La fecha de fin de cada grupo es su inicio + la duración del programa
            if ($programa->wasChanged('duracion_meses')) {
                $programa->cursos->each(fn (Curso $c) => $c->update(['fecha_fin' => Curso::finPara($c->fecha_inicio, $programa)]));
            }
        });
        $detalle = $cambios ? ': '.implode('; ', $cambios) : '';
        Auditoria::registrar('editar', $programa, "Editó el programa {$programa->nombre} ({$programa->codigo}){$detalle}.");

        return response()->json(['mensaje' => "Se guardaron los cambios del programa{$detalle}."]);
    }

    /** Activo ⇄ inactivo. No se desactiva con grupos vigentes en alguna sede. */
    public function estado(Programa $programa): JsonResponse
    {
        $activar = $programa->estado === 'inactivo';
        if (! $activar && ($n = $programa->cursos()->where('estado', '!=', 'finalizado')->count())) {
            return $this->error("No se puede desactivar: tiene {$n} ".($n === 1 ? 'grupo vigente' : 'grupos vigentes').' en las sedes. Se desactiva cuando terminen.');
        }
        $programa->update(['estado' => $activar ? 'activo' : 'inactivo']);
        Auditoria::registrar($activar ? 'activar' : 'desactivar', $programa, ($activar ? 'Activó' : 'Desactivó')." el programa {$programa->nombre} ({$programa->codigo}).");

        return response()->json(['mensaje' => $activar ? 'Se activó el programa.' : 'Se desactivó el programa.']);
    }

    /** Ofrece o deja de ofrecer el programa en una sede (no se quita con grupos vigentes allí). */
    public function oferta(Programa $programa, Sede $sede): JsonResponse
    {
        $ofrece = $programa->sedes()->where('sedes.id', $sede->id)->exists();
        $corto = str_replace('Técnico Laboral en ', '', $programa->nombre);
        if ($ofrece) {
            $n = $programa->cursos()->where('sede_id', $sede->id)->where('estado', '!=', 'finalizado')->count();
            if ($n) {
                return $this->error("No se puede quitar de {$sede->nombre}: tiene {$n} ".($n === 1 ? 'grupo vigente' : 'grupos vigentes').'. Se quita cuando terminen.');
            }
            $programa->sedes()->detach($sede->id);
            Auditoria::registrar('editar', $programa, "Quitó el programa {$programa->nombre} de la sede {$sede->nombre}.", $sede->id);

            return response()->json(['mensaje' => "La sede {$sede->nombre} ya no ofrece {$corto}."]);
        }
        if ($programa->estado === 'inactivo') {
            return $this->error('El programa está inactivo: actívalo antes de ofrecerlo en una sede.');
        }
        $programa->sedes()->attach($sede->id);
        Auditoria::registrar('editar', $programa, "Asignó el programa {$programa->nombre} a la sede {$sede->nombre}.", $sede->id);

        return response()->json(['mensaje' => "La sede {$sede->nombre} ya puede abrir grupos de {$corto}."]);
    }

    /* ── Auxiliares ── */

    private function datos(ProgramaRequest $request): array
    {
        $datos = collect($request->validated())->except('modulos')->all();
        $datos['duracion_periodos'] = Programa::calcularPeriodos($datos['duracion_meses'], $datos['tipo_periodo']);
        $datos['jornadas'] = array_values(array_intersect(Programa::JORNADAS, $datos['jornadas']));

        return $datos;
    }

    /** Deja la malla como llegó, en orden: actualiza los módulos que ya existen (conservan su id), crea o borra el resto. */
    private function guardarMalla(Programa $programa, array $modulos): void
    {
        $actuales = $programa->modulos()->get()->values();
        foreach (array_values($modulos) as $i => $m) {
            $datos = ['nombre' => $m['nombre'], 'horas' => $m['horas'], 'orden' => $i + 1];
            isset($actuales[$i]) ? $actuales[$i]->update($datos) : $programa->modulos()->create($datos);
        }
        $actuales->slice(count($modulos))->each->delete();
        $programa->unsetRelation('modulos');
    }

    private function plata(mixed $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }

    private function error(string $mensaje): JsonResponse
    {
        return response()->json(['message' => $mensaje], 422);
    }
}
