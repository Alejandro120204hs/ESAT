{{-- Foro (Comunicación): datos reales desde Admin\Foro\ForoController (ForoService).
     Un solo foro general por sede: estudiantes, docentes, acudientes y administración abren temas (con categoría
     fija) y responden; un adjunto por mensaje. Moderación después, con reportes: el admin fija, cierra o reabre
     temas, oculta mensajes con motivo (y los restaura), descarta reportes y responde como administración. --}}
@extends('admin.layout')

@section('title', 'Foro')
@section('page-title', 'Foro')
@section('active', 'foro')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/foro.css') }}?v={{ filemtime(public_path('assets/css/admin/foro.css')) }}">
@endpush

@section('content')
<div class="for-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="for-header">
        <div>
            <h2 class="for-title">Foro</h2>
            <p class="for-sub">El espacio donde estudiantes, docentes y acudientes de la sede conversan. Revisa los reportes, oculta lo que no cumpla las normas y responde como administración.</p>
        </div>
        <button type="button" class="for-btn for-btn-primary" id="for-btn-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo tema
        </button>
    </div>

    {{-- KPIs (los llena foro.js) --}}
    <div class="for-kpis">
        <div class="for-kpi-chip for-kpi-blue">
            <div class="for-kpi-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
            <div class="for-min0">
                <div class="for-kpi-val" id="for-k-temas"></div>
                <div class="for-kpi-lbl" id="for-k-temas-lbl"></div>
            </div>
        </div>
        <div class="for-kpi-chip for-kpi-green">
            <div class="for-kpi-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div class="for-min0">
                <div class="for-kpi-val" id="for-k-semana"></div>
                <div class="for-kpi-lbl" id="for-k-semana-lbl"></div>
            </div>
        </div>
        <div class="for-kpi-chip for-kpi-red">
            <div class="for-kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg></div>
            <div class="for-min0">
                <div class="for-kpi-val" id="for-k-reportes"></div>
                <div class="for-kpi-lbl" id="for-k-reportes-lbl"></div>
            </div>
        </div>
        <div class="for-kpi-chip for-kpi-orange">
            <div class="for-kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
            <div class="for-min0">
                <div class="for-kpi-val" id="for-k-sinresp"></div>
                <div class="for-kpi-lbl" id="for-k-sinresp-lbl"></div>
            </div>
        </div>
    </div>

    {{-- Vistas --}}
    <div class="for-vistas" role="tablist" aria-label="Vista">
        <button type="button" class="for-vista-btn is-active" data-vista="temas" role="tab" aria-selected="true">Temas <span class="for-vista-num" id="for-n-temas"></span></button>
        <button type="button" class="for-vista-btn" data-vista="reportes" role="tab" aria-selected="false">Reportes <span class="for-vista-num" id="for-n-reportes"></span></button>
        <button type="button" class="for-vista-btn" data-vista="ocultos" role="tab" aria-selected="false">Ocultos <span class="for-vista-num" id="for-n-ocultos"></span></button>
    </div>

    {{-- ═══════════ Temas ═══════════ --}}
    <section class="for-vista" data-panel="temas">
        <div class="for-filters">
            <div class="for-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="for-t-search" placeholder="Buscar por título, mensaje o persona..." autocomplete="off" aria-label="Buscar tema">
            </div>
            @foreach(['categoria' => 'Todas las categorías', 'estado' => 'Todos los temas', 'rol' => 'Cualquier autor'] as $k => $lbl)
            <div class="app-combobox" id="for-t-cb-{{ $k }}" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>{{ $lbl }}</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" id="for-t-{{ $k }}" data-combobox-value>
            </div>
            @endforeach
            <span class="for-count-badge" id="for-t-count"></span>
            <button type="button" class="for-btn for-btn-ghost for-btn-sm" id="for-exportar" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="for-table-wrap">
            <table class="for-table">
                <thead><tr><th>Tema</th><th>Categoría</th><th>Abierto por</th><th class="num">Respuestas</th><th>Última actividad</th><th></th></tr></thead>
                <tbody id="for-t-body"></tbody>
            </table>
            <div class="for-empty" id="for-t-empty" hidden>
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <p>No hay temas que coincidan con la búsqueda.</p>
            </div>
        </div>
        <div class="for-pagination" id="for-t-pagination" hidden></div>
    </section>

    {{-- ═══════════ Reportes ═══════════ --}}
    <section class="for-vista" data-panel="reportes" hidden>
        <p class="for-ayuda">Mensajes que alguien reportó como inapropiados. Ábrelos para leerlos en su conversación y decide: ocultarlos (nadie los vuelve a ver) o descartar el reporte (siguen visibles).</p>
        <div class="for-table-wrap">
            <table class="for-table">
                <thead><tr><th>Mensaje reportado</th><th>Escrito por</th><th>Reportes</th><th>Último reporte</th><th></th></tr></thead>
                <tbody id="for-r-body"></tbody>
            </table>
            <div class="for-empty" id="for-r-empty" hidden>
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <p>No hay reportes por revisar.</p>
            </div>
        </div>
    </section>

    {{-- ═══════════ Ocultos ═══════════ --}}
    <section class="for-vista" data-panel="ocultos" hidden>
        <p class="for-ayuda">Mensajes que la administración ocultó. Nadie más los ve; si se ocultó por error, ábrelo y restáuralo.</p>
        <div class="for-table-wrap">
            <table class="for-table">
                <thead><tr><th>Mensaje oculto</th><th>Escrito por</th><th>Motivo</th><th>Ocultado</th><th></th></tr></thead>
                <tbody id="for-o-body"></tbody>
            </table>
            <div class="for-empty" id="for-o-empty" hidden>
                <svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                <p>No hay mensajes ocultos.</p>
            </div>
        </div>
    </section>

