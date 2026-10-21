{{-- Pagos (Finanzas): datos reales desde Admin\Pagos\CarteraController (CarteraService). Pestañas: Por cobrar ·
     Pagos recibidos · Resumen de caja (por rango de fechas) · Por programa · Devoluciones. Registrar y anular un pago
     usan las rutas de Matrículas (PagoRequest / AnularPagoRequest + PagoService): una sola regla de cobro y el
     historial queda en la matrícula. --}}
@extends('admin.layout')

@section('title', 'Pagos')
@section('page-title', 'Pagos')
@section('active', 'pagos')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/pagos.css') }}?v={{ filemtime(public_path('assets/css/admin/pagos.css')) }}">
@endpush

@php $sede = auth()->user()->sede; @endphp

@section('content')
<div class="pag-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="pag-header">
        <div>
            <h2 class="pag-title">Pagos</h2>
            <p class="pag-sub">Revisa quién debe y quién ya pagó, registra los pagos que recibes con su medio de pago y consulta cuánto ha entrado a la caja en las fechas que necesites.</p>
        </div>
        <button type="button" class="pag-btn pag-btn-primary" id="pag-btn-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Registrar pago
        </button>
    </div>

    {{-- KPIs (los llena pagos.js para que cambien al registrar un pago) --}}
    <div class="pag-kpis">
        <div class="pag-kpi-chip pag-kpi-green">
            <div class="pag-kpi-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
            <div class="pag-min0">
                <div class="pag-kpi-val" id="pag-k-recibido"></div>
                <div class="pag-kpi-lbl" id="pag-k-recibido-lbl"></div>
            </div>
        </div>
        <div class="pag-kpi-chip pag-kpi-blue">
            <div class="pag-kpi-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div class="pag-min0">
                <div class="pag-kpi-val" id="pag-k-mes"></div>
                <div class="pag-kpi-lbl" id="pag-k-mes-lbl"></div>
            </div>
        </div>
        <div class="pag-kpi-chip pag-kpi-red">
            <div class="pag-kpi-icon"><svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
            <div class="pag-min0">
                <div class="pag-kpi-val" id="pag-k-vencido"></div>
                <div class="pag-kpi-lbl" id="pag-k-vencido-lbl"></div>
            </div>
        </div>
        <div class="pag-kpi-chip pag-kpi-orange">
            <div class="pag-kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="pag-min0">
                <div class="pag-kpi-val" id="pag-k-sinplan"></div>
                <div class="pag-kpi-lbl">Sin plan de pago · no se les está cobrando</div>
            </div>
        </div>
    </div>

    {{-- Vistas --}}
    <div class="pag-vistas" role="tablist" aria-label="Vista">
        <button type="button" class="pag-vista-btn is-active" data-vista="cobrar" role="tab" aria-selected="true">Por cobrar <span class="pag-vista-num" id="pag-n-cobrar"></span></button>
        <button type="button" class="pag-vista-btn" data-vista="recibidos" role="tab" aria-selected="false">Pagos recibidos <span class="pag-vista-num" id="pag-n-recibidos"></span></button>
        <button type="button" class="pag-vista-btn" data-vista="caja" role="tab" aria-selected="false">Resumen de caja</button>
        <button type="button" class="pag-vista-btn" data-vista="programas" role="tab" aria-selected="false">Por programa</button>
        <button type="button" class="pag-vista-btn" data-vista="devoluciones" role="tab" aria-selected="false">Devoluciones <span class="pag-vista-num" id="pag-n-devoluciones"></span></button>
    </div>

    {{-- ═══════════ Por cobrar ═══════════ --}}
    <section class="pag-vista" data-panel="cobrar">
        <div class="pag-filters">
            <div class="pag-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="pag-c-search" placeholder="Buscar por nombre, documento o código..." autocomplete="off" aria-label="Buscar estudiante">
            </div>
            @foreach(['programa' => 'Todos los programas', 'situacion' => 'Todas las situaciones', 'orden' => 'Más vencido primero'] as $k => $lbl)
            <div class="app-combobox" id="pag-c-cb-{{ $k }}" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>{{ $lbl }}</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" id="pag-c-{{ $k }}" data-combobox-value>
            </div>
            @endforeach
            <span class="pag-count-badge" id="pag-c-count"></span>
            <button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" data-exportar="cobrar" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="pag-table-wrap">
            <table class="pag-table">
                <thead><tr><th>Estudiante</th><th>Programa y grupo</th><th class="num">Vencido</th><th class="num">Este mes</th><th class="num">Por pagar</th><th>Situación</th><th></th></tr></thead>
                <tbody id="pag-c-body"></tbody>
            </table>
            <div class="pag-empty" id="pag-c-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>No hay estudiantes que coincidan con la búsqueda.</p>
            </div>
        </div>
        <div class="pag-pagination" id="pag-c-pagination" hidden></div>
    </section>

    {{-- ═══════════ Pagos recibidos ═══════════ --}}
    <section class="pag-vista" data-panel="recibidos" hidden>
        <div class="pag-filters">
            <div class="pag-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="pag-r-search" placeholder="Buscar por recibo, estudiante o referencia..." autocomplete="off" aria-label="Buscar pago">
            </div>
            @foreach(['periodo' => 'Este mes', 'metodo' => 'Todos los medios', 'estado' => 'Todos los pagos'] as $k => $lbl)
            <div class="app-combobox" id="pag-r-cb-{{ $k }}" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>{{ $lbl }}</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" id="pag-r-{{ $k }}" data-combobox-value>
            </div>
            @endforeach
            <span class="pag-count-badge" id="pag-r-count"></span>
            <button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" data-exportar="recibidos" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="pag-table-wrap">
            <table class="pag-table">
                <thead><tr><th>Recibo</th><th>Estudiante</th><th>Qué pagó</th><th>Medio de pago</th><th class="num">Valor</th><th></th></tr></thead>
                <tbody id="pag-r-body"></tbody>
            </table>
            <div class="pag-empty" id="pag-r-empty" hidden>
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                <p>No hay pagos en esas fechas o con esos filtros.</p>
            </div>
        </div>
        <div class="pag-pagination" id="pag-r-pagination" hidden></div>
    </section>

    {{-- ═══════════ Resumen de caja ═══════════ --}}
    <section class="pag-vista" data-panel="caja" hidden>
        <div class="pag-filters">
            <div class="pag-rangos" role="radiogroup" aria-label="Fechas del resumen">
                <button type="button" class="pag-rango" data-rango="mes">Este mes</button>
                <button type="button" class="pag-rango" data-rango="anterior">Mes anterior</button>
                <button type="button" class="pag-rango" data-rango="semestre">Este semestre</button>
                <button type="button" class="pag-rango" data-rango="otro">Escoger fechas</button>
            </div>
            <div class="pag-fechas" id="pag-caja-fechas" hidden>
                <label class="pag-fecha"><span>Desde</span><input type="date" id="pag-caja-desde"></label>
                <label class="pag-fecha"><span>Hasta</span><input type="date" id="pag-caja-hasta"></label>
            </div>
            <span class="pag-filters-sep"></span>
            <button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" id="pag-caja-imprimir">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir resumen
            </button>
        </div>
        <div id="pag-caja"></div>
    </section>

    {{-- ═══════════ Por programa ═══════════ --}}
    <section class="pag-vista" data-panel="programas" hidden>
        <p class="pag-ayuda">Lo que ha pagado y lo que debe cada programa, con los estudiantes matriculados hoy (activos y aplazados). Haz clic en un programa para ver quién debe.</p>
        <div class="pag-table-wrap">
            <table class="pag-table">
                <thead><tr><th>Programa</th><th class="num">Estudiantes</th><th class="num">Pagado</th><th class="num">Por cobrar</th><th class="num">Vencido</th><th>Recaudado</th><th></th></tr></thead>
                <tbody id="pag-p-body"></tbody>
                <tfoot id="pag-p-foot"></tfoot>
            </table>
        </div>
    </section>

    {{-- ═══════════ Devoluciones ═══════════ --}}
    <section class="pag-vista" data-panel="devoluciones" hidden>
        <p class="pag-ayuda">Dinero pagado por adelantado que se le devolvió a un estudiante que se retiró o aplazó. Las devoluciones se registran desde Matrículas, en la ficha de la matrícula.</p>
        <div class="pag-table-wrap">
            <table class="pag-table">
                <thead><tr><th>Comprobante</th><th>Estudiante</th><th>Motivo</th><th>Medio</th><th class="num">Valor</th><th></th></tr></thead>
                <tbody id="pag-d-body"></tbody>
            </table>
            <div class="pag-empty" id="pag-d-empty" hidden>
                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                <p>Todavía no se ha hecho ninguna devolución.</p>
            </div>
        </div>
    </section>

