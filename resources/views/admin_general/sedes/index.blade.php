{{-- Sedes (Admin de ESAT general) — SOLO FRONTEND con datos de ejemplo (public/assets/js/admin_general/datos-ejemplo.js).
     Nombre, ubicación y dirección de las 4 sedes son reales; secretarías, estudiantes y cifras son ficticias.
     Crear y editar sedes (sin eliminar ni desactivar, como en el Superadmin). Lo creado se guarda solo en la pestaña
     (GEN_API, sessionStorage). sedes.js pinta todo y escapa lo que viene de los datos. --}}
@extends('admin_general.layout')

@section('title', 'Sedes')
@section('active', 'sedes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/sedes.css') }}?v={{ filemtime(public_path('assets/css/admin_general/sedes.css')) }}">
@endpush

@section('content')
<div class="sed-wrap app-anim">

    <div class="sed-header">
        <div>
            <h1 class="sed-title">Sedes</h1>
            <p class="sed-sub">Las sedes de ESAT con su secretaría, sus estudiantes y los programas que ofrecen. Crea una sede nueva o corrige sus datos.</p>
        </div>
        <button type="button" class="sed-btn sed-btn-primary" id="sed-nueva">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva sede
        </button>
    </div>

    <div class="sed-kpis" id="sed-kpis"></div>

    <div class="sed-filters">
        <div class="sed-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="sed-buscar" placeholder="Buscar por nombre, ciudad o dirección..." autocomplete="off" aria-label="Buscar sede">
        </div>
        <span class="sed-count" id="sed-count"></span>
    </div>

    <div class="sed-table-wrap">
        <table class="sed-table">
            <thead>
                <tr>
                    <th scope="col">Sede</th>
                    <th scope="col">Ubicación</th>
                    <th scope="col">Secretaría</th>
                    <th scope="col" class="sed-num">Estudiantes</th>
                    <th scope="col" class="sed-num">Programas</th>
                    <th scope="col"><span class="sed-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="sed-body"></tbody>
        </table>
        <p class="sed-empty" id="sed-empty" hidden>No hay sedes que coincidan con la búsqueda.</p>
    </div>

    <nav class="sed-pagination" id="sed-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Drawer: detalle de la sede ─── --}}
<div class="sed-drawer" id="sed-drawer" aria-hidden="true">
    <div class="sed-drawer-overlay" data-cerrar-drawer></div>
    <div class="sed-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="sed-dw-nombre">
        <div class="sed-drawer-head">
            <div>
                <h2 class="sed-drawer-title" id="sed-dw-nombre"></h2>
                <p class="sed-drawer-sub" id="sed-dw-lugar"></p>
            </div>
            <button type="button" class="sed-close" data-cerrar-drawer aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sed-tabs" role="tablist">
            <button type="button" class="sed-tab is-active" role="tab" data-tab="datos" aria-selected="true">Datos</button>
            <button type="button" class="sed-tab" role="tab" data-tab="secretarias" aria-selected="false">Secretarías</button>
            <button type="button" class="sed-tab" role="tab" data-tab="programas" aria-selected="false">Programas</button>
        </div>
        <div class="sed-drawer-body">
            <div class="sed-panel" data-panel="datos" role="tabpanel"><div class="sed-info" id="sed-dw-datos"></div></div>
            <div class="sed-panel" data-panel="secretarias" role="tabpanel" hidden><ul class="sed-list" id="sed-dw-secretarias"></ul></div>
            <div class="sed-panel" data-panel="programas" role="tabpanel" hidden><ul class="sed-list" id="sed-dw-programas"></ul></div>
        </div>
        <div class="sed-drawer-foot">
            <button type="button" class="sed-btn sed-btn-ghost" id="sed-dw-entrar">
                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Entrar a la sede
            </button>
            <button type="button" class="sed-btn sed-btn-primary" id="sed-dw-editar">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar sede
            </button>
        </div>
    </div>
</div>

