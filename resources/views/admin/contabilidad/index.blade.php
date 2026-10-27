{{-- Contabilidad (Finanzas): datos reales desde Admin\Contabilidad\ContabilidadController (ContabilidadService).
     Pestañas: Resumen del mes · Egresos (nómina docentes, arriendo y servicios, materiales e insumos; las
     devoluciones de Matrículas aparecen solas, solo consulta) · Ingresos (lo recaudado en Pagos, solo consulta).
     Registrar, editar y anular un egreso: EgresoController (EgresoRequest / AnularEgresoRequest). Sin cierre de mes. --}}
@extends('admin.layout')

@section('title', 'Contabilidad')
@section('page-title', 'Contabilidad')
@section('active', 'contabilidad')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/contabilidad.css') }}?v={{ filemtime(public_path('assets/css/admin/contabilidad.css')) }}">
@endpush

@php $sede = auth()->user()->sede; @endphp

@section('content')
<div class="con-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="con-header">
        <div>
            <h2 class="con-title">Contabilidad</h2>
            <p class="con-sub">Registra los gastos de la sede y compáralos con lo que entra por los pagos de los estudiantes para saber cómo cerró cada mes.</p>
        </div>
        <button type="button" class="con-btn con-btn-primary" id="con-btn-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Registrar egreso
        </button>
    </div>

    {{-- KPIs del mes en curso (los llena contabilidad.js) --}}
    <div class="con-kpis">
        <div class="con-kpi-chip con-kpi-green">
            <div class="con-kpi-icon"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
            <div class="con-min0">
                <div class="con-kpi-val" id="con-k-ingresos"></div>
                <div class="con-kpi-lbl" id="con-k-ingresos-lbl"></div>
            </div>
        </div>
        <div class="con-kpi-chip con-kpi-red">
            <div class="con-kpi-icon"><svg viewBox="0 0 24 24"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg></div>
            <div class="con-min0">
                <div class="con-kpi-val" id="con-k-egresos"></div>
                <div class="con-kpi-lbl" id="con-k-egresos-lbl"></div>
            </div>
        </div>
        <div class="con-kpi-chip con-kpi-blue">
            <div class="con-kpi-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="con-min0">
                <div class="con-kpi-val" id="con-k-saldo"></div>
                <div class="con-kpi-lbl" id="con-k-saldo-lbl"></div>
            </div>
        </div>
        <div class="con-kpi-chip con-kpi-orange">
            <div class="con-kpi-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <div class="con-min0">
                <div class="con-kpi-val" id="con-k-nomina"></div>
                <div class="con-kpi-lbl" id="con-k-nomina-lbl"></div>
            </div>
        </div>
    </div>

    {{-- Vistas --}}
    <div class="con-vistas" role="tablist" aria-label="Vista">
        <button type="button" class="con-vista-btn is-active" data-vista="resumen" role="tab" aria-selected="true">Resumen del mes</button>
        <button type="button" class="con-vista-btn" data-vista="egresos" role="tab" aria-selected="false">Egresos <span class="con-vista-num" id="con-n-egresos"></span></button>
        <button type="button" class="con-vista-btn" data-vista="ingresos" role="tab" aria-selected="false">Ingresos</button>
    </div>

    {{-- ═══════════ Resumen del mes ═══════════ --}}
    <section class="con-vista" data-panel="resumen">
        <div class="con-filters">
            <div class="app-combobox" id="con-s-cb-mes" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Este mes</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" id="con-s-mes" data-combobox-value>
            </div>
            <span class="con-filters-sep"></span>
            <button type="button" class="con-btn con-btn-ghost con-btn-sm" id="con-resumen-imprimir">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir resumen
            </button>
        </div>
        <div id="con-resumen"></div>
    </section>

    {{-- ═══════════ Egresos ═══════════ --}}
    <section class="con-vista" data-panel="egresos" hidden>
        <div class="con-filters">
            <div class="con-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="con-e-search" placeholder="Buscar por comprobante, concepto, beneficiario o factura..." autocomplete="off" aria-label="Buscar egreso">
            </div>
            @foreach(['categoria' => 'Todas las categorías', 'periodo' => 'Este mes', 'metodo' => 'Todos los medios'] as $k => $lbl)
            <div class="app-combobox" id="con-e-cb-{{ $k }}" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>{{ $lbl }}</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" id="con-e-{{ $k }}" data-combobox-value>
            </div>
            @endforeach
            <span class="con-count-badge" id="con-e-count"></span>
            <button type="button" class="con-btn con-btn-ghost con-btn-sm" id="con-e-exportar" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="con-table-wrap">
            <table class="con-table">
                <thead><tr><th>Comprobante</th><th>Concepto</th><th>Categoría</th><th>Medio de pago</th><th class="num">Valor</th><th></th></tr></thead>
                <tbody id="con-e-body"></tbody>
                <tfoot id="con-e-foot"></tfoot>
            </table>
            <div class="con-empty" id="con-e-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>No hay egresos en esas fechas o con esos filtros.</p>
            </div>
        </div>
        <div class="con-pagination" id="con-e-pagination" hidden></div>
    </section>

    {{-- ═══════════ Ingresos (solo consulta) ═══════════ --}}
    <section class="con-vista" data-panel="ingresos" hidden>
        <div class="con-filters">
            <div class="app-combobox" id="con-i-cb-mes" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Este mes</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" id="con-i-mes" data-combobox-value>
            </div>
            <span class="con-filters-sep"></span>
            <a class="con-btn con-btn-ghost con-btn-sm" href="{{ route('admin.pagos') }}">
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                Ir a Pagos
            </a>
        </div>
        <p class="con-ayuda">Lo que pagaron los estudiantes en el mes, sin contar los recibos anulados. Los pagos se registran en Pagos o en Matrículas: aquí solo se consultan.</p>
        <div id="con-ingresos"></div>
    </section>

