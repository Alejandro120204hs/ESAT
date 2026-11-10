{{-- Programas (Admin de ESAT general). Datos reales: AdminGeneral ProgramaController@index los pasa en window.GEN_DATOS.
     Decidido con el usuario: el Admin crea y edita escuelas y programas (con su malla) y fija el precio (uno solo para todas
     las sedes); la secretaría solo elige cuáles ofrece su sede. Editar no puede invalidar los grupos vigentes (cupo, jornadas,
     horas: ProgramaRequest) y la malla no cambia si ya hay notas. Todo queda en la auditoría. --}}
@extends('admin_general.layout')

@section('title', 'Programas')
@section('active', 'programas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/programas.css') }}?v={{ filemtime(public_path('assets/css/admin_general/programas.css')) }}">
@endpush

@php
    $cerrar = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
@endphp

@section('content')
<div class="prg-wrap app-anim">

    <div class="prg-header">
        <div>
            <h1 class="prg-title">Programas</h1>
            <p class="prg-sub">El catálogo de ESAT: escuelas, programas, malla de módulos y precios, iguales para todas las sedes. Cada secretaría elige cuáles ofrece en su sede.</p>
        </div>
        <button type="button" class="prg-btn prg-btn-primary" id="prg-nuevo">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span id="prg-nuevo-txt">Nuevo programa</span>
        </button>
    </div>

    <div class="prg-kpis" id="prg-kpis"></div>

    <div class="prg-ptabs" role="tablist">
        <button type="button" class="prg-ptab is-active" role="tab" data-ptab="programas" aria-selected="true">Programas</button>
        <button type="button" class="prg-ptab" role="tab" data-ptab="escuelas" aria-selected="false">Escuelas</button>
    </div>

    {{-- ─── Programas ─── --}}
    <section class="prg-ppanel" data-ppanel="programas" aria-label="Programas">
        <div class="prg-filters">
            <div class="prg-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="prg-buscar" placeholder="Buscar por nombre o código..." autocomplete="off" aria-label="Buscar programa">
            </div>
            @foreach ([['prg-cb-escuela', 'Todas las escuelas'], ['prg-cb-sede', 'Ofrecido en cualquier sede'], ['prg-cb-estado', 'Todos los estados']] as [$id, $texto])
            <div class="app-combobox" id="{{ $id }}" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                    <span data-combobox-label>{{ $texto }}</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                <input type="hidden" data-combobox-value>
            </div>
            @endforeach
            <span class="prg-count" id="prg-count"></span>
        </div>
        <div class="prg-table-wrap">
            <table class="prg-table">
                <thead><tr>
                    <th scope="col">Programa</th><th scope="col">Escuela</th><th scope="col">Duración</th><th scope="col" class="prg-num">Precio</th>
                    <th scope="col">Sedes que lo ofrecen</th><th scope="col" class="prg-num">Estudiantes</th><th scope="col">Estado</th>
                </tr></thead>
                <tbody id="prg-body"></tbody>
            </table>
            <p class="prg-empty" id="prg-empty" hidden>No hay programas con esos filtros.</p>
        </div>
    </section>

    {{-- ─── Escuelas ─── --}}
    <section class="prg-ppanel" data-ppanel="escuelas" aria-label="Escuelas" hidden>
        <div class="prg-table-wrap">
            <table class="prg-table">
                <thead><tr><th scope="col">Escuela</th><th scope="col">Sigla</th><th scope="col" class="prg-num">Programas</th><th scope="col" class="prg-num">Docentes</th><th scope="col"><span class="prg-sr">Acciones</span></th></tr></thead>
                <tbody id="prg-esc-body"></tbody>
            </table>
        </div>
    </section>
</div>

