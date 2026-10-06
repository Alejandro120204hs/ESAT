<?php

namespace App\Http\Controllers\Admin\Programas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Programas\EscuelaRequest;
use App\Models\Academico\Escuela;
use App\Models\Sistema\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class EscuelaController extends Controller
{
    public function store(EscuelaRequest $request): JsonResponse
    {
        $nombre = $request->validated('nombre');
        $escuela = Escuela::create(['nombre' => $nombre, 'sigla' => $this->siglaLibre($nombre)]);

        Auditoria::registrar('crear', $escuela, "Creó la escuela {$escuela->nombre}.");

        return response()->json(['ok' => true, 'mensaje' => "Se creó la escuela {$escuela->nombre}."]);
    }

    public function update(EscuelaRequest $request, Escuela $escuela): JsonResponse
    {
        $anterior = $escuela->nombre;
        // La sigla no cambia: los códigos de sus programas ya la usan
        $escuela->update(['nombre' => $request->validated('nombre')]);

        Auditoria::registrar('editar', $escuela, "Renombró la escuela {$anterior} a {$escuela->nombre}.");

        return response()->json(['ok' => true, 'mensaje' => "Se guardó la escuela {$escuela->nombre}."]);
    }

    public function destroy(Escuela $escuela): JsonResponse
    {
        $total = $escuela->programas()->count();
        if ($total > 0) {
            return response()->json([
                'ok'      => false,
                'message' => "No se puede eliminar {$escuela->nombre}: tiene {$total} ".($total === 1 ? 'programa' : 'programas').'. Muévelos a otra escuela primero.',
            ], 422);
        }

        Auditoria::registrar('eliminar', $escuela, "Eliminó la escuela {$escuela->nombre}.");
        $escuela->delete();

        return response()->json(['ok' => true, 'mensaje' => "Se eliminó la escuela {$escuela->nombre}."]);
    }

    /** Tres primeras letras del nombre (sin tildes) y un número si ya existe. */
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
