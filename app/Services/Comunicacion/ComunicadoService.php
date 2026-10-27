<?php

namespace App\Services\Comunicacion;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Comunicacion\Comunicado;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Panel Admin › Comunicados de una sede: quién recibe cada comunicado (se calcula con la gente que hoy
 * pertenece a ese rol o grupo), quién lo ha leído, y crear / editar / archivar / eliminar los del admin.
 * Las cuentas (porcentajes, pestañas, filtros) se hacen en comunicados.js con estos datos.
 *
 * Destinatarios: estudiantes = con matrícula vigente (activa o aplazada) en la sede y cuenta activa;
 * docentes y acudientes = cuentas activas de la sede; grupo = estudiantes con matrícula vigente en ese grupo.
 */
class ComunicadoService
{
    private const LETRA = ['estudiante' => 'e', 'docente' => 'd', 'acudiente' => 'a'];

    public function datosSede(int $sedeId): array
    {
        return [
            'personas'    => $this->personas($sedeId),
            'grupos'      => $this->grupos($sedeId),
            'comunicados' => Comunicado::where('sede_id', $sedeId)
                ->with(['autor', 'archivadoPor', 'lectores'])
                ->orderByDesc('id')->get()
                ->map(fn (Comunicado $c) => $this->comunicado($c))->values(),
        ];
    }

    /** Las personas que pueden recibir comunicados en la sede, en el formato de comunicados.js. */
    public function personas(int $sedeId): array
    {
        $matriculas = Matricula::where('sede_id', $sedeId)->whereIn('estado', ['activo', 'aplazado'])
            ->whereHas('estudiante', fn ($q) => $q->where('activo', true))
            ->with(['estudiante.acudientes', 'curso', 'programa'])
            ->get()->sortBy(fn (Matricula $m) => $m->estudiante->nombres.' '.$m->estudiante->apellidos);

        $acudientes = User::where('role', RolUsuario::Acudiente)->where('sede_id', $sedeId)->where('activo', true)
            ->with('estudiantesACargo')->orderBy('nombres')->get();

        return [
            'estudiantes' => $matriculas->map(function (Matricula $m) {
                $e = $m->estudiante;
                $a = $e->acudientes->first();

                return [
                    'id'        => $e->id,
                    'nombre'    => $this->nombre($e),
                    'documento' => $e->numero_documento,
                    'celular'   => $this->digitos($e->telefono),
                    'menor'     => $e->fecha_nacimiento !== null && $e->fecha_nacimiento->age < 18,
                    'grupo_id'  => $m->curso_id,
                    'detalle'   => $this->corto($m->programa->nombre).($m->curso ? ' · '.$m->curso->nombre : ''),
                    'acudiente' => $a ? ['nombre' => $this->nombre($a), 'celular' => $this->digitos($a->telefono), 'parentesco' => $a->pivot->parentesco] : null,
                ];
            })->values(),
            'docentes'    => User::where('role', RolUsuario::Docente)->where('sede_id', $sedeId)->where('activo', true)
                ->with('docentePerfil.escuela')->orderBy('nombres')->get()
                ->map(fn (User $d) => [
                    'id'      => $d->id,
                    'nombre'  => $this->nombre($d),
                    'celular' => $this->digitos($d->telefono),
                    'detalle' => $d->docentePerfil?->escuela?->nombre ?? 'Sin escuela',
                ])->values(),
            'acudientes'  => $acudientes->map(function (User $a) {
                $p = $a->estudiantesACargo->first();
                $hijos = $a->estudiantesACargo->map(fn (User $e) => explode(' ', $e->nombres)[0])->join(', ', ' y ');

                return [
                    'id'      => $a->id,
                    'nombre'  => $this->nombre($a),
                    'celular' => $this->digitos($a->telefono),
                    'detalle' => ($p?->pivot->parentesco ?? 'Acudiente').' de '.($hijos ?: 'un estudiante'),
                ];
            })->values(),
        ];
    }

    /** Grupos de la sede con su docente (para mostrar los comunicados que publican los docentes). */
    public function grupos(int $sedeId): Collection
    {
        return Curso::where('sede_id', $sedeId)->with(['programa', 'docentes'])->orderBy('id')->get()
            ->map(fn (Curso $c) => [
                'id'         => $c->id,
                'nombre'     => $c->nombre,
                'programa'   => $this->corto($c->programa->nombre),
                'docente_id' => $c->docentes->first()?->id,
            ])->values();
    }

