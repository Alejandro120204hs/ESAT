<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Sistema\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        if ($user->isSuperadmin()) {
            return view('superadmin.profile', ['user' => $user]);
        }

        if ($user->isAdmin()) {
            return view('admin.profile', ['user' => $user]);
        }

        if ($user->isAdminGeneral()) {
            return view('admin_general.profile', ['user' => $user]);
        }

        if ($user->isEstudiante()) {
            return view('estudiante.perfil', ['user' => $user]);
        }

        if ($user->isDocente()) {
            return view('docente.perfil', ['user' => $user->load(['sede', 'docentePerfil.escuela'])]);
        }

        return view('profile.edit', ['user' => $user]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $u = $request->user();
        $u->fill($request->safe()->except('clave_actual'));
        $cambios = array_keys($u->getDirty());

        if ($u->isDirty('email')) {
            $u->email_verified_at = null;
        }

        $u->save();
        // Lo que cada quien cambia de su perfil queda en la auditoría (HALLAZGOS M20, ES18)
        if ($cambios) {
            $nombres = ['email' => 'correo', 'telefono' => 'celular', 'direccion' => 'dirección', 'nombres' => 'nombres', 'apellidos' => 'apellidos'];
            Auditoria::registrar('editar', $u, 'Actualizó su perfil: '.implode(', ', array_map(fn ($k) => $nombres[$k] ?? $k, $cambios)).'.', $u->sede_id);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
