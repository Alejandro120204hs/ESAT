@extends('errors.layout')

@section('codigo', '419')
@section('titulo', 'Tu sesión venció')
@section('texto', 'Pasó mucho tiempo sin actividad y, por seguridad, la página dejó de ser válida. Vuelve a entrar y repite lo que estabas haciendo.')
@section('boton', 'Volver a entrar')
@section('enlace', route('login'))
