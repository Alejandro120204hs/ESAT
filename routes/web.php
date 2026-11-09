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
use App\Http\Controllers\Admin\Comunicados\ComunicadoController;
use App\Http\Controllers\Admin\Foro\ForoController;
use App\Http\Controllers\Admin\Inicio\InicioController;
use App\Http\Controllers\Admin\Reportes\ReporteController;
use App\Http\Controllers\Admin\Contabilidad\ContabilidadController;
use App\Http\Controllers\Admin\Contabilidad\EgresoController;
use App\Http\Controllers\Admin\Pagos\CarteraController;
use App\Http\Controllers\Admin\Programas\EscuelaController;
use App\Http\Controllers\Admin\Programas\ProgramaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sitio\PaginaController;
use App\Http\Controllers\Superadmin\Administradores\AdministradorController;
use App\Http\Controllers\Superadmin\Sedes\SedeController;
use App\Http\Controllers\Docente\Asistencia\AsistenciaController as DocenteAsistenciaController;
use App\Http\Controllers\Docente\Grupos\GrupoController as DocenteGrupoController;
use App\Http\Controllers\Docente\Inicio\InicioController as DocenteInicioController;
use App\Http\Controllers\Docente\Notas\NotaController as DocenteNotaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// ============ SUBPÁGINAS DEL SITIO PÚBLICO ============
// Una ruta por programa, línea de Educación Continuada y escuela, con su
// nombre de siempre (programas.{slug}, continuada.{slug}); el contenido sale
// de config/contenido.php y config/sitio.php.
foreach (array_keys(config('contenido.programas')) as $slug) {
    Route::get('/' . $slug, [PaginaController::class, 'programa'])->defaults('slug', $slug)->name('programas.' . $slug);
}
foreach (array_keys(config('contenido.continuada')) as $slug) {
    Route::get('/' . $slug, [PaginaController::class, 'continuada'])->defaults('slug', $slug)->name('continuada.' . $slug);
}
foreach (config('sitio.escuelas') as $escuela) {
    Route::get('/escuelas/' . $escuela['slug'], [PaginaController::class, 'escuela'])->defaults('slug', $escuela['slug'])->name('escuelas.' . $escuela['slug']);
}

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
    Route::get('/', [InicioController::class, 'index'])->name('dashboard');

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

    // Panel "Contabilidad" (Finanzas): ingresos (lo recaudado en Pagos) contra egresos de la sede.
    Route::get('contabilidad', [ContabilidadController::class, 'index'])->name('contabilidad');
    Route::post('contabilidad/egresos', [EgresoController::class, 'store'])->name('contabilidad.egresos.store');
    Route::put('contabilidad/egresos/{egreso}', [EgresoController::class, 'update'])->name('contabilidad.egresos.update');
    Route::patch('contabilidad/egresos/{egreso}/anular', [EgresoController::class, 'anular'])->name('contabilidad.egresos.anular');

    // Panel "Comunicados" (Comunicación): los del admin (toda la sede o un rol) y los que los docentes publican
    // a sus grupos (aquí solo se archivan). El PUT llega como POST con _method porque puede traer el adjunto.
    Route::get('comunicados', [ComunicadoController::class, 'index'])->name('comunicados');
    Route::post('comunicados', [ComunicadoController::class, 'store'])->name('comunicados.store');
    Route::put('comunicados/{comunicado}', [ComunicadoController::class, 'update'])->name('comunicados.update');
    Route::patch('comunicados/{comunicado}/archivar', [ComunicadoController::class, 'archivar'])->name('comunicados.archivar');
    Route::delete('comunicados/{comunicado}', [ComunicadoController::class, 'destroy'])->name('comunicados.destroy');
    Route::get('comunicados/{comunicado}/adjunto', [ComunicadoController::class, 'adjunto'])->name('comunicados.adjunto');

    // Panel "Foro" (Comunicación): el foro general de la sede y su moderación. Los POST pueden traer adjunto.
    Route::get('foro', [ForoController::class, 'index'])->name('foro');
    Route::post('foro/temas', [ForoController::class, 'store'])->name('foro.temas.store');
    Route::post('foro/temas/{tema}/mensajes', [ForoController::class, 'responder'])->name('foro.temas.responder');
    Route::patch('foro/temas/{tema}', [ForoController::class, 'estado'])->name('foro.temas.estado');
    Route::patch('foro/mensajes/{mensaje}/ocultar', [ForoController::class, 'ocultar'])->name('foro.mensajes.ocultar');
    Route::patch('foro/mensajes/{mensaje}/restaurar', [ForoController::class, 'restaurar'])->name('foro.mensajes.restaurar');
    Route::patch('foro/mensajes/{mensaje}/descartar', [ForoController::class, 'descartar'])->name('foro.mensajes.descartar');
    Route::get('foro/mensajes/{mensaje}/adjunto', [ForoController::class, 'adjunto'])->name('foro.adjunto');

    // Panel "Reportes": documentos formales por mes (solo consulta; reportes.js los arma al cierre del mes elegido)
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes');

    // Panel "Acudientes": la persona responsable y sus estudiantes a cargo
    Route::get('acudientes', [AcudienteController::class, 'index'])->name('acudientes');
    Route::post('acudientes', [AcudienteController::class, 'store'])->name('acudientes.store');
    Route::put('acudientes/{acudiente}', [AcudienteController::class, 'update'])->name('acudientes.update');
    Route::post('acudientes/{acudiente}/estudiantes', [AcudienteController::class, 'vincular'])->name('acudientes.vincular');
    Route::delete('acudientes/{acudiente}/estudiantes/{estudiante}', [AcudienteController::class, 'desvincular'])->name('acudientes.desvincular');
    Route::patch('acudientes/{acudiente}/clave', [AcudienteController::class, 'restablecerClave'])->name('acudientes.clave');
    Route::patch('acudientes/{acudiente}/estado', [AcudienteController::class, 'cambiarEstado'])->name('acudientes.estado');
});

