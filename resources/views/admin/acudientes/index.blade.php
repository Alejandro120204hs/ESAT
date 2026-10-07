{{-- Panel Admin › Acudientes (backend real: Admin/Acudientes/AcudienteController + AcudienteService).
     La persona responsable y sus estudiantes a cargo. Usuario del portal = correo, clave inicial = documento.
     Cada estudiante tiene un solo acudiente y ningún menor de edad queda sin acudiente.
     Programa, grupo, pagos y asistencia de los estudiantes solo se consultan aquí. --}}
@extends('admin.layout')

@section('title', 'Acudientes')
@section('page-title', 'Acudientes')
@section('active', 'acudientes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/acudientes.css') }}?v={{ filemtime(public_path('assets/css/admin/acudientes.css')) }}">
@endpush

@section('content')
<div class="acu-wrap">

    {{-- Encabezado (misma estructura que Docentes y Estudiantes) --}}
    <div class="acu-header">
        <div>
            <h2 class="acu-title">Acudientes</h2>
            <p class="acu-sub">Aquí podrás registrar a las personas responsables de los estudiantes, vincularlas con uno o varios estudiantes y consultar su ficha. Ingresan al portal de acudientes con su correo para seguir notas, asistencia y pagos.</p>
        </div>
        <button class="acu-btn acu-btn-primary" id="acu-btn-nuevo" type="button">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Registrar acudiente
        </button>
    </div>

    {{-- KPIs (mismo formato que Docentes; valores calculados por acudientes.js) --}}
    <div class="acu-kpis">
        <div class="acu-kpi-chip acu-kpi-blue">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-total">0</div>
                <div class="acu-kpi-lbl">Acudientes</div>
            </div>
        </div>
        <div class="acu-kpi-chip acu-kpi-green">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-portal">0</div>
                <div class="acu-kpi-lbl">Usan el portal</div>
            </div>
        </div>
        <div class="acu-kpi-chip acu-kpi-orange">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-menores">0</div>
                <div class="acu-kpi-lbl">Menores sin acudiente</div>
            </div>
        </div>
        <div class="acu-kpi-chip acu-kpi-navy">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-estudiantes">0</div>
                <div class="acu-kpi-lbl">Estudiantes a cargo</div>
            </div>
        </div>
    </div>

    {{-- Filtros (misma fila que Docentes) --}}
    <div class="acu-toolbar">
        <div class="acu-filtros">
            <div class="acu-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="acu-search" id="acu-search" type="search" placeholder="Acudiente, cédula o estudiante..." aria-label="Buscar acudiente" autocomplete="off">
            </div>
            <div class="app-combobox" id="acu-cb-programa">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los programas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="acu-val-programa" data-combobox-value>
            </div>
            <div class="app-combobox" id="acu-cb-cuenta">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las cuentas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="acu-val-cuenta" data-combobox-value>
            </div>
            <div class="app-combobox" id="acu-cb-alerta">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las alertas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="acu-val-alerta" data-combobox-value>
            </div>
            <span class="acu-count-badge" id="acu-count" aria-live="polite"></span>
        </div>
    </div>

    {{-- Tabla (generada por acudientes.js) --}}
    <div class="acu-lista-wrap" id="acu-lista-wrap">
        <table class="acu-lista">
            <thead>
                <tr>
                    <th>Acudiente</th>
                    <th>Estudiantes a cargo</th>
                    <th>Cuenta</th>
                    <th>Alertas</th>
                    <th>Contacto</th>
                    <th><span class="acu-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="acu-lista-body"></tbody>
        </table>
    </div>

    <div class="acu-empty" id="acu-empty" hidden>
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span class="acu-empty-ttl">No hay acudientes con estos filtros</span>
        <span class="acu-empty-sub">Prueba con otro nombre, cédula o estudiante, o quita el filtro de programa, cuenta o alerta.</span>
        <button type="button" class="acu-btn acu-btn-ghost" id="acu-limpiar">Quitar filtros</button>
    </div>

    <nav class="acu-pagination" id="acu-pagination" aria-label="Paginación" hidden>
        <button type="button" class="acu-pg-btn" id="acu-pg-prev">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="acu-pg-pages" id="acu-pg-pages"></div>
        <button type="button" class="acu-pg-btn" id="acu-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </nav>

</div>

