{{-- mock --}}
@extends('admin.layout')

@section('title', 'Docentes')
@section('page-title', 'Docentes')
@section('active', 'docentes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/docentes.css') }}">
@endpush

@php
$sedeName = auth()->user()->sede?->nombre ?? 'Sede';
$escMap   = ['Salud'=>'sal','Cocina y Turismo'=>'tur','Administrativa'=>'adm',
             'Educación e Idiomas'=>'edu','Deporte y Cultura'=>'dep','Ciencias'=>'cie','Belleza'=>'bel'];

/* ---- mock data ---- */
$docentes = [
    ['id'=>1,'nombres'=>'María','apellidos'=>'González Ruiz','cedula'=>'52.345.678','tipo_doc'=>'CC',
     'email'=>'mgonzalez@esat.edu.co','telefono'=>'310 123 4567','fecha_nac'=>'12 Mar 1985',
     'direccion'=>'Cra 15 # 32-45, Medellín','escuela'=>'Salud',
     'titulo'=>'Enfermera Profesional','especialidad'=>'Cuidados Críticos y Geriatría',
     'vinculacion'=>'tiempo_completo','estado'=>'activo',
     'programas'=>['Servicios Geriátricos','Auxiliar de Psiquiatría'],
     'cursos'=>[
         ['nombre'=>'Fundamentos de gerontología','programa'=>'Servicios Geriátricos','grupo'=>'Grupo A — Mañana','horario'=>'Lun-Mié 7:00–10:00'],
         ['nombre'=>'Cuidado básico de enfermería','programa'=>'Servicios Geriátricos','grupo'=>'Grupo B — Tarde','horario'=>'Mar-Jue 14:00–17:00'],
     ]],
    ['id'=>2,'nombres'=>'Alejandro','apellidos'=>'Ríos Mora','cedula'=>'71.234.567','tipo_doc'=>'CC',
     'email'=>'arios@esat.edu.co','telefono'=>'315 987 6543','fecha_nac'=>'05 Jun 1979',
     'direccion'=>'Av. El Poblado # 15-30, Medellín','escuela'=>'Salud',
     'titulo'=>'Médico General','especialidad'=>'Salud Ocupacional y Seguridad',
     'vinculacion'=>'hora_catedra','estado'=>'activo',
     'programas'=>['Seguridad Ocupacional y Laboral','Auxiliar de Psiquiatría'],
     'cursos'=>[
         ['nombre'=>'Ética y legislación en salud','programa'=>'Servicios Geriátricos','grupo'=>'Grupo A — Mañana','horario'=>'Vie 7:00–10:00'],
         ['nombre'=>'Primeros auxilios y emergencias','programa'=>'Seguridad Ocupacional','grupo'=>'Grupo A — Tarde','horario'=>'Mar 14:00–17:00'],
     ]],
    ['id'=>3,'nombres'=>'Laura','apellidos'=>'Martínez Peña','cedula'=>'43.567.890','tipo_doc'=>'CC',
     'email'=>'lmartinez@esat.edu.co','telefono'=>'312 456 7890','fecha_nac'=>'22 Sep 1987',
     'direccion'=>'Cll 33 # 78-12, Medellín','escuela'=>'Cocina y Turismo',
     'titulo'=>'Gastrónoma','especialidad'=>'Alta Cocina y Panadería Artesanal',
     'vinculacion'=>'tiempo_completo','estado'=>'activo',
     'programas'=>['Cocina Nacional e Internacional','Servicios Hoteleros y Turísticos'],
     'cursos'=>[
         ['nombre'=>'Técnicas culinarias básicas','programa'=>'Cocina Nacional','grupo'=>'Grupo A — Mañana','horario'=>'Lun-Mié-Vie 8:00–12:00'],
         ['nombre'=>'Cocina colombiana y fusión','programa'=>'Cocina Nacional','grupo'=>'Grupo B — Tarde','horario'=>'Mar-Jue 13:00–17:00'],
     ]],
    ['id'=>4,'nombres'=>'Andrés','apellidos'=>'Zapata Villa','cedula'=>'8.765.432','tipo_doc'=>'CC',
     'email'=>'azapata@esat.edu.co','telefono'=>'318 321 6540','fecha_nac'=>'14 Feb 1982',
     'direccion'=>'Cra 43 # 12-80, El Poblado','escuela'=>'Cocina y Turismo',
     'titulo'=>'Chef Ejecutivo — Grado WSET','especialidad'=>'Sommelier y Enología',
     'vinculacion'=>'hora_catedra','estado'=>'activo',
     'programas'=>['Cocina Nacional e Internacional'],
     'cursos'=>[
         ['nombre'=>'Introducción a la enología','programa'=>'Cocina Nacional','grupo'=>'Grupo A — Mañana','horario'=>'Jue 8:00–11:00'],
     ]],
    ['id'=>5,'nombres'=>'Patricia','apellidos'=>'López Castro','cedula'=>'21.987.654','tipo_doc'=>'CC',
     'email'=>'plopez@esat.edu.co','telefono'=>'314 654 3210','fecha_nac'=>'03 Nov 1980',
     'direccion'=>'Cll 50 # 35-20, Laureles','escuela'=>'Administrativa',
     'titulo'=>'Contadora Pública','especialidad'=>'Contabilidad y Facturación Médica',
     'vinculacion'=>'tiempo_completo','estado'=>'activo',
     'programas'=>['Auditoría y Facturación de Cuentas Médicas','Auxiliar Contable y Administrativo'],
     'cursos'=>[
         ['nombre'=>'Fundamentos de contabilidad','programa'=>'Auditoría y Facturación','grupo'=>'Grupo A — Mañana','horario'=>'Lun-Mié 8:00–11:00'],
         ['nombre'=>'Software contable SIIGO','programa'=>'Auxiliar Contable','grupo'=>'Grupo B — Tarde','horario'=>'Mar-Jue 14:00–16:00'],
         ['nombre'=>'Facturación médica y RIPS','programa'=>'Auditoría y Facturación','grupo'=>'Grupo A — Tarde','horario'=>'Vie 13:00–16:00'],
     ]],
    ['id'=>6,'nombres'=>'Roberto','apellidos'=>'Díaz Sierra','cedula'=>'98.456.123','tipo_doc'=>'CC',
     'email'=>'rdiaz@esat.edu.co','telefono'=>'316 789 0123','fecha_nac'=>'19 Jul 1975',
     'direccion'=>'Cll 11 # 27-45, Envigado','escuela'=>'Administrativa',
     'titulo'=>'Administrador de Empresas','especialidad'=>'Gestión del Talento Humano',
     'vinculacion'=>'medio_tiempo','estado'=>'activo',
     'programas'=>['Auxiliar Contable y Administrativo'],
     'cursos'=>[
         ['nombre'=>'Habilidades gerenciales','programa'=>'Auxiliar Contable','grupo'=>'Grupo A — Mañana','horario'=>'Lun-Vie 10:00–12:00'],
     ]],
    ['id'=>7,'nombres'=>'Diana','apellidos'=>'Vargas Nieto','cedula'=>'32.109.876','tipo_doc'=>'CC',
     'email'=>'dvargas@esat.edu.co','telefono'=>'313 567 8901','fecha_nac'=>'08 Abr 1990',
     'direccion'=>'Cra 65 # 46-30, Belén','escuela'=>'Educación e Idiomas',
     'titulo'=>'Licenciada en Lenguas Modernas','especialidad'=>'Inglés y Francés DELF/Cambridge',
     'vinculacion'=>'tiempo_completo','estado'=>'activo',
     'programas'=>['Inglés A1–B2','Asistente de Preescolar'],
     'cursos'=>[
         ['nombre'=>'Inglés A1 — Básico','programa'=>'Inglés A1–B2','grupo'=>'Grupo A — Mañana','horario'=>'Lun-Mié-Vie 7:00–9:00'],
         ['nombre'=>'Inglés A2 — Elemental','programa'=>'Inglés A1–B2','grupo'=>'Grupo B — Tarde','horario'=>'Mar-Jue 14:00–16:00'],
         ['nombre'=>'Inglés B1 — Intermedio','programa'=>'Inglés A1–B2','grupo'=>'Grupo C — Noche','horario'=>'Lun-Mié 18:00–20:00'],
     ]],
    ['id'=>8,'nombres'=>'Felipe','apellidos'=>'Morales Gómez','cedula'=>'1.098.765.432','tipo_doc'=>'CC',
     'email'=>'fmorales@esat.edu.co','telefono'=>'319 876 5432','fecha_nac'=>'25 Ene 1995',
     'direccion'=>'Cll 30 # 55-10, Itagüí','escuela'=>'Educación e Idiomas',
     'titulo'=>'Licenciado en Pedagogía Infantil','especialidad'=>'Primera Infancia y Desarrollo',
     'vinculacion'=>'hora_catedra','estado'=>'en_proceso',
     'programas'=>['Asistente de Preescolar'],
     'cursos'=>[
         ['nombre'=>'Desarrollo infantil integral','programa'=>'Asistente de Preescolar','grupo'=>'Grupo A — Mañana','horario'=>'Mar-Jue 8:00–11:00'],
     ]],
    ['id'=>9,'nombres'=>'Sandra','apellidos'=>'Torres Álvarez','cedula'=>'43.210.987','tipo_doc'=>'CC',
     'email'=>'storres@esat.edu.co','telefono'=>'311 234 5678','fecha_nac'=>'17 Dic 1988',
     'direccion'=>'Cll 18 # 43-65, Sabaneta','escuela'=>'Deporte y Cultura',
     'titulo'=>'Licenciada en Educación Física','especialidad'=>'Natación y Salvamento Acuático',
     'vinculacion'=>'medio_tiempo','estado'=>'activo',
     'programas'=>['Salvamento Acuático'],
     'cursos'=>[
         ['nombre'=>'Técnicas de natación avanzada','programa'=>'Salvamento Acuático','grupo'=>'Grupo A — Mañana','horario'=>'Lun-Mié-Vie 6:00–8:00'],
         ['nombre'=>'Rescate y salvamento','programa'=>'Salvamento Acuático','grupo'=>'Grupo B — Tarde','horario'=>'Mar-Jue 15:00–18:00'],
     ]],
    ['id'=>10,'nombres'=>'Fabián','apellidos'=>'Castro Herrera','cedula'=>'8.123.456','tipo_doc'=>'CC',
     'email'=>'fcastro@esat.edu.co','telefono'=>'317 345 6789','fecha_nac'=>'30 Mar 1983',
     'direccion'=>'Cra 48 # 10-25, Bello','escuela'=>'Ciencias',
     'titulo'=>'Ingeniero Electricista','especialidad'=>'Instalaciones Eléctricas y RETIE',
     'vinculacion'=>'hora_catedra','estado'=>'activo',
     'programas'=>['Electricista','Electromecánica'],
     'cursos'=>[
         ['nombre'=>'Fundamentos de electricidad','programa'=>'Electricista','grupo'=>'Grupo A — Mañana','horario'=>'Lun-Mié 8:00–11:00'],
         ['nombre'=>'Instalaciones eléctricas residenciales','programa'=>'Electricista','grupo'=>'Grupo B — Noche','horario'=>'Mar-Jue 18:00–21:00'],
         ['nombre'=>'Electricidad y magnetismo aplicada','programa'=>'Electromecánica','grupo'=>'Grupo A — Tarde','horario'=>'Vie 13:00–16:00'],
     ]],
    ['id'=>11,'nombres'=>'Adriana','apellidos'=>'Mora Parra','cedula'=>'43.765.432','tipo_doc'=>'CC',
     'email'=>'amora@esat.edu.co','telefono'=>'315 456 7890','fecha_nac'=>'11 Oct 1986',
     'direccion'=>'Cll 55 # 70-40, Laureles','escuela'=>'Ciencias',
     'titulo'=>'Ingeniera Industrial','especialidad'=>'Automatización y Electromecánica',
     'vinculacion'=>'hora_catedra','estado'=>'activo',
     'programas'=>['Electromecánica'],
     'cursos'=>[
         ['nombre'=>'Automatización industrial básica','programa'=>'Electromecánica','grupo'=>'Grupo A — Tarde','horario'=>'Mar-Jue 14:00–17:00'],
         ['nombre'=>'Mantenimiento preventivo y correctivo','programa'=>'Electromecánica','grupo'=>'Grupo A — Tarde','horario'=>'Lun-Mié 14:00–17:00'],
     ]],
    ['id'=>12,'nombres'=>'Iván','apellidos'=>'Peña Salazar','cedula'=>'71.654.321','tipo_doc'=>'CC',
     'email'=>'ipena@esat.edu.co','telefono'=>'316 567 8901','fecha_nac'=>'04 Ago 1991',
     'direccion'=>'Cra 80 # 33-50, Robledo','escuela'=>'Belleza',
     'titulo'=>'Barbero Master Certificado','especialidad'=>'Barbería y Estética Masculina',
     'vinculacion'=>'medio_tiempo','estado'=>'activo',
     'programas'=>['Barbería Profesional'],
     'cursos'=>[
         ['nombre'=>'Técnicas de corte masculino','programa'=>'Barbería Profesional','grupo'=>'Grupo A — Mañana','horario'=>'Mié-Vie 8:00–12:00'],
         ['nombre'=>'Afeitado clásico y cuidado de barba','programa'=>'Barbería Profesional','grupo'=>'Grupo B — Tarde','horario'=>'Mar-Jue 14:00–18:00'],
         ['nombre'=>'Historia y tendencias de barbería','programa'=>'Barbería Profesional','grupo'=>'Grupo A — Mañana','horario'=>'Lun 8:00–10:00'],
     ]],
];

