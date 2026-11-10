<?php

namespace App\Http\Controllers\AdminGeneral\Inicio;

use App\Http\Controllers\Controller;
use App\Services\General\ComunicacionService;
use App\Services\General\EstructuraService;
use App\Services\General\SupervisionService;
use Illuminate\View\View;

/**
 * Inicio del Admin: cifras de hoy de cada sede (estudiantes, ingresos de 6 meses, cartera, grupos y cupos) con su
 * variación, alertas (sedes sin secretaría, mora alta, ocupación) y la actividad reciente. Solo consulta.
 */
class InicioController extends Controller
{
    public function index(EstructuraService $estructura, SupervisionService $supervision, ComunicacionService $comunicacion): View
    {
        return view('admin_general.dashboard', ['datos' => [
            'hoy'         => now()->toIso8601String(),
            'sedes'       => $estructura->sedes(),
            'secretarias' => collect($estructura->secretarias())->map(fn ($s) => collect($s)->only(['id', 'sede_id', 'nombres', 'apellidos', 'activo', 'ultimo_acceso']))->all(),
            'auditoria'   => array_slice($supervision->auditoria(), 0, 60),
            'comunicados' => collect($comunicacion->comunicados())->where('estado', 'programado')->values()->all(),
        ]]);
    }
}
