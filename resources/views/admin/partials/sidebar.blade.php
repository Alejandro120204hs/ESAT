@php
    $active = $active ?? '';
    $sedeName = auth()->user()->sede?->nombre ?? 'Sin sede';
    $initials = mb_strtoupper(mb_substr(auth()->user()->nombres ?? auth()->user()->name ?? 'A', 0, 1) . mb_substr(auth()->user()->apellidos ?? '', 0, 1));
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

        {{-- Principal --}}
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

        {{-- Académico --}}
        <div class="app-nav-group">
            <div class="app-nav-group-label">Académico</div>
            <ul class="app-nav">
                <li>
                    <a href="{{ route('admin.programas') }}" class="app-nav-link {{ $active === 'programas' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Programas
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.docentes') }}" class="app-nav-link {{ $active === 'docentes' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/><path d="M2 10h2M20 10h2"/></svg>
                        Docentes
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.cursos') }}" class="app-nav-link {{ $active === 'cursos' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Grupos
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.horarios') }}" class="app-nav-link {{ $active === 'horarios' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        Horarios
                    </a>
                </li>
            </ul>
        </div>

        {{-- Inscripciones --}}
        <div class="app-nav-group">
            <div class="app-nav-group-label">Inscripciones</div>
            <ul class="app-nav">
                <li>
                    <a href="{{ route('admin.estudiantes') }}" class="app-nav-link {{ $active === 'estudiantes' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Estudiantes
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'acudientes' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Acudientes
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'matriculas' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                        Matrículas
                    </a>
                </li>
            </ul>
        </div>

        {{-- Seguimiento --}}
        <div class="app-nav-group">
            <div class="app-nav-group-label">Seguimiento</div>
            <ul class="app-nav">
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'asistencia' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Asistencia
                    </a>
                </li>
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'calificaciones' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Calificaciones
                    </a>
                </li>
            </ul>
        </div>

        {{-- Finanzas --}}
        <div class="app-nav-group app-nav-group--flyout">
            <button type="button"
                class="app-nav-flyout-btn {{ in_array($active, ['pagos','contabilidad']) ? 'is-active' : '' }}"
                data-flyout="flyout-finanzas">
                Finanzas
                <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
            </button>
        </div>

        {{-- Comunicación --}}
        <div class="app-nav-group app-nav-group--flyout">
            <button type="button"
                class="app-nav-flyout-btn {{ in_array($active, ['comunicados','foro']) ? 'is-active' : '' }}"
                data-flyout="flyout-comunicacion">
                Comunicación
                <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
            </button>
        </div>

        <div class="app-nav-sep" style="margin: 8px 10px;"></div>

        {{-- Reportes --}}
        <div class="app-nav-group">
            <ul class="app-nav">
                <li>
                    <a href="#" class="app-nav-link {{ $active === 'reportes' ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Reportes
                    </a>
                </li>
            </ul>
        </div>

    </nav>

    {{-- Footer: usuario + sede combinados --}}
    <div class="app-sidebar-user">
        <div class="app-sidebar-user-avatar">{{ $initials }}</div>
        <div class="app-sidebar-user-info">
            <div class="app-sidebar-user-name">{{ auth()->user()->nombres ?? auth()->user()->name }}</div>
            <div class="app-sidebar-user-role">{{ auth()->user()->role->label() }}</div>
        </div>
    </div>
</aside>

<div class="app-sidebar-backdrop"></div>
