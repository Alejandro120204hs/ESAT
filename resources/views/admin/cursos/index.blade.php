{{-- mock --}}
@extends('admin.layout')

@section('title', 'Cursos')
@section('page-title', 'Cursos')
@section('active', 'cursos')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/cursos.css') }}">
@endpush

@php
/* ---- mock data ---- */
$cursos = [
    [
        'id'=>1,'codigo'=>'CUR-SAL-001-A','nombre'=>'Servicios Geriátricos','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud',
        'docente'=>'María González Ruiz','modalidad'=>'Presencial','jornada'=>'Mañana',
        'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'08:00','hora_fin'=>'12:00',
        'fecha_inicio'=>'15 Ene 2026','fecha_fin'=>'15 Ene 2027','cupo_max'=>25,'inscritos'=>22,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'Laura Ospina Reyes','cedula'=>'1.020.456.789'],
            ['nombre'=>'Carlos Mendez Rios','cedula'=>'1.030.234.567'],
            ['nombre'=>'Ana Torres Peña','cedula'=>'52.340.123'],
            ['nombre'=>'Jorge Morales Gil','cedula'=>'71.890.234'],
            ['nombre'=>'Sandra Ruiz Cano','cedula'=>'43.120.890'],
        ],
    ],
    [
        'id'=>2,'codigo'=>'CUR-SAL-001-B','nombre'=>'Servicios Geriátricos','grupo'=>'Grupo B',
        'programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud',
        'docente'=>'Alejandro Ríos Mora','modalidad'=>'Presencial','jornada'=>'Tarde',
        'dias'=>['Martes','Jueves'],'hora_inicio'=>'14:00','hora_fin'=>'18:00',
        'fecha_inicio'=>'01 Feb 2026','fecha_fin'=>'01 Feb 2027','cupo_max'=>25,'inscritos'=>18,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'David Castro Lara','cedula'=>'1.015.678.901'],
            ['nombre'=>'Valentina Suárez Mora','cedula'=>'1.022.345.678'],
            ['nombre'=>'Felipe Jiménez Cruz','cedula'=>'80.456.789'],
        ],
    ],
    [
        'id'=>3,'codigo'=>'CUR-SAL-002-A','nombre'=>'Auxiliar de Psiquiatría','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Auxiliar de Psiquiatría','escuela'=>'Salud',
        'docente'=>'María González Ruiz','modalidad'=>'Presencial','jornada'=>'Tarde',
        'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'14:00','hora_fin'=>'18:00',
        'fecha_inicio'=>'10 Mar 2026','fecha_fin'=>'10 Mar 2027','cupo_max'=>20,'inscritos'=>19,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'Paola Herrera Díaz','cedula'=>'43.678.901'],
            ['nombre'=>'Ricardo Gómez Villa','cedula'=>'71.234.890'],
            ['nombre'=>'Camila Rojas Pinto','cedula'=>'1.018.456.234'],
        ],
    ],
    [
        'id'=>4,'codigo'=>'CUR-TUR-001-A','nombre'=>'Cocina Nacional e Internacional','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo',
        'docente'=>'Laura Martínez Peña','modalidad'=>'Presencial','jornada'=>'Mañana',
        'dias'=>['Lunes','Martes','Jueves'],'hora_inicio'=>'07:00','hora_fin'=>'11:00',
        'fecha_inicio'=>'20 Ene 2026','fecha_fin'=>'20 Ene 2027','cupo_max'=>20,'inscritos'=>20,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'Sofía Vargas León','cedula'=>'1.019.345.678'],
            ['nombre'=>'Miguel Ángel Ríos','cedula'=>'80.789.012'],
            ['nombre'=>'Isabella Torres Cruz','cedula'=>'1.025.678.901'],
        ],
    ],
    [
        'id'=>5,'codigo'=>'CUR-TUR-001-B','nombre'=>'Cocina Nacional e Internacional','grupo'=>'Grupo B',
        'programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo',
        'docente'=>'Andrés Zapata Villa','modalidad'=>'Presencial','jornada'=>'Tarde',
        'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'13:00','hora_fin'=>'17:00',
        'fecha_inicio'=>'03 Feb 2026','fecha_fin'=>'03 Feb 2027','cupo_max'=>20,'inscritos'=>17,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'Daniela Pérez Mora','cedula'=>'1.023.456.789'],
            ['nombre'=>'Sebastián López Gil','cedula'=>'1.031.234.567'],
        ],
    ],
    [
        'id'=>6,'codigo'=>'CUR-TUR-002-A','nombre'=>'Sommelier y Enología','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Sommelier y Enología','escuela'=>'Cocina y Turismo',
        'docente'=>'Andrés Zapata Villa','modalidad'=>'Presencial','jornada'=>'Noche',
        'dias'=>['Martes','Jueves','Sábado'],'hora_inicio'=>'18:00','hora_fin'=>'21:00',
        'fecha_inicio'=>'15 Feb 2026','fecha_fin'=>'15 Feb 2027','cupo_max'=>15,'inscritos'=>12,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'Andrés Moreno Cárdenas','cedula'=>'79.890.123'],
            ['nombre'=>'Natalia Soto Reyes','cedula'=>'43.901.234'],
        ],
    ],
    [
        'id'=>7,'codigo'=>'CUR-ADM-001-A','nombre'=>'Auditoría y Facturación Médica','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas','escuela'=>'Administrativa',
        'docente'=>'Patricia López Castro','modalidad'=>'Mixta','jornada'=>'Mañana',
        'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'08:00','hora_fin'=>'12:00',
        'fecha_inicio'=>'12 Ene 2026','fecha_fin'=>'12 Ene 2027','cupo_max'=>30,'inscritos'=>28,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'Juan Pablo Cárdenas','cedula'=>'80.123.456'],
            ['nombre'=>'Luisa Fernanda Niño','cedula'=>'52.890.123'],
            ['nombre'=>'Ernesto Salcedo Vega','cedula'=>'79.345.678'],
        ],
    ],
    [
        'id'=>8,'codigo'=>'CUR-ADM-002-A','nombre'=>'Auxiliar Contable y Administrativo','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa',
        'docente'=>'Roberto Díaz Sierra','modalidad'=>'Presencial','jornada'=>'Tarde',
        'dias'=>['Martes','Jueves'],'hora_inicio'=>'14:00','hora_fin'=>'18:00',
        'fecha_inicio'=>'05 Feb 2026','fecha_fin'=>'05 Nov 2026','cupo_max'=>25,'inscritos'=>21,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'María Camila Torres','cedula'=>'1.021.789.012'],
            ['nombre'=>'Óscar Pineda Ruiz','cedula'=>'80.678.901'],
        ],
    ],
    [
        'id'=>9,'codigo'=>'CUR-EDU-001-A','nombre'=>'Auxiliar de Preescolar','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Asistente de Preescolar','escuela'=>'Educación e Idiomas',
        'docente'=>'Diana Vargas Nieto','modalidad'=>'Presencial','jornada'=>'Mañana',
        'dias'=>['Lunes','Martes','Miércoles','Jueves','Viernes'],'hora_inicio'=>'07:00','hora_fin'=>'10:00',
        'fecha_inicio'=>'20 Ene 2026','fecha_fin'=>'20 Oct 2026','cupo_max'=>20,'inscritos'=>16,
        'estado'=>'activo',
        'estudiantes'=>[
            ['nombre'=>'Alejandra Bernal Lozano','cedula'=>'1.024.567.890'],
            ['nombre'=>'Tatiana Guzmán Pardo','cedula'=>'52.456.789'],
        ],
    ],
    [
        'id'=>10,'codigo'=>'CUR-SAL-003-A','nombre'=>'Seguridad Ocupacional','grupo'=>'Grupo A',
        'programa'=>'Técnico Laboral en Seguridad Ocupacional y Laboral','escuela'=>'Salud',
        'docente'=>'Alejandro Ríos Mora','modalidad'=>'Presencial','jornada'=>'Noche',
        'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'18:00','hora_fin'=>'22:00',
        'fecha_inicio'=>'01 Mar 2026','fecha_fin'=>'01 Mar 2027','cupo_max'=>20,'inscritos'=>8,
        'estado'=>'planificacion',
        'estudiantes'=>[],
    ],
    [
        'id'=>11,'codigo'=>'CUR-ADM-002-B','nombre'=>'Auxiliar Contable y Administrativo','grupo'=>'Grupo B',
        'programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa',
        'docente'=>'Patricia López Castro','modalidad'=>'Virtual','jornada'=>'Noche',
        'dias'=>['Sábado'],'hora_inicio'=>'08:00','hora_fin'=>'14:00',
        'fecha_inicio'=>'10 Ene 2025','fecha_fin'=>'10 Oct 2025','cupo_max'=>25,'inscritos'=>25,
        'estado'=>'finalizado',
        'estudiantes'=>[
            ['nombre'=>'Rodrigo Herrera Ossa','cedula'=>'8.765.432'],
            ['nombre'=>'Marcela Salazar Forero','cedula'=>'43.234.567'],
        ],
    ],
];

