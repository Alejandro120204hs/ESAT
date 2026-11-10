{{-- Comunicados (Admin de ESAT general). Datos reales: AdminGeneral ComunicadoController@index (ComunicacionService) los
     pasa en window.GEN_DATOS. El Admin publica, programa o deja en borrador a todas o algunas sedes (una fila por sede,
     agrupadas por lote) para todos o un rol; edita los suyos (un publicado no cambia de destinatarios ni de sedes), archiva
     cualquiera con motivo y elimina sus borradores. comunicados.js escapa todo. --}}
@extends('admin_general.layout')

@section('title', 'Comunicados')
@section('active', 'comunicados')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/comunicados.css') }}?v={{ filemtime(public_path('assets/css/admin_general/comunicados.css')) }}">
@endpush

@section('content')
<div class="com-wrap app-anim">

    <div class="com-header">
        <div>
            <h1 class="com-title">Comunicados</h1>
            <p class="com-sub">Avisos para toda la comunidad de ESAT o para algunas sedes. Aquí también ves lo que publican las secretarías y los docentes, y puedes archivar lo que no corresponda.</p>
        </div>
        <button type="button" class="com-btn com-btn-primary" id="com-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo comunicado
        </button>
    </div>

    <div class="com-kpis" id="com-kpis"></div>

    <div class="com-filters">
        <div class="com-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="com-buscar" placeholder="Buscar por título o mensaje..." autocomplete="off" aria-label="Buscar comunicado">
        </div>
        @foreach ([['com-cb-sede', 'Todas las sedes'], ['com-cb-estado', 'Todos los estados'], ['com-cb-autor', 'Todos los autores']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
        <span class="com-count" id="com-count"></span>
    </div>

    <div class="com-table-wrap">
        <table class="com-table">
            <thead>
                <tr>
                    <th scope="col">Comunicado</th>
                    <th scope="col">Para</th>
                    <th scope="col">Autor</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Lectura</th>
                </tr>
            </thead>
            <tbody id="com-body"></tbody>
        </table>
        <p class="com-empty" id="com-empty" hidden>No hay comunicados con esos filtros.</p>
    </div>

    <nav class="com-pagination" id="com-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Drawer: detalle ─── --}}
<div class="com-drawer" id="com-drawer" aria-hidden="true">
    <div class="com-drawer-overlay" data-cerrar-drawer></div>
    <div class="com-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="com-dw-titulo">
        <div class="com-drawer-head">
            <div>
                <h2 class="com-drawer-title" id="com-dw-titulo"></h2>
                <p class="com-drawer-sub" id="com-dw-sub"></p>
            </div>
            <button type="button" class="com-close" data-cerrar-drawer aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="com-drawer-body">
            <div class="com-info" id="com-dw-info"></div>
            <p class="com-mensaje" id="com-dw-mensaje"></p>
            <p class="com-motivo" id="com-dw-motivo" hidden></p>
        </div>
        <div class="com-drawer-foot">
            <button type="button" class="com-btn com-btn-ghost" id="com-dw-eliminar" hidden>Eliminar borrador</button>
            <div class="com-dw-acciones">
                <button type="button" class="com-btn com-btn-ghost" id="com-dw-archivar" hidden>Archivar</button>
                <button type="button" class="com-btn com-btn-ghost" id="com-dw-duplicar" hidden>Duplicar</button>
                <button type="button" class="com-btn com-btn-primary" id="com-dw-editar" hidden>Editar</button>
            </div>
        </div>
    </div>
</div>

{{-- ─── Modal: crear / editar ─── --}}
<div class="com-modal" id="com-modal" hidden>
    <div class="com-modal-overlay" data-cerrar-modal></div>
    <form class="com-modal-panel com-modal-lg" id="com-form" role="dialog" aria-modal="true" aria-labelledby="com-modal-titulo" novalidate>
        <div class="com-modal-head">
            <div>
                <h2 class="com-modal-title" id="com-modal-titulo">Nuevo comunicado</h2>
                <p class="com-modal-sub">Lo verán en su panel; a quien no lo lea le puede llegar el recordatorio por WhatsApp desde su sede.</p>
            </div>
            <button type="button" class="com-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="com-modal-body">
            <div class="com-fields">
                <div class="com-field com-field-full">
                    <fieldset class="com-opciones" id="com-f-alcance">
                        <legend>Sedes</legend>
                        <label class="com-opcion"><input type="radio" name="alcance" value="todas" checked><span>Todas las sedes</span></label>
                        <label class="com-opcion"><input type="radio" name="alcance" value="algunas"><span>Algunas sedes</span></label>
                    </fieldset>
                    <div class="com-sedes-lista" id="com-f-sedes" hidden></div>
                    <span class="com-field-error" id="com-e-sedes" hidden></span>
                </div>
                <div class="com-field com-field-full">
                    <fieldset class="com-opciones" id="com-f-destino">
                        <legend>Para quién</legend>
                        <label class="com-opcion"><input type="radio" name="destino" value="todos" checked><span>Todos</span></label>
                        <label class="com-opcion"><input type="radio" name="destino" value="estudiantes"><span>Estudiantes</span></label>
                        <label class="com-opcion"><input type="radio" name="destino" value="docentes"><span>Docentes</span></label>
                        <label class="com-opcion"><input type="radio" name="destino" value="acudientes"><span>Acudientes</span></label>
                    </fieldset>
                    <p class="com-alcance" id="com-alcance"></p>
                    <p class="com-bloqueado" id="com-bloqueado" hidden>Ya está publicado: no se pueden cambiar las sedes ni los destinatarios.</p>
                </div>
                <div class="com-field com-field-full">
                    <label for="com-f-titulo">Título</label>
                    <input type="text" id="com-f-titulo" maxlength="120" placeholder="Ej. Horario de atención en diciembre" autocomplete="off">
                    <span class="com-field-error" id="com-e-titulo" hidden></span>
                </div>
                <div class="com-field com-field-full">
                    <label for="com-f-mensaje">Mensaje</label>
                    <textarea id="com-f-mensaje" maxlength="2000" placeholder="Escribe el aviso con las fechas, horas y lugares que necesiten saber."></textarea>
                    <span class="com-contador" id="com-contador">0 / 2000</span>
                    <span class="com-field-error" id="com-e-mensaje" hidden></span>
                </div>
                <div class="com-field com-field-full" id="com-cuando-wrap">
                    <fieldset class="com-opciones" id="com-f-cuando">
                        <legend>Cuándo sale</legend>
                        <label class="com-opcion"><input type="radio" name="cuando" value="ahora" checked><span>Publicar ahora</span></label>
                        <label class="com-opcion"><input type="radio" name="cuando" value="programar"><span>Programar</span></label>
                        <label class="com-opcion"><input type="radio" name="cuando" value="borrador"><span>Guardar como borrador</span></label>
                    </fieldset>
                    <div class="com-programar" id="com-programar" hidden>
                        <label for="com-f-fecha" class="com-label-bloque">Fecha y hora de publicación</label>
                        <input type="datetime-local" id="com-f-fecha">
                    </div>
                    <span class="com-field-error" id="com-e-fecha" hidden></span>
                </div>
            </div>
        </div>
        <div class="com-modal-foot">
            <button type="button" class="com-btn com-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="com-btn com-btn-primary" id="com-guardar">Publicar</button>
        </div>
    </form>
</div>

{{-- ─── Modal: archivar / eliminar ─── --}}
<div class="com-modal" id="com-confirmar" hidden>
    <div class="com-modal-overlay" data-cerrar-modal></div>
    <form class="com-modal-panel com-modal-sm" id="com-conf-form" role="alertdialog" aria-modal="true" aria-labelledby="com-conf-titulo" novalidate>
        <div class="com-modal-head">
            <h2 class="com-modal-title" id="com-conf-titulo"></h2>
            <button type="button" class="com-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="com-modal-body">
            <p class="com-modal-text" id="com-conf-texto"></p>
            <div class="com-field com-conf-motivo" id="com-conf-motivo-wrap">
                <label for="com-conf-motivo">Motivo</label>
                <input type="text" id="com-conf-motivo" maxlength="200" placeholder="Ej. La actividad se canceló" autocomplete="off">
                <span class="com-field-error" id="com-e-motivo" hidden></span>
            </div>
        </div>
        <div class="com-modal-foot">
            <button type="button" class="com-btn com-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="com-btn com-btn-primary" id="com-conf-ok"></button>
        </div>
    </form>
</div>

<div class="com-toast" id="com-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/comunicados.js') }}?v={{ filemtime(public_path('assets/js/admin_general/comunicados.js')) }}" defer></script>
@endpush
