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
