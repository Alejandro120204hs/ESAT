{{-- Asistencia: datos reales desde Admin\Asistencia\AsistenciaController (AsistenciaService). Solo consulta: la toma el docente. --}}
@extends('admin.layout')

@section('title', 'Asistencia')
@section('page-title', 'Asistencia')
@section('active', 'asistencia')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/asistencia.css') }}?v={{ filemtime(public_path('assets/css/admin/asistencia.css')) }}">
@endpush

@php
/* Datos reales (AsistenciaService::resumenSede): grupos vigentes de la sede, sus estudiantes y docentes. */
$grupos      = collect($grupos);
$estudiantes = collect($estudiantes);
$docentes    = collect($docentes);
$gruposPorId = $grupos->keyBy('id');
$vigentes    = $estudiantes->where('estado', 'activo');
$pctSede     = $resumen['pct_sede'];
$hoyTotal    = $resumen['hoy_total'];
$hoyTomadas  = $resumen['hoy_tomadas'];
$hoyMasTarde = $resumen['hoy_mas_tarde'];
$sinTomar    = $resumen['sin_tomar'];
$bajo80      = $resumen['bajo_minimo'];
$docSinTomar = $resumen['docentes_sin'];
$jornadas    = $grupos->pluck('jornada')->unique()->sort()->values();

$tonoPct   = fn ($p) => $p === null ? 'none' : ($p >= 90 ? 'ok' : ($p >= 80 ? 'warn' : 'bad'));
$iniciales = fn ($n, $a) => mb_strtoupper(mb_substr($n, 0, 1) . mb_substr($a, 0, 1));
@endphp

