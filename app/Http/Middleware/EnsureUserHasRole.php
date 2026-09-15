<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Deja pasar solo si el usuario autenticado tiene uno de los roles
     * indicados (ej. ->middleware('role:superadmin')). Un usuario
     * autenticado con otro rol recibe 403; uno sin sesión, 401.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if(! $user, 401);
        abort_unless(in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}
