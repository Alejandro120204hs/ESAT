{{-- Panel Admin › Matrículas (backend real: Admin/Matriculas/MatriculaController + PagoController,
     MatriculaService y PagoService). Matricular = estudiante + grupo (el programa es el del grupo).
     Aquí se cambia de grupo, se cambia el estado (aplazar, retirar, graduar, reintegrar) y se registran
     los pagos con recibo. El plan de pago (mensual o por periodo) lo elige el estudiante desde su panel.
     Los datos de la persona se editan en Estudiantes. --}}
@extends('admin.layout')

@section('title', 'Matrículas')
@section('page-title', 'Matrículas')
@section('active', 'matriculas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/matriculas.css') }}?v={{ filemtime(public_path('assets/css/admin/matriculas.css')) }}">
@endpush

@section('content')
<div class="mat-wrap">

    {{-- Encabezado (misma estructura que Docentes, Estudiantes y Acudientes) --}}
    <div class="mat-header">
        <div>
            <h2 class="mat-title">Matrículas</h2>
            <p class="mat-sub">Aquí podrás matricular a los estudiantes en un programa y un grupo, cambiarlos de grupo, aplazar, retirar o graduar su matrícula y registrar sus pagos.</p>
        </div>
        <button class="mat-btn mat-btn-primary" id="mat-btn-nueva" type="button">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva matrícula
        </button>
    </div>

    {{-- KPIs (valores calculados por matriculas.js) --}}
    <div class="mat-kpis">
        <div class="mat-kpi-chip mat-kpi-blue">
            <div class="mat-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>
            </div>
            <div>
                <div class="mat-kpi-val" id="mat-kpi-activas">0</div>
                <div class="mat-kpi-lbl">Matrículas activas</div>
            </div>
        </div>
        <div class="mat-kpi-chip mat-kpi-orange">
            <div class="mat-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </div>
            <div>
                <div class="mat-kpi-val" id="mat-kpi-pendientes">0</div>
                <div class="mat-kpi-lbl">Por matricular</div>
            </div>
        </div>
        <div class="mat-kpi-chip mat-kpi-green">
            <div class="mat-kpi-icon">
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>
            </div>
            <div>
                <div class="mat-kpi-val" id="mat-kpi-recaudo">$ 0</div>
                <div class="mat-kpi-lbl" id="mat-kpi-recaudo-lbl">Recaudado este mes</div>
            </div>
        </div>
        <div class="mat-kpi-chip mat-kpi-red">
            <div class="mat-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="mat-kpi-val" id="mat-kpi-cartera">$ 0</div>
                <div class="mat-kpi-lbl" id="mat-kpi-cartera-lbl">Cartera vencida</div>
            </div>
        </div>
    </div>

    {{-- Vista: vigentes (activas y aplazadas) / registrados sin matrícula / finalizadas (graduadas y retiradas) --}}
    <div class="mat-vistas" role="tablist" aria-label="Vista">
        <button type="button" class="mat-vista-btn is-active" data-vista="vigentes" role="tab" aria-selected="true">
            Matrículas <span class="mat-vista-num" id="mat-num-vigentes">0</span>
        </button>
        <button type="button" class="mat-vista-btn" data-vista="pendientes" role="tab" aria-selected="false">
            Por matricular <span class="mat-vista-num is-aviso" id="mat-num-pendientes">0</span>
        </button>
        <button type="button" class="mat-vista-btn" data-vista="finalizadas" role="tab" aria-selected="false">
            Finalizadas <span class="mat-vista-num" id="mat-num-finalizadas">0</span>
        </button>
    </div>

    {{-- Filtros de matrículas (misma fila que Docentes) --}}
    <div class="mat-toolbar" id="mat-toolbar">
        <div class="mat-filtros">
            <div class="mat-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="mat-search" id="mat-search" type="search" placeholder="Estudiante, documento o código..." aria-label="Buscar matrícula" autocomplete="off">
            </div>
            <div class="app-combobox" id="mat-cb-programa">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los programas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="mat-val-programa" data-combobox-value>
            </div>
            <div class="app-combobox" id="mat-cb-grupo">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los grupos</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="mat-val-grupo" data-combobox-value>
            </div>
            <div class="app-combobox" id="mat-cb-estado">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los estados</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="mat-val-estado" data-combobox-value>
            </div>
            <div class="app-combobox" id="mat-cb-pago">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los pagos</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="mat-val-pago" data-combobox-value>
            </div>
            <span class="mat-count-badge" id="mat-count" aria-live="polite"></span>
            <button type="button" class="mat-btn mat-btn-ghost mat-btn-sm" data-exportar title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
    </div>

    {{-- Filtro de "Por matricular" --}}
    <div class="mat-toolbar" id="mat-toolbar-pend" hidden>
        <div class="mat-filtros">
            <div class="mat-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="mat-search" id="mat-search-pend" type="search" placeholder="Nombre o documento..." aria-label="Buscar estudiante por matricular" autocomplete="off">
            </div>
            <span class="mat-count-badge" id="mat-count-pend" aria-live="polite"></span>
            <button type="button" class="mat-btn mat-btn-ghost mat-btn-sm" data-exportar title="Descarga la lista para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>
    </div>

    {{-- Tabla de matrículas (generada por matriculas.js) --}}
    <div class="mat-lista-wrap" id="mat-lista-wrap">
        <table class="mat-lista">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Programa y grupo</th>
                    <th>Estado</th>
                    <th>Cuotas</th>
                    <th class="mat-num-col" id="mat-th-saldo">A pagar este mes</th>
                    <th><span class="mat-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="mat-lista-body"></tbody>
        </table>
    </div>

    {{-- Tabla de estudiantes por matricular --}}
    <div class="mat-lista-wrap" id="mat-pend-wrap" hidden>
        <table class="mat-lista">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Situación</th>
                    <th>Acudiente</th>
                    <th>Documentos</th>
                    <th><span class="mat-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="mat-pend-body"></tbody>
        </table>
    </div>

    <div class="mat-empty" id="mat-empty" hidden>
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <span class="mat-empty-ttl" id="mat-empty-ttl">No hay matrículas con estos filtros</span>
        <span class="mat-empty-sub" id="mat-empty-sub">Prueba con otro nombre, documento o código, o quita el filtro de programa, grupo, estado o pago.</span>
        <button type="button" class="mat-btn mat-btn-ghost" id="mat-limpiar">Quitar filtros</button>
    </div>

    <nav class="mat-pagination" id="mat-pagination" aria-label="Paginación" hidden>
        <button type="button" class="mat-pg-btn" id="mat-pg-prev">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="mat-pg-pages" id="mat-pg-pages"></div>
        <button type="button" class="mat-pg-btn" id="mat-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </nav>

