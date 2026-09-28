@extends('superadmin.layout')

@section('title', 'Estudiantes')
@section('page-title', 'Estudiantes')
@section('active', 'estudiantes')
@section('content-class', 'app-est-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/estudiantes.css') }}">
@endpush

@section('content')
    {{--
        Vista previa de interfaz — solo frontend, sin conexión a base de datos
        todavía. Los 24 estudiantes de ejemplo son ficticios (nombres,
        documentos y pagos inventados para previsualizar la pantalla); las
        sedes y programas a los que están matriculados sí son los reales de
        ESAT. Esta pantalla es de solo consulta: el Superadmin no crea ni
        edita estudiantes aquí (eso es trabajo del Admin de cada sede) — solo
        ve cuántos hay y cómo van con sus pagos. Falta backend completo
        (consultas reales a matriculas/pagos).
    --}}
    @php
        $sedesList = ['Bogotá', 'Sasaima', 'Guaduas', 'La Dorada'];
        $cuotaPrograma = [
            'Servicios Geriátricos' => 100000,
            'Sommelier' => 166667,
            'Barbería' => 108333,
            'Electricista' => 125000,
            'Inglés' => 133333,
            'Auxiliar Contable y Administrativo' => 133333,
            'Cocina Nacional e Internacional' => 108333,
            'Camillero Hospitalario' => 100000,
        ];
        $meses2026 = ['Julio', 'Agosto', 'Septiembre'];

        function generarHistorial($estadoPago, $cuota, $meses) {
            $historial = [];
            foreach ($meses as $i => $mes) {
                if ($estadoPago === 'al_dia') {
                    $historial[] = ['mes' => $mes, 'monto' => $cuota, 'estado' => 'Pagado', 'fecha_pago' => '5 ' . strtolower(substr($mes, 0, 3)) . ' 2026'];
                } elseif ($estadoPago === 'pendiente') {
                    $historial[] = $i < 2
                        ? ['mes' => $mes, 'monto' => $cuota, 'estado' => 'Pagado', 'fecha_pago' => '5 ' . strtolower(substr($mes, 0, 3)) . ' 2026']
                        : ['mes' => $mes, 'monto' => $cuota, 'estado' => 'Pendiente', 'fecha_pago' => null];
                } else {
                    $historial[] = $i < 1
                        ? ['mes' => $mes, 'monto' => $cuota, 'estado' => 'Pagado', 'fecha_pago' => '5 ' . strtolower(substr($mes, 0, 3)) . ' 2026']
                        : ['mes' => $mes, 'monto' => $cuota, 'estado' => 'Vencido', 'fecha_pago' => null];
                }
            }
            return $historial;
        }

        $datosBase = [
            ['nombres' => 'Juliana', 'apellidos' => 'Restrepo Gómez', 'documento' => '1032456789', 'sede' => 'Bogotá', 'programa' => 'Servicios Geriátricos', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Andrés Felipe', 'apellidos' => 'Morales Peña', 'documento' => '1098765432', 'sede' => 'Bogotá', 'programa' => 'Sommelier', 'matricula' => 'activa', 'pago' => 'pendiente'],
            ['nombres' => 'Camila', 'apellidos' => 'Rojas Duarte', 'documento' => '1076543210', 'sede' => 'Bogotá', 'programa' => 'Barbería', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Santiago', 'apellidos' => 'Herrera Ochoa', 'documento' => '1122334455', 'sede' => 'Bogotá', 'programa' => 'Electricista', 'matricula' => 'activa', 'pago' => 'vencido'],
            ['nombres' => 'Valentina', 'apellidos' => 'Castro Nieto', 'documento' => '1099887766', 'sede' => 'Sasaima', 'programa' => 'Inglés', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Juan Pablo', 'apellidos' => 'Ortiz Camacho', 'documento' => '1044556677', 'sede' => 'Sasaima', 'programa' => 'Auxiliar Contable y Administrativo', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Mariana', 'apellidos' => 'Gil Beltrán', 'documento' => '1033445566', 'sede' => 'Sasaima', 'programa' => 'Cocina Nacional e Internacional', 'matricula' => 'activa', 'pago' => 'pendiente'],
            ['nombres' => 'Sebastián', 'apellidos' => 'Vargas Mora', 'documento' => '1011223344', 'sede' => 'Sasaima', 'programa' => 'Camillero Hospitalario', 'matricula' => 'inactiva', 'pago' => 'al_dia'],
            ['nombres' => 'Laura', 'apellidos' => 'Jiménez Roldán', 'documento' => '1055667788', 'sede' => 'Guaduas', 'programa' => 'Servicios Geriátricos', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Nicolás', 'apellidos' => 'Peña Aguilar', 'documento' => '1066778899', 'sede' => 'Guaduas', 'programa' => 'Sommelier', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Daniela', 'apellidos' => 'Suárez Cano', 'documento' => '1077889900', 'sede' => 'Guaduas', 'programa' => 'Barbería', 'matricula' => 'activa', 'pago' => 'vencido'],
            ['nombres' => 'Felipe', 'apellidos' => 'Cárdenas Lozano', 'documento' => '1088990011', 'sede' => 'Guaduas', 'programa' => 'Electricista', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Isabella', 'apellidos' => 'Molina Prada', 'documento' => '1099001122', 'sede' => 'La Dorada', 'programa' => 'Inglés', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Tomás', 'apellidos' => 'Ríos Guzmán', 'documento' => '1100112233', 'sede' => 'La Dorada', 'programa' => 'Auxiliar Contable y Administrativo', 'matricula' => 'activa', 'pago' => 'pendiente'],
            ['nombres' => 'Gabriela', 'apellidos' => 'Espinosa Vega', 'documento' => '1111223344', 'sede' => 'La Dorada', 'programa' => 'Cocina Nacional e Internacional', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Emmanuel', 'apellidos' => 'Torres Salgado', 'documento' => '1122334400', 'sede' => 'La Dorada', 'programa' => 'Camillero Hospitalario', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Paula Andrea', 'apellidos' => 'Salazar Uribe', 'documento' => '1133445566', 'sede' => 'Bogotá', 'programa' => 'Servicios Geriátricos', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Cristian Camilo', 'apellidos' => 'Ruiz Pardo', 'documento' => '1144556677', 'sede' => 'Bogotá', 'programa' => 'Sommelier', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Natalia', 'apellidos' => 'Bermúdez Osorio', 'documento' => '1155667788', 'sede' => 'Sasaima', 'programa' => 'Barbería', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Kevin Alexander', 'apellidos' => 'Díaz Cuervo', 'documento' => '1166778899', 'sede' => 'Sasaima', 'programa' => 'Electricista', 'matricula' => 'activa', 'pago' => 'vencido'],
            ['nombres' => 'Sara Lucía', 'apellidos' => 'Mendoza Rincón', 'documento' => '1177889900', 'sede' => 'Guaduas', 'programa' => 'Inglés', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Jorge Iván', 'apellidos' => 'Castaño Buitrago', 'documento' => '1188990011', 'sede' => 'Guaduas', 'programa' => 'Auxiliar Contable y Administrativo', 'matricula' => 'activa', 'pago' => 'al_dia'],
            ['nombres' => 'Angie Paola', 'apellidos' => 'Reyes Solano', 'documento' => '1199001122', 'sede' => 'La Dorada', 'programa' => 'Cocina Nacional e Internacional', 'matricula' => 'activa', 'pago' => 'pendiente'],
            ['nombres' => 'Miguel Ángel', 'apellidos' => 'Correa Zapata', 'documento' => '1200112233', 'sede' => 'La Dorada', 'programa' => 'Camillero Hospitalario', 'matricula' => 'activa', 'pago' => 'al_dia'],
        ];

        $programasList = array_values(array_unique(array_column($datosBase, 'programa')));

        $estudiantes = array_map(function ($e) use ($cuotaPrograma, $meses2026) {
            $cuota = $cuotaPrograma[$e['programa']];
            $historial = generarHistorial($e['pago'], $cuota, $meses2026);
            $pendientes = array_filter($historial, fn ($h) => $h['estado'] !== 'Pagado');
            $e['cuota'] = $cuota;
            $e['monto_pendiente'] = array_sum(array_column($pendientes, 'monto'));
            $e['historial'] = $historial;
            return $e;
        }, $datosBase);

        $totalEstudiantes = count($estudiantes);
        $totalAlDia = count(array_filter($estudiantes, fn ($e) => $e['pago'] === 'al_dia'));
        $totalPendiente = count(array_filter($estudiantes, fn ($e) => $e['pago'] === 'pendiente'));
        $totalVencido = count(array_filter($estudiantes, fn ($e) => $e['pago'] === 'vencido'));

        $ESTADO_PAGO_TEXTO = ['al_dia' => 'Al día', 'pendiente' => 'Pendiente', 'vencido' => 'Vencido'];
    @endphp

    <p class="app-page-intro">Aquí puedes ver cuántos estudiantes hay matriculados y cómo van con sus pagos. Esta pantalla es solo de consulta — crear o editar estudiantes es trabajo del administrador de cada sede.</p>

    <div class="app-stat-row">
        <div class="app-stat-tile">
            <span class="app-stat-value">{{ $totalEstudiantes }}</span>
            <span class="app-stat-label">Estudiantes matriculados</span>
        </div>
        <div class="app-stat-tile app-stat-tile-green">
            <span class="app-stat-value">{{ $totalAlDia }}</span>
            <span class="app-stat-label">Al día</span>
        </div>
        <div class="app-stat-tile app-stat-tile-orange">
            <span class="app-stat-value">{{ $totalPendiente }}</span>
            <span class="app-stat-label">Pendientes</span>
        </div>
        <div class="app-stat-tile app-stat-tile-red">
            <span class="app-stat-value">{{ $totalVencido }}</span>
            <span class="app-stat-label">Vencidos</span>
        </div>
    </div>

    <div class="app-panel app-list-panel">
        <div class="app-panel-heading">
            <div class="app-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" placeholder="Buscar estudiante..." data-table-search="estudiantes">
            </div>
            <div class="app-panel-heading-actions">
                <select class="app-select" data-filter-sede data-table-target="estudiantes">
                    <option value="todas">Todas las sedes</option>
                    @foreach ($sedesList as $sede)
                        <option value="{{ $sede }}">{{ $sede }}</option>
                    @endforeach
                </select>
                <select class="app-select" data-filter-programa data-table-target="estudiantes">
                    <option value="todos">Todos los programas</option>
                    @foreach ($programasList as $programa)
                        <option value="{{ $programa }}">{{ $programa }}</option>
                    @endforeach
                </select>
                <select class="app-select" data-filter-pago data-table-target="estudiantes">
                    <option value="todos">Cualquier estado de pago</option>
                    <option value="al_dia">Al día</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="vencido">Vencido</option>
                </select>
            </div>
        </div>

        <div class="app-table-scroll app-table-scroll-tall">
            <table class="app-table" data-table="estudiantes" data-per-page="15">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Documento</th>
                        <th>Sede</th>
                        <th>Programa</th>
                        <th>Matrícula</th>
                        <th>Pago</th>
                        <th>Debe</th>
                        <th class="app-table-actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($estudiantes as $i => $est)
                        <tr data-sede="{{ $est['sede'] }}" data-programa="{{ $est['programa'] }}" data-pago="{{ $est['pago'] }}" data-est-index="{{ $i }}">
                            <td class="app-table-strong">{{ $est['nombres'] }} {{ $est['apellidos'] }}</td>
                            <td>CC {{ $est['documento'] }}</td>
                            <td>{{ $est['sede'] }}</td>
                            <td>{{ $est['programa'] }}</td>
                            <td>
                                <span class="app-status-tag {{ $est['matricula'] === 'activa' ? 'app-status-active' : 'app-status-muted' }}">
                                    {{ $est['matricula'] === 'activa' ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td>
                                <span class="app-status-tag app-status-{{ $est['pago'] === 'al_dia' ? 'active' : ($est['pago'] === 'pendiente' ? 'warn' : 'danger') }}">
                                    {{ $ESTADO_PAGO_TEXTO[$est['pago']] }}
                                </span>
                            </td>
                            <td>
                                @if ($est['monto_pendiente'] > 0)
                                    ${{ number_format($est['monto_pendiente'], 0, ',', '.') }}
                                @else
                                    <span class="app-table-empty">—</span>
                                @endif
                            </td>
                            <td class="app-table-actions">
                                <button type="button" class="app-icon-btn" data-view-student aria-label="Ver historial de {{ $est['nombres'] }}">
                                    <svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('superadmin.partials.table-pagination', ['name' => 'estudiantes'])
    </div>

    {{-- ============ DETALLE DE ESTUDIANTE (solo lectura) ============ --}}
    <div class="app-modal-backdrop" data-modal="modal-estudiante" hidden>
        <div class="app-modal">
            <div class="app-modal-header">
                <div>
                    <h3 data-detalle-nombre></h3>
                    <p class="app-modal-subtext" data-detalle-info></p>
                </div>
                <button type="button" class="app-modal-close" data-close-student aria-label="Cerrar">
                    <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <h4 class="app-modal-section-title">Historial de pagos</h4>
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Fecha de pago</th>
                    </tr>
                </thead>
                <tbody data-detalle-historial></tbody>
            </table>
        </div>
    </div>

    <script type="application/json" id="app-estudiantes-data">{!! json_encode($estudiantes) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/superadmin/estudiantes.js') }}" defer></script>
@endpush
