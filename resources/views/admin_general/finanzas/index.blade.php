{{-- Finanzas (Admin de ESAT general): solo consulta. Datos reales: AdminGeneral FinanzasController@index (FinanzasService) los
     pasa en window.GEN_DATOS: recibos, cartera de los estudiantes, egresos sin anular y devoluciones del último año de todas
     las sedes. Cobrar, anular y registrar egresos se hace entrando a la sede. finanzas.js escapa todo. --}}
@extends('admin_general.layout')

@section('title', 'Finanzas')
@section('active', 'finanzas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/finanzas.css') }}?v={{ filemtime(public_path('assets/css/admin_general/finanzas.css')) }}">
@endpush

@section('content')
<div class="fin-wrap app-anim">

    <div class="fin-header">
        <div>
            <h1 class="fin-title">Finanzas</h1>
            <p class="fin-sub">La plata de ESAT: lo que entra, lo que se debe y lo que sale, de todas las sedes o de una. Es solo consulta: los pagos y los gastos los registra cada secretaría.</p>
        </div>
    </div>

    <div class="fin-barra-filtros">
        <div class="fin-chips" id="fin-chips" role="radiogroup" aria-label="Sede">
            <button type="button" class="fin-chip is-active" role="radio" aria-checked="true" data-sede="">Todas las sedes</button>
        </div>
        <div class="app-combobox" id="fin-cb-mes" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="Mes">
                <span data-combobox-label>Este mes</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
    </div>

    <div class="fin-ptabs" role="tablist">
        <button type="button" class="fin-ptab is-active" role="tab" data-ptab="recaudo" aria-selected="true">Recaudo</button>
        <button type="button" class="fin-ptab" role="tab" data-ptab="cartera" aria-selected="false">Cartera</button>
        <button type="button" class="fin-ptab" role="tab" data-ptab="contabilidad" aria-selected="false">Contabilidad</button>
        <button type="button" class="fin-ptab" role="tab" data-ptab="devoluciones" aria-selected="false">Devoluciones</button>
    </div>

    {{-- ─── Recaudo ─── --}}
    <section class="fin-ppanel" data-ppanel="recaudo" role="tabpanel" aria-label="Recaudo">
        <div class="fin-kpis" id="fin-rec-kpis"></div>
        <div class="fin-grid2">
            <div class="fin-card">
                <div class="fin-card-head"><div><h2 class="fin-card-title">Por sede</h2><p class="fin-card-sub" id="fin-rec-sede-sub"></p></div></div>
                <div class="fin-card-body"><ul class="fin-barras" id="fin-rec-sedes"></ul></div>
            </div>
            <div class="fin-card">
                <div class="fin-card-head"><div><h2 class="fin-card-title">Por medio de pago</h2><p class="fin-card-sub">Sin recibos anulados</p></div></div>
                <div class="fin-card-body"><ul class="fin-barras" id="fin-rec-medios"></ul></div>
            </div>
        </div>
        <div class="fin-filters">
            <div class="fin-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="fin-rec-buscar" placeholder="Buscar por recibo, estudiante o referencia..." autocomplete="off" aria-label="Buscar recibo">
            </div>
            <div class="app-combobox" id="fin-cb-medio" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="Medio de pago">
                    <span data-combobox-label>Todos los medios</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" data-combobox-value>
            </div>
            <span class="fin-count" id="fin-rec-count"></span>
            <button type="button" class="fin-btn fin-btn-ghost fin-btn-sm" data-exportar="recaudo" title="Descarga los recibos filtrados para abrirlos en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="fin-table-wrap">
            <table class="fin-table">
                <thead><tr>
                    <th scope="col">Recibo</th><th scope="col">Estudiante</th><th scope="col">Sede</th><th scope="col">Concepto</th>
                    <th scope="col">Medio</th><th scope="col">Registró</th><th scope="col" class="fin-num">Valor</th>
                </tr></thead>
                <tbody id="fin-rec-body"></tbody>
            </table>
            <p class="fin-empty" id="fin-rec-empty" hidden>No hay recibos con esos filtros en el mes elegido.</p>
        </div>
        <nav class="fin-pagination" id="fin-rec-paginacion" aria-label="Páginas de recibos" hidden></nav>
    </section>

    {{-- ─── Cartera ─── --}}
    <section class="fin-ppanel" data-ppanel="cartera" role="tabpanel" aria-label="Cartera" hidden>
        <p class="fin-nota-corte">La cartera es a hoy: lo vencido y sin pagar en este momento (el mes elegido no aplica aquí).</p>
        <div class="fin-kpis" id="fin-car-kpis"></div>
        <div class="fin-card">
            <div class="fin-card-head"><div><h2 class="fin-card-title">Cartera por edades</h2><p class="fin-card-sub">Cuánto se debe y desde hace cuánto, por sede</p></div></div>
            <div class="fin-table-wrap" style="border:none;border-radius:0">
                <table class="fin-table">
                    <thead><tr><th scope="col">Sede</th><th scope="col" class="fin-num">1 a 30 días</th><th scope="col" class="fin-num">31 a 60 días</th>
                        <th scope="col" class="fin-num">61 a 90 días</th><th scope="col" class="fin-num">Más de 90 días</th><th scope="col" class="fin-num">Total</th></tr></thead>
                    <tbody id="fin-car-edades"></tbody>
                </table>
            </div>
        </div>
        <div class="fin-filters">
            <div class="fin-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="fin-car-buscar" placeholder="Buscar estudiante en mora..." autocomplete="off" aria-label="Buscar estudiante en mora">
            </div>
            <span class="fin-count" id="fin-car-count"></span>
            <button type="button" class="fin-btn fin-btn-ghost fin-btn-sm" data-exportar="cartera">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="fin-table-wrap">
            <table class="fin-table">
                <thead><tr><th scope="col">Estudiante</th><th scope="col">Sede</th><th scope="col">Programa</th><th scope="col" class="fin-num">Cuotas vencidas</th>
                    <th scope="col">La más antigua</th><th scope="col" class="fin-num">Debe</th><th scope="col"><span class="fin-sr">Recordatorio</span></th></tr></thead>
                <tbody id="fin-car-body"></tbody>
            </table>
            <p class="fin-empty" id="fin-car-empty" hidden>No hay estudiantes en mora con esos filtros.</p>
        </div>
        <nav class="fin-pagination" id="fin-car-paginacion" aria-label="Páginas de cartera" hidden></nav>
    </section>

    {{-- ─── Contabilidad ─── --}}
    <section class="fin-ppanel" data-ppanel="contabilidad" role="tabpanel" aria-label="Contabilidad" hidden>
        <div class="fin-kpis" id="fin-con-kpis"></div>
        <div class="fin-grid2">
            <div class="fin-card">
                <div class="fin-card-head"><div><h2 class="fin-card-title">Resultado por sede</h2><p class="fin-card-sub" id="fin-con-sede-sub"></p></div></div>
                <table class="fin-table"><thead><tr><th scope="col">Sede</th><th scope="col" class="fin-num">Ingresos</th><th scope="col" class="fin-num">Egresos</th><th scope="col" class="fin-num">Resultado</th></tr></thead>
                    <tbody id="fin-con-sedes"></tbody></table>
            </div>
            <div class="fin-card">
                <div class="fin-card-head"><div><h2 class="fin-card-title">Egresos por categoría</h2><p class="fin-card-sub">Las devoluciones se registran en Matrículas de cada sede</p></div></div>
                <div class="fin-card-body"><ul class="fin-barras" id="fin-con-cats"></ul></div>
            </div>
        </div>
        <div class="fin-card">
            <div class="fin-card-head"><div><h2 class="fin-card-title">Últimos 6 meses</h2><p class="fin-card-sub" id="fin-con-meses-sub"></p></div></div>
            <div class="fin-table-wrap" style="border:none;border-radius:0">
                <table class="fin-table"><thead><tr><th scope="col">Mes</th><th scope="col" class="fin-num">Ingresos</th><th scope="col" class="fin-num">Egresos</th><th scope="col" class="fin-num">Resultado</th></tr></thead>
                    <tbody id="fin-con-meses"></tbody></table>
            </div>
        </div>
        <div class="fin-card">
            <div class="fin-card-head">
                <div><h2 class="fin-card-title">Egresos del mes</h2><p class="fin-card-sub" id="fin-con-eg-sub"></p></div>
                <button type="button" class="fin-btn fin-btn-ghost fin-btn-sm" data-exportar="egresos">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Exportar
                </button>
            </div>
            <div class="fin-table-wrap" style="border:none;border-radius:0">
                <table class="fin-table"><thead><tr><th scope="col">Comprobante</th><th scope="col">Sede</th><th scope="col">Categoría</th><th scope="col">Beneficiario</th><th scope="col">Medio</th><th scope="col" class="fin-num">Valor</th></tr></thead>
                    <tbody id="fin-con-egresos"></tbody></table>
                <p class="fin-empty" id="fin-con-eg-empty" hidden>No hay egresos en el mes elegido.</p>
            </div>
        </div>
    </section>

    {{-- ─── Devoluciones ─── --}}
    <section class="fin-ppanel" data-ppanel="devoluciones" role="tabpanel" aria-label="Devoluciones" hidden>
        <p class="fin-nota-corte">Todas las devoluciones de los últimos 6 meses. Se registran en Matrículas de cada sede.</p>
        <div class="fin-table-wrap">
            <table class="fin-table"><thead><tr><th scope="col">Comprobante</th><th scope="col">Estudiante</th><th scope="col">Sede</th><th scope="col">Motivo</th><th scope="col">Medio</th><th scope="col">Registró</th><th scope="col" class="fin-num">Valor</th></tr></thead>
                <tbody id="fin-dev-body"></tbody></table>
            <p class="fin-empty" id="fin-dev-empty" hidden>No hay devoluciones en esta sede.</p>
        </div>
    </section>
</div>

{{-- ─── Modal: recibo ─── --}}
<div class="fin-modal" id="fin-recibo-modal" hidden>
    <div class="fin-modal-overlay" data-cerrar-modal></div>
    <div class="fin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="fin-rc-titulo">
        <div class="fin-modal-head">
            <div>
                <h2 class="fin-modal-title" id="fin-rc-titulo"></h2>
                <p class="fin-modal-sub">Escuela Nacional de Educación ESAT · NIT 900820764-1</p>
            </div>
            <button type="button" class="fin-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="fin-modal-body"><div class="fin-recibo" id="fin-rc-cuerpo"></div></div>
        <div class="fin-modal-foot">
            <button type="button" class="fin-btn fin-btn-ghost" data-cerrar-modal>Cerrar</button>
            <button type="button" class="fin-btn fin-btn-primary" id="fin-rc-imprimir">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir
            </button>
        </div>
    </div>
</div>

<div class="fin-toast" id="fin-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/finanzas.js') }}?v={{ filemtime(public_path('assets/js/admin_general/finanzas.js')) }}" defer></script>
@endpush
