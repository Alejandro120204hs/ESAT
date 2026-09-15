@php $active = $active ?? null; @endphp

<aside class="app-sidebar">
    <div class="app-sidebar-brand">
        <img src="{{ asset('assets/img/favicon.png') }}" alt="">
        <span>Superadmin</span>
    </div>

    <ul class="app-nav">
        <li>
            <a href="{{ route('superadmin.dashboard') }}" class="app-nav-link {{ $active === 'inicio' ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>
                Inicio
            </a>
        </li>
        <li>
            <span class="app-nav-soon">
                <svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/><circle cx="17" cy="7" r="3"/><path d="M22 21v-1a5 5 0 0 0-3.5-4.8"/></svg>
                Administradores
                <span class="app-nav-soon-tag">Pronto</span>
            </span>
        </li>
        <li>
            <span class="app-nav-soon">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v3M16 4v3"/></svg>
                Sedes y programas
                <span class="app-nav-soon-tag">Pronto</span>
            </span>
        </li>
        <li>
            <span class="app-nav-soon">
                <svg viewBox="0 0 24 24"><path d="M4 4h16v14l-8 4-8-4Z"/><path d="M9 12h6M9 9h6M9 15h3"/></svg>
                Auditoría
                <span class="app-nav-soon-tag">Pronto</span>
            </span>
        </li>
        <li>
            <span class="app-nav-soon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
                Configuración
                <span class="app-nav-soon-tag">Pronto</span>
            </span>
        </li>
    </ul>

    <p class="app-sidebar-footer">© {{ date('Y') }} ESAT</p>
</aside>
