<?php

namespace App\Http\Controllers\AdminGeneral\Academico;

use App\Http\Controllers\Controller;
use App\Services\General\AcademicoService;
use App\Services\General\EstructuraService;
use App\Services\General\PersonasService;
use Illuminate\View\View;

/** Grupos, asistencia y notas de todas las sedes: solo consulta (los docentes registran; la secretaría consulta). */
class AcademicoController extends Controller
{
    public function index(AcademicoService $academico, EstructuraService $estructura, PersonasService $personas): View
    {
        [$docentes] = $personas->docentes();

        return view('admin_general.academico.index', ['datos' => [
            'sedes'     => collect($estructura->sedes())->map(fn ($s) => collect($s)->only(['id', 'nombre', 'ciudad']))->all(),
            'programas' => collect($estructura->programas())->map(fn ($p) => collect($p)->only(['id', 'nombre', 'modulos']))->all(),
            'docentes'  => collect($docentes)->map(fn ($d) => collect($d)->only(['id', 'nombres', 'apellidos', 'estado', 'sedes']))->all(),
            'grupos'    => $academico->grupos(),
        ]]);
    }
}
