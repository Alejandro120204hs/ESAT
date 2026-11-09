{{-- Estudiantes (Admin de ESAT general) — SOLO FRONTEND con datos de ejemplo (public/assets/js/admin_general/datos-ejemplo.js).
     Estudiantes, acudientes, notas y pagos son ficticios; sedes y programas son reales. El Admin registra y edita a la PERSONA
     igual que la secretaría, en cualquier sede (correo obligatorio = usuario, clave = documento, acudiente obligatorio si es
     menor). Matricular y cobrar sigue siendo de Matrículas: el Admin entra a la sede. estudiantes.js pinta todo escapado y
     exporta lo filtrado a Excel (CSV). --}}
@extends('admin_general.layout')

@section('title', 'Estudiantes')
@section('active', 'estudiantes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/estudiantes.css') }}?v={{ filemtime(public_path('assets/css/admin_general/estudiantes.css')) }}">
@endpush

@section('content')
<div class="est-wrap app-anim">

    <div class="est-header">
        <div>
            <h1 class="est-title">Estudiantes</h1>
            <p class="est-sub">Todos los estudiantes de ESAT con su matrícula y cómo van con los pagos. Registra o corrige sus datos en cualquier sede; para matricularlo o cobrarle, entra a la sede.</p>
        </div>
        <div class="est-header-acciones">
            <button type="button" class="est-btn est-btn-ghost" id="est-exportar" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
            <button type="button" class="est-btn est-btn-primary" id="est-nuevo">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo estudiante
            </button>
        </div>
    </div>

    <div class="est-kpis" id="est-kpis"></div>

    <div class="est-filters">
        <div class="est-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="est-buscar" placeholder="Buscar por nombre o documento..." autocomplete="off" aria-label="Buscar estudiante">
        </div>
        @foreach ([['est-cb-sede', 'Todas las sedes'], ['est-cb-programa', 'Todos los programas'], ['est-cb-estado', 'Matrículas vigentes'], ['est-cb-pago', 'Todos los pagos']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
        <span class="est-count" id="est-count"></span>
    </div>

    <div class="est-table-wrap">
        <table class="est-table">
            <thead>
                <tr>
                    <th scope="col">Estudiante</th>
                    <th scope="col">Sede</th>
                    <th scope="col">Programa</th>
                    <th scope="col">Matrícula</th>
                    <th scope="col">Pagos</th>
                    <th scope="col"><span class="est-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="est-body"></tbody>
        </table>
        <p class="est-empty" id="est-empty" hidden>No hay estudiantes que coincidan con los filtros.</p>
    </div>

    <nav class="est-pagination" id="est-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Drawer: ficha del estudiante (consulta) ─── --}}
<div class="est-drawer" id="est-drawer" aria-hidden="true">
    <div class="est-drawer-overlay" data-cerrar-drawer></div>
    <div class="est-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="est-dw-nombre">
        <div class="est-drawer-head">
            <div class="est-drawer-head-left">
                <div class="est-drawer-avatar" id="est-dw-avatar"></div>
                <div>
                    <h2 class="est-drawer-title" id="est-dw-nombre"></h2>
                    <p class="est-drawer-sub" id="est-dw-doc"></p>
                    <div class="est-badges" id="est-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="est-close" data-cerrar-drawer aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="est-tabs" role="tablist">
            <button type="button" class="est-tab is-active" role="tab" data-tab="datos" aria-selected="true">Datos</button>
            <button type="button" class="est-tab" role="tab" data-tab="matricula" aria-selected="false">Matrícula</button>
            <button type="button" class="est-tab" role="tab" data-tab="pagos" aria-selected="false">Pagos</button>
        </div>
        <div class="est-drawer-body">
            <div class="est-panel" data-panel="datos" role="tabpanel" id="est-dw-datos"></div>
            <div class="est-panel" data-panel="matricula" role="tabpanel" id="est-dw-matricula" hidden></div>
            <div class="est-panel" data-panel="pagos" role="tabpanel" id="est-dw-pagos" hidden></div>
        </div>
        <div class="est-drawer-foot">
            <button type="button" class="est-btn est-btn-ghost" id="est-dw-entrar" title="Para matricularlo o cobrarle">
                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Matricular o cobrar
            </button>
            <button type="button" class="est-btn est-btn-primary" id="est-dw-editar">Editar datos</button>
        </div>
    </div>
</div>

{{-- ─── Modal: entrar a la sede ─── --}}
<div class="est-modal" id="est-entrar" hidden>
    <div class="est-modal-overlay" data-cerrar-modal></div>
    <div class="est-modal-panel est-modal-sm" role="dialog" aria-modal="true" aria-labelledby="est-entrar-titulo">
        <div class="est-modal-head">
            <div>
                <h2 class="est-modal-title" id="est-entrar-titulo">Entrar a la sede</h2>
                <p class="est-modal-sub" id="est-entrar-sub"></p>
            </div>
            <button type="button" class="est-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="est-modal-body">
            <p class="est-modal-text">Vas a trabajar en esta sede con las mismas pantallas de la secretaría y se abrirá la ficha de este estudiante. Todo lo que hagas queda en la auditoría de la sede con tu nombre.</p>
            <p class="est-nota" id="est-entrar-nota" hidden>Esta pantalla todavía es una maqueta: entrar a la sede funcionará cuando se conecte el backend.</p>
        </div>
        <div class="est-modal-foot">
            <button type="button" class="est-btn est-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="button" class="est-btn est-btn-primary" id="est-entrar-ok">Entrar a la sede</button>
        </div>
    </div>
</div>

{{-- ─── Modal: registrar / editar estudiante (2 pasos) ─── --}}
<div class="est-modal" id="est-modal" hidden>
    <div class="est-modal-overlay" data-cerrar-modal></div>
    <form class="est-modal-panel" id="est-form" role="dialog" aria-modal="true" aria-labelledby="est-modal-titulo" novalidate>
        <div class="est-modal-head">
            <div>
                <h2 class="est-modal-title" id="est-modal-titulo">Nuevo estudiante</h2>
                <p class="est-modal-sub" id="est-modal-sub">Paso 1 de 2 · Datos personales</p>
            </div>
            <button type="button" class="est-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="est-modal-body">
            <div class="est-paso" data-paso="1">
                <div class="est-fields">
                    <div class="est-field est-field-full"><label for="est-f-sede">Sede</label><select id="est-f-sede"></select><span class="est-field-error" data-error="sede" hidden></span></div>
                    <div class="est-field"><label for="est-f-nombres">Nombres</label><input type="text" id="est-f-nombres" maxlength="60" autocomplete="off"><span class="est-field-error" data-error="nombres" hidden></span></div>
                    <div class="est-field"><label for="est-f-apellidos">Apellidos</label><input type="text" id="est-f-apellidos" maxlength="60" autocomplete="off"><span class="est-field-error" data-error="apellidos" hidden></span></div>
                    <div class="est-field"><label for="est-f-tipo">Tipo de documento</label><select id="est-f-tipo"><option value="CC">Cédula de ciudadanía</option><option value="TI">Tarjeta de identidad</option><option value="CE">Cédula de extranjería</option><option value="PA">Pasaporte</option><option value="PPT">Permiso por protección temporal</option></select></div>
                    <div class="est-field"><label for="est-f-documento">Número de documento</label><input type="text" id="est-f-documento" inputmode="numeric" maxlength="12" autocomplete="off"><span class="est-field-error" data-error="documento" hidden></span></div>
                    <div class="est-field"><label for="est-f-nacimiento">Fecha de nacimiento</label><input type="date" id="est-f-nacimiento"><span class="est-hint" id="est-edad"></span><span class="est-field-error" data-error="nacimiento" hidden></span></div>
                    <div class="est-field"><label for="est-f-genero">Género</label><select id="est-f-genero"><option value="">Selecciona</option><option value="femenino">Femenino</option><option value="masculino">Masculino</option><option value="otro">Otro</option></select><span class="est-field-error" data-error="genero" hidden></span></div>
                </div>
            </div>
            <div class="est-paso" data-paso="2" hidden>
                <div class="est-fields">
                    <div class="est-field"><label for="est-f-telefono">Celular</label><input type="tel" id="est-f-telefono" inputmode="numeric" maxlength="14" placeholder="3001234567" autocomplete="off"><span class="est-field-error" data-error="telefono" hidden></span></div>
                    <div class="est-field"><label for="est-f-correo">Correo (es su usuario)</label><input type="email" id="est-f-correo" maxlength="120" autocomplete="off"><span class="est-field-error" data-error="correo" hidden></span></div>
                    <div class="est-field est-field-full"><label for="est-f-ciudad">Ciudad de residencia</label><input type="text" id="est-f-ciudad" maxlength="80" autocomplete="off"><span class="est-field-error" data-error="ciudad" hidden></span></div>
                </div>
                <h3 class="est-seccion">Acudiente <span class="est-muted" id="est-acu-req"></span></h3>
                <div class="est-fields">
                    <div class="est-field"><label for="est-f-acudiente">Acudiente de la sede</label><select id="est-f-acudiente"></select><span class="est-field-error" data-error="acudiente" hidden></span></div>
                    <div class="est-field"><label for="est-f-parentesco">Parentesco</label><select id="est-f-parentesco"></select><span class="est-field-error" data-error="parentesco" hidden></span></div>
                </div>
                <p class="est-hint est-hint-acu">¿El acudiente no está en la lista? Regístralo primero en <a class="est-link" href="/general/acudientes">Acudientes</a>.</p>
                <p class="est-nota" id="est-nota-clave">La clave inicial es su número de documento. Para matricularlo, entra a la sede: sin todos los documentos requeridos entregados no se matricula.</p>
            </div>
        </div>
        <div class="est-modal-foot">
            <button type="button" class="est-btn est-btn-ghost" id="est-atras">Atrás</button>
            <button type="submit" class="est-btn est-btn-primary" id="est-siguiente">Siguiente</button>
        </div>
    </form>
</div>

<div class="est-toast" id="est-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin_general/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/admin_general/datos-ejemplo.js')) }}"></script>
    <script src="{{ asset('assets/js/admin_general/estudiantes.js') }}?v={{ filemtime(public_path('assets/js/admin_general/estudiantes.js')) }}" defer></script>
@endpush
