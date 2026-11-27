<?php

namespace App\Http\Controllers\AdminGeneral\Docentes;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Docentes\DocenteRequest as DocenteSedeRequest;
use App\Http\Requests\AdminGeneral\Docentes\DocenteRequest;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\General\EstructuraService;
use App\Services\General\PersonasService;
use App\Services\Sistema\SesionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Docentes de todas las sedes: registrar y editar en la sede elegida (reglas de la secretaría), activar o desactivar
 * (no con grupos vigentes) y restablecer la clave. Asignarles grupos y horarios se hace entrando a la sede.
 */
class DocenteController extends Controller
{
    public function index(PersonasService $personas, EstructuraService $estructura): View
    {
        [$docentes, $grupos] = $personas->docentes();

        return view('admin_general.docentes.index', ['datos' => [
            'sedes'     => collect($estructura->sedes())->map(fn ($s) => collect($s)->only(['id', 'nombre', 'ciudad']))->all(),
            'escuelas'  => $estructura->escuelas(),
            'programas' => collect($estructura->programas())->map(fn ($p) => collect($p)->only(['id', 'nombre', 'escuela_id']))->all(),
            'docentes'  => $docentes,
            'grupos'    => $grupos,
        ]]);
    }

    public function store(DocenteRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $sede = Sede::findOrFail($datos['sede_id']);
        $docente = DB::transaction(function () use ($datos) {
            $docente = User::create($this->datosUsuario($datos) + [
                'role'     => RolUsuario::Docente,
                'password' => Hash::make($datos['numero_documento']),   // la clave inicial es el número de documento
            ]);
            $docente->docentePerfil()->create($this->datosPerfil($datos));

            return $docente;
        });
        Auditoria::registrar('crear', $docente, "Registró al docente {$docente->name} ({$docente->numero_documento}) en la sede {$sede->nombre}.", $sede->id);

        return response()->json(['mensaje' => "Se registró a {$docente->name}. Su clave inicial es su número de documento.", 'id' => $docente->id], 201);
    }

    public function update(DocenteRequest $request, User $docente): JsonResponse
    {
        $this->asegurarDocente($docente);
        $datos = $request->validated();
        DB::transaction(function () use ($docente, $datos) {
            $docente->update($this->datosUsuario($datos));
            $docente->docentePerfil()->update($this->datosPerfil($datos));
        });
        Auditoria::registrar('editar', $docente, "Editó los datos del docente {$docente->name}.", $docente->sede_id);

        return response()->json(['mensaje' => "Se guardaron los cambios de {$docente->name}.", 'id' => $docente->id]);
    }

    /** Activo ⇄ inactivo (uno en proceso pasa a activo). No se desactiva con grupos vigentes. */
    public function estado(User $docente): JsonResponse
    {
        $this->asegurarDocente($docente);
        $nuevo = $docente->docentePerfil->estado === 'activo' ? 'inactivo' : 'activo';
        if ($nuevo === 'inactivo' && $error = DocenteSedeRequest::gruposACargo($docente)) {
            return response()->json(['message' => $error], 422);
        }
        DB::transaction(function () use ($docente, $nuevo) {
            $docente->docentePerfil->update(['estado' => $nuevo]);
            $docente->update(['activo' => $nuevo === 'activo']);
        });
        Auditoria::registrar($nuevo === 'activo' ? 'activar' : 'desactivar', $docente, ($nuevo === 'activo' ? 'Activó' : 'Desactivó')." al docente {$docente->name}.", $docente->sede_id);

        return response()->json(['mensaje' => $nuevo === 'activo' ? 'Se activó al docente.' : 'Se desactivó al docente.']);
    }

    public function restablecerClave(User $docente, SesionesService $sesiones): JsonResponse
    {
        $this->asegurarDocente($docente);
        $sesiones->restablecerClave($docente);
        Auditoria::registrar('editar', $docente, "Restableció la clave del docente {$docente->name}.", $docente->sede_id);

        return response()->json(['mensaje' => "La clave de {$docente->name} volvió a ser su número de documento."]);
    }

    private function asegurarDocente(User $u): void
    {
        abort_unless($u->isDocente() && $u->docentePerfil, 404);
    }

    private function datosUsuario(array $d): array
    {
        return [
            'sede_id'                 => $d['sede_id'],
            'nombres'                 => $d['nombres'],
            'apellidos'               => $d['apellidos'],
            'tipo_documento'          => $d['tipo_documento'],
            'numero_documento'        => $d['numero_documento'],
            'email'                   => $d['email'],
            'telefono'                => $d['telefono'],
            'genero'                  => $d['genero'],
            'fecha_nacimiento'        => $d['fecha_nacimiento'],
            'direccion'               => $d['direccion'],
            'departamento_residencia' => $d['departamento_residencia'],
            'ciudad_residencia'       => $d['ciudad_residencia'],
            'profesion'               => $d['profesion'],
            'titulo_academico'        => $d['titulo_academico'],
            'activo'                  => $d['estado'] === 'activo',   // solo un docente activo ingresa
        ];
    }

    private function datosPerfil(array $d): array
    {
        return ['escuela_id' => $d['escuela_id'], 'vinculacion' => $d['vinculacion'], 'estado' => $d['estado']];
    }
}
