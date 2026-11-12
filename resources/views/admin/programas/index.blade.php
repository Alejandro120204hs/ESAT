{{-- Programas y escuelas de la sede: datos reales desde Admin\Programas\ProgramaController. Decidido con el usuario: el
     catálogo (programas, malla, precios y escuelas) lo maneja el Admin de ESAT; la secretaría solo elige qué programas del
     catálogo ofrece su sede (agregar / dejar de ofrecer, no con grupos vigentes). --}}
@extends('admin.layout')

@section('title', 'Programas')
@section('page-title', 'Programas')
@section('active', 'programas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/programas.css') }}?v={{ filemtime(public_path('assets/css/admin/programas.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin/escuelas.css') }}?v={{ filemtime(public_path('assets/css/admin/escuelas.css')) }}">
@endpush

@php
/* Datos reales (ProgramaController@index): programas ofertados en la sede del admin. */
$programas   = collect($programas);
$totalEst    = $programas->sum('estudiantes');
$totalGrupos = $programas->sum(fn ($p) => count($p['grupos']));
$escFiltro   = $programas->pluck('escuela')->unique()->sort()->values();
$escuelasData = $escuelas->map(fn ($e) => [
    'id' => $e->id, 'nombre' => $e->nombre, 'slug' => mb_strtolower($e->sigla), 'programas' => $e->programas_sede,
])->values();
@endphp

@section('content')
<div class="prg-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="prg-header">
        <div class="prg-header-top">
            <h2 class="prg-title" id="prg-main-title">Programas académicos</h2>
            <div style="display:flex;gap:8px;">
                <button class="prg-btn prg-btn-primary" id="btn-catalogo">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Agregar del catálogo
                </button>
            </div>
        </div>
        <p class="prg-sub" id="prg-main-sub">Los programas que ofrece tu sede. El catálogo, la malla y los precios los maneja el Admin de ESAT: aquí eliges cuáles ofrece tu sede.</p>
    </div>

    {{-- Tabs --}}
    <div class="prg-tabs">
        <button class="prg-tab-btn is-active" data-tab="programas">Programas</button>
        <button class="prg-tab-btn" data-tab="escuelas">Escuelas</button>
    </div>

    {{-- Tab: Programas --}}
    <div id="tab-programas" class="prg-page-panel is-active">

    {{-- KPI chips --}}
    <div class="prg-kpis">
        <div class="prg-kpi-chip prg-kpi-blue">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ $programas->count() }}</div>
                <div class="prg-kpi-lbl">Programas</div>
            </div>
        </div>
        <div class="prg-kpi-chip prg-kpi-orange">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ $totalEst }}</div>
                <div class="prg-kpi-lbl">Estudiantes</div>
            </div>
        </div>
        <div class="prg-kpi-chip prg-kpi-green">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ $totalGrupos }}</div>
                <div class="prg-kpi-lbl">Grupos activos</div>
            </div>
        </div>
        <div class="prg-kpi-chip prg-kpi-navy">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ $escuelas->count() }}</div>
                <div class="prg-kpi-lbl">Escuelas</div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="prg-filters">
        <div class="prg-search-box">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="prg-search" placeholder="Buscar programa..." autocomplete="off">
        </div>
        <div class="app-combobox" id="prg-cb-filter-escuela" data-position="absolute"
             data-options='@json($escFiltro)'>
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todas las escuelas</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="prg-filter-escuela" data-combobox-value>
        </div>
        <div class="app-combobox" id="prg-cb-filter-estado" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todos los estados</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="prg-filter-estado" data-combobox-value>
        </div>
        <span class="prg-count-badge" id="prg-count">{{ $programas->count() }} programas</span>
    </div>

    {{-- Tabla --}}
    <div class="prg-table-wrap">
        <table class="prg-table" id="prg-table">
            <thead>
                <tr>
                    <th>Programa</th>
                    <th>Escuela</th>
                    <th>Duración</th>
                    <th>Modalidad</th>
                    <th>Estudiantes</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($programas as $p)
                @php $esc = $p['sigla']; @endphp
                <tr class="prg-row"
                    data-nombre="{{ mb_strtolower($p['nombre']) }}"
                    data-escuela="{{ $p['escuela'] }}"
                    data-estado="{{ $p['estado'] }}"
                    data-id="{{ $p['id'] }}">
                    <td>
                        <div class="prg-prog-cell">
                            <div class="prg-prog-avatar prg-esc-{{ $esc }}">
                                {{ mb_strtoupper(mb_substr($p['escuela'],0,2)) }}
                            </div>
                            <div>
                                <div class="prg-prog-name">{{ $p['nombre'] }}</div>
                                <div class="prg-prog-code">{{ $p['codigo'] }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="prg-esc-tag prg-esc-{{ $esc }}">{{ $p['escuela'] }}</span></td>
                    <td class="prg-dur-cell">
                        <strong>{{ number_format($p['horas']) }}</strong> h
                        <span class="prg-dur-meses">· {{ $p['meses'] }} meses</span>
                    </td>
                    <td>{{ $p['modalidad'] }}</td>
                    <td>
                        <div class="prg-est-cell">
                            <strong>{{ $p['estudiantes'] }}</strong>
                            <div class="prg-est-bar">
                                <div class="prg-est-fill" style="width:{{ min(100, round($p['estudiantes'] / max(1, $p['cupo'] * max(1, count($p['grupos']))) * 100)) }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($p['estado']==='activo')
                            <span class="app-status-tag app-status-active">Activo</span>
                        @elseif($p['estado']==='en_aprobacion')
                            <span class="app-status-tag app-status-warn">En aprobación</span>
                        @else
                            <span class="app-status-tag app-status-muted">Inactivo</span>
                        @endif
                    </td>
                    <td class="prg-actions-cell">
                        <button class="prg-icon-btn" title="Ver detalle" data-id="{{ $p['id'] }}" data-action="ver">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="prg-empty" id="prg-empty" style="display:none">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <p>{{ $programas->isEmpty() ? 'Tu sede todavía no ofrece programas. Elígelos con «Agregar del catálogo».' : 'No hay programas que coincidan con la búsqueda' }}</p>
        </div>
    </div>

    {{-- Paginación --}}
    <div class="prg-pagination" id="prg-pagination">
        <button class="prg-pg-btn prg-pg-prev" id="prg-pg-prev" disabled>
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="prg-pg-pages" id="prg-pg-pages"></div>
        <button class="prg-pg-btn prg-pg-next" id="prg-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </div>

    </div>{{-- /tab-programas --}}

    {{-- Tab: Escuelas --}}
    <div id="tab-escuelas" class="prg-page-panel">

        <div class="esc-grid">
            @foreach($escuelasData as $e)
            <div class="esc-card" data-id="{{ $e['id'] }}">
                <div class="esc-card-avatar prg-prog-avatar prg-esc-{{ $e['slug'] }}">
                    {{ mb_strtoupper(mb_substr($e['nombre'], 0, 2)) }}
                </div>
                <div class="esc-card-body">
                    <div class="esc-card-name">{{ $e['nombre'] }}</div>
                    <div class="esc-card-meta">{{ $e['programas'] }} {{ $e['programas'] === 1 ? 'programa' : 'programas' }}</div>
                </div>
            </div>
            @endforeach
        </div>

    </div>{{-- /tab-escuelas --}}

</div>{{-- /prg-wrap --}}

{{-- ═══════════════════════════════════════════════════════
     DRAWER — Detalle de programa
     ═══════════════════════════════════════════════════════ --}}
<div class="prg-drawer" id="prg-drawer" aria-hidden="true">
    <div class="prg-drawer-overlay" id="prg-drawer-overlay"></div>
    <div class="prg-drawer-panel" role="dialog" aria-modal="true">

        {{-- Cabecera --}}
        <div class="prg-drawer-head">
            <div class="prg-drawer-head-info">
                <span class="prg-drawer-code" id="dw-codigo"></span>
                <h3 class="prg-drawer-title" id="dw-nombre"></h3>
                <div id="dw-badges" class="prg-drawer-badges"></div>
            </div>
            <button class="prg-close-btn" id="prg-drawer-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Tabs --}}
        <div class="prg-dtabs" role="tablist">
            <button class="prg-dtab is-active" data-tab="general" role="tab">General</button>
            <button class="prg-dtab" data-tab="plan" role="tab">Plan de estudios</button>
            <button class="prg-dtab" data-tab="grupos" role="tab">Grupos</button>
            <button class="prg-dtab" data-tab="estudiantes" role="tab">Estudiantes</button>
            <button class="prg-dtab" data-tab="documentos" role="tab">Documentos</button>
        </div>

        {{-- Cuerpo --}}
        <div class="prg-drawer-body">

            {{-- Tab General --}}
            <div class="prg-tab-panel is-active" data-panel="general">
                <div class="prg-info-grid">
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Escuela</span>
                        <span class="prg-info-val" id="dw-escuela"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Nivel</span>
                        <span class="prg-info-val" id="dw-nivel"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Modalidad</span>
                        <span class="prg-info-val" id="dw-modalidad"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Duración</span>
                        <span class="prg-info-val" id="dw-duracion"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Jornadas</span>
                        <span class="prg-info-val" id="dw-jornadas"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Cupo por grupo</span>
                        <span class="prg-info-val" id="dw-cupo"></span>
                    </div>
                    <div class="prg-info-item prg-info-full">
                        <span class="prg-info-lbl">Resolución de aprobación</span>
                        <span class="prg-info-val" id="dw-resolucion"></span>
                    </div>
                    <div class="prg-info-item prg-info-full">
                        <span class="prg-info-lbl">Descripción</span>
                        <span class="prg-info-val prg-info-text" id="dw-descripcion"></span>
                    </div>
                    <div class="prg-info-item prg-info-full">
                        <span class="prg-info-lbl">Perfil del egresado</span>
                        <span class="prg-info-val prg-info-text" id="dw-perfil"></span>
                    </div>
                </div>
                <div class="prg-costos-row prg-costos-2col">
                    <div class="prg-costo-card">
                        <div class="prg-costo-ico"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                        <div class="prg-costo-lbl">Valor del programa</div>
                        <div class="prg-costo-val" id="dw-valor-programa"></div>
                    </div>
                    <div class="prg-costo-card">
                        <div class="prg-costo-ico"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
                        <div class="prg-costo-lbl">Matrícula (pago inicial)</div>
                        <div class="prg-costo-val" id="dw-matricula"></div>
                    </div>
                </div>
                <div class="prg-planes-section">
                    <div class="prg-planes-title">Valor mensual del sistema</div>
                    <div class="prg-plan-row">
                        <span class="prg-plan-nombre">Cuota mensual (igual para todos los programas)</span>
                        <span class="prg-plan-detalle" id="dw-valor-mensual"></span>
                    </div>
                    <div class="prg-plan-row">
                        <span class="prg-plan-nombre">Periodos del programa</span>
                        <span class="prg-plan-detalle" id="dw-periodos"></span>
                    </div>
                    <div class="prg-planes-nota">El estudiante elige si paga mensual o por periodo al matricularse.</div>
                </div>
            </div>

            {{-- Tab Plan de estudios --}}
            <div class="prg-tab-panel" data-panel="plan">
                <div class="prg-plan-header">
                    <span>Plan de estudios</span>
                    <span class="prg-plan-total" id="dw-horas-total"></span>
                </div>
                <table class="prg-modulos-table">
                    <thead>
                        <tr><th>#</th><th>Módulo / Asignatura</th><th>Horas</th><th>Docente asignado</th></tr>
                    </thead>
                    <tbody id="dw-modulos-body"></tbody>
                </table>
            </div>

            {{-- Tab Grupos --}}
            <div class="prg-tab-panel" data-panel="grupos">
                <div id="dw-grupos-grid" class="prg-grupos-grid"></div>
            </div>

            {{-- Tab Estudiantes --}}
            <div class="prg-tab-panel" data-panel="estudiantes">
                <div class="prg-placeholder">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <p id="dw-est-txt"></p>
                    <span>Vista detallada disponible en el módulo Estudiantes</span>
                </div>
            </div>

            {{-- Tab Documentos --}}
            <div class="prg-tab-panel" data-panel="documentos">
                <div class="prg-docs-list">
                    <div class="prg-doc-item">
                        <div class="prg-doc-ico">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="prg-doc-info">
                            <div class="prg-doc-name">Resolución de aprobación</div>
                            <div class="prg-doc-meta" id="dw-doc-res"></div>
                        </div>
                        <span class="prg-doc-badge">PDF</span>
                    </div>
                    <div class="prg-doc-item">
                        <div class="prg-doc-ico">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="prg-doc-info">
                            <div class="prg-doc-name">Pensum académico</div>
                            <div class="prg-doc-meta">Plan de estudios completo</div>
                        </div>
                        <span class="prg-doc-badge">PDF</span>
                    </div>
                    <div class="prg-doc-item">
                        <div class="prg-doc-ico">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="prg-doc-info">
                            <div class="prg-doc-name">Formato de matrícula</div>
                            <div class="prg-doc-meta">Formulario para inscripción de estudiantes</div>
                        </div>
                        <span class="prg-doc-badge">PDF</span>
                    </div>
                    <button class="prg-doc-add">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Subir documento
                    </button>
                </div>
            </div>

        </div>{{-- /prg-drawer-body --}}

        {{-- Footer del drawer --}}
        <div class="prg-drawer-foot">
            <button class="prg-btn prg-btn-danger" id="prg-drawer-quitar-btn">
                Dejar de ofrecer en mi sede
            </button>
        </div>

    </div>{{-- /prg-drawer-panel --}}
</div>

{{-- Agregar del catálogo: programas activos que la sede todavía no ofrece --}}
<div class="esc-modal-backdrop" id="prg-cat-modal" hidden>
    <div class="esc-modal-box prg-cat-box" role="dialog" aria-modal="true" aria-labelledby="prg-cat-titulo">
        <div class="esc-modal-head">
            <span class="esc-modal-title" id="prg-cat-titulo">Agregar del catálogo de ESAT</span>
            <button class="esc-modal-close" data-cerrar-cat aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p class="esc-modal-texto">El precio y la malla son los mismos en todas las sedes. Al agregarlo, tu sede ya puede abrir grupos del programa.</p>
        <ul class="prg-cat-lista">
            @forelse($catalogo as $c)
                <li>
                    <div>
                        <div class="prg-prog-name">{{ $c['nombre'] }}</div>
                        <div class="prg-prog-code">{{ $c['codigo'] }} · {{ $c['escuela'] }} · {{ $c['meses'] }} meses · $ {{ number_format($c['precio_total'], 0, ',', '.') }}</div>
                    </div>
                    <button type="button" class="prg-btn prg-btn-secondary" data-ofrecer="{{ $c['id'] }}">Ofrecer en mi sede</button>
                </li>
            @empty
                <li class="prg-cat-vacio">Tu sede ya ofrece todos los programas activos del catálogo.</li>
            @endforelse
        </ul>
        <div class="esc-modal-foot">
            <button class="prg-btn prg-btn-ghost" data-cerrar-cat>Cerrar</button>
        </div>
    </div>
</div>
{{-- Aviso al guardar --}}
<div class="prg-toast" id="prg-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
    var PRG_DATA = @json($programas);
    var ESC_DATA = @json($escuelasData);
    var PRG_CUOTA = {{ (float) $cuotaSistema }};
</script>
<script src="{{ asset('assets/js/admin/programas.js') }}?v={{ filemtime(public_path('assets/js/admin/programas.js')) }}" defer></script>
<script src="{{ asset('assets/js/admin/escuelas.js') }}?v={{ filemtime(public_path('assets/js/admin/escuelas.js')) }}" defer></script>
@endpush
