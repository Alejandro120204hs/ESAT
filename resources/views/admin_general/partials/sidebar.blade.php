@php
    $active = $active ?? '';
    $initials = mb_strtoupper(mb_substr(auth()->user()->nombres ?? 'A', 0, 1) . mb_substr(auth()->user()->apellidos ?? '', 0, 1));
    if (mb_strlen($initials) < 1) $initials = 'A';

    $menu = [
        'Principal' => [
            ['inicio', 'general.dashboard', 'Inicio', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
        ],
        'Estructura' => [
            ['sedes', 'general.sedes', 'Sedes', '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>'],
            ['programas', 'general.programas', 'Programas', '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
            ['secretarias', 'general.secretarias', 'Secretarías', '<circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/><circle cx="17" cy="7" r="3"/><path d="M22 21v-1a5 5 0 0 0-3.5-4.8"/>'],
        ],
        'Personas' => [
            ['estudiantes', 'general.estudiantes', 'Estudiantes', '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/>'],
            ['acudientes', 'general.acudientes', 'Acudientes', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['docentes', 'general.docentes', 'Docentes', '<circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/><path d="M2 10h2M20 10h2"/>'],
        ],
        'Seguimiento' => [
            ['academico', 'general.academico', 'Académico', '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
            ['finanzas', 'general.finanzas', 'Finanzas', '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
        ],
        'Comunicación' => [
            ['comunicados', 'general.comunicados', 'Comunicados', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
            ['foro', 'general.foro', 'Foro', '<path d="M17 8h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/><path d="M15 3H5a2 2 0 0 0-2 2v11l4-4h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/>'],
        ],
        'Supervisión' => [
            ['reportes', 'general.reportes', 'Reportes', '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
            ['auditoria', 'general.auditoria', 'Auditoría', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>'],
            ['configuracion', 'general.configuracion', 'Configuración', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
        ],
    ];

    // Grupos que se despliegan a un lado en vez de mostrar sus opciones en el menú
    $desplegables = ['Supervisión'];
@endphp

<aside class="app-sidebar">
    {{-- Cabecera: solo logo --}}
    <div class="app-sidebar-head">
        <div class="app-sidebar-brand">
            <img class="app-sidebar-brand-mark" src="{{ asset('assets/img/favicon.png') }}" alt="">
            <img class="app-sidebar-brand-logo" src="{{ asset('assets/img/logo-negativo.png') }}" alt="ESAT">
        </div>
    </div>

    {{-- Navegación --}}
    <nav class="app-sidebar-nav" aria-label="Menú principal">
        @foreach ($menu as $grupo => $items)
        @if (in_array($grupo, $desplegables, true))
        {{-- Grupo que se despliega a un lado (como Finanzas y Comunicación en la secretaría) --}}
        <div class="app-nav-group app-nav-group--flyout">
            <button type="button"
                class="app-nav-flyout-btn {{ in_array($active, array_column($items, 0), true) ? 'is-active' : '' }}"
                data-flyout="flyout-{{ \Illuminate\Support\Str::slug($grupo) }}" aria-haspopup="true" aria-expanded="false">
                {{ $grupo }}
                <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
            </button>
        </div>
        @else
        <div class="app-nav-group">
            <div class="app-nav-group-label">{{ $grupo }}</div>
            <ul class="app-nav">
                @foreach ($items as [$clave, $ruta, $texto, $icono])
                <li>
                    <a href="{{ route($ruta) }}" class="app-nav-link {{ $active === $clave ? 'is-active' : '' }}" @if ($active === $clave) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24">{!! $icono !!}</svg>
                        {{ $texto }}
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
        @endforeach
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

{{-- Paneles de los grupos desplegables (fuera del sidebar: position fixed, al lado del botón) --}}
@foreach ($menu as $grupo => $items)
@if (in_array($grupo, $desplegables, true))
<div id="flyout-{{ \Illuminate\Support\Str::slug($grupo) }}" class="app-nav-flyout" role="menu" aria-label="{{ $grupo }}">
    <div class="app-nav-flyout-header">{{ $grupo }}</div>
    <ul class="app-nav">
        @foreach ($items as [$clave, $ruta, $texto, $icono])
        <li>
            <a href="{{ route($ruta) }}" class="app-nav-link {{ $active === $clave ? 'is-active' : '' }}" role="menuitem" @if ($active === $clave) aria-current="page" @endif>
                <svg viewBox="0 0 24 24">{!! $icono !!}</svg>
                {{ $texto }}
            </a>
        </li>
        @endforeach
    </ul>
</div>
@endif
@endforeach

<div class="app-sidebar-backdrop"></div>
