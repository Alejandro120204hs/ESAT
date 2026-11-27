<?php

namespace App\Services\Comunicacion;

use App\Enums\RolUsuario;
use App\Models\Comunicacion\ForoMensaje;
use App\Models\Comunicacion\ForoReporte;
use App\Models\Comunicacion\ForoTema;
use App\Models\Finanzas\Matricula;
use App\Models\User;
use App\Support\Archivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Panel Admin › Foro de una sede: los temas con sus mensajes y reportes, quién es cada persona, y lo que hace la
 * administración (abrir temas, responder, fijar, cerrar, ocultar, restaurar, descartar reportes). Las cuentas
 * (KPI, pestañas, filtros) se hacen en foro.js con estos datos.
 *
 * Cada persona va con una clave corta: e (estudiante), d (docente), x (administración) + su id.
 */
class ForoService
{
    private const LETRA = ['estudiante' => 'e', 'docente' => 'd', 'admin' => 'x', 'admin_general' => 'x', 'superadmin' => 'x'];

    public function datosSede(int $sedeId): array
    {
        $temas = $this->temasDe($sedeId);
        $ids = $temas->flatMap(fn (ForoTema $t) => $t->mensajes->flatMap(fn (ForoMensaje $m) => array_merge(
            [$m->autor_id, $m->oculto_por], $m->reportes->pluck('user_id')->all()
        ))->push($t->cerrado_por))->push(auth()->id())->filter()->unique()->values();

        return [
            'personas' => $this->personas($ids),
            'temas'    => $temas->map(fn (ForoTema $t) => $this->tema($t))->values(),
        ];
    }

    /**
     * El foro de su sede como lo ve quien participa desde su panel (estudiante o docente): sin lo que ocultó la
     * secretaría (un tema cuyo primer mensaje está oculto no se ve) y con lo que esa persona ya reportó.
     * $rutaAdjunto: la ruta de su panel que descarga el adjunto de un mensaje.
     */
    public function panel(User $u, string $rutaAdjunto): array
    {
        $temas = $u->sede_id ? ForoTema::where('sede_id', $u->sede_id)
            ->with(['mensajes.autor', 'mensajes.reportes' => fn ($q) => $q->where('user_id', $u->id)])
            ->orderBy('id')->get() : collect();

        return [
            'categorias' => array_values(ForoTema::CATEGORIAS),
            'claves'     => array_flip(ForoTema::CATEGORIAS),
            'temas'      => $temas->reject(fn (ForoTema $t) => $t->oculto())->map(function (ForoTema $t) use ($u, $rutaAdjunto) {
                $mensajes = $t->mensajes->sortBy('id')->reject(fn (ForoMensaje $m) => $m->oculto())->values()
                    ->map(fn (ForoMensaje $m) => [
                        'id'        => $m->id,
                        'autor'     => [
                            'nombre' => $m->autor ? (trim($m->autor->nombres.' '.$m->autor->apellidos) ?: 'Administración de la sede') : 'Administración de la sede',
                            'rol'    => self::LETRA[$m->autor?->role->value ?? 'admin'] ?? 'x',
                            'yo'     => $m->autor_id === $u->id,
                        ],
                        'fecha'     => $m->created_at->format('Y-m-d H:i'),
                        'texto'     => $m->texto,
                        'adjunto'   => $m->adjunto_ruta ? ['nombre' => $m->adjunto_nombre, 'tamano' => (int) $m->adjunto_peso, 'url' => route($rutaAdjunto, $m)] : null,
                        'reportado' => $m->reportes->isNotEmpty(),
                    ]);
                if ($mensajes->isEmpty()) {
                    return null;
                }

                return [
                    'id'        => $t->id,
                    'categoria' => ForoTema::CATEGORIAS[$t->categoria] ?? $t->categoria,
                    'titulo'    => $t->titulo,
                    'fijado'    => (bool) $t->fijado,
                    'cerrado'   => $t->cerrado(),
                    'mensajes'  => $mensajes->all(),
                    'ultimo'    => $mensajes->last()['fecha'],
                    'mio'       => $mensajes->first()['autor']['yo'],
                ];
            })->filter()->values()->all(),
        ];
    }

