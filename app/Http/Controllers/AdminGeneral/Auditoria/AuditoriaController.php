<?php

namespace App\Http\Controllers\AdminGeneral\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Institucional\Sede;
use App\Services\General\SupervisionService;
use Illuminate\View\View;

/** Auditoría de todas las sedes: solo consulta. */
class AuditoriaController extends Controller
{
    public function index(SupervisionService $supervision): View
    {
        return view('admin_general.auditoria.index', ['datos' => [
            'sedes'     => Sede::orderBy('nombre')->get(['id', 'nombre'])->all(),
            'auditoria' => $supervision->auditoria(),
        ]]);
    }
}
