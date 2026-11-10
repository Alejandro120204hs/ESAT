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
     * su panel general; a un fetch se le responde 409 con el motivo.
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
        }

        return $next($request);
    }
}
