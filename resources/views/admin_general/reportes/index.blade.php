{{-- Reportes (Admin de ESAT general): solo consulta. Datos reales: AdminGeneral ReporteController@index (FinanzasService) los
     pasa en window.GEN_DATOS. Por mes y sede: matriculados, novedades, recaudo, ingresos y egresos, cartera por edades (a hoy).
     Imprimir y Excel. reportes.js escapa todo. --}}
@extends('admin_general.layout')

@section('title', 'Reportes')
@section('active', 'reportes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/reportes.css') }}?v={{ filemtime(public_path('assets/css/admin_general/reportes.css')) }}">
@endpush

@section('content')
<div class="rep-wrap app-anim">

    <div class="rep-header">
        <div>
            <h1 class="rep-title">Reportes</h1>
            <p class="rep-sub">Los documentos de todo ESAT o de una sede, como estaban al cierre del mes que elijas. Para presentar a la junta, al contador o a la Secretaría de Educación.</p>
        </div>
    </div>

    <div class="rep-barra">
        @foreach ([['rep-cb-mes', 'Mes'], ['rep-cb-sede', 'Todas las sedes']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
    </div>

    <div class="rep-layout">
        <ul class="rep-lista" id="rep-lista" aria-label="Documentos"></ul>
        <div class="rep-visor">
            <div class="rep-acciones">
                <button type="button" class="rep-btn rep-btn-ghost" id="rep-excel">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Excel
                </button>
                <button type="button" class="rep-btn rep-btn-primary" id="rep-imprimir">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Imprimir
                </button>
            </div>
            <article class="rep-hoja" id="rep-hoja" aria-live="polite"></article>
        </div>
    </div>
</div>

<div class="rep-toast" id="rep-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/reportes.js') }}?v={{ filemtime(public_path('assets/js/admin_general/reportes.js')) }}" defer></script>
@endpush
