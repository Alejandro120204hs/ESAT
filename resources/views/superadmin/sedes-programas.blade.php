@extends('superadmin.layout')

@section('title', 'Sedes')
@section('page-title', 'Sedes')
@section('active', 'sedes')
@section('content-class', 'app-sp-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/sedes-programas.css') }}">
@endpush

@section('content')
    <p class="app-page-intro" data-page-intro>Aquí podrás crear nuevas sedes, editarlas, y desactivar las que ya no estén en funcionamiento.</p>

    <div class="app-tab-panel app-panel">
        <div class="app-panel-heading">
            <div class="app-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" placeholder="Buscar sede..." data-table-search="sedes">
            </div>
            <button type="button" class="app-btn-primary" data-open-modal="modal-sede">Nueva sede</button>
        </div>

        <div class="app-table-scroll app-table-scroll-tall">
            <table class="app-table" data-table="sedes" data-per-page="15">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Dirección</th>
                        <th>Ciudad</th>
                        <th>Estado</th>
                        <th class="app-table-actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sedes as $sede)
                        <tr
                            data-id="{{ $sede->id }}"
                            data-nombre="{{ $sede->nombre }}"
                            data-direccion="{{ $sede->direccion }}"
                            data-departamento="{{ $sede->departamento }}"
                            data-ciudad="{{ $sede->ciudad }}"
                        >
                            <td class="app-table-strong">{{ $sede->nombre }}</td>
                            <td>{{ $sede->direccion }}</td>
                            <td>{{ $sede->ciudad }}, {{ $sede->departamento }}</td>
                            <td><span class="app-status-tag app-status-active">Activa</span></td>
                            <td class="app-table-actions">
                                <button type="button" class="app-icon-btn" data-edit-modal="sede" aria-label="Editar {{ $sede->nombre }}">
                                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    @if ($sedes->isEmpty())
                        <tr><td colspan="5" class="app-table-empty-row">No hay sedes registradas todavía.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        @include('superadmin.partials.table-pagination', ['name' => 'sedes'])
    </div>

    <div class="app-modal-backdrop" data-modal="modal-sede" hidden>
        <div class="app-modal">
            <div class="app-modal-header">
                <h3 data-modal-title data-create-title="Nueva sede" data-edit-title="Editar sede">Nueva sede</h3>
                <button type="button" class="app-modal-close" data-close-modal aria-label="Cerrar">
                    <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="app-form-grid">
                <div class="app-field app-field-wide">
                    <label>Nombre de la sede</label>
                    <input type="text" id="sede-nombre" placeholder="Ej. Villeta">
                </div>
                <div class="app-field">
                    <label>Departamento</label>
                    <div class="app-combobox" data-combobox="departamento">
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
                        <input type="hidden" id="sede-departamento" data-combobox-value>
                    </div>
                </div>
                <div class="app-field">
                    <label>Ciudad</label>
                    <div class="app-combobox" data-combobox="ciudad">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                            <span data-combobox-label>Elige el departamento</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <div class="app-combobox-search-wrap">
                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar ciudad...">
                            </div>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" id="sede-ciudad" data-combobox-value>
                    </div>
                </div>
                <div class="app-field app-field-wide">
                    <label>Dirección</label>
                    <input type="text" id="sede-direccion" placeholder="Cra 5 #10-20">
                </div>
            </div>
            <div class="app-modal-actions">
                <button type="button" class="app-btn-secondary" data-close-modal>Cancelar</button>
                <button type="button" class="app-btn-primary" data-modal-save data-create-label="Guardar sede" data-edit-label="Guardar cambios">Guardar sede</button>
            </div>
        </div>
    </div>

    {{--
        ================================================================
        RESERVADO PARA EL PANEL ADMIN (no Superadmin) — no borrar.
        Escuelas, Programas, Asignaciones (qué programas ofrece cada
        sede) y Periodos académicos son trabajo del rol Admin de cada
        sede, no del Superadmin. Superadmin solo crea/edita sedes.
        Este bloque completo (pestañas, tablas, modales) ya está armado
        y probado — cuando se construya el panel Admin, se reactiva
        moviendo este contenido a esa vista (con su propio layout/CSS/JS
        de Admin, no el de Superadmin) y descomentando lo de abajo.
        ================================================================

        @php
            $escuelas = [
                'Escuela de Salud',
                'Escuela de Cocina y Turismo',
                'Escuela Administrativa',
                'Escuela Deporte y Cultura',
                'Escuela Ciencias',
                'Escuela de Educación e Idiomas',
                'Escuela de Belleza',
            ];

            $programas = [
                ['nombre' => 'Servicios Geriátricos', 'escuela' => 'Escuela de Salud', 'precio' => 2400000, 'tipo' => 'Semestre', 'periodos' => 4, 'meses' => 24, 'activo' => true],
                ['nombre' => 'Seguridad Ocupacional y Laboral', 'escuela' => 'Escuela de Salud', 'precio' => 1800000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Auxiliar de Psiquiatría', 'escuela' => 'Escuela de Salud', 'precio' => 2000000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Camillero Hospitalario', 'escuela' => 'Escuela de Salud', 'precio' => 1200000, 'tipo' => 'Semestre', 'periodos' => 2, 'meses' => 12, 'activo' => true],
                ['nombre' => 'Servicios Hoteleros y Turísticos', 'escuela' => 'Escuela de Cocina y Turismo', 'precio' => 2200000, 'tipo' => 'Semestre', 'periodos' => 4, 'meses' => 24, 'activo' => true],
                ['nombre' => 'Cocina Nacional e Internacional', 'escuela' => 'Escuela de Cocina y Turismo', 'precio' => 2600000, 'tipo' => 'Semestre', 'periodos' => 4, 'meses' => 24, 'activo' => true],
                ['nombre' => 'Sommelier', 'escuela' => 'Escuela de Cocina y Turismo', 'precio' => 1500000, 'tipo' => 'Trimestre', 'periodos' => 3, 'meses' => 9, 'activo' => true],
                ['nombre' => 'Inspector de Calidad de Alimentos y Bebidas', 'escuela' => 'Escuela de Cocina y Turismo', 'precio' => 1900000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Auditoría y Facturación de Cuentas Médicas', 'escuela' => 'Escuela Administrativa', 'precio' => 1700000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Agente de Tránsito', 'escuela' => 'Escuela Administrativa', 'precio' => 1400000, 'tipo' => 'Semestre', 'periodos' => 2, 'meses' => 12, 'activo' => true],
                ['nombre' => 'Auxiliar Contable y Administrativo', 'escuela' => 'Escuela Administrativa', 'precio' => 1600000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Salvamento Acuático', 'escuela' => 'Escuela Deporte y Cultura', 'precio' => 1300000, 'tipo' => 'Semestre', 'periodos' => 2, 'meses' => 12, 'activo' => true],
                ['nombre' => 'Gestión y Promoción Artística', 'escuela' => 'Escuela Deporte y Cultura', 'precio' => 1500000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Servicios de Recreación y Deportes', 'escuela' => 'Escuela Deporte y Cultura', 'precio' => 1400000, 'tipo' => 'Semestre', 'periodos' => 2, 'meses' => 12, 'activo' => true],
                ['nombre' => 'Producción Agropecuaria y Zootecnia', 'escuela' => 'Escuela Ciencias', 'precio' => 2000000, 'tipo' => 'Semestre', 'periodos' => 4, 'meses' => 24, 'activo' => true],
                ['nombre' => 'Asistente de Veterinaria y Zootecnia', 'escuela' => 'Escuela Ciencias', 'precio' => 1900000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Obras Civiles y Arquitectura', 'escuela' => 'Escuela Ciencias', 'precio' => 2400000, 'tipo' => 'Semestre', 'periodos' => 4, 'meses' => 24, 'activo' => true],
                ['nombre' => 'Criminalística, Investigación Judicial y Ciencias Forenses', 'escuela' => 'Escuela Ciencias', 'precio' => 2600000, 'tipo' => 'Semestre', 'periodos' => 4, 'meses' => 24, 'activo' => true],
                ['nombre' => 'Energías Renovables', 'escuela' => 'Escuela Ciencias', 'precio' => 2200000, 'tipo' => 'Trimestre', 'periodos' => 3, 'meses' => 9, 'activo' => true],
                ['nombre' => 'Asistente Lab. Clínico Veterinario', 'escuela' => 'Escuela Ciencias', 'precio' => 1700000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Electricista', 'escuela' => 'Escuela Ciencias', 'precio' => 1500000, 'tipo' => 'Semestre', 'periodos' => 2, 'meses' => 12, 'activo' => true],
                ['nombre' => 'Electromecánica', 'escuela' => 'Escuela Ciencias', 'precio' => 2100000, 'tipo' => 'Semestre', 'periodos' => 4, 'meses' => 24, 'activo' => true],
                ['nombre' => 'Mecánica y Electrónica de Motos', 'escuela' => 'Escuela Ciencias', 'precio' => 1800000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Inglés', 'escuela' => 'Escuela de Educación e Idiomas', 'precio' => 1600000, 'tipo' => 'Trimestre', 'periodos' => 4, 'meses' => 12, 'activo' => true],
                ['nombre' => 'Francés', 'escuela' => 'Escuela de Educación e Idiomas', 'precio' => 1600000, 'tipo' => 'Trimestre', 'periodos' => 4, 'meses' => 12, 'activo' => true],
                ['nombre' => 'Asistente de Preescolar', 'escuela' => 'Escuela de Educación e Idiomas', 'precio' => 1700000, 'tipo' => 'Semestre', 'periodos' => 3, 'meses' => 18, 'activo' => true],
                ['nombre' => 'Barbería', 'escuela' => 'Escuela de Belleza', 'precio' => 1300000, 'tipo' => 'Semestre', 'periodos' => 2, 'meses' => 12, 'activo' => true],
            ];

            $asignaciones = [
                'Bogotá' => [0,1,2,3,4,5,6,7,8,9,10,12,13,16,17,20,21,22,23,24,25,26],
                'Sasaima' => [1,3,9,10,12,13,14,20,25,26],
                'Guaduas' => [1,3,9,11,13,14,15,20,23,26],
                'La Dorada' => [0,1,2,3,8,9,17,19,20,26],
            ];

            $periodosPorPrograma = [
                'Servicios Geriátricos' => [
                    ['numero' => 1, 'nombre' => 'Semestre 1', 'inicio' => '3 feb 2026', 'fin' => '19 jun 2026'],
                    ['numero' => 2, 'nombre' => 'Semestre 2', 'inicio' => '6 jul 2026', 'fin' => '20 nov 2026'],
                    ['numero' => 3, 'nombre' => 'Semestre 3', 'inicio' => '2 feb 2027', 'fin' => '18 jun 2027'],
                    ['numero' => 4, 'nombre' => 'Semestre 4', 'inicio' => '5 jul 2027', 'fin' => '19 nov 2027'],
                ],
                'Sommelier' => [
                    ['numero' => 1, 'nombre' => 'Trimestre 1', 'inicio' => '3 feb 2026', 'fin' => '24 abr 2026'],
                    ['numero' => 2, 'nombre' => 'Trimestre 2', 'inicio' => '4 may 2026', 'fin' => '24 jul 2026'],
                    ['numero' => 3, 'nombre' => 'Trimestre 3', 'inicio' => '3 ago 2026', 'fin' => '23 oct 2026'],
                ],
            ];
        @endphp

        <div class="app-tabs" role="tablist">
            <button type="button" class="app-tab is-active" data-tab="sedes">Sedes</button>
            <button type="button" class="app-tab" data-tab="escuelas">Escuelas</button>
            <button type="button" class="app-tab" data-tab="programas">Programas</button>
            <button type="button" class="app-tab" data-tab="asignaciones">Asignaciones</button>
            <button type="button" class="app-tab" data-tab="periodos">Periodos académicos</button>
        </div>

        <div class="app-tab-panel app-panel" data-tab-panel="escuelas" hidden>
            <div class="app-panel-heading">
                <div class="app-search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" placeholder="Buscar escuela..." data-table-search="escuelas">
                </div>
                <button type="button" class="app-btn-primary" data-open-modal="modal-escuela">Nueva escuela</button>
            </div>

            <div class="app-table-scroll app-table-scroll-tall">
                <table class="app-table" data-table="escuelas" data-per-page="15">
                    <thead>
                        <tr>
                            <th>Escuela</th>
                            <th>Programas</th>
                            <th class="app-table-actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($escuelas as $escuela)
                            <tr data-nombre="{{ $escuela }}">
                                <td class="app-table-strong">{{ $escuela }}</td>
                                <td>{{ collect($programas)->where('escuela', $escuela)->count() }} programas</td>
                                <td class="app-table-actions">
                                    <button type="button" class="app-icon-btn" data-edit-modal="escuela" aria-label="Editar {{ $escuela }}">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('superadmin.partials.table-pagination', ['name' => 'escuelas'])
        </div>

        <div class="app-tab-panel app-panel" data-tab-panel="programas" hidden>
            <div class="app-panel-heading">
                <div class="app-search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" placeholder="Buscar programa..." data-table-search="programas">
                </div>
                <div class="app-panel-heading-actions">
                    <select class="app-select" data-filter-escuela data-table-target="programas">
                        <option value="todas">Todas las escuelas</option>
                        @foreach ($escuelas as $escuela)
                            <option value="{{ $escuela }}">{{ $escuela }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="app-btn-primary" data-open-modal="modal-programa">Nuevo programa</button>
                </div>
            </div>

            <div class="app-table-scroll app-table-scroll-tall">
                <table class="app-table" data-table="programas" data-per-page="15">
                    <thead>
                        <tr>
                            <th>Programa</th>
                            <th>Escuela</th>
                            <th>Precio total</th>
                            <th>Duración</th>
                            <th>Estado</th>
                            <th class="app-table-actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($programas as $programa)
                            <tr
                                data-escuela="{{ $programa['escuela'] }}"
                                data-nombre="{{ $programa['nombre'] }}"
                                data-precio="{{ $programa['precio'] }}"
                                data-tipo="{{ $programa['tipo'] }}"
                                data-periodos="{{ $programa['periodos'] }}"
                                data-meses="{{ $programa['meses'] }}"
                            >
                                <td class="app-table-strong">{{ $programa['nombre'] }}</td>
                                <td>{{ $programa['escuela'] }}</td>
                                <td>${{ number_format($programa['precio'], 0, ',', '.') }}</td>
                                <td>{{ $programa['periodos'] }} {{ strtolower($programa['tipo']) }}s · {{ $programa['meses'] }} meses</td>
                                <td><span class="app-status-tag app-status-active">Activo</span></td>
                                <td class="app-table-actions">
                                    <button type="button" class="app-icon-btn" data-edit-modal="programa" aria-label="Editar {{ $programa['nombre'] }}">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('superadmin.partials.table-pagination', ['name' => 'programas'])
        </div>

        <div class="app-tab-panel app-panel" data-tab-panel="asignaciones" hidden>
            <div class="app-panel-heading">
                <h3>Programas por sede</h3>
            </div>

            <div class="app-sede-pills">
                @foreach ($sedes as $i => $sede)
                    <button type="button" class="app-sede-pill {{ $i === 0 ? 'is-active' : '' }}" data-sede-pill="{{ $sede['nombre'] }}">{{ $sede['nombre'] }}</button>
                @endforeach
            </div>

            <div class="app-table-scroll app-table-scroll-tall">
                <ul class="app-assign-list">
                    @foreach ($programas as $i => $programa)
                        <li class="app-assign-row">
                            <label>
                                <input type="checkbox" data-programa-index="{{ $i }}">
                                <span>{{ $programa['nombre'] }}</span>
                            </label>
                            <span class="app-assign-escuela">{{ $programa['escuela'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="app-save-bar">
                <button type="button" class="app-btn-primary" data-save-asignaciones>Guardar cambios</button>
                <span class="app-save-confirm" data-save-confirm hidden>
                    <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                    Cambios guardados
                </span>
            </div>

            <script type="application/json" id="app-asignaciones-data">{!! json_encode($asignaciones) !!}</script>
        </div>

        <div class="app-tab-panel app-panel" data-tab-panel="periodos" hidden>
            <div class="app-panel-heading">
                <h3>Periodos académicos</h3>
                <select class="app-select" data-select-programa>
                    @foreach ($programas as $programa)
                        <option value="{{ $programa['nombre'] }}">{{ $programa['nombre'] }}</option>
                    @endforeach
                </select>
            </div>

            @foreach ($programas as $programa)
                @php
                    $periodosDeEste = $periodosPorPrograma[$programa['nombre']] ?? array_map(
                        fn ($numero) => ['numero' => $numero, 'nombre' => "{$programa['tipo']} {$numero}", 'inicio' => null, 'fin' => null],
                        range(1, $programa['periodos'])
                    );
                @endphp
                <div class="app-periodos-set" data-periodos-for="{{ $programa['nombre'] }}" @if(!$loop->first) hidden @endif>
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Periodo</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th class="app-table-actions-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($periodosDeEste as $periodo)
                                <tr data-nombre="{{ $periodo['nombre'] }}" data-inicio="{{ $periodo['inicio'] }}" data-fin="{{ $periodo['fin'] }}">
                                    <td>{{ $periodo['numero'] }}</td>
                                    <td class="app-table-strong">{{ $periodo['nombre'] }}</td>
                                    <td>
                                        @if ($periodo['inicio'])
                                            {{ $periodo['inicio'] }}
                                        @else
                                            <span class="app-table-empty">Sin definir</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($periodo['fin'])
                                            {{ $periodo['fin'] }}
                                        @else
                                            <span class="app-table-empty">Sin definir</span>
                                        @endif
                                    </td>
                                    <td class="app-table-actions">
                                        <button type="button" class="app-icon-btn" data-edit-modal="periodo" aria-label="Editar {{ $periodo['nombre'] }}">
                                            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

        <div class="app-modal-backdrop" data-modal="modal-escuela" hidden>
            <div class="app-modal">
                <div class="app-modal-header">
                    <h3 data-modal-title data-create-title="Nueva escuela" data-edit-title="Editar escuela">Nueva escuela</h3>
                    <button type="button" class="app-modal-close" data-close-modal aria-label="Cerrar">
                        <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="app-form-grid">
                    <div class="app-field app-field-wide">
                        <label>Nombre de la escuela</label>
                        <input type="text" id="escuela-nombre" placeholder="Ej. Escuela de Tecnología">
                    </div>
                </div>
                <div class="app-modal-actions">
                    <button type="button" class="app-btn-secondary" data-close-modal>Cancelar</button>
                    <button type="button" class="app-btn-primary" data-close-modal data-modal-save data-create-label="Guardar escuela" data-edit-label="Guardar cambios">Guardar escuela</button>
                </div>
            </div>
        </div>

        <div class="app-modal-backdrop" data-modal="modal-programa" hidden>
            <div class="app-modal">
                <div class="app-modal-header">
                    <h3 data-modal-title data-create-title="Nuevo programa" data-edit-title="Editar programa">Nuevo programa</h3>
                    <button type="button" class="app-modal-close" data-close-modal aria-label="Cerrar">
                        <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="app-form-grid">
                    <div class="app-field app-field-wide">
                        <label>Nombre del programa</label>
                        <input type="text" id="programa-nombre" placeholder="Ej. Asistente Administrativo">
                    </div>
                    <div class="app-field">
                        <label>Escuela</label>
                        <select id="programa-escuela">
                            @foreach ($escuelas as $escuela)
                                <option>{{ $escuela }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="app-field">
                        <label>Precio total</label>
                        <input type="text" id="programa-precio" placeholder="$1.800.000">
                    </div>
                    <div class="app-field">
                        <label>Tipo de periodo</label>
                        <select id="programa-tipo">
                            <option>Semestre</option>
                            <option>Trimestre</option>
                        </select>
                    </div>
                    <div class="app-field">
                        <label>Duración (en periodos)</label>
                        <input type="number" id="programa-periodos" placeholder="3">
                    </div>
                    <div class="app-field app-field-wide">
                        <label>Duración total (en meses)</label>
                        <input type="number" id="programa-meses" placeholder="18">
                    </div>
                </div>
                <div class="app-modal-actions">
                    <button type="button" class="app-btn-secondary" data-close-modal>Cancelar</button>
                    <button type="button" class="app-btn-primary" data-close-modal data-modal-save data-create-label="Guardar programa" data-edit-label="Guardar cambios">Guardar programa</button>
                </div>
            </div>
        </div>

        <div class="app-modal-backdrop" data-modal="modal-periodo" hidden>
            <div class="app-modal">
                <div class="app-modal-header">
                    <h3 data-modal-title data-create-title="Nuevo periodo" data-edit-title="Editar periodo">Nuevo periodo</h3>
                    <button type="button" class="app-modal-close" data-close-modal aria-label="Cerrar">
                        <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="app-form-grid">
                    <div class="app-field app-field-wide">
                        <label>Nombre del periodo</label>
                        <input type="text" id="periodo-nombre" placeholder="Ej. Semestre 1">
                    </div>
                    <div class="app-field">
                        <label>Fecha de inicio</label>
                        <input type="text" id="periodo-inicio" placeholder="3 feb 2026">
                    </div>
                    <div class="app-field">
                        <label>Fecha de fin</label>
                        <input type="text" id="periodo-fin" placeholder="19 jun 2026">
                    </div>
                </div>
                <div class="app-modal-actions">
                    <button type="button" class="app-btn-secondary" data-close-modal>Cancelar</button>
                    <button type="button" class="app-btn-primary" data-close-modal data-modal-save data-create-label="Guardar periodo" data-edit-label="Guardar cambios">Guardar periodo</button>
                </div>
            </div>
        </div>
    --}}
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/superadmin/sedes-programas.js') }}" defer></script>
@endpush
