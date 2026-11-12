<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Cada petición que escribe (paneles de la secretaría, del Admin y del docente) es una sola transacción: el cambio y su
 * línea en la auditoría quedan juntos o no queda ninguno. Laravel convierte las excepciones del controlador en respuesta
 * antes de volver aquí, así que se confirma solo si la respuesta salió bien (2xx/3xx); un 4xx o 5xx deshace todo.
 */
class TransaccionEscritura
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        DB::beginTransaction();
        try {
            $respuesta = $next($request);
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $respuesta->isSuccessful() || $respuesta->isRedirection() ? DB::commit() : DB::rollBack();

        return $respuesta;
    }
}
