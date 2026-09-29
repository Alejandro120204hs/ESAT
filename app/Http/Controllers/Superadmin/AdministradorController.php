<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdministradorController extends Controller
{
    public function index()
    {
        $administradores = User::where('role', RolUsuario::Admin)
            ->with('sede')
            ->orderBy('apellidos')
            ->get();

        $sedes = Sede::orderBy('nombre')->get();

        return view('superadmin.administradores', compact('administradores', 'sedes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombres'                 => ['required', 'string', 'max:100'],
            'apellidos'               => ['required', 'string', 'max:100'],
            'genero'                  => ['required', Rule::in(['masculino', 'femenino', 'otro'])],
            'fecha_nacimiento'        => ['required', 'date'],
            'departamento_nacimiento' => ['required', 'string', 'max:100'],
            'ciudad_nacimiento'       => ['required', 'string', 'max:100'],
            'tipo_documento'          => ['required', Rule::in(['CC', 'TI', 'CE', 'PA'])],
            'numero_documento'        => ['required', 'string', 'max:20', Rule::unique('users')],
            'telefono'                => ['required', 'string', 'max:20'],
            'correo'                  => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'sede_id'                 => ['required', 'exists:sedes,id'],
        ]);

        User::create([
            'nombres'                 => $data['nombres'],
            'apellidos'               => $data['apellidos'],
            'genero'                  => $data['genero'],
            'fecha_nacimiento'        => $data['fecha_nacimiento'],
            'departamento_nacimiento' => $data['departamento_nacimiento'],
            'lugar_nacimiento'        => $data['ciudad_nacimiento'],
            'tipo_documento'          => $data['tipo_documento'],
            'numero_documento'        => $data['numero_documento'],
            'telefono'                => $data['telefono'],
            'email'                   => $data['correo'],
            'password'                => Hash::make($data['numero_documento']),
            'role'                    => RolUsuario::Admin,
            'sede_id'                 => $data['sede_id'],
            'activo'                  => true,
        ]);

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, User $administrador)
    {
        $data = $request->validate([
            'nombres'                 => ['required', 'string', 'max:100'],
            'apellidos'               => ['required', 'string', 'max:100'],
            'genero'                  => ['required', Rule::in(['masculino', 'femenino', 'otro'])],
            'fecha_nacimiento'        => ['required', 'date'],
            'departamento_nacimiento' => ['required', 'string', 'max:100'],
            'ciudad_nacimiento'       => ['required', 'string', 'max:100'],
            'tipo_documento'          => ['required', Rule::in(['CC', 'TI', 'CE', 'PA'])],
            'numero_documento'        => ['required', 'string', 'max:20', Rule::unique('users')->ignore($administrador)],
            'telefono'                => ['required', 'string', 'max:20'],
            'correo'                  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($administrador)],
            'sede_id'                 => ['required', 'exists:sedes,id'],
        ]);

        $administrador->update([
            'nombres'                 => $data['nombres'],
            'apellidos'               => $data['apellidos'],
            'genero'                  => $data['genero'],
            'fecha_nacimiento'        => $data['fecha_nacimiento'],
            'departamento_nacimiento' => $data['departamento_nacimiento'],
            'lugar_nacimiento'        => $data['ciudad_nacimiento'],
            'tipo_documento'          => $data['tipo_documento'],
            'numero_documento'        => $data['numero_documento'],
            'telefono'                => $data['telefono'],
            'email'                   => $data['correo'],
            'sede_id'                 => $data['sede_id'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function toggleActivo(User $administrador)
    {
        $administrador->update(['activo' => !$administrador->activo]);
        return response()->json(['ok' => true, 'activo' => $administrador->activo]);
    }
}
