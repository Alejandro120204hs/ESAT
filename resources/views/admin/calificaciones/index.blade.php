{{-- Calificaciones: datos reales desde Admin\Calificaciones\CalificacionController (CalificacionService).
     Solo consulta: la nota la pone el docente. Nota por módulo de 1,0 a 5,0 (aprueba 3,0), una sola
     habilitación por módulo perdido; las fechas de cada módulo se calculan con el horario del grupo y
     las horas de cada módulo en orden. --}}
@extends('admin.layout')

@section('title', 'Calificaciones')
@section('page-title', 'Calificaciones')
@section('active', 'calificaciones')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/calificaciones.css') }}?v={{ filemtime(public_path('assets/css/admin/calificaciones.css')) }}">
@endpush

@php
/* Datos reales (CalificacionService::resumenSede): grupos con clases de la sede, sus estudiantes y notas. */
$gruposPorId  = $grupos->keyBy('id');
$promSede     = $resumen['prom_sede'];
$listos       = $resumen['listos'];
$porHabilitar = $resumen['por_habilitar'];
$reprobados   = $resumen['reprobados'];
$modSinNota   = $resumen['mod_sin_nota'];
$docSinNota   = $resumen['doc_sin_nota'];

$fmt       = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '');
$tonoNota  = fn ($v) => $v === null ? 'none' : ($v >= 4.0 ? 'ok' : ($v >= 3.0 ? 'warn' : 'bad'));
$iniciales = fn ($n, $a) => mb_strtoupper(mb_substr($n, 0, 1) . mb_substr($a, 0, 1));
$SITUACION = [
    'listo'     => ['app-status-active', 'Listo para graduar'],
    'graduado'  => ['app-status-active', 'Graduado'],
    'al_dia'    => ['app-status-muted',  'Al día'],
    'habilitar' => ['app-status-warn',   'Por habilitar'],
    'reprobado' => ['app-status-danger', 'Perdió la habilitación'],
    'sin_notas' => ['app-status-muted',  'Sin notas aún'],
];
$sede = auth()->user()->sede;
@endphp

