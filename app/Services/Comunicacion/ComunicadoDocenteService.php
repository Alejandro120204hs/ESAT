<?php

namespace App\Services\Comunicacion;

use App\Models\Academico\Curso;
use App\Models\Comunicacion\Comunicado;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Panel Docente › Comunicados (decidido con el usuario el 24-11-2026):
 * - Recibidos: lo que la secretaría o el Admin manda a toda la sede o a los docentes. Abrir uno lo marca leído.
 * - Mis publicaciones: lo que el docente publica a uno o varios de sus grupos. Un envío a varios grupos es una fila por
 *   grupo con el mismo `lote` (como el Admin con varias sedes): cada grupo cuenta sus destinatarios y lecturas, y la
 *   secretaría ve y archiva la copia de su grupo. Las reglas de guardar son las de la secretaría (ComunicadoService::guardar):
 *   publicar ya, programar o borrador, con adjunto; un publicado cambia su texto pero no sus grupos; solo se eliminan
 *   borradores. Destinatarios = estudiantes con matrícula vigente en el grupo y cuenta activa.
 */
class ComunicadoDocenteService
{
    public function __construct(private ComunicadoService $comunicados) {}

    public function datos(User $d): array
    {
        $grupos = $this->grupos($d);

        return [
            'hoy'        => today()->toDateString(),
            'ahora'      => now()->format('Y-m-d H:i'),
            'categorias' => Comunicado::CATEGORIAS,
            'recibidos'  => $this->recibidos($d),
            'grupos'     => $grupos->map(fn (Curso $c) => [
                'id'       => $c->id,
                'nombre'   => $c->nombre,
                'programa' => $this->corto($c->programa->nombre),
                'abierto'  => $c->estado !== 'finalizado',
            ])->values()->all(),
            'mios'       => $this->mios($d, $grupos),
        ];
    }

    /** Los que le llegan: su sede, a toda la sede o a los docentes, publicados, sin archivar y todavía visibles. */
    public function recibidosDe(User $d): Collection
    {
        if (! $d->sede_id) {
            return collect();
        }

        return Comunicado::where('sede_id', $d->sede_id)
            ->whereNull('archivado_en')
            ->whereIn('estado', ['publicado', 'programado'])
            ->whereIn('destino', ['sede', 'docentes'])
            ->where(fn ($q) => $q->whereNull('visible_hasta')->orWhereDate('visible_hasta', '>=', today()->toDateString()))
            ->with(['autor', 'lectores' => fn ($q) => $q->where('users.id', $d->id)])
            ->get()
            ->filter(fn (Comunicado $c) => $c->estadoActual() === 'publicado')
            ->values();
    }

    private function recibidos(User $d): array
    {
        return $this->recibidosDe($d)
            ->sortByDesc(fn (Comunicado $c) => [(int) $c->fijado, ($c->publicado_en ?? $c->created_at)->timestamp])
            ->values()
            ->map(function (Comunicado $c) {
                $a = $c->autor;

                return [
                    'id'      => $c->id,
                    'titulo'  => $c->titulo,
                    'cuerpo'  => (string) $c->mensaje,
                    'de'      => [
                        'nombre' => $a ? (trim($a->nombres.' '.$a->apellidos) ?: 'Administración de la sede') : 'Administración de la sede',
                        'rol'    => $a?->isAdminGeneral() ? 'Admin' : 'Administrativo',
                    ],
                    'para'    => $c->destino === 'docentes' ? 'Docentes de la sede' : 'Toda la sede',
                    'fecha'   => ($c->publicado_en ?? $c->created_at)->format('Y-m-d H:i'),
                    'fijado'  => (bool) $c->fijado,
                    'urgente' => $c->categoria === 'urgente',
                    'leido'   => $c->lectores->isNotEmpty(),
                    'adjunto' => $c->adjunto_ruta ? ['nombre' => $c->adjunto_nombre, 'tamano' => (int) $c->adjunto_peso, 'url' => route('docente.comunicados.adjunto', $c)] : null,
                ];
            })->all();
    }

    /** Sus grupos (curso_docente), los vigentes primero. */
    public function grupos(User $d): Collection
    {
        return $d->cursosComoDocente()->with('programa')->get()
            ->sortBy(fn (Curso $c) => [$c->estado === 'finalizado' ? 1 : 0, $c->nombre])->values();
    }

