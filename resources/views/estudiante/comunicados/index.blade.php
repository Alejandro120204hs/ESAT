{{-- Estudiante › Comunicados (Estudiante/Comunicados/ComunicadoController): los que le llegan con los destinatarios de
     siempre (toda la sede, los estudiantes o su grupo; con matrícula vigente). Abrir uno lo marca leído en
     comunicado_lecturas: la Secretaría ve lecturas reales. Solo lectura. Enlace: ?id=ID abre uno. --}}
@extends('estudiante.layout')

@section('title', 'Comunicados')
@section('active', 'comunicados')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/comunicados.css') }}?v={{ filemtime(public_path('assets/css/estudiante/comunicados.css')) }}">
@endpush

@section('content')
<div class="com-wrap app-anim" id="com-app">

    <header class="com-header">
        <h2 class="com-title">Comunicados</h2>
        <p class="com-sub">Los avisos que te envían el administrativo de tu sede, el Admin de ESAT y tu docente.</p>
    </header>

    <div class="com-toolbar app-anim app-anim-d1">
        <div class="com-vistas" role="tablist" aria-label="Comunicados">
            <button type="button" class="com-vista-btn is-active" role="tab" id="com-tab-todos" aria-selected="true" aria-controls="com-panel" data-vista="todos">Todos</button>
            <button type="button" class="com-vista-btn" role="tab" id="com-tab-sinleer" aria-selected="false" aria-controls="com-panel" data-vista="sinleer" tabindex="-1">
                Sin leer <span class="com-vista-num" id="com-num-sinleer"></span>
            </button>
        </div>
    </div>

    <div class="com-layout app-anim app-anim-d2" id="com-layout">
        <div class="com-col-lista" id="com-panel" role="tabpanel" aria-labelledby="com-tab-todos">
            <ul class="com-lista" id="com-lista"></ul>
            <p class="com-lista-vacia" id="com-lista-vacia" hidden>No tienes comunicados sin leer.</p>
        </div>
        <article class="com-lector" id="com-lector" aria-live="polite" tabindex="-1"></article>
    </div>
</div>
@endsection

@push('scripts')
    @include('estudiante.partials.datos')
    <script src="{{ asset('assets/js/estudiante/comunicados.js') }}?v={{ filemtime(public_path('assets/js/estudiante/comunicados.js')) }}"></script>
@endpush