{{-- ══════════ Ficha del acudiente (panel lateral) ══════════ --}}
<div class="acu-drawer" id="acu-drawer" aria-hidden="true">
    <div class="acu-drawer-overlay" id="acu-drawer-overlay"></div>
    <aside class="acu-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="acu-fi-nombre">
        <div class="acu-drawer-head">
            <div class="acu-drawer-head-left">
                <div class="acu-drawer-avatar" id="acu-fi-avatar" aria-hidden="true"></div>
                <div class="acu-drawer-head-info">
                    <h3 class="acu-drawer-title" id="acu-fi-nombre"></h3>
                    <div class="acu-drawer-doc" id="acu-fi-doc"></div>
                    <div class="acu-drawer-badges" id="acu-fi-badges"></div>
                </div>
            </div>
            <button type="button" class="acu-icon-btn" id="acu-drawer-close" aria-label="Cerrar ficha">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="acu-fi-tabs" id="acu-fi-tabs" role="tablist">
            <button type="button" class="acu-fi-tab is-active" data-tab="datos" role="tab">General</button>
            <button type="button" class="acu-fi-tab" data-tab="estudiantes" role="tab">Estudiantes a cargo</button>
            <button type="button" class="acu-fi-tab" data-tab="cuenta" role="tab">Cuenta</button>
        </div>

        {{-- Vista principal de la ficha --}}
        <div class="acu-drawer-body" id="acu-ficha">
            <div class="acu-fi-panel" id="acu-fi-panel"></div>
        </div>

        {{-- Vista de acción (vincular / desvincular / cuenta / contraseña) dentro de la misma ficha --}}
        <div class="acu-drawer-body" id="acu-accion" hidden>
            <button type="button" class="acu-back" id="acu-accion-volver">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Volver a la ficha
            </button>
            <h3 class="acu-accion-ttl" id="acu-accion-ttl"></h3>
            <p class="acu-accion-sub" id="acu-accion-sub"></p>
            <div id="acu-accion-cuerpo"></div>
            <div class="acu-form-error" id="acu-accion-error" role="alert" hidden></div>
        </div>

        <div class="acu-drawer-foot" id="acu-ficha-foot">
            <button type="button" class="acu-btn acu-btn-secondary" data-accion="editar">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Editar datos
            </button>
            <button type="button" class="acu-btn acu-btn-secondary" data-accion="clave">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Restablecer contraseña
            </button>
            <button type="button" class="acu-btn acu-btn-danger" data-accion="cuenta" id="acu-btn-cuenta">Desactivar cuenta</button>
        </div>
        <div class="acu-drawer-foot" id="acu-accion-foot" hidden>
            <button type="button" class="acu-btn acu-btn-ghost" id="acu-accion-cancelar">Cancelar</button>
            <button type="button" class="acu-btn acu-btn-primary" id="acu-accion-confirmar">Confirmar</button>
        </div>
    </aside>
</div>