@section('content')
<div class="asi-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="asi-header">
        <div>
            <h2 class="asi-title">Asistencia</h2>
            <p class="asi-sub">Consulta la asistencia que registran los docentes en cada clase: qué grupos y estudiantes están faltando y qué clases quedaron sin registrar. Es informativa: no bloquea matrículas ni grados.</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="asi-kpis">
        <div class="asi-kpi-chip asi-kpi-blue">
            <div class="asi-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div>
                <div class="asi-kpi-val">{{ $pctSede !== null ? $pctSede.' %' : '—' }}</div>
                <div class="asi-kpi-lbl">Asistencia de la sede</div>
            </div>
        </div>
        <div class="asi-kpi-chip asi-kpi-green">
            <div class="asi-kpi-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <div class="asi-kpi-val">{{ $hoyTomadas }} <span class="asi-kpi-de">de {{ $hoyTotal }}</span></div>
                <div class="asi-kpi-lbl">
                    @if($hoyTotal === 0)
                        Hoy no hay clases
                    @else
                        Clases de hoy registradas{{ $hoyMasTarde ? ' · ' . $hoyMasTarde . ' más tarde' : '' }}
                    @endif
                </div>
            </div>
        </div>
        <div class="asi-kpi-chip asi-kpi-orange">
            <div class="asi-kpi-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="asi-kpi-val">{{ $sinTomar }}</div>
                <div class="asi-kpi-lbl">Clases sin registrar · {{ $docSinTomar }} {{ $docSinTomar === 1 ? 'docente' : 'docentes' }}</div>
            </div>
        </div>
        <div class="asi-kpi-chip asi-kpi-red">
            <div class="asi-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="asi-kpi-val">{{ $bajo80 }}</div>
                <div class="asi-kpi-lbl">Estudiantes bajo el 80 %</div>
            </div>
        </div>
    </div>

    {{-- Vistas --}}
    <div class="asi-vistas" role="tablist" aria-label="Vista">
        <button type="button" class="asi-vista-btn is-active" data-vista="grupos" role="tab" aria-selected="true">
            Por grupo <span class="asi-vista-num">{{ $grupos->count() }}</span>
        </button>
        <button type="button" class="asi-vista-btn" data-vista="estudiantes" role="tab" aria-selected="false">
            Por estudiante <span class="asi-vista-num {{ $bajo80 ? 'is-aviso' : '' }}">{{ $vigentes->count() }}</span>
        </button>
        <button type="button" class="asi-vista-btn" data-vista="docentes" role="tab" aria-selected="false">
            Por docente <span class="asi-vista-num {{ $docSinTomar ? 'is-aviso' : '' }}">{{ $docentes->count() }}</span>
        </button>
    </div>

    {{-- ═══════════ Por grupo ═══════════ --}}
    <section class="asi-vista" data-panel="grupos">
        <div class="asi-filters">
            <div class="asi-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="asi-g-search" placeholder="Buscar por programa, grupo o docente..." autocomplete="off" aria-label="Buscar grupo">
            </div>
            <div class="app-combobox" id="asi-g-cb-jornada" data-position="absolute" data-options='@json($jornadas)'>
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las jornadas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="asi-g-jornada" data-combobox-value>
            </div>
            <div class="app-combobox" id="asi-g-cb-situacion" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las situaciones</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="asi-g-situacion" data-combobox-value>
            </div>
            <span class="asi-count-badge" id="asi-g-count"></span>
            <button type="button" class="asi-btn asi-btn-ghost asi-btn-sm" data-exportar="grupos" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>

        <div class="asi-table-wrap">
            <table class="asi-table" id="asi-g-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Docente</th>
                        <th>Horario</th>
                        <th>Registradas</th>
                        <th>Asistencia</th>
                        <th>Hoy</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grupos as $g)
                    @php $nSin = count($g['sin_tomar']); @endphp
                    <tr class="asi-row" data-id="{{ $g['id'] }}"
                        data-buscar="{{ mb_strtolower($g['programa'].' '.$g['grupo'].' '.$g['docente'].' '.$g['escuela']) }}"
                        data-jornada="{{ $g['jornada'] }}"
                        data-sin="{{ $nSin }}" data-pct="{{ $g['pct'] ?? '' }}">
                        <td>
                            <div class="asi-person-cell">
                                <div class="asi-avatar asi-esc-{{ $g['sigla'] }}">{{ mb_substr($g['grupo'], -1) }}</div>
                                <div>
                                    <div class="asi-person-name">{{ $g['programa'] }}</div>
                                    <div class="asi-person-sub">{{ $g['grupo'] }} · {{ $g['activos'] }} estudiantes</div>
                                </div>
                            </div>
                        </td>
                        <td class="asi-muted-cell">{{ $g['docente'] }}</td>
                        <td>
                            <div class="asi-horario">{{ $g['dias_txt'] }}</div>
                            <div class="asi-person-sub">{{ $g['horas_txt'] }} · {{ $g['jornada'] }}</div>
                        </td>
                        <td>
                            <span class="asi-num">{{ $g['tomadas'] }}</span><span class="asi-de"> de {{ $g['clases'] }}</span>
                            @if($nSin)
                                <span class="asi-sin-tag">{{ $nSin }} sin registrar</span>
                            @endif
                        </td>
                        <td>
                            <div class="asi-meter-cell asi-tono-{{ $tonoPct($g['pct']) }}">
                                <span class="asi-meter"><span style="width: {{ $g['pct'] ?? 0 }}%"></span></span>
                                <span class="asi-pct">{{ $g['pct'] !== null ? $g['pct'].' %' : '—' }}</span>
                            </div>
                        </td>
                        <td>
                            @switch($g['hoy'])
                                @case('tomada')    <span class="app-status-tag app-status-active">Registrada</span> @break
                                @case('sin_tomar') <span class="app-status-tag app-status-danger">Sin registrar</span> @break
                                @case('pendiente') <span class="app-status-tag app-status-muted">Más tarde</span> @break
                                @default           <span class="asi-person-sub">Sin clase</span>
                            @endswitch
                        </td>
                        <td class="asi-actions-cell">
                            <button type="button" class="asi-icon-btn" title="Ver planilla" aria-label="Ver planilla de {{ $g['programa'] }} {{ $g['grupo'] }}">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="asi-empty" id="asi-g-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>{{ $grupos->isEmpty() ? 'Tu sede todavía no tiene grupos con clases y horario.' : 'No hay grupos que coincidan con la búsqueda.' }}</p>
            </div>
        </div>
        <div class="asi-pagination" id="asi-g-pagination" hidden></div>
    </section>

    {{-- ═══════════ Por estudiante ═══════════ --}}
    <section class="asi-vista" data-panel="estudiantes" hidden>
        <div class="asi-filters">
            <div class="asi-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="asi-e-search" placeholder="Buscar por nombre o documento..." autocomplete="off" aria-label="Buscar estudiante">
            </div>
            <div class="app-combobox" id="asi-e-cb-grupo" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los grupos</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="asi-e-grupo" data-combobox-value>
            </div>
            <div class="app-combobox" id="asi-e-cb-situacion" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las situaciones</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="asi-e-situacion" data-combobox-value>
            </div>
            <div class="app-combobox" id="asi-e-cb-orden" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Más fallas primero</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="asi-e-orden" data-combobox-value>
            </div>
            <span class="asi-count-badge" id="asi-e-count"></span>
            <button type="button" class="asi-btn asi-btn-ghost asi-btn-sm" data-exportar="estudiantes" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>

        <div class="asi-table-wrap">
            <table class="asi-table" id="asi-e-table">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Programa y grupo</th>
                        <th>Asistencia</th>
                        <th>Faltas</th>
                        <th>Tardes</th>
                        <th>Excusas</th>
                        <th>Última falta</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vigentes as $e)
                    @php $g = $gruposPorId[$e['grupo_id']]; @endphp
                    <tr class="asi-row" data-id="{{ $e['id'] }}"
                        data-buscar="{{ mb_strtolower($e['nombres'].' '.$e['apellidos'].' '.$e['documento']) }}"
                        data-grupo="{{ $e['grupo_id'] }}" data-situacion="{{ $e['situacion'] }}"
                        data-pct="{{ $e['pct'] ?? 101 }}" data-faltas="{{ $e['A'] }}"
                        data-nombre="{{ mb_strtolower($e['apellidos'].' '.$e['nombres']) }}">
                        <td>
                            <div class="asi-person-cell">
                                <div class="asi-avatar asi-esc-{{ $g['sigla'] }}">{{ $iniciales($e['nombres'], $e['apellidos']) }}</div>
                                <div>
                                    <div class="asi-person-name">{{ $e['nombres'] }} {{ $e['apellidos'] }}</div>
                                    <div class="asi-person-sub">{{ $e['tipo_doc'] }} {{ $e['documento'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="asi-prog">{{ $g['programa'] }}</div>
                            <div class="asi-person-sub">{{ $g['grupo'] }} · {{ $g['jornada'] }}</div>
                        </td>
                        <td>
                            <div class="asi-meter-cell asi-tono-{{ $tonoPct($e['pct']) }}">
                                <span class="asi-meter"><span style="width: {{ $e['pct'] ?? 0 }}%"></span></span>
                                <span class="asi-pct">{{ $e['pct'] !== null ? $e['pct'].' %' : '—' }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="asi-num {{ $e['A'] ? 'asi-num-bad' : '' }}">{{ $e['A'] }}</span>
                            @if($e['racha'] >= 2)
                                <span class="asi-racha-tag">{{ $e['racha'] }} seguidas</span>
                            @endif
                        </td>
                        <td><span class="asi-num">{{ $e['T'] }}</span></td>
                        <td><span class="asi-num">{{ $e['E'] }}</span></td>
                        <td class="asi-muted-cell">{{ $e['ultima_falla_txt'] ?? 'Ninguna' }}</td>
                        <td class="asi-actions-cell">
                            <button type="button" class="asi-icon-btn" title="Ver historial" aria-label="Ver historial de {{ $e['nombres'] }}">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="asi-empty" id="asi-e-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>{{ $vigentes->isEmpty() ? 'Todavía no hay estudiantes activos en grupos con clases.' : 'No hay estudiantes que coincidan con la búsqueda.' }}</p>
            </div>
        </div>
        <div class="asi-pagination" id="asi-e-pagination" hidden></div>
    </section>

    {{-- ═══════════ Por docente ═══════════ --}}
    <section class="asi-vista" data-panel="docentes" hidden>
        <div class="asi-filters">
            <div class="asi-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="asi-d-search" placeholder="Buscar por nombre o cédula..." autocomplete="off" aria-label="Buscar docente">
            </div>
            <div class="app-combobox" id="asi-d-cb-situacion" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los docentes</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="asi-d-situacion" data-combobox-value>
            </div>
            <span class="asi-count-badge" id="asi-d-count"></span>
            <button type="button" class="asi-btn asi-btn-ghost asi-btn-sm" data-exportar="docentes" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>

        <div class="asi-table-wrap">
            <table class="asi-table" id="asi-d-table">
                <thead>
                    <tr>
                        <th>Docente</th>
                        <th>Grupos a cargo</th>
                        <th>Registradas</th>
                        <th>Sin registrar</th>
                        <th>Último registro</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($docentes as $d)
                    <tr class="asi-row" data-id="{{ $d['id'] }}"
                        data-buscar="{{ mb_strtolower($d['nombres'].' '.$d['apellidos'].' '.$d['documento']) }}"
                        data-sin="{{ $d['sin_tomar'] }}">
                        <td>
                            <div class="asi-person-cell">
                                <div class="asi-avatar asi-esc-{{ $d['sigla'] }}">{{ $iniciales($d['nombres'], $d['apellidos']) }}</div>
                                <div>
                                    <div class="asi-person-name">{{ $d['nombres'] }} {{ $d['apellidos'] }}</div>
                                    <div class="asi-person-sub">{{ $d['tipo_doc'] }} {{ $d['documento'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="asi-grupos-chips">
                                @foreach($d['grupos'] as $gid)
                                    <span class="asi-grupo-chip" title="{{ $gruposPorId[$gid]['programa'] }} · {{ $gruposPorId[$gid]['grupo'] }}">{{ $gruposPorId[$gid]['programa'] }} · {{ mb_substr($gruposPorId[$gid]['grupo'], -1) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td><span class="asi-num">{{ $d['tomadas'] }}</span><span class="asi-de"> de {{ $d['clases'] }}</span></td>
                        <td>
                            @if($d['sin_tomar'])
                                <span class="asi-sin-tag">{{ $d['sin_tomar'] }} {{ $d['sin_tomar'] === 1 ? 'clase' : 'clases' }}</span>
                            @else
                                <span class="app-status-tag app-status-active">Al día</span>
                            @endif
                        </td>
                        <td class="asi-muted-cell">{{ $d['ultima_txt'] ?? 'Todavía no registra' }}</td>
                        <td class="asi-actions-cell">
                            <button type="button" class="asi-icon-btn" title="Ver detalle" aria-label="Ver detalle de {{ $d['nombres'] }}">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="asi-empty" id="asi-d-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>{{ $docentes->isEmpty() ? 'Ningún grupo con clases tiene docente asignado.' : 'No hay docentes que coincidan con la búsqueda.' }}</p>
            </div>
        </div>
        <div class="asi-pagination" id="asi-d-pagination" hidden></div>
    </section>

</div>{{-- /asi-wrap --}}

{{-- ═══════════════════════════════════════════════════════
     DRAWER — Planilla del grupo / historial del estudiante / docente
     (un solo cajón; el contenido lo arma asistencia.js)
     ═══════════════════════════════════════════════════════ --}}
<div class="asi-drawer" id="asi-drawer" aria-hidden="true">
    <div class="asi-drawer-overlay" data-cerrar></div>
    <div class="asi-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="asi-dw-titulo">
        <div class="asi-drawer-head">
            <div class="asi-drawer-head-left">
                <button type="button" class="asi-close-btn" id="asi-dw-volver" aria-label="Volver" hidden>
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="asi-drawer-avatar" id="asi-dw-avatar"></div>
                <div class="asi-drawer-head-info">
                    <h3 class="asi-drawer-title" id="asi-dw-titulo"></h3>
                    <div class="asi-drawer-sub" id="asi-dw-sub"></div>
                    <div class="asi-drawer-badges" id="asi-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="asi-close-btn" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="asi-drawer-body" id="asi-dw-body"></div>
    </div>
</div>

<div class="asi-toast" id="asi-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var ASI_DATA = {
    grupos: @json($grupos->values()),
    estudiantes: @json($estudiantes->values()),
    docentes: @json($docentes),
    hoy: @json($hoy)
};
</script>
<script src="{{ asset('assets/js/admin/asistencia.js') }}?v={{ filemtime(public_path('assets/js/admin/asistencia.js')) }}"></script>
@endpush
