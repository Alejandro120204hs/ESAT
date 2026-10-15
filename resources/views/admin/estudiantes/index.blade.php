{{-- $estudiantes (uno por persona, con su matrícula solo para consulta), $grupos, $acudientes y $opciones
     vienen de Admin/Estudiantes/EstudianteController. Aquí se registra y edita a la persona; matricularla
     (programa, grupo, estado, pagos) es de Matrículas. La edad nunca se guarda: se calcula de la fecha de nacimiento. --}}
@extends('admin.layout')

@section('title', 'Estudiantes')
@section('page-title', 'Estudiantes')
@section('active', 'estudiantes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/estudiantes.css') }}?v={{ filemtime(public_path('assets/css/admin/estudiantes.css')) }}">
@endpush

@section('content')
<div class="est-wrap">

    {{-- Encabezado (misma estructura que Docentes) --}}
    <div class="est-header">
        <div>
            <h2 class="est-title">Estudiantes</h2>
            <p class="est-sub">Aquí podrás registrar estudiantes con su acudiente cuando sean menores de edad, editar sus datos y consultar su ficha completa: documentos, matrícula, pagos y asistencia. Para darles programa y grupo, matricúlalos en Matrículas.</p>
        </div>
        <button class="est-btn est-btn-primary" id="est-btn-registrar" type="button">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Registrar estudiante
        </button>
    </div>

    {{-- KPIs (mismo formato que Docentes; valores calculados por estudiantes.js) --}}
    <div class="est-kpis">
        <div class="est-kpi-chip est-kpi-blue">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-total">0</div>
                <div class="est-kpi-lbl">Estudiantes</div>
            </div>
        </div>
        <div class="est-kpi-chip est-kpi-green">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-activos">0</div>
                <div class="est-kpi-lbl">Activos</div>
            </div>
        </div>
        <div class="est-kpi-chip est-kpi-orange">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-alertas">0</div>
                <div class="est-kpi-lbl">Requieren atención</div>
            </div>
        </div>
        <div class="est-kpi-chip est-kpi-navy">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-programas">0</div>
                <div class="est-kpi-lbl">Programas</div>
            </div>
        </div>
    </div>

    {{-- Filtros (misma fila que Docentes) --}}
    <div class="est-toolbar">
        <div class="est-filtros">
            <div class="est-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="est-search" id="est-search" type="search" placeholder="Nombre o documento..." aria-label="Buscar estudiante" autocomplete="off">
            </div>
            <div class="app-combobox" id="est-cb-programa">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los programas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-programa" data-combobox-value>
            </div>
            <div class="app-combobox" id="est-cb-grupo">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los grupos</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-grupo" data-combobox-value>
            </div>
            <div class="app-combobox" id="est-cb-estado">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los estados</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-estado" data-combobox-value>
            </div>
            <div class="app-combobox" id="est-cb-alerta">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las alertas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-alerta" data-combobox-value>
            </div>
            <span class="est-count-badge" id="est-count" aria-live="polite"></span>
            <button type="button" class="est-btn est-btn-ghost est-btn-sm" id="est-exportar" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
        <div class="est-vista" role="group" aria-label="Vista">
            <button type="button" class="est-vista-btn is-active" data-vista="lista" aria-pressed="true" title="Ver como tabla" aria-label="Ver como tabla">
                <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
            <button type="button" class="est-vista-btn" data-vista="carnes" aria-pressed="false" title="Ver como carnés" aria-label="Ver como carnés">
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c.6-1.4 1.7-2 3-2s2.4.6 3 2"/><line x1="14" y1="10" x2="19" y2="10"/><line x1="14" y1="14" x2="18" y2="14"/></svg>
            </button>
        </div>
    </div>

    {{-- Tabla / carnés (generados por estudiantes.js) --}}
    <div class="est-grid" id="est-grid" hidden></div>
    <div class="est-lista-wrap" id="est-lista-wrap">
        <table class="est-lista">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Escuela</th>
                    <th>Programa y grupo</th>
                    <th>Estado</th>
                    <th>Alertas</th>
                    <th>Contacto</th>
                    <th><span class="est-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="est-lista-body"></tbody>
        </table>
    </div>

    <div class="est-empty" id="est-empty" hidden>
        <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c.6-1.4 1.7-2 3-2s2.4.6 3 2"/><line x1="14" y1="10" x2="19" y2="10"/><line x1="14" y1="14" x2="18" y2="14"/></svg>
        <span class="est-empty-ttl">No hay estudiantes con estos filtros</span>
        <span class="est-empty-sub">Prueba con otro nombre o documento, o quita el filtro de estado, programa o alerta.</span>
        <button type="button" class="est-btn est-btn-ghost" id="est-limpiar">Quitar filtros</button>
    </div>

    <nav class="est-pagination" id="est-pagination" aria-label="Paginación" hidden>
        <button type="button" class="est-pg-btn" id="est-pg-prev">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="est-pg-pages" id="est-pg-pages"></div>
        <button type="button" class="est-pg-btn" id="est-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </nav>

