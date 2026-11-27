<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'sede.trabajo' => \App\Http\Middleware\EnsureSedeDeTrabajo::class,
            'transaccion' => \App\Http\Middleware\TransaccionEscritura::class,
        ]);
        // Cuenta desactivada o derecho de matrícula vencido: se corta también la sesión abierta
        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountIsEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Dos envíos iguales al mismo tiempo (doble clic o dos personas): la BD rechaza el segundo por un índice único.
        // En vez de un error 500, se avisa y se pide recargar.
        $exceptions->render(function (\Illuminate\Database\UniqueConstraintViolationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Otra persona (u otro clic) acaba de guardar lo mismo. Recarga la página y revisa antes de volver a intentarlo.'], 409);
            }
        });
    })->create();