{{-- ══════════ Asistente: Registrar / editar acudiente ══════════ --}}
<div class="acu-modal" id="acu-modal" aria-hidden="true">
    <div class="acu-modal-overlay" id="acu-modal-overlay"></div>
    <div class="acu-modal-box" role="dialog" aria-modal="true" aria-labelledby="acu-modal-ttl">
        <div class="acu-modal-head">
            <div>
                <h3 class="acu-modal-ttl" id="acu-modal-ttl">Registrar acudiente</h3>
                <span class="acu-modal-paso" id="acu-modal-paso">Paso 1 de 3</span>
            </div>
            <button type="button" class="acu-icon-btn" id="acu-modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Indicador de pasos (mismo formato que el asistente de Docentes) --}}
        <div class="acu-wsteps" id="acu-pasos">
            <div class="acu-wstep is-active" data-step="1" title="Paso 1: Datos personales"><div class="acu-wstep-dot"></div><span>Datos personales</span></div>
            <div class="acu-wstep-line"></div>
            <div class="acu-wstep" data-step="2" title="Paso 2: Contacto"><div class="acu-wstep-dot"></div><span>Contacto</span></div>
            <div class="acu-wstep-line"></div>
            <div class="acu-wstep" data-step="3" title="Paso 3: Estudiantes a cargo"><div class="acu-wstep-dot"></div><span>Estudiantes a cargo</span></div>
        </div>

        <div class="acu-modal-body">

            {{-- Paso 1: Datos personales e identificación --}}
            <section class="acu-mstep is-active" data-step="1">
                <div class="acu-form-row">
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-nombres">Nombres *</label>
                        <input class="acu-input" id="reg-nombres" type="text" autocomplete="off" maxlength="100" placeholder="Gloria Inés">
                    </div>
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-apellidos">Apellidos *</label>
                        <input class="acu-input" id="reg-apellidos" type="text" autocomplete="off" maxlength="100" placeholder="Cárdenas Mora">
                    </div>
                </div>
                <div class="acu-form-row">
                    <div class="acu-field">
                        <span class="acu-lbl">Tipo de documento *</span>
                        <div class="app-combobox" id="reg-cb-tipo">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona un tipo</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="reg-tipo" data-combobox-value>
                        </div>
                    </div>
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-documento">Número de documento *</label>
                        <input class="acu-input acu-num" id="reg-documento" type="text" inputmode="numeric" autocomplete="off" placeholder="39645218">
                    </div>
                </div>
                <div class="acu-field">
                    <label class="acu-lbl" for="reg-ocupacion">Ocupación <em>opcional</em></label>
                    <input class="acu-input" id="reg-ocupacion" type="text" maxlength="100" autocomplete="off" placeholder="Comerciante">
                </div>
            </section>

            {{-- Paso 2: Contacto --}}
            <section class="acu-mstep" data-step="2">
                <div class="acu-form-row">
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-email">Correo electrónico *</label>
                        <input class="acu-input" id="reg-email" type="email" maxlength="255" autocomplete="off" placeholder="nombre@correo.com">
                    </div>
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-telefono">Celular *</label>
                        <input class="acu-input acu-num" id="reg-telefono" type="tel" inputmode="numeric" autocomplete="off" placeholder="3XX XXX XXXX">
                    </div>
                </div>
                <div class="acu-field">
                    <label class="acu-lbl" for="reg-direccion">Dirección de residencia *</label>
                    <input class="acu-input" id="reg-direccion" type="text" autocomplete="off" maxlength="255" placeholder="Ej. Calle 7 # 9-24">
                </div>
                <div class="acu-form-row">
                    <div class="acu-field">
                        <span class="acu-lbl">Departamento de residencia *</span>
                        <div class="app-combobox" id="reg-cb-depto">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona un departamento</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar departamento" autocomplete="off">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="reg-depto" data-combobox-value>
                        </div>
                    </div>
                    <div class="acu-field">
                        <span class="acu-lbl">Ciudad de residencia *</span>
                        <div class="app-combobox" id="reg-cb-ciudad">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Elige el departamento primero</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar municipio" autocomplete="off">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="reg-ciudad" data-combobox-value>
                        </div>
                    </div>
                </div>
                <div class="acu-nota" id="reg-nota-clave">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <p>El correo será el usuario del acudiente en el portal y su contraseña inicial, el número de documento. Podrá cambiarla desde su perfil.</p>
                </div>
            </section>

            {{-- Paso 3: Estudiantes a cargo --}}
            <section class="acu-mstep" data-step="3">
                <div class="acu-field">
                    <span class="acu-lbl">Estudiante *</span>
                    <div class="app-combobox" id="reg-cb-estudiante">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                            <span data-combobox-label>Busca por nombre o documento</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <div class="app-combobox-search-wrap">
                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" class="app-combobox-search" data-combobox-search placeholder="Nombre o documento" autocomplete="off">
                            </div>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" id="reg-estudiante" data-combobox-value>
                    </div>
                </div>
                <div class="acu-field" id="reg-par-wrap" hidden>
                    <span class="acu-lbl">Parentesco con el estudiante *</span>
                    <div class="acu-chips" id="reg-parentesco" role="radiogroup" aria-label="Parentesco"></div>
                </div>
                <div id="reg-reemplazo"></div>
                <div class="acu-agregar-row">
                    <button type="button" class="acu-btn acu-btn-navy acu-btn-sm" id="reg-agregar">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Agregar estudiante
                    </button>
                </div>

                <div class="acu-sec">
                    <div class="acu-sec-head">
                        <h4 class="acu-sec-title">Estudiantes vinculados</h4>
                        <span class="acu-sec-dato" id="reg-vinc-count">0</span>
                    </div>
                    <ul class="acu-vinculos" id="reg-vinculos"></ul>
                </div>

                <div class="acu-resumen" id="reg-resumen" hidden></div>
            </section>

            <div class="acu-form-error" id="reg-error" role="alert" hidden></div>
        </div>

        <div class="acu-modal-foot">
            <button type="button" class="acu-btn acu-btn-ghost" id="acu-btn-atras">Atrás</button>
            <button type="button" class="acu-btn acu-btn-primary" id="acu-btn-siguiente">Siguiente</button>
        </div>
    </div>
</div>

{{-- Aviso al guardar --}}
<div class="acu-toast" id="acu-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
    var ACU_DATA = @json($acudientes);
    var ACU_ESTUDIANTES = @json($estudiantes);
    var ACU_GRUPOS = @json($grupos);
    var ACU_OPCIONES = @json($opciones);
</script>
<script src="{{ asset('assets/js/admin/acudientes.js') }}?v={{ filemtime(public_path('assets/js/admin/acudientes.js')) }}" defer></script>
@endpush
