<?php

namespace App\Services\Sistema;

use App\Enums\RolUsuario;
use App\Models\Institucional\Configuracion;
use App\Models\User;

/**
 * Bloqueo de cuentas (Admin › Configuración › Seguridad): por intentos fallidos seguidos o por pasar demasiados días
 * sin entrar. Una cuenta bloqueada no ingresa (EnsureAccountIsEnabled::motivo) hasta que el Admin la desbloquee.
 * El Admin de ESAT y el superadmin nunca se bloquean: son quienes desbloquean.
 */
class BloqueoService
{
    public const MOTIVOS = [
        'intentos_fallidos' => 'Demasiados intentos fallidos',
        'inactividad'       => 'Mucho tiempo sin entrar',
        'manual'            => 'Bloqueada por la administración',
    ];

    public function seBloquea(User $u): bool
    {
        return ! in_array($u->role, [RolUsuario::Superadmin, RolUsuario::AdminGeneral], true);
    }

    /** Un intento con la clave equivocada: al llegar al máximo, la cuenta queda bloqueada. */
    public function registrarFallo(string $email): void
    {
        $u = User::where('email', mb_strtolower(trim($email)))->first();
        if (! $u || ! $this->seBloquea($u) || $u->bloqueado_en) {
            return;
        }
        $u->forceFill(['intentos_fallidos' => min(255, $u->intentos_fallidos + 1)]);
        if ($u->intentos_fallidos >= Configuracion::seguridad()['intentos_maximos']) {
            $u->forceFill(['bloqueado_en' => now(), 'motivo_bloqueo' => 'intentos_fallidos']);
        }
        $u->saveQuietly();
    }

    /**
     * Al ingresar con la clave correcta: si entró antes y lleva más días sin entrar de los permitidos, se bloquea.
     * Una cuenta que nunca ha ingresado no se bloquea por inactividad (todavía no la han usado).
     */
    public function revisarInactividad(User $u): void
    {
        $dias = Configuracion::seguridad()['dias_inactividad'];
        if ($this->seBloquea($u) && ! $u->bloqueado_en && $u->ultimo_acceso && $u->ultimo_acceso->lt(now()->subDays($dias))) {
            $u->forceFill(['bloqueado_en' => now(), 'motivo_bloqueo' => 'inactividad'])->saveQuietly();
        }
    }

    /** Ingresó bien: los intentos fallidos vuelven a cero. */
    public function ingresoCorrecto(User $u): void
    {
        $u->forceFill(['intentos_fallidos' => 0, 'ultimo_acceso' => now()])->saveQuietly();
    }

    public function desbloquear(User $u): void
    {
        // El último acceso también cuenta desde hoy: si no, volvería a bloquearse por inactividad al entrar
        $u->forceFill(['bloqueado_en' => null, 'motivo_bloqueo' => null, 'intentos_fallidos' => 0,
            'ultimo_acceso' => $u->motivo_bloqueo === 'inactividad' ? now() : $u->ultimo_acceso])->saveQuietly();
    }

    /** Texto del login para una cuenta bloqueada. */
    public static function mensaje(User $u): string
    {
        return match ($u->motivo_bloqueo) {
            'intentos_fallidos' => 'Tu cuenta está bloqueada por demasiados intentos fallidos. Comunícate con la sede de ESAT para que la desbloqueen.',
            'inactividad'       => 'Tu cuenta está bloqueada porque pasó mucho tiempo sin ingresar. Comunícate con la sede de ESAT para que la desbloqueen.',
            default             => 'Tu cuenta está bloqueada. Comunícate con la sede de ESAT para desbloquearla.',
        };
    }
}
