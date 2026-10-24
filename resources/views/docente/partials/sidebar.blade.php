@php
    $active = $active ?? '';
    $initials = mb_strtoupper(mb_substr(auth()->user()->nombres ?? 'D', 0, 1) . mb_substr(auth()->user()->apellidos ?? '', 0, 1));
    if (mb_strlen($initials) < 1) $initials = 'D';
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
                    <a href="{{ route('docente.dashboard') }}" class="app-nav-link {{ $active === 'inicio' ? 'is-active' : '' }}" @if ($active === 'inicio') aria-current="page" @endif>
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Inicio
                    </a>
                </li>
            </ul>
        </div>

        {{-- Mis clases --}}
        <div class="app-nav-group">
            <div class="app-nav-group-label">Mis clases</div>
            <ul class="app-nav">
                <li>
                    <a href="{{ route('docente.grupos') }}" class="app-nav-link {{ $active === 'grupos' ? 'is-active' : '' }}" @if ($active === 'grupos') aria-current="page" @endif>
                        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Mis grupos
                    </a>
                </li>
                <li>
                    <a href="{{ route('docente.asistencia') }}" class="app-nav-link {{ $active === 'asistencia' ? 'is-active' : '' }}" @if ($active === 'asistencia') aria-current="page" @endif>
                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Asistencia
                    </a>
                </li>
                <li>
                    <a href="{{ route('docente.notas') }}" class="app-nav-link {{ $active === 'notas' ? 'is-active' : '' }}" @if ($active === 'notas') aria-current="page" @endif>
                        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Notas
                    </a>
                </li>
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
