<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un usuario ya logueado que visita /login (middleware 'guest')
        // cae en su propio panel según el rol, no en el dashboard
        // genérico de Breeze.
        RedirectIfAuthenticated::redirectUsing(fn ($request) => $request->user()->panelUrl());

        // Shared across every page (not just the layout) because a
        // child view's @section content is captured before the parent
        // layout it extends ever runs its own @php block.
        View::composer('*', function ($view) {
            $view->with('wa', function (string $topic, string $number = '573152957588') {
                $msg = "Hola ESAT, quiero información sobre: {$topic}";
                return 'https://wa.me/' . $number . '?text=' . rawurlencode($msg);
            });
            $view->with('educacionContinuada', array_column(config('sitio.continuada'), 0));
            $view->with('sitio', config('sitio'));
            // URL de un archivo de public/ con su fecha como versión, para
            // que el navegador no siga usando el viejo después de cambiarlo.
            $view->with('recurso', fn (string $ruta) => asset($ruta) . '?v=' . @filemtime(public_path($ruta)));
            // Foto del sitio: prefiere la versión .webp (y su -960 para pantallas
            // chicas); null si la foto todavía no existe en public/assets/img.
            $view->with('foto', function (?string $archivo) {
                if (! $archivo) {
                    return null;
                }
                $base = pathinfo($archivo, PATHINFO_FILENAME);
                if (is_file(public_path("assets/img/$base.webp"))) {
                    $chica = is_file(public_path("assets/img/$base-960.webp")) ? asset("assets/img/$base-960.webp") . ' 960w, ' : '';
                    return ['src' => asset("assets/img/$base.webp"), 'srcset' => $chica . asset("assets/img/$base.webp") . ' 1920w'];
                }
                return is_file(public_path("assets/img/$archivo")) ? ['src' => asset("assets/img/$archivo"), 'srcset' => null] : null;
            });
        });
    }
}
