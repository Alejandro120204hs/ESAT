<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas web (auditoría del panel Estudiante, ES20):
 * - nosniff: un archivo que alguien subió se abre con el tipo con el que se sirve, no con el que el navegador adivine;
 * - SAMEORIGIN: las páginas no se pueden meter en un marco de otro sitio (clickjacking);
 * - Referrer-Policy: a los enlaces externos (material de clase) no les llega la dirección completa de la página.
 * Sin CSP por ahora: los paneles usan scripts en línea (EST_SERVIDOR, DOC_SERVIDOR) y librerías por CDN.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $respuesta;
    }
}
