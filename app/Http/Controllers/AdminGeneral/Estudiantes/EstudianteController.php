<?php

namespace App\Http\Controllers\AdminGeneral\Estudiantes;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Estudiantes\EstudianteRequest;
use App\Models\Academico\ContactoEmergencia;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Academico\EstudianteService;
use App\Services\General\EstructuraService;
use App\Services\General\PersonasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Estudiantes de todas las sedes: consultar (matrícula, pagos y rendimiento) y registrar o editar a la persona en la sede
 * que se elija. Matricular y cobrar se hace entrando a la sede (Matrículas de la secretaría).
 */
class EstudianteController extends Controller
{
    public function __construct(private EstudianteService $estudiantes) {}

    public function index(PersonasService $personas, EstructuraService $estructura): View
    {
        [$estudiantes, $recibos] = $personas->estudiantes();

        return view('admin_general.estudiantes.index', ['datos' => [
            'sedes'       => collect($estructura->sedes())->map(fn ($s) => collect($s)->only(['id', 'nombre', 'ciudad', 'programas']))->all(),
            'programas'   => collect($estructura->programas())->map(fn ($p) => collect($p)->only(['id', 'nombre']))->all(),
            'estudiantes' => $estudiantes,
            'recaudos'    => $recibos,
            'opciones'    => ['parentescos' => ContactoEmergencia::PARENTESCOS, 'tipos' => EstudianteRequest::TIPOS_DOCUMENTO,
                              'extranjero' => EstudianteRequest::DOCUMENTOS_EXTRANJERO],
        ]]);
    }

    public function store(EstudianteRequest $request): JsonResponse
    {
        $sede = Sede::findOrFail($request->integer('sede_id'));
        $e = $this->estudiantes->registrar($request->datosEstudiante(), $request->datosContacto(), $sede->id);
        Auditoria::registrar('crear', $e, "Registró al estudiante {$e->name} en la sede {$sede->nombre}.", $sede->id);

        return response()->json(['mensaje' => "Se registró a {$e->name}. Su clave inicial es su número de documento. Para matricularlo, entra a la sede {$sede->nombre}.", 'id' => $e->id], 201);
    }

    public function update(EstudianteRequest $request, User $estudiante): JsonResponse
    {
        $sedeId = $request->integer('sede_id');
        DB::transaction(function () use ($request, $estudiante, $sedeId) {
            $estudiante->update($request->datosEstudiante() + ['sede_id' => $sedeId]);
            $this->estudiantes->guardarContacto($estudiante, $request->datosContacto());
        });
        Auditoria::registrar('editar', $estudiante, "Editó los datos del estudiante {$estudiante->name}.", $sedeId);

        return response()->json(['mensaje' => "Se guardaron los cambios de {$estudiante->name}.", 'id' => $estudiante->id]);
    }
}
