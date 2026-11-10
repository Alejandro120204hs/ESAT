<?php

namespace App\Services\Sistema;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Restablecer la clave de una cuenta desde el panel del Admin. */
class SesionesService
{
    /**
     * La clave vuelve a ser el número de documento (convención del proyecto) y se cierran sus sesiones abiertas:
     * las de la base de datos y la cookie "Recordarme" (cambia el remember_token).
     */
    public function restablecerClave(User $u): void
    {
        $u->forceFill(['password' => Hash::make((string) $u->numero_documento), 'remember_token' => Str::random(60)])->save();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $u->id)->delete();
        }
    }
}