$totalInscritos = array_sum(array_column($cursos, 'inscritos'));
$totalCupo      = array_sum(array_column($cursos, 'cupo_max'));
$activos        = count(array_filter($cursos, fn($c)=>$c['estado']==='activo'));
$cupoDisponible = $totalCupo - $totalInscritos;
$programasUnicos = array_values(array_unique(array_column($cursos, 'programa')));
@endphp

@section('content')
<div class="cur-wrap">

    {{-- Encabezado --}}
    <div class="cur-header">
        <div class="cur-header-top">
            <h2 class="cur-title">Cursos</h2>
            <button class="cur-btn cur-btn-primary" id="btn-nuevo-cur">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo curso
            </button>
        </div>
        <p class="cur-sub">Aquí podrás crear nuevos cursos, asignarles un docente y consultar los estudiantes inscritos en cada uno.</p>
    </div>

    {{-- KPIs --}}
    <div class="cur-kpi-grid">
        <div class="cur-kpi-card">
            <div class="cur-kpi-icon cur-kpi-icon-blue">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <div>
                <div class="cur-kpi-val">{{ count($cursos) }}</div>
                <div class="cur-kpi-lbl">Total cursos</div>
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
            <input type="text" id="cur-search" class="cur-search" placeholder="Buscar curso...">
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

        <span class="cur-count-badge" id="cur-count">{{ count($cursos) }} cursos</span>
    </div>

    {{-- Tabla --}}
    <div class="cur-table-wrap">
        <table class="cur-table" id="cur-table">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Programa</th>
                    <th>Docente</th>
                    <th>Horario</th>
                    <th>Inscritos</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($cursos as $c)
                @php
                    $pct = $c['cupo_max'] > 0 ? round(($c['inscritos'] / $c['cupo_max']) * 100) : 0;
                    $fillClass = $pct >= 100 ? 'is-full' : ($pct < 50 ? 'is-low' : '');
                    $escCls = match($c['escuela']) {
                        'Salud' => 'sal', 'Cocina y Turismo' => 'tur',
                        'Administrativa' => 'adm', 'Educación e Idiomas' => 'edu',
                        'Deporte y Cultura' => 'dep', 'Ciencias' => 'cie', default => 'bel'
                    };
                    $estadoCls = match($c['estado']) {
                        'activo' => 'cur-estado-activo',
                        'planificacion' => 'cur-estado-planificacion',
                        default => 'cur-estado-finalizado'
                    };
                    $estadoLbl = match($c['estado']) {
                        'activo' => 'Activo', 'planificacion' => 'En planificación', default => 'Finalizado'
                    };
                    $diasStr = implode(' · ', array_map(fn($d) => substr($d,0,3), $c['dias']));
                @endphp
                <tr class="cur-row"
                    data-id="{{ $c['id'] }}"
                    data-nombre="{{ strtolower($c['nombre'] . ' ' . $c['grupo']) }}"
                    data-programa="{{ $c['programa'] }}"
                    data-estado="{{ $c['estado'] }}">
                    <td>
                        <div class="cur-cell-nombre">
                            <span class="cur-nombre-txt">{{ $c['nombre'] }}</span>
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
                            <span class="cur-dias-txt">{{ $diasStr }}</span>
                            <span class="cur-hora-txt">{{ $c['hora_inicio'] }} – {{ $c['hora_fin'] }}</span>
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
            <div class="cur-empty-ttl">Sin cursos</div>
            <div class="cur-empty-sub">No se encontraron cursos con ese filtro.</div>
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
                    <span class="cur-horario-row-lbl">Hora</span>
                    <span class="cur-horario-row-val" id="dw-hora"></span>
                </div>
                <div class="cur-horario-row" style="margin-top:8px;">
                    <span class="cur-horario-row-lbl">Duración</span>
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
            Editar curso
        </button>
        <button class="cur-btn cur-btn-danger" id="cur-drawer-toggle-btn">Finalizar curso</button>
    </div>