// ============ PANEL ADMIN (ESAT GENERAL) ============
// Rol admin_general: toda ESAT, sin sede propia. Solo frontend por ahora: las vistas usan datos de ejemplo
// (public/assets/js/admin_general/datos-ejemplo.js).
Route::middleware(['auth', 'role:admin_general'])->prefix('general')->name('general.')->group(function () {
    Route::get('/', function () {
        return view('admin_general.dashboard');
    })->name('dashboard');

    foreach (['sedes', 'secretarias', 'programas', 'estudiantes', 'acudientes', 'docentes', 'academico', 'finanzas', 'comunicados', 'foro', 'reportes', 'auditoria', 'configuracion'] as $pantalla) {
        Route::get($pantalla, function () use ($pantalla) {
            return view('admin_general.'.$pantalla.'.index');
        })->name($pantalla);
    }
});

// ============ PANEL DOCENTE ============
// Los grupos que tiene a cargo (curso_docente). Toma y corrige la asistencia y las notas mientras el
// grupo no haya terminado (PanelDocenteService); Admin › Asistencia y Calificaciones solo consultan.
Route::middleware(['auth', 'role:docente'])->prefix('docente')->name('docente.')->group(function () {
    Route::get('/', [DocenteInicioController::class, 'index'])->name('dashboard');
    Route::get('grupos', [DocenteGrupoController::class, 'index'])->name('grupos');
    Route::get('asistencia', [DocenteAsistenciaController::class, 'index'])->name('asistencia');
    Route::post('asistencia/{curso}', [DocenteAsistenciaController::class, 'store'])->whereNumber('curso')->name('asistencia.guardar');
    Route::get('notas', [DocenteNotaController::class, 'index'])->name('notas');
    Route::post('notas/{curso}/{modulo}', [DocenteNotaController::class, 'store'])->whereNumber(['curso', 'modulo'])->name('notas.guardar');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Sin "eliminar mi cuenta": borraría en cascada matrículas, pagos y recibos. Desactivar es del admin.
});

require __DIR__.'/auth.php';
