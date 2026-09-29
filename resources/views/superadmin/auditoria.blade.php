@extends('superadmin.layout')

@section('title', 'Auditoría')
@section('page-title', 'Auditoría')
@section('active', 'auditoria')
@section('content-class', 'app-aud-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/auditoria.css') }}">
@endpush

@section('content')
    {{--
        Vista previa de interfaz — solo frontend, sin conexión a base de datos
        todavía. Los 24 eventos de ejemplo son ficticios (inventados para
        previsualizar la pantalla); los administradores, sedes y programas
        que aparecen sí son los reales/ya definidos en el resto del panel.
        Falta backend completo: registrar automáticamente cada acción en la
        tabla `auditorias` y consultarla aquí en vez de este arreglo fijo.
    --}}
    @php
        $eventos = [
            ['quien' => 'María Pérez', 'tipo' => 'crear', 'descripcion' => 'creó la sede Guaduas', 'sede' => 'Guaduas', 'fecha' => '2026-09-29', 'fechaTexto' => '29 sep 2026', 'hora' => '8:15 a.m.'],
            ['quien' => 'Carlos Gómez', 'tipo' => 'editar', 'descripcion' => 'actualizó el programa Barbería', 'sede' => 'Sasaima', 'fecha' => '2026-09-29', 'fechaTexto' => '29 sep 2026', 'hora' => '6:40 a.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'crear', 'descripcion' => 'registró a la administradora Laura Torres', 'sede' => 'Guaduas', 'fecha' => '2026-09-28', 'fechaTexto' => '28 sep 2026', 'hora' => '4:20 p.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'editar', 'descripcion' => 'modificó la cuota del sistema a $2.000', 'sede' => null, 'fecha' => '2026-09-27', 'fechaTexto' => '27 sep 2026', 'hora' => '11:05 a.m.'],
            ['quien' => 'María Pérez', 'tipo' => 'eliminar', 'descripcion' => 'eliminó un programa inactivo', 'sede' => 'Bogotá', 'fecha' => '2026-09-26', 'fechaTexto' => '26 sep 2026', 'hora' => '2:50 p.m.'],
            ['quien' => 'Carlos Gómez', 'tipo' => 'crear', 'descripcion' => 'creó el programa Sommelier', 'sede' => 'Sasaima', 'fecha' => '2026-09-25', 'fechaTexto' => '25 sep 2026', 'hora' => '9:30 a.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'editar', 'descripcion' => 'actualizó qué programas ofrece la sede Sasaima', 'sede' => 'Sasaima', 'fecha' => '2026-09-24', 'fechaTexto' => '24 sep 2026', 'hora' => '3:15 p.m.'],
            ['quien' => 'María Pérez', 'tipo' => 'editar', 'descripcion' => 'actualizó los datos de la sede Bogotá', 'sede' => 'Bogotá', 'fecha' => '2026-09-23', 'fechaTexto' => '23 sep 2026', 'hora' => '10:00 a.m.'],
            ['quien' => 'Carlos Gómez', 'tipo' => 'activar', 'descripcion' => 'activó la cuenta de la administradora Laura Torres', 'sede' => 'Guaduas', 'fecha' => '2026-09-22', 'fechaTexto' => '22 sep 2026', 'hora' => '1:40 p.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'crear', 'descripcion' => 'creó la sede La Dorada', 'sede' => 'La Dorada', 'fecha' => '2026-09-15', 'fechaTexto' => '15 sep 2026', 'hora' => '9:10 a.m.'],
            ['quien' => 'Laura Torres', 'tipo' => 'crear', 'descripcion' => 'creó un periodo académico de Servicios Geriátricos', 'sede' => 'Bogotá', 'fecha' => '2026-09-14', 'fechaTexto' => '14 sep 2026', 'hora' => '11:25 a.m.'],
            ['quien' => 'Laura Torres', 'tipo' => 'editar', 'descripcion' => 'actualizó un periodo académico de Sommelier', 'sede' => 'Sasaima', 'fecha' => '2026-09-13', 'fechaTexto' => '13 sep 2026', 'hora' => '4:05 p.m.'],
            ['quien' => 'María Pérez', 'tipo' => 'desactivar', 'descripcion' => 'desactivó una cuenta de administrador temporal', 'sede' => 'Bogotá', 'fecha' => '2026-09-12', 'fechaTexto' => '12 sep 2026', 'hora' => '8:50 a.m.'],
            ['quien' => 'Carlos Gómez', 'tipo' => 'crear', 'descripcion' => 'creó el programa Electricista', 'sede' => 'Sasaima', 'fecha' => '2026-09-11', 'fechaTexto' => '11 sep 2026', 'hora' => '2:15 p.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'editar', 'descripcion' => 'actualizó los datos de la sede Guaduas', 'sede' => 'Guaduas', 'fecha' => '2026-09-10', 'fechaTexto' => '10 sep 2026', 'hora' => '9:45 a.m.'],
            ['quien' => 'María Pérez', 'tipo' => 'eliminar', 'descripcion' => 'eliminó un periodo académico duplicado', 'sede' => 'Bogotá', 'fecha' => '2026-09-09', 'fechaTexto' => '9 sep 2026', 'hora' => '3:30 p.m.'],
            ['quien' => 'Carlos Gómez', 'tipo' => 'editar', 'descripcion' => 'actualizó qué programas ofrece la sede Sasaima', 'sede' => 'Sasaima', 'fecha' => '2026-09-08', 'fechaTexto' => '8 sep 2026', 'hora' => '10:20 a.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'activar', 'descripcion' => 'activó la sede La Dorada', 'sede' => 'La Dorada', 'fecha' => '2026-09-08', 'fechaTexto' => '8 sep 2026', 'hora' => '8:00 a.m.'],
            ['quien' => 'Laura Torres', 'tipo' => 'crear', 'descripcion' => 'creó el programa Auxiliar Contable y Administrativo', 'sede' => 'Guaduas', 'fecha' => '2026-09-07', 'fechaTexto' => '7 sep 2026', 'hora' => '5:10 p.m.'],
            ['quien' => 'María Pérez', 'tipo' => 'editar', 'descripcion' => 'actualizó el teléfono de contacto de la sede Bogotá', 'sede' => 'Bogotá', 'fecha' => '2026-09-06', 'fechaTexto' => '6 sep 2026', 'hora' => '11:00 a.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'crear', 'descripcion' => 'creó la escuela de Ciencias', 'sede' => null, 'fecha' => '2026-09-05', 'fechaTexto' => '5 sep 2026', 'hora' => '9:15 a.m.'],
            ['quien' => 'Carlos Gómez', 'tipo' => 'eliminar', 'descripcion' => 'eliminó un programa descontinuado', 'sede' => 'Sasaima', 'fecha' => '2026-09-04', 'fechaTexto' => '4 sep 2026', 'hora' => '2:40 p.m.'],
            ['quien' => 'Laura Torres', 'tipo' => 'editar', 'descripcion' => 'actualizó el precio del programa Barbería', 'sede' => 'Guaduas', 'fecha' => '2026-09-03', 'fechaTexto' => '3 sep 2026', 'hora' => '10:05 a.m.'],
            ['quien' => 'Diego Hernandez', 'tipo' => 'crear', 'descripcion' => 'configuró la cuota inicial del sistema', 'sede' => null, 'fecha' => '2026-09-02', 'fechaTexto' => '2 sep 2026', 'hora' => '8:30 a.m.'],
        ];

        $administradoresList = array_values(array_unique(array_column($eventos, 'quien')));
        $totalEventos = count($eventos);
        $hoy = now()->toDateString();
        $inicioSemana = now()->subDays(6)->toDateString();
        $totalEstaSemana = count(array_filter($eventos, fn ($e) => $e['fecha'] >= $inicioSemana));

        $TIPO_CLASE = ['crear' => 'app-tag-crear', 'editar' => 'app-tag-editar', 'eliminar' => 'app-tag-eliminar', 'activar' => 'app-tag-crear', 'desactivar' => 'app-tag-desactivar'];
    @endphp

    <p class="app-page-intro">Aquí puedes revisar qué ha hecho cada administrador en el sistema: qué creó, editó o eliminó, y cuándo.</p>

    <div class="app-stat-row">
        <div class="app-stat-tile">
            <span class="app-stat-value">{{ $totalEventos }}</span>
            <span class="app-stat-label">Eventos registrados</span>
        </div>
        <div class="app-stat-tile">
            <span class="app-stat-value">{{ $totalEstaSemana }}</span>
            <span class="app-stat-label">En los últimos 7 días</span>
        </div>
        <div class="app-stat-tile">
            <span class="app-stat-value">{{ count($administradoresList) }}</span>
            <span class="app-stat-label">Administradores con actividad</span>
        </div>
    </div>

    <div class="app-panel app-list-panel">
        <div class="app-panel-heading">
            <div class="app-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" placeholder="Buscar en el historial..." data-table-search="eventos">
            </div>
            <div class="app-panel-heading-actions">
                <select class="app-select" data-filter-quien data-table-target="eventos">
                    <option value="todos">Todos los administradores</option>
                    @foreach ($administradoresList as $admin)
                        <option value="{{ $admin }}">{{ $admin }}</option>
                    @endforeach
                </select>
                <select class="app-select" data-filter-tipo data-table-target="eventos">
                    <option value="todos">Cualquier acción</option>
                    <option value="crear">Crear</option>
                    <option value="editar">Editar</option>
                    <option value="eliminar">Eliminar</option>
                    <option value="activar">Activar</option>
                    <option value="desactivar">Desactivar</option>
                </select>
                <select class="app-select" data-filter-periodo>
                    <option value="todo">Todo el tiempo</option>
                    <option value="hoy">Hoy</option>
                    <option value="semana">Últimos 7 días</option>
                    <option value="mes">Últimos 30 días</option>
                </select>
            </div>
        </div>

        <div class="app-table-scroll app-table-scroll-tall">
            <table class="app-table" data-table="eventos" data-per-page="15">
                <thead>
                    <tr>
                        <th>Acción</th>
                        <th>Administrador</th>
                        <th>Tipo</th>
                        <th>Sede</th>
                        <th>Fecha</th>
                        <th class="app-table-actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($eventos as $i => $evento)
                        <tr
                            data-quien="{{ $evento['quien'] }}"
                            data-tipo="{{ $evento['tipo'] }}"
                            data-fecha="{{ $evento['fecha'] }}"
                            data-evento-index="{{ $i }}"
                        >
                            <td>{{ ucfirst($evento['descripcion']) }}</td>
                            <td class="app-table-strong">{{ $evento['quien'] }}</td>
                            <td><span class="app-tag {{ $TIPO_CLASE[$evento['tipo']] }}">{{ ucfirst($evento['tipo']) }}</span></td>
                            <td>{{ $evento['sede'] ?? '—' }}</td>
                            <td>
                                <span class="app-table-fecha">{{ $evento['fechaTexto'] }}</span>
                                <span class="app-table-hora">{{ $evento['hora'] }}</span>
                            </td>
                            <td class="app-table-actions">
                                <button type="button" class="app-icon-btn" data-view-event aria-label="Ver detalle">
                                    <svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('superadmin.partials.table-pagination', ['name' => 'eventos'])
    </div>

    {{-- ============ DETALLE DEL EVENTO (solo lectura) ============ --}}
    <div class="app-modal-backdrop" data-modal="modal-evento" hidden>
        <div class="app-modal">
            <div class="app-modal-header">
                <h3>Detalle del evento</h3>
                <button type="button" class="app-modal-close" data-close-event aria-label="Cerrar">
                    <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <p class="app-modal-detalle-desc" data-detalle-desc></p>

            <dl class="app-modal-detalle-grid">
                <div>
                    <dt>Administrador</dt>
                    <dd data-detalle-quien></dd>
                </div>
                <div>
                    <dt>Tipo de acción</dt>
                    <dd data-detalle-tipo></dd>
                </div>
                <div>
                    <dt>Sede</dt>
                    <dd data-detalle-sede></dd>
                </div>
                <div>
                    <dt>Fecha y hora</dt>
                    <dd data-detalle-fecha></dd>
                </div>
            </dl>
        </div>
    </div>

    <script type="application/json" id="app-eventos-data">{!! json_encode($eventos) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/superadmin/auditoria.js') }}" defer></script>
@endpush