    private function temasDe(int $sedeId): Collection
    {
        return ForoTema::where('sede_id', $sedeId)
            ->with(['mensajes.autor', 'mensajes.ocultoPor', 'mensajes.reportes.usuario', 'cerradoPor'])
            ->orderBy('id')->get();
    }

    /** Nombre y detalle de cada persona que aparece en el foro: {'e26': [nombre, 'Programa · Grupo'], …}. */
    public function personas(Collection $ids): array
    {
        $usuarios = User::whereIn('id', $ids)->with(['docentePerfil.escuela'])->get();
        $matriculas = Matricula::whereIn('estudiante_id', $usuarios->where('role', RolUsuario::Estudiante)->pluck('id'))
            ->where('estado', '!=', 'anulado')->with(['programa', 'curso'])->orderBy('id')->get()
            ->groupBy('estudiante_id')
            ->map(fn ($ms) => $ms->sortByDesc(fn (Matricula $m) => [in_array($m->estado, ['activo', 'aplazado']), $m->id])->first());

        return $usuarios->mapWithKeys(function (User $u) use ($matriculas) {
            $detalle = match ($u->role) {
                RolUsuario::Estudiante => ($m = $matriculas->get($u->id))
                    ? str_replace('Técnico Laboral en ', '', $m->programa->nombre).($m->curso ? ' · '.$m->curso->nombre : '') : 'Estudiante',
                RolUsuario::Docente   => $u->docentePerfil?->escuela?->nombre ?? 'Docente',
                RolUsuario::AdminGeneral => 'Admin de ESAT',
                default               => 'Administrativo',
            };

            return [$this->clave($u) => [$this->nombre($u), $detalle]];
        })->all();
    }

