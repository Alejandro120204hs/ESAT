@php $title = $title ?? 'Panel Superadmin'; @endphp

<header class="app-topbar">
    <div class="app-topbar-left">
        <button type="button" class="app-menu-toggle" aria-label="Abrir menú">
            <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <h1>{{ $title }}</h1>
    </div>

    <div class="app-user">
        <div class="app-user-info">
            <div class="app-user-name">{{ auth()->user()->name }}</div>
            <div class="app-user-role">{{ auth()->user()->role->label() }}</div>
        </div>

        <button type="button" class="app-user-trigger" aria-haspopup="true" aria-expanded="false" aria-label="Abrir menú de usuario">
            <span class="app-user-avatar">{{ strtoupper(substr(auth()->user()->nombres ?? auth()->user()->email, 0, 1)) }}</span>
        </button>

        <div class="app-user-dropdown">
            <a href="{{ route('profile.edit') }}" class="app-user-dropdown-link">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-3.5 3.5-6 8-6s8 2.5 8 6"/></svg>
                Ver mi perfil
            </a>
            <div class="app-user-dropdown-divider"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="app-user-dropdown-link app-user-dropdown-logout">
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>