</div>

{{-- ══════════ Ficha del estudiante (panel lateral) ══════════ --}}
<div class="est-drawer" id="est-drawer" aria-hidden="true">
    <div class="est-drawer-overlay" id="est-drawer-overlay"></div>
    <aside class="est-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="est-fi-nombre">
        {{-- Cabecera (mismo formato que la ficha de Docentes) --}}
        <div class="est-drawer-head">
            <div class="est-drawer-head-left">
                <div class="est-drawer-avatar" id="est-fi-avatar" aria-hidden="true"></div>
                <div class="est-drawer-head-info">
                    <h3 class="est-drawer-title" id="est-fi-nombre"></h3>
                    <div class="est-drawer-doc" id="est-fi-doc"></div>
                    <div class="est-drawer-badges" id="est-fi-badges"></div>
                </div>
            </div>
            <button type="button" class="est-icon-btn" id="est-drawer-close" aria-label="Cerrar ficha">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="est-fi-tabs" id="est-fi-tabs" role="tablist">
            <button type="button" class="est-fi-tab is-active" data-tab="datos" role="tab">General</button>
            <button type="button" class="est-fi-tab" data-tab="acudiente" role="tab">Acudiente</button>
            <button type="button" class="est-fi-tab" data-tab="grupo" role="tab">Matrícula</button>
            <button type="button" class="est-fi-tab" data-tab="seguimiento" role="tab">Pagos y asistencia</button>
        </div>

        {{-- Vista principal de la ficha --}}
        <div class="est-drawer-body" id="est-ficha">
            <div class="est-fi-panel" id="est-fi-panel"></div>
        </div>

        {{-- Vista de acción (acudiente / contraseña) dentro de la misma ficha --}}
        <div class="est-drawer-body" id="est-accion" hidden>
            <button type="button" class="est-back" id="est-accion-volver">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Volver a la ficha
            </button>
            <h3 class="est-accion-ttl" id="est-accion-ttl"></h3>
            <p class="est-accion-sub" id="est-accion-sub"></p>
            <div id="est-accion-cuerpo"></div>
            <div class="est-form-error" id="est-accion-error" role="alert" hidden></div>
        </div>

        <div class="est-drawer-foot" id="est-ficha-foot">
            <button type="button" class="est-btn est-btn-secondary" data-accion="editar">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Editar datos
            </button>
            <button type="button" class="est-btn est-btn-secondary" data-accion="clave">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Restablecer contraseña
            </button>
        </div>
        <div class="est-drawer-foot" id="est-accion-foot" hidden>
            <button type="button" class="est-btn est-btn-ghost" id="est-accion-cancelar">Cancelar</button>
            <button type="button" class="est-btn est-btn-primary" id="est-accion-confirmar">Confirmar</button>
        </div>
    </aside>
</div>

