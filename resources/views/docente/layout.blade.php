<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — ESAT Docente</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800;900&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/docente/layout.css') }}?v={{ filemtime(public_path('assets/css/docente/layout.css')) }}">
    @stack('styles')
</head>
<body class="app-page">

@php $activeSection = trim($__env->yieldContent('active')); @endphp

<div class="app-shell">

    {{-- Sidebar --}}
    @include('docente.partials.sidebar', ['active' => $activeSection])

    {{-- Main --}}
    <div class="app-main">
        {{-- Topbar --}}
        @include('docente.partials.topbar')

        {{-- Contenido --}}
        <main class="app-content">
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('assets/js/docente/layout.js') }}?v={{ filemtime(public_path('assets/js/docente/layout.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