</div>{{-- /pag-wrap --}}

{{-- ═══════════ Cajón: estudiante / recibo / devolución (lo arma pagos.js) ═══════════ --}}
<div class="pag-drawer" id="pag-drawer" aria-hidden="true">
    <div class="pag-drawer-overlay" data-cerrar></div>
    <div class="pag-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="pag-dw-titulo">
        <div class="pag-drawer-head">
            <div class="pag-drawer-head-left">
                <button type="button" class="pag-close-btn" id="pag-dw-volver" aria-label="Volver" hidden>
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="pag-drawer-avatar" id="pag-dw-avatar"></div>
                <div class="pag-drawer-head-info">
                    <h3 class="pag-drawer-title" id="pag-dw-titulo"></h3>
                    <div class="pag-drawer-sub" id="pag-dw-sub"></div>
                    <div class="pag-drawer-badges" id="pag-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="pag-close-btn" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="pag-drawer-body" id="pag-dw-body"></div>
    </div>
</div>

{{-- ═══════════ Modal: registrar pago (estudiante → pago) / anular pago ═══════════ --}}
<div class="pag-modal" id="pag-modal" aria-hidden="true">
    <div class="pag-modal-overlay" data-modal-cerrar></div>
    <div class="pag-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pag-md-titulo">
        <div class="pag-modal-head">
            <div>
                <h3 class="pag-modal-title" id="pag-md-titulo">Registrar pago</h3>
                <div class="pag-modal-step-lbl" id="pag-md-paso"></div>
            </div>
            <button type="button" class="pag-close-btn" data-modal-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="pag-wsteps" id="pag-md-steps">
            <div class="pag-wstep" data-step="1"><div class="pag-wstep-dot"></div><span>Estudiante</span></div>
            <div class="pag-wstep-line"></div>
            <div class="pag-wstep" data-step="2"><div class="pag-wstep-dot"></div><span>Pago</span></div>
        </div>
        <div class="pag-modal-body" id="pag-md-body"></div>
        <div class="pag-form-error" id="pag-md-error" role="alert" hidden></div>
        <div class="pag-modal-footer">
            <button type="button" class="pag-btn pag-btn-ghost" id="pag-md-atras">Atrás</button>
            <button type="button" class="pag-btn pag-btn-primary" id="pag-md-ok">Siguiente</button>
        </div>
    </div>
