<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // La página que se pidió antes del login solo se respeta si es del panel de este usuario
        // (o su perfil). Si no, un docente que entra después de que expiró la sesión de un admin
        // terminaba en /admin/... con un 403.
        $panel = $request->user()->panelUrl();
        $pedida = $request->session()->pull('url.intended');
        $propia = $pedida && collect([$panel, route('profile.edit')])->contains(
            fn ($base) => $pedida === $base || str_starts_with($pedida, rtrim($base, '/').'/') || str_starts_with($pedida, $base.'?')
        );

        return redirect($propia ? $pedida : $panel);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
