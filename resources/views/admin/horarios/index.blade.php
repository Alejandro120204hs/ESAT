{{-- mock --}}
@extends('admin.layout')

@section('title', 'Horarios')
@section('page-title', 'Horarios')
@section('active', 'horarios')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/horarios.css') }}">
@endpush

@php
/* ── Datos mock de grupos con horario asignado ── */
$grupos = [
    ['id'=>1,'codigo'=>'CUR-SAL-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud',
     'docente'=>'María González Ruiz','modalidad'=>'Presencial','jornada'=>'Mañana',
     'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'08:00','hora_fin'=>'12:00','estado'=>'activo'],
    ['id'=>2,'codigo'=>'CUR-SAL-001-B','grupo'=>'Grupo B',
     'programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud',
     'docente'=>'Alejandro Ríos Mora','modalidad'=>'Presencial','jornada'=>'Tarde',
     'dias'=>['Martes','Jueves'],'hora_inicio'=>'14:00','hora_fin'=>'18:00','estado'=>'activo'],
    ['id'=>3,'codigo'=>'CUR-SAL-002-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Auxiliar de Psiquiatría','escuela'=>'Salud',
     'docente'=>'María González Ruiz','modalidad'=>'Presencial','jornada'=>'Tarde',
     'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'14:00','hora_fin'=>'18:00','estado'=>'activo'],
    ['id'=>4,'codigo'=>'CUR-TUR-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo',
     'docente'=>'Laura Martínez Peña','modalidad'=>'Presencial','jornada'=>'Mañana',
     'dias'=>['Lunes','Martes','Jueves'],'hora_inicio'=>'07:00','hora_fin'=>'11:00','estado'=>'activo'],
    ['id'=>5,'codigo'=>'CUR-TUR-001-B','grupo'=>'Grupo B',
     'programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo',
     'docente'=>'Andrés Zapata Villa','modalidad'=>'Presencial','jornada'=>'Tarde',
     'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'13:00','hora_fin'=>'17:00','estado'=>'activo'],
    ['id'=>6,'codigo'=>'CUR-TUR-002-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Sommelier y Enología','escuela'=>'Cocina y Turismo',
     'docente'=>'Andrés Zapata Villa','modalidad'=>'Presencial','jornada'=>'Noche',
     'dias'=>['Martes','Jueves','Sábado'],'hora_inicio'=>'18:00','hora_fin'=>'21:00','estado'=>'activo'],
    ['id'=>7,'codigo'=>'CUR-ADM-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas','escuela'=>'Administrativa',
     'docente'=>'Patricia López Castro','modalidad'=>'Mixta','jornada'=>'Mañana',
     'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'08:00','hora_fin'=>'12:00','estado'=>'activo'],
    ['id'=>8,'codigo'=>'CUR-ADM-002-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa',
     'docente'=>'Roberto Díaz Sierra','modalidad'=>'Presencial','jornada'=>'Tarde',
     'dias'=>['Martes','Jueves'],'hora_inicio'=>'14:00','hora_fin'=>'18:00','estado'=>'activo'],
    ['id'=>9,'codigo'=>'CUR-EDU-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Asistente de Preescolar','escuela'=>'Educación e Idiomas',
     'docente'=>'Diana Vargas Nieto','modalidad'=>'Presencial','jornada'=>'Mañana',
     'dias'=>['Lunes','Martes','Miércoles','Jueves','Viernes'],'hora_inicio'=>'07:00','hora_fin'=>'10:00','estado'=>'activo'],
    ['id'=>10,'codigo'=>'CUR-SAL-003-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Seguridad Ocupacional y Laboral','escuela'=>'Salud',
     'docente'=>'Alejandro Ríos Mora','modalidad'=>'Presencial','jornada'=>'Noche',
     'dias'=>['Lunes','Miércoles','Viernes'],'hora_inicio'=>'18:00','hora_fin'=>'22:00','estado'=>'planificacion'],
];

$total   = count($grupos);
$manana  = count(array_filter($grupos, fn($g) => $g['jornada'] === 'Mañana'));
$tarde   = count(array_filter($grupos, fn($g) => $g['jornada'] === 'Tarde'));
$noche   = count(array_filter($grupos, fn($g) => $g['jornada'] === 'Noche'));
@endphp

@section('content')
<div class="hor-wrap">

    {{-- Encabezado --}}
    <div>
        <div class="hor-header-top">
            <div>
                <h2 class="hor-title">Horarios</h2>
                <p class="hor-sub">Gestión y asignación de horarios por grupo y programa.</p>
            </div>
            <button class="hor-btn hor-btn-primary" id="hor-btn-nuevo" type="button">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo horario
            </button>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="hor-kpi-grid">
        <div class="hor-kpi-card">
            <div class="hor-kpi-icon hor-kpi-icon-blue">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <div class="hor-kpi-val" id="hor-kpi-total">{{ $total }}</div>
                <div class="hor-kpi-lbl">Total horarios</div>
            </div>
        </div>
        <div class="hor-kpi-card">
            <div class="hor-kpi-icon hor-kpi-icon-orange">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            </div>
            <div>
                <div class="hor-kpi-val">{{ $manana }}</div>
                <div class="hor-kpi-lbl">Jornada mañana</div>
            </div>
        </div>
        <div class="hor-kpi-card">
            <div class="hor-kpi-icon hor-kpi-icon-green">
                <svg viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/></svg>
            </div>
            <div>
                <div class="hor-kpi-val">{{ $tarde }}</div>
                <div class="hor-kpi-lbl">Jornada tarde</div>
            </div>
        </div>
        <div class="hor-kpi-card">
            <div class="hor-kpi-icon hor-kpi-icon-purple">
                <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </div>
            <div>
                <div class="hor-kpi-val">{{ $noche }}</div>
                <div class="hor-kpi-lbl">Jornada noche</div>
            </div>
        </div>
    </div>

    {{-- Toolbar: filtros + toggle --}}
    <div class="hor-toolbar">
        <div class="hor-filter-bar">
            <div class="hor-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="hor-search" id="hor-search" type="text" placeholder="Buscar grupo, programa…">
            </div>

            {{-- Filtro escuela --}}
            <div class="app-combobox" id="hor-cb-escuela" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las escuelas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="hor-val-escuela" data-combobox-value>
            </div>

            {{-- Filtro jornada --}}
            <div class="app-combobox" id="hor-cb-jornada" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las jornadas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="hor-val-jornada" data-combobox-value>
            </div>

            <span class="hor-count-badge" id="hor-count-badge">{{ $total }} horarios</span>
        </div>

        {{-- Toggle vista --}}
        <div class="hor-view-toggle">
            <button class="hor-view-btn is-active" id="hor-btn-tabla" type="button" title="Vista tabla">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/></svg>
                Tabla
            </button>
            <button class="hor-view-btn" id="hor-btn-grilla" type="button" title="Vista semanal">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Grilla
            </button>
        </div>
    </div>

    {{-- ── Vista TABLA ── --}}
    <div id="hor-view-tabla">
        <div class="hor-table-wrap">
            <table class="hor-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Programa</th>
                        <th>Días</th>
                        <th>Horario</th>
                        <th>Jornada</th>
                        <th>Docente</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="hor-tbody">
                    @foreach($grupos as $g)
                    @php
                        $escMap = [
                            'Salud'=>'sal','Cocina y Turismo'=>'tur','Administrativa'=>'adm',
                            'Educación e Idiomas'=>'edu','Deporte y Cultura'=>'dep','Ciencias'=>'cie','Belleza'=>'bel'
                        ];
                        $escCls = $escMap[$g['escuela']] ?? 'adm';
                        $jorCls = strtolower(str_replace('ñ','n',$g['jornada']));
                        $diasCodes = ['Lunes'=>'L','Martes'=>'M','Miércoles'=>'Mi','Jueves'=>'J','Viernes'=>'V','Sábado'=>'S'];
                        $todosDias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

                        // Duración
                        $hi = explode(':',$g['hora_inicio']);
                        $hf = explode(':',$g['hora_fin']);
                        $durMin = (intval($hf[0])*60+intval($hf[1])) - (intval($hi[0])*60+intval($hi[1]));
                        $durH = floor($durMin/60);
                        $durStr = $durH.'h';
                    @endphp
                    <tr class="hor-row"
                        data-grupo="{{ strtolower($g['grupo'].' '.$g['codigo']) }}"
                        data-programa="{{ strtolower($g['programa']) }}"
                        data-escuela="{{ $g['escuela'] }}"
                        data-jornada="{{ $g['jornada'] }}"
                        data-docente="{{ strtolower($g['docente']) }}">
                        <td>
                            <div class="hor-cell-grupo">
                                <span class="hor-grupo-txt">{{ $g['grupo'] }}</span>
                                <span class="hor-codigo-txt">{{ $g['codigo'] }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="hor-cell-programa">
                                <span class="hor-programa-txt">{{ Str::limit($g['programa'], 42) }}</span>
                                <span class="hor-esc-tag hor-esc-{{ $escCls }}">{{ $g['escuela'] }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="hor-dias-wrap">
                                @foreach($todosDias as $d)
                                    <span class="hor-dia-chip {{ in_array($d,$g['dias']) ? 'activo' : '' }}">{{ $diasCodes[$d] }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <div class="hor-horario-cell">
                                <span class="hor-hora-rng">{{ $g['hora_inicio'] }} – {{ $g['hora_fin'] }}</span>
                                <span class="hor-duracion">{{ $durStr }} por sesión</span>
                            </div>
                        </td>
                        <td>
                            <span class="hor-jornada-tag hor-jornada-{{ $jorCls }}">{{ $g['jornada'] }}</span>
                        </td>
                        <td style="font-size:0.82rem; color:var(--text);">{{ $g['docente'] }}</td>
                        <td>
                            <button class="hor-action-btn hor-btn-edit" type="button" data-id="{{ $g['id'] }}" title="Editar">
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Empty state --}}
            <div class="hor-empty" id="hor-empty" style="display:none;">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span class="hor-empty-ttl">Sin resultados</span>
                <span class="hor-empty-sub">No hay horarios que coincidan con los filtros.</span>
            </div>
        </div>
    </div>

    {{-- ── Vista GRILLA ── --}}
    <div id="hor-view-grilla" style="display:none;">
        <div class="hor-grilla-wrap">
            <div class="hor-grilla" id="hor-grilla">
                {{-- Cabecera días --}}
                <div class="hor-grilla-head-cell hor-time-header"></div>
                @foreach(['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] as $d)
                    <div class="hor-grilla-head-cell">{{ Str::upper(Str::substr($d,0,3)) }}</div>
                @endforeach
                {{-- Filas generadas por JS --}}
            </div>
        </div>
    </div>

</div>

{{-- ══ Modal crear/editar horario ══ --}}
<div class="hor-modal-overlay" id="hor-modal-overlay">
    <div class="hor-modal" role="dialog" aria-modal="true" aria-labelledby="hor-modal-title">

        <div class="hor-modal-head">
            <span class="hor-modal-title" id="hor-modal-title">Nuevo horario</span>
            <button class="hor-modal-close" id="hor-modal-close" type="button" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="hor-modal-body">

            {{-- Grupo --}}
            <div class="hor-form-group">
                <label class="hor-form-lbl">Grupo</label>
                <div class="app-combobox" id="hor-cb-grupo-modal" data-position="absolute" data-list-max-height="160">
                    <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                        <span data-combobox-label>Seleccionar grupo</span>
                        <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="app-combobox-panel" data-combobox-panel hidden>
                        <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                    </div>
                    <input type="hidden" id="hor-val-grupo-modal" data-combobox-value>
                </div>
            </div>

            {{-- Programa y Escuela (readonly, se llenan al elegir grupo) --}}
            <div class="hor-form-row">
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Programa</label>
                    <input class="hor-form-input" id="hor-inp-programa" type="text" placeholder="—" readonly style="background:#F8FAFC; cursor:default;">
                </div>
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Escuela</label>
                    <input class="hor-form-input" id="hor-inp-escuela" type="text" placeholder="—" readonly style="background:#F8FAFC; cursor:default;">
                </div>
            </div>

            {{-- Días --}}
            <div class="hor-form-group">
                <label class="hor-form-lbl">Días de clase</label>
                <div class="hor-dias-grid">
                    @foreach([['L','Lunes'],['M','Martes'],['Mi','Miércoles'],['J','Jueves'],['V','Viernes'],['S','Sábado']] as [$code,$name])
                    <input class="hor-dia-cb" type="checkbox" id="hor-dia-{{ $code }}" name="dias" value="{{ $name }}">
                    <label class="hor-dia-lbl" for="hor-dia-{{ $code }}">{{ $code }}</label>
                    @endforeach
                </div>
            </div>

            {{-- Horas y Jornada --}}
            <div class="hor-form-row">
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Hora inicio</label>
                    <input class="hor-form-input" id="hor-inp-hinicio" type="time" value="07:00">
                </div>
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Hora fin</label>
                    <input class="hor-form-input" id="hor-inp-hfin" type="time" value="11:00">
                </div>
            </div>

            {{-- Jornada (auto) --}}
            <div class="hor-form-row">
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Jornada</label>
                    <input class="hor-form-input" id="hor-inp-jornada" type="text" placeholder="Se calcula automáticamente" readonly style="background:#F8FAFC; cursor:default;">
                </div>
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Docente</label>
                    <div class="app-combobox" id="hor-cb-docente-modal" data-position="absolute" data-list-max-height="140">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                            <span data-combobox-label>Seleccionar docente</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" id="hor-val-docente-modal" data-combobox-value>
                    </div>
                </div>
            </div>

        </div>

        <div class="hor-modal-foot">
            <button class="hor-btn hor-btn-ghost" id="hor-modal-cancel" type="button">Cancelar</button>
            <button class="hor-btn hor-btn-primary" id="hor-modal-save" type="button">Guardar horario</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>var HOR_DATA = @json($grupos);</script>
<script src="{{ asset('assets/js/admin/horarios.js') }}?v={{ filemtime(public_path('assets/js/admin/horarios.js')) }}" defer></script>
@endpush
