<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/servicios-geriatricos', function () {
    return view('paginas.servicios-geriatricos');
})->name('programas.servicios-geriatricos');
