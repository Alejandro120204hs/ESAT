{{-- Reportes: datos reales desde Admin\Reportes\ReporteController (ReporteService), con sus fechas. Los datos legales del
     encabezado (NIT, licencia) son reales.
     Reportes POR MES (decidido con el usuario): arriba se elige el mes y todos los documentos salen como estaban al
     cierre de ese mes (el mes en curso, hasta hoy). No se guarda nada: cualquier mes se vuelve a ver cuando se quiera.
     Documentos: para la Secretaría de Educación (matriculados, certificados, retiros y aplazamientos), para el aula
     (lista de clase, planilla de notas, asistencia del mes) y para la administración (cartera por edades, estudiantes en
     riesgo, caracterización). Cada uno: vista previa con gráfica, Imprimir / PDF y Excel. No repite nada del Inicio. --}}
@extends('admin.layout')

@section('title', 'Reportes')
@section('page-title', 'Reportes')
@section('active', 'reportes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/reportes.css') }}?v={{ filemtime(public_path('assets/css/admin/reportes.css')) }}">
@endpush

@section('content')
<div class="rep-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="rep-header">
        <div>
            <h2 class="rep-title">Reportes</h2>
            <p class="rep-sub">Los documentos formales que la sede entrega: a la Secretaría de Educación, a los docentes y a la administración. Elige el mes y cada documento sale como estaba al cierre de ese mes; imprímelo o descárgalo en Excel.</p>
        </div>
    </div>

    {{-- Mes de los reportes (vale para todos los documentos) --}}
    <div class="rep-mes-barra">
        <span class="rep-mes-lbl">Mes de los reportes</span>
        <div class="app-combobox" id="rep-cb-mes" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="Mes de los reportes">
                <span data-combobox-label>Elige el mes</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" id="rep-f-mes" data-combobox-value>
        </div>
        <span class="rep-mes-corte" id="rep-mes-corte"></span>
    </div>

    {{-- ═══════════ Generar ═══════════ --}}
    <section class="rep-vista">
        <div id="rep-catalogo"></div>

        <div class="rep-visor" id="rep-visor" hidden>
            <div class="rep-visor-barra">
                <button type="button" class="rep-btn rep-btn-ghost rep-btn-sm" id="rep-volver">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Todos los documentos
                </button>
                <span class="rep-filters-sep"></span>
                <button type="button" class="rep-btn rep-btn-ghost rep-btn-sm" id="rep-excel">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Excel
                </button>
                <button type="button" class="rep-btn rep-btn-ghost rep-btn-sm" id="rep-imprimir">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Imprimir / PDF
                </button>
            </div>

            <div class="rep-filters" id="rep-filtros">
                @foreach(['programa' => 'Todos los programas', 'grupo' => 'Elige el grupo'] as $k => $lbl)
                <div class="app-combobox" id="rep-cb-{{ $k }}" data-position="absolute" hidden>
                    <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ ['programa' => 'Programa', 'grupo' => 'Grupo'][$k] }}">
                        <span data-combobox-label>{{ $lbl }}</span>
                        <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                    <input type="hidden" id="rep-f-{{ $k }}" data-combobox-value>
                </div>
                @endforeach
                <span class="rep-ayuda-filtro" id="rep-ayuda-filtro"></span>
            </div>

            <div class="rep-hoja-marco">
                <article class="rep-hoja" id="rep-hoja"></article>
            </div>
        </div>
    </section>

</div>{{-- /rep-wrap --}}

{{-- Al imprimir solo se ve esta copia de la hoja (hijo directo de <body>) --}}
<div class="rep-impresion" id="rep-impresion" aria-hidden="true"></div>

<div class="rep-toast" id="rep-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var REP_CFG = {
    hoy: @json(today()->toDateString()),
    ahora: @json(now()->format('Y-m-d H:i')),
    admin: @json(trim(auth()->user()->nombres.' '.auth()->user()->apellidos)),
    logo: @json(asset('assets/img/logo.png'))
};
var REP_DATOS = @json($datos);
</script>
<script src="{{ asset('assets/js/admin/reportes.js') }}?v={{ filemtime(public_path('assets/js/admin/reportes.js')) }}"></script>
@endpush