    /** Mismo formato que lee foro.js. */
    public function tema(ForoTema $t): array
    {
        return [
            'id'        => $t->id,
            'categoria' => $t->categoria,
            'titulo'    => $t->titulo,
            'fijado'    => $t->fijado,
            'cerrado'   => $t->cerrado_en ? ['fecha' => $t->cerrado_en->format('Y-m-d H:i'), 'por' => $this->clave($t->cerradoPor)] : null,
            'mensajes'  => $t->mensajes->map(fn (ForoMensaje $m) => [
                'id'       => $m->id,
                'autor'    => $this->clave($m->autor),
                'fecha'    => $m->created_at->format('Y-m-d H:i'),
                'texto'    => $m->texto,
                'adjunto'  => $m->adjunto_ruta ? [
                    'nombre' => $m->adjunto_nombre,
                    'tipo'   => $m->adjunto_tipo,
                    'peso'   => (int) $m->adjunto_peso,
                    'url'    => route('admin.foro.adjunto', $m),
                ] : null,
                'oculto'   => $m->oculto_en ? ['fecha' => $m->oculto_en->format('Y-m-d H:i'), 'por' => $this->clave($m->ocultoPor), 'motivo' => $m->motivo_oculto] : null,
                'reportes' => $m->reportes->map(fn (ForoReporte $r) => [
                    'por'    => $this->clave($r->usuario),
                    'fecha'  => $r->created_at->format('Y-m-d H:i'),
                    'motivo' => $r->motivo,
                    'estado' => $r->estado,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** El tema recargado con todo lo que pinta foro.js. */
    public function cargar(ForoTema $t): ForoTema
    {
        return $t->load(['mensajes.autor', 'mensajes.ocultoPor', 'mensajes.reportes.usuario', 'cerradoPor']);
    }

    public function crearTema(int $sedeId, array $datos, ?UploadedFile $archivo): ForoTema
    {
        return DB::transaction(function () use ($sedeId, $datos, $archivo) {
            $t = ForoTema::create([
                'sede_id' => $sedeId, 'autor_id' => auth()->id(), 'categoria' => $datos['categoria'],
                'titulo' => $datos['titulo'], 'fijado' => (bool) ($datos['fijado'] ?? false),
            ]);
            $this->nuevoMensaje($t, $datos['texto'], $archivo);

            return $this->cargar($t);
        });
    }

    public function responder(ForoTema $t, string $texto, ?UploadedFile $archivo): ForoTema
    {
        DB::transaction(function () use ($t, $texto, $archivo) {
            // Se vuelve a revisar con el tema bloqueado: otra persona pudo cerrarlo u ocultarlo al mismo tiempo (M13; la
            // Form Request ya lo revisó antes, sin bloquear; auditoría del panel Estudiante, ES21)
            $tema = ForoTema::whereKey($t->id)->lockForUpdate()->with('mensajes')->firstOrFail();
            if ($tema->cerrado()) {
                throw ValidationException::withMessages(['texto' => 'El tema está cerrado: nadie puede responder.']);
            }
            if ($tema->oculto()) {
                throw ValidationException::withMessages(['texto' => 'El tema está oculto: no recibe respuestas.']);
            }
            $this->nuevoMensaje($tema, $texto, $archivo);
            $tema->touch();
        });

        return $this->cargar($t);
    }

    public function fijar(ForoTema $t, bool $si): ForoTema
    {
        $t->update(['fijado' => $si]);

        return $this->cargar($t);
    }

    public function cerrar(ForoTema $t, bool $si): ForoTema
    {
        $t->update(['cerrado_en' => $si ? now() : null, 'cerrado_por' => $si ? auth()->id() : null]);

        return $this->cargar($t);
    }

    /** Ocultar atiende los reportes pendientes del mensaje. */
    public function ocultar(ForoMensaje $m, string $motivo): ForoTema
    {
        DB::transaction(function () use ($m, $motivo) {
            // El tema primero: una respuesta que llega a la vez espera y ve el tema oculto (responder)
            ForoTema::whereKey($m->tema_id)->lockForUpdate()->first();
            $m->update(['oculto_en' => now(), 'oculto_por' => auth()->id(), 'motivo_oculto' => trim($motivo)]);
            $m->reportes()->where('estado', 'pendiente')->update(['estado' => 'atendido', 'revisado_por' => auth()->id(), 'revisado_en' => now()]);
        });

        return $this->cargar($m->tema);
    }

    public function restaurar(ForoMensaje $m): ForoTema
    {
        $m->update(['oculto_en' => null, 'oculto_por' => null, 'motivo_oculto' => null]);

        return $this->cargar($m->tema);
    }

    public function descartarReportes(ForoMensaje $m): ForoTema
    {
        $m->reportes()->where('estado', 'pendiente')->update(['estado' => 'descartado', 'revisado_por' => auth()->id(), 'revisado_en' => now()]);

        return $this->cargar($m->tema);
    }

    /**
     * Abren temas, responden y reportan los estudiantes con matrícula vigente (un retirado o graduado conserva su cuenta y
     * lee, pero ya no escribe). Los docentes y la administración siempre participan. La usan las Form Requests del foro del
     * estudiante (ExigeMatriculaVigente) y su panel (antes vivía en el trait de Http\Requests, ES28).
     */
    public static function participa(User $u): bool
    {
        return ! $u->isEstudiante() || Matricula::where('estudiante_id', $u->id)->whereIn('estado', Matricula::VIGENTES)->exists();
    }

    /** Un reporte por persona y mensaje (la Form Request revisa que no lo haya reportado ya); paneles Estudiante y Docente. */
    public function reportar(ForoMensaje $mensaje, User $u, string $motivo): ForoReporte
    {
        return ForoReporte::create(['mensaje_id' => $mensaje->id, 'user_id' => $u->id, 'motivo' => $motivo]);
    }

    public static function disco(): string
    {
        return ForoMensaje::DISCO;
    }

    private function nuevoMensaje(ForoTema $t, string $texto, ?UploadedFile $archivo): ForoMensaje
    {
        return ForoMensaje::create([
            'tema_id'  => $t->id,
            'autor_id' => auth()->id(),
            'texto'    => trim($texto),
        ] + ($archivo ? [
            'adjunto_ruta'   => $archivo->store("foro/{$t->sede_id}", self::disco()),
            'adjunto_nombre' => Archivos::nombre($archivo),
            'adjunto_tipo'   => strtolower($archivo->getClientOriginalExtension()) === 'pdf' ? 'pdf' : 'imagen',
            'adjunto_peso'   => $archivo->getSize(),
        ] : []));
    }

    private function clave(?User $u): string
    {
        return $u ? (self::LETRA[$u->role->value] ?? 'x').$u->id : 'x0';
    }

    private function nombre(User $u): string
    {
        return trim($u->nombres.' '.$u->apellidos) ?: 'Administrativo';
    }
}