</div>

{{-- ══════════ Ficha de la matrícula (panel lateral) ══════════ --}}
<div class="mat-drawer" id="mat-drawer" aria-hidden="true">
    <div class="mat-drawer-overlay" id="mat-drawer-overlay"></div>
    <aside class="mat-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="mat-fi-nombre">
        <div class="mat-drawer-head">
            <div class="mat-drawer-head-left">
                <div class="mat-drawer-avatar" id="mat-fi-avatar" aria-hidden="true"></div>
                <div class="mat-drawer-head-info">
                    <h3 class="mat-drawer-title" id="mat-fi-nombre"></h3>
                    <div class="mat-drawer-doc" id="mat-fi-doc"></div>
                    <div class="mat-drawer-badges" id="mat-fi-badges"></div>
                </div>
            </div>
            <button type="button" class="mat-icon-btn" id="mat-drawer-close" aria-label="Cerrar ficha">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="mat-fi-tabs" id="mat-fi-tabs" role="tablist">
            <button type="button" class="mat-fi-tab is-active" data-tab="general" role="tab">General</button>
            <button type="button" class="mat-fi-tab" data-tab="pagos" role="tab">Pagos</button>
            <button type="button" class="mat-fi-tab" data-tab="historial" role="tab">Historial</button>
        </div>

        <div class="mat-drawer-body" id="mat-ficha">
            <div class="mat-fi-panel" id="mat-fi-panel"></div>
        </div>

        {{-- Vista de acción (cambiar grupo / estado / registrar o anular pago) dentro de la misma ficha --}}
        <div class="mat-drawer-body" id="mat-accion" hidden>
            <button type="button" class="mat-back" id="mat-accion-volver">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Volver a la ficha
            </button>
            <h3 class="mat-accion-ttl" id="mat-accion-ttl"></h3>
            <p class="mat-accion-sub" id="mat-accion-sub"></p>
            <div id="mat-accion-cuerpo"></div>
            <div class="mat-form-error" id="mat-accion-error" role="alert" hidden></div>
        </div>

        <div class="mat-drawer-foot" id="mat-ficha-foot">
            <button type="button" class="mat-btn mat-btn-primary" data-accion="pagar" id="mat-btn-pagar">
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Registrar pago
            </button>
            <button type="button" class="mat-btn mat-btn-secondary" data-accion="grupo" id="mat-btn-grupo">
                <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                Cambiar de grupo
            </button>
            <button type="button" class="mat-btn mat-btn-secondary" data-accion="estado" id="mat-btn-estado">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Cambiar estado
            </button>
        </div>
        <div class="mat-drawer-foot" id="mat-accion-foot" hidden>
            <button type="button" class="mat-btn mat-btn-ghost" id="mat-accion-cancelar">Cancelar</button>
            <button type="button" class="mat-btn mat-btn-primary" id="mat-accion-confirmar">Confirmar</button>
        </div>
    </aside>
</div>

