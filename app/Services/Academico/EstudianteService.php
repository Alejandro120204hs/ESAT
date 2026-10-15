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
 * Reglas de negocio del estudiante (panel Admin › Estudiantes): registro, acudiente
 * y el resumen de pagos/asistencia de su matrícula que se consulta en la ficha.
 * Las validaciones de entrada viven en los Form Requests de Admin/Estudiantes.
 */
class EstudianteService
{
    /** Parentescos que se pueden registrar entre acudiente y estudiante. */
    public const PARENTESCOS = ['Madre', 'Padre', 'Abuelo(a)', 'Tío(a)', 'Hermano(a)', 'Otro'];

    /**
     * Registra al estudiante: su cuenta (clave inicial = documento), su lista de
     * documentos y, si viene, su acudiente. No lo matricula (eso es de Matrículas).
     */
    public function registrar(array $datos, ?array $acudiente, ?string $parentesco, int $sedeId): User
    {
        return DB::transaction(function () use ($datos, $acudiente, $parentesco, $sedeId) {
            $estudiante = User::create($datos + [
                'role'     => RolUsuario::Estudiante,
                'sede_id'  => $sedeId,
                'password' => Hash::make($datos['numero_documento']),
                'activo'   => true,
            ]);

            $estudiante->documentos()->createMany(
                array_map(fn ($d) => ['documento' => $d], EstudianteDocumento::REQUERIDOS)
            );

            if ($acudiente) {
                $this->vincularAcudiente($estudiante, $acudiente, $parentesco, $sedeId);
            }

            return $estudiante;
        });
    }

    /**
     * Deja a $acudiente como el acudiente del estudiante (reemplaza al anterior).
     * $acudiente es ['id' => …] si ya existe o los datos para registrarlo
     * (clave inicial = su cédula). Devuelve el acudiente.
     */
    public function vincularAcudiente(User $estudiante, array $acudiente, string $parentesco, int $sedeId): User
    {
        return DB::transaction(function () use ($estudiante, $acudiente, $parentesco, $sedeId) {
            $usuario = isset($acudiente['id'])
                ? User::findOrFail($acudiente['id'])
                : User::create([
                    'role'             => RolUsuario::Acudiente,
                    'sede_id'          => $sedeId,
                    'nombres'          => $acudiente['nombres'],
                    'apellidos'        => $acudiente['apellidos'],
                    'tipo_documento'   => 'CC',
                    'numero_documento' => $acudiente['documento'],
                    'telefono'         => $acudiente['telefono'],
                    'email'            => $acudiente['email'],
                    'password'         => Hash::make($acudiente['documento']),
                    'activo'           => true,
                ]);

            $estudiante->acudientes()->sync([$usuario->id => ['parentesco' => $parentesco]]);

            return $usuario;
        });
    }

    /**
     * Matrícula que se muestra: la activa o, si no hay, la más reciente (las anuladas no cuentan).
     * Se consulta en Estudiantes y Acudientes, pero se gestiona en Matrículas.
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
        // Una sola regla de cobro para Matrículas, Estudiantes y Acudientes
        return app(PagoService::class)->resumen($matricula);
    }

    /**
     * Porcentaje de asistencia en su grupo: presente o tarde cuentan como asistió;
     * las excusas no cuentan en contra. null si todavía no hay clases registradas.
     */
    public function porcentajeAsistencia(User $estudiante, ?Curso $curso): ?int
    {
        if (! $curso) {
            return null;
        }
        $registros = $estudiante->asistencias->where('curso_id', $curso->id);

        // Misma regla que el panel Asistencia
        return AsistenciaService::porcentaje(
            $registros->whereIn('estado', ['presente', 'tarde'])->count(),
            $registros->where('estado', 'ausente')->count(),
        );
    }
}
