{{-- Acudientes (Admin de ESAT general). Datos reales: AdminGeneral AcudienteController@index los pasa en window.GEN_DATOS.
     Registrar (con al menos un estudiante a cargo de la sede elegida) y editar con las reglas de la secretaría, vincular y
     desvincular estudiantes (ningún menor queda sin acudiente), activar/desactivar y restablecer la clave. --}}
@extends('admin_general.layout')

@section('title', 'Acudientes')
@section('active', 'acudientes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/acudientes.css') }}?v={{ filemtime(public_path('assets/css/admin_general/acudientes.css')) }}">
@endpush

@php
    $cerrar = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
@endphp

@section('content')
<div class="acu-wrap app-anim">

    <div class="acu-header">
        <div>
            <h1 class="acu-title">Acudientes</h1>
            <p class="acu-sub">Las personas responsables de los estudiantes menores de edad. Registra un acudiente en la sede que elijas, vincúlale sus estudiantes, corrige sus datos o restablece su clave.</p>
        </div>
        <button type="button" class="acu-btn acu-btn-primary" id="acu-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo acudiente
        </button>
    </div>

    <div class="acu-kpis" id="acu-kpis"></div>

    <div class="acu-filters">
        <div class="acu-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="acu-buscar" placeholder="Buscar por nombre, documento, correo o estudiante..." autocomplete="off" aria-label="Buscar acudiente">
        </div>
        @foreach ([['acu-cb-sede', 'Todas las sedes'], ['acu-cb-estado', 'Todos los estados']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
        <span class="acu-count" id="acu-count"></span>
        <button type="button" class="acu-btn acu-btn-ghost acu-btn-sm" id="acu-exportar" title="Descarga la lista filtrada para abrirla en Excel">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exportar
        </button>
    </div>

    <div class="acu-table-wrap">
        <table class="acu-table">
            <thead><tr>
                <th scope="col">Acudiente</th><th scope="col">Sede</th><th scope="col">Contacto</th><th scope="col">Estudiantes a cargo</th>
                <th scope="col">Último ingreso</th><th scope="col">Estado</th><th scope="col"><span class="acu-sr">Acciones</span></th>
            </tr></thead>
            <tbody id="acu-body"></tbody>
        </table>
        <p class="acu-empty" id="acu-empty" hidden>No hay acudientes con esos filtros.</p>
    </div>
    <nav class="acu-pagination" id="acu-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Drawer: ficha del acudiente ─── --}}
<div class="acu-drawer" id="acu-drawer" aria-hidden="true">
    <div class="acu-drawer-overlay" data-cerrar-drawer></div>
    <div class="acu-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="acu-dw-nombre">
        <div class="acu-drawer-head">
            <div>
                <h2 class="acu-drawer-title" id="acu-dw-nombre"></h2>
                <p class="acu-drawer-sub" id="acu-dw-sub"></p>
            </div>
            <button type="button" class="acu-close" data-cerrar-drawer aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="acu-tabs" role="tablist">
            <button type="button" class="acu-tab is-active" role="tab" data-tab="datos" aria-selected="true">Datos</button>
            <button type="button" class="acu-tab" role="tab" data-tab="estudiantes" aria-selected="false">Estudiantes</button>
        </div>
        <div class="acu-drawer-body">
            <div class="acu-panel" data-panel="datos" role="tabpanel" id="acu-dw-datos"></div>
            <div class="acu-panel" data-panel="estudiantes" role="tabpanel" hidden>
                <ul class="acu-list" id="acu-dw-estudiantes"></ul>
                <h3 class="acu-seccion">Vincular otro estudiante</h3>
                <div class="acu-vincular">
                    <select id="acu-v-estudiante" aria-label="Estudiante de la sede"></select>
                    <select id="acu-v-parentesco" aria-label="Parentesco"></select>
                    <button type="button" class="acu-btn acu-btn-ghost acu-btn-sm" id="acu-v-vincular">Vincular</button>
                </div>
                <p class="acu-aviso" id="acu-v-aviso" hidden></p>
            </div>
        </div>
        <div class="acu-drawer-foot">
            <div class="acu-drawer-acciones">
                <button type="button" class="acu-btn acu-btn-ghost" id="acu-dw-estado"></button>
                <button type="button" class="acu-btn acu-btn-ghost" id="acu-dw-clave">Restablecer clave</button>
            </div>
            <button type="button" class="acu-btn acu-btn-primary" id="acu-dw-editar">Editar acudiente</button>
        </div>
    </div>
</div>

{{-- ─── Modal: crear / editar (3 pasos) ─── --}}
<div class="acu-modal" id="acu-modal" hidden>
    <div class="acu-modal-overlay" data-cerrar-modal></div>
    <form class="acu-modal-panel" id="acu-form" role="dialog" aria-modal="true" aria-labelledby="acu-modal-titulo" novalidate>
        <div class="acu-modal-head">
            <div>
                <h2 class="acu-modal-title" id="acu-modal-titulo">Nuevo acudiente</h2>
                <p class="acu-modal-sub" id="acu-modal-sub">Paso 1 de 3</p>
            </div>
            <button type="button" class="acu-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="acu-steps" aria-hidden="true">
            <div class="acu-step" data-step="1"><div class="acu-step-dot">1</div><span>Datos personales</span></div>
            <div class="acu-step-line"></div>
            <div class="acu-step" data-step="2"><div class="acu-step-dot">2</div><span>Contacto y residencia</span></div>
            <div class="acu-step-line"></div>
            <div class="acu-step" data-step="3"><div class="acu-step-dot">3</div><span>Sede y estudiantes</span></div>
        </div>
        <div class="acu-modal-body">
            <div class="acu-paso" data-paso="1">
                <div class="acu-fields">
                    <div class="acu-field"><label for="acu-f-nombres">Nombres</label><input type="text" id="acu-f-nombres" maxlength="60" autocomplete="off"><span class="acu-field-error" data-error="nombres" hidden></span></div>
                    <div class="acu-field"><label for="acu-f-apellidos">Apellidos</label><input type="text" id="acu-f-apellidos" maxlength="60" autocomplete="off"><span class="acu-field-error" data-error="apellidos" hidden></span></div>
                    <div class="acu-field"><label for="acu-f-tipo">Tipo de documento</label><select id="acu-f-tipo">@foreach (\App\Http\Requests\Admin\Acudientes\AcudienteRequest::TIPOS_DOCUMENTO as $valor => $tipo)<option value="{{ $valor }}">{{ $tipo }}</option>@endforeach</select></div>
                    <div class="acu-field"><label for="acu-f-documento">Número de documento</label><input type="text" id="acu-f-documento" inputmode="numeric" maxlength="12" autocomplete="off"><span class="acu-field-error" data-error="documento" hidden></span></div>
                    <div class="acu-field"><label for="acu-f-genero">Género <span class="acu-muted">(opcional)</span></label><select id="acu-f-genero"><option value="">Selecciona</option><option value="femenino">Femenino</option><option value="masculino">Masculino</option><option value="otro">Otro</option></select><span class="acu-field-error" data-error="genero" hidden></span></div>
                    <div class="acu-field"><label for="acu-f-profesion">Ocupación <span class="acu-muted">(opcional)</span></label><input type="text" id="acu-f-profesion" maxlength="100" placeholder="Ej. Comerciante" autocomplete="off"><span class="acu-field-error" data-error="profesion" hidden></span></div>
                </div>
            </div>
            <div class="acu-paso" data-paso="2" hidden>
                <div class="acu-fields">
                    <div class="acu-field"><label for="acu-f-telefono">Celular</label><input type="tel" id="acu-f-telefono" inputmode="numeric" maxlength="14" placeholder="3001234567" autocomplete="off"><span class="acu-field-error" data-error="telefono" hidden></span></div>
                    <div class="acu-field"><label for="acu-f-correo">Correo (es su usuario)</label><input type="email" id="acu-f-correo" maxlength="120" autocomplete="off"><span class="acu-field-error" data-error="correo" hidden></span></div>
                    @foreach ([['depto', 'Departamento de residencia', 'Selecciona un departamento'], ['ciudad', 'Ciudad de residencia', 'Elige el departamento primero']] as [$k, $etq, $ph])
                    <div class="acu-field">
                        <span class="acu-label" id="acu-l-{{ $k }}">{{ $etq }}</span>
                        <div class="app-combobox" id="acu-cb-{{ $k }}">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-labelledby="acu-l-{{ $k }}">
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
                        <span class="acu-field-error" data-error="{{ $k }}" hidden></span>
                    </div>
                    @endforeach
                    <div class="acu-field acu-field-full"><label for="acu-f-direccion">Dirección</label><input type="text" id="acu-f-direccion" maxlength="150" autocomplete="off"><span class="acu-field-error" data-error="direccion" hidden></span></div>
                </div>
            </div>
            <div class="acu-paso" data-paso="3" hidden>
                <div class="acu-fields">
                    <div class="acu-field acu-field-full"><label for="acu-f-sede">Sede</label><select id="acu-f-sede"></select><span class="acu-field-error" data-error="sede" hidden></span></div>
                </div>
                <div id="acu-f-bloque-est" class="acu-bloque-est">
                    <h3 class="acu-seccion">Estudiantes a cargo</h3>
                    <p class="acu-hint">Agrega al menos uno: el acudiente se registra con los estudiantes que tiene a cargo. Después puedes vincularle más desde su ficha. Un estudiante tiene un solo acudiente; si ya tenía uno, se reemplaza.</p>
                    <div class="acu-vincular">
                        <select id="acu-f-estudiante" aria-label="Estudiante"></select>
                        <select id="acu-f-parentesco" aria-label="Parentesco"></select>
                        <button type="button" class="acu-btn acu-btn-ghost acu-btn-sm" id="acu-f-agregar">Agregar</button>
                    </div>
                    <ul class="acu-elegidos" id="acu-f-elegidos"></ul>
                </div>
                <div class="acu-resumen" id="acu-resumen"></div>
                <p class="acu-nota" id="acu-nota-clave">La clave inicial es su número de documento. Su usuario es el correo.</p>
            </div>
        </div>
        <div class="acu-modal-foot">
            <button type="button" class="acu-btn acu-btn-ghost" id="acu-atras">Atrás</button>
            <button type="submit" class="acu-btn acu-btn-primary" id="acu-siguiente">Siguiente</button>
        </div>
    </form>
</div>

{{-- ─── Modal: confirmar ─── --}}
<div class="acu-modal" id="acu-confirmar" hidden>
    <div class="acu-modal-overlay" data-cerrar-modal></div>
    <div class="acu-modal-panel acu-modal-sm" role="alertdialog" aria-modal="true" aria-labelledby="acu-conf-titulo" aria-describedby="acu-conf-texto">
        <div class="acu-modal-head">
            <h2 class="acu-modal-title" id="acu-conf-titulo"></h2>
            <button type="button" class="acu-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="acu-modal-body"><p class="acu-modal-text" id="acu-conf-texto"></p></div>
        <div class="acu-modal-foot">
            <button type="button" class="acu-btn acu-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="button" class="acu-btn acu-btn-primary" id="acu-conf-ok"></button>
        </div>
    </div>
</div>

<div class="acu-toast" id="acu-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/acudientes.js') }}?v={{ filemtime(public_path('assets/js/admin_general/acudientes.js')) }}" defer></script>
@endpush
