{{--
    Páginas de error de toda la plataforma (sitio y paneles), en español. Cada código tiene su vista
    (403, 404, 419, 429, 500, 503) que llena título, explicación y botón. No consulta la base de datos: un 500
    puede venir justo de ahí. Con sesión, el botón lleva al panel de su rol; sin sesión, al inicio del sitio.
--}}
@php
    try {
        $usuario = auth()->user();
        $destino = $usuario ? $usuario->panelUrl() : url('/');
    } catch (\Throwable $e) {
        $usuario = null;
        $destino = url('/');
    }
    $boton = trim($__env->yieldContent('boton')) ?: ($usuario ? 'Ir a mi panel' : 'Ir al inicio');
    $enlace = trim($__env->yieldContent('enlace')) ?: $destino;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') — ESAT</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/errores.css') }}">
</head>
<body>
    <main class="err">
        <img class="err-logo" src="{{ asset('assets/img/logo.png') }}" alt="ESAT">
        <p class="err-codigo">Error @yield('codigo')</p>
        <h1 class="err-titulo">@yield('titulo')</h1>
        <p class="err-texto">@yield('texto')</p>
        <div class="err-acciones">
            <a class="err-btn" href="{{ $enlace }}">{{ $boton }}</a>
            <button type="button" class="err-link" onclick="history.length > 1 ? history.back() : location.assign('{{ $enlace }}')">Volver a la página anterior</button>
        </div>
    </main>
</body>
</html>
