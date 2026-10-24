{{-- Notas (panel Docente): pantalla SOLO FRONTEND con datos de ejemplo de public/assets/js/docente/datos-ejemplo.js.
     Programas, escuelas y jornadas son reales; grupos, estudiantes, notas y asistencia son FICTICIOS.
     "Guardar notas" llama DOC_API.guardarNotas, que por ahora guarda en el sessionStorage de la pestaña
     mientras no exista el backend (CalificacionService).
     Reglas: nota por módulo de 1,0 a 5,0 con un decimal, se aprueba con 3,0; una sola habilitación por módulo
     perdido y solo cuando el módulo ya terminó. Los grupos finalizados y las matrículas que no están activas
     solo se consultan. Enlace directo: ?grupo=ID&modulo=ID (los dos opcionales). --}}
@extends('docente.layout')

@section('title', 'Notas')
@section('active', 'notas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/docente/notas.css') }}?v={{ filemtime(public_path('assets/css/docente/notas.css')) }}">
@endpush

@section('content')
<div class="not-wrap" id="not-app">

    {{-- Encabezado --}}
    <header class="not-header app-anim">
        <h1 class="not-title">Notas</h1>
        <p class="not-sub">Pon la nota de cada módulo de 1,0 a 5,0, con un decimal: se aprueba con 3,0. Quien pierde un módulo ya terminado tiene una sola habilitación.</p>
    </header>

    {{-- Sin grupos asignados --}}
    <div class="not-empty not-empty-caja" id="not-sin-grupos" hidden>
        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        <p>Todavía no tienes grupos asignados. Cuando la sede te asigne uno, aquí podrás poner sus notas.</p>
    </div>

    {{-- Grupos del docente --}}
    <section class="not-grupos-zona app-anim app-anim-d1" id="not-grupos-zona" aria-labelledby="not-grupos-titulo">
        <div class="not-grupos-bloque" id="not-grupos-vig-zona">
            <h2 class="not-seccion-titulo" id="not-grupos-titulo">Tus grupos</h2>
            <div class="not-grupos" id="not-grupos-vigentes"></div>
        </div>
        <div class="not-grupos-fin" id="not-grupos-fin-zona" hidden>
            <h3 class="not-seccion-titulo">Finalizados · solo consulta</h3>
            <div class="not-grupos" id="not-grupos-finalizados"></div>
        </div>
    </section>

    {{-- Trabajo sobre el grupo elegido --}}
    <section class="not-trabajo app-anim app-anim-d2" id="not-trabajo" aria-labelledby="not-ctx-titulo">
        <div class="not-contexto">
            <div class="not-min0">
                <h2 class="not-ctx-titulo" id="not-ctx-titulo"></h2>
                <p class="not-ctx-sub" id="not-ctx-sub"></p>
            </div>
            <div class="not-vistas" role="tablist" aria-label="Vista de las notas">
                <button type="button" class="not-vista-btn is-active" id="not-tab-modulo" data-vista="modulo" role="tab" aria-selected="true" aria-controls="not-panel-modulo">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    Por módulo
                </button>
                <button type="button" class="not-vista-btn" id="not-tab-completa" data-vista="completa" role="tab" aria-selected="false" aria-controls="not-panel-completa" tabindex="-1">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    Planilla completa
                </button>
            </div>
        </div>

        {{-- ═══════════ Por módulo ═══════════ --}}
        <div class="not-vista" id="not-panel-modulo" role="tabpanel" aria-labelledby="not-tab-modulo">

            <div class="not-modulos-zona">
                <div class="not-seccion-fila">
                    <h3 class="not-seccion-titulo" id="not-malla-titulo">Malla del grupo</h3>
                    <span class="not-seccion-nota">Las fechas salen del horario y de las horas de cada módulo</span>
                </div>
                <div class="not-modulos" id="not-modulos" role="tablist" aria-labelledby="not-malla-titulo"></div>
            </div>

            <div class="not-hoja" id="not-hoja" role="tabpanel">
                <div class="not-hoja-head" id="not-hoja-head"></div>
                <div class="not-avisos" id="not-hoja-avisos"></div>

                <div class="not-kpis" id="not-kpis">
                    <div class="not-kpi-chip not-kpi-blue">
                        <div class="not-kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
                        <div><div class="not-kpi-val" id="not-k-prom">—</div><div class="not-kpi-lbl">Promedio del módulo</div></div>
                    </div>
                    <div class="not-kpi-chip not-kpi-green">
                        <div class="not-kpi-icon"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></div>
                        <div><div class="not-kpi-val" id="not-k-aprob">0</div><div class="not-kpi-lbl">Aprobados</div></div>
                    </div>
                    <div class="not-kpi-chip not-kpi-amber">
                        <div class="not-kpi-icon"><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/></svg></div>
                        <div><div class="not-kpi-val" id="not-k-hab">0</div><div class="not-kpi-lbl">Por habilitar</div></div>
                    </div>
                    <div class="not-kpi-chip not-kpi-red">
                        <div class="not-kpi-icon"><svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                        <div><div class="not-kpi-val" id="not-k-rep">0</div><div class="not-kpi-lbl">Reprobados</div></div>
                    </div>
                    <div class="not-kpi-chip not-kpi-orange">
                        <div class="not-kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                        <div><div class="not-kpi-val" id="not-k-sin">0</div><div class="not-kpi-lbl">Sin nota</div></div>
                    </div>
                </div>

                <div class="not-table-wrap" id="not-tabla-wrap">
                    <table class="not-table">
                        <caption class="not-sr" id="not-tabla-cap"></caption>
                        <thead>
                            <tr>
                                <th scope="col">Estudiante</th>
                                <th scope="col">Nota</th>
                                <th scope="col">Habilitación</th>
                                <th scope="col">Nota final</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="not-filas"></tbody>
                    </table>
                    <p class="not-pie" id="not-tabla-pie"></p>
                </div>

                <div class="not-empty not-empty-caja" id="not-hoja-vacia" hidden>
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <p id="not-hoja-vacia-txt"></p>
                </div>

                <p class="not-form-error" id="not-guardar-error" role="alert" hidden></p>

                <div class="not-guardar" id="not-guardar" hidden>
                    <p class="not-cambios" id="not-cambios" aria-live="polite">Sin cambios por guardar</p>
                    <div class="not-guardar-acciones">
                        <button type="button" class="not-btn not-btn-ghost" id="not-descartar" disabled>Descartar cambios</button>
                        <button type="button" class="not-btn not-btn-primary" id="not-guardar-btn" disabled>
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span id="not-guardar-txt">Guardar notas</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════ Planilla completa ═══════════ --}}
        <div class="not-vista" id="not-panel-completa" role="tabpanel" aria-labelledby="not-tab-completa" hidden>
            <div class="not-avisos" id="not-completa-avisos"></div>
            <div class="not-matriz-wrap" id="not-matriz-wrap" tabindex="0" role="region" aria-label="Notas de todos los módulos"></div>
            <div class="not-leyenda" id="not-leyenda">
                <span><span class="not-celda not-c-aprobado">4,2</span>Aprobado</span>
                <span><span class="not-celda not-c-habilito">3,4<sup>H</sup></span>Habilitó</span>
                <span><span class="not-celda not-c-por_habilitar">2,5</span>Por habilitar</span>
                <span><span class="not-celda not-c-reprobado">2,4<sup>H</sup></span>Reprobado</span>
                <span><span class="not-celda not-c-sin_nota"></span>Sin nota</span>
                <span><span class="not-celda not-c-no_cursado">·</span>No cursado, en curso o por iniciar</span>
            </div>
        </div>
    </section>