{{-- ─── Modal: crear / editar sede ─── --}}
<div class="sed-modal" id="sed-modal" hidden>
    <div class="sed-modal-overlay" data-cerrar-modal></div>
    <form class="sed-modal-panel" id="sed-form" role="dialog" aria-modal="true" aria-labelledby="sed-modal-titulo" novalidate>
        <div class="sed-modal-head">
            <div>
                <h2 class="sed-modal-title" id="sed-modal-titulo">Nueva sede</h2>
                <p class="sed-modal-sub">Todos los campos son obligatorios.</p>
            </div>
            <button type="button" class="sed-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sed-modal-body">
            <div class="sed-fields">
                <div class="sed-field sed-field-full">
                    <label for="sed-f-nombre">Nombre de la sede</label>
                    <input type="text" id="sed-f-nombre" maxlength="80" placeholder="Ej. Villeta" autocomplete="off">
                    <span class="sed-field-error" id="sed-e-nombre" hidden></span>
                </div>
                <div class="sed-field">
                    <span class="sed-label" id="sed-l-depto">Departamento</span>
                    <div class="app-combobox" id="sed-cb-depto">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-labelledby="sed-l-depto">
                            <span data-combobox-label>Selecciona un departamento</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <div class="app-combobox-search-wrap">
                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar..." aria-label="Buscar departamento">
                            </div>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" data-combobox-value>
                    </div>
                    <span class="sed-field-error" id="sed-e-depto" hidden></span>
                </div>
                <div class="sed-field">
                    <span class="sed-label" id="sed-l-ciudad">Ciudad o municipio</span>
                    <div class="app-combobox" id="sed-cb-ciudad">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-labelledby="sed-l-ciudad">
                            <span data-combobox-label>Elige el departamento primero</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <div class="app-combobox-search-wrap">
                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar..." aria-label="Buscar ciudad">
                            </div>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" data-combobox-value>
                    </div>
                    <span class="sed-field-error" id="sed-e-ciudad" hidden></span>
                </div>
                <div class="sed-field sed-field-full">
                    <label for="sed-f-direccion">Dirección</label>
                    <input type="text" id="sed-f-direccion" maxlength="150" placeholder="Ej. Calle 5 # 4-20, barrio Centro" autocomplete="off">
                    <span class="sed-field-error" id="sed-e-direccion" hidden></span>
                </div>
            </div>
            <p class="sed-nota" id="sed-nota-nueva" hidden>Después de crearla, asígnale una secretaría en «Secretarías» y pídele que registre los programas que ofrece la sede.</p>
        </div>
        <div class="sed-modal-foot">
            <button type="button" class="sed-btn sed-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="sed-btn sed-btn-primary" id="sed-guardar">Crear sede</button>
        </div>
    </form>
</div>

{{-- ─── Modal: entrar a la sede ─── --}}
<div class="sed-modal" id="sed-entrar" hidden>
    <div class="sed-modal-overlay" data-cerrar-modal></div>
    <div class="sed-modal-panel sed-modal-sm" role="dialog" aria-modal="true" aria-labelledby="sed-entrar-titulo">
        <div class="sed-modal-head">
            <div>
                <h2 class="sed-modal-title" id="sed-entrar-titulo">Entrar a la sede</h2>
                <p class="sed-modal-sub" id="sed-entrar-sub"></p>
            </div>
            <button type="button" class="sed-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sed-modal-body">
            <p class="sed-modal-text">Vas a trabajar en esta sede con las mismas pantallas de la secretaría. Todo lo que hagas queda en la auditoría de la sede con tu nombre.</p>
            <p class="sed-nota" id="sed-entrar-nota" hidden>Esta pantalla todavía es una maqueta: entrar a la sede funcionará cuando se conecte el backend.</p>
        </div>
        <div class="sed-modal-foot">
            <button type="button" class="sed-btn sed-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="button" class="sed-btn sed-btn-primary" id="sed-entrar-ok">Entrar a la sede</button>
        </div>
    </div>
</div>

<div class="sed-toast" id="sed-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin_general/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/admin_general/datos-ejemplo.js')) }}"></script>
    <script src="{{ asset('assets/js/admin_general/sedes.js') }}?v={{ filemtime(public_path('assets/js/admin_general/sedes.js')) }}" defer></script>
@endpush