    /** Sus envíos (uno por lote), del más nuevo al más viejo, con cada grupo, sus destinatarios y quién leyó. */
    private function mios(User $d, Collection $grupos): array
    {
        $filas = Comunicado::where('autor_id', $d->id)->where('destino', 'grupo')
            ->with(['archivadoPor', 'lectores:id', 'curso.programa'])->orderBy('id')->get();
        $porGrupo = $this->estudiantesPorGrupo($filas->pluck('curso_id')->filter()->unique());

        return $filas->groupBy(fn (Comunicado $c) => $c->lote ?: 'c'.$c->id)
            ->map(function (Collection $lote) use ($porGrupo) {
                $vivas = $lote->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado');
                $c = ($vivas->isNotEmpty() ? $vivas : $lote)->first();

                return [
                    'id'            => $c->id,
                    'titulo'        => $c->titulo,
                    'mensaje'       => (string) $c->mensaje,
                    'categoria'     => $c->categoria,
                    'estado'        => $c->estadoActual(),
                    'publicado_en'  => $c->publicado_en?->format('Y-m-d H:i'),
                    'visible_hasta' => $c->visible_hasta?->toDateString(),
                    'creado'        => $lote->first()->created_at->format('Y-m-d H:i'),
                    'adjunto'       => $c->adjunto_ruta ? ['nombre' => $c->adjunto_nombre, 'tamano' => (int) $c->adjunto_peso, 'url' => route('docente.comunicados.adjunto', $c)] : null,
                    'archivado'     => $vivas->isEmpty() && $c->archivado_en ? [
                        'fecha'  => $c->archivado_en->format('Y-m-d H:i'),
                        'por'    => $c->archivado_por === $c->autor_id ? 'ti' : $this->nombre($c->archivadoPor),
                        'motivo' => $c->motivo_archivo,
                    ] : null,
                    'grupos'        => $lote->map(fn (Comunicado $f) => [
                        'id'        => $f->curso_id,
                        'nombre'    => $f->curso?->nombre ?? 'Grupo',
                        'programa'  => $this->corto($f->curso?->programa?->nombre ?? ''),
                        // Lo archivó la secretaría en ese grupo (el resto del envío sigue)
                        'archivado' => $f->estadoActual() === 'archivado' && $vivas->isNotEmpty() ? [
                            'por' => $this->nombre($f->archivadoPor), 'motivo' => $f->motivo_archivo,
                        ] : null,
                        'personas'  => collect($porGrupo->get($f->curso_id, []))->map(function (array $p) use ($f) {
                            $leido = $f->lectores->firstWhere('id', $p['id']);

                            return $p + ['leido' => $leido ? Carbon::parse($leido->pivot->leido_en)->format('Y-m-d H:i') : null];
                        })->values()->all(),
                    ])->values()->all(),
                ];
            })->sortByDesc('id')->values()->all();
    }

    /** Estudiantes con matrícula vigente y cuenta activa de cada grupo, con su contacto de emergencia (WhatsApp al contacto si es menor). */
    private function estudiantesPorGrupo(Collection $cursoIds): Collection
    {
        return Matricula::whereIn('curso_id', $cursoIds)->whereIn('estado', Matricula::VIGENTES)
            ->whereHas('estudiante', fn ($q) => $q->where('activo', true))
            ->with('estudiante.contactoEmergencia')->get()
            ->sortBy(fn (Matricula $m) => $m->estudiante->nombres.' '.$m->estudiante->apellidos)
            ->groupBy('curso_id')
            ->map(fn (Collection $ms) => $ms->map(function (Matricula $m) {
                $e = $m->estudiante;

                return [
                    'id'        => $e->id,
                    'nombre'    => $this->nombre($e),
                    'celular'   => $this->digitos($e->telefono),
                    'menor'     => $e->fecha_nacimiento !== null && $e->fecha_nacimiento->age < 18,
                    'contacto'  => $e->contactoEmergencia?->paraVista(),
                ];
            })->values()->all());
    }

    /** Las filas del mismo envío (una sola si no tiene lote). */
    public function lote(Comunicado $c): Collection
    {
        return $c->lote ? Comunicado::where('lote', $c->lote)->orderBy('id')->get() : collect([$c]);
    }

