@extends('superadmin.layout')

@section('title', 'Administradores')
@section('page-title', 'Administradores')
@section('active', 'administradores')
@section('content-class', 'app-adm-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/administradores.css') }}">
@endpush

@section('content')
    <p class="app-page-intro">Aquí podrás crear nuevas cuentas de administrador, asignarlas a una sede y activarlas o desactivarlas.</p>

    <div class="app-panel app-list-panel">
        <div class="app-panel-heading">
            <div class="app-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" placeholder="Buscar administrador..." data-table-search="administradores">
            </div>
            <button type="button" class="app-btn-primary" data-open-wizard>Nuevo administrador</button>
        </div>

        <div class="app-table-scroll app-table-scroll-tall">
            <table class="app-table" data-table="administradores" data-per-page="15">
                <thead>
                    <tr>
                        <th>Administrador</th>
                        <th>Documento</th>
                        <th>Sede</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th class="app-table-actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($administradores as $admin)
                        <tr
                            data-id="{{ $admin->id }}"
                            data-nombres="{{ $admin->nombres }}"
                            data-apellidos="{{ $admin->apellidos }}"
                            data-genero="{{ $admin->genero }}"
                            data-fecha_nacimiento="{{ $admin->fecha_nacimiento?->format('Y-m-d') }}"
                            data-departamento_nacimiento="{{ $admin->departamento_nacimiento }}"
                            data-ciudad_nacimiento="{{ $admin->lugar_nacimiento }}"
                            data-tipo_documento="{{ $admin->tipo_documento }}"
                            data-numero_documento="{{ $admin->numero_documento }}"
                            data-telefono="{{ $admin->telefono }}"
                            data-correo="{{ $admin->email }}"
                            data-sede="{{ $admin->sede_id }}"
                        >
                            <td class="app-table-strong">{{ $admin->nombres }} {{ $admin->apellidos }}</td>
                            <td>{{ $admin->tipo_documento }} {{ $admin->numero_documento }}</td>
                            <td>{{ $admin->sede?->nombre ?? '—' }}</td>
                            <td>{{ $admin->email }}</td>
                            <td>{{ $admin->telefono }}</td>
                            <td>
                                <button type="button" class="app-status-toggle {{ $admin->activo ? 'is-active' : '' }}" data-status-toggle aria-pressed="{{ $admin->activo ? 'true' : 'false' }}">
                                    <span class="app-status-toggle-dot"></span>
                                    <span data-status-label>{{ $admin->activo ? 'Activo' : 'Inactivo' }}</span>
                                </button>
                            </td>
                            <td class="app-table-actions">
                                <button type="button" class="app-icon-btn" data-edit-wizard aria-label="Editar {{ $admin->nombres }}">
                                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    @if ($administradores->isEmpty())
                        <tr><td colspan="7" class="app-table-empty-row">No hay administradores registrados todavía.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        @include('superadmin.partials.table-pagination', ['name' => 'administradores'])
    </div>

    {{-- ============ ASISTENTE: NUEVO / EDITAR ADMINISTRADOR ============ --}}
    <div class="app-modal-backdrop" data-modal="modal-administrador" hidden>
        <div class="app-modal app-modal-wizard">
            <div class="app-modal-header">
                <h3 data-wizard-title data-create-title="Nuevo administrador" data-edit-title="Editar administrador">Nuevo administrador</h3>
                <button type="button" class="app-modal-close" data-close-wizard aria-label="Cerrar">
                    <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="app-wizard-steps">
                <div class="app-wizard-step is-active" data-wizard-step-indicator="1">
                    <span class="app-wizard-step-num">1</span>
                    <span class="app-wizard-step-label">Datos personales</span>
                </div>
                <div class="app-wizard-step" data-wizard-step-indicator="2">
                    <span class="app-wizard-step-num">2</span>
                    <span class="app-wizard-step-label">Identificación</span>
                </div>
                <div class="app-wizard-step" data-wizard-step-indicator="3">
                    <span class="app-wizard-step-num">3</span>
                    <span class="app-wizard-step-label">Contacto</span>
                </div>
                <div class="app-wizard-step" data-wizard-step-indicator="4">
                    <span class="app-wizard-step-num">4</span>
                    <span class="app-wizard-step-label">Sede y confirmación</span>
                </div>
            </div>

            <div class="app-wizard-body">
                {{-- Paso 1: Datos personales --}}
                <div class="app-wizard-panel" data-wizard-panel="1">
                    <div class="app-form-grid">
                        <div class="app-field app-field-wide">
                            <label>Nombres</label>
                            <input type="text" id="administrador-nombres" data-required placeholder="Ej. Andrea">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field app-field-wide">
                            <label>Apellidos</label>
                            <input type="text" id="administrador-apellidos" data-required placeholder="Ej. Ramírez Soto">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label>Género</label>
                            <select id="administrador-genero" data-required>
                                <option value="" selected disabled>Selecciona</option>
                                <option value="femenino">Femenino</option>
                                <option value="masculino">Masculino</option>
                                <option value="otro">Otro</option>
                            </select>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label>Fecha de nacimiento</label>
                            <input type="date" id="administrador-fecha_nacimiento" data-required data-edad-input>
                            <span class="app-field-hint" data-edad-preview></span>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label>Departamento de nacimiento</label>
                            <div class="app-combobox" data-combobox="departamento_nacimiento">
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
                                <input type="hidden" id="administrador-departamento_nacimiento" data-required data-combobox-value>
                            </div>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label>Ciudad de nacimiento</label>
                            <div class="app-combobox" data-combobox="ciudad_nacimiento">
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
                                <input type="hidden" id="administrador-ciudad_nacimiento" data-required data-combobox-value>
                            </div>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                    </div>
                </div>

                {{-- Paso 2: Identificación --}}
                <div class="app-wizard-panel" data-wizard-panel="2" hidden>
                    <div class="app-form-grid">
                        <div class="app-field">
                            <label>Tipo de documento</label>
                            <select id="administrador-tipo_documento" data-required>
                                <option value="" selected disabled>Selecciona</option>
                                <option value="CC">Cédula de ciudadanía</option>
                                <option value="TI">Tarjeta de identidad</option>
                                <option value="CE">Cédula de extranjería</option>
                                <option value="PA">Pasaporte</option>
                            </select>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label>Número de documento</label>
                            <input type="text" id="administrador-numero_documento" data-required placeholder="1070942496">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                    </div>
                </div>

                {{-- Paso 3: Contacto --}}
                <div class="app-wizard-panel" data-wizard-panel="3" hidden>
                    <div class="app-form-grid">
                        <div class="app-field">
                            <label>Teléfono</label>
                            <input type="text" id="administrador-telefono" data-required placeholder="3001234567">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label>Correo electrónico</label>
                            <input type="email" id="administrador-correo" data-required placeholder="nombre@esat.edu.co">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                    </div>
                </div>

                {{-- Paso 4: Sede y confirmación --}}
                <div class="app-wizard-panel" data-wizard-panel="4" hidden>
                    <div class="app-form-grid">
                        <div class="app-field app-field-wide">
                            <label>Sede asignada</label>
                            <select id="administrador-sede" data-required>
                                <option value="" selected disabled>Selecciona una sede</option>
                                @foreach ($sedes as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                                @endforeach
                            </select>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                    </div>

                    <div class="app-wizard-summary">
                        <h4>Resumen</h4>
                        <dl data-wizard-summary-list></dl>
                    </div>

                    <div class="app-wizard-note">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
                        <p>La contraseña inicial de esta cuenta será su número de documento. Podrá cambiarla luego desde su perfil.</p>
                    </div>
                </div>
            </div>

            <div class="app-wizard-footer">
                <button type="button" class="app-btn-secondary" data-wizard-back hidden>Atrás</button>
                <span class="app-wizard-progress" data-wizard-progress>Paso 1 de 4</span>
                <button type="button" class="app-btn-primary" data-wizard-next>Siguiente</button>
                <button type="button" class="app-btn-primary" data-wizard-finish data-create-label="Crear administrador" data-edit-label="Guardar cambios" hidden>Crear administrador</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/superadmin/administradores.js') }}" defer></script>
@endpush
