{{-- Docente › Aula virtual (Docente/Aula/AulaController + AulaService). Datos reales de sus grupos: crea evaluaciones de
     selección múltiple y actividades por grupo y módulo, ve quién respondió o entregó y califica las entregas. Reglas
     (24-11-2026): una evaluación se edita o elimina mientras nadie la responda; una actividad se elimina sin entregas;
     la nota de una entrega es de 1,0 a 5,0 con un decimal; ninguna calcula la nota del módulo (esa se pone en Notas). Un
     grupo finalizado es solo consulta. Enlace: ?grupo=ID. Tarjetas, chips y cajón de Mis grupos (DESIGN-PANELES.md). --}}
@extends('docente.layout')

@section('title', 'Aula virtual')
@section('active', 'aula')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/docente/aula.css') }}?v={{ filemtime(public_path('assets/css/docente/aula.css')) }}">
@endpush

@section('content')
<div class="dau-wrap app-anim" id="dau-app">
    <header class="dau-header">
        <div class="dau-header-txt">
            <h2 class="dau-title">Aula virtual</h2>
            <p class="dau-sub">Evaluaciones y actividades para tus grupos. Sus notas son aparte de la nota del módulo, que pones en Notas.</p>
        </div>
        <div class="dau-acciones" id="dau-acciones">
            <button type="button" class="dau-btn dau-btn-ghost" id="dau-nueva-act">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                Nueva actividad
            </button>
            <button type="button" class="dau-btn dau-btn-ghost" id="dau-nueva-ev">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Nueva evaluación
            </button>
        </div>
    </header>

    <div class="dau-toolbar app-anim app-anim-d1">
        <fieldset class="dau-chips" id="dau-grupos"><legend class="sr-solo">Grupo</legend></fieldset>
        <p class="dau-resumen" id="dau-resumen" aria-live="polite"></p>
    </div>

    <p class="dau-solo" id="dau-solo" hidden>
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <span>Este grupo ya finalizó: su Aula virtual es solo consulta.</span>
    </p>

    <section class="app-anim app-anim-d2" aria-label="Evaluaciones y actividades">
        <div class="dau-grid" id="dau-grid"></div>
        <div class="dau-vacio" id="dau-vacio" hidden>
            <p class="dau-vacio-ttl" id="dau-vacio-ttl"></p>
            <p class="dau-vacio-sub" id="dau-vacio-sub"></p>
        </div>
    </section>
</div>

{{-- ═══════════ CAJÓN: resultados, entregas o formulario (lo arma aula.js) ═══════════ --}}
<div class="dau-drawer" id="dau-drawer" aria-hidden="true">
    <div class="dau-drawer-overlay" data-cerrar></div>
    <div class="dau-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="dau-dw-titulo">
        <div class="dau-drawer-head">
            <div class="dau-drawer-head-info">
                <h3 class="dau-drawer-title" id="dau-dw-titulo"></h3>
                <div class="dau-drawer-sub" id="dau-dw-sub"></div>
            </div>
            <button type="button" class="dau-icon-btn" id="dau-dw-cerrar" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="dau-drawer-body" id="dau-dw-body" tabindex="0" role="region" aria-label="Contenido"></div>
        <div class="dau-drawer-foot" id="dau-dw-foot" hidden></div>
    </div>
</div>

{{-- Confirmación (eliminar) --}}
<div class="dau-dialogo" id="dau-dialogo" hidden>
    <div class="dau-dialogo-fondo" data-no></div>
    <div class="dau-dialogo-caja" role="alertdialog" aria-modal="true" aria-labelledby="dau-dlg-titulo" aria-describedby="dau-dlg-txt">
        <h3 class="dau-dialogo-titulo" id="dau-dlg-titulo"></h3>
        <p class="dau-dialogo-txt" id="dau-dlg-txt"></p>
        <div class="dau-dialogo-acciones">
            <button type="button" class="dau-btn dau-btn-ghost" data-no>Volver</button>
            <button type="button" class="dau-btn dau-btn-peligro" id="dau-dlg-si"></button>
        </div>
    </div>
</div>

<div class="dau-toast" id="dau-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    @php
        $dauServidor = ['datos' => $datos, 'rutas' => [
            'crearEvaluacion' => route('docente.aula.evaluaciones.store', ['curso' => '__ID__']),
            'evaluacion'      => route('docente.aula.evaluaciones.update', ['evaluacion' => '__ID__']),
            'crearActividad'  => route('docente.aula.actividades.store', ['curso' => '__ID__']),
            'actividad'       => route('docente.aula.actividades.update', ['actividad' => '__ID__']),
            'calificar'       => route('docente.aula.calificar', ['entrega' => '__ID__']),
        ]];
    @endphp
    <script>
        window.DAU_SERVIDOR = @json($dauServidor);
    </script>
    <script src="{{ asset('assets/js/docente/aula.js') }}?v={{ filemtime(public_path('assets/js/docente/aula.js')) }}" defer></script>
@endpush
