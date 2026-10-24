{{--
    Docente · Tomar asistencia — PANTALLA SOLO FRONTEND (maqueta, sin backend todavía).
    - Los datos salen de public/assets/js/docente/datos-ejemplo.js (window.DOC_DATOS): programas, escuelas y
      jornadas son reales; grupos, estudiantes, clases y asistencias son FICTICIOS.
    - Guardar va a window.DOC_API.guardarAsistencia (sessionStorage de esta pestaña) hasta que exista el backend
      (mismas reglas de AsistenciaService: solo clases dictadas o la de hoy, grupo vigente, excusa con motivo).
    - Enlace directo: /docente/asistencia?grupo=ID&fecha=AAAA-MM-DD (ambos opcionales; lo usa el Inicio).
--}}
@extends('docente.layout')

@section('title', 'Asistencia')
@section('active', 'asistencia')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/docente/asistencia.css') }}?v={{ filemtime(public_path('assets/css/docente/asistencia.css')) }}">
@endpush

@section('content')
<div class="tas-wrap app-anim">

    {{-- Encabezado --}}
    <div class="tas-header">
        <div>
            <h2 class="tas-title">Asistencia</h2>
            <p class="tas-sub">Marca quién vino a cada clase y corrige lo que haga falta mientras el grupo esté vigente. El 80 % de asistencia es solo una referencia: no bloquea nada.</p>
        </div>
    </div>

    {{-- Grupos del docente --}}
    <section class="tas-grupos app-anim app-anim-d1" aria-labelledby="tas-grupos-ttl">
        <h3 class="tas-sec-lbl" id="tas-grupos-ttl">Tus grupos</h3>
        <div class="tas-grupos-lista" id="tas-vigentes"></div>
        <div class="tas-grupos-fin" id="tas-fin-wrap" hidden>
            <h3 class="tas-sec-lbl">Finalizados · solo consulta</h3>
            <div class="tas-grupos-lista" id="tas-finalizados"></div>
        </div>
    </section>

    {{-- Sin grupos --}}
    <div class="tas-vacio tas-vacio-grande" id="tas-sin-grupos" hidden>
        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        <p>Todavía no tienes grupos asignados. Cuando la sede te asigne uno, aquí podrás tomar su asistencia.</p>
    </div>

    {{-- Resumen del grupo elegido --}}
    <div class="tas-resumen app-anim app-anim-d2" id="tas-resumen" hidden></div>

    <div class="tas-main app-anim app-anim-d3" id="tas-main" hidden>

        {{-- ═══════════ Clases del grupo ═══════════ --}}
        <section class="tas-panel tas-clases" aria-labelledby="tas-clases-ttl">
            <div class="tas-panel-head">
                <h3 class="tas-panel-ttl" id="tas-clases-ttl">Clases</h3>
                <label class="tas-switch">
                    <input type="checkbox" id="tas-solo-sin" role="switch">
                    <span class="tas-switch-track" aria-hidden="true"></span>
                    <span class="tas-switch-txt">Solo sin registrar</span>
                    <span class="tas-switch-num" id="tas-solo-num"></span>
                </label>
            </div>

            <div class="tas-pend" id="tas-pend" hidden></div>

            <div class="tas-cal" id="tas-cal"></div>
            <div class="tas-lista-sin" id="tas-lista-sin" hidden></div>

            <div class="tas-leyenda" id="tas-leyenda">
                <span><i class="tas-ley tas-ley-reg"></i>Registrada</span>
                <span><i class="tas-ley tas-ley-sin"></i>Sin registrar</span>
                <span><i class="tas-ley tas-ley-hoy"></i>Hoy</span>
                <span><i class="tas-ley tas-ley-fut"></i>Futura</span>
            </div>
        </section>

        {{-- ═══════════ Planilla de la clase ═══════════ --}}
        <section class="tas-panel tas-planilla" id="tas-planilla" aria-labelledby="tas-pl-titulo">
            <div class="tas-pl-head">
                <div class="tas-pl-head-txt">
                    <h3 class="tas-pl-titulo" id="tas-pl-titulo" tabindex="-1"></h3>
                    <div class="tas-pl-sub" id="tas-pl-sub"></div>
                </div>
                <div class="tas-pl-estado" id="tas-pl-estado"></div>
            </div>

            <div class="tas-nota" id="tas-pl-nota" hidden></div>

            <div class="tas-pl-cuerpo" id="tas-pl-cuerpo">
                <div class="tas-conteo" id="tas-conteo"></div>

                <div class="tas-tools" id="tas-tools">
                    <button type="button" class="tas-btn tas-btn-ghost tas-btn-sm" id="tas-todos">
                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Todos presentes
                    </button>
                    <span class="tas-tools-hint" id="tas-todos-hint"></span>
                </div>

                <div class="tas-confirmar" id="tas-confirmar" hidden>
                    <p id="tas-confirmar-txt"></p>
                    <div class="tas-confirmar-acc">
                        <button type="button" class="tas-btn tas-btn-ghost tas-btn-sm" id="tas-conf-no">No, dejar como está</button>
                        <button type="button" class="tas-btn tas-btn-primary tas-btn-sm" id="tas-conf-si">Sí, a todos</button>
                    </div>
                </div>

                <ul class="tas-filas" id="tas-filas" aria-label="Estudiantes de la clase"></ul>
            </div>

            <div class="tas-vacio" id="tas-pl-vacio" hidden></div>

            {{-- Barra de guardado (fija abajo al bajar por la lista) --}}
            <div class="tas-barra" id="tas-barra">
                <div class="tas-error" id="tas-error" role="alert" hidden></div>
                <div class="tas-barra-fila">
                    <div class="tas-barra-info">
                        <span class="tas-cambios" id="tas-cambios"></span>
                        <span class="tas-faltan" id="tas-faltan"></span>
                    </div>
                    <div class="tas-barra-acc">
                        <button type="button" class="tas-btn tas-btn-ghost" id="tas-descartar" hidden>Descartar</button>
                        <button type="button" class="tas-btn tas-btn-primary" id="tas-guardar">
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span id="tas-guardar-txt">Guardar asistencia</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>{{-- /tas-wrap --}}

{{-- Cambios sin guardar: Guardar / Descartar / Cancelar --}}
<div class="tas-dialogo" id="tas-dialogo" hidden>
    <div class="tas-dialogo-overlay" data-dlg="cancelar"></div>
    <div class="tas-dialogo-box" role="dialog" aria-modal="true" aria-labelledby="tas-dlg-titulo" aria-describedby="tas-dlg-txt">
        <h3 class="tas-dialogo-ttl" id="tas-dlg-titulo">Tienes cambios sin guardar</h3>
        <p class="tas-dialogo-txt" id="tas-dlg-txt"></p>
        <div class="tas-dialogo-acc">
            <button type="button" class="tas-btn tas-btn-ghost" data-dlg="cancelar">Cancelar</button>
            <button type="button" class="tas-btn tas-btn-danger" data-dlg="descartar">Descartar</button>
            <button type="button" class="tas-btn tas-btn-primary" data-dlg="guardar">Guardar</button>
        </div>
    </div>
</div>

<div class="tas-toast" id="tas-toast" role="status" aria-live="polite" hidden></div>
<div class="tas-sr" id="tas-anuncio" aria-live="polite"></div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/docente/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/docente/datos-ejemplo.js')) }}"></script>
<script src="{{ asset('assets/js/docente/asistencia.js') }}?v={{ filemtime(public_path('assets/js/docente/asistencia.js')) }}"></script>
@endpush