    /**
     * Crea (sin $c) o edita un envío a sus grupos. $accion: borrador | publicar | programar. Mientras no esté publicado,
     * los grupos pueden cambiar (se quitan las filas que sobran y se crean las nuevas, con copia del adjunto); publicado,
     * solo cambia el texto de las copias que siguen vivas. Las reglas se vuelven a revisar con las filas bloqueadas.
     *
     * @param  int[]  $grupoIds
     */
    public function guardar(User $d, ?Comunicado $c, array $datos, string $accion, array $grupoIds, ?UploadedFile $archivo, bool $quitarAdjunto): Comunicado
    {
        return DB::transaction(function () use ($d, $c, $datos, $accion, $grupoIds, $archivo, $quitarAdjunto) {
            $sedeId = (int) $d->sede_id;
            $datos = ['destino' => 'grupo', 'fijado' => false] + $datos;

            $filas = $c ? Comunicado::where('autor_id', $d->id)
                ->where(fn ($q) => $c->lote ? $q->where('lote', $c->lote) : $q->where('id', $c->id))
                ->orderBy('id')->lockForUpdate()->get() : collect();
            $vivas = $filas->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado');
            if ($c && $vivas->isEmpty()) {
                throw ValidationException::withMessages(['titulo' => 'Un comunicado archivado no se edita.']);
            }
            $publicado = $vivas->contains(fn (Comunicado $f) => $f->estadoActual() === 'publicado');

            // La primera fila guarda el archivo y las demás una copia: store() repite el mismo nombre para el mismo archivo
            // y, compartido, quitarlo de un grupo lo borraría de los demás
            $subida = null;
            $guardarFila = function (Comunicado $f, string $accion) use ($sedeId, $datos, $archivo, $quitarAdjunto, &$subida) {
                $f = $this->comunicados->guardar($sedeId, $f, $datos, $accion, $subida ? null : $archivo, $quitarAdjunto);
                if ($archivo) {
                    $subida ? $this->copiarAdjunto($subida, $f, $sedeId) : $subida = $f;
                }

                return $f;
            };

            if ($publicado) {
                if ($accion !== 'publicar') {
                    throw ValidationException::withMessages(['titulo' => 'Un comunicado publicado no vuelve a borrador ni se programa.']);
                }
                $vivas->each(fn (Comunicado $f) => $guardarFila($f, 'publicar'));

                return $vivas->first()->fresh();
            }

            // Sin publicar: los grupos pueden cambiar. Los nuevos deben seguir siendo suyos y no estar finalizados.
            $abiertos = $this->grupos($d)->where('estado', '!=', 'finalizado')->pluck('id')->all();
            $nuevos = array_diff($grupoIds, $filas->pluck('curso_id')->all());
            if (array_diff($nuevos, $abiertos)) {
                throw ValidationException::withMessages(['grupos' => 'Elige solo grupos tuyos que no hayan finalizado.']);
            }

            $lote = $c?->lote ?? (string) Str::uuid();
            $plantilla = $vivas->first(fn (Comunicado $f) => $f->adjunto_ruta);
            foreach ($filas as $f) {
                if (! in_array($f->curso_id, $grupoIds, true) && $f->estadoActual() !== 'archivado') {
                    $this->comunicados->eliminar($f);   // nadie lo ha visto: no estaba publicado
                }
            }
            $resultado = null;
            foreach ($grupoIds as $gid) {
                $f = $filas->firstWhere('curso_id', $gid);
                if ($f && $f->estadoActual() === 'archivado') {
                    continue;   // el administrativo archivó la copia de ese grupo: se respeta
                }
                $nueva = ! $f;
                $f ??= new Comunicado(['sede_id' => $sedeId, 'autor_id' => $d->id, 'curso_id' => $gid]);
                $f->lote = $lote;
                $f = $guardarFila($f, $accion);
                if ($nueva && ! $archivo && ! $quitarAdjunto && $plantilla) {
                    $this->copiarAdjunto($plantilla, $f, $sedeId);
                }
                $resultado ??= $f;
            }

            return ($resultado ?? $vivas->first())->fresh();
        });
    }

    /** Archiva las copias que siguen vivas (una que archivó la secretaría conserva su motivo). */
    public function archivar(Comunicado $c, string $motivo): void
    {
        DB::transaction(fn () => $this->lote($c)->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado')
            ->each(fn (Comunicado $f) => $this->comunicados->archivar($f, $motivo)));
    }

    /** Solo borradores (nadie los ha visto): se van todas las filas del envío, con sus adjuntos. */
    public function eliminar(Comunicado $c): void
    {
        DB::transaction(fn () => $this->lote($c)->each(fn (Comunicado $f) => $this->comunicados->eliminar($f)));
    }

    /** Para quién es el envío, en palabras: «Grupo A (Mecánica) y Grupo B (Electricidad)». */
    public function para(Comunicado $c): string
    {
        return $this->lote($c)->load('curso.programa')
            ->map(fn (Comunicado $f) => ($f->curso?->nombre ?? 'un grupo').' ('.$this->corto($f->curso?->programa?->nombre ?? '').')')
            ->join(', ', ' y ');
    }

    /** Cada fila de un envío guarda su propia copia del adjunto (borrar una no deja a otra sin él); reemplaza la que tenía. */
    private function copiarAdjunto(Comunicado $origen, Comunicado $destino, int $sedeId): void
    {
        $disco = Storage::disk(ComunicadoService::disco());
        if (! $disco->exists($origen->adjunto_ruta)) {
            return;
        }
        $ruta = "comunicados/{$sedeId}/".Str::random(40).'.'.pathinfo($origen->adjunto_ruta, PATHINFO_EXTENSION);
        $disco->copy($origen->adjunto_ruta, $ruta);
        $viejo = $destino->adjunto_ruta;
        if ($viejo && $viejo !== $ruta) {
            DB::afterCommit(fn () => $disco->delete($viejo));
        }
        $destino->update([
            'adjunto_ruta'   => $ruta,
            'adjunto_nombre' => $origen->adjunto_nombre,
            'adjunto_tipo'   => $origen->adjunto_tipo,
            'adjunto_peso'   => $origen->adjunto_peso,
        ]);
    }

    private function nombre(?User $u): string
    {
        return $u ? (trim($u->nombres.' '.$u->apellidos) ?: 'Administrativo') : 'Administrativo';
    }

    private function corto(string $programa): string
    {
        return str_replace('Técnico Laboral en ', '', $programa);
    }

    private function digitos(?string $tel): string
    {
        return preg_replace('/\D/', '', (string) $tel);
    }
}