{{-- ══════════ Asistente: Registrar / Editar estudiante ══════════ --}}
<div class="est-modal" id="est-modal" aria-hidden="true">
    <div class="est-modal-overlay" id="est-modal-overlay"></div>
    <div class="est-modal-box" role="dialog" aria-modal="true" aria-labelledby="est-modal-ttl">
        <div class="est-modal-head">
            <div>
                <h3 class="est-modal-ttl" id="est-modal-ttl">Registrar estudiante</h3>
                <span class="est-modal-paso" id="est-modal-paso">Paso 1 de 3</span>
            </div>
            <button type="button" class="est-icon-btn" id="est-modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Indicador de pasos (mismo formato que el asistente de Docentes) --}}
        <div class="est-wsteps" id="est-pasos">
            <div class="est-wstep is-active" data-step="1" title="Paso 1: Datos personales"><div class="est-wstep-dot"></div><span>Datos personales</span></div>
            <div class="est-wstep-line"></div>
            <div class="est-wstep" data-step="2" title="Paso 2: Contacto"><div class="est-wstep-dot"></div><span>Contacto</span></div>
            {{-- Al editar no hay paso de acudiente (se cambia desde la ficha) --}}
            <div class="est-wstep-line is-paso-acu"></div>
            <div class="est-wstep" data-step="3" title="Paso 3: Acudiente"><div class="est-wstep-dot"></div><span>Acudiente</span></div>
        </div>

        <div class="est-modal-body">

            {{-- Paso 1: Datos personales --}}
            <section class="est-mstep is-active" data-step="1">
                <div class="est-form-row">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-nombres">Nombres *</label>
                        <input class="est-input" id="ins-nombres" type="text" maxlength="100" autocomplete="off" placeholder="Valentina">
                    </div>
                    <div class="est-field">
                        <label class="est-lbl" for="ins-apellidos">Apellidos *</label>
                        <input class="est-input" id="ins-apellidos" type="text" maxlength="100" autocomplete="off" placeholder="Ríos Cárdenas">
                    </div>
                </div>
                <div class="est-form-row">
                    <div class="est-field">
                        <span class="est-lbl">Tipo de documento *</span>
                        <div class="app-combobox" id="ins-cb-tipo">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona un tipo</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="ins-tipo" data-combobox-value>
                        </div>
                    </div>
                    <div class="est-field">
                        <label class="est-lbl" for="ins-documento">Número de documento *</label>
                        <input class="est-input est-num" id="ins-documento" type="text" inputmode="numeric" autocomplete="off" placeholder="1070123456">
                    </div>
                </div>
                <div class="est-form-row">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-nacimiento">Fecha de nacimiento *</label>
                        <input class="est-input" id="ins-nacimiento" type="date">
                        <span class="est-edad" id="ins-edad" aria-live="polite"></span>
                    </div>
                    <div class="est-field">
                        <span class="est-lbl">Género *</span>
                        <div class="app-combobox" id="ins-cb-genero">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona el género</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="ins-genero" data-combobox-value>
                        </div>
                    </div>
                </div>
                <div class="est-form-row">
                    <div class="est-field">
                        <span class="est-lbl">Departamento de nacimiento *</span>
                        <div class="app-combobox" id="ins-cb-depto-nac">
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
                            <input type="hidden" id="ins-depto-nac" data-combobox-value>
                        </div>
                    </div>
                    <div class="est-field">
                        <span class="est-lbl">Ciudad de nacimiento *</span>
                        <div class="app-combobox" id="ins-cb-ciudad-nac">
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
                            <input type="hidden" id="ins-ciudad-nac" data-combobox-value>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Paso 2: Contacto --}}
            <section class="est-mstep" data-step="2">
                <div class="est-form-row">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-email">Correo electrónico *</label>
                        <input class="est-input" id="ins-email" type="email" autocomplete="off" maxlength="255" placeholder="nombre@correo.com">
                    </div>
                    <div class="est-field">
                        <label class="est-lbl" for="ins-telefono">Celular *</label>
                        <input class="est-input est-num" id="ins-telefono" type="tel" inputmode="numeric" autocomplete="off" placeholder="3XX XXX XXXX">
                    </div>
                </div>
                <div class="est-field">
                    <label class="est-lbl" for="ins-direccion">Dirección de residencia *</label>
                    <input class="est-input" id="ins-direccion" type="text" autocomplete="off" placeholder="Ej. Cra 5 # 8-20">
                </div>
                <div class="est-form-row">
                    <div class="est-field">
                        <span class="est-lbl">Departamento de residencia *</span>
                        <div class="app-combobox" id="ins-cb-depto">
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
                            <input type="hidden" id="ins-depto" data-combobox-value>
                        </div>
                    </div>
                    <div class="est-field">
                        <span class="est-lbl">Ciudad de residencia *</span>
                        <div class="app-combobox" id="ins-cb-ciudad">
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
                            <input type="hidden" id="ins-ciudad" data-combobox-value>
                        </div>
                    </div>
                </div>
                <div class="est-nota">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <p>El correo será el usuario del estudiante y su contraseña inicial, el número de documento. Podrá cambiarla desde su perfil.</p>
                </div>
            </section>

            {{-- Paso 3: Acudiente --}}
            <section class="est-mstep" data-step="3">
                <div class="est-acu-aviso" id="ins-acu-aviso"></div>

                <label class="est-check" id="ins-acu-opcional">
                    <input type="checkbox" id="ins-acu-agregar">
                    <span>Registrar un acudiente para este estudiante</span>
                </label>

                <div id="ins-acu-bloque">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-acu-buscar">Cédula del acudiente *</label>
                        <div class="est-buscar-row">
                            <input class="est-input est-num" id="ins-acu-buscar" type="text" inputmode="numeric" autocomplete="off" placeholder="Busca primero si ya está registrado">
                            <button type="button" class="est-btn est-btn-navy" id="ins-acu-btn-buscar">Buscar</button>
                        </div>
                    </div>

                    <div id="ins-acu-resultado"></div>

                    <div id="ins-acu-nuevo" hidden>
                        <div class="est-form-row">
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-nombres">Nombres *</label>
                                <input class="est-input" id="ins-acu-nombres" type="text" autocomplete="off">
                            </div>
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-apellidos">Apellidos *</label>
                                <input class="est-input" id="ins-acu-apellidos" type="text" autocomplete="off">
                            </div>
                        </div>
                        <div class="est-form-row">
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-telefono">Celular *</label>
                                <input class="est-input est-num" id="ins-acu-telefono" type="tel" inputmode="numeric" autocomplete="off" placeholder="3XX XXX XXXX">
                            </div>
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-email">Correo *</label>
                                <input class="est-input" id="ins-acu-email" type="email" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="est-field" id="ins-acu-parentesco-wrap" hidden>
                        <span class="est-lbl">Parentesco con el estudiante *</span>
                        <div class="est-chips" id="ins-parentesco" role="radiogroup" aria-label="Parentesco"></div>
                    </div>
                </div>
            </section>

            <div class="est-form-error" id="ins-error" role="alert" hidden></div>
        </div>

        <div class="est-modal-foot">
            <button type="button" class="est-btn est-btn-ghost" id="est-btn-atras">Atrás</button>
            <button type="button" class="est-btn est-btn-primary" id="est-btn-siguiente">Siguiente</button>
        </div>
    </div>
</div>

{{-- Aviso al guardar --}}
<div class="est-toast" id="est-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
    var EST_DATA = @json($estudiantes);
    var EST_GRUPOS = @json($grupos);
    var EST_ACUDIENTES = @json($acudientes);
    var EST_OPCIONES = @json($opciones);
</script>
<script src="{{ asset('assets/js/admin/estudiantes.js') }}?v={{ filemtime(public_path('assets/js/admin/estudiantes.js')) }}" defer></script>
@endpush
