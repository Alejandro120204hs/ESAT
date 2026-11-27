<?php

namespace App\Services\Sistema;

use App\Enums\RolUsuario;
use App\Models\Institucional\Configuracion;
use App\Models\User;

/**
 * Bloqueo de cuentas (Admin › Configuración › Seguridad): por intentos fallidos seguidos o por pasar demasiados días
 * sin entrar. Una cuenta bloqueada no ingresa (EnsureAccountIsEnabled::motivo). La de intentos fallidos se levanta sola
 * a los MINUTOS_INTENTOS (si no, cualquiera que sepa un correo dejaría a esa persona por fuera hasta que el Admin la
 * desbloquee); la de inactividad, hasta que el Admin la desbloquee o la secretaría le restablezca la clave.
 * El Admin de ESAT y el superadmin nunca se bloquean: son quienes desbloquean.
 */
class BloqueoService
{
    public const MOTIVOS = [
        'intentos_fallidos' => 'Demasiados intentos fallidos',
        'inactividad'       => 'Mucho tiempo sin entrar',
        'manual'            => 'Bloqueada por la administración',
    ];

    public const MINUTOS_INTENTOS = 15;

    /** Si la cuenta está bloqueada ahora. Un bloqueo por intentos fallidos que ya cumplió su tiempo se levanta aquí. */
    public function bloqueada(User $u): bool
    {
        if (! $u->bloqueado_en) {
            return false;
        }
        if ($u->motivo_bloqueo === 'intentos_fallidos' && $u->bloqueado_en->lte(now()->subMinutes(self::MINUTOS_INTENTOS))) {
            $u->forceFill(['bloqueado_en' => null, 'motivo_bloqueo' => null, 'intentos_fallidos' => 0])->saveQuietly();

            return false;
        }

        return true;
    }

    public function seBloquea(User $u): bool
    {
        return ! in_array($u->role, [RolUsuario::Superadmin, RolUsuario::AdminGeneral], true);
    }

    /** Un intento con la clave equivocada: al llegar al máximo, la cuenta queda bloqueada. */
    public function registrarFallo(string $email): void
    {
        $u = User::where('email', mb_strtolower(trim($email)))->first();
        if (! $u || ! $this->seBloquea($u) || $this->bloqueada($u)) {
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
        if ($this->seBloquea($u) && ! $this->bloqueada($u) && $u->ultimo_acceso && $u->ultimo_acceso->lt(now()->subDays($dias))) {
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
            'intentos_fallidos' => 'Tu cuenta está bloqueada por demasiados intentos fallidos. Intenta de nuevo en '.self::MINUTOS_INTENTOS.' minutos o pide en la sede de ESAT que te restablezcan la clave.',
            'inactividad'       => 'Tu cuenta está bloqueada porque pasó mucho tiempo sin ingresar. Pide en la sede de ESAT que te restablezcan la clave.',
            default             => 'Tu cuenta está bloqueada. Comunícate con la sede de ESAT para desbloquearla.',
        };
    }
}
