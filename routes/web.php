<?php

use App\Http\Controllers\Admin\Acudientes\AcudienteController;
use App\Http\Controllers\Admin\Asistencia\AsistenciaController;
use App\Http\Controllers\Admin\Calificaciones\CalificacionController;
use App\Http\Controllers\Admin\Docentes\DocenteController;
use App\Http\Controllers\Admin\Estudiantes\EstudianteController;
use App\Http\Controllers\Admin\Grupos\GrupoController;
use App\Http\Controllers\Admin\Horarios\HorarioController;
use App\Http\Controllers\Admin\Matriculas\DocumentoController;
use App\Http\Controllers\Admin\Matriculas\MatriculaController;
use App\Http\Controllers\Admin\Matriculas\PagoController;
use App\Http\Controllers\Admin\Pagos\CarteraController;
use App\Http\Controllers\Admin\Programas\EscuelaController;
use App\Http\Controllers\Admin\Programas\ProgramaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Superadmin\Administradores\AdministradorController;
use App\Http\Controllers\Superadmin\Sedes\SedeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// ============ ESCUELA DE SALUD ============
Route::get('/servicios-geriatricos', function () {
    return view('paginas.escuela-de-salud.servicios-geriatricos');
})->name('programas.servicios-geriatricos');

Route::get('/seguridad-ocupacional-y-laboral', function () {
    return view('paginas.escuela-de-salud.seguridad-ocupacional-y-laboral');
})->name('programas.seguridad-ocupacional-y-laboral');

Route::get('/auxiliar-de-psiquiatria', function () {
    return view('paginas.escuela-de-salud.auxiliar-de-psiquiatria');
})->name('programas.auxiliar-de-psiquiatria');

Route::get('/camillero-hospitalario', function () {
    return view('paginas.escuela-de-salud.camillero-hospitalario');
})->name('programas.camillero-hospitalario');

// ============ ESCUELA DE COCINA Y TURISMO ============
Route::get('/servicios-hoteleros-y-turisticos', function () {
    return view('paginas.escuela-de-cocina-y-turismo.servicios-hoteleros-y-turisticos');
})->name('programas.servicios-hoteleros-y-turisticos');

Route::get('/cocina-nacional-e-internacional', function () {
    return view('paginas.escuela-de-cocina-y-turismo.cocina-nacional-e-internacional');
})->name('programas.cocina-nacional-e-internacional');

Route::get('/sommelier', function () {
    return view('paginas.escuela-de-cocina-y-turismo.sommelier');
})->name('programas.sommelier');

Route::get('/inspector-de-calidad-de-alimentos-y-bebidas', function () {
    return view('paginas.escuela-de-cocina-y-turismo.inspector-de-calidad-de-alimentos-y-bebidas');
})->name('programas.inspector-de-calidad-de-alimentos-y-bebidas');

// ============ ESCUELA ADMINISTRATIVA ============
Route::get('/auditoria-y-facturacion-de-cuentas-medicas', function () {
    return view('paginas.escuela-administrativa.auditoria-y-facturacion-de-cuentas-medicas');
})->name('programas.auditoria-y-facturacion-de-cuentas-medicas');

Route::get('/agente-de-transito', function () {
    return view('paginas.escuela-administrativa.agente-de-transito');
})->name('programas.agente-de-transito');

Route::get('/auxiliar-contable-y-administrativo', function () {
    return view('paginas.escuela-administrativa.auxiliar-contable-y-administrativo');
})->name('programas.auxiliar-contable-y-administrativo');

// ============ DEPORTE Y CULTURA ============
Route::get('/salvamento-acuatico', function () {
    return view('paginas.deporte-y-cultura.salvamento-acuatico');
})->name('programas.salvamento-acuatico');

Route::get('/gestion-y-promocion-artistica', function () {
    return view('paginas.deporte-y-cultura.gestion-y-promocion-artistica');
})->name('programas.gestion-y-promocion-artistica');

Route::get('/servicios-de-recreacion-y-deportes', function () {
    return view('paginas.deporte-y-cultura.servicios-de-recreacion-y-deportes');
})->name('programas.servicios-de-recreacion-y-deportes');

// ============ ESCUELA CIENCIAS ============
Route::get('/produccion-agropecuaria-y-zootecnia', function () {
    return view('paginas.escuela-ciencias.produccion-agropecuaria-y-zootecnia');
})->name('programas.produccion-agropecuaria-y-zootecnia');

Route::get('/asistente-de-veterinaria-y-zootecnia', function () {
    return view('paginas.escuela-ciencias.asistente-de-veterinaria-y-zootecnia');
})->name('programas.asistente-de-veterinaria-y-zootecnia');

Route::get('/obras-civiles-y-arquitectura', function () {
    return view('paginas.escuela-ciencias.obras-civiles-y-arquitectura');
})->name('programas.obras-civiles-y-arquitectura');

Route::get('/criminalistica-investigacion-judicial-y-ciencias-forenses', function () {
    return view('paginas.escuela-ciencias.criminalistica-investigacion-judicial-y-ciencias-forenses');
})->name('programas.criminalistica-investigacion-judicial-y-ciencias-forenses');

