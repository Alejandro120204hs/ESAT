<?php

namespace App\Http\Controllers\AdminGeneral\Programas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Programas\EscuelaRequest;
use App\Models\Academico\DocentePerfil;
use App\Models\Academico\Escuela;
use App\Models\Sistema\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Escuelas del catálogo (Admin). La sigla se genera al crearla y no cambia (los códigos de sus programas la usan).
 * Solo se elimina una escuela sin programas ni docentes.
 */
class EscuelaController extends Controller
{
    public function store(EscuelaRequest $request): JsonResponse
    {
        $nombre = $request->validated('nombre');
        $escuela = Escuela::create(['nombre' => $nombre, 'sigla' => $this->siglaLibre($nombre)]);
        Auditoria::registrar('crear', $escuela, "Creó la escuela {$escuela->nombre} ({$escuela->sigla}).");

        return response()->json(['mensaje' => "Se creó la escuela {$escuela->nombre} (sigla {$escuela->sigla})."], 201);
    }

    public function update(EscuelaRequest $request, Escuela $escuela): JsonResponse
    {
        $anterior = $escuela->nombre;
        $escuela->update(['nombre' => $request->validated('nombre')]);
        if ($escuela->wasChanged('nombre')) {
            Auditoria::registrar('editar', $escuela, "Renombró la escuela {$anterior} a {$escuela->nombre}.");
        }

        return response()->json(['mensaje' => "Se guardó la escuela {$escuela->nombre}."]);
    }

    public function destroy(Escuela $escuela): JsonResponse
    {
        $programas = $escuela->programas()->count();
        $docentes = DocentePerfil::where('escuela_id', $escuela->id)->count();
        if ($programas || $docentes) {
            return response()->json(['message' => "No se puede eliminar {$escuela->nombre}: tiene {$programas} programa(s) y {$docentes} docente(s). Pásalos a otra escuela primero."], 422);
        }
        Auditoria::registrar('eliminar', $escuela, "Eliminó la escuela {$escuela->nombre}.");
        $escuela->delete();

        return response()->json(['mensaje' => "Se eliminó la escuela {$escuela->nombre}."]);
    }

    /** Tres primeras letras del nombre (sin tildes ni palabras de enlace) y un número si ya existe: la misma regla de la secretaría. */
    private function siglaLibre(string $nombre): string
    {
        $palabras = array_values(array_filter(
            explode(' ', Str::upper(Str::ascii($nombre))),
            fn ($w) => ! in_array($w, ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'E', 'EN', 'ESCUELA'], true)
        ));
        $base = substr(preg_replace('/[^A-Z]/', '', $palabras[0] ?? 'ESC'), 0, 3) ?: 'ESC';
        $sigla = $base;
        for ($n = 2; Escuela::where('sigla', $sigla)->exists(); $n++) {
            $sigla = substr($base, 0, 2).$n;
        }

        return $sigla;
    }
}