</div>{{-- /con-wrap --}}

{{-- ═══════════ Cajón: egreso / devolución (lo arma contabilidad.js) ═══════════ --}}
<div class="con-drawer" id="con-drawer" aria-hidden="true">
    <div class="con-drawer-overlay" data-cerrar></div>
    <div class="con-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="con-dw-titulo">
        <div class="con-drawer-head">
            <div class="con-drawer-head-left">
                <div class="con-drawer-avatar" id="con-dw-avatar"></div>
                <div class="con-drawer-head-info">
                    <h3 class="con-drawer-title" id="con-dw-titulo"></h3>
                    <div class="con-drawer-sub" id="con-dw-sub"></div>
                    <div class="con-drawer-badges" id="con-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="con-close-btn" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="con-drawer-body" id="con-dw-body"></div>
    </div>
</div>

{{-- ═══════════ Modal: registrar / editar / anular egreso ═══════════ --}}
<div class="con-modal" id="con-modal" aria-hidden="true">
    <div class="con-modal-overlay" data-modal-cerrar></div>
    <div class="con-modal-panel" role="dialog" aria-modal="true" aria-labelledby="con-md-titulo">
        <div class="con-modal-head">
            <div>
                <h3 class="con-modal-title" id="con-md-titulo">Registrar egreso</h3>
                <div class="con-modal-step-lbl" id="con-md-paso"></div>
            </div>
            <button type="button" class="con-close-btn" data-modal-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="con-modal-body" id="con-md-body"></div>
        <div class="con-form-error" id="con-md-error" role="alert" hidden></div>
        <div class="con-modal-footer">
            <button type="button" class="con-btn con-btn-ghost" id="con-md-atras">Cancelar</button>
            <button type="button" class="con-btn con-btn-primary" id="con-md-ok">Registrar egreso</button>
        </div>
    </div>
</div>

{{-- ═══════════ Hoja imprimible: comprobante de egreso / resumen del mes (datos legales reales de ESAT) ═══════════ --}}
<div class="con-hoja-modal" id="con-hoja-modal" aria-hidden="true">
    <div class="con-hoja-overlay" data-hoja-cerrar></div>
    <div class="con-hoja-dialog" role="dialog" aria-modal="true" aria-labelledby="con-hoja-tipo">
        <div class="con-hoja-acciones">
            <button type="button" class="con-btn con-btn-ghost con-btn-sm" data-hoja-cerrar>Cerrar</button>
            <button type="button" class="con-btn con-btn-primary con-btn-sm" id="con-hoja-imprimir">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir o guardar en PDF
            </button>
        </div>
        <article class="con-hoja">
            <header class="con-hoja-cab">
                <div class="con-hoja-marca">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT">
                    <div>
                        <strong>Escuela Nacional de Educación ESAT</strong>
                        <span>NIT 900820764-1 · Lic. Funcionamiento 5850</span>
                        @if($sede)
                            <span>Sede {{ $sede->nombre }}@if($sede->ciudad) · {{ $sede->ciudad }}@endif</span>
                        @endif
                    </div>
                </div>
                <div class="con-hoja-num">
                    <span id="con-hoja-tipo"></span>
                    <strong id="con-hoja-numero"></strong>
                </div>
            </header>
            <div id="con-hoja-cuerpo"></div>
        </article>
    </div>
</div>

<div class="con-toast" id="con-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var CON_CFG = {
    hoy: @json(today()->toDateString()),
    admin: @json(trim(auth()->user()->nombres.' '.auth()->user()->apellidos)),
    ciudad: @json($sede?->ciudad),
    matriculasUrl: @json(route('admin.matriculas')),
    egresosUrl: @json(url('admin/contabilidad/egresos'))
};
var CON_DATOS = {
    docentes: @json($docentes),
    metodos: @json($metodos),
    egresos: @json($egresos),
    devoluciones: @json($devoluciones),
    ingresos: @json($ingresos)
};
</script>
<script src="{{ asset('assets/js/admin/contabilidad.js') }}?v={{ filemtime(public_path('assets/js/admin/contabilidad.js')) }}"></script>
@endpush
