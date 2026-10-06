<?php

namespace App\Http\Controllers\Admin\Docentes;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Docentes\DocenteRequest;
use App\Models\Academico\DocentePerfil;
use App\Models\Academico\Escuela;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DocenteController extends Controller
{
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function index(): View
    {
        $docentes = User::where('role', RolUsuario::Docente)
            ->where('sede_id', $this->sedeId())
            ->whereHas('docentePerfil')
            ->with(['docentePerfil.escuela', 'cursosComoDocente.programa'])
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();

        return view('admin.docentes.index', [
            'docentes' => $docentes->map(fn (User $d) => $this->paraVista($d))->values(),
            'escuelas' => Escuela::orderBy('nombre')->get(['id', 'nombre', 'sigla']),
        ]);
    }

    public function store(DocenteRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $docente = DB::transaction(function () use ($datos) {
            $docente = User::create($this->datosUsuario($datos) + [
                'role'     => RolUsuario::Docente,
                'sede_id'  => $this->sedeId(),
                // Convención del proyecto: la clave inicial es el número de documento
                'password' => Hash::make($datos['numero_documento']),
            ]);
            $docente->docentePerfil()->create($this->datosPerfil($datos));

            return $docente;
        });

        Auditoria::registrar('crear', $docente, "Registró al docente {$docente->name} ({$docente->numero_documento}).");

        return response()->json([
            'ok'      => true,
            'mensaje' => "Se registró a {$docente->name}. Usuario y contraseña inicial: {$docente->numero_documento}.",
        ]);
    }

    public function update(DocenteRequest $request, User $docente): JsonResponse
    {
        $this->asegurarDocenteDeSede($docente);
        $datos = $request->validated();

        DB::transaction(function () use ($docente, $datos) {
            $docente->update($this->datosUsuario($datos));
            $docente->docentePerfil()->update($this->datosPerfil($datos));
        });

        Auditoria::registrar('editar', $docente, "Editó los datos del docente {$docente->name}.");

        return response()->json(['ok' => true, 'mensaje' => "Se guardaron los cambios de {$docente->name}."]);
    }

    /** Activo ⇄ inactivo (un docente en proceso pasa a activo). */
    public function cambiarEstado(User $docente): JsonResponse
    {
        $this->asegurarDocenteDeSede($docente);

        $nuevo = $docente->docentePerfil->estado === 'activo' ? 'inactivo' : 'activo';
        DB::transaction(function () use ($docente, $nuevo) {
            $docente->docentePerfil->update(['estado' => $nuevo]);
            $docente->update(['activo' => $nuevo === 'activo']);
        });

        $verbo = $nuevo === 'activo' ? 'Activó' : 'Desactivó';
        Auditoria::registrar($nuevo === 'activo' ? 'activar' : 'desactivar', $docente, "{$verbo} al docente {$docente->name}.");

        return response()->json([
            'ok'      => true,
            'estado'  => $nuevo,
            'mensaje' => $nuevo === 'activo' ? "La cuenta de {$docente->name} quedó activa." : "La cuenta de {$docente->name} quedó inactiva.",
        ]);
    }

    /* ── Auxiliares ── */

    private function sedeId(): int
    {
        $sedeId = auth()->user()->sede_id;
        abort_if(! $sedeId, 403, 'Tu usuario no tiene una sede asignada.');

        return $sedeId;
    }

    /** El admin solo gestiona docentes de su sede. */
    private function asegurarDocenteDeSede(User $docente): void
    {
        abort_unless($docente->isDocente() && $docente->sede_id === $this->sedeId() && $docente->docentePerfil, 404);
    }

    private function datosUsuario(array $d): array
    {
        return [
            'nombres'                 => $d['nombres'],
            'apellidos'               => $d['apellidos'],
            'tipo_documento'          => $d['tipo_documento'],
            'numero_documento'        => $d['numero_documento'],
            'email'                   => $d['email'],
            'telefono'                => $d['telefono'],
            'fecha_nacimiento'        => $d['fecha_nacimiento'] ?? null,
            'direccion'               => $d['direccion'] ?? null,
            'departamento_residencia' => $d['departamento_residencia'] ?? null,
            'ciudad_residencia'       => $d['ciudad_residencia'] ?? null,
            'profesion'               => $d['profesion'],
            'titulo_academico'        => $d['titulo_academico'],
            // Solo un docente activo puede ingresar al sistema
            'activo'                  => $d['estado'] === 'activo',
        ];
    }

    private function datosPerfil(array $d): array
    {
        return [
            'escuela_id'  => $d['escuela_id'],
            'vinculacion' => $d['vinculacion'],
            'estado'      => $d['estado'],
        ];
    }

    /** Forma que espera docentes.js. */
    private function paraVista(User $d): array
    {
        $perfil = $d->docentePerfil;
        $fecha  = $d->fecha_nacimiento;
        $tel    = (string) $d->telefono;

        return [
            'id'             => $d->id,
            'nombres'        => $d->nombres,
            'apellidos'      => $d->apellidos,
            'tipo_doc'       => $d->tipo_documento,
            'documento'      => $d->numero_documento,
            'cedula'         => ctype_digit((string) $d->numero_documento)
                ? number_format((int) $d->numero_documento, 0, ',', '.')
                : $d->numero_documento,
            'email'          => $d->email,
            'telefono_raw'   => $tel,
            'telefono'       => strlen($tel) === 10 ? substr($tel, 0, 3).' '.substr($tel, 3, 3).' '.substr($tel, 6) : $tel,
            'fecha_nac_iso'  => $fecha?->format('Y-m-d'),
            'fecha_nac'      => $fecha ? $fecha->format('d').' '.self::MESES[$fecha->month - 1].' '.$fecha->format('Y') : '—',
            'direccion_raw'  => $d->direccion,
            'depto'          => $d->departamento_residencia,
            'ciudad'         => $d->ciudad_residencia,
            'direccion'      => collect([$d->direccion, $d->ciudad_residencia])->filter()->implode(', ') ?: '—',
            'escuela_id'     => $perfil->escuela_id,
            'escuela'        => $perfil->escuela->nombre,
            'sigla'          => mb_strtolower($perfil->escuela->sigla),
            'titulo'         => $d->profesion,
            'especialidad'   => $d->titulo_academico,
            'vinculacion'    => $perfil->vinculacion,
            'estado'         => $perfil->estado,
            // Programas y cursos salen de curso_docente (se llenan con el módulo de Cursos)
            'programas'      => $d->cursosComoDocente->pluck('programa.nombre')->filter()->unique()->values(),
            'cursos'         => $d->cursosComoDocente->map(fn ($c) => [
                'nombre'   => $c->nombre,
                'programa' => $c->programa?->nombre ?? '—',
                'grupo'    => '—',
                'horario'  => '—',
            ])->values(),
        ];
    }
}
