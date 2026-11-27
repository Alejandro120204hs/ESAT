<?php

namespace App\Services\Academico;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Reglas de negocio del estudiante (panel Admin › Estudiantes): registro, contacto de emergencia
 * y el resumen de pagos/asistencia de su matrícula que se consulta en la ficha.
 * Las validaciones de entrada viven en los Form Requests de Admin/Estudiantes.
 */
class EstudianteService
{
    /**
     * Registra al estudiante: su cuenta (clave inicial = documento), su lista de documentos y su contacto de emergencia.
     * No lo matricula (eso es de Matrículas).
     */
    public function registrar(array $datos, array $contacto, int $sedeId): User
    {
        return DB::transaction(function () use ($datos, $contacto, $sedeId) {
            $estudiante = User::create($datos + [
                'role'     => RolUsuario::Estudiante,
                'sede_id'  => $sedeId,
                'password' => Hash::make($datos['numero_documento']),
                'activo'   => true,
            ]);

            $estudiante->documentos()->createMany(
                array_map(fn ($d) => ['documento' => $d], EstudianteDocumento::REQUERIDOS)
            );
            $this->guardarContacto($estudiante, $contacto);

            return $estudiante;
        });
    }

    /** Crea o reemplaza el contacto de emergencia (nombre, celular y parentesco) del estudiante. */
    public function guardarContacto(User $estudiante, array $contacto): void
    {
        $estudiante->contactoEmergencia()->updateOrCreate([], [
            'nombre'     => $contacto['nombre'],
            'celular'    => $contacto['celular'],
            'parentesco' => $contacto['parentesco'],
        ]);
        $estudiante->unsetRelation('contactoEmergencia');
    }

    /**
     * Matrícula que se muestra: la activa o, si no hay, la más reciente (las anuladas no cuentan).
     * Se consulta en Estudiantes, pero se gestiona en Matrículas.
     * Espera cargada la relación matriculas.
     */
    public function matriculaVigente(User $estudiante): ?Matricula
    {
        return $estudiante->matriculas->where('estado', '!=', 'anulado')->sortByDesc(fn (Matricula $m) => [$m->estado === 'activo', $m->id])->first();
    }

    /**
     * Todas las matrículas del estudiante, de la más reciente a la más antigua: su recorrido
     * en ESAT (programas terminados, retiros, la actual). Espera cargadas matriculas con
     * programa, curso y pagos.
     */
    public function trayectoria(User $estudiante): array
    {
        $pagos = app(PagoService::class);

        return $estudiante->matriculas->where('estado', '!=', 'anulado')->sortByDesc(fn (Matricula $m) => [$m->fecha_inicio->timestamp, $m->id])
            ->map(fn (Matricula $m) => [
                'id'            => $m->id,
                'codigo'        => $m->codigo,
                'programa'      => $m->programa->nombre,
                'grupo'         => $m->curso ? $m->curso->nombre.' · '.$m->curso->codigo : null,
                'jornada'       => $m->curso?->jornada,
                'estado'        => $m->estado,
                'estado_fecha'  => $m->estado_fecha?->format('Y-m-d'),
                'estado_motivo' => $m->estado_motivo,
                'inicio'        => $m->fecha_inicio->format('Y-m-d'),
                'fin'           => $m->curso?->fecha_fin?->format('Y-m-d'),
                'debe'          => $pagos->resumen($m)['por_pagar'],
            ])->values()->all();
    }

    /** Grupo de esa matrícula (espera cargada la relación cursosComoEstudiante). */
    public function grupoDeMatricula(User $estudiante, ?Matricula $matricula): ?Curso
    {
        if (! $matricula?->curso_id) {
            return null;
        }

        return $estudiante->cursosComoEstudiante->firstWhere('id', $matricula->curso_id);
    }

    /**
     * Resumen de pagos de la matrícula. Sin plan elegido no hay cuotas.
     * vencido > pendiente > al día (lo más grave manda).
     */
    public function resumenPagos(Matricula $matricula): array
    {
        // Una sola regla de cobro para Matrículas y Estudiantes
        return app(PagoService::class)->resumen($matricula);
    }

    /**
     * Porcentaje de asistencia en su grupo: presente o tarde cuentan como asistió;
     * las excusas no cuentan en contra. null si todavía no hay clases registradas.
     */
    public function porcentajeAsistencia(User $estudiante, ?Curso $curso, ?Matricula $matricula = null): ?int
    {
        if (! $curso) {
            return null;
        }
        $registros = $estudiante->asistencias->where('curso_id', $curso->id);
        // Con la matrícula: lo registrado en cada grupo por el que pasó, solo en las fechas en que contaba allí
        if ($matricula) {
            $matricula->loadMissing('grupos');
            $cursos = Curso::whereIn('id', $matricula->periodos()->pluck('curso_id')->push($curso->id)->unique())->get()->keyBy('id');
            $registros = $estudiante->asistencias->filter(function ($a) use ($matricula, $cursos) {
                $c = $cursos->get($a->curso_id);

                return $c && Matricula::enVentanas($matricula->ventanasEn($c), $a->fecha->toDateString());
            });
        }

        // Misma regla que el panel Asistencia
        return AsistenciaService::porcentaje(
            $registros->whereIn('estado', ['presente', 'tarde'])->count(),
            $registros->where('estado', 'ausente')->count(),
        );
    }
}