@section('content')
<div class="cal-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="cal-header">
        <div>
            <h2 class="cal-title">Calificaciones</h2>
            <p class="cal-sub">Consulta las notas que ponen los docentes en cada módulo del programa. Escala de 1,0 a 5,0: se aprueba con 3,0 y quien pierde un módulo tiene una habilitación. Para graduarse hay que tener todos los módulos aprobados.</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="cal-kpis">
        <div class="cal-kpi-chip cal-kpi-blue">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $fmt($promSede) }}</div>
                <div class="cal-kpi-lbl">Promedio de la sede</div>
            </div>
        </div>
        <div class="cal-kpi-chip cal-kpi-green">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $listos }}</div>
                <div class="cal-kpi-lbl">Listos para graduar (notas)</div>
            </div>
        </div>
        <div class="cal-kpi-chip cal-kpi-orange">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $modSinNota }}</div>
                <div class="cal-kpi-lbl">Módulos terminados sin nota · {{ $docSinNota }} {{ $docSinNota === 1 ? 'docente' : 'docentes' }}</div>
            </div>
        </div>
        <div class="cal-kpi-chip cal-kpi-red">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $porHabilitar }}</div>
                <div class="cal-kpi-lbl">Por habilitar{{ $reprobados ? ' · ' . $reprobados . ' perdieron la habilitación' : '' }}</div>
            </div>
        </div>
    </div>

    {{-- Vistas --}}
    <div class="cal-vistas" role="tablist" aria-label="Vista">
        <button type="button" class="cal-vista-btn is-active" data-vista="grupos" role="tab" aria-selected="true">
            Por grupo <span class="cal-vista-num {{ $modSinNota ? 'is-aviso' : '' }}">{{ $grupos->count() }}</span>
        </button>
        <button type="button" class="cal-vista-btn" data-vista="estudiantes" role="tab" aria-selected="false">
            Por estudiante <span class="cal-vista-num {{ $porHabilitar + $reprobados ? 'is-aviso' : '' }}">{{ $estudiantes->count() }}</span>
        </button>
    </div>

    {{-- ═══════════ Por grupo ═══════════ --}}
    <section class="cal-vista" data-panel="grupos">
        <div class="cal-filters">
            <div class="cal-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="cal-g-search" placeholder="Buscar por programa, grupo o docente..." autocomplete="off" aria-label="Buscar grupo">
            </div>
            <div class="app-combobox" id="cal-g-cb-jornada" data-position="absolute" data-options='@json($jornadas)'>
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las jornadas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-g-jornada" data-combobox-value>
            </div>
            <div class="app-combobox" id="cal-g-cb-situacion" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las situaciones</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-g-situacion" data-combobox-value>
            </div>
            <span class="cal-count-badge" id="cal-g-count"></span>
            <button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" data-exportar="grupos" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>

        <div class="cal-table-wrap">
            <table class="cal-table" id="cal-g-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Docente</th>
                        <th>Avance de módulos</th>
                        <th>Promedio</th>
                        <th>Pendientes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grupos as $g)
                    @php
                        $nMod = count($g['modulos']);
                        $mAct = $g['actual'] !== null ? $g['modulos'][$g['actual']] : null;
                    @endphp
                    <tr class="cal-row" data-id="{{ $g['id'] }}"
                        data-buscar="{{ mb_strtolower($g['programa'].' '.$g['grupo'].' '.$g['docente'].' '.$g['escuela']) }}"
                        data-jornada="{{ $g['jornada'] }}"
                        data-sin="{{ count($g['sin_nota']) }}" data-hab="{{ $g['por_habilitar'] + $g['reprobados'] }}"
                        data-listos="{{ $g['listos'] }}" data-terminado="{{ $g['terminado'] ? 1 : 0 }}">
                        <td>
                            <div class="cal-person-cell">
                                <div class="cal-avatar cal-esc-{{ $g['sigla'] }}">{{ mb_substr($g['grupo'], -1) }}</div>
                                <div>
                                    <div class="cal-person-name">{{ $g['programa'] }}</div>
                                    <div class="cal-person-sub">{{ $g['grupo'] }} · {{ $g['jornada'] }} · {{ $g['activos'] }} {{ $g['activos'] === 1 ? 'estudiante' : 'estudiantes' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="cal-muted-cell">{{ $g['docente'] }}</td>
                        <td>
                            <div class="cal-avance" aria-label="{{ $g['terminados'] }} de {{ $nMod }} módulos terminados">
                                @foreach($g['modulos'] as $m)
                                    <span class="cal-avance-seg is-{{ $m['sin_nota'] ? 'sin' : $m['estado'] }}" title="{{ $loop->iteration }}. {{ $m['nombre'] }}"></span>
                                @endforeach
                            </div>
                            <div class="cal-person-sub">
                                @if($g['terminado'])
                                    Grupo terminado el {{ $g['fin_txt'] }}
                                @elseif($mAct)
                                    En curso: {{ $g['actual'] + 1 }} de {{ $nMod }} · {{ $mAct['nombre'] }}
                                @else
                                    {{ $g['terminados'] }} de {{ $nMod }} terminados
                                @endif
                            </div>
                        </td>
                        <td><span class="cal-nota cal-tono-{{ $tonoNota($g['prom']) }}">{{ $fmt($g['prom']) }}</span></td>
                        <td>
                            <div class="cal-tags">
                                @if(count($g['sin_nota']))
                                    <span class="cal-sin-tag">{{ count($g['sin_nota']) === 1 ? '1 módulo sin nota' : count($g['sin_nota']).' módulos sin nota' }}</span>
                                @endif
                                @if($g['por_habilitar'])
                                    <span class="cal-hab-tag">{{ $g['por_habilitar'] }} por habilitar</span>
                                @endif
                                @if($g['reprobados'])
                                    <span class="cal-rep-tag">{{ $g['reprobados'] }} {{ $g['reprobados'] === 1 ? 'reprobó' : 'reprobaron' }}</span>
                                @endif
                                @if($g['listos'])
                                    <span class="app-status-tag app-status-active">{{ $g['listos'] }} {{ $g['listos'] === 1 ? 'listo' : 'listos' }} para graduar</span>
                                @endif
                                @if($g['graduados'])
                                    <span class="app-status-tag app-status-muted">{{ $g['graduados'] }} {{ $g['graduados'] === 1 ? 'graduado' : 'graduados' }}</span>
                                @endif
                                @if(! count($g['sin_nota']) && ! $g['por_habilitar'] && ! $g['reprobados'] && ! $g['listos'] && ! $g['graduados'])
                                    <span class="cal-person-sub">Ninguno</span>
                                @endif
                            </div>
                        </td>
                        <td class="cal-actions-cell">
                            <button type="button" class="cal-icon-btn" title="Ver notas del grupo" aria-label="Ver notas de {{ $g['programa'] }} {{ $g['grupo'] }}">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="cal-empty" id="cal-g-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>{{ $grupos->isEmpty() ? 'Tu sede todavía no tiene grupos con clases.' : 'No hay grupos que coincidan con la búsqueda.' }}</p>
            </div>
        </div>
        <div class="cal-pagination" id="cal-g-pagination" hidden></div>
    </section>

    {{-- ═══════════ Por estudiante ═══════════ --}}
    <section class="cal-vista" data-panel="estudiantes" hidden>
        <div class="cal-filters">
            <div class="cal-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="cal-e-search" placeholder="Buscar por nombre o documento..." autocomplete="off" aria-label="Buscar estudiante">
            </div>
            <div class="app-combobox" id="cal-e-cb-grupo" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los grupos</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-e-grupo" data-combobox-value>
            </div>
            <div class="app-combobox" id="cal-e-cb-situacion" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las situaciones</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-e-situacion" data-combobox-value>
            </div>
            <div class="app-combobox" id="cal-e-cb-orden" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Menor promedio primero</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-e-orden" data-combobox-value>
            </div>
            <span class="cal-count-badge" id="cal-e-count"></span>
            <button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" data-exportar="estudiantes" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>

        <div class="cal-table-wrap">
            <table class="cal-table" id="cal-e-table">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Programa y grupo</th>
                        <th>Módulos aprobados</th>
                        <th>Promedio</th>
                        <th>Situación</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($estudiantes as $e)
                    @php $g = $gruposPorId[$e['grupo_id']]; $pctAprob = $e['total'] ? round($e['aprobados'] * 100 / $e['total']) : 0; @endphp
                    <tr class="cal-row" data-id="{{ $e['id'] }}"
                        data-buscar="{{ mb_strtolower($e['nombres'].' '.$e['apellidos'].' '.$e['documento']) }}"
                        data-grupo="{{ $e['grupo_id'] }}" data-situacion="{{ $e['situacion'] }}"
                        data-prom="{{ $e['prom'] ?? 9 }}" data-perdidos="{{ $e['por_habilitar'] + $e['reprobados'] }}"
                        data-nombre="{{ mb_strtolower($e['apellidos'].' '.$e['nombres']) }}">
                        <td>
                            <div class="cal-person-cell">
                                <div class="cal-avatar cal-esc-{{ $g['sigla'] }}">{{ $iniciales($e['nombres'], $e['apellidos']) }}</div>
                                <div>
                                    <div class="cal-person-name">{{ $e['nombres'] }} {{ $e['apellidos'] }}</div>
                                    <div class="cal-person-sub">{{ $e['tipo_doc'] }} {{ $e['documento'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cal-prog">{{ $g['programa'] }}</div>
                            <div class="cal-person-sub">{{ $g['grupo'] }} · {{ $g['jornada'] }}</div>
                        </td>
                        <td>
                            <div class="cal-meter-cell">
                                <span class="cal-meter"><span style="width: {{ $pctAprob }}%"></span></span>
                                <span class="cal-num">{{ $e['aprobados'] }}</span><span class="cal-de">de {{ $e['total'] }}</span>
                            </div>
                        </td>
                        <td><span class="cal-nota cal-tono-{{ $tonoNota($e['prom']) }}">{{ $fmt($e['prom']) }}</span></td>
                        <td><span class="app-status-tag {{ $SITUACION[$e['situacion']][0] }}">{{ $SITUACION[$e['situacion']][1] }}</span></td>
                        <td class="cal-actions-cell">
                            <button type="button" class="cal-icon-btn" title="Ver notas" aria-label="Ver notas de {{ $e['nombres'] }}">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="cal-empty" id="cal-e-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>{{ $estudiantes->isEmpty() ? 'Todavía no hay estudiantes en grupos con clases.' : 'No hay estudiantes que coincidan con la búsqueda.' }}</p>
            </div>
        </div>
        <div class="cal-pagination" id="cal-e-pagination" hidden></div>
    </section>

</div>{{-- /cal-wrap --}}

{{-- ═══════════════════════════════════════════════════════
     DRAWER — Notas del grupo / notas del estudiante
     (un solo cajón; el contenido lo arma calificaciones.js)
     ═══════════════════════════════════════════════════════ --}}
<div class="cal-drawer" id="cal-drawer" aria-hidden="true">
    <div class="cal-drawer-overlay" data-cerrar></div>
    <div class="cal-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="cal-dw-titulo">
        <div class="cal-drawer-head">
            <div class="cal-drawer-head-left">
                <button type="button" class="cal-close-btn" id="cal-dw-volver" aria-label="Volver" hidden>
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="cal-drawer-avatar" id="cal-dw-avatar"></div>
                <div class="cal-drawer-head-info">
                    <h3 class="cal-drawer-title" id="cal-dw-titulo"></h3>
                    <div class="cal-drawer-sub" id="cal-dw-sub"></div>
                    <div class="cal-drawer-badges" id="cal-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="cal-close-btn" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="cal-drawer-body" id="cal-dw-body"></div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     CERTIFICADO DE NOTAS — vista previa e impresión
     (datos legales reales de ESAT; las notas las arma calificaciones.js)
     ═══════════════════════════════════════════════════════ --}}
<div class="cal-cert-modal" id="cal-cert" aria-hidden="true">
    <div class="cal-cert-overlay" data-cert-cerrar></div>
    <div class="cal-cert-dialog" role="dialog" aria-modal="true" aria-label="Certificado de notas">
        <div class="cal-cert-acciones">
            <button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" data-cert-cerrar>Cerrar</button>
            <button type="button" class="cal-btn cal-btn-primary cal-btn-sm" id="cal-cert-imprimir">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir o guardar en PDF
            </button>
        </div>
        <article class="cal-hoja" id="cal-hoja">
            <header class="cal-hoja-cab">
                <div class="cal-hoja-marca">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT">
                    <div>
                        <strong>Escuela Nacional de Educación ESAT</strong>
                        <span>NIT 900820764-1 · Lic. Funcionamiento 5850</span>
                        @if($sede)
                            <span>Sede {{ $sede->nombre }}@if($sede->direccion) · {{ $sede->direccion }}@endif @if($sede->ciudad) · {{ $sede->ciudad }}@endif</span>
                        @endif
                    </div>
                </div>
                <div class="cal-hoja-num">
                    <span>Certificado de notas</span>
                    <strong id="cal-hoja-fecha"></strong>
                </div>
            </header>
            <div id="cal-hoja-cuerpo"></div>
        </article>
    </div>
</div>

<div class="cal-toast" id="cal-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var CAL_DATA = {
    grupos: @json($grupos->values()),
    estudiantes: @json($estudiantes->values()),
    hoy: @json($hoy),
    ciudad: @json($sede?->ciudad)
};
</script>
<script src="{{ asset('assets/js/admin/calificaciones.js') }}?v={{ filemtime(public_path('assets/js/admin/calificaciones.js')) }}"></script>
@endpush
