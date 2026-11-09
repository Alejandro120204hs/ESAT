<?php

namespace App\Http\Controllers\Sitio;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Subpáginas del sitio público: programas técnicos, líneas de Educación
 * Continuada y escuelas. El contenido vive en config/contenido.php y la
 * estructura de escuelas/programas en config/sitio.php; cada ruta fija su
 * slug con ->defaults() en routes/web.php, así los nombres de ruta
 * (programas.{slug}, continuada.{slug}, escuelas.{slug}) no cambian.
 */
class PaginaController extends Controller
{
    public function programa(string $slug): View
    {
        $programa = $this->conPortada($slug);
        $escuela = $this->buscarEscuela($programa['escuela']);
        $hermanos = collect($escuela['programas'])
            ->reject(fn ($p) => $p[1] === $slug)
            ->map(fn ($p) => $this->conPortada($p[1]))
            ->values()->all();

        return view('paginas.programa', compact('slug', 'programa', 'escuela', 'hermanos'));
    }

    public function continuada(string $slug): View
    {
        $linea = config("contenido.continuada.$slug") ?? abort(404);

        return view('paginas.continuada', compact('slug', 'linea'));
    }

    public function escuela(string $slug): View
    {
        $escuela = $this->buscarEscuela($slug);
        $programas = collect($escuela['programas'])->map(fn ($p) => $this->conPortada($p[1]))->all();

        return view('paginas.escuela', compact('escuela', 'programas'));
    }

    private function buscarEscuela(string $slug): array
    {
        return collect(config('sitio.escuelas'))->firstWhere('slug', $slug) ?? abort(404);
    }

    /**
     * El programa con su slug y la foto de portada: la suya, y si todavía no
     * existe, la primera foto real del mismo programa (salidas o práctica).
     * Solo si no hay ninguna, queda null y la vista usa el ícono de la escuela.
     */
    private function conPortada(string $slug): array
    {
        $p = config("contenido.programas.$slug") ?? abort(404);
        $candidatas = array_merge([$p['foto']], array_column($p['salidas'], 4), [$p['practica'][0]]);
        $p['slug'] = $slug;
        $p['foto_portada'] = collect($candidatas)->first(fn ($f) => $this->existeFoto($f));

        return $p;
    }

    private function existeFoto(?string $archivo): bool
    {
        if (! $archivo) {
            return false;
        }
        $base = pathinfo($archivo, PATHINFO_FILENAME);

        return is_file(public_path("assets/img/$base.webp")) || is_file(public_path("assets/img/$archivo"));
    }
}
