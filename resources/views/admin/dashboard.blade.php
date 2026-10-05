{{-- mock --}}
@extends('admin.layout')

@section('title', 'Inicio')
@section('page-title', 'Inicio')
@section('active', 'inicio')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/dashboard.css') }}?v={{ filemtime(public_path('assets/css/admin/dashboard.css')) }}">
@endpush

@php
    $nombreAdmin = auth()->user()->nombres ?? auth()->user()->name ?? 'Administrador';
    $saludo = 'Hola';
    $sedeName = auth()->user()->sede?->nombre ?? 'sede';

    /* mock data */
    $kpis = [
        ['label' => 'Estudiantes activos',   'value' => 342,  'delta' => '+12%',  'dir' => 'up',   'foot' => '28 nuevos este mes',     'icon' => 'users',   'color' => 'blue'],
        ['label' => 'Ingresos del mes',       'value' => 18450,'delta' => '+8%',   'dir' => 'up',   'foot' => 'vs. mes anterior',       'icon' => 'money',   'color' => 'green',  'prefix' => '$', 'suffix' => 'K'],
        ['label' => 'Cursos activos',         'value' => 24,   'delta' => '+3',    'dir' => 'up',   'foot' => 'en 4 programas',         'icon' => 'book',    'color' => 'orange'],
        ['label' => 'Pagos pendientes',       'value' => 17,   'delta' => '-5',    'dir' => 'down', 'foot' => 'requieren seguimiento',  'icon' => 'alert',   'color' => 'warn'],
    ];

    $meses = [
        ['mes' => 'Abr', 'val' => 38, 'pct' => 52],
        ['mes' => 'May', 'val' => 45, 'pct' => 62],
        ['mes' => 'Jun', 'val' => 41, 'pct' => 57],
        ['mes' => 'Jul', 'val' => 56, 'pct' => 77],
        ['mes' => 'Ago', 'val' => 62, 'pct' => 85],
        ['mes' => 'Sep', 'val' => 73, 'pct' => 100, 'active' => true],
    ];

    $actividad = [
        ['dot' => 'orange', 'ini' => 'LM', 'texto' => '<strong>Laura Martínez</strong> se inscribió en Administración de Empresas', 'time' => 'Hace 15 min'],
        ['dot' => 'blue',   'ini' => 'CR', 'texto' => '<strong>Carlos Ruiz</strong> realizó pago de matrícula $1,250,000',            'time' => 'Hace 42 min'],
        ['dot' => 'green',  'ini' => 'AP', 'texto' => 'Docente <strong>Andrea Pérez</strong> publicó material en Contabilidad I',    'time' => 'Hace 1 h'],
        ['dot' => 'red',    'ini' => 'MG', 'texto' => '<strong>Miguel García</strong> tiene 2 cuotas vencidas',                     'time' => 'Hace 2 h'],
        ['dot' => 'blue',   'ini' => 'JP', 'texto' => '<strong>Juan Patiño</strong> completó el módulo Finanzas Básicas',            'time' => 'Hace 3 h'],
    ];

    $programas = [
        ['nombre' => 'Administración de Empresas', 'pct' => 87, 'sub' => '42 estudiantes · 6 cursos', 'color' => 'blue'],
        ['nombre' => 'Contabilidad y Finanzas',     'pct' => 72, 'sub' => '31 estudiantes · 5 cursos', 'color' => 'orange'],
        ['nombre' => 'Marketing Digital',           'pct' => 58, 'sub' => '24 estudiantes · 4 cursos', 'color' => 'green'],
        ['nombre' => 'Recursos Humanos',            'pct' => 43, 'sub' => '18 estudiantes · 3 cursos', 'color' => 'blue'],
    ];

    $tareas = [
        ['titulo' => 'Reunión docentes',        'meta' => 'Hoy 3:00 PM',            'tag' => 'Urgente', 'color' => 'orange'],
        ['titulo' => 'Revisar pagos oct.',      'meta' => 'Mañana',                  'tag' => 'Pagos',   'color' => 'blue'],
        ['titulo' => 'Abrir inscripciones II',  'meta' => 'Oct 15',                  'tag' => 'Académico','color' => 'green'],
        ['titulo' => 'Informe mensual sede',    'meta' => 'Oct 30',                  'tag' => 'Reporte', 'color' => 'blue'],
    ];

    $topEstudiantes = [
        ['ini' => 'LM', 'nombre' => 'Laura Martínez',   'programa' => 'Adm. Empresas',  'nota' => '4.9', 'rank' => 1],
        ['ini' => 'CR', 'nombre' => 'Carlos Ruiz',       'programa' => 'Cont. Finanzas', 'nota' => '4.8', 'rank' => 2],
        ['ini' => 'AP', 'nombre' => 'Andrea Pérez',      'programa' => 'Marketing Dig.', 'nota' => '4.7', 'rank' => 3],
        ['ini' => 'DS', 'nombre' => 'Diana Salazar',     'programa' => 'Rec. Humanos',   'nota' => '4.6', 'rank' => 4],
        ['ini' => 'MO', 'nombre' => 'Mateo Ospina',      'programa' => 'Adm. Empresas',  'nota' => '4.5', 'rank' => 5],
    ];
