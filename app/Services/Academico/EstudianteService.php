<?php

namespace App\Services\Academico;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Academico\EstudianteDocumento;
use App\Models\Finanzas\Matricula;
use App\Models\User;
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
     * Resumen de pagos de la matrícula. Sin plan elegido no hay cuotas.
     * vencido > pendiente > al día (lo más grave manda).
     */
    public function resumenPagos(Matricula $matricula): array
    {
        $pagos = $matricula->pagos;
        $programa = $matricula->programa;

        $total = match ($matricula->plan_pago) {
            'mensual' => $programa->duracion_meses,
            'periodo' => $programa->duracion_periodos,
            default   => null,
        };
        $estado = $pagos->contains('estado', 'vencido') ? 'vencido'
            : ($pagos->contains('estado', 'pendiente') ? 'pendiente' : 'al_dia');

        return [
            'plan_pago'      => $matricula->plan_pago,
            'pago'           => $estado,
            'cuotas_total'   => $total,
            'cuotas_pagadas' => $pagos->where('estado', 'pagado')->count(),
            'saldo'          => (int) round($pagos->whereIn('estado', ['pendiente', 'vencido'])->sum('monto_total')),
            'ultimo_pago'    => $pagos->where('estado', 'pagado')->max('fecha_pago')?->format('Y-m-d'),
        ];
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
        $validas   = $registros->where('estado', '!=', 'excusa');
        if ($validas->isEmpty()) {
            return null;
        }

        return (int) round($validas->whereIn('estado', ['presente', 'tarde'])->count() / $validas->count() * 100);
    }
}
