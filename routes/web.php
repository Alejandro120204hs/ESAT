<?php

use App\Http\Controllers\Admin\Acudientes\AcudienteController;
use App\Http\Controllers\AdminGeneral\Academico\AcademicoController as GeneralAcademicoController;
use App\Http\Controllers\AdminGeneral\Acudientes\AcudienteController as GeneralAcudienteController;
use App\Http\Controllers\AdminGeneral\Auditoria\AuditoriaController as GeneralAuditoriaController;
use App\Http\Controllers\AdminGeneral\Comunicados\ComunicadoController as GeneralComunicadoController;
use App\Http\Controllers\AdminGeneral\Configuracion\ConfiguracionController as GeneralConfiguracionController;
use App\Http\Controllers\AdminGeneral\Docentes\DocenteController as GeneralDocenteController;
use App\Http\Controllers\AdminGeneral\Estudiantes\EstudianteController as GeneralEstudianteController;
use App\Http\Controllers\AdminGeneral\Finanzas\FinanzasController as GeneralFinanzasController;
use App\Http\Controllers\AdminGeneral\Foro\ForoController as GeneralForoController;
use App\Http\Controllers\AdminGeneral\Inicio\InicioController as GeneralInicioController;
use App\Http\Controllers\AdminGeneral\Programas\EscuelaController as GeneralEscuelaController;
use App\Http\Controllers\AdminGeneral\Programas\ProgramaController as GeneralProgramaController;
use App\Http\Controllers\AdminGeneral\Reportes\ReporteController as GeneralReporteController;
use App\Http\Controllers\AdminGeneral\Secretarias\SecretariaController as GeneralSecretariaController;
use App\Http\Controllers\AdminGeneral\Sedes\EntrarSedeController;
use App\Http\Controllers\AdminGeneral\Sedes\SedeController as GeneralSedeController;
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
// La secretaría trabaja en su sede; el Admin de ESAT también entra aquí, en la sede que eligió ("Entrar a la sede").
// Todas las pantallas leen la sede con User::sedeTrabajoId(); 'sede.trabajo' exige que el Admin ya haya elegido una.
Route::middleware(['auth', 'role:admin,admin_general', 'sede.trabajo'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [InicioController::class, 'index'])->name('dashboard');

    // El catálogo (programas, precios, escuelas) lo maneja el Admin de ESAT; la sede elige cuáles ofrece
    Route::get('programas', [ProgramaController::class, 'index'])->name('programas');
    Route::patch('programas/{programa}/oferta', [ProgramaController::class, 'oferta'])->name('programas.oferta');

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
// Rol admin_general ("Admin"): toda ESAT, sin sede propia. Ve todo consolidado con filtro de sede, maneja la
// estructura (sedes, secretarías, catálogo), y para matricular/cobrar entra a una sede (EntrarSedeController).
Route::middleware(['auth', 'role:admin_general'])->prefix('general')->name('general.')->group(function () {
    Route::post('sedes/{sede}/entrar', [EntrarSedeController::class, 'entrar'])->name('sedes.entrar');
    Route::post('salir-de-sede', [EntrarSedeController::class, 'salir'])->name('sedes.salir');

    Route::get('/', [GeneralInicioController::class, 'index'])->name('dashboard');

    // Estructura
    Route::get('sedes', [GeneralSedeController::class, 'index'])->name('sedes');
    Route::post('sedes', [GeneralSedeController::class, 'store'])->name('sedes.store');
    Route::put('sedes/{sede}', [GeneralSedeController::class, 'update'])->name('sedes.update');

    Route::get('secretarias', [GeneralSecretariaController::class, 'index'])->name('secretarias');
    Route::post('secretarias', [GeneralSecretariaController::class, 'store'])->name('secretarias.store');
    Route::put('secretarias/{secretaria}', [GeneralSecretariaController::class, 'update'])->name('secretarias.update');
    Route::patch('secretarias/{secretaria}/estado', [GeneralSecretariaController::class, 'estado'])->name('secretarias.estado');
    Route::patch('secretarias/{secretaria}/clave', [GeneralSecretariaController::class, 'restablecerClave'])->name('secretarias.clave');

    Route::get('programas', [GeneralProgramaController::class, 'index'])->name('programas');
    Route::post('programas', [GeneralProgramaController::class, 'store'])->name('programas.store');
    Route::put('programas/{programa}', [GeneralProgramaController::class, 'update'])->name('programas.update');
    Route::patch('programas/{programa}/estado', [GeneralProgramaController::class, 'estado'])->name('programas.estado');
    Route::patch('programas/{programa}/sedes/{sede}', [GeneralProgramaController::class, 'oferta'])->name('programas.oferta');
    Route::post('escuelas', [GeneralEscuelaController::class, 'store'])->name('escuelas.store');
    Route::put('escuelas/{escuela}', [GeneralEscuelaController::class, 'update'])->name('escuelas.update');
    Route::delete('escuelas/{escuela}', [GeneralEscuelaController::class, 'destroy'])->name('escuelas.destroy');

    // Personas
    Route::get('estudiantes', [GeneralEstudianteController::class, 'index'])->name('estudiantes');
    Route::post('estudiantes', [GeneralEstudianteController::class, 'store'])->name('estudiantes.store');
    Route::put('estudiantes/{estudiante}', [GeneralEstudianteController::class, 'update'])->name('estudiantes.update');

    Route::get('acudientes', [GeneralAcudienteController::class, 'index'])->name('acudientes');
    Route::post('acudientes', [GeneralAcudienteController::class, 'store'])->name('acudientes.store');
    Route::put('acudientes/{acudiente}', [GeneralAcudienteController::class, 'update'])->name('acudientes.update');
    Route::post('acudientes/{acudiente}/vincular', [GeneralAcudienteController::class, 'vincular'])->name('acudientes.vincular');
    Route::delete('acudientes/{acudiente}/estudiantes/{estudiante}', [GeneralAcudienteController::class, 'desvincular'])->name('acudientes.desvincular');
    Route::patch('acudientes/{acudiente}/estado', [GeneralAcudienteController::class, 'estado'])->name('acudientes.estado');
    Route::patch('acudientes/{acudiente}/clave', [GeneralAcudienteController::class, 'restablecerClave'])->name('acudientes.clave');

    Route::get('docentes', [GeneralDocenteController::class, 'index'])->name('docentes');
    Route::post('docentes', [GeneralDocenteController::class, 'store'])->name('docentes.store');
    Route::put('docentes/{docente}', [GeneralDocenteController::class, 'update'])->name('docentes.update');
    Route::patch('docentes/{docente}/estado', [GeneralDocenteController::class, 'estado'])->name('docentes.estado');
    Route::patch('docentes/{docente}/clave', [GeneralDocenteController::class, 'restablecerClave'])->name('docentes.clave');

    // Seguimiento (solo consulta)
    Route::get('academico', [GeneralAcademicoController::class, 'index'])->name('academico');
    Route::get('finanzas', [GeneralFinanzasController::class, 'index'])->name('finanzas');
    Route::get('reportes', [GeneralReporteController::class, 'index'])->name('reportes');

    // Comunicación
    Route::get('comunicados', [GeneralComunicadoController::class, 'index'])->name('comunicados');
    Route::post('comunicados', [GeneralComunicadoController::class, 'store'])->name('comunicados.store');
    Route::put('comunicados/{comunicado}', [GeneralComunicadoController::class, 'update'])->name('comunicados.update');
    Route::patch('comunicados/{comunicado}/archivar', [GeneralComunicadoController::class, 'archivar'])->name('comunicados.archivar');
    Route::delete('comunicados/{comunicado}', [GeneralComunicadoController::class, 'destroy'])->name('comunicados.destroy');

    Route::get('foro', [GeneralForoController::class, 'index'])->name('foro');
    Route::post('foro/temas', [GeneralForoController::class, 'store'])->name('foro.temas.store');
    Route::post('foro/temas/{tema}/responder', [GeneralForoController::class, 'responder'])->name('foro.responder');
    Route::patch('foro/temas/{tema}/estado', [GeneralForoController::class, 'estado'])->name('foro.estado');
    Route::patch('foro/mensajes/{mensaje}/ocultar', [GeneralForoController::class, 'ocultar'])->name('foro.ocultar');
    Route::patch('foro/mensajes/{mensaje}/restaurar', [GeneralForoController::class, 'restaurar'])->name('foro.restaurar');
    Route::patch('foro/mensajes/{mensaje}/descartar', [GeneralForoController::class, 'descartar'])->name('foro.descartar');

    // Supervisión
    Route::get('auditoria', [GeneralAuditoriaController::class, 'index'])->name('auditoria');
    Route::get('configuracion', [GeneralConfiguracionController::class, 'index'])->name('configuracion');
    Route::put('configuracion/institucion', [GeneralConfiguracionController::class, 'institucion'])->name('configuracion.institucion');
    Route::put('configuracion/cobros', [GeneralConfiguracionController::class, 'cobros'])->name('configuracion.cobros');
    Route::put('configuracion/seguridad', [GeneralConfiguracionController::class, 'seguridad'])->name('configuracion.seguridad');
    Route::patch('configuracion/bloqueadas/{usuario}', [GeneralConfiguracionController::class, 'desbloquear'])->name('configuracion.desbloquear');
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
