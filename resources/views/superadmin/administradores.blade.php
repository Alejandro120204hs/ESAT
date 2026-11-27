@extends('superadmin.layout')

{{-- Superadmin › Admin de ESAT: la cuenta del Admin (rol admin_general, una sola, sin sede). El Admin crea las
     secretarías de cada sede desde su panel. Todo sale de la BD (AdministradorController); nada de ejemplo. --}}

@section('title', 'Admin de ESAT')
@section('page-title', 'Admin de ESAT')
@section('active', 'administradores')
@section('content-class', 'app-adm-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/administradores.css') }}">
@endpush

@section('content')
    {{-- Lo que se guardó antes de recargar (administradores.js lo deja en sessionStorage) --}}
    <div class="app-alert app-alert-success" data-aviso role="status" hidden></div>
    <p class="app-page-intro">La cuenta del Admin, que maneja toda ESAT: sedes, administrativos, carreras, personas y finanzas. Los administrativos de cada sede los crea el Admin desde su panel.</p>

    <div class="app-panel app-list-panel">
        @if ($admin)
            <div class="app-adm-cuenta"
                data-id="{{ $admin->id }}"
                data-activo="{{ $admin->activo ? '1' : '0' }}"
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
            >
                <div class="app-adm-persona">
                    <span class="app-adm-avatar{{ $admin->activo ? '' : ' is-off' }}" aria-hidden="true">{{ mb_substr($admin->nombres, 0, 1) }}{{ mb_substr($admin->apellidos, 0, 1) }}</span>
                    <div class="app-adm-id">
                        <h2>{{ $admin->nombres }} {{ $admin->apellidos }}</h2>
                        <span class="app-adm-estado {{ $admin->activo ? 'is-activo' : '' }}">{{ $admin->activo ? 'Activa' : 'Inactiva: no puede ingresar' }}</span>
                    </div>
                </div>

                <dl class="app-adm-datos">
                    <div><dt>Documento</dt><dd>{{ $admin->tipo_documento }} {{ $admin->numero_documento }}</dd></div>
                    <div><dt>Correo (usuario)</dt><dd>{{ $admin->email }}</dd></div>
                    <div><dt>Celular</dt><dd>{{ $admin->telefono ?: 'Sin registrar' }}</dd></div>
                    <div><dt>Último ingreso</dt><dd>{{ $admin->ultimo_acceso ? $admin->ultimo_acceso->format('d/m/Y g:i a') : 'Nunca ha ingresado' }}</dd></div>
                </dl>

                <div class="app-adm-acciones">
                    <button type="button" class="app-btn-secondary" data-edit-wizard>Editar datos</button>
                    <button type="button" class="app-btn-secondary" data-confirmar="clave">Restablecer clave</button>
                    <button type="button" class="app-btn-secondary" data-confirmar="estado">{{ $admin->activo ? 'Desactivar cuenta' : 'Activar cuenta' }}</button>
                </div>
            </div>
        @else
            <div class="app-adm-vacio">
                <h2>Todavía no hay cuenta de Admin</h2>
                <p>Créala para que alguien maneje ESAT y cree los administrativos de cada sede.</p>
                <button type="button" class="app-btn-primary" data-open-wizard>Crear la cuenta del Admin</button>
            </div>
        @endif
    </div>

    {{-- ============ CONFIRMAR: RESTABLECER CLAVE / ACTIVAR O DESACTIVAR ============ --}}
    <div class="app-modal-backdrop" data-modal="modal-confirmar" hidden>
        <div class="app-modal" role="dialog" aria-modal="true" aria-labelledby="adm-confirmar-titulo">
            <div class="app-modal-header">
                <h3 id="adm-confirmar-titulo" data-confirmar-titulo></h3>
                <button type="button" class="app-modal-close" data-close-confirmar aria-label="Cerrar">
                    <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="app-adm-confirmar-texto" data-confirmar-texto></p>
            <p class="app-field-error app-adm-error" data-confirmar-error role="alert"></p>
            <div class="app-wizard-footer">
                <button type="button" class="app-btn-secondary" data-close-confirmar>Cancelar</button>
                <button type="button" class="app-btn-primary" data-confirmar-ok></button>
            </div>
        </div>
    </div>

    {{-- ============ ASISTENTE: CREAR / EDITAR LA CUENTA DEL ADMIN ============ --}}
    <div class="app-modal-backdrop" data-modal="modal-administrador" hidden>
        <div class="app-modal app-modal-wizard" role="dialog" aria-modal="true" aria-labelledby="adm-wizard-titulo">
            <div class="app-modal-header">
                <h3 id="adm-wizard-titulo" data-wizard-title data-create-title="Crear la cuenta del Admin" data-edit-title="Editar la cuenta del Admin">Crear la cuenta del Admin</h3>
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
                    <span class="app-wizard-step-label">Identificación y contacto</span>
                </div>
                <div class="app-wizard-step" data-wizard-step-indicator="3">
                    <span class="app-wizard-step-num">3</span>
                    <span class="app-wizard-step-label">Confirmación</span>
                </div>
            </div>

            <div class="app-wizard-body">
                {{-- Paso 1: Datos personales --}}
                <div class="app-wizard-panel" data-wizard-panel="1">
                    <div class="app-form-grid">
                        <div class="app-field app-field-wide">
                            <label for="administrador-nombres">Nombres</label>
                            <input type="text" id="administrador-nombres" data-required maxlength="100" placeholder="Ej. Andrea">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field app-field-wide">
                            <label for="administrador-apellidos">Apellidos</label>
                            <input type="text" id="administrador-apellidos" data-required maxlength="100" placeholder="Ej. Ramírez Soto">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label for="administrador-genero">Género</label>
                            <select id="administrador-genero" data-required>
                                <option value="" selected disabled>Selecciona</option>
                                <option value="femenino">Femenino</option>
                                <option value="masculino">Masculino</option>
                                <option value="otro">Otro</option>
                            </select>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label for="administrador-fecha_nacimiento">Fecha de nacimiento</label>
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

                {{-- Paso 2: Identificación y contacto --}}
                <div class="app-wizard-panel" data-wizard-panel="2" hidden>
                    <div class="app-form-grid">
                        <div class="app-field">
                            <label for="administrador-tipo_documento">Tipo de documento</label>
                            <select id="administrador-tipo_documento" data-required>
                                <option value="" selected disabled>Selecciona</option>
                                @foreach ($tipos as $valor => $texto)
                                    <option value="{{ $valor }}">{{ $texto }}</option>
                                @endforeach
                            </select>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label for="administrador-numero_documento">Número de documento</label>
                            <input type="text" id="administrador-numero_documento" data-required maxlength="15" placeholder="Solo números (pasaporte: letras y números)">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label for="administrador-telefono">Celular</label>
                            <input type="text" id="administrador-telefono" data-required inputmode="numeric" maxlength="14" placeholder="3001234567">
                            <span class="app-field-error" data-field-error></span>
                        </div>
                        <div class="app-field">
                            <label for="administrador-correo">Correo electrónico</label>
                            <input type="email" id="administrador-correo" data-required maxlength="255" placeholder="nombre@esat.edu.co">
                            <span class="app-field-hint">Es su usuario para ingresar.</span>
                            <span class="app-field-error" data-field-error></span>
                        </div>
                    </div>
                </div>

                {{-- Paso 3: Confirmación --}}
                <div class="app-wizard-panel" data-wizard-panel="3" hidden>
                    <div class="app-wizard-summary app-wizard-summary--solo">
                        <h4>Resumen</h4>
                        <dl data-wizard-summary-list></dl>
                    </div>

                    <div class="app-wizard-note" data-wizard-note-crear>
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
                        <p>La contraseña inicial será su número de documento. Podrá cambiarla luego desde su perfil.</p>
                    </div>
                    <p class="app-field-error app-adm-error" data-wizard-error role="alert"></p>
                </div>
            </div>

            <div class="app-wizard-footer">
                <button type="button" class="app-btn-secondary" data-wizard-back hidden>Atrás</button>
                <span class="app-wizard-progress" data-wizard-progress>Paso 1 de 3</span>
                <button type="button" class="app-btn-primary" data-wizard-next>Siguiente</button>
                <button type="button" class="app-btn-primary" data-wizard-finish data-create-label="Crear la cuenta" data-edit-label="Guardar cambios" hidden>Crear la cuenta</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/superadmin/administradores.js') }}" defer></script>
@endpush
