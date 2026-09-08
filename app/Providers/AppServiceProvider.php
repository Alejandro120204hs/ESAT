<?php

namespace App\Providers;

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
        // Shared across every page (not just the layout) because a
        // child view's @section content is captured before the parent
        // layout it extends ever runs its own @php block.
        View::composer('*', function ($view) {
            $view->with('wa', function (string $topic, string $number = '573152957588') {
                $msg = "Hola ESAT, quiero información sobre: {$topic}";
                return 'https://wa.me/' . $number . '?text=' . rawurlencode($msg);
            });
            $view->with('educacionContinuada', [
                'Salud Ocupacional',
                'Áreas Sociales',
                'Pedagogía',
                'Administración y Gerencia',
                'Edificios y Afines',
                'Hotelería y Turismo',
                'Salud',
            ]);
        });
    }
}
