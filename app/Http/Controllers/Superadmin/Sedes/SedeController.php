<?php

namespace App\Http\Controllers\Superadmin\Sedes;

use App\Http\Controllers\Controller;
use App\Models\Institucional\Sede;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SedeController extends Controller
{
    public function index()
    {
        $sedes = Sede::orderBy('nombre')->get();
        return view('superadmin.sedes-programas', compact('sedes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'       => ['required', 'string', 'max:100', Rule::unique('sedes')],
            'departamento' => ['required', 'string', 'max:100'],
            'ciudad'       => ['required', 'string', 'max:100'],
            'direccion'    => ['required', 'string', 'max:255'],
        ]);

        Sede::create($data);
        return response()->json(['ok' => true]);
    }

    public function update(Request $request, Sede $sede)
    {
        $data = $request->validate([
            'nombre'       => ['required', 'string', 'max:100', Rule::unique('sedes')->ignore($sede)],
            'departamento' => ['required', 'string', 'max:100'],
            'ciudad'       => ['required', 'string', 'max:100'],
            'direccion'    => ['required', 'string', 'max:255'],
        ]);

        $sede->update($data);
        return response()->json(['ok' => true]);
    }
}