$totalDocentes = count($docentes);
$activos       = count(array_filter($docentes, fn($d) => $d['estado'] === 'activo'));
$tiempoComp    = count(array_filter($docentes, fn($d) => $d['vinculacion'] === 'tiempo_completo'));
$escuelasDoc   = count(array_unique(array_column($docentes, 'escuela')));
@endphp

@section('content')
<div class="doc-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="doc-header">
        <div>
            <h2 class="doc-title">Planta docente</h2>
            <p class="doc-sub">{{ $sedeName }} · Educación para el Trabajo y el Desarrollo Humano</p>
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
             data-options='@json(array_values(array_unique(array_column($docentes,"escuela"))))'>
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
                    $esc      = $escMap[$d['escuela']] ?? 'adm';
                    $initials = strtoupper(substr($d['nombres'],0,1) . substr($d['apellidos'],0,1));
                    $numCursos= count($d['cursos']);
                @endphp
                <tr class="doc-row"
                    data-nombre="{{ strtolower($d['nombres'].' '.$d['apellidos'].' '.$d['cedula']) }}"
                    data-escuela="{{ $d['escuela'] }}"
                    data-vinculacion="{{ $d['vinculacion'] }}"
                    data-estado="{{ $d['estado'] }}"
                    data-id="{{ $d['id'] }}">
                    <td>
                        <div class="doc-person-cell">
                            <div class="doc-avatar doc-esc-{{ $esc }}">{{ $initials }}</div>
                            <div>
                                <div class="doc-person-name">{{ $d['nombres'] }} {{ $d['apellidos'] }}</div>
                                <div class="doc-person-cedula">CC {{ $d['cedula'] }}</div>
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
            <p>No hay docentes que coincidan con la búsqueda</p>
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
            <div class="doc-wstep is-active" data-step="1">
                <div class="doc-wstep-dot"></div>
                <span>Datos personales</span>
            </div>
            <div class="doc-wstep-line"></div>
            <div class="doc-wstep" data-step="2">
                <div class="doc-wstep-dot"></div>
                <span>Info académica</span>
            </div>
            <div class="doc-wstep-line"></div>
            <div class="doc-wstep" data-step="3">
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
                        <label>Nombres *</label>
                        <input type="text" id="doc-inp-nombres" placeholder="María">
                    </div>
                    <div class="doc-field">
                        <label>Apellidos *</label>
                        <input type="text" id="doc-inp-apellidos" placeholder="González Ruiz">
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
                        <label>Número de documento *</label>
                        <input type="text" id="doc-inp-cedula" placeholder="52.345.678">
                    </div>
                    <div class="doc-field">
                        <label>Correo electrónico *</label>
                        <input type="email" id="doc-inp-email" placeholder="docente@esat.edu.co">
                    </div>
                    <div class="doc-field">
                        <label>Teléfono *</label>
                        <input type="tel" id="doc-inp-telefono" placeholder="310 123 4567">
                    </div>
                    <div class="doc-field doc-field-full">
                        <label>Fecha de nacimiento</label>
                        <input type="date" id="doc-inp-fecha-nac">
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
                        <label>Título profesional *</label>
                        <input type="text" id="doc-inp-titulo" placeholder="Ej. Enfermera Profesional">
                    </div>
                    <div class="doc-field doc-field-full">
                        <label>Especialidad / Énfasis *</label>
                        <input type="text" id="doc-inp-especialidad" placeholder="Ej. Cuidados Críticos y Geriatría">
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

        </div>{{-- /doc-modal-body --}}

        {{-- Footer --}}
        <div class="doc-modal-footer">
            <button class="doc-btn doc-btn-ghost" id="doc-btn-back">Atrás</button>
            <button class="doc-btn doc-btn-primary" id="doc-btn-next">Siguiente</button>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
var DOC_DATA = @json($docentes);
</script>
<script src="{{ asset('assets/js/admin/docentes.js') }}"></script>
@endpush
