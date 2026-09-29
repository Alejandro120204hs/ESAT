@php
    $active = $active ?? '';
    $sedeName = auth()->user()->sede?->nombre ?? 'Sin sede';
    $initials = strtoupper(substr(auth()->user()->nombres ?? auth()->user()->name ?? 'A', 0, 1) . substr(auth()->user()->apellidos ?? '', 0, 1));
    if (strlen($initials) < 1) $initials = 'A';
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
        <div class="app-nav-group">
            <div class="app-nav-group-label">Principal</div>
            <ul class="app-nav">
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="app-nav-link {{ $active === 'inicio' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Inicio
                    </a>
                </li>
            </ul>
        </div>

        <div class="app-nav-group">
            <div class="app-nav-group-label">Académico</div>
            <ul class="app-nav">
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'programas' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Programas
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'estudiantes' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Estudiantes
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'docentes' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/><path d="M2 10h2M20 10h2"/></svg>
                        Docentes
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'cursos' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Cursos
                    </a>
                </li>
            </ul>
        </div>

        <div class="app-nav-group">
            <div class="app-nav-group-label">Gestión</div>
            <ul class="app-nav">
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'pagos' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                        Pagos
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'foro' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Foro
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'reportes' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Reportes
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    {{-- Sede + usuario en el footer --}}
    <div class="app-sidebar-sede">
        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
        <div>
            <span class="app-sidebar-sede-label">Sede</span>
            <span class="app-sidebar-sede-name">{{ $sedeName }}</span>
        </div>
    </div>

    <div class="app-sidebar-user">
        <div class="app-sidebar-user-avatar">{{ $initials }}</div>
        <div class="app-sidebar-user-info">
            <div class="app-sidebar-user-name">{{ auth()->user()->nombres ?? auth()->user()->name }}</div>
            <div class="app-sidebar-user-role">{{ auth()->user()->role->label() }}</div>
        </div>
    </div>
</aside>

<div class="app-sidebar-backdrop"></div>
