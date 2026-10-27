<?php

namespace App\Http\Middleware;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsEnabled
{
    /**
     * Revisa en cada petición lo mismo que el login: una cuenta desactivada, o un estudiante
     * con el derecho de matrícula vencido sin pagar, no sigue adentro aunque ya tuviera la
     * sesión abierta o la cookie de "Recordarme". Cierra la sesión y vuelve al login con el motivo.
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

        // El derecho de matrícula se paga antes de empezar clases: vencido sin pagar, no se ingresa
        if ($user->role === RolUsuario::Estudiante && ($derecho = app(PagoService::class)->derechoVencido($user))) {
            return 'Tu acceso está bloqueado porque el derecho de matrícula venció el '
                .$derecho->fecha_vencimiento->format('d/m/Y').' sin pagarse. Págalo en la sede de ESAT para volver a ingresar.';
        }

        return null;
    }
}