{{-- ─── Drawer: programa ─── --}}
<div class="prg-drawer" id="prg-drawer" aria-hidden="true">
    <div class="prg-drawer-overlay" data-cerrar-drawer></div>
    <div class="prg-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="prg-dw-nombre">
        <div class="prg-drawer-head">
            <div>
                <h2 class="prg-drawer-title" id="prg-dw-nombre"></h2>
                <p class="prg-drawer-sub" id="prg-dw-sub"></p>
            </div>
            <button type="button" class="prg-close" data-cerrar-drawer aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="prg-tabs" role="tablist">
            <button type="button" class="prg-tab is-active" role="tab" data-tab="datos" aria-selected="true">Datos y precio</button>
            <button type="button" class="prg-tab" role="tab" data-tab="malla" aria-selected="false">Malla</button>
            <button type="button" class="prg-tab" role="tab" data-tab="sedes" aria-selected="false">Sedes</button>
        </div>
        <div class="prg-drawer-body">
            <div class="prg-panel" data-panel="datos" role="tabpanel" id="prg-dw-datos"></div>
            <div class="prg-panel" data-panel="malla" role="tabpanel" id="prg-dw-malla" hidden></div>
            <div class="prg-panel" data-panel="sedes" role="tabpanel" id="prg-dw-sedes" hidden></div>
        </div>
        <div class="prg-drawer-foot">
            <button type="button" class="prg-btn prg-btn-ghost" id="prg-dw-estado"></button>
            <button type="button" class="prg-btn prg-btn-primary" id="prg-dw-editar">Editar programa</button>
        </div>
    </div>
</div>

{{-- ─── Modal: crear / editar programa (3 pasos) ─── --}}
<div class="prg-modal" id="prg-modal" hidden>
    <div class="prg-modal-overlay" data-cerrar-modal></div>
    <form class="prg-modal-panel prg-modal-lg" id="prg-form" role="dialog" aria-modal="true" aria-labelledby="prg-modal-titulo" novalidate>
        <div class="prg-modal-head">
            <div>
                <h2 class="prg-modal-title" id="prg-modal-titulo">Nuevo programa</h2>
                <p class="prg-modal-sub" id="prg-modal-sub">Paso 1 de 3 · Datos del programa</p>
            </div>
            <button type="button" class="prg-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="prg-modal-body">
            <div class="prg-paso" data-paso="1">
                <div class="prg-fields">
                    <div class="prg-field prg-field-full"><label for="prg-f-nombre">Nombre</label><input type="text" id="prg-f-nombre" maxlength="150" placeholder="Técnico Laboral en ..."><span class="prg-field-error" data-error="nombre" hidden></span></div>
                    <div class="prg-field"><label for="prg-f-codigo">Código</label><input type="text" id="prg-f-codigo" maxlength="20" placeholder="ESA-SAL-004"><span class="prg-field-error" data-error="codigo" hidden></span></div>
                    <div class="prg-field"><label for="prg-f-escuela">Escuela</label><select id="prg-f-escuela"></select><span class="prg-field-error" data-error="escuela" hidden></span></div>
                    <div class="prg-field"><label for="prg-f-modalidad">Modalidad</label><select id="prg-f-modalidad"><option>Presencial</option><option>Mixta</option><option>Virtual</option></select></div>
                    <div class="prg-field"><label for="prg-f-nivel">Nivel</label><select id="prg-f-nivel">@foreach (\App\Models\Academico\Programa::NIVELES as $nivel)<option>{{ $nivel }}</option>@endforeach</select></div>
                    <div class="prg-field"><label for="prg-f-estado">Estado</label><select id="prg-f-estado"><option value="activo">Activo</option><option value="en_aprobacion">En aprobación</option></select></div>
                    <div class="prg-field prg-field-full"><label for="prg-f-resolucion">Resolución de aprobación</label><input type="text" id="prg-f-resolucion" maxlength="120" placeholder="Res. 0000-2026 · SED Bogotá, o «En trámite»"><span class="prg-field-error" data-error="resolucion" hidden></span></div>
                    <fieldset class="prg-field prg-field-full prg-jornadas" id="prg-f-jornadas">
                        <legend class="prg-label">Jornadas en que se puede dictar</legend>
                        <div class="prg-checks">
                            @foreach (['Mañana', 'Tarde', 'Noche', 'Fines de semana'] as $j)
                            <label class="prg-check"><input type="checkbox" value="{{ $j }}">{{ $j }}</label>
                            @endforeach
                        </div>
                        <span class="prg-field-error" data-error="jornadas" hidden></span>
                    </fieldset>
                    <div class="prg-field"><label for="prg-f-cupo">Cupo por grupo</label><input type="number" id="prg-f-cupo" min="5" max="60" step="1"><span class="prg-field-error" data-error="cupo" hidden></span></div>
                </div>
            </div>
            <div class="prg-paso" data-paso="2" hidden>
                <div class="prg-fields">
                    <div class="prg-field"><label for="prg-f-meses">Duración (meses)</label><input type="number" id="prg-f-meses" min="3" max="36" step="1"><span class="prg-field-error" data-error="meses" hidden></span></div>
                    <div class="prg-field"><label for="prg-f-periodo">Se divide en</label><select id="prg-f-periodo"><option value="trimestre">Trimestres</option><option value="semestre">Semestres</option></select><span class="prg-hint" id="prg-periodos"></span></div>
                    <div class="prg-field"><label for="prg-f-precio">Precio total del programa</label><div class="prg-prefijo"><span>$</span><input type="text" id="prg-f-precio" inputmode="numeric" maxlength="13"></div><span class="prg-field-error" data-error="precio" hidden></span></div>
                    <div class="prg-field"><label for="prg-f-matricula">Derecho de matrícula</label><div class="prg-prefijo"><span>$</span><input type="text" id="prg-f-matricula" inputmode="numeric" maxlength="11"></div><span class="prg-field-error" data-error="matricula" hidden></span></div>
                </div>
                <div class="prg-resumen" id="prg-resumen-precio"></div>
            </div>
            <div class="prg-paso" data-paso="3" hidden>
                <p class="prg-hint prg-malla-ayuda">Los módulos se dictan en este orden. Las horas del programa son la suma de las horas de los módulos.</p>
                <p class="prg-nota" id="prg-malla-bloqueada" hidden>Ya hay notas puestas en los módulos de este programa: la malla queda como está.</p>
                <ol class="prg-malla-edit" id="prg-malla-edit"></ol>
                <button type="button" class="prg-btn prg-btn-ghost prg-btn-sm" id="prg-mod-agregar">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Agregar módulo
                </button>
                <span class="prg-field-error" data-error="malla" hidden></span>
                <p class="prg-malla-total" id="prg-malla-total"></p>
            </div>
        </div>
        <div class="prg-modal-foot">
            <button type="button" class="prg-btn prg-btn-ghost" id="prg-atras">Atrás</button>
            <button type="submit" class="prg-btn prg-btn-primary" id="prg-siguiente">Siguiente</button>
        </div>
    </form>
