<?php

namespace App\Http\Controllers\AdminGeneral\Sedes;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Sedes\SedeRequest;
use App\Models\Finanzas\Matricula;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Services\General\EstructuraService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** Sedes de ESAT: crear y corregir sus datos (sin eliminar ni desactivar). */
class SedeController extends Controller
{
    public function index(EstructuraService $estructura): View
    {
        // Estudiantes activos de cada programa en cada sede (detalle de la sede)
        $porPrograma = Matricula::where('estado', 'activo')->selectRaw('sede_id, programa_id, count(distinct estudiante_id) as n')
            ->groupBy('sede_id', 'programa_id')->get()
            ->groupBy('sede_id')->map(fn ($filas) => $filas->pluck('n', 'programa_id')->map(fn ($n) => (int) $n));

        $sedes = collect($estructura->sedes())->map(fn ($s) => $s + ['por_programa' => (object) ($porPrograma->get($s['id'])?->all() ?? [])])->all();

        return view('admin_general.sedes.index', ['datos' => [
            'sedes'       => $sedes,
            'secretarias' => $estructura->secretarias(),
            'programas'   => collect($estructura->programas())->map(fn ($p) => ['id' => $p['id'], 'nombre' => $p['nombre']])->all(),
        ]]);
    }

    public function store(SedeRequest $request): JsonResponse
    {
        $sede = Sede::create($request->validated());
        Auditoria::registrar('crear', $sede, "Creó la sede {$sede->nombre} ({$sede->ciudad}, {$sede->departamento}).", $sede->id);

        return response()->json(['mensaje' => "Se creó la sede {$sede->nombre}. Ahora asígnale una secretaría.", 'id' => $sede->id], 201);
    }

    public function update(SedeRequest $request, Sede $sede): JsonResponse
    {
        $sede->fill($request->validated());
        if (! $sede->isDirty()) {
            return response()->json(['mensaje' => 'No había cambios que guardar.']);
        }
        $cambios = collect(array_keys($sede->getDirty()))->map(fn ($c) => ['nombre' => 'el nombre', 'departamento' => 'el departamento', 'ciudad' => 'la ciudad', 'direccion' => 'la dirección'][$c])->join(', ', ' y ');
        $antes = $sede->getOriginal('nombre');
        $sede->save();
        Auditoria::registrar('editar', $sede, "Editó la sede {$antes}: cambió {$cambios}.", $sede->id);

        return response()->json(['mensaje' => "Se guardaron los cambios de la sede {$sede->nombre}."]);
    }
}
