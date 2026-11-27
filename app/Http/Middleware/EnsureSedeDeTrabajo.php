<?php

namespace App\Http\Middleware;

use App\Models\Institucional\Sede;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSedeDeTrabajo
{
    /**
     * Las pantallas de la secretaría trabajan siempre con una sede (User::sedeTrabajoId()). La secretaría usa la suya;
     * el Admin de ESAT, la que eligió al "Entrar a la sede". Si el Admin no eligió (o la sede ya no existe), vuelve a
     * su panel general; a un fetch se le responde 409 con el motivo. El fetch de una pestaña trae la sede con la que se
     * abrió (X-Sede-Trabajo, lo pone el layout): si ya no es la de la sesión, también 409, para no guardar en otra sede.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isAdminGeneral()) {
            $id = $user->sedeTrabajoId();
            if (! $id || ! Sede::whereKey($id)->exists()) {
                $request->session()->forget(User::SESION_SEDE);
                $motivo = 'Primero elige en qué sede vas a trabajar.';

                return $request->expectsJson()
                    ? response()->json(['message' => $motivo], 409)
                    : redirect()->route('general.dashboard')->with('aviso', $motivo);
            }

            // Una pestaña abierta en otra sede (el Admin entró a otra en otra pestaña): no actúa sobre la sede nueva
            $pestana = $request->header('X-Sede-Trabajo');
            if ($pestana !== null && (int) $pestana !== $id) {
                $antes = Sede::find((int) $pestana)?->nombre ?? 'otra sede';
                $ahora = Sede::find($id)->nombre;

                return response()->json(['message' => "Esta pestaña es de la sede {$antes}, pero en otra pestaña entraste a {$ahora}. "
                    ."Recarga la página para trabajar en {$ahora}, o vuelve a entrar a {$antes} desde el panel general."], 409);
            }
        }

        return $next($request);
    }
}
