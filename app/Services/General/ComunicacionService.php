<?php

namespace App\Services\General;

use App\Enums\RolUsuario;
use App\Models\Comunicacion\Comunicado;
use App\Models\Institucional\Sede;
use App\Services\Comunicacion\ComunicadoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comunicados de todas las sedes para el Admin. Lo que el Admin envía a varias sedes es una fila por sede con el mismo
 * `lote` (cada sede cuenta sus destinatarios y lecturas); aquí se muestran juntas como un solo comunicado.
 */
class ComunicacionService
{
    /** Destino en pantalla ⇄ destino guardado: "todos" es toda la sede. */
    public const DESTINOS = ['todos' => 'sede', 'estudiantes' => 'estudiantes', 'docentes' => 'docentes', 'acudientes' => 'acudientes'];

    public function __construct(private ComunicadoService $comunicados) {}

    /** Cuántas personas recibirían hoy un comunicado en cada sede, por destino (los mismos que calcula la secretaría). */
    public function alcance(): array
    {
        return Sede::pluck('id')->mapWithKeys(function ($id) {
            $p = $this->comunicados->personas($id);

            return [$id => ['estudiantes' => count($p['estudiantes']), 'docentes' => count($p['docentes']), 'acudientes' => count($p['acudientes']),
                'grupos' => collect($p['estudiantes'])->countBy('grupo_id')->all()]];
        })->all();
    }

    public function comunicados(): array
    {
        $alcance = $this->alcance();
        $total = Sede::count();

        return Comunicado::with(['autor', 'curso.programa', 'lectores:id'])->orderByDesc('id')->get()
            ->groupBy(fn (Comunicado $c) => $c->lote ?: 'c'.$c->id)
            ->map(function (Collection $filas) use ($alcance, $total) {
                // Si una secretaría archivó su copia, el envío sigue vivo en las demás: manda la primera sin archivar
                $vivas = $filas->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado');
                $c = ($vivas->isNotEmpty() ? $vivas : $filas)->sortBy('id')->first();
                $sedes = $filas->pluck('sede_id')->unique()->sort()->values()->all();
                $destino = array_search($c->destino, self::DESTINOS, true) ?: $c->destino;
                $estado = $c->estadoActual();
                $destinatarios = ($vivas->isNotEmpty() ? $vivas : $filas)->sum(fn (Comunicado $f) => $this->destinatarios($f, $alcance[$f->sede_id] ?? null));

                return [
                    'id'             => $c->id,
                    'sedes'          => count($sedes) > 1 && count($sedes) === $total ? 'todas' : $sedes,
                    'destino'        => $destino,
                    'grupo'          => $c->curso ? str_replace('Técnico Laboral en ', '', $c->curso->programa->nombre).' · '.$c->curso->nombre : null,
                    'titulo'         => $c->titulo,
                    'mensaje'        => (string) $c->mensaje,
                    'autor'          => $this->autor($c),
                    'estado'         => $estado,
                    'publicado_en'   => $c->publicado_en?->toIso8601String(),
                    'creado'         => $c->created_at?->toIso8601String(),
                    'destinatarios'  => in_array($estado, ['publicado', 'archivado'], true) ? $destinatarios : 0,
                    'leidos'         => $filas->sum(fn (Comunicado $f) => $f->lectores->count()),
                    'motivo_archivo' => $c->motivo_archivo,
                    // Sedes donde la secretaría lo archivó mientras sigue publicado en las demás
                    'archivado_en_sedes' => $vivas->isNotEmpty() ? $filas->diff($vivas)->pluck('sede_id')->values()->all() : [],
                ];
            })->values()->all();
    }

    /**
     * Crea el comunicado en cada sede (todas con el mismo lote). $accion: borrador | publicar | programar.
     *
     * @param  int[]  $sedes
     */
    public function crear(array $sedes, array $datos, string $accion): Comunicado
    {
        return DB::transaction(function () use ($sedes, $datos, $accion) {
            $lote = (string) Str::uuid();
            $primero = null;
            foreach ($sedes as $sedeId) {
                $c = $this->comunicados->guardar($sedeId, new Comunicado(['sede_id' => $sedeId, 'autor_id' => auth()->id(), 'lote' => $lote]), $datos, $accion, null, false);
                $primero ??= $c;
            }

            return $primero;
        });
    }

    /**
     * Edita todas las filas del lote. Mientras no esté publicado puede cambiar de sedes: se quitan las que sobran (nadie
     * lo ha visto) y se crean las nuevas.
     */
    public function editar(Comunicado $c, array $sedes, array $datos, string $accion): Comunicado
    {
        return DB::transaction(function () use ($c, $sedes, $datos, $accion) {
            $filas = $this->lote($c);
            $lote = $c->lote ?: (string) Str::uuid();
            $filas->each(fn (Comunicado $f) => $f->lote ?: $f->update(['lote' => $lote]));
            if ($c->estadoActual() !== 'publicado') {
                $filas->whereNotIn('sede_id', $sedes)->each(fn (Comunicado $f) => $this->comunicados->eliminar($f));
                foreach (array_diff($sedes, $filas->pluck('sede_id')->all()) as $sedeId) {
                    $this->comunicados->guardar($sedeId, new Comunicado(['sede_id' => $sedeId, 'autor_id' => $c->autor_id, 'lote' => $lote]), $datos, $accion, null, false);
                }
                $filas = $filas->whereIn('sede_id', $sedes);
            }
            // Una copia archivada en su sede (por la secretaría) no vuelve a publicarse
            $filas->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado')
                ->each(fn (Comunicado $f) => $this->comunicados->guardar($f->sede_id, $f, $datos, $accion, null, false));

            // La fila original pudo irse si se quitó su sede: el envío sigue en las demás
            return Comunicado::where('lote', $lote)->orderBy('id')->firstOrFail();
        });
    }

    public function archivar(Comunicado $c, string $motivo): void
    {
        // Las copias que una secretaría ya archivó conservan su motivo
        DB::transaction(fn () => $this->lote($c)->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado')
            ->each(fn (Comunicado $f) => $this->comunicados->archivar($f, $motivo)));
    }

    public function eliminar(Comunicado $c): void
    {
        DB::transaction(fn () => $this->lote($c)->each(fn (Comunicado $f) => $this->comunicados->eliminar($f)));
    }

    /** @return int[] sedes donde está el comunicado */
    public function sedesDe(Comunicado $c): array
    {
        return $this->lote($c)->pluck('sede_id')->all();
    }

    /** Las filas del mismo envío (una sola si no tiene lote). */
    public function lote(Comunicado $c): Collection
    {
        return $c->lote ? Comunicado::where('lote', $c->lote)->orderBy('id')->get() : collect([$c]);
    }

    private function destinatarios(Comunicado $c, ?array $a): int
    {
        if (! $a) {
            return 0;
        }

        return match ($c->destino) {
            'sede'  => $a['estudiantes'] + $a['docentes'] + $a['acudientes'],
            'grupo' => $a['grupos'][$c->curso_id] ?? 0,
            default => $a[$c->destino] ?? 0,
        };
    }

    private function autor(Comunicado $c): array
    {
        $u = $c->autor;
        $tipo = match ($u?->role) {
            RolUsuario::AdminGeneral => 'admin',
            RolUsuario::Docente      => 'docente',
            default                  => 'secretaria',
        };

        return ['tipo' => $tipo, 'nombre' => $u ? trim($u->nombres.' '.$u->apellidos) : 'Cuenta eliminada'];
    }
}
