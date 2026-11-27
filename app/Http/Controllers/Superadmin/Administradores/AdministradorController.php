<?php

namespace App\Http\Controllers\Superadmin\Administradores;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Administradores\AdminRequest;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Sistema\SesionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Panel Superadmin › Admin de ESAT: el Superadmin crea y maneja la cuenta del Admin (rol admin_general, una sola, sin
 * sede). Las secretarías de cada sede las crea el Admin desde su panel (Estructura › Secretarías), no el Superadmin.
 */
class AdministradorController extends Controller
{
    public function index(): View
    {
        return view('superadmin.administradores', [
            'admin'  => User::where('role', RolUsuario::AdminGeneral)->orderBy('id')->first(),
            'tipos'  => AdminRequest::TIPOS_DOCUMENTO,
        ]);
    }

    /** Solo hay un Admin de ESAT: si ya existe, se edita. La clave inicial es su número de documento. */
    public function store(AdminRequest $request): JsonResponse
    {
        if (User::where('role', RolUsuario::AdminGeneral)->exists()) {
            return response()->json(['ok' => false, 'message' => 'Ya existe la cuenta del Admin de ESAT: edítala en vez de crear otra.'], 422);
        }
        $admin = User::create($request->datos() + [
            'role'     => RolUsuario::AdminGeneral,
            'sede_id'  => null,
            'password' => Hash::make($request->validated('numero_documento')),
            'activo'   => true,
        ]);
        Auditoria::registrar('crear', $admin, "Creó la cuenta del Admin de ESAT de {$admin->name}.");

        return response()->json(['ok' => true, 'mensaje' => "Se creó la cuenta de {$admin->name}. Su clave inicial es su número de documento."]);
    }

    public function update(AdminRequest $request, User $administrador): JsonResponse
    {
        $this->asegurarAdmin($administrador);
        $administrador->fill($request->datos());
        if (! $administrador->isDirty()) {
            return response()->json(['ok' => true, 'mensaje' => 'No había cambios que guardar.']);
        }
        $administrador->save();
        Auditoria::registrar('editar', $administrador, "Editó la cuenta del Admin de ESAT de {$administrador->name}.");

        return response()->json(['ok' => true, 'mensaje' => "Se guardaron los cambios de {$administrador->name}."]);
    }

    /** Activa o desactiva la cuenta. Desactivada pierde la sesión en su siguiente petición (EnsureAccountIsEnabled). */
    public function toggleActivo(User $administrador): JsonResponse
    {
        $this->asegurarAdmin($administrador);
        $administrador->update(['activo' => ! $administrador->activo]);
        Auditoria::registrar($administrador->activo ? 'activar' : 'desactivar', $administrador,
            ($administrador->activo ? 'Activó' : 'Desactivó')." la cuenta del Admin de ESAT de {$administrador->name}.");

        return response()->json([
            'ok'      => true,
            'activo'  => $administrador->activo,
            'mensaje' => $administrador->activo ? "La cuenta de {$administrador->name} quedó activa." : "La cuenta de {$administrador->name} quedó inactiva: ya no puede ingresar.",
        ]);
    }

    /** La clave vuelve a ser su número de documento y se cierran sus sesiones abiertas (también "Recordarme"). */
    public function restablecerClave(User $administrador, SesionesService $sesiones): JsonResponse
    {
        $this->asegurarAdmin($administrador);
        $sesiones->restablecerClave($administrador);
        Auditoria::registrar('editar', $administrador, "Restableció la clave del Admin de ESAT {$administrador->name}.");

        return response()->json(['ok' => true, 'mensaje' => "La clave de {$administrador->name} volvió a ser su número de documento."]);
    }

    /** Estas rutas reciben cualquier id de users: solo tocan la cuenta del Admin de ESAT (HALLAZGOS C4). */
    private function asegurarAdmin(User $usuario): void
    {
        abort_unless($usuario->isAdminGeneral(), 404);
    }
}