@endphp

@section('content')

{{-- ─── Strip de bienvenida ─────────────────────────── --}}
<div class="dash-welcome-strip adm-anim">
    <img class="dash-welcome-mark" src="{{ asset('assets/img/favicon.png') }}" alt="">
    <div class="dash-welcome-text">
        <div class="dash-welcome-title">
            {{ $saludo }}, <span>{{ $nombreAdmin }}.</span>
        </div>
        <div class="dash-welcome-desc">
            Panel de gestión de la sede <strong style="color:rgba(255,255,255,0.85);">{{ $sedeName }}</strong>.
            Aquí tienes un resumen de lo que está pasando hoy con tus estudiantes, programas y pagos.
        </div>
    </div>
    <div class="dash-welcome-badge">
        <div class="dash-welcome-badge-num">{{ now()->locale('es')->translatedFormat('d') }}</div>
        <div class="dash-welcome-badge-label">{{ now()->locale('es')->translatedFormat('M Y') }}</div>
    </div>
</div>

{{-- ─── KPI tiles ────────────────────────────────────── --}}
<div class="dash-kpi-grid">
    @foreach ($kpis as $i => $kpi)
    <div class="dash-kpi dash-kpi-{{ $kpi['color'] }} adm-anim adm-anim-d{{ $i + 1 }}">
        <div class="dash-kpi-head">
            <div class="dash-kpi-icon dash-kpi-icon-{{ $kpi['color'] }}">
                @if ($kpi['icon'] === 'users')
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                @elseif ($kpi['icon'] === 'money')
                    <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                @elseif ($kpi['icon'] === 'book')
                    <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                @else
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                @endif
            </div>
            <span class="dash-kpi-delta dash-kpi-delta-{{ $kpi['dir'] }}">
                @if ($kpi['dir'] === 'up')
                    <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
                @else
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                @endif
                {{ $kpi['delta'] }}
            </span>
        </div>
        <div class="dash-kpi-label">{{ $kpi['label'] }}</div>
        <div class="dash-kpi-value"
             data-target="{{ $kpi['value'] }}"
             data-prefix="{{ $kpi['prefix'] ?? '' }}"
             data-suffix="{{ $kpi['suffix'] ?? '' }}">
            {{ ($kpi['prefix'] ?? '') . number_format($kpi['value'], 0, ',', '.') . ($kpi['suffix'] ?? '') }}
        </div>
        <div class="dash-kpi-foot">{{ $kpi['foot'] }}</div>
    </div>
    @endforeach
</div>

