{{-- Inicio (Admin de ESAT general) — SOLO FRONTEND con datos de ejemplo (public/assets/js/admin_general/datos-ejemplo.js):
     los nombres de las sedes son los de CLAUDE.md; personas, montos, estudiantes, grupos, cartera y actividad son ficticios.
     Bloques acordados con el usuario: filtro de sede, KPI de ESAT con variación, comparación de sedes (con "Entrar a la
     sede"), ingresos de los últimos 6 meses por sede, alertas y actividad reciente de las secretarías. dashboard.js pinta
     todo según la sede elegida y escapa lo que viene de los datos. --}}
@extends('admin_general.layout')

@section('title', 'Inicio')
@section('active', 'inicio')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/dashboard.css') }}?v={{ filemtime(public_path('assets/css/admin_general/dashboard.css')) }}">
@endpush

@section('content')

{{-- ─── Bienvenida + filtro de sede ─────────────────── --}}
<div class="gen-welcome app-anim">
    <img class="gen-welcome-mark" src="{{ asset('assets/img/favicon.png') }}" alt="">
    <div class="gen-welcome-text">
        <h1 class="gen-welcome-title">Hola, <span>{{ auth()->user()->nombres ?: 'Admin' }}.</span></h1>
        <p class="gen-welcome-desc" id="gen-welcome-desc">Así va ESAT hoy en todas sus sedes: estudiantes, plata y lo que necesita atención.</p>
    </div>
    <div class="gen-welcome-badge">
        <div class="gen-welcome-badge-num">{{ now()->locale('es')->translatedFormat('d') }}</div>
        <div class="gen-welcome-badge-label">{{ now()->locale('es')->translatedFormat('M Y') }}</div>
    </div>
</div>

<div class="gen-filtro app-anim app-anim-d1" role="radiogroup" aria-label="Sede" id="gen-filtro">
    <button type="button" class="gen-chip is-active" role="radio" aria-checked="true" data-sede="">Todas las sedes</button>
    {{-- Las sedes las agrega dashboard.js desde los datos --}}
</div>

{{-- ─── KPI ─────────────────────────────────────────── --}}
<div class="gen-kpi-grid" id="gen-kpis" aria-live="polite"></div>

{{-- ─── Comparación de sedes ────────────────────────── --}}
<section class="gen-card app-anim app-anim-d2" aria-labelledby="gen-comp-titulo">
    <div class="gen-card-head">
        <div>
            <h2 class="gen-card-title" id="gen-comp-titulo">Comparación de sedes</h2>
            <p class="gen-card-subtitle">Cómo va cada sede hoy. Toca una fila para ver solo esa sede.</p>
        </div>
    </div>
    <div class="gen-table-wrap">
        <table class="gen-table">
            <thead>
                <tr>
                    <th scope="col">Sede</th>
                    <th scope="col">Secretaría</th>
                    <th scope="col" class="gen-num">Estudiantes</th>
                    <th scope="col" class="gen-num">Ingresos del mes</th>
                    <th scope="col" class="gen-num">Cartera vencida</th>
                    <th scope="col">Ocupación de cupos</th>
                    <th scope="col"><span class="gen-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="gen-comp-body"></tbody>
            <tfoot id="gen-comp-foot"></tfoot>
        </table>
    </div>
</section>

{{-- ─── Ingresos + alertas ──────────────────────────── --}}
<div class="gen-grid app-anim app-anim-d3">
    <section class="gen-card" aria-labelledby="gen-ing-titulo">
        <div class="gen-card-head">
            <div>
                <h2 class="gen-card-title" id="gen-ing-titulo">Ingresos de los últimos 6 meses</h2>
                <p class="gen-card-subtitle" id="gen-ing-sub">Lo recaudado por mes, sin recibos anulados</p>
            </div>
        </div>
        <div class="gen-legend" id="gen-legend"></div>
        <div class="gen-chart" id="gen-chart">
            <div class="gen-chart-grid" id="gen-chart-grid" aria-hidden="true"></div>
            <div class="gen-chart-cols" id="gen-chart-cols"></div>
            <div class="gen-tip" id="gen-tip" role="tooltip" hidden></div>
        </div>
        <div class="gen-chart-foot" id="gen-chart-foot"></div>
    </section>

    <section class="gen-card" aria-labelledby="gen-al-titulo">
        <div class="gen-card-head">
            <div>
                <h2 class="gen-card-title" id="gen-al-titulo">Alertas</h2>
                <p class="gen-card-subtitle">Lo que necesita tu atención</p>
            </div>
            <span class="gen-count" id="gen-al-count"></span>
        </div>
        <div class="gen-card-body">
            <ul class="gen-alerts" id="gen-alerts"></ul>
            <button type="button" class="gen-btn gen-btn-ghost gen-al-mas" id="gen-al-mas" hidden></button>
        </div>
    </section>
</div>

{{-- ─── Actividad reciente ──────────────────────────── --}}
<section class="gen-card app-anim app-anim-d4" aria-labelledby="gen-act-titulo">
    <div class="gen-card-head">
        <div>
            <h2 class="gen-card-title" id="gen-act-titulo">Actividad reciente</h2>
            <p class="gen-card-subtitle" id="gen-act-sub">Lo último que se hizo en las sedes</p>
        </div>
    </div>
    <div class="gen-card-body gen-act-body">
        <ul class="gen-act-list" id="gen-act-list"></ul>
        <button type="button" class="gen-btn gen-btn-ghost gen-act-mas" id="gen-act-mas" hidden>Ver más actividad</button>
    </div>
</section>

{{-- ─── Modal: detalle de actividad ─────────────────── --}}
<div class="gen-modal-overlay" id="gen-act-modal" hidden>
    <div class="gen-modal" role="dialog" aria-modal="true" aria-labelledby="gen-act-modal-titulo">
        <div class="gen-modal-head">
            <div class="gen-modal-dot" id="gen-act-modal-dot"></div>
            <div class="gen-modal-head-text">
                <h3 class="gen-modal-title" id="gen-act-modal-titulo">Actividad</h3>
                <p class="gen-modal-sub" id="gen-act-modal-sede"></p>
            </div>
            <button type="button" class="gen-modal-close" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="gen-modal-body">
            <p class="gen-modal-text" id="gen-act-modal-text"></p>
            <p class="gen-modal-time" id="gen-act-modal-time"></p>
        </div>
    </div>
</div>

{{-- ─── Modal: entrar a la sede ─────────────────────── --}}
<div class="gen-modal-overlay" id="gen-entrar-modal" hidden>
    <div class="gen-modal" role="dialog" aria-modal="true" aria-labelledby="gen-entrar-titulo">
        <div class="gen-modal-head">
            <div class="gen-modal-head-text">
                <h3 class="gen-modal-title" id="gen-entrar-titulo">Entrar a la sede</h3>
                <p class="gen-modal-sub" id="gen-entrar-sub"></p>
            </div>
            <button type="button" class="gen-modal-close" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="gen-modal-body">
            <p class="gen-modal-text">Vas a trabajar en esta sede con las mismas pantallas de la secretaría: matricular, registrar pagos, editar grupos y horarios, publicar comunicados.</p>
            <ul class="gen-entrar-lista">
                <li>Arriba verás una franja con la sede en la que estás y el botón para volver al panel general.</li>
                <li>Todo lo que hagas queda en la auditoría de la sede con tu nombre.</li>
            </ul>
            <p class="gen-entrar-nota" id="gen-entrar-nota" hidden>Esta pantalla todavía es una maqueta: entrar a la sede funcionará cuando se conecte el backend.</p>
        </div>
        <div class="gen-modal-foot">
            <button type="button" class="gen-btn gen-btn-ghost" data-cerrar>Cancelar</button>
            <button type="button" class="gen-btn gen-btn-primary" id="gen-entrar-ok">Entrar a la sede</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin_general/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/admin_general/datos-ejemplo.js')) }}"></script>
    <script src="{{ asset('assets/js/admin_general/dashboard.js') }}?v={{ filemtime(public_path('assets/js/admin_general/dashboard.js')) }}" defer></script>
@endpush
