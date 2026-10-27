{{-- Comunicados (Comunicación): datos reales desde Admin\Comunicados\ComunicadoController (ComunicadoService).
     El admin publica a toda la sede o a un rol (estudiantes, docentes, acudientes); los docentes publican a sus
     grupos desde su panel y aquí el admin solo los ve y los archiva. Se registra quién lo leyó (comunicado_lecturas)
     y se puede reenviar por WhatsApp a quien no lo ha leído. Crear/editar: ComunicadoRequest; archivar:
     ArchivarComunicadoRequest. --}}
@extends('admin.layout')

@section('title', 'Comunicados')
@section('page-title', 'Comunicados')
@section('active', 'comunicados')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/comunicados.css') }}?v={{ filemtime(public_path('assets/css/admin/comunicados.css')) }}">
@endpush

@section('content')
<div class="com-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="com-header">
        <div>
            <h2 class="com-title">Comunicados</h2>
            <p class="com-sub">Publica avisos para toda la sede o para estudiantes, docentes o acudientes, y revisa quién ya los leyó. Aquí también ves lo que los docentes les publican a sus grupos.</p>
        </div>
        <button type="button" class="com-btn com-btn-primary" id="com-btn-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo comunicado
        </button>
    </div>

    {{-- KPIs (los llena comunicados.js) --}}
    <div class="com-kpis">
        <div class="com-kpi-chip com-kpi-blue">
            <div class="com-kpi-icon"><svg viewBox="0 0 24 24"><path d="M3 11l18-8v18l-18-8z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></div>
            <div class="com-min0">
                <div class="com-kpi-val" id="com-k-publicados"></div>
                <div class="com-kpi-lbl" id="com-k-publicados-lbl"></div>
            </div>
        </div>
        <div class="com-kpi-chip com-kpi-green">
            <div class="com-kpi-icon"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
            <div class="com-min0">
                <div class="com-kpi-val" id="com-k-lectura"></div>
                <div class="com-kpi-lbl" id="com-k-lectura-lbl"></div>
            </div>
        </div>
        <div class="com-kpi-chip com-kpi-orange">
            <div class="com-kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div class="com-min0">
                <div class="com-kpi-val" id="com-k-programados"></div>
                <div class="com-kpi-lbl" id="com-k-programados-lbl"></div>
            </div>
        </div>
        <div class="com-kpi-chip com-kpi-gray">
            <div class="com-kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></div>
            <div class="com-min0">
                <div class="com-kpi-val" id="com-k-borradores"></div>
                <div class="com-kpi-lbl" id="com-k-borradores-lbl"></div>
            </div>
        </div>
    </div>

    {{-- Vistas por estado --}}
    <div class="com-vistas" role="tablist" aria-label="Estado">
        <button type="button" class="com-vista-btn is-active" data-vista="publicado" role="tab" aria-selected="true">Publicados <span class="com-vista-num" id="com-n-publicado"></span></button>
        <button type="button" class="com-vista-btn" data-vista="programado" role="tab" aria-selected="false">Programados <span class="com-vista-num" id="com-n-programado"></span></button>
        <button type="button" class="com-vista-btn" data-vista="borrador" role="tab" aria-selected="false">Borradores <span class="com-vista-num" id="com-n-borrador"></span></button>
        <button type="button" class="com-vista-btn" data-vista="archivado" role="tab" aria-selected="false">Archivados <span class="com-vista-num" id="com-n-archivado"></span></button>
    </div>

    <section class="com-vista">
        <div class="com-filters">
            <div class="com-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="com-search" placeholder="Buscar por título, mensaje o autor..." autocomplete="off" aria-label="Buscar comunicado">
            </div>
            @foreach(['categoria' => 'Todas las categorías', 'destino' => 'Todos los destinatarios', 'autor' => 'Todos los autores'] as $k => $lbl)
            <div class="app-combobox" id="com-cb-{{ $k }}" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>{{ $lbl }}</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" id="com-{{ $k }}" data-combobox-value>
            </div>
            @endforeach
            <span class="com-count-badge" id="com-count"></span>
            <button type="button" class="com-btn com-btn-ghost com-btn-sm" id="com-exportar" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="com-table-wrap">
            <table class="com-table">
                <thead><tr><th>Comunicado</th><th>Categoría</th><th>Para</th><th>Autor</th><th id="com-th-fecha">Publicado</th><th id="com-th-lectura">Leído</th><th></th></tr></thead>
                <tbody id="com-body"></tbody>
            </table>
            <div class="com-empty" id="com-empty" hidden>
                <svg viewBox="0 0 24 24"><path d="M3 11l18-8v18l-18-8z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                <p id="com-empty-txt">No hay comunicados que coincidan con la búsqueda.</p>
            </div>
        </div>
        <div class="com-pagination" id="com-pagination" hidden></div>
    </section>

