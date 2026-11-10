{{-- Foro (Admin de ESAT general). Datos reales: AdminGeneral ForoController@index los pasa en window.GEN_DATOS: el foro de
     cada sede. El Admin modera (ocultar con motivo —el primer mensaje oculta el tema—, restaurar, descartar reportes, fijar,
     cerrar o reabrir) y participa como Admin (abrir temas y responder), con las reglas de la secretaría (ForoService). --}}
@extends('admin_general.layout')

@section('title', 'Foro')
@section('active', 'foro')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/foro.css') }}?v={{ filemtime(public_path('assets/css/admin_general/foro.css')) }}">
@endpush

@php
    $cerrar = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
@endphp

@section('content')
<div class="for-wrap app-anim">

    <div class="for-header">
        <div>
            <h1 class="for-title">Foro</h1>
            <p class="for-sub">Los foros de todas las sedes, donde conversan estudiantes, docentes, acudientes y secretarías. Atiende los reportes, oculta lo que no cumpla las normas y responde como Admin.</p>
        </div>
        <button type="button" class="for-btn for-btn-primary" id="for-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo tema
        </button>
    </div>

    <div class="for-chips" id="for-chips" role="radiogroup" aria-label="Sede">
        <button type="button" class="for-chip is-active" role="radio" aria-checked="true" data-sede="">Todas las sedes</button>
    </div>

    <div class="for-kpis" id="for-kpis"></div>

    <div class="for-filters">
        <div class="for-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="for-buscar" placeholder="Buscar en títulos y mensajes..." autocomplete="off" aria-label="Buscar en el foro">
        </div>
        @foreach ([['for-cb-categoria', 'Todas las categorías'], ['for-cb-estado', 'Todos los temas']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
        <span class="for-count" id="for-count"></span>
    </div>

    <div class="for-table-wrap">
        <table class="for-table">
            <thead><tr>
                <th scope="col">Tema</th><th scope="col">Sede</th><th scope="col">Abrió</th><th scope="col" class="for-num">Mensajes</th><th scope="col">Última actividad</th><th scope="col">Atención</th>
            </tr></thead>
            <tbody id="for-body"></tbody>
        </table>
        <p class="for-empty" id="for-empty" hidden>No hay temas con esos filtros.</p>
    </div>
    <nav class="for-pagination" id="for-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Drawer: el tema con sus mensajes ─── --}}
<div class="for-drawer" id="for-drawer" aria-hidden="true">
    <div class="for-drawer-overlay" data-cerrar-drawer></div>
    <div class="for-drawer-panel for-drawer-ancho" role="dialog" aria-modal="true" aria-labelledby="for-dw-titulo">
        <div class="for-drawer-head">
            <div>
                <h2 class="for-drawer-title" id="for-dw-titulo"></h2>
                <p class="for-drawer-sub" id="for-dw-sub"></p>
            </div>
            <button type="button" class="for-close" data-cerrar-drawer aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="for-dw-acciones">
            <button type="button" class="for-btn for-btn-ghost for-btn-sm" id="for-dw-fijar"></button>
            <button type="button" class="for-btn for-btn-ghost for-btn-sm" id="for-dw-cerrar"></button>
        </div>
        <div class="for-drawer-body"><ol class="for-hilo" id="for-hilo"></ol></div>
        <form class="for-responder" id="for-responder" novalidate>
            <label for="for-r-texto" class="for-label">Responder como Admin</label>
            <textarea id="for-r-texto" maxlength="2000" placeholder="Escribe la respuesta. La verán todos en el foro de la sede."></textarea>
            <span class="for-field-error" id="for-e-respuesta" hidden></span>
            <p class="for-cerrado-aviso" id="for-cerrado-aviso" hidden>El tema está cerrado: reábrelo para responder.</p>
            <div class="for-responder-foot"><button type="submit" class="for-btn for-btn-primary" id="for-r-enviar">Responder</button></div>
        </form>
    </div>
</div>

{{-- ─── Modal: nuevo tema ─── --}}
<div class="for-modal" id="for-modal" hidden>
    <div class="for-modal-overlay" data-cerrar-modal></div>
    <form class="for-modal-panel" id="for-form" role="dialog" aria-modal="true" aria-labelledby="for-modal-titulo" novalidate>
        <div class="for-modal-head">
            <div>
                <h2 class="for-modal-title" id="for-modal-titulo">Nuevo tema</h2>
                <p class="for-modal-sub">Se publica como Admin en el foro de la sede que elijas.</p>
            </div>
            <button type="button" class="for-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="for-modal-body">
            <div class="for-fields">
                <div class="for-field"><label for="for-f-sede">Sede</label><select id="for-f-sede"></select><span class="for-field-error" data-error="sede" hidden></span></div>
                <div class="for-field"><label for="for-f-categoria">Categoría</label><select id="for-f-categoria"></select><span class="for-field-error" data-error="categoria" hidden></span></div>
                <div class="for-field for-field-full"><label for="for-f-titulo">Título</label><input type="text" id="for-f-titulo" maxlength="120" placeholder="Ej. Horario de la biblioteca en diciembre"><span class="for-field-error" data-error="titulo" hidden></span></div>
                <div class="for-field for-field-full"><label for="for-f-texto">Mensaje</label><textarea id="for-f-texto" maxlength="2000"></textarea><span class="for-field-error" data-error="texto" hidden></span></div>
                <label class="for-check for-field-full"><input type="checkbox" id="for-f-fijar"> Fijarlo arriba del foro de la sede</label>
            </div>
        </div>
        <div class="for-modal-foot">
            <button type="button" class="for-btn for-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="for-btn for-btn-primary" id="for-f-publicar">Publicar tema</button>
        </div>
    </form>
</div>

{{-- ─── Modal: ocultar mensaje ─── --}}
<div class="for-modal" id="for-ocultar" hidden>
    <div class="for-modal-overlay" data-cerrar-modal></div>
    <form class="for-modal-panel for-modal-sm" id="for-ocultar-form" role="dialog" aria-modal="true" aria-labelledby="for-oc-titulo" novalidate>
        <div class="for-modal-head">
            <h2 class="for-modal-title" id="for-oc-titulo">Ocultar mensaje</h2>
            <button type="button" class="for-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="for-modal-body">
            <p class="for-modal-text" id="for-oc-texto"></p>
            <div class="for-field for-oc-motivo"><label for="for-oc-motivo">Motivo</label><input type="text" id="for-oc-motivo" maxlength="200" placeholder="Ej. Comparte datos personales"><span class="for-field-error" id="for-e-motivo" hidden></span></div>
        </div>
        <div class="for-modal-foot">
            <button type="button" class="for-btn for-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="for-btn for-btn-primary">Ocultar</button>
        </div>
    </form>
</div>

<div class="for-toast" id="for-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/foro.js') }}?v={{ filemtime(public_path('assets/js/admin_general/foro.js')) }}" defer></script>
@endpush
