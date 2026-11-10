{{-- Auditoría (Admin de ESAT general). Solo consulta. Datos reales: AuditoriaController@index (SupervisionService) los pasa en
     window.GEN_DATOS: lo que hicieron secretarías, docentes y el Admin en el último año, con filtros de periodo, sede,
     persona y acción, detalle y exportar. auditoria.js escapa todo. --}}
@extends('admin_general.layout')

@section('title', 'Auditoría')
@section('active', 'auditoria')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/auditoria.css') }}?v={{ filemtime(public_path('assets/css/admin_general/auditoria.css')) }}">
@endpush

@section('content')
<div class="aud-wrap app-anim">

    <div class="aud-header">
        <div>
            <h1 class="aud-title">Auditoría</h1>
            <p class="aud-sub">Todo lo que se ha hecho en las sedes: quién lo hizo, qué hizo y cuándo. Sirve para revisar pagos anulados, cambios en matrículas o cuentas desactivadas.</p>
        </div>
        <button type="button" class="aud-btn aud-btn-ghost" id="aud-exportar" title="Descarga los eventos filtrados para abrirlos en Excel">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exportar
        </button>
    </div>

    <div class="aud-kpis" id="aud-kpis"></div>

    <div class="aud-filters">
        <div class="aud-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="aud-buscar" placeholder="Buscar en el detalle (nombre, recibo, programa)..." autocomplete="off" aria-label="Buscar evento">
        </div>
        @foreach ([['aud-cb-periodo', 'Últimos 30 días'], ['aud-cb-sede', 'Todas las sedes'], ['aud-cb-persona', 'Todas las personas'], ['aud-cb-tipo', 'Todas las acciones']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
        <span class="aud-count" id="aud-count"></span>
    </div>

    <div class="aud-table-wrap">
        <table class="aud-table">
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Quién</th>
                    <th scope="col">Sede</th>
                    <th scope="col">Acción</th>
                    <th scope="col">Detalle</th>
                </tr>
            </thead>
            <tbody id="aud-body"></tbody>
        </table>
        <p class="aud-empty" id="aud-empty" hidden>No hay eventos con esos filtros.</p>
    </div>

    <nav class="aud-pagination" id="aud-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Modal: detalle del evento ─── --}}
<div class="aud-modal" id="aud-modal" hidden>
    <div class="aud-modal-overlay" data-cerrar-modal></div>
    <div class="aud-modal-panel" role="dialog" aria-modal="true" aria-labelledby="aud-md-titulo">
        <div class="aud-modal-head">
            <div>
                <h2 class="aud-modal-title" id="aud-md-titulo"></h2>
                <p class="aud-modal-sub" id="aud-md-sub"></p>
            </div>
            <button type="button" class="aud-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="aud-modal-body">
            <p class="aud-modal-text" id="aud-md-texto"></p>
            <div class="aud-info" id="aud-md-info"></div>
        </div>
        <div class="aud-modal-foot">
            <button type="button" class="aud-btn aud-btn-ghost" id="aud-md-persona">Ver todo lo de esta persona</button>
            <button type="button" class="aud-btn aud-btn-primary" data-cerrar-modal>Cerrar</button>
        </div>
    </div>
</div>

<div class="aud-toast" id="aud-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/auditoria.js') }}?v={{ filemtime(public_path('assets/js/admin_general/auditoria.js')) }}" defer></script>
@endpush
