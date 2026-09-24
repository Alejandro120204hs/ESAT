@extends('superadmin.layout')

@section('title', 'Panel Superadmin')
@section('page-title', 'Panel Superadmin')
@section('active', 'inicio')
@section('content-class', 'app-dashboard-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/dashboard.css') }}">
@endpush

@section('content')
    {{--
        Vista previa con datos de ejemplo: aún no hay pagos/sedes/programas/
        auditorías reales cargados. Sustituir por consultas reales (Pago,
        Sede, Auditoria, User::where('role', ...)) cuando existan datos.
        La paginación de alertas y el tope de 10 en actividad reciente son
        solo de frontend por ahora — falta la parte de backend (traer por
        páginas, recortar a los últimos 10 registros reales).
    --}}
    @php
        $ingresosPorMes = [
            ['mes' => 'Abr', 'valor' => 9.2],
            ['mes' => 'May', 'valor' => 10.1],
            ['mes' => 'Jun', 'valor' => 11.4],
            ['mes' => 'Jul', 'valor' => 10.8],
            ['mes' => 'Ago', 'valor' => 13.2],
            ['mes' => 'Sep', 'valor' => 14.25],
        ];
        $maxIngreso = max(array_column($ingresosPorMes, 'valor'));

        $alertas = [
            'La sede <strong>La Dorada</strong> no tiene administrador asignado.',
            '<strong>3 pagos</strong> llevan más de 30 días vencidos.',
            'El programa <strong>Sommelier</strong> no tiene periodos académicos configurados.',
            'La cuota del sistema no se ha actualizado en <strong>6 meses</strong>.',
            '<strong>2 administradores</strong> no han iniciado sesión en 30 días.',
            'La sede <strong>Sasaima</strong> superó su capacidad de estudiantes.',
            'Hay <strong>5 matrículas</strong> pendientes de aprobación.',
            'El programa <strong>Barbería</strong> no tiene docente asignado.',
            'La sede <strong>Bogotá</strong> no tiene programas activos.',
            '<strong>1 pago</strong> con método de pago inválido.',
            'La escuela de <strong>Ciencias</strong> no tiene coordinador asignado.',
            'Hay <strong>2 cuentas</strong> de administrador inactivas hace más de 90 días.',
        ];

        $actividades = [
            ['quien' => 'María Pérez', 'accion' => 'creó la sede Guaduas', 'cuando' => 'Hace 2 horas'],
            ['quien' => 'Carlos Gómez', 'accion' => 'actualizó el programa Barbería', 'cuando' => 'Hace 5 horas'],
            ['quien' => 'Diego Hernandez', 'accion' => 'registró un nuevo administrador', 'cuando' => 'Ayer'],
            ['quien' => 'Diego Hernandez', 'accion' => 'modificó la cuota del sistema a $2.000', 'cuando' => 'Hace 2 días'],
            ['quien' => 'María Pérez', 'accion' => 'eliminó un programa inactivo', 'cuando' => 'Hace 3 días'],
            ['quien' => 'Carlos Gómez', 'accion' => 'creó el programa Sommelier', 'cuando' => 'Hace 4 días'],
            ['quien' => 'Diego Hernandez', 'accion' => 'asignó un docente al programa Barbería', 'cuando' => 'Hace 5 días'],
            ['quien' => 'María Pérez', 'accion' => 'actualizó los datos de la sede Bogotá', 'cuando' => 'Hace 6 días'],
            ['quien' => 'Carlos Gómez', 'accion' => 'aprobó 3 matrículas nuevas', 'cuando' => 'Hace 1 semana'],
            ['quien' => 'Diego Hernandez', 'accion' => 'creó la sede La Dorada', 'cuando' => 'Hace 2 semanas'],
        ];

        $alertasPorPagina = 4;
    @endphp

    <section class="app-revenue-hero">
        <div class="app-revenue-main">
            <p class="app-revenue-label">Recaudado este mes</p>
            <p class="app-revenue-value">$14.250.000</p>
            <p class="app-revenue-delta">
                <svg viewBox="0 0 24 24"><path d="M7 17 17 7M9 7h8v8"/></svg>
                8% vs. mes anterior
            </p>
            <p class="app-revenue-total">$186.400.000 recaudados en total</p>
        </div>

        <div class="app-revenue-chart">
            @foreach ($ingresosPorMes as $item)
                <div class="app-revenue-bar-col">
                    <div class="app-revenue-bar" style="height: {{ round(($item['valor'] / $maxIngreso) * 100) }}%"></div>
                    <span class="app-revenue-bar-label">{{ $item['mes'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="app-oversight-grid">
        <div class="app-panel app-cartera-panel">
            <h3>Estado de cartera</h3>

            <div class="app-cartera-bar">
                <span class="app-cartera-seg app-cartera-seg-pagado" style="width: 68%"></span>
                <span class="app-cartera-seg app-cartera-seg-pendiente" style="width: 22%"></span>
                <span class="app-cartera-seg app-cartera-seg-vencido" style="width: 10%"></span>
            </div>

            <div class="app-cartera-legend">
                <span><i class="app-dot app-dot-pagado"></i>Pagado · 68%</span>
                <span><i class="app-dot app-dot-pendiente"></i>Pendiente · 22%</span>
                <span><i class="app-dot app-dot-vencido"></i>Vencido · 10%</span>
            </div>

            <div class="app-cartera-breakdown">
                <div class="app-cartera-breakdown-row">
                    <span class="app-cartera-breakdown-label"><i class="app-dot app-dot-pagado"></i>Pagado</span>
                    <span class="app-cartera-breakdown-amount">$12.580.000</span>
                </div>
                <div class="app-cartera-breakdown-row">
                    <span class="app-cartera-breakdown-label"><i class="app-dot app-dot-pendiente"></i>Pendiente</span>
                    <span class="app-cartera-breakdown-amount">$4.070.000</span>
                </div>
                <div class="app-cartera-breakdown-row">
                    <span class="app-cartera-breakdown-label"><i class="app-dot app-dot-vencido"></i>Vencido</span>
                    <span class="app-cartera-breakdown-amount">$1.850.000</span>
                </div>
            </div>
        </div>

        <div class="app-panel app-alerts-panel">
            <div class="app-panel-heading">
                <h3>Alertas</h3>
                <span class="app-panel-count">{{ count($alertas) }}</span>
            </div>

            <ul class="app-alert-list">
                @foreach ($alertas as $i => $alerta)
                    <li class="app-alert-item" data-page="{{ intdiv($i, $alertasPorPagina) }}">
                        <span class="app-alert-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 2.5 17a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        </span>
                        <p>{!! $alerta !!}</p>
                    </li>
                @endforeach
            </ul>

            <div class="app-pagination">
                <button type="button" class="app-pagination-btn" data-alerts-prev aria-label="Página anterior">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <span class="app-pagination-label" data-alerts-indicator></span>
                <button type="button" class="app-pagination-btn" data-alerts-next aria-label="Página siguiente">
                    <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div class="app-panel app-activity-panel">
        <div class="app-panel-heading">
            <h3>Actividad reciente</h3>
            <span class="app-panel-count">Últimas {{ count($actividades) }}</span>
        </div>

        <ul class="app-activity-list">
            @foreach ($actividades as $actividad)
                <li>
                    <span class="app-activity-dot"></span>
                    <div>
                        <p><strong>{{ $actividad['quien'] }}</strong> {{ $actividad['accion'] }}</p>
                        <span class="app-activity-time">{{ $actividad['cuando'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="app-mini-stats">
        <span><strong>4</strong> sedes</span>
        <span><strong>7</strong> escuelas</span>
        <span><strong>24</strong> programas</span>
        <span><strong>3</strong> administradores</span>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/superadmin/dashboard.js') }}" defer></script>
@endpush
