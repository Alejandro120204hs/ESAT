{{-- Académico (Admin de ESAT general) — SOLO FRONTEND con datos de ejemplo (public/assets/js/admin_general/datos-ejemplo.js).
     Programas y módulos reales; docentes, grupos, asistencia y notas ficticios (los grupos cuadran con el Inicio).
     SOLO CONSULTA de todas las sedes o de una: pestañas Grupos, Asistencia y Notas (los docentes tienen su pantalla), con exportar a Excel.
     La asistencia es informativa (80 % es solo referencia); las notas las pone el docente. Para corregir algo se entra a la sede. --}}
@extends('admin_general.layout')

@section('title', 'Académico')
@section('active', 'academico')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/academico.css') }}?v={{ filemtime(public_path('assets/css/admin_general/academico.css')) }}">
@endpush

@section('content')
<div class="aca-wrap app-anim">

    <div class="aca-header">
        <div>
            <h1 class="aca-title">Académico</h1>
            <p class="aca-sub">Grupos, asistencia y notas de todas las sedes, para ver dónde hace falta atención. Es solo consulta: la asistencia y las notas las registran los docentes.</p>
        </div>
        <button type="button" class="aca-btn aca-btn-ghost" id="aca-exportar" title="Descarga la pestaña que estás viendo para abrirla en Excel">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exportar
        </button>
    </div>

    <div class="aca-chips" id="aca-chips" role="radiogroup" aria-label="Sede">
        <button type="button" class="aca-chip is-active" role="radio" aria-checked="true" data-sede="">Todas las sedes</button>
    </div>

    <div class="aca-kpis" id="aca-kpis"></div>

    <div class="aca-ptabs" role="tablist">
        <button type="button" class="aca-ptab is-active" role="tab" data-ptab="grupos" aria-selected="true">Grupos</button>
        <button type="button" class="aca-ptab" role="tab" data-ptab="asistencia" aria-selected="false">Asistencia</button>
        <button type="button" class="aca-ptab" role="tab" data-ptab="notas" aria-selected="false">Notas</button>
    </div>

    <div class="aca-filters">
        <div class="aca-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="aca-buscar" placeholder="Buscar..." autocomplete="off" aria-label="Buscar">
        </div>
        <div class="app-combobox" id="aca-cb-filtro" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="Filtro">
                <span data-combobox-label>Todos</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        <span class="aca-count" id="aca-count"></span>
    </div>

    <p class="aca-nota" id="aca-nota" hidden></p>

    <div class="aca-table-wrap">
        <table class="aca-table">
            <thead id="aca-head"></thead>
            <tbody id="aca-body"></tbody>
        </table>
        <p class="aca-empty" id="aca-empty" hidden>No hay resultados con esos filtros.</p>
    </div>
    <nav class="aca-pagination" id="aca-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Modal: detalle (docente o grupo) ─── --}}
<div class="aca-modal" id="aca-modal" hidden>
    <div class="aca-modal-overlay" data-cerrar-modal></div>
    <div class="aca-modal-panel aca-modal-lg" role="dialog" aria-modal="true" aria-labelledby="aca-md-titulo">
        <div class="aca-modal-head">
            <div>
                <h2 class="aca-modal-title" id="aca-md-titulo"></h2>
                <p class="aca-modal-sub" id="aca-md-sub"></p>
            </div>
            <button type="button" class="aca-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="aca-modal-body" id="aca-md-cuerpo"></div>
        <div class="aca-modal-foot">
            <button type="button" class="aca-btn aca-btn-ghost" id="aca-md-entrar">Entrar a la sede</button>
            <button type="button" class="aca-btn aca-btn-primary" data-cerrar-modal>Cerrar</button>
        </div>
    </div>
</div>

<div class="aca-toast" id="aca-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin_general/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/admin_general/datos-ejemplo.js')) }}"></script>
    <script src="{{ asset('assets/js/admin_general/academico.js') }}?v={{ filemtime(public_path('assets/js/admin_general/academico.js')) }}" defer></script>
@endpush
