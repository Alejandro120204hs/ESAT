<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — ESAT Admin</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800;900&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/admin/layout.css') }}">
    @stack('styles')
</head>
<body class="app-page">

@php $activeSection = trim($__env->yieldContent('active')); @endphp

<div class="app-shell @yield('shell-class')">

    {{-- Sidebar --}}
    @include('admin.partials.sidebar', ['active' => $activeSection])

    {{-- Flyout: Finanzas --}}
    <div id="flyout-finanzas" class="app-nav-flyout" role="menu">
        <div class="app-nav-flyout-header">Finanzas</div>
        <ul class="app-nav">
            <li>
                <a href="#" class="app-nav-link {{ $activeSection === 'pagos' ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    Pagos
                </a>
            </li>
            <li>
                <a href="#" class="app-nav-link {{ $activeSection === 'contabilidad' ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Contabilidad
                </a>
            </li>
        </ul>
    </div>

    {{-- Flyout: Comunicación --}}
    <div id="flyout-comunicacion" class="app-nav-flyout" role="menu">
        <div class="app-nav-flyout-header">Comunicación</div>
        <ul class="app-nav">
            <li>
                <a href="#" class="app-nav-link {{ $activeSection === 'comunicados' ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Comunicados
                </a>
            </li>
            <li>
                <a href="#" class="app-nav-link {{ $activeSection === 'foro' ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Foro
                </a>
            </li>
        </ul>
    </div>

    {{-- Main --}}
    <div class="app-main">
        {{-- Topbar --}}
        @include('admin.partials.topbar')

        {{-- Contenido --}}
        <main class="app-content @yield('content-class')">
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('assets/js/admin/layout.js') }}" defer></script>
@stack('scripts')
</body>
</html>
