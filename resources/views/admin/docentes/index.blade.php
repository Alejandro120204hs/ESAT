{{-- Docentes: datos reales desde Admin\Docentes\DocenteController. --}}
@extends('admin.layout')

@section('title', 'Docentes')
@section('page-title', 'Docentes')
@section('active', 'docentes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/docentes.css') }}?v={{ filemtime(public_path('assets/css/admin/docentes.css')) }}">
@endpush

@php
/* Datos reales (Admin\Docentes\DocenteController@index): docentes de la sede del admin. */
$docentes      = collect($docentes);
$totalDocentes = $docentes->count();
$activos       = $docentes->where('estado', 'activo')->count();
$tiempoComp    = $docentes->where('vinculacion', 'tiempo_completo')->count();
$escuelasDoc   = $docentes->pluck('escuela')->unique()->count();
$escFiltro     = $docentes->pluck('escuela')->unique()->sort()->values();
@endphp

@section('content')
<div class="doc-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="doc-header">
        <div>
            <h2 class="doc-title">Planta docente</h2>
            <p class="doc-sub">Aquí podrás registrar nuevos docentes, consultar su información, ver los cursos que tienen asignados y gestionar su estado en la institución.</p>
        </div>
        <button class="doc-btn doc-btn-primary" id="btn-nuevo-doc">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo docente
        </button>
    </div>

    {{-- KPIs --}}
    <div class="doc-kpis">
        <div class="doc-kpi-chip doc-kpi-blue">
            <div class="doc-kpi-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/><path d="M2 10h2M20 10h2"/></svg>
            </div>
            <div>
                <div class="doc-kpi-val">{{ $totalDocentes }}</div>
                <div class="doc-kpi-lbl">Docentes</div>
            </div>
        </div>
        <div class="doc-kpi-chip doc-kpi-green">
            <div class="doc-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div class="doc-kpi-val">{{ $activos }}</div>
                <div class="doc-kpi-lbl">Activos</div>
            </div>
        </div>
        <div class="doc-kpi-chip doc-kpi-orange">
            <div class="doc-kpi-icon">
                <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            </div>
            <div>
                <div class="doc-kpi-val">{{ $tiempoComp }}</div>
                <div class="doc-kpi-lbl">Tiempo completo</div>
            </div>
        </div>
        <div class="doc-kpi-chip doc-kpi-navy">
            <div class="doc-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div>
                <div class="doc-kpi-val">{{ $escuelasDoc }}</div>
                <div class="doc-kpi-lbl">Escuelas</div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="doc-filters">
        <div class="doc-search-box">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="doc-search" placeholder="Buscar por nombre o cédula..." autocomplete="off">
        </div>
        <div class="app-combobox" id="doc-cb-filter-escuela" data-position="absolute"
             data-options='@json($escFiltro)'>
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todas las escuelas</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="doc-filter-escuela" data-combobox-value>
        </div>
        <div class="app-combobox" id="doc-cb-filter-vinc" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todas las vinculaciones</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="doc-filter-vinc" data-combobox-value>
        </div>
        <div class="app-combobox" id="doc-cb-filter-estado" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todos los estados</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="doc-filter-estado" data-combobox-value>
        </div>
        <span class="doc-count-badge" id="doc-count">{{ $totalDocentes }} docentes</span>
    </div>

    {{-- Tabla --}}
    <div class="doc-table-wrap">
        <table class="doc-table" id="doc-table">
            <thead>
                <tr>
                    <th>Docente</th>
                    <th>Escuela</th>
                    <th>Especialidad</th>
                    <th>Vinculación</th>
                    <th>Cursos</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($docentes as $d)
                @php
                    $esc      = $d['sigla'];
                    $initials = mb_strtoupper(mb_substr($d['nombres'],0,1) . mb_substr($d['apellidos'],0,1));
                    $numCursos= count($d['cursos']);
                @endphp
                <tr class="doc-row"
                    data-nombre="{{ mb_strtolower($d['nombres'].' '.$d['apellidos'].' '.$d['cedula'].' '.$d['documento']) }}"
                    data-escuela="{{ $d['escuela'] }}"
                    data-vinculacion="{{ $d['vinculacion'] }}"
                    data-estado="{{ $d['estado'] }}"
                    data-id="{{ $d['id'] }}">
                    <td>
                        <div class="doc-person-cell">
                            <div class="doc-avatar doc-esc-{{ $esc }}">{{ $initials }}</div>
                            <div>
                                <div class="doc-person-name">{{ $d['nombres'] }} {{ $d['apellidos'] }}</div>
                                <div class="doc-person-cedula">{{ $d['tipo_doc'] }} {{ $d['cedula'] }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="doc-esc-tag doc-esc-{{ $esc }}">{{ $d['escuela'] }}</span></td>
                    <td class="doc-esp-cell">{{ $d['especialidad'] }}</td>
                    <td>
                        @if($d['vinculacion']==='tiempo_completo')
                            <span class="doc-vinc-tag doc-vinc-tc">Tiempo completo</span>
                        @elseif($d['vinculacion']==='hora_catedra')
                            <span class="doc-vinc-tag doc-vinc-hc">Hora cátedra</span>
                        @else
                            <span class="doc-vinc-tag doc-vinc-mt">Medio tiempo</span>
                        @endif
                    </td>
                    <td>
                        <span class="doc-cursos-num">{{ $numCursos }}</span>
                    </td>
                    <td>
                        @if($d['estado']==='activo')
                            <span class="app-status-tag app-status-active">Activo</span>
                        @elseif($d['estado']==='en_proceso')
                            <span class="app-status-tag app-status-warn">En proceso</span>
                        @else
                            <span class="app-status-tag app-status-muted">Inactivo</span>
                        @endif
                    </td>
                    <td class="doc-actions-cell">
                        <button class="doc-icon-btn" title="Ver detalle" data-id="{{ $d['id'] }}" data-action="ver">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="doc-icon-btn" title="Editar" data-id="{{ $d['id'] }}" data-action="editar">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="doc-empty" id="doc-empty" style="display:none">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <p>{{ $docentes->isEmpty() ? 'Tu sede todavía no tiene docentes. Registra el primero con «Nuevo docente».' : 'No hay docentes que coincidan con la búsqueda' }}</p>
        </div>
    </div>

    {{-- Paginación --}}
    <div class="doc-pagination" id="doc-pagination">
        <button class="doc-pg-btn doc-pg-prev" id="doc-pg-prev" disabled>
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="doc-pg-pages" id="doc-pg-pages"></div>
        <button class="doc-pg-btn doc-pg-next" id="doc-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </div>

</div>{{-- /doc-wrap --}}

{{-- ═══════════════════════════════════════════════════════
     DRAWER — Detalle del docente
     ═══════════════════════════════════════════════════════ --}}
<div class="doc-drawer" id="doc-drawer" aria-hidden="true">
    <div class="doc-drawer-overlay" id="doc-drawer-overlay"></div>
    <div class="doc-drawer-panel" role="dialog" aria-modal="true">

        {{-- Cabecera --}}
        <div class="doc-drawer-head">
            <div class="doc-drawer-head-left">
                <div class="doc-drawer-avatar" id="dw-avatar"></div>
                <div class="doc-drawer-head-info">
                    <h3 class="doc-drawer-title" id="dw-nombre"></h3>
                    <div class="doc-drawer-cedula" id="dw-cedula-txt"></div>
                    <div id="dw-badges" class="doc-drawer-badges"></div>
                </div>
            </div>
            <button class="doc-close-btn" id="doc-drawer-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Tabs --}}
        <div class="doc-dtabs" role="tablist">
            <button class="doc-dtab is-active" data-tab="general" role="tab">General</button>
            <button class="doc-dtab" data-tab="academico" role="tab">Académico</button>
            <button class="doc-dtab" data-tab="cursos" role="tab">Cursos</button>
            <button class="doc-dtab" data-tab="documentos" role="tab">Documentos</button>
        </div>

        {{-- Cuerpo --}}
        <div class="doc-drawer-body">

            {{-- Tab General --}}
            <div class="doc-tab-panel is-active" data-panel="general">
                <div class="doc-info-grid">
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Correo electrónico</span>
                        <span class="doc-info-val" id="dw-email"></span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Teléfono</span>
                        <span class="doc-info-val" id="dw-telefono"></span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Tipo de documento</span>
                        <span class="doc-info-val" id="dw-tipo-doc"></span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Número de documento</span>
                        <span class="doc-info-val" id="dw-num-doc"></span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Fecha de nacimiento</span>
                        <span class="doc-info-val" id="dw-fecha-nac"></span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Dirección</span>
                        <span class="doc-info-val" id="dw-direccion"></span>
                    </div>
                </div>
            </div>

            {{-- Tab Académico --}}
            <div class="doc-tab-panel" data-panel="academico">
                <div class="doc-info-grid">
                    <div class="doc-info-item doc-info-full">
                        <span class="doc-info-lbl">Título profesional</span>
                        <span class="doc-info-val doc-info-highlight" id="dw-titulo"></span>
                    </div>
                    <div class="doc-info-item doc-info-full">
                        <span class="doc-info-lbl">Especialidad / Énfasis</span>
                        <span class="doc-info-val" id="dw-especialidad"></span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Escuela asignada</span>
                        <span class="doc-info-val" id="dw-escuela-txt"></span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-lbl">Vinculación</span>
                        <span class="doc-info-val" id="dw-vinc-txt"></span>
                    </div>
                </div>
                <div class="doc-programas-section">
                    <div class="doc-programas-title">Programas que dicta</div>
                    <div id="dw-programas-list" class="doc-programas-list"></div>
                </div>
            </div>

            {{-- Tab Cursos --}}
            <div class="doc-tab-panel" data-panel="cursos">
                <div class="doc-cursos-wrap">
                    <table class="doc-cursos-table">
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th>Programa</th>
                                <th>Grupo</th>
                                <th>Horario</th>
                            </tr>
                        </thead>
                        <tbody id="dw-cursos-body"></tbody>
                    </table>
                    <div class="doc-cursos-empty" id="dw-cursos-empty" style="display:none">
                        Sin cursos asignados actualmente.
                    </div>
                </div>
            </div>

            {{-- Tab Documentos --}}
            <div class="doc-tab-panel" data-panel="documentos">
                <div class="doc-docs-placeholder">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <p>Gestión de documentos próximamente</p>
                    <span>Hoja de vida, títulos, certificaciones y contratos.</span>
                </div>
            </div>

        </div>{{-- /doc-drawer-body --}}

        {{-- Footer --}}
        <div class="doc-drawer-footer">
            <button class="doc-btn doc-btn-secondary" id="doc-drawer-edit-btn">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar docente
            </button>
            <button class="doc-btn doc-btn-danger" id="doc-drawer-toggle-btn">Desactivar docente</button>
        </div>

    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     MODAL — Nuevo / Editar docente
     ═══════════════════════════════════════════════════════ --}}
