{{--
    Docente › Mis grupos — PANTALLA SOLO FRONTEND (todavía sin backend).
    Todos los datos salen de window.DOC_DATOS (public/assets/js/docente/datos-ejemplo.js):
    programas, escuelas y jornadas son reales; grupos, módulos, estudiantes, acudientes,
    teléfonos, asistencias y notas son FICTICIOS.
    Esta pantalla solo consulta: no guarda nada. "Tomar asistencia" y "Poner notas" llevan a
    sus pantallas con ?grupo=ID; ahí se guarda vía DOC_API (sessionStorage) hasta que exista el backend.
    Deep link: /docente/grupos?grupo=ID abre la ficha de ese grupo.
--}}
@extends('docente.layout')

@section('title', 'Mis grupos')
@section('active', 'grupos')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/docente/grupos.css') }}?v={{ filemtime(public_path('assets/css/docente/grupos.css')) }}">
@endpush

@section('content')
<div class="grp-wrap app-anim" id="grp-app"
     data-url-asistencia="{{ route('docente.asistencia') }}"
     data-url-notas="{{ route('docente.notas') }}">

    {{-- Encabezado --}}
    <div class="grp-header">
        <h2 class="grp-title">Mis grupos</h2>
        <p class="grp-sub">Los grupos que tienes a cargo: horario, avance del programa, asistencia y lo que te falta por registrar en cada uno.</p>
    </div>

    {{-- Pestañas + búsqueda --}}
    <div class="grp-toolbar app-anim app-anim-d1">
        <div class="grp-vistas" role="tablist" aria-label="Estado de los grupos">
            <button type="button" class="grp-vista-btn is-active" role="tab" id="grp-tab-vigentes"
                    aria-selected="true" aria-controls="grp-panel" data-vista="vigentes">
                Vigentes <span class="grp-vista-num" id="grp-num-vigentes">0</span>
            </button>
            <button type="button" class="grp-vista-btn" role="tab" id="grp-tab-finalizados"
                    aria-selected="false" aria-controls="grp-panel" data-vista="finalizados" tabindex="-1">
                Finalizados <span class="grp-vista-num" id="grp-num-finalizados">0</span>
            </button>
        </div>

        <div class="grp-search-box">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="grp-search" placeholder="Buscar por programa, código o grupo..." autocomplete="off" aria-label="Buscar grupo por programa, código o grupo">
        </div>

        <span class="grp-count-badge" id="grp-count" aria-live="polite"></span>
    </div>

    {{-- Tarjetas de grupos (las arma grupos.js) --}}
    <section class="grp-panel app-anim app-anim-d2" id="grp-panel" role="tabpanel" aria-labelledby="grp-tab-vigentes">
        <div class="grp-grid" id="grp-grid"></div>

        <div class="grp-empty" id="grp-empty" hidden>
            <div class="grp-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <p class="grp-empty-ttl" id="grp-empty-ttl"></p>
            <p class="grp-empty-sub" id="grp-empty-sub"></p>
            <div class="grp-empty-acc">
                <button type="button" class="grp-btn grp-btn-ghost grp-btn-sm" id="grp-empty-limpiar" hidden>Limpiar búsqueda</button>
                <button type="button" class="grp-btn grp-btn-ghost grp-btn-sm" id="grp-empty-otra" hidden></button>
            </div>
        </div>
    </section>

</div>

{{-- ═══════════ FICHA DEL GRUPO (cajón lateral; el contenido lo arma grupos.js) ═══════════ --}}
<div class="grp-drawer" id="grp-drawer" aria-hidden="true">
    <div class="grp-drawer-overlay" data-cerrar></div>
    <div class="grp-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="grp-dw-titulo">

        <div class="grp-drawer-head">
            <div class="grp-drawer-head-left">
                <div class="grp-drawer-avatar" id="grp-dw-avatar" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
                <div class="grp-drawer-head-info">
                    <h3 class="grp-drawer-title" id="grp-dw-titulo"></h3>
                    <div class="grp-drawer-sub" id="grp-dw-sub"></div>
                    <div class="grp-drawer-badges" id="grp-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="grp-icon-btn" id="grp-dw-cerrar" data-cerrar aria-label="Cerrar ficha">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="grp-solo" id="grp-dw-solo" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <p><strong>Grupo finalizado: solo consulta.</strong> <span id="grp-dw-solo-txt"></span></p>
        </div>

        <div class="grp-dtabs" role="tablist" aria-label="Ficha del grupo">
            <button type="button" class="grp-dtab is-active" role="tab" id="grp-dtab-estudiantes"
                    aria-selected="true" aria-controls="grp-dpanel-estudiantes" data-tab="estudiantes">
                Estudiantes <span class="grp-dtab-num" id="grp-dtab-num-estudiantes"></span>
            </button>
            <button type="button" class="grp-dtab" role="tab" id="grp-dtab-malla"
                    aria-selected="false" aria-controls="grp-dpanel-malla" data-tab="malla" tabindex="-1">
                Malla <span class="grp-dtab-num" id="grp-dtab-num-malla"></span>
            </button>
            <button type="button" class="grp-dtab" role="tab" id="grp-dtab-horario"
                    aria-selected="false" aria-controls="grp-dpanel-horario" data-tab="horario" tabindex="-1">
                Horario
            </button>
        </div>

        <div class="grp-drawer-body" id="grp-dw-body">
            <section class="grp-dpanel" role="tabpanel" id="grp-dpanel-estudiantes" aria-labelledby="grp-dtab-estudiantes"></section>
            <section class="grp-dpanel" role="tabpanel" id="grp-dpanel-malla" aria-labelledby="grp-dtab-malla" hidden></section>
            <section class="grp-dpanel" role="tabpanel" id="grp-dpanel-horario" aria-labelledby="grp-dtab-horario" hidden></section>
        </div>

        <div class="grp-drawer-foot" id="grp-dw-foot" hidden>
            <a class="grp-btn grp-btn-primary" id="grp-dw-asistencia" href="#">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Tomar asistencia
            </a>
            <a class="grp-btn grp-btn-ghost" id="grp-dw-notas" href="#">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                Poner notas
            </a>
        </div>
    </div>
</div>

<div class="grp-toast" id="grp-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/docente/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/docente/datos-ejemplo.js')) }}" defer></script>
<script src="{{ asset('assets/js/docente/grupos.js') }}?v={{ filemtime(public_path('assets/js/docente/grupos.js')) }}" defer></script>
@endpush
