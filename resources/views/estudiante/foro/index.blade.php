{{-- Estudiante › Foro de la sede (Estudiante/Foro/ForoController + ForoService, las reglas de la Secretaría): un foro por
     sede, 4 categorías fijas, un adjunto PDF/JPG/PNG ≤ 5 MB por mensaje, temas cerrados sin respuestas, un reporte por
     persona y mensaje; lo que la secretaría oculta no se ve. Mensajes, roles y adjuntos con el mismo formato que
     Secretaría › Foro. Enlace: ?tema=ID abre uno. --}}
@extends('estudiante.layout')

@section('title', 'Foro')
@section('active', 'foro')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/foro.css') }}?v={{ filemtime(public_path('assets/css/estudiante/foro.css')) }}">
@endpush

@section('content')
<div class="for-wrap app-anim" id="for-app">

    <header class="for-header">
        <div class="for-header-txt">
            <h2 class="for-title">Foro</h2>
            <p class="for-sub">El foro de tu sede: preguntas, prácticas, trámites y vida en la sede. Lo leen estudiantes, docentes, acudientes y la secretaría.</p>
        </div>
        <button type="button" class="for-btn for-btn-primary" id="for-nuevo">
            <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Abrir un tema
        </button>
    </header>

    <div class="for-toolbar app-anim app-anim-d1">
        <fieldset class="for-chips" id="for-cats">
            <legend class="sr-solo">Categoría</legend>
        </fieldset>
        <div class="for-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="for-buscar" placeholder="Buscar en los temas..." autocomplete="off" aria-label="Buscar en los temas">
        </div>
    </div>

    <section class="app-anim app-anim-d2" aria-label="Temas">
        <ul class="for-temas" id="for-temas"></ul>
        <div class="for-vacio" id="for-vacio" hidden>
            <p class="for-vacio-ttl">No hay temas que coincidan</p>
            <p class="for-vacio-sub">Prueba con otra palabra o con otra categoría, o abre un tema nuevo.</p>
        </div>
    </section>
</div>

{{-- ═══════════ CAJÓN: conversación del tema ═══════════ --}}
<div class="for-drawer" id="for-drawer" aria-hidden="true">
    <div class="for-drawer-overlay" data-cerrar></div>
    <div class="for-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="for-dw-titulo">
        <div class="for-drawer-head">
            <div class="for-drawer-head-info">
                <h3 class="for-drawer-title" id="for-dw-titulo"></h3>
                <div class="for-drawer-sub" id="for-dw-sub"></div>
            </div>
            <button type="button" class="for-icon-btn" id="for-dw-cerrar" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="for-drawer-body" id="for-dw-body" tabindex="0" role="region" aria-label="Conversación"></div>
        <div class="for-responder" id="for-responder"></div>
    </div>
</div>

{{-- ═══════════ DIÁLOGO: abrir un tema / reportar ═══════════ --}}
<div class="for-dialogo" id="for-dialogo" hidden>
    <div class="for-dialogo-fondo" data-no></div>
    <form class="for-dialogo-caja" role="dialog" aria-modal="true" aria-labelledby="for-dlg-titulo" id="for-dlg-form" novalidate>
        <h3 class="for-dialogo-titulo" id="for-dlg-titulo"></h3>
        <div id="for-dlg-cuerpo"></div>
        <p class="for-error" id="for-dlg-error" role="alert"></p>
        <div class="for-dialogo-acciones">
            <button type="button" class="for-btn for-btn-ghost" data-no>Cancelar</button>
            <button type="submit" class="for-btn for-btn-primary" id="for-dlg-si"></button>
        </div>
    </form>
</div>

<div class="for-toast" id="for-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    @include('estudiante.partials.datos')
    <script src="{{ asset('assets/js/estudiante/foro.js') }}?v={{ filemtime(public_path('assets/js/estudiante/foro.js')) }}"></script>
@endpush
