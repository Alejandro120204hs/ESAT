{{-- Inicio del docente — PANTALLA SOLO FRONTEND (todavía sin controlador ni persistencia real).
     Todo lo que se ve sale de los datos de ejemplo de public/assets/js/docente/datos-ejemplo.js
     (window.DOC_DATOS): programas, escuelas y jornadas son reales; grupos, horarios, estudiantes,
     asistencias y notas son FICTICIOS. Esta pantalla no guarda nada: sus enlaces llevan a Asistencia,
     Notas y Mis grupos, que guardan con DOC_API (sessionStorage) mientras no exista el backend.
     Solo cuentan los grupos sin finalizar; los finalizados son historial. --}}
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
        <p class="ini-fecha">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span id="ini-fecha"></span>
        </p>
        <h2 class="ini-title">Hola, {{ auth()->user()->nombres }}</h2>
        <p class="ini-sub" id="ini-resumen" aria-live="polite"></p>
    </header>

    {{-- KPIs --}}
    <div class="ini-kpis">
        <div class="ini-kpi-chip ini-kpi-green" id="ini-kpi-hoy">
            <div class="ini-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <div class="ini-kpi-val" data-kpi-val>0</div>
                <div class="ini-kpi-lbl" data-kpi-lbl>Clases hoy</div>
            </div>
        </div>
        <div class="ini-kpi-chip ini-kpi-orange" id="ini-kpi-asis">
            <div class="ini-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="ini-kpi-val" data-kpi-val>0</div>
                <div class="ini-kpi-lbl" data-kpi-lbl>Asistencias sin registrar</div>
            </div>
        </div>
        <div class="ini-kpi-chip ini-kpi-blue" id="ini-kpi-notas">
            <div class="ini-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <div>
                <div class="ini-kpi-val" data-kpi-val>0</div>
                <div class="ini-kpi-lbl" data-kpi-lbl>Notas por poner</div>
            </div>
        </div>
        <div class="ini-kpi-chip ini-kpi-red" id="ini-kpi-hab">
            <div class="ini-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="ini-kpi-val" data-kpi-val>0</div>
                <div class="ini-kpi-lbl" data-kpi-lbl>Por habilitar</div>
            </div>
        </div>
    </div>

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
                        <p class="ini-card-sub">Van por debajo del 80 % de asistencia en las clases que llevan.</p>
                    </div>
                    <span class="ini-card-num" id="ini-acomp-num"></span>
                </div>
                <ul class="ini-acomp-lista" id="ini-acomp-lista"></ul>
                <div class="ini-empty" id="ini-acomp-empty" hidden>
                    <div class="ini-empty-icono" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <p class="ini-empty-titulo">Todos van bien con la asistencia</p>
                    <p class="ini-empty-txt">Ninguno de tus estudiantes está por debajo del 80 %.</p>
                </div>
                <p class="ini-nota">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    El 80 % es solo una referencia: la asistencia es informativa y no bloquea matrículas ni grados.
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
                    <span class="ini-card-num" id="ini-pend-num"></span>
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
            <ul class="ini-leyenda" id="ini-leyenda" aria-label="Jornadas"></ul>
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
    <script src="{{ asset('assets/js/docente/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/docente/datos-ejemplo.js')) }}"></script>
    <script src="{{ asset('assets/js/docente/dashboard.js') }}?v={{ filemtime(public_path('assets/js/docente/dashboard.js')) }}"></script>
@endpush