{{-- ══════════ Asistente: Nueva matrícula ══════════ --}}
<div class="mat-modal" id="mat-modal" aria-hidden="true">
    <div class="mat-modal-overlay" id="mat-modal-overlay"></div>
    <div class="mat-modal-box" role="dialog" aria-modal="true" aria-labelledby="mat-modal-ttl">
        <div class="mat-modal-head">
            <div>
                <h3 class="mat-modal-ttl" id="mat-modal-ttl">Nueva matrícula</h3>
                <span class="mat-modal-paso" id="mat-modal-paso">Paso 1 de 3</span>
            </div>
            <button type="button" class="mat-icon-btn" id="mat-modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Indicador de pasos (mismo formato que el asistente de Docentes) --}}
        <div class="mat-wsteps">
            <div class="mat-wstep is-active" data-step="1" title="Paso 1: Estudiante"><div class="mat-wstep-dot"></div><span>Estudiante</span></div>
            <div class="mat-wstep-line"></div>
            <div class="mat-wstep" data-step="2" title="Paso 2: Programa y grupo"><div class="mat-wstep-dot"></div><span>Programa y grupo</span></div>
            <div class="mat-wstep-line"></div>
            <div class="mat-wstep" data-step="3" title="Paso 3: Confirmación"><div class="mat-wstep-dot"></div><span>Confirmación</span></div>
        </div>

        <div class="mat-modal-body">

            {{-- Paso 1: Estudiante --}}
            <section class="mat-mstep is-active" data-step="1">
                <div class="mat-field">
                    <span class="mat-lbl">Estudiante *</span>
                    <div class="app-combobox" id="nm-cb-estudiante">
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
                        <input type="hidden" id="nm-estudiante" data-combobox-value>
                    </div>
                </div>
                <div class="mat-est-card" id="nm-est-card" hidden></div>
                <div class="mat-nota">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <p>Solo aparecen los estudiantes sin matrícula vigente. Si no encuentras a alguien, regístralo primero en Estudiantes.</p>
                </div>
            </section>

            {{-- Paso 2: Programa y grupo --}}
            <section class="mat-mstep" data-step="2">
                <div class="mat-field">
                    <span class="mat-lbl">Programa *</span>
                    <div class="app-combobox" id="nm-cb-programa">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                            <span data-combobox-label>Selecciona un programa</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <div class="app-combobox-search-wrap">
                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar programa" autocomplete="off">
                            </div>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" id="nm-programa" data-combobox-value>
                    </div>
                </div>
                <div class="mat-field">
                    <span class="mat-lbl">Grupo *</span>
                    <div class="mat-opciones" id="nm-grupos" role="radiogroup" aria-label="Grupo"></div>
                </div>
            </section>

            {{-- Paso 3: Confirmación y derecho de matrícula --}}
            <section class="mat-mstep" data-step="3">
                <div class="mat-resumen" id="nm-resumen"></div>
                <label class="mat-check" id="nm-pagar-wrap">
                    <input type="checkbox" id="nm-pagar">
                    <span id="nm-pagar-txt">Ya pagó el derecho de matrícula</span>
                </label>
                <div class="mat-pago-bloque" id="nm-pago" hidden>
                    <div class="mat-form-row">
                        <div class="mat-field">
                            <label class="mat-lbl" for="nm-fecha">Fecha del pago *</label>
                            <input class="mat-input" id="nm-fecha" type="date">
                        </div>
                        <div class="mat-field">
                            <label class="mat-lbl" for="nm-referencia">Referencia <em id="nm-ref-opc">opcional en efectivo</em></label>
                            <input class="mat-input" id="nm-referencia" type="text" maxlength="60" autocomplete="off" placeholder="N.º de transacción">
                        </div>
                    </div>
                    <div class="mat-field">
                        <span class="mat-lbl">Medio de pago *</span>
                        <div class="mat-chips" id="nm-metodos" role="radiogroup" aria-label="Medio de pago"></div>
                    </div>
                </div>
                <div class="mat-nota">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <p>El plan de cuotas (mensual, o semestral/trimestral según el programa) lo elige el estudiante desde su panel. Cuando lo elija, sus cuotas aparecerán aquí con sus fechas de vencimiento.</p>
                </div>
            </section>

            <div class="mat-form-error" id="nm-error" role="alert" hidden></div>
        </div>

        <div class="mat-modal-foot">
            <button type="button" class="mat-btn mat-btn-ghost" id="nm-atras">Atrás</button>
            <button type="button" class="mat-btn mat-btn-primary" id="nm-siguiente">Siguiente</button>
        </div>
    </div>
</div>

{{-- Aviso al guardar --}}
<div class="mat-toast" id="mat-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
    var MAT_DATA = @json($matriculas);
    var MAT_PENDIENTES = @json($porMatricular);
    var MAT_GRUPOS = @json($grupos);
    var MAT_PROGRAMAS = @json($programas);
    var MAT_OPCIONES = @json($opciones);
</script>
<script src="{{ asset('assets/js/admin/matriculas.js') }}?v={{ filemtime(public_path('assets/js/admin/matriculas.js')) }}" defer></script>
@endpush
