<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountIsEnabled;
use App\Models\User;
use App\Services\Sistema\BloqueoService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        $this->protegerMigraciones();
        $this->registrarIngresoRecordado();

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

    /**
     * HALLAZGOS B22. Quien entra con la cookie "Recordarme" no pasa por LoginRequest: aquí se revisa su inactividad y se
     * guarda su último acceso como en el formulario (si queda bloqueado, EnsureAccountIsEnabled cierra la sesión en esa
     * misma petición). El ingreso por formulario también dispara Login, pero lo atiende LoginRequest (viaRemember es
     * falso), que revisa la inactividad antes de actualizar el último acceso.
     */
    private function registrarIngresoRecordado(): void
    {
        Event::listen(function (Login $evento) {
            if (! $evento->user instanceof User || ! Auth::guard($evento->guard)->viaRemember()) {
                return;
            }
            $bloqueos = app(BloqueoService::class);
            $bloqueos->revisarInactividad($evento->user);
            if (! EnsureAccountIsEnabled::motivo($evento->user)) {
                $bloqueos->ingresoCorrecto($evento->user);
            }
        });
    }

    /**
     * HALLAZGOS A12. Deshacer una migración del 07-10-2026 en adelante con datos borra dinero (recibos, cobros) o deja la
     * BD a medias, así que se niega mientras haya matrículas, cobros o recibos: se restaura un respaldo en su lugar. En
     * producción, además, no se corren migrate:fresh, migrate:refresh, migrate:reset ni db:wipe.
     */
    private function protegerMigraciones(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Event::listen(function (MigrationStarted $evento) {
            if ($evento->method !== 'down') {
                return;
            }
            $archivo = basename((string) (new \ReflectionClass($evento->migration))->getFileName(), '.php');
            if ($archivo < '2026_10_07' || ! $this->hayDinero()) {
                return;
            }
            throw new RuntimeException("No se deshace la migración {$archivo}: la base de datos tiene matrículas, cobros o recibos "
                .'y deshacerla los borraría o la dejaría a medias. Si necesitas volver atrás, restaura un respaldo (mysqldump).');
        });
    }

    private function hayDinero(): bool
    {
        foreach (['matriculas', 'pagos', 'recaudos'] as $tabla) {
            if (Schema::hasTable($tabla) && DB::table($tabla)->exists()) {
                return true;
            }
        }

        return false;
    }
}
