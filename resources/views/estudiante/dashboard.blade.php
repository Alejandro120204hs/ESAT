{{-- Inicio del estudiante (Estudiante/Inicio/InicioController): datos reales de PanelEstudianteService::datos. No
     guarda nada: cada pendiente lleva a la pantalla que lo resuelve. Sin matrícula vigente (o sin grupo) muestra solo el
     saludo y un aviso. Copiado del Inicio del Docente (DESIGN-PANELES.md): saludo con resumen, tarjetas sin cifras
     repetidas y el mismo horario semanal. --}}
@extends('estudiante.layout')

@section('title', 'Inicio')
@section('active', 'inicio')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/dashboard.css') }}?v={{ filemtime(public_path('assets/css/estudiante/dashboard.css')) }}">
@endpush

@section('content')
<div class="ini-wrap app-anim" id="ini-app"
     data-url-programa="{{ route('estudiante.programa') }}"
     data-url-aula="{{ route('estudiante.aula') }}"
     data-url-pagos="{{ route('estudiante.pagos') }}"
     data-url-comunicados="{{ route('estudiante.comunicados') }}">

    {{-- Encabezado --}}
    <header class="ini-header">
        <h2 class="ini-title">Hola, {{ auth()->user()->nombres }}</h2>
        <p class="ini-sub" id="ini-resumen" aria-live="polite"></p>
    </header>

    <div class="ini-cols">
        <div class="ini-col">

            {{-- Clase de hoy --}}
            <section class="ini-card ini-hoy" aria-labelledby="ini-hoy-titulo">
                <div class="ini-card-head">
                    <div>
                        <h3 class="ini-card-title" id="ini-hoy-titulo">Clase de hoy</h3>
                        <p class="ini-card-sub" id="ini-hoy-sub"></p>
                    </div>
                </div>
                <div id="ini-hoy-body"></div>
            </section>

            {{-- Tu avance --}}
            <section class="ini-card ini-avance" aria-labelledby="ini-avance-titulo">
                <div class="ini-card-head">
                    <div>
                        <h3 class="ini-card-title" id="ini-avance-titulo">Tu avance</h3>
                        <p class="ini-card-sub" id="ini-avance-sub"></p>
                    </div>
                </div>
                <div class="ini-avance-body" id="ini-avance-body"></div>
            </section>
        </div>

        <div class="ini-col">
            {{-- Pendientes --}}
            <section class="ini-card ini-pend" aria-labelledby="ini-pend-titulo">
                <div class="ini-card-head">
                    <div>
                        <h3 class="ini-card-title" id="ini-pend-titulo">Pendientes</h3>
                        <p class="ini-card-sub">Lo que tienes por responder, entregar, pagar o leer.</p>
                    </div>
                </div>
                <div id="ini-pend-body"></div>
                <div class="ini-empty" id="ini-pend-empty" hidden>
                    <div class="ini-empty-icono" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <p class="ini-empty-titulo">Estás al día</p>
                    <p class="ini-empty-txt">No tienes evaluaciones ni actividades por hacer, pagos vencidos ni comunicados sin leer.</p>
                </div>
            </section>
        </div>
    </div>

    {{-- Mi horario --}}
    <section class="ini-card ini-horario" aria-labelledby="ini-horario-titulo">
        <div class="ini-card-head">
            <div>
                <h3 class="ini-card-title" id="ini-horario-titulo">Mi horario</h3>
                <p class="ini-card-sub" id="ini-horario-sub"></p>
            </div>
        </div>
        <div class="ini-sem" id="ini-sem"></div>
        <div class="ini-sem-lista" id="ini-sem-lista"></div>
    </section>
</div>
@endsection

@push('scripts')
    @include('estudiante.partials.datos')
    <script src="{{ asset('assets/js/estudiante/dashboard.js') }}?v={{ filemtime(public_path('assets/js/estudiante/dashboard.js')) }}"></script>
@endpush
