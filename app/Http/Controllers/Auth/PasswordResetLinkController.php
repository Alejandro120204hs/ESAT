<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        // Una cuenta desactivada no recibe el enlace (HALLAZGOS B23); la respuesta es la misma, para no revelar su estado
        if (User::where('email', $request->input('email'))->where('activo', false)->exists()) {
            return back()->with('status', __(Password::RESET_LINK_SENT));
        }

        Password::sendResetLink(
            $request->only('email')
        );

        // Un correo que no existe recibe la misma respuesta que uno que sí: así no se puede averiguar quién tiene cuenta
        // (auditoría del panel Estudiante, ES19). Tampoco se dice "espera un momento": solo le pasa a una cuenta que existe
        // y pidió el enlace hace menos de un minuto (revisión de ES19); el límite se sigue cumpliendo, en silencio.
        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
