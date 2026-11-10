<?php

namespace App\Services\General;

use App\Enums\RolUsuario;
use App\Models\Sistema\Auditoria;
use App\Models\User;

/** Auditoría de todo ESAT para el panel del Admin (lo que hicieron las secretarías, los docentes y el Admin). */
class SupervisionService
{
    /** Nombre en pantalla de cada entidad auditada (class_basename del modelo). */
    private const ENTIDADES = [
        'Matricula' => 'Matrícula', 'Pago' => 'Pago', 'Recaudo' => 'Pago', 'Devolucion' => 'Devolución', 'Egreso' => 'Egreso',
        'Programa' => 'Programa', 'Escuela' => 'Escuela', 'Sede' => 'Sede', 'Curso' => 'Grupo', 'CursoSesion' => 'Horario',
        'Comunicado' => 'Comunicado', 'ForoTema' => 'Foro', 'ForoMensaje' => 'Foro', 'Configuracion' => 'Configuración',
        'Calificacion' => 'Nota', 'Asistencia' => 'Asistencia',
    ];

    /** Rol de quien actuó, como lo agrupa la pantalla. */
    private const ROLES = [
        'admin_general' => 'admin', 'superadmin' => 'admin', 'admin' => 'secretaria', 'docente' => 'docente',
        'estudiante' => 'estudiante', 'acudiente' => 'acudiente',
    ];

    /** Eventos del último año (los más recientes primero, hasta 5.000). */
    public function auditoria(): array
    {
        $eventos = Auditoria::with('usuario:id,nombres,apellidos,role,sede_id')
            ->where('created_at', '>=', now()->subYear())->orderByDesc('created_at')->orderByDesc('id')->limit(5000)->get();

        // Las cuentas tocadas: la entidad "User" se muestra con su rol (Estudiante, Docente, Secretaría...)
        $cuentas = User::whereIn('id', $eventos->where('entidad_tipo', 'User')->pluck('entidad_id')->unique())->pluck('role', 'id');

        return $eventos->map(function (Auditoria $a) use ($cuentas) {
            $u = $a->usuario;
            $entidad = $a->entidad_tipo === 'User'
                ? ($cuentas->get($a->entidad_id)?->label() ?? 'Cuenta')
                : (self::ENTIDADES[$a->entidad_tipo] ?? $a->entidad_tipo);

            return [
                'id'      => $a->id,
                'fecha'   => $a->created_at?->toIso8601String(),
                // Eventos de antes de guardar la sede en la auditoría: la sede de quien actuó
                'sede_id' => $a->sede_id ?? ($u && $u->role !== RolUsuario::AdminGeneral ? $u->sede_id : null),
                'quien'   => $u ? trim($u->nombres.' '.$u->apellidos) : 'Cuenta eliminada',
                'rol'     => $u ? (self::ROLES[$u->role->value] ?? $u->role->value) : 'secretaria',
                'tipo'    => $a->accion,
                'entidad' => $entidad,
                'texto'   => $a->descripcion,
            ];
        })->all();
    }
}
