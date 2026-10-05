@php
    $initials = mb_strtoupper(mb_substr(auth()->user()->nombres ?? auth()->user()->name ?? 'A', 0, 1) . mb_substr(auth()->user()->apellidos ?? '', 0, 1));
    if (strlen($initials) < 1) $initials = 'A';
    $topbarSede = auth()->user()->sede?->nombre ?? 'Sin sede';
@endphp

<header class="app-topbar">
    <div class="app-topbar-left">
        <button class="app-menu-toggle" aria-label="Abrir menú">
            <svg viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="app-topbar-sede">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>{{ $topbarSede }}</span>
        </div>
    </div>

    <div class="app-user">
        <div class="app-user-info">
            <div class="app-user-name">{{ auth()->user()->nombres ?? auth()->user()->name }}</div>
            <div class="app-user-role">{{ auth()->user()->role->label() }}</div>
        </div>
        <button class="app-user-trigger" aria-label="Menú de usuario" aria-haspopup="true">
            <div class="app-user-avatar">{{ $initials }}</div>
        </button>
        <div class="app-user-dropdown">
            <a href="{{ route('profile.edit') }}" class="app-user-dropdown-link">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/></svg>
                Ver mi perfil
            </a>
            <div class="app-user-dropdown-divider"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="app-user-dropdown-link app-user-dropdown-logout">
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>
