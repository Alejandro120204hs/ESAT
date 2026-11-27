<?php

namespace App\Services\Comunicacion;

use App\Enums\RolUsuario;
use App\Models\Academico\Curso;
use App\Models\Comunicacion\Comunicado;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Support\Archivos;
use Carbon\CarbonInterface;
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
 * docentes = cuentas activas de la sede; grupo = estudiantes con matrícula vigente en ese grupo. El WhatsApp de un
 * estudiante menor va a su contacto de emergencia.
 */
class ComunicadoService
{
    private const LETRA = ['estudiante' => 'e', 'docente' => 'd'];

    public function datosSede(int $sedeId): array
    {
        return [
            'personas'    => $this->personas($sedeId),
            'grupos'      => $this->grupos($sedeId),
            'comunicados' => Comunicado::where('sede_id', $sedeId)
                ->where(fn ($q) => $q->where('destino', '!=', 'grupo')->orWhere('estado', '!=', 'borrador'))   // el borrador de un docente es solo suyo
                ->with(['autor', 'archivadoPor', 'lectores'])
                ->orderByDesc('id')->get()
                ->map(fn (Comunicado $c) => $this->comunicado($c))->values(),
        ];
    }

    /** Las personas que pueden recibir comunicados en la sede, en el formato de comunicados.js. */
    public function personas(int $sedeId): array
    {
        $matriculas = Matricula::where('sede_id', $sedeId)->whereIn('estado', Matricula::VIGENTES)
            ->whereHas('estudiante', fn ($q) => $q->where('activo', true))
            ->with(['estudiante.contactoEmergencia', 'curso', 'programa'])
            ->get()->sortBy(fn (Matricula $m) => $m->estudiante->nombres.' '.$m->estudiante->apellidos);

        return [
            'estudiantes' => $matriculas->map(function (Matricula $m) {
                $e = $m->estudiante;

                return [
                    'id'        => $e->id,
                    'nombre'    => $this->nombre($e),
                    'documento' => $e->numero_documento,
                    'celular'   => $this->digitos($e->telefono),
                    'menor'     => $e->fecha_nacimiento !== null && $e->fecha_nacimiento->age < 18,
                    'grupo_id'  => $m->curso_id,
                    'detalle'   => $this->corto($m->programa->nombre).($m->curso ? ' · '.$m->curso->nombre : ''),
                    'contacto'  => $e->contactoEmergencia?->paraVista(),
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
            // Lo envió el Admin desde el panel general (se edita y elimina allá, entero) o es del Admin y quien mira es la
            // secretaría: aquí solo se archiva
            // (un docente también usa lote cuando publica a varios grupos: esos son "del docente", no del general)
            'solo_general'  => ($c->lote !== null && $c->esDelAdmin()) || ($autor->isAdminGeneral() && ! auth()->user()?->isAdminGeneral()),
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
                    'adjunto_nombre' => Archivos::nombre($archivo),
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

    /**
     * Lo contrario de personas(): los comunicados que le llegan a un estudiante, con la misma regla de destinatarios.
     * Publicados y sin archivar de su sede, para toda la sede o para estudiantes, y los de su docente a su grupo; solo con
     * matrícula vigente y cuenta activa. Lo usan su panel y la descarga del adjunto (antes vivía en el panel, ES28).
     */
    public function recibidosPorEstudiante(User $estudiante, ?Matricula $m, ?CarbonInterface $ahora = null): Collection
    {
        if (! $m || ! in_array($m->estado, Matricula::VIGENTES, true) || ! $estudiante->activo || ! $estudiante->sede_id) {
            return collect();
        }
        $ahora ??= now();
        $hoy = $ahora->toDateString();

        return Comunicado::where('sede_id', $estudiante->sede_id)
            ->whereNull('archivado_en')
            ->whereIn('estado', ['publicado', 'programado'])
            ->where(fn ($q) => $q->whereIn('destino', ['sede', 'estudiantes'])
                ->orWhere(fn ($q2) => $q2->where('destino', 'grupo')->where('curso_id', $m->curso_id)))
            ->where(fn ($q) => $q->whereNull('visible_hasta')->orWhereDate('visible_hasta', '>=', $hoy))
            ->with(['autor', 'curso.programa', 'lectores' => fn ($q) => $q->where('users.id', $estudiante->id)])
            ->get()
            ->filter(fn (Comunicado $c) => $c->estadoActual($ahora) === 'publicado')
            ->values();
    }

    /** Abrir un comunicado que le llegó lo marca leído; la primera lectura no cambia (paneles Estudiante y Docente). */
    public function marcarLeido(Comunicado $c, User $u): void
    {
        DB::table('comunicado_lecturas')->insertOrIgnore([
            'comunicado_id' => $c->id, 'user_id' => $u->id, 'leido_en' => now(),
        ]);
    }

    public static function disco(): string
    {
        return Comunicado::DISCO;
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
