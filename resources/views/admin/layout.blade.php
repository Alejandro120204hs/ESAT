<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (auth()->user()->isAdminGeneral())
    {{-- El Admin puede tener pestañas en sedes distintas: cada fetch dice de qué sede es la pestaña y el servidor
         lo rechaza si en otra pestaña ya entró a otra sede (EnsureSedeDeTrabajo). Va antes de cualquier script. --}}
    <script>
        (function (sede, fetchOriginal) {
            window.fetch = function (recurso, opciones) {
                var url = new URL(recurso instanceof Request ? recurso.url : recurso, location.href);
                if (url.origin !== location.origin) return fetchOriginal(recurso, opciones);
                opciones = Object.assign({}, opciones);
                var h = new Headers(opciones.headers || (recurso instanceof Request ? recurso.headers : undefined));
                h.set('X-Sede-Trabajo', sede);
                opciones.headers = h;
                return fetchOriginal(recurso, opciones);
            };
        })(@json((string) auth()->user()->sedeTrabajoId()), window.fetch.bind(window));
    </script>
    @endif
    <title>@yield('title', 'Panel') — ESAT Secretaría</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800;900&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/admin/layout.css') }}?v={{ filemtime(public_path('assets/css/admin/layout.css')) }}">
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
                <a href="{{ route('admin.pagos') }}" class="app-nav-link {{ $activeSection === 'pagos' ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    Pagos
                </a>
            </li>
            <li>
                <a href="{{ route('admin.contabilidad') }}" class="app-nav-link {{ $activeSection === 'contabilidad' ? 'is-active' : '' }}">
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
                <a href="{{ route('admin.comunicados') }}" class="app-nav-link {{ $activeSection === 'comunicados' ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Comunicados
                </a>
            </li>
            <li>
                <a href="{{ route('admin.foro') }}" class="app-nav-link {{ $activeSection === 'foro' ? 'is-active' : '' }}">
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

        {{-- El Admin de ESAT está trabajando dentro de una sede ("Entrar a la sede") --}}
        @if (auth()->user()->isAdminGeneral())
        <div class="app-franja-sede" role="status">
            <svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
            <span>Estás trabajando en la sede <strong>{{ auth()->user()->sedeTrabajo()?->nombre }}</strong> como Admin. Todo queda en la auditoría de la sede con tu nombre.</span>
            <form method="POST" action="{{ route('general.sedes.salir') }}">
                @csrf
                <button type="submit" class="app-franja-sede-btn">Volver al panel general</button>
            </form>
        </div>
        @endif

        {{-- Contenido --}}
        <main class="app-content @yield('content-class')">
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('assets/js/admin/layout.js') }}?v={{ filemtime(public_path('assets/js/admin/layout.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
