@php
    $active = $active ?? '';
    $initials = mb_strtoupper(mb_substr(auth()->user()->nombres ?? 'E', 0, 1) . mb_substr(auth()->user()->apellidos ?? '', 0, 1));
    if (mb_strlen($initials) < 1) $initials = 'E';
    $enlace = function (string $clave, string $ruta, string $texto, string $icono) use ($active) {
        $on = $active === $clave;
        return '<a href="' . e(route($ruta)) . '" class="app-nav-link' . ($on ? ' is-active' : '') . '"' . ($on ? ' aria-current="page"' : '') . '>'
            . '<svg viewBox="0 0 24 24" aria-hidden="true">' . $icono . '</svg>' . e($texto) . '</a>';
    };
@endphp

<aside class="app-sidebar" id="app-sidebar">
    {{-- Cabecera: solo logo --}}
    <div class="app-sidebar-head">
        <div class="app-sidebar-brand">
            <img class="app-sidebar-brand-mark" src="{{ asset('assets/img/favicon.png') }}" alt="">
            <img class="app-sidebar-brand-logo" src="{{ asset('assets/img/logo-negativo.png') }}" alt="ESAT">
        </div>
    </div>

    {{-- Navegación (los íconos de Pagos, Comunicados y Foro son los de la Secretaría) --}}
    <nav class="app-sidebar-nav" aria-label="Menú principal">

        <div class="app-nav-group">
            <div class="app-nav-group-label">Principal</div>
            <ul class="app-nav">
                <li>{!! $enlace('inicio', 'estudiante.dashboard', 'Inicio', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>') !!}</li>
            </ul>
        </div>

        <div class="app-nav-group">
            <div class="app-nav-group-label">Mi formación</div>
            <ul class="app-nav">
                <li>{!! $enlace('programa', 'estudiante.programa', 'Mi carrera', '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>') !!}</li>
                <li>{!! $enlace('aula', 'estudiante.aula', 'Aula virtual', '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>') !!}</li>
            </ul>
        </div>

        <div class="app-nav-group">
            <div class="app-nav-group-label">Matrícula</div>
            <ul class="app-nav">
                <li>{!! $enlace('pagos', 'estudiante.pagos', 'Mis pagos', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>') !!}</li>
            </ul>
        </div>

        <div class="app-nav-group">
            <div class="app-nav-group-label">Comunicación</div>
            <ul class="app-nav">
                <li>{!! $enlace('comunicados', 'estudiante.comunicados', 'Comunicados', '<path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>') !!}</li>
                <li>{!! $enlace('foro', 'estudiante.foro', 'Foro', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>') !!}</li>
            </ul>
        </div>

    </nav>

    {{-- Footer: usuario --}}
    <div class="app-sidebar-user">
        <div class="app-sidebar-user-avatar">{{ $initials }}</div>
        <div class="app-sidebar-user-info">
            <div class="app-sidebar-user-name">{{ auth()->user()->nombres }}</div>
            <div class="app-sidebar-user-role">{{ auth()->user()->role->label() }}</div>
        </div>
    </div>
</aside>

<div class="app-sidebar-backdrop"></div>
