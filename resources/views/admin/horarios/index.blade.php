{{-- mock --}}
@extends('admin.layout')

@section('title', 'Horarios')
@section('page-title', 'Horarios')
@section('active', 'horarios')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/horarios.css') }}?v={{ filemtime(public_path('assets/css/admin/horarios.css')) }}">
@endpush

@php
/* ── Datos mock (ficticios): cada grupo tiene sus sesiones semanales.
      Una sesión = día + hora inicio + hora fin, así un grupo puede tener
      horas distintas según el día (p. ej. lunes mañana, jueves tarde). ── */
$ses = fn(array $dias, string $hi, string $hf) => array_map(
    fn($d) => ['dia' => $d, 'hora_inicio' => $hi, 'hora_fin' => $hf], $dias
);

$grupos = [
    ['id'=>1,'codigo'=>'CUR-SAL-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud',
     'docente'=>'María González Ruiz','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>$ses(['Lunes','Miércoles','Viernes'],'08:00','12:00')],
    ['id'=>2,'codigo'=>'CUR-SAL-001-B','grupo'=>'Grupo B',
     'programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud',
     'docente'=>'Alejandro Ríos Mora','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>$ses(['Martes','Jueves'],'14:00','18:00')],
    ['id'=>3,'codigo'=>'CUR-SAL-002-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Auxiliar de Psiquiatría','escuela'=>'Salud',
     'docente'=>'María González Ruiz','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>$ses(['Lunes','Miércoles','Viernes'],'14:00','18:00')],
    ['id'=>4,'codigo'=>'CUR-TUR-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo',
     'docente'=>'Laura Martínez Peña','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>array_merge($ses(['Lunes','Martes'],'07:00','11:00'), $ses(['Jueves'],'14:00','17:00'))],
    ['id'=>5,'codigo'=>'CUR-TUR-001-B','grupo'=>'Grupo B',
     'programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo',
     'docente'=>'Andrés Zapata Villa','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>$ses(['Lunes','Miércoles','Viernes'],'13:00','17:00')],
    ['id'=>6,'codigo'=>'CUR-TUR-002-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Sommelier y Enología','escuela'=>'Cocina y Turismo',
     'docente'=>'Andrés Zapata Villa','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>array_merge($ses(['Martes','Jueves'],'18:00','21:00'), $ses(['Sábado'],'08:00','12:00'))],
    ['id'=>7,'codigo'=>'CUR-ADM-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas','escuela'=>'Administrativa',
     'docente'=>'Patricia López Castro','modalidad'=>'Mixta','estado'=>'activo',
     'sesiones'=>$ses(['Lunes','Miércoles','Viernes'],'08:00','12:00')],
    ['id'=>8,'codigo'=>'CUR-ADM-002-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa',
     'docente'=>'Roberto Díaz Sierra','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>array_merge($ses(['Martes'],'14:00','18:00'), $ses(['Jueves'],'18:00','21:00'))],
    ['id'=>9,'codigo'=>'CUR-EDU-001-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Asistente de Preescolar','escuela'=>'Educación e Idiomas',
     'docente'=>'Diana Vargas Nieto','modalidad'=>'Presencial','estado'=>'activo',
     'sesiones'=>$ses(['Lunes','Martes','Miércoles','Jueves','Viernes'],'07:00','10:00')],
    ['id'=>10,'codigo'=>'CUR-SAL-003-A','grupo'=>'Grupo A',
     'programa'=>'Técnico Laboral en Seguridad Ocupacional y Laboral','escuela'=>'Salud',
     'docente'=>'Alejandro Ríos Mora','modalidad'=>'Presencial','estado'=>'planificacion',
     'sesiones'=>array_merge($ses(['Lunes','Miércoles'],'18:00','22:00'), $ses(['Sábado'],'07:00','11:00'))],
];

$jornadaDe = fn(string $h) => ((int) $h < 12) ? 'Mañana' : (((int) $h < 18) ? 'Tarde' : 'Noche');
$tieneJornada = fn(array $g, string $j) => count(array_filter($g['sesiones'], fn($s) => $jornadaDe($s['hora_inicio']) === $j)) > 0;

$total   = count($grupos);
$manana  = count(array_filter($grupos, fn($g) => $tieneJornada($g, 'Mañana')));
$tarde   = count(array_filter($grupos, fn($g) => $tieneJornada($g, 'Tarde')));
$noche   = count(array_filter($grupos, fn($g) => $tieneJornada($g, 'Noche')));
@endphp

@section('content')
<div class="hor-wrap">

    {{-- Encabezado --}}
    <div>
        <div class="hor-header-top">
            <h2 class="hor-title">Horarios</h2>
            <button class="hor-btn hor-btn-primary" id="hor-btn-nuevo" type="button">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo horario
            </button>
        </div>
        <p class="hor-sub">Aquí podrás asignar los días y las horas de clase de cada grupo, con horarios distintos según el día si lo necesitas, verificar que ningún docente quede con dos clases a la misma hora y consultar el horario semanal por grupo, por docente o el general de la sede.</p>
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
                <div class="hor-kpi-val" id="hor-kpi-manana">{{ $manana }}</div>
                <div class="hor-kpi-lbl">Jornada mañana</div>
            </div>
        </div>
        <div class="hor-kpi-card">
            <div class="hor-kpi-icon hor-kpi-icon-green">
                <svg viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/></svg>
            </div>
            <div>
                <div class="hor-kpi-val" id="hor-kpi-tarde">{{ $tarde }}</div>
                <div class="hor-kpi-lbl">Jornada tarde</div>
            </div>
        </div>
        <div class="hor-kpi-card">
            <div class="hor-kpi-icon hor-kpi-icon-purple">
                <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </div>
            <div>
                <div class="hor-kpi-val" id="hor-kpi-noche">{{ $noche }}</div>
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
            <div class="app-combobox" id="hor-cb-escuela">
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
            <div class="app-combobox" id="hor-cb-jornada">
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
            <button class="hor-view-btn" id="hor-btn-grilla" type="button" title="Horario semanal por grupo o docente">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Horario
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
                {{-- Filas generadas por horarios.js desde HOR_DATA (así "Guardar" se refleja al instante) --}}
                <tbody id="hor-tbody"></tbody>
            </table>

            {{-- Empty state --}}
            <div class="hor-empty" id="hor-empty" style="display:none;">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span class="hor-empty-ttl">Sin resultados</span>
                <span class="hor-empty-sub">No hay horarios que coincidan con los filtros.</span>
            </div>
        </div>
    </div>

    {{-- ── Vista HORARIO: horario clásico de un grupo o de un docente ── --}}
    <div id="hor-view-grilla" style="display:none;">
        <div class="hor-cal-card" id="hor-cal-card">

            <div class="hor-cal-top">
                <div class="hor-cal-modo" role="tablist" aria-label="Ver horario">
                    <button type="button" class="hor-cal-modo-btn is-active" data-modo="grupo" role="tab" aria-selected="true">Por grupo</button>
                    <button type="button" class="hor-cal-modo-btn" data-modo="docente" role="tab" aria-selected="false">Por docente</button>
                </div>

                <div class="hor-cal-sel">
                    <button type="button" class="hor-cal-flecha" id="hor-cal-prev" aria-label="Anterior">
                        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <div class="app-combobox hor-cal-cb" id="hor-cb-ver">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                            <span data-combobox-label>Seleccionar</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" id="hor-val-ver" data-combobox-value>
                    </div>
                    <button type="button" class="hor-cal-flecha" id="hor-cal-next" aria-label="Siguiente">
                        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>

            {{-- Encabezado del horario (generado por horarios.js) --}}
            <div class="hor-cal-info" id="hor-cal-info"></div>

            {{-- Tabla días × horas (generada por horarios.js) --}}
            <div class="hor-cal" id="hor-grilla"></div>

            <div class="hor-empty" id="hor-cal-empty" hidden>
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span class="hor-empty-ttl">Sin horarios para mostrar</span>
                <span class="hor-empty-sub">Ningún grupo o docente coincide con los filtros.</span>
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
                <div class="app-combobox" id="hor-cb-grupo-modal">
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

            {{-- Días y horas de clase --}}
            <div class="hor-form-group">
                <div class="hor-ses-head">
                    <label class="hor-form-lbl">Días de clase</label>
                    <label class="hor-switch" for="hor-mismo-horario">
                        <input type="checkbox" id="hor-mismo-horario" checked>
                        <span class="hor-switch-track" aria-hidden="true"><span class="hor-switch-thumb"></span></span>
                        <span class="hor-switch-txt">Mismo horario todos los días</span>
                    </label>
                </div>
                <div class="hor-dias-grid">
                    @foreach([['L','Lunes'],['M','Martes'],['Mi','Miércoles'],['J','Jueves'],['V','Viernes'],['S','Sábado']] as [$code,$name])
                    <input class="hor-dia-cb" type="checkbox" id="hor-dia-{{ $code }}" name="dias" value="{{ $name }}">
                    <label class="hor-dia-lbl" for="hor-dia-{{ $code }}" title="{{ $name }}">{{ $code }}</label>
                    @endforeach
                </div>
            </div>

            {{-- Modo 1: un solo rango de horas para todos los días marcados --}}
            <div class="hor-form-row" id="hor-horas-comunes">
                <div class="hor-form-group">
                    <label class="hor-form-lbl" for="hor-inp-hinicio">Hora inicio</label>
                    <input class="hor-form-input" id="hor-inp-hinicio" type="time" value="07:00">
                </div>
                <div class="hor-form-group">
                    <label class="hor-form-lbl" for="hor-inp-hfin">Hora fin</label>
                    <input class="hor-form-input" id="hor-inp-hfin" type="time" value="11:00">
                </div>
            </div>

            {{-- Modo 2: horas distintas por día (filas generadas por JS) --}}
            <div class="hor-form-group" id="hor-horas-por-dia" hidden>
                <label class="hor-form-lbl">Horas por día</label>
                <div class="hor-ses-list" id="hor-ses-list"></div>
                <p class="hor-ses-vacio" id="hor-ses-vacio">Marca los días de clase para asignar sus horas.</p>
            </div>

            <p class="hor-ses-resumen" id="hor-ses-resumen"></p>

            {{-- Jornada (auto) --}}
            <div class="hor-form-row">
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Jornada</label>
                    <input class="hor-form-input" id="hor-inp-jornada" type="text" placeholder="Se calcula automáticamente" readonly style="background:#F8FAFC; cursor:default;">
                </div>
                <div class="hor-form-group">
                    <label class="hor-form-lbl">Docente</label>
                    <div class="app-combobox" id="hor-cb-docente-modal">
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

        <div class="hor-form-error" id="hor-form-error" role="alert" hidden></div>

        <div class="hor-modal-foot">
            <button class="hor-btn hor-btn-ghost" id="hor-modal-cancel" type="button">Cancelar</button>
            <button class="hor-btn hor-btn-primary" id="hor-modal-save" type="button">Guardar horario</button>
        </div>
    </div>
</div>

{{-- Aviso al guardar --}}
<div class="hor-toast" id="hor-toast" role="status" aria-live="polite" hidden></div>

@endsection

@push('scripts')
<script>var HOR_DATA = @json($grupos);</script>
<script src="{{ asset('assets/js/admin/horarios.js') }}?v={{ filemtime(public_path('assets/js/admin/horarios.js')) }}" defer></script>
@endpush
