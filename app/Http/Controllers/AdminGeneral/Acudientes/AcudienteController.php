<?php

namespace App\Http\Controllers\AdminGeneral\Acudientes;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Acudientes\AcudienteRequest;
use App\Http\Requests\AdminGeneral\Acudientes\VinculoRequest;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\AcudienteService;
use App\Services\Academico\EstudianteService;
use App\Services\General\EstructuraService;
use App\Services\General\PersonasService;
use App\Services\Sistema\SesionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Acudientes de todas las sedes: registrar y editar (con las reglas de la secretaría, en la sede elegida), vincular y
 * desvincular estudiantes, activar o desactivar la cuenta y restablecer su clave.
 */
class AcudienteController extends Controller
{
    public function __construct(private AcudienteService $acudientes) {}

    public function index(PersonasService $personas, EstructuraService $estructura): View
    {
        [$estudiantes] = $personas->estudiantes();
        $campos = ['id', 'sede_id', 'nombres', 'apellidos', 'edad', 'estado', 'programa', 'acudiente', 'acudiente_id', 'parentesco'];

        return view('admin_general.acudientes.index', ['datos' => [
            'sedes'       => collect($estructura->sedes())->map(fn ($s) => collect($s)->only(['id', 'nombre', 'ciudad', 'departamento']))->all(),
            'acudientes'  => $personas->acudientes(),
            'estudiantes' => collect($estudiantes)->map(fn ($e) => collect($e)->only($campos))->all(),
            'opciones'    => ['parentescos' => EstudianteService::PARENTESCOS],
        ]]);
    }

    public function store(AcudienteRequest $request): JsonResponse
    {
        $sede = Sede::findOrFail($request->integer('sede_id'));
        $a = $this->acudientes->registrar($request->datosAcudiente(), $request->input('estudiantes'), $sede->id);
        $nombres = $a->estudiantesACargo()->pluck('nombres')->map(fn ($n) => strtok($n, ' '))->join(', ', ' y ');
        Auditoria::registrar('crear', $a, "Registró al acudiente {$a->name} en la sede {$sede->nombre} (a cargo de {$nombres}).", $sede->id);

        return response()->json(['mensaje' => "{$a->name} quedó como acudiente de {$nombres}. Su clave inicial es su número de documento.", 'id' => $a->id], 201);
    }

    public function update(AcudienteRequest $request, User $acudiente): JsonResponse
    {
        DB::transaction(function () use ($request, $acudiente) {
            $this->acudientes->actualizar($acudiente, $request->datosAcudiente(), $request->input('estudiantes'));
            $acudiente->update(['sede_id' => $request->integer('sede_id')]);
        });
        Auditoria::registrar('editar', $acudiente, "Editó los datos del acudiente {$acudiente->name}.", $acudiente->sede_id);

        return response()->json(['mensaje' => "Se guardaron los cambios de {$acudiente->name}.", 'id' => $acudiente->id]);
    }

    /** Si el estudiante ya tenía acudiente, este lo reemplaza. */
    public function vincular(VinculoRequest $request, User $acudiente): JsonResponse
    {
        $estudiante = $request->estudiante();
        $this->acudientes->vincular($acudiente, $estudiante, $request->input('parentesco'));
        Auditoria::registrar('editar', $acudiente, "Dejó a {$acudiente->name} como acudiente de {$estudiante->name}.", $acudiente->sede_id);

        return response()->json(['mensaje' => "{$estudiante->name} quedó a cargo de {$acudiente->name}."]);
    }

    /** Un menor de edad no puede quedar sin acudiente. */
    public function desvincular(User $acudiente, User $estudiante): JsonResponse
    {
        $this->asegurarAcudiente($acudiente);
        abort_unless($acudiente->estudiantesACargo()->whereKey($estudiante->id)->exists(), 404);
        if (AcudienteService::esMenor($estudiante)) {
            return response()->json(['message' => "{$estudiante->name} es menor de edad y quedaría sin acudiente. Para cambiarlo, vincúlale otro acudiente."], 422);
        }
        $this->acudientes->desvincular($acudiente, $estudiante);
        Auditoria::registrar('editar', $acudiente, "{$acudiente->name} dejó de ser acudiente de {$estudiante->name}.", $acudiente->sede_id);

        return response()->json(['mensaje' => "Se desvinculó a {$estudiante->name}."]);
    }

    /** Una cuenta inactiva no ingresa al portal; sigue vinculada a sus estudiantes. */
    public function estado(User $acudiente): JsonResponse
    {
        $this->asegurarAcudiente($acudiente);
        $acudiente->update(['activo' => ! $acudiente->activo]);
        Auditoria::registrar($acudiente->activo ? 'activar' : 'desactivar', $acudiente, ($acudiente->activo ? 'Activó' : 'Desactivó')." la cuenta del acudiente {$acudiente->name}.", $acudiente->sede_id);

        return response()->json(['mensaje' => $acudiente->activo ? 'Se activó la cuenta.' : 'Se desactivó la cuenta.']);
    }

    public function restablecerClave(User $acudiente, SesionesService $sesiones): JsonResponse
    {
        $this->asegurarAcudiente($acudiente);
        $sesiones->restablecerClave($acudiente);
        Auditoria::registrar('editar', $acudiente, "Restableció la contraseña de {$acudiente->name}.", $acudiente->sede_id);

        return response()->json(['mensaje' => "La clave de {$acudiente->name} volvió a ser su número de documento."]);
    }

    private function asegurarAcudiente(User $u): void
    {
        abort_unless($u->role === RolUsuario::Acudiente, 404);
    }
}
