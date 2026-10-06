{{-- Grupos: datos reales desde Admin\Grupos\GrupoController. --}}
@extends('admin.layout')

@section('title', 'Grupos')
@section('page-title', 'Grupos')
@section('active', 'cursos')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/cursos.css') }}?v={{ filemtime(public_path('assets/css/admin/cursos.css')) }}">
@endpush

@php
/* Datos reales (Admin\Grupos\GrupoController@index): grupos de la sede del admin. */
$grupos          = collect($grupos);
$totalInscritos  = $grupos->sum('inscritos');
$activos         = $grupos->where('estado', 'activo')->count();
$cupoDisponible  = $grupos->where('estado', '!=', 'finalizado')->sum(fn ($g) => max(0, $g['cupo_max'] - $g['inscritos']));
$programasUnicos = $grupos->pluck('programa')->unique()->sort()->values();
$diaCorto        = ['Lunes'=>'Lun','Martes'=>'Mar','Miércoles'=>'Mié','Jueves'=>'Jue','Viernes'=>'Vie','Sábado'=>'Sáb'];
@endphp

@section('content')
<div class="cur-wrap">

    {{-- Encabezado --}}
    <div class="cur-header">
        <div class="cur-header-top">
            <h2 class="cur-title">Grupos</h2>
            <button class="cur-btn cur-btn-primary" id="btn-nuevo-cur">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo grupo
            </button>
        </div>
        <p class="cur-sub">Aquí podrás registrar nuevos grupos por programa, asignarles un docente y consultar los estudiantes inscritos en cada uno.</p>
    </div>

    {{-- KPIs --}}
    <div class="cur-kpi-grid">
        <div class="cur-kpi-card">
            <div class="cur-kpi-icon cur-kpi-icon-blue">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <div>
                <div class="cur-kpi-val">{{ $grupos->count() }}</div>
                <div class="cur-kpi-lbl">Total grupos</div>
            </div>
        </div>
        <div class="cur-kpi-card">
            <div class="cur-kpi-icon cur-kpi-icon-green">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div>
                <div class="cur-kpi-val">{{ $activos }}</div>
                <div class="cur-kpi-lbl">Activos</div>
            </div>
        </div>
        <div class="cur-kpi-card">
            <div class="cur-kpi-icon cur-kpi-icon-orange">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="cur-kpi-val">{{ $totalInscritos }}</div>
                <div class="cur-kpi-lbl">Estudiantes inscritos</div>
            </div>
        </div>
        <div class="cur-kpi-card">
            <div class="cur-kpi-icon cur-kpi-icon-purple">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="cur-kpi-val">{{ $cupoDisponible }}</div>
                <div class="cur-kpi-lbl">Cupos disponibles</div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="cur-filter-bar">
        <div class="cur-search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="cur-search" class="cur-search" placeholder="Buscar grupo...">
        </div>

        {{-- Filtro programa --}}
        <div class="app-combobox" id="cur-cb-filter-programa" data-position="absolute"
             data-options='@json($programasUnicos)'>
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todos los programas</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="cur-filter-programa" data-combobox-value>
        </div>

        {{-- Filtro estado --}}
        <div class="app-combobox" id="cur-cb-filter-estado" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todos los estados</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="cur-filter-estado" data-combobox-value>
        </div>

        <span class="cur-count-badge" id="cur-count">{{ $grupos->count() }} grupos</span>
    </div>

    {{-- Tabla --}}
    <div class="cur-table-wrap">
        <table class="cur-table" id="cur-table">
            <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Programa</th>
                    <th>Docente</th>
                    <th>Horario</th>
                    <th>Inscritos</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupos as $c)
                @php
                    $pct = $c['cupo_max'] > 0 ? round(($c['inscritos'] / $c['cupo_max']) * 100) : 0;
                    $fillClass = $pct >= 100 ? 'is-full' : ($pct < 50 ? 'is-low' : '');
                    $escCls = $c['sigla'];
                    $estadoCls = match($c['estado']) {
                        'activo' => 'cur-estado-activo',
                        'planificacion' => 'cur-estado-planificacion',
                        default => 'cur-estado-finalizado'
                    };
                    $estadoLbl = match($c['estado']) {
                        'activo' => 'Activo', 'planificacion' => 'En planificación', default => 'Finalizado'
                    };
                    $ses     = collect($c['sesiones']);
                    $diasStr = $ses->pluck('dia')->unique()->map(fn ($d) => $diaCorto[$d])->implode(' · ');
                    $rangos  = $ses->map(fn ($x) => $x['hora_inicio'].' – '.$x['hora_fin'])->unique();
                @endphp
                <tr class="cur-row"
                    data-id="{{ $c['id'] }}"
                    data-nombre="{{ mb_strtolower($c['grupo'].' '.$c['codigo'].' '.$c['programa'].' '.$c['docente']) }}"
                    data-programa="{{ $c['programa'] }}"
                    data-estado="{{ $c['estado'] }}">
                    <td>
                        <div class="cur-cell-nombre">
                            <span class="cur-grupo-txt">{{ $c['grupo'] }}</span>
                            <span class="cur-codigo-txt">{{ $c['codigo'] }}</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:4px;">
                            <span class="cur-programa-txt">{{ Str::limit($c['programa'], 38) }}</span>
                            <span class="cur-esc-tag cur-esc-{{ $escCls }}">{{ $c['escuela'] }}</span>
                        </div>
                    </td>
                    <td><span class="cur-docente-txt">{{ $c['docente'] }}</span></td>
                    <td>
                        <div class="cur-horario-cell">
                            @if($ses->isEmpty())
                                <span class="cur-dias-txt">Sin horario</span>
                                <span class="cur-hora-txt">Asígnalo en Horarios</span>
                            @else
                                <span class="cur-dias-txt">{{ $diasStr }}</span>
                                <span class="cur-hora-txt">{{ $rangos->count() === 1 ? $rangos->first() : 'Varios horarios · '.$c['horas_semana'].' h/sem' }}</span>
                            @endif
                            <span class="cur-jornada-tag">{{ $c['jornada'] }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="cur-cupo-cell">
                            <span class="cur-cupo-nums">{{ $c['inscritos'] }} <span>/ {{ $c['cupo_max'] }}</span></span>
                            <div class="cur-cupo-bar">
                                <div class="cur-cupo-fill {{ $fillClass }}" style="width:{{ $pct }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="cur-estado-tag {{ $estadoCls }}">{{ $estadoLbl }}</span></td>
                    <td>
                        <div class="cur-actions">
                            <button class="cur-action-btn" data-action="ver" data-id="{{ $c['id'] }}" title="Ver detalle">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button class="cur-action-btn" data-action="editar" data-id="{{ $c['id'] }}" title="Editar">
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div id="cur-empty" class="cur-empty" style="display:none;">
            <div class="cur-empty-icon"><svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
            <div class="cur-empty-ttl">Sin grupos</div>
            <div class="cur-empty-sub">{{ $grupos->isEmpty() ? 'Tu sede todavía no tiene grupos. Crea el primero con «Nuevo grupo».' : 'No se encontraron grupos con ese filtro.' }}</div>
        </div>

        <div id="cur-pagination" class="cur-pagination" style="display:none;">
            <button class="cur-pg-btn" id="cur-pg-prev">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="cur-pg-pages" id="cur-pg-pages"></div>
            <button class="cur-pg-btn" id="cur-pg-next">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </div>

</div>

{{-- ═══════════════════ DRAWER ═══════════════════ --}}
<div id="cur-drawer-overlay" class="cur-drawer-overlay"></div>
<div id="cur-drawer" class="cur-drawer" role="dialog" aria-hidden="true">

    <div class="cur-drawer-head">
        <div class="cur-drawer-avatar">
            <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        </div>
        <div class="cur-drawer-meta">
            <div class="cur-drawer-nombre" id="dw-nombre"></div>
            <div class="cur-drawer-codigo" id="dw-codigo"></div>
            <div class="cur-drawer-badges" id="dw-badges"></div>
        </div>
        <button class="cur-drawer-close" id="cur-drawer-close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="cur-drawer-tabs">
        <button class="cur-dtab is-active" data-tab="general">General</button>
        <button class="cur-dtab" data-tab="horario">Horario</button>
        <button class="cur-dtab" data-tab="estudiantes">Estudiantes</button>
    </div>

    <div class="cur-drawer-body">

        {{-- Tab General --}}
        <div class="cur-tab-panel is-active" data-panel="general">
            <div class="cur-info-grid">
                <div class="cur-info-item" style="grid-column:1/-1;">
                    <span class="cur-info-lbl">Programa</span>
                    <span class="cur-info-val" id="dw-programa"></span>
                </div>
                <div class="cur-info-item">
                    <span class="cur-info-lbl">Escuela</span>
                    <span class="cur-info-val" id="dw-escuela-val"></span>
                </div>
                <div class="cur-info-item">
                    <span class="cur-info-lbl">Modalidad</span>
                    <span class="cur-info-val" id="dw-modalidad"></span>
                </div>
                <div class="cur-info-item">
                    <span class="cur-info-lbl">Docente</span>
                    <span class="cur-info-val" id="dw-docente-val"></span>
                </div>
                <div class="cur-info-item">
                    <span class="cur-info-lbl">Jornada</span>
                    <span class="cur-info-val" id="dw-jornada-val"></span>
                </div>
                <div class="cur-info-item">
                    <span class="cur-info-lbl">Fecha de inicio</span>
                    <span class="cur-info-val" id="dw-fecha-inicio"></span>
                </div>
                <div class="cur-info-item">
                    <span class="cur-info-lbl">Fecha de fin</span>
                    <span class="cur-info-val" id="dw-fecha-fin"></span>
                </div>
                <div class="cur-info-item" style="grid-column:1/-1;">
                    <span class="cur-info-lbl">Cupo</span>
                    <span class="cur-info-val" id="dw-cupo-num"></span>
                    <div class="cur-cupo-drawer">
                        <div class="cur-cupo-drawer-bar">
                            <div class="cur-cupo-drawer-fill" id="dw-cupo-fill" style="width:0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab Horario --}}
        <div class="cur-tab-panel" data-panel="horario">
            <div class="cur-horario-block">
                <div class="cur-horario-dias" id="dw-dias"></div>
                <div class="cur-horario-row">
                    <span class="cur-horario-row-lbl">Por semana</span>
                    <span class="cur-horario-row-val" id="dw-hora"></span>
                </div>
                <div class="cur-horario-row" style="margin-top:8px;">
                    <span class="cur-horario-row-lbl">En todo el grupo</span>
                    <span class="cur-horario-row-val" id="dw-duracion"></span>
                </div>
            </div>
        </div>

        {{-- Tab Estudiantes --}}
        <div class="cur-tab-panel" data-panel="estudiantes">
            <div class="cur-est-list" id="dw-est-list"></div>
        </div>

    </div>

    <div class="cur-drawer-footer">
        <button class="cur-btn cur-btn-ghost" id="cur-drawer-edit-btn">
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Editar grupo
        </button>
        <button class="cur-btn cur-btn-danger" id="cur-drawer-toggle-btn">Finalizar grupo</button>
    </div>
</div>

{{-- ═══════════════════ MODAL WIZARD ═══════════════════ --}}
<div id="cur-modal-overlay" class="cur-modal-overlay">
    <div id="cur-modal" class="cur-modal" role="dialog" aria-hidden="true">

        <div class="cur-modal-head">
            <span class="cur-modal-title" id="cur-modal-title">Nuevo grupo</span>
            <button class="cur-modal-close" id="cur-modal-close">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="cur-wizard-steps">
            <div class="cur-wstep is-active" data-step="1">
                <div class="cur-wstep-num">1</div>
                <span>Información básica</span>
            </div>
            <div class="cur-wstep-connector"></div>
            <div class="cur-wstep" data-step="2">
                <div class="cur-wstep-num">2</div>
                <span>Docente</span>
            </div>
        </div>

        <div class="cur-modal-body">

            {{-- Paso 1 --}}
            <div class="cur-mstep is-active" data-step="1">
                <div class="cur-form-grid">
                    <div class="cur-form-group">
                        <label class="cur-form-lbl" for="cur-inp-codigo">Código</label>
                        <input type="text" class="cur-form-input" id="cur-inp-codigo" maxlength="30" autocomplete="off" placeholder="Se genera solo">
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl" for="cur-inp-grupo">Grupo</label>
                        <input type="text" class="cur-form-input" id="cur-inp-grupo" maxlength="60" autocomplete="off" placeholder="Siguiente letra libre">
                    </div>
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Escuela / Área <span class="cur-form-req">*</span></label>
                        <div class="app-combobox" id="cur-cb-escuela" data-list-max-height="160">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar escuela</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="cur-val-escuela" data-combobox-value>
                        </div>
                    </div>
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Programa <span class="cur-form-req">*</span></label>
                        <div class="app-combobox" id="cur-cb-programa" data-list-max-height="160">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar programa</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar programa...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="cur-val-programa" data-combobox-value>
                        </div>
                    </div>
                    {{-- Modalidad y cupo son los del programa: se editan solo en Programas --}}
                    <div class="cur-form-group">
                        <label class="cur-form-lbl" for="cur-inp-modalidad">Modalidad</label>
                        <input type="text" class="cur-form-input" id="cur-inp-modalidad" readonly tabindex="-1" placeholder="Elige el programa">
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl" for="cur-inp-cupo">Cupo máximo</label>
                        <input type="text" class="cur-form-input" id="cur-inp-cupo" readonly tabindex="-1" placeholder="Elige el programa">
                    </div>
                    <p class="cur-form-hint full">La modalidad y el cupo son los del programa. Para cambiarlos, edita el programa en Programas.</p>
                </div>
            </div>

            {{-- Paso 2 (días y horas de clase se asignan en Horarios) --}}
            <div class="cur-mstep" data-step="2">
                <div class="cur-form-grid">
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Docente <span class="cur-form-req">*</span></label>
                        <div class="app-combobox" id="cur-cb-docente">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar docente</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar docente...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="cur-val-docente" data-combobox-value>
                        </div>
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl">Jornada <span class="cur-form-req">*</span></label>
                        <div class="app-combobox" id="cur-cb-jornada">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Elige el programa primero</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="cur-val-jornada" data-combobox-value>
                        </div>
                    </div>

                    <div class="cur-form-group">
                        <label class="cur-form-lbl" for="cur-inp-inicio">Fecha inicio <span class="cur-form-req">*</span></label>
                        <input type="date" class="cur-form-input" id="cur-inp-inicio">
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl" for="cur-inp-fin">Fecha fin</label>
                        <input type="text" class="cur-form-input" id="cur-inp-fin" readonly tabindex="-1" placeholder="Se calcula sola">
                        <span class="cur-form-hint" id="cur-fin-hint"></span>
                    </div>
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Estado <span class="cur-form-req">*</span></label>
                        <div class="app-combobox" id="cur-cb-estado">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Activo</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="cur-val-estado" data-combobox-value>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="cur-form-error" id="cur-form-error" role="alert" hidden></div>

        <div class="cur-modal-foot">
            <span class="cur-step-lbl" id="cur-step-lbl">Paso 1 de 2</span>
            <div class="cur-modal-foot-btns">
                <button class="cur-btn cur-btn-ghost" id="cur-btn-back" style="visibility:hidden;">Atrás</button>
                <button class="cur-btn cur-btn-primary" id="cur-btn-next">Siguiente</button>
            </div>
        </div>

    </div>
</div>

{{-- Aviso al guardar --}}
<div class="cur-toast" id="cur-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var CUR_DATA = @json($grupos);
var CUR_ESC_PRG = @json($escuelas);
var CUR_DOCENTES = @json($docentes);
</script>
<script src="{{ asset('assets/js/admin/cursos.js') }}?v={{ filemtime(public_path('assets/js/admin/cursos.js')) }}" defer></script>
@endpush