{{-- ─── Grid central: gráfica + actividad ──────────────── --}}
<div class="dash-grid adm-anim adm-anim-d2">

    {{-- Gráfica de inscripciones --}}
    <div class="dash-card">
        <div class="dash-card-head">
            <div>
                <div class="dash-card-title">Inscripciones mensuales</div>
                <div class="dash-card-subtitle">Últimos 6 meses · Sede {{ $sedeName }}</div>
            </div>
            <a href="#" class="dash-card-link">
                Ver detalle
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
        <div class="dash-chart-wrap">
            <div class="dash-chart-bars">
                @foreach ($meses as $m)
                <div class="dash-bar-col">
                    <div class="dash-bar {{ isset($m['active']) ? 'dash-bar-active' : '' }}"
                         style="height: {{ $m['pct'] }}%"
                         data-pct="{{ $m['pct'] }}"
                         data-val="{{ $m['val'] }} inscrip."></div>
                    <span class="dash-bar-label">{{ $m['mes'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="dash-card-body" style="padding-top:0; border-top: 1px solid var(--line);">
            <div style="display:flex; gap:20px; flex-wrap:wrap; padding-top:14px;">
                <span style="font-size:0.8rem; color:var(--muted);">
                    <strong style="color:var(--navy-3); font-family:'Archivo',sans-serif; font-weight:800; font-size:1.1rem;">73</strong>
                    &nbsp;inscrip. en sept.
                </span>
                <span style="font-size:0.8rem; color:var(--muted);">
                    <strong style="color:var(--green); font-family:'Archivo',sans-serif; font-weight:800; font-size:1.1rem;">+17.7%</strong>
                    &nbsp;vs. ago.
                </span>
                <span style="font-size:0.8rem; color:var(--muted);">
                    <strong style="color:var(--navy-3); font-family:'Archivo',sans-serif; font-weight:800; font-size:1.1rem;">315</strong>
                    &nbsp;total semestre
                </span>
            </div>
        </div>
    </div>

    {{-- Actividad reciente --}}
    <div class="dash-card">
        <div class="dash-card-head">
            <div>
                <div class="dash-card-title">Actividad reciente</div>
                <div class="dash-card-subtitle">Últimas acciones en la sede</div>
            </div>
        </div>
        <div class="dash-card-body" style="padding-top:8px; padding-bottom:8px;">
            <div class="dash-activity-list">
                @foreach ($actividad as $act)
                <div class="dash-activity-item"
                     role="button" tabindex="0"
                     data-act-dot="{{ $act['dot'] }}"
                     data-act-ini="{{ $act['ini'] }}"
                     data-act-texto="{!! addslashes(strip_tags($act['texto'])) !!}"
                     data-act-texto-html="{!! htmlspecialchars($act['texto'], ENT_QUOTES) !!}"
                     data-act-time="{{ $act['time'] }}">
                    <div class="dash-activity-dot dash-activity-dot-{{ $act['dot'] }}">{{ $act['ini'] }}</div>
                    <div class="dash-activity-body">
                        <div class="dash-activity-text">{!! $act['texto'] !!}</div>
                        <div class="dash-activity-time">{{ $act['time'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ─── Fila: ranking + programas + pendientes ───────── --}}
<div class="dash-grid-3 adm-anim adm-anim-d3">

    {{-- Mejores estudiantes --}}
    <div class="dash-card">
        <div class="dash-card-head">
            <div>
                <div class="dash-card-title">Mejores estudiantes</div>
                <div class="dash-card-subtitle">Por promedio · este semestre</div>
            </div>
            <a href="#" class="dash-card-link">
                Ver todos
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
        <div class="dash-card-body" style="padding:0 20px 4px;">
            <table class="dash-top-table">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>Estudiante</th>
                        <th style="text-align:right;">Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topEstudiantes as $e)
                    <tr>
                        <td>
                            <span class="dash-rank {{ $e['rank'] <= 3 ? 'dash-rank-' . $e['rank'] : 'dash-rank-n' }}">
                                {{ $e['rank'] }}
                            </span>
                        </td>
                        <td>
                            <div class="dash-student-cell">
                                <div class="dash-student-av">{{ $e['ini'] }}</div>
                                <div>
                                    <div class="dash-student-name">{{ $e['nombre'] }}</div>
                                    <div style="font-size:0.76rem; color:var(--muted);">{{ $e['programa'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:right;">
                            <strong style="font-family:'Archivo',sans-serif; font-weight:800; color:var(--navy-3);">{{ $e['nota'] }}</strong>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Programas: ocupación --}}
    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-title">Programas</div>
            <div class="dash-card-subtitle" style="font-size:0.76rem;">Ocupación actual</div>
        </div>
        <div class="dash-card-body">
            <div class="dash-prog-list">
                @foreach ($programas as $p)
                <div class="dash-prog-item">
                    <div class="dash-prog-head">
                        <span class="dash-prog-name">{{ $p['nombre'] }}</span>
                        <span class="dash-prog-pct">{{ $p['pct'] }}%</span>
                    </div>
                    <div class="dash-prog-bar-bg">
                        <div class="dash-prog-bar-fill dash-prog-bar-{{ $p['color'] }}" style="width:{{ $p['pct'] }}%" data-w="{{ $p['pct'] }}"></div>
                    </div>
                    <div class="dash-prog-sub">{{ $p['sub'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Pendientes --}}
    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-title">Pendientes</div>
        </div>
        <div class="dash-card-body">
            <div class="dash-todo-list">
                @foreach ($tareas as $t)
                <div class="dash-todo-item dash-todo-item-{{ $t['color'] }}">
                    <div class="dash-todo-body">
                        <div class="dash-todo-title">{{ $t['titulo'] }}</div>
                        <div class="dash-todo-meta">{{ $t['meta'] }}</div>
                    </div>
                    <span class="dash-todo-tag dash-todo-tag-{{ $t['color'] }}">{{ $t['tag'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>

{{-- ─── Accesos rápidos ─────────────────────────────── --}}
<div class="dash-card adm-anim adm-anim-d4">
    <div class="dash-card-head">
        <div class="dash-card-title">Accesos rápidos</div>
    </div>
    <div class="dash-card-body">
        <div class="dash-quick-grid">
            <a href="#" class="dash-quick-item">
                <div class="dash-quick-icon dash-quick-icon-blue">
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </div>
                <div class="dash-quick-label">Nuevo estudiante</div>
                <div class="dash-quick-sub">Inscribir y asignar programa</div>
            </a>
            <a href="#" class="dash-quick-item">
                <div class="dash-quick-icon dash-quick-icon-orange">
                    <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                </div>
                <div class="dash-quick-label">Registrar pago</div>
                <div class="dash-quick-sub">Matrículas y cuotas</div>
            </a>
            <a href="{{ route('admin.programas') }}#nuevo" class="dash-quick-item">
                <div class="dash-quick-icon dash-quick-icon-green">
                    <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="9" y1="10" x2="15" y2="10"/></svg>
                </div>
                <div class="dash-quick-label">Crear programa</div>
                <div class="dash-quick-sub">Carrera, duración y valor</div>
            </a>
            <a href="#" class="dash-quick-item">
                <div class="dash-quick-icon dash-quick-icon-purple">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="dash-quick-label">Generar reporte</div>
                <div class="dash-quick-sub">Exportar informe mensual</div>
            </a>
        </div>
    </div>
</div>

{{-- ─── Modal: detalle de actividad ─────────────────────── --}}
<div class="dash-act-modal-overlay" id="actModal" role="dialog" aria-modal="true" aria-label="Detalle de actividad" hidden>
    <div class="dash-act-modal">
        <div class="dash-act-modal-head">
            <div class="dash-act-modal-dot" id="actModalDot"></div>
            <div class="dash-act-modal-title">Actividad reciente</div>
            <button class="dash-act-modal-close" id="actModalClose" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="dash-act-modal-body">
            <div class="dash-act-modal-text" id="actModalText"></div>
            <div class="dash-act-modal-time" id="actModalTime"></div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin/dashboard.js') }}?v={{ filemtime(public_path('assets/js/admin/dashboard.js')) }}" defer></script>
@endpush