</div>{{-- /not-wrap --}}

{{-- Diálogo: cambios sin guardar al cambiar de módulo o de grupo --}}
<div class="not-dialogo" id="not-dialogo" hidden>
    <div class="not-dialogo-overlay" data-dlg="cancelar"></div>
    <div class="not-dialogo-caja" role="dialog" aria-modal="true" aria-labelledby="not-dlg-titulo" aria-describedby="not-dlg-texto">
        <h2 class="not-dialogo-titulo" id="not-dlg-titulo">Tienes notas sin guardar</h2>
        <p class="not-dialogo-texto" id="not-dlg-texto"></p>
        <p class="not-form-error" id="not-dlg-error" role="alert" hidden></p>
        <div class="not-dialogo-acciones">
            <button type="button" class="not-btn not-btn-ghost" data-dlg="cancelar">Cancelar</button>
            <button type="button" class="not-btn not-btn-ghost not-btn-peligro" data-dlg="descartar">Descartar</button>
            <button type="button" class="not-btn not-btn-primary" data-dlg="guardar">Guardar</button>
        </div>
    </div>
</div>

<div class="not-toast" id="not-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/docente/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/docente/datos-ejemplo.js')) }}"></script>
<script src="{{ asset('assets/js/docente/notas.js') }}?v={{ filemtime(public_path('assets/js/docente/notas.js')) }}"></script>
@endpush