</div>{{-- /com-wrap --}}

{{-- ═══════════ Cajón: ficha del comunicado (lo arma comunicados.js) ═══════════ --}}
<div class="com-drawer" id="com-drawer" aria-hidden="true">
    <div class="com-drawer-overlay" data-cerrar></div>
    <div class="com-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="com-dw-titulo">
        <div class="com-drawer-head">
            <div class="com-drawer-head-left">
                <div class="com-drawer-avatar" id="com-dw-avatar"></div>
                <div class="com-drawer-head-info">
                    <h3 class="com-drawer-title" id="com-dw-titulo"></h3>
                    <div class="com-drawer-sub" id="com-dw-sub"></div>
                    <div class="com-drawer-badges" id="com-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="com-close-btn" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="com-drawer-body" id="com-dw-body"></div>
    </div>
</div>

{{-- ═══════════ Modal: asistente (Destinatarios → Contenido → Publicación) / archivar / eliminar ═══════════ --}}
<div class="com-modal" id="com-modal" aria-hidden="true">
    <div class="com-modal-overlay" data-modal-cerrar></div>
    <div class="com-modal-panel" role="dialog" aria-modal="true" aria-labelledby="com-md-titulo">
        <div class="com-modal-head">
            <div>
                <h3 class="com-modal-title" id="com-md-titulo">Nuevo comunicado</h3>
                <div class="com-modal-step-lbl" id="com-md-paso"></div>
            </div>
            <button type="button" class="com-close-btn" data-modal-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="com-wsteps" id="com-md-steps">
            <div class="com-wstep" data-step="1"><div class="com-wstep-dot"></div><span>Destinatarios</span></div>
            <div class="com-wstep-line"></div>
            <div class="com-wstep" data-step="2"><div class="com-wstep-dot"></div><span>Contenido</span></div>
            <div class="com-wstep-line"></div>
            <div class="com-wstep" data-step="3"><div class="com-wstep-dot"></div><span>Publicación</span></div>
        </div>
        <div class="com-modal-body" id="com-md-body"></div>
        <div class="com-form-error" id="com-md-error" role="alert" hidden></div>
        <div class="com-modal-footer">
            <button type="button" class="com-btn com-btn-ghost" id="com-md-atras">Cancelar</button>
            <div class="com-modal-footer-der">
                <button type="button" class="com-btn com-btn-ghost" id="com-md-borrador" hidden>Guardar borrador</button>
                <button type="button" class="com-btn com-btn-primary" id="com-md-ok">Siguiente</button>
            </div>
        </div>
    </div>
</div>

<div class="com-toast" id="com-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var COM_CFG = {
    ahora: @json(now()->format('Y-m-d H:i')),
    admin: @json(trim(auth()->user()->nombres.' '.auth()->user()->apellidos)),
    sede: @json(auth()->user()->sede?->nombre),
    url: @json(url('admin/comunicados'))
};
var COM_DATOS = {
    personas: @json($personas),
    grupos: @json($grupos),
    comunicados: @json($comunicados)
};
</script>
<script src="{{ asset('assets/js/admin/comunicados.js') }}?v={{ filemtime(public_path('assets/js/admin/comunicados.js')) }}"></script>
@endpush
