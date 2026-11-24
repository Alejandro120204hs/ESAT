<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Sistema\BloqueoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsEnabled
{
    /**
     * Revisa en cada petición lo mismo que el login: una cuenta desactivada o bloqueada no sigue adentro aunque ya
     * tuviera la sesión abierta o la cookie de "Recordarme". Cierra la sesión y vuelve al login con el motivo.
     * El derecho de matrícula vencido ya no bloquea (lo pidió el cliente, 24-11-2026): el estudiante entra igual, la
     * deuda se acumula y no se gradúa sin estar a paz y salvo.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($motivo = self::motivo($user))) {
            Auth::guard('web')->logout(); // también cambia el remember_token: la cookie deja de servir
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => $motivo], 401)
                : redirect()->route('login')->withErrors(['email' => $motivo]);
        }

        return $next($request);
    }

    /** Por qué esta cuenta no puede estar adentro (null = sí puede). Lo usan el login y cada petición. */
    public static function motivo(User $user): ?string
    {
        if (! $user->activo) {
            return 'Tu cuenta está desactivada. Comunícate con la sede de ESAT para activarla.';
        }
        // Bloqueada por intentos fallidos o inactividad (Admin › Configuración): no entra hasta que la desbloqueen
        if ($user->bloqueado_en) {
            return BloqueoService::mensaje($user);
        }


        return null;
    }
}
