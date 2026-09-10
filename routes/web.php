<?php

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
