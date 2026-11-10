<?php

namespace App\Http\Controllers\AdminGeneral\Sedes;

use App\Http\Controllers\Controller;
use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Entrar a la sede": el Admin de ESAT trabaja en una sede con las mismas pantallas (y reglas) de la secretaría.
 * La sede elegida queda en la sesión (User::SESION_SEDE) y User::sedeTrabajoId() la devuelve; lo que haga queda en
 * la auditoría de esa sede con su nombre.
 */
class EntrarSedeController extends Controller
{
    /** Pantallas de la secretaría a las que se puede llegar directo al entrar. */
    private const DESTINOS = ['dashboard', 'matriculas', 'estudiantes', 'acudientes', 'docentes', 'cursos', 'horarios', 'pagos', 'asistencia', 'calificaciones'];

    public function entrar(Request $request, Sede $sede): RedirectResponse
    {
        $request->session()->put(User::SESION_SEDE, $sede->id);
        $destino = in_array($request->input('destino'), self::DESTINOS, true) ? $request->input('destino') : 'dashboard';

        return redirect()->route('admin.'.$destino)->with('aviso', 'Estás trabajando en la sede '.$sede->nombre.'.');
    }

    public function salir(Request $request): RedirectResponse
    {
        $request->session()->forget(User::SESION_SEDE);

        return redirect()->route('general.dashboard');
    }
}