</div>{{-- /for-wrap --}}

{{-- ═══════════ Cajón: la conversación de un tema (lo arma foro.js) ═══════════ --}}
<div class="for-drawer" id="for-drawer" aria-hidden="true">
    <div class="for-drawer-overlay" data-cerrar></div>
    <div class="for-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="for-dw-titulo">
        <div class="for-drawer-head">
            <div class="for-drawer-head-left">
                <div class="for-drawer-avatar" id="for-dw-avatar"></div>
                <div class="for-drawer-head-info">
                    <h3 class="for-drawer-title" id="for-dw-titulo"></h3>
                    <div class="for-drawer-sub" id="for-dw-sub"></div>
                    <div class="for-drawer-badges" id="for-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="for-close-btn" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="for-drawer-body" id="for-dw-body"></div>
        <div class="for-responder" id="for-responder"></div>
    </div>
</div>

{{-- ═══════════ Modal: nuevo tema / ocultar mensaje ═══════════ --}}
<div class="for-modal" id="for-modal" aria-hidden="true">
    <div class="for-modal-overlay" data-modal-cerrar></div>
    <div class="for-modal-panel" role="dialog" aria-modal="true" aria-labelledby="for-md-titulo">
        <div class="for-modal-head">
            <div>
                <h3 class="for-modal-title" id="for-md-titulo">Nuevo tema</h3>
                <div class="for-modal-step-lbl" id="for-md-sub"></div>
            </div>
            <button type="button" class="for-close-btn" data-modal-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="for-modal-body" id="for-md-body"></div>
        <div class="for-form-error" id="for-md-error" role="alert" hidden></div>
        <div class="for-modal-footer">
            <button type="button" class="for-btn for-btn-ghost" data-modal-cerrar id="for-md-cancelar">Cancelar</button>
            <button type="button" class="for-btn for-btn-primary" id="for-md-ok">Publicar tema</button>
        </div>
    </div>
</div>

<div class="for-toast" id="for-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var FORO_CFG = {
    ahora: @json(now()->format('Y-m-d H:i')),
    admin: @json(trim(auth()->user()->nombres.' '.auth()->user()->apellidos)),
    sede: @json(auth()->user()->sede?->nombre),
    yo: @json('x'.auth()->id()),
    url: @json(url('admin/foro'))
};
var FORO_DATOS = {
    personas: @json($personas),
    temas: @json($temas)
};
</script>
<script src="{{ asset('assets/js/admin/foro.js') }}?v={{ filemtime(public_path('assets/js/admin/foro.js')) }}"></script>
@endpush