</div>

{{-- ═══════════ Hoja imprimible del resumen de caja (datos legales reales de ESAT) ═══════════ --}}
<div class="pag-hoja-modal" id="pag-hoja-modal" aria-hidden="true">
    <div class="pag-hoja-overlay" data-hoja-cerrar></div>
    <div class="pag-hoja-dialog" role="dialog" aria-modal="true" aria-label="Resumen de caja">
        <div class="pag-hoja-acciones">
            <button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" data-hoja-cerrar>Cerrar</button>
            <button type="button" class="pag-btn pag-btn-primary pag-btn-sm" id="pag-hoja-imprimir">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir o guardar en PDF
            </button>
        </div>
        <article class="pag-hoja">
            <header class="pag-hoja-cab">
                <div class="pag-hoja-marca">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT">
                    <div>
                        <strong>Escuela Nacional de Educación ESAT</strong>
                        <span>NIT 900820764-1 · Lic. Funcionamiento 5850</span>
                        @if($sede)
                            <span>Sede {{ $sede->nombre }}@if($sede->ciudad) · {{ $sede->ciudad }}@endif</span>
                        @endif
                    </div>
                </div>
                <div class="pag-hoja-num">
                    <span>Resumen de caja</span>
                    <strong id="pag-hoja-fecha"></strong>
                </div>
            </header>
            <div id="pag-hoja-cuerpo"></div>
        </article>
    </div>
</div>

<div class="pag-toast" id="pag-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var PAG_DATA = {
    matriculas: @json($matriculas),
    recaudos: @json($recaudos),
    devoluciones: @json($devoluciones),
    metodos: @json($metodos),
    hoy: @json(today()->toDateString()),
    admin: @json(auth()->user()->nombres),
    ciudad: @json($sede?->ciudad),
    reciboUrl: @json(url('admin/matriculas/recibos')),
    matriculasUrl: @json(url('admin/matriculas'))
};
</script>
<script src="{{ asset('assets/js/admin/pagos.js') }}?v={{ filemtime(public_path('assets/js/admin/pagos.js')) }}"></script>
@endpush