Route::get('/energias-renovables-y-alternativas', function () {
    return view('paginas.escuela-ciencias.energias-renovables-y-alternativas');
})->name('programas.energias-renovables-y-alternativas');

Route::get('/asistente-de-laboratorio-clinico-veterinario', function () {
    return view('paginas.escuela-ciencias.asistente-de-laboratorio-clinico-veterinario');
})->name('programas.asistente-de-laboratorio-clinico-veterinario');

Route::get('/electricista', function () {
    return view('paginas.escuela-ciencias.electricista');
})->name('programas.electricista');

Route::get('/electromecanica', function () {
    return view('paginas.escuela-ciencias.electromecanica');
})->name('programas.electromecanica');

Route::get('/mecanica-y-electronica-de-motos', function () {
    return view('paginas.escuela-ciencias.mecanica-y-electronica-de-motos');
})->name('programas.mecanica-y-electronica-de-motos');

// ============ EDUCACIÓN E IDIOMAS ============
Route::get('/ingles-a1-a2-b1-b2', function () {
    return view('paginas.educacion-e-idiomas.ingles-a1-a2-b1-b2');
})->name('programas.ingles-a1-a2-b1-b2');

Route::get('/frances-a1-a2-b1-b2', function () {
    return view('paginas.educacion-e-idiomas.frances-a1-a2-b1-b2');
})->name('programas.frances-a1-a2-b1-b2');

Route::get('/asistente-de-preescolar', function () {
    return view('paginas.educacion-e-idiomas.asistente-de-preescolar');
})->name('programas.asistente-de-preescolar');

// ============ ESCUELA DE BELLEZA ============
Route::get('/barberia', function () {
    return view('paginas.escuela-de-belleza.barberia');
})->name('programas.barberia');

// ============ EDUCACIÓN CONTINUADA ============
Route::get('/salud-ocupacional', function () {
    return view('paginas.educacion-continuada.salud-ocupacional');
})->name('continuada.salud-ocupacional');

Route::get('/areas-sociales', function () {
    return view('paginas.educacion-continuada.areas-sociales');
})->name('continuada.areas-sociales');

Route::get('/pedagogia', function () {
    return view('paginas.educacion-continuada.pedagogia');
})->name('continuada.pedagogia');

Route::get('/administracion-y-gerencia', function () {
    return view('paginas.educacion-continuada.administracion-y-gerencia');
})->name('continuada.administracion-y-gerencia');

Route::get('/edificios-y-afines', function () {
    return view('paginas.educacion-continuada.edificios-y-afines');
})->name('continuada.edificios-y-afines');

Route::get('/hoteleria-y-turismo', function () {
    return view('paginas.educacion-continuada.hoteleria-y-turismo');
})->name('continuada.hoteleria-y-turismo');

Route::get('/salud', function () {
    return view('paginas.educacion-continuada.salud');
})->name('continuada.salud');

// ============ ACADEMICO (Breeze) ============
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// ============ PANEL SUPERADMIN ============
Route::middleware(['auth', 'role:superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', function () {
        return view('superadmin.dashboard');
    })->name('dashboard');

    Route::get('/sedes', [SedeController::class, 'index'])->name('sedes');
    Route::post('/sedes', [SedeController::class, 'store'])->name('sedes.store');
    Route::put('/sedes/{sede}', [SedeController::class, 'update'])->name('sedes.update');

    Route::get('/administradores', [AdministradorController::class, 'index'])->name('administradores');
    Route::post('/administradores', [AdministradorController::class, 'store'])->name('administradores.store');
    Route::put('/administradores/{administrador}', [AdministradorController::class, 'update'])->name('administradores.update');
    Route::patch('/administradores/{administrador}/toggle', [AdministradorController::class, 'toggleActivo'])->name('administradores.toggle');

    Route::get('/estudiantes', function () {
        return view('superadmin.estudiantes');
    })->name('estudiantes');

    Route::get('/auditoria', function () {
        return view('superadmin.auditoria');
    })->name('auditoria');

    Route::get('/configuracion', function () {
        return view('superadmin.configuracion');
    })->name('configuracion');
});

