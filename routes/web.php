<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/servicios-geriatricos', function () {
    return view('paginas.servicios-geriatricos');
})->name('programas.servicios-geriatricos');

Route::get('/seguridad-ocupacional-y-laboral', function () {
    return view('paginas.seguridad-ocupacional-y-laboral');
})->name('programas.seguridad-ocupacional-y-laboral');