</div>

{{-- ─── Modal: escuela ─── --}}
<div class="prg-modal" id="prg-esc-modal" hidden>
    <div class="prg-modal-overlay" data-cerrar-modal></div>
    <form class="prg-modal-panel prg-modal-sm" id="prg-esc-form" role="dialog" aria-modal="true" aria-labelledby="prg-esc-titulo" novalidate>
        <div class="prg-modal-head">
            <h2 class="prg-modal-title" id="prg-esc-titulo">Nueva escuela</h2>
            <button type="button" class="prg-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="prg-modal-body">
            <div class="prg-fields">
                <div class="prg-field prg-field-full"><label for="prg-e-nombre">Nombre</label><input type="text" id="prg-e-nombre" maxlength="80" placeholder="Ej. Belleza"><span class="prg-field-error" data-error="e-nombre" hidden></span></div>
                <div class="prg-field" id="prg-e-sigla-campo"><label for="prg-e-sigla">Sigla</label><input type="text" id="prg-e-sigla" readonly><span class="prg-hint">Se generó al crearla y no cambia: va en el código de sus programas.</span></div>
            </div>
        </div>
        <div class="prg-modal-foot">
            <button type="button" class="prg-btn prg-btn-ghost" id="prg-esc-eliminar" hidden>Eliminar escuela</button>
            <button type="button" class="prg-btn prg-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="prg-btn prg-btn-primary" id="prg-esc-guardar">Crear escuela</button>
        </div>
    </form>
</div>

{{-- ─── Modal: confirmar ─── --}}
<div class="prg-modal" id="prg-confirmar" hidden>
    <div class="prg-modal-overlay" data-cerrar-modal></div>
    <div class="prg-modal-panel prg-modal-sm" role="alertdialog" aria-modal="true" aria-labelledby="prg-conf-titulo" aria-describedby="prg-conf-texto">
        <div class="prg-modal-head">
            <h2 class="prg-modal-title" id="prg-conf-titulo"></h2>
            <button type="button" class="prg-close" data-cerrar-modal aria-label="Cerrar">{!! $cerrar !!}</button>
        </div>
        <div class="prg-modal-body"><p class="prg-modal-text" id="prg-conf-texto"></p></div>
        <div class="prg-modal-foot">
            <button type="button" class="prg-btn prg-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="button" class="prg-btn prg-btn-primary" id="prg-conf-ok"></button>
        </div>
    </div>
</div>

<div class="prg-toast" id="prg-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/programas.js') }}?v={{ filemtime(public_path('assets/js/admin_general/programas.js')) }}" defer></script>
@endpush