    /** Mismo formato que lee comunicados.js. lecturas: {'e26': 'AAAA-MM-DD HH:MM', 'd9': …}. */
    public function comunicado(Comunicado $c): array
    {
        $autor = $c->autor;

        return [
            'id'            => $c->id,
            'titulo'        => $c->titulo,
            'mensaje'       => (string) $c->mensaje,
            'categoria'     => $c->categoria,
            'destino'       => $c->destino,
            'grupo_id'      => $c->curso_id,
            'autor'         => [
                'tipo'   => $autor->role === RolUsuario::Docente ? 'docente' : 'admin',
                'id'     => $autor->id,
                'nombre' => $this->nombre($autor),
            ],
            'estado'        => $c->estado,
            'publicado_en'  => $c->publicado_en?->format('Y-m-d H:i'),
            'visible_hasta' => $c->visible_hasta?->toDateString(),
            'fijado'        => $c->fijado,
            'adjunto'       => $c->adjunto_ruta ? [
                'nombre' => $c->adjunto_nombre,
                'tipo'   => $c->adjunto_tipo,
                'peso'   => (int) $c->adjunto_peso,
                'url'    => route('admin.comunicados.adjunto', $c),
            ] : null,
            'creado'        => $c->created_at->format('Y-m-d H:i'),
            'archivado'     => $c->archivado_en ? [
                'fecha'  => $c->archivado_en->format('Y-m-d H:i'),
                'por'    => $this->nombre($c->archivadoPor),
                'motivo' => $c->motivo_archivo,
            ] : null,
            'lecturas'      => $c->lectores->mapWithKeys(fn (User $u) => isset(self::LETRA[$u->role->value])
                ? [self::LETRA[$u->role->value].$u->id => Carbon::parse($u->pivot->leido_en)->format('Y-m-d H:i')]
                : [])->all(),
        ];
    }

    /**
     * Crea (sin $c) o edita un comunicado del admin. $accion: borrador | publicar | programar.
     * Un publicado conserva su fecha de publicación y sus destinatarios.
     */
    public function guardar(int $sedeId, ?Comunicado $c, array $datos, string $accion, ?UploadedFile $archivo, bool $quitarAdjunto): Comunicado
    {
        return DB::transaction(function () use ($sedeId, $c, $datos, $accion, $archivo, $quitarAdjunto) {
            $c ??= new Comunicado(['sede_id' => $sedeId, 'autor_id' => auth()->id()]);
            $yaPublicado = $c->exists && $c->estadoActual() === 'publicado';

            $c->fill([
                'titulo'        => $datos['titulo'],
                'mensaje'       => $datos['mensaje'] ?? null,
                'categoria'     => $datos['categoria'] ?? 'general',
                'destino'       => $yaPublicado ? $c->destino : $datos['destino'],
                'visible_hasta' => $datos['visible_hasta'] ?? null,
                'fijado'        => (bool) ($datos['fijado'] ?? false),
            ]);
            if (! $yaPublicado) {
                $c->estado       = ['borrador' => 'borrador', 'programar' => 'programado', 'publicar' => 'publicado'][$accion];
                $c->publicado_en = match ($accion) {
                    'borrador'  => null,
                    'programar' => $datos['publicar_en'],
                    default     => now(),
                };
            } elseif ($c->estado === 'programado') {
                $c->estado = 'publicado'; // su hora ya llegó
            }

            $viejo = $c->adjunto_ruta;
            if ($archivo) {
                $c->fill([
                    'adjunto_ruta'   => $archivo->store("comunicados/{$sedeId}", self::disco()),
                    'adjunto_nombre' => mb_substr($archivo->getClientOriginalName(), 0, 150),
                    'adjunto_tipo'   => strtolower($archivo->getClientOriginalExtension()) === 'pdf' ? 'pdf' : 'imagen',
                    'adjunto_peso'   => $archivo->getSize(),
                ]);
            } elseif ($quitarAdjunto) {
                $c->fill(['adjunto_ruta' => null, 'adjunto_nombre' => null, 'adjunto_tipo' => null, 'adjunto_peso' => null]);
            }
            $c->save();

            if ($viejo && $viejo !== $c->adjunto_ruta) {
                DB::afterCommit(fn () => Storage::disk(self::disco())->delete($viejo));
            }

            return $c->load(['autor', 'archivadoPor', 'lectores']);
        });
    }

    public function archivar(Comunicado $c, string $motivo): Comunicado
    {
        $c->update([
            'estado'         => 'archivado',
            'archivado_en'   => now(),
            'archivado_por'  => auth()->id(),
            'motivo_archivo' => trim($motivo),
        ]);

        return $c->load(['autor', 'archivadoPor', 'lectores']);
    }

    /** Solo borradores (nadie los ha visto): se va también el adjunto. */
    public function eliminar(Comunicado $c): void
    {
        $ruta = $c->adjunto_ruta;
        $c->delete();
        if ($ruta) {
            Storage::disk(self::disco())->delete($ruta);
        }
    }

    public static function disco(): string
    {
        return Comunicado::DISCO;
    }

    private function nombre(?User $u): string
    {
        return $u ? (trim($u->nombres.' '.$u->apellidos) ?: 'Administrador') : 'Administrador';
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
