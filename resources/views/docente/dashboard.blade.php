{{-- Inicio del docente (Docente/Inicio/InicioController). Datos reales de los grupos que tiene a cargo
     (PanelDocenteService::datos → window.DOC_SERVIDOR; datos.js arma DOC_DATOS). Esta pantalla no guarda nada:
     sus enlaces llevan a Asistencia, Notas y Mis grupos. Solo cuentan los grupos sin finalizar; los
     finalizados son historial. --}}
@extends('docente.layout')

@section('title', 'Inicio')
@section('active', 'inicio')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/docente/dashboard.css') }}?v={{ filemtime(public_path('assets/css/docente/dashboard.css')) }}">
@endpush

@section('content')
<div class="ini-wrap app-anim" id="ini-app"
     data-url-asistencia="{{ route('docente.asistencia') }}"
     data-url-notas="{{ route('docente.notas') }}"
     data-url-grupos="{{ route('docente.grupos') }}">

    {{-- Encabezado --}}
    <header class="ini-header">
        <h2 class="ini-title">Hola, {{ auth()->user()->nombres }}</h2>
        <p class="ini-sub" id="ini-resumen" aria-live="polite"></p>
    </header>

    <div class="ini-cols">
        <div class="ini-col">

            {{-- Clases de hoy --}}
            <section class="ini-card ini-hoy" aria-labelledby="ini-hoy-titulo">
                <div class="ini-card-head">
                    <div>
                        <h3 class="ini-card-title" id="ini-hoy-titulo">Clases de hoy</h3>
                        <p class="ini-card-sub" id="ini-hoy-sub"></p>
                    </div>
                </div>
                <ul class="ini-clases" id="ini-clases"></ul>
                <div class="ini-empty" id="ini-hoy-empty" hidden>
                    <div class="ini-empty-icono ini-empty-calma" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </div>
                    <p class="ini-empty-titulo">Hoy no tienes clases</p>
                    <p class="ini-empty-txt" id="ini-hoy-empty-txt"></p>
                </div>
                <div class="ini-proxima" id="ini-proxima" hidden></div>
            </section>

            {{-- Estudiantes para acompañar --}}
            <section class="ini-card ini-acomp" aria-labelledby="ini-acomp-titulo">
                <div class="ini-card-head">
                    <div>
                        <h3 class="ini-card-title" id="ini-acomp-titulo">Estudiantes para acompañar</h3>
                        <p class="ini-card-sub">Van por debajo del 80 % de asistencia. Es una referencia: no bloquea matrículas ni grados.</p>
                    </div>
                </div>
                <ul class="ini-acomp-lista" id="ini-acomp-lista"></ul>
                <p class="ini-linea-ok" id="ini-acomp-empty" hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    Todos tus estudiantes van sobre el 80 %.
                </p>
            </section>
        </div>

        <div class="ini-col">
            {{-- Pendientes --}}
            <section class="ini-card ini-pend" aria-labelledby="ini-pend-titulo">
                <div class="ini-card-head">
                    <div>
                        <h3 class="ini-card-title" id="ini-pend-titulo">Pendientes</h3>
                        <p class="ini-card-sub">Lo que falta registrar en tus grupos.</p>
                    </div>
                </div>
                <div id="ini-pend-body"></div>
                <div class="ini-empty" id="ini-pend-empty" hidden>
                    <div class="ini-empty-icono" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <p class="ini-empty-titulo">Estás al día</p>
                    <p class="ini-empty-txt">No tienes asistencias por registrar, notas por poner ni habilitaciones por calificar.</p>
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
        <div class="ini-empty" id="ini-sem-empty" hidden>
            <div class="ini-empty-icono ini-empty-calma" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <p class="ini-empty-titulo">No tienes grupos en curso</p>
            <p class="ini-empty-txt">Cuando la sede te asigne un grupo, su horario aparecerá aquí.</p>
        </div>
    </section>
</div>
@endsection

@push('scripts')
    @include('docente.partials.datos')
    <script src="{{ asset('assets/js/docente/dashboard.js') }}?v={{ filemtime(public_path('assets/js/docente/dashboard.js')) }}"></script>
@endpush