// ============ PANEL ADMIN ============
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::get('programas', [ProgramaController::class, 'index'])->name('programas');
    Route::post('programas', [ProgramaController::class, 'store'])->name('programas.store');
    Route::put('programas/{programa}', [ProgramaController::class, 'update'])->name('programas.update');
    Route::patch('programas/{programa}/estado', [ProgramaController::class, 'cambiarEstado'])->name('programas.estado');

    Route::post('escuelas', [EscuelaController::class, 'store'])->name('escuelas.store');
    Route::put('escuelas/{escuela}', [EscuelaController::class, 'update'])->name('escuelas.update');
    Route::delete('escuelas/{escuela}', [EscuelaController::class, 'destroy'])->name('escuelas.destroy');

    Route::get('docentes', [DocenteController::class, 'index'])->name('docentes');
    Route::post('docentes', [DocenteController::class, 'store'])->name('docentes.store');
    Route::put('docentes/{docente}', [DocenteController::class, 'update'])->name('docentes.update');
    Route::patch('docentes/{docente}/estado', [DocenteController::class, 'cambiarEstado'])->name('docentes.estado');

    // Panel "Grupos" (la tabla y la ruta se llaman cursos)
    Route::get('cursos', [GrupoController::class, 'index'])->name('cursos');
    Route::post('cursos', [GrupoController::class, 'store'])->name('cursos.store');
    Route::put('cursos/{curso}', [GrupoController::class, 'update'])->name('cursos.update');
    Route::patch('cursos/{curso}/estado', [GrupoController::class, 'cambiarEstado'])->name('cursos.estado');

    Route::get('horarios', [HorarioController::class, 'index'])->name('horarios');
    Route::put('horarios/{curso}', [HorarioController::class, 'update'])->name('horarios.update');

    // Panel "Estudiantes": la persona (datos, acudiente, documentos). Matricular es de Matrículas.
    Route::get('estudiantes', [EstudianteController::class, 'index'])->name('estudiantes');
    Route::post('estudiantes', [EstudianteController::class, 'store'])->name('estudiantes.store');
    Route::put('estudiantes/{estudiante}', [EstudianteController::class, 'update'])->name('estudiantes.update');
    Route::patch('estudiantes/{estudiante}/clave', [EstudianteController::class, 'restablecerClave'])->name('estudiantes.clave');
    Route::put('estudiantes/{estudiante}/acudiente', [EstudianteController::class, 'acudiente'])->name('estudiantes.acudiente');
    Route::patch('estudiantes/{estudiante}/documentos/{documento}', [EstudianteController::class, 'documento'])->name('estudiantes.documento');

    // Panel "Matrículas": programa + grupo, cambio de grupo, estado de la matrícula y pagos
    Route::get('matriculas', [MatriculaController::class, 'index'])->name('matriculas');
    Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
    Route::patch('matriculas/{matricula}/grupo', [MatriculaController::class, 'cambiarGrupo'])->name('matriculas.grupo');
    Route::patch('matriculas/{matricula}/estado', [MatriculaController::class, 'cambiarEstado'])->name('matriculas.estado');
    Route::patch('matriculas/{matricula}/descuento', [MatriculaController::class, 'descuento'])->name('matriculas.descuento');
    Route::post('matriculas/{matricula}/pagos', [PagoController::class, 'store'])->name('matriculas.pagos.store');
    Route::patch('matriculas/{matricula}/recaudos/{recaudo}/anular', [PagoController::class, 'anular'])->name('matriculas.pagos.anular');
    Route::get('matriculas/recibos/{recaudo}', [PagoController::class, 'recibo'])->name('matriculas.recibo');
    Route::patch('matriculas/{matricula}/programa', [MatriculaController::class, 'cambiarPrograma'])->name('matriculas.programa');
    Route::post('matriculas/{matricula}/devoluciones', [PagoController::class, 'devolucion'])->name('matriculas.devoluciones.store');
    Route::get('matriculas/devoluciones/{devolucion}', [PagoController::class, 'comprobanteDevolucion'])->name('matriculas.devolucion');
    Route::get('matriculas/{matricula}/constancia', [DocumentoController::class, 'constancia'])->name('matriculas.constancia');
    Route::get('matriculas/{matricula}/paz-y-salvo', [DocumentoController::class, 'pazYSalvo'])->name('matriculas.paz-y-salvo');

    // Panel "Asistencia": solo consulta (la toma el docente)
    Route::get('asistencia', [AsistenciaController::class, 'index'])->name('asistencia');

    // Panel "Calificaciones": solo consulta (la nota la pone el docente)
    Route::get('calificaciones', [CalificacionController::class, 'index'])->name('calificaciones');

    // Panel "Pagos" (Finanzas): cartera, pagos recibidos, resumen de caja, por programa y devoluciones.
    // Registrar y anular un pago usan las rutas de Matrículas (matriculas.pagos.store / matriculas.pagos.anular).
    Route::get('pagos', [CarteraController::class, 'index'])->name('pagos');

    // Panel "Acudientes": la persona responsable y sus estudiantes a cargo
    Route::get('acudientes', [AcudienteController::class, 'index'])->name('acudientes');
    Route::post('acudientes', [AcudienteController::class, 'store'])->name('acudientes.store');
    Route::put('acudientes/{acudiente}', [AcudienteController::class, 'update'])->name('acudientes.update');
    Route::post('acudientes/{acudiente}/estudiantes', [AcudienteController::class, 'vincular'])->name('acudientes.vincular');
    Route::delete('acudientes/{acudiente}/estudiantes/{estudiante}', [AcudienteController::class, 'desvincular'])->name('acudientes.desvincular');
    Route::patch('acudientes/{acudiente}/clave', [AcudienteController::class, 'restablecerClave'])->name('acudientes.clave');
    Route::patch('acudientes/{acudiente}/estado', [AcudienteController::class, 'cambiarEstado'])->name('acudientes.estado');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