<div class="doc-modal" id="doc-modal" aria-hidden="true">
    <div class="doc-modal-overlay" id="doc-modal-overlay"></div>
    <div class="doc-modal-panel" role="dialog" aria-modal="true">

        {{-- Header --}}
        <div class="doc-modal-head">
            <div>
                <h3 class="doc-modal-title" id="doc-modal-title">Nuevo docente</h3>
                <span class="doc-modal-step-lbl" id="doc-step-lbl">Paso 1 de 3</span>
            </div>
            <button class="doc-close-btn" id="doc-modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Wizard steps --}}
        <div class="doc-wsteps">
            <div class="doc-wstep is-active" data-step="1" title="Paso 1: Datos personales">
                <div class="doc-wstep-dot"></div>
                <span>Datos personales</span>
            </div>
            <div class="doc-wstep-line"></div>
            <div class="doc-wstep" data-step="2" title="Paso 2: Info académica">
                <div class="doc-wstep-dot"></div>
                <span>Info académica</span>
            </div>
            <div class="doc-wstep-line"></div>
            <div class="doc-wstep" data-step="3" title="Paso 3: Vinculación">
                <div class="doc-wstep-dot"></div>
                <span>Vinculación</span>
            </div>
        </div>

        {{-- Steps content --}}
        <div class="doc-modal-body">

            {{-- Paso 1: Datos personales --}}
            <div class="doc-mstep is-active" data-step="1">
                <div class="doc-fields-grid">
                    <div class="doc-field">
                        <label for="doc-inp-nombres">Nombres *</label>
                        <input type="text" id="doc-inp-nombres" maxlength="100" autocomplete="off" placeholder="María">
                    </div>
                    <div class="doc-field">
                        <label for="doc-inp-apellidos">Apellidos *</label>
                        <input type="text" id="doc-inp-apellidos" maxlength="100" autocomplete="off" placeholder="González Ruiz">
                    </div>
                    <div class="doc-field">
                        <label>Tipo de documento *</label>
                        <div class="app-combobox" id="doc-cb-tipo-doc">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona un tipo</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar tipo...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="doc-inp-tipo-doc" data-combobox-value>
                        </div>
                    </div>
                    <div class="doc-field">
                        <label for="doc-inp-cedula">Número de documento *</label>
                        <input type="text" id="doc-inp-cedula" inputmode="numeric" maxlength="15" autocomplete="off" placeholder="52345678">
                    </div>
                    <div class="doc-field">
                        <label for="doc-inp-email">Correo electrónico *</label>
                        <input type="email" id="doc-inp-email" maxlength="255" autocomplete="off" placeholder="docente@esat.edu.co">
                    </div>
                    <div class="doc-field">
                        <label for="doc-inp-telefono">Teléfono *</label>
                        <input type="tel" id="doc-inp-telefono" inputmode="numeric" maxlength="14" autocomplete="off" placeholder="310 123 4567">
                    </div>
                    <div class="doc-field">
                        <label for="doc-inp-fecha-nac">Fecha de nacimiento</label>
                        <input type="date" id="doc-inp-fecha-nac">
                    </div>
                    <div class="doc-field">
                        <label for="doc-inp-direccion">Dirección de residencia</label>
                        <input type="text" id="doc-inp-direccion" maxlength="255" autocomplete="off" placeholder="Ej. Cra 4 # 6-21">
                    </div>
                    <div class="doc-field">
                        <label>Departamento de residencia</label>
                        <div class="app-combobox" id="doc-cb-depto" data-direction="up">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona un departamento</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar departamento...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="doc-inp-depto" data-combobox-value>
                        </div>
                    </div>
                    <div class="doc-field">
                        <label>Ciudad de residencia</label>
                        <div class="app-combobox" id="doc-cb-ciudad" data-direction="up">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Elige el departamento primero</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar ciudad...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="doc-inp-ciudad" data-combobox-value>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Paso 2: Info académica --}}
            <div class="doc-mstep" data-step="2">
                <div class="doc-fields-grid">
                    <div class="doc-field doc-field-full">
                        <label for="doc-inp-titulo">Título profesional *</label>
                        <input type="text" id="doc-inp-titulo" maxlength="150" autocomplete="off" placeholder="Ej. Enfermera Profesional">
                    </div>
                    <div class="doc-field doc-field-full">
                        <label for="doc-inp-especialidad">Especialidad / Énfasis *</label>
                        <input type="text" id="doc-inp-especialidad" maxlength="150" autocomplete="off" placeholder="Ej. Cuidados Críticos y Geriatría">
                    </div>
                    <div class="doc-field doc-field-full">
                        <label>Escuela asignada *</label>
                        <div class="app-combobox" id="doc-cb-escuela" data-direction="up">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona una escuela</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar escuela...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="doc-inp-escuela" data-combobox-value>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Paso 3: Vinculación --}}
            <div class="doc-mstep" data-step="3">
                <div class="doc-fields-grid">
                    <div class="doc-field doc-field-full">
                        <label>Tipo de vinculación *</label>
                        <div class="app-combobox" id="doc-cb-vinculacion">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona la vinculación</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="doc-inp-vinculacion" data-combobox-value>
                        </div>
                    </div>
                    <div class="doc-field doc-field-full">
                        <label>Estado *</label>
                        <div class="app-combobox" id="doc-cb-estado">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona el estado</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="doc-inp-estado" data-combobox-value>
                        </div>
                    </div>
                </div>
            </div>

            <div class="doc-nota" id="doc-nota-clave">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <p>El número de documento será el usuario y la contraseña inicial del docente. Solo los docentes en estado <strong>Activo</strong> pueden ingresar al sistema.</p>
            </div>
            <div class="doc-form-error" id="doc-form-error" role="alert" hidden></div>
        </div>{{-- /doc-modal-body --}}

        {{-- Footer --}}
        <div class="doc-modal-footer">
            <button class="doc-btn doc-btn-ghost" id="doc-btn-back">Atrás</button>
            <button class="doc-btn doc-btn-primary" id="doc-btn-next">Siguiente</button>
        </div>

    </div>
</div>

{{-- Aviso al guardar --}}
<div class="doc-toast" id="doc-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var DOC_DATA = @json($docentes);
var DOC_ESCUELAS = @json($escuelas);
</script>
<script src="{{ asset('assets/js/admin/docentes.js') }}?v={{ filemtime(public_path('assets/js/admin/docentes.js')) }}"></script>
@endpush