</div>

{{-- ═══════════════════ MODAL WIZARD ═══════════════════ --}}
<div id="cur-modal-overlay" class="cur-modal-overlay">
    <div id="cur-modal" class="cur-modal" role="dialog" aria-hidden="true">

        <div class="cur-modal-head">
            <span class="cur-modal-title" id="cur-modal-title">Nuevo curso</span>
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
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Nombre del curso</label>
                        <input type="text" class="cur-form-input" placeholder="Ej: Servicios Geriátricos">
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl">Código</label>
                        <input type="text" class="cur-form-input" placeholder="Ej: CUR-SAL-001-A">
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl">Grupo</label>
                        <input type="text" class="cur-form-input" placeholder="Ej: Grupo A">
                    </div>
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Programa</label>
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
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Modalidad</label>
                        <div class="app-combobox" id="cur-cb-modalidad">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Presencial</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="cur-val-modalidad" data-combobox-value>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Paso 2 --}}
            <div class="cur-mstep" data-step="2">
                <div class="cur-form-grid">
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Docente</label>
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
                        <label class="cur-form-lbl">Jornada</label>
                        <div class="app-combobox" id="cur-cb-jornada">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Mañana</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="cur-val-jornada" data-combobox-value>
                        </div>
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl">Cupo máximo</label>
                        <input type="number" class="cur-form-input" placeholder="Ej: 25" min="1">
                    </div>

                    <div class="cur-form-group">
                        <label class="cur-form-lbl">Fecha inicio</label>
                        <input type="date" class="cur-form-input">
                    </div>
                    <div class="cur-form-group">
                        <label class="cur-form-lbl">Fecha fin</label>
                        <input type="date" class="cur-form-input">
                    </div>
                    <div class="cur-form-group full">
                        <label class="cur-form-lbl">Estado</label>
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

        <div class="cur-modal-foot">
            <span class="cur-step-lbl" id="cur-step-lbl">Paso 1 de 2</span>
            <div class="cur-modal-foot-btns">
                <button class="cur-btn cur-btn-ghost" id="cur-btn-back" style="visibility:hidden;">Atrás</button>
                <button class="cur-btn cur-btn-primary" id="cur-btn-next">Siguiente</button>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>var CUR_DATA = @json($cursos);</script>
<script src="{{ asset('assets/js/admin/cursos.js') }}" defer></script>
@endpush
