{{-- Docentes (Admin de ESAT general) — SOLO FRONTEND con datos de ejemplo (public/assets/js/admin_general/datos-ejemplo.js).
     Docentes, grupos y datos personales ficticios; sedes, escuelas y programas reales. Decidido con el usuario: el Admin crea y
     edita igual que la secretaría, pero en cualquier sede (la elige en el formulario). Mismas reglas: clave inicial = documento,
     correo y documento únicos, celular ^3\d{9}$, mayor de edad, no se desactiva con grupos vigentes. Guarda en la pestaña (GEN_API). --}}
@extends('admin_general.layout')

@section('title', 'Docentes')
@section('active', 'docentes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/docentes.css') }}?v={{ filemtime(public_path('assets/css/admin_general/docentes.css')) }}">
@endpush

@php
    $cerrar = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
@endphp

@section('content')
<div class="dcn-wrap app-anim">

    <div class="dcn-header">
        <div>
            <h1 class="dcn-title">Docentes</h1>
            <p class="dcn-sub">La planta docente de todas las sedes. Registra un docente en la sede que elijas, corrige sus datos, desactívalo cuando ya no trabaje en ESAT o restablece su clave.</p>
        </div>
        <button type="button" class="dcn-btn dcn-btn-primary" id="dcn-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo docente
        </button>
    </div>

    <div class="dcn-kpis" id="dcn-kpis"></div>

    <div class="dcn-filters">
        <div class="dcn-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="dcn-buscar" placeholder="Buscar por nombre, documento o correo..." autocomplete="off" aria-label="Buscar docente">
        </div>
        @foreach ([['dcn-cb-sede', 'Todas las sedes'], ['dcn-cb-escuela', 'Todas las escuelas'], ['dcn-cb-estado', 'Todos los estados']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
        <span class="dcn-count" id="dcn-count"></span>
        <button type="button" class="dcn-btn dcn-btn-ghost dcn-btn-sm" id="dcn-exportar" title="Descarga la lista filtrada para abrirla en Excel">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exportar
        </button>
    </div>

    <div class="dcn-table-wrap">
        <table class="dcn-table">
            <thead><tr>
                <th scope="col">Docente</th><th scope="col">Sede</th><th scope="col">Escuela</th><th scope="col">Vinculación</th>
                <th scope="col" class="dcn-num">Grupos vigentes</th><th scope="col">Estado</th><th scope="col"><span class="dcn-sr">Acciones</span></th>
            </tr></thead>
            <tbody id="dcn-body"></tbody>
        </table>
        <p class="dcn-empty" id="dcn-empty" hidden>No hay docentes con esos filtros.</p>
    </div>
    <nav class="dcn-pagination" id="dcn-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Drawer: ficha del docente ─── --}}
<div class="dcn-drawer" id="dcn-drawer" aria-hidden="true">
    <div class="dcn-drawer-overlay" data-cerrar-drawer></div>
    <div class="dcn-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="dcn-dw-nombre">
        <div class="dcn-drawer-head">
            <div>
                <h2 class="dcn-drawer-title" id="dcn-dw-nombre"></h2>
                <p class="dcn-drawer-sub" id="dcn-dw-sub"></p>
            </div>
            <button type="button" class="dcn-close" data-cerrar-drawer aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="dcn-tabs" role="tablist">
            <button type="button" class="dcn-tab is-active" role="tab" data-tab="datos" aria-selected="true">Datos</button>
            <button type="button" class="dcn-tab" role="tab" data-tab="perfil" aria-selected="false">Perfil</button>
            <button type="button" class="dcn-tab" role="tab" data-tab="grupos" aria-selected="false">Grupos</button>
        </div>
        <div class="dcn-drawer-body">
            <div class="dcn-panel" data-panel="datos" role="tabpanel" id="dcn-dw-datos"></div>
            <div class="dcn-panel" data-panel="perfil" role="tabpanel" id="dcn-dw-perfil" hidden></div>
            <div class="dcn-panel" data-panel="grupos" role="tabpanel" id="dcn-dw-grupos" hidden></div>
        </div>
        <div class="dcn-drawer-foot">
            <div class="dcn-drawer-acciones">
                <button type="button" class="dcn-btn dcn-btn-ghost" id="dcn-dw-estado"></button>
                <button type="button" class="dcn-btn dcn-btn-ghost" id="dcn-dw-clave">Restablecer clave</button>
            </div>
            <button type="button" class="dcn-btn dcn-btn-primary" id="dcn-dw-editar">Editar docente</button>
        </div>
    </div>
</div>

{{-- ─── Modal: crear / editar (3 pasos) ─── --}}
<div class="dcn-modal" id="dcn-modal" hidden>
    <div class="dcn-modal-overlay" data-cerrar-modal></div>
    <form class="dcn-modal-panel" id="dcn-form" role="dialog" aria-modal="true" aria-labelledby="dcn-modal-titulo" novalidate>
        <div class="dcn-modal-head">
            <div>
                <h2 class="dcn-modal-title" id="dcn-modal-titulo">Nuevo docente</h2>
                <p class="dcn-modal-sub" id="dcn-modal-sub">Paso 1 de 3</p>
            </div>
            <button type="button" class="dcn-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="dcn-steps" aria-hidden="true">
            <div class="dcn-step" data-step="1"><div class="dcn-step-dot">1</div><span>Datos personales</span></div>
            <div class="dcn-step-line"></div>
            <div class="dcn-step" data-step="2"><div class="dcn-step-dot">2</div><span>Contacto y residencia</span></div>
            <div class="dcn-step-line"></div>
            <div class="dcn-step" data-step="3"><div class="dcn-step-dot">3</div><span>Perfil y sede</span></div>
        </div>
        <div class="dcn-modal-body">
            <div class="dcn-paso" data-paso="1">
                <div class="dcn-fields">
                    <div class="dcn-field"><label for="dcn-f-nombres">Nombres</label><input type="text" id="dcn-f-nombres" maxlength="60" autocomplete="off"><span class="dcn-field-error" data-error="nombres" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-apellidos">Apellidos</label><input type="text" id="dcn-f-apellidos" maxlength="60" autocomplete="off"><span class="dcn-field-error" data-error="apellidos" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-tipo">Tipo de documento</label><select id="dcn-f-tipo"><option value="CC">Cédula de ciudadanía</option><option value="CE">Cédula de extranjería</option><option value="PA">Pasaporte</option><option value="PPT">Permiso por protección temporal</option></select></div>
                    <div class="dcn-field"><label for="dcn-f-documento">Número de documento</label><input type="text" id="dcn-f-documento" inputmode="numeric" maxlength="12" autocomplete="off"><span class="dcn-field-error" data-error="documento" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-nacimiento">Fecha de nacimiento</label><input type="date" id="dcn-f-nacimiento"><span class="dcn-hint" id="dcn-edad"></span><span class="dcn-field-error" data-error="nacimiento" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-genero">Género</label><select id="dcn-f-genero"><option value="">Selecciona</option><option value="femenino">Femenino</option><option value="masculino">Masculino</option><option value="otro">Otro</option></select><span class="dcn-field-error" data-error="genero" hidden></span></div>
                </div>
            </div>
            <div class="dcn-paso" data-paso="2" hidden>
                <div class="dcn-fields">
                    <div class="dcn-field"><label for="dcn-f-telefono">Celular</label><input type="tel" id="dcn-f-telefono" inputmode="numeric" maxlength="14" placeholder="3001234567" autocomplete="off"><span class="dcn-field-error" data-error="telefono" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-correo">Correo (es su usuario)</label><input type="email" id="dcn-f-correo" maxlength="120" autocomplete="off"><span class="dcn-field-error" data-error="correo" hidden></span></div>
                    @foreach ([['depto', 'Departamento de residencia', 'Selecciona un departamento'], ['ciudad', 'Ciudad de residencia', 'Elige el departamento primero']] as [$k, $etq, $ph])
                    <div class="dcn-field">
                        <span class="dcn-label" id="dcn-l-{{ $k }}">{{ $etq }}</span>
                        <div class="app-combobox" id="dcn-cb-{{ $k }}">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-labelledby="dcn-l-{{ $k }}">
                                <span data-combobox-label>{{ $ph }}</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar..." aria-label="Buscar">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" data-combobox-value>
                        </div>
                        <span class="dcn-field-error" data-error="{{ $k }}" hidden></span>
                    </div>
                    @endforeach
                    <div class="dcn-field dcn-field-full"><label for="dcn-f-direccion">Dirección</label><input type="text" id="dcn-f-direccion" maxlength="150" autocomplete="off"><span class="dcn-field-error" data-error="direccion" hidden></span></div>
                </div>
            </div>
            <div class="dcn-paso" data-paso="3" hidden>
                <div class="dcn-fields">
                    <div class="dcn-field"><label for="dcn-f-sede">Sede</label><select id="dcn-f-sede"></select><span class="dcn-field-error" data-error="sede" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-escuela">Escuela</label><select id="dcn-f-escuela"></select><span class="dcn-field-error" data-error="escuela" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-profesion">Título profesional</label><input type="text" id="dcn-f-profesion" maxlength="120" placeholder="Ej. Enfermera profesional" autocomplete="off"><span class="dcn-field-error" data-error="profesion" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-especialidad">Especialidad</label><input type="text" id="dcn-f-especialidad" maxlength="120" placeholder="Ej. Docencia en salud" autocomplete="off"><span class="dcn-field-error" data-error="especialidad" hidden></span></div>
                    <div class="dcn-field"><label for="dcn-f-vinculacion">Vinculación</label><select id="dcn-f-vinculacion"><option value="tiempo_completo">Tiempo completo</option><option value="medio_tiempo">Medio tiempo</option><option value="hora_catedra">Hora cátedra</option></select></div>
                    <div class="dcn-field"><label for="dcn-f-estado">Estado</label><select id="dcn-f-estado"><option value="activo">Activo</option><option value="en_proceso">En proceso</option></select></div>
                </div>
                <div class="dcn-resumen" id="dcn-resumen"></div>
                <p class="dcn-nota" id="dcn-nota-clave">La clave inicial es su número de documento.</p>
            </div>
        </div>
        <div class="dcn-modal-foot">
            <button type="button" class="dcn-btn dcn-btn-ghost" id="dcn-atras">Atrás</button>
            <button type="submit" class="dcn-btn dcn-btn-primary" id="dcn-siguiente">Siguiente</button>
        </div>
    </form>
</div>

{{-- ─── Modal: confirmar ─── --}}
<div class="dcn-modal" id="dcn-confirmar" hidden>
    <div class="dcn-modal-overlay" data-cerrar-modal></div>
    <div class="dcn-modal-panel dcn-modal-sm" role="alertdialog" aria-modal="true" aria-labelledby="dcn-conf-titulo" aria-describedby="dcn-conf-texto">
        <div class="dcn-modal-head">
            <h2 class="dcn-modal-title" id="dcn-conf-titulo"></h2>
            <button type="button" class="dcn-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="dcn-modal-body"><p class="dcn-modal-text" id="dcn-conf-texto"></p></div>
        <div class="dcn-modal-foot">
            <button type="button" class="dcn-btn dcn-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="button" class="dcn-btn dcn-btn-primary" id="dcn-conf-ok"></button>
        </div>
    </div>
</div>

<div class="dcn-toast" id="dcn-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin_general/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/admin_general/datos-ejemplo.js')) }}"></script>
    <script src="{{ asset('assets/js/admin_general/docentes.js') }}?v={{ filemtime(public_path('assets/js/admin_general/docentes.js')) }}" defer></script>
@endpush
