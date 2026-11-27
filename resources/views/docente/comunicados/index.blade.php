{{-- Docente › Comunicados (Docente/Comunicados/ComunicadoController + ComunicadoDocenteService). DATOS REALES. Decidido con
     el usuario el 24-11-2026:
     - Recibidos: lo que la secretaría o el Admin manda a toda la sede o a los docentes (la lista + lector del panel
       Estudiante). Abrir uno lo marca leído en comunicado_lecturas: la secretaría ve lecturas reales.
     - Mis publicaciones: lo que el docente publica a uno o varios de sus grupos (una fila por grupo con el mismo lote).
       Publicar ya, programar o borrador, con adjunto PDF/JPG/PNG ≤ 5 MB; un publicado cambia su texto pero no sus
       grupos; archivar con motivo; solo se eliminan borradores. Quién lo leyó por grupo y WhatsApp a quien falta (a su
       contacto de emergencia si es menor), como en Secretaría › Comunicados.
     Enlaces: ?id=ID abre uno recibido; ?vista=mios abre Mis publicaciones. Tarjetas, cajón y diálogo del Aula virtual. --}}
@extends('docente.layout')

@section('title', 'Comunicados')
@section('active', 'comunicados')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/docente/comunicados.css') }}?v={{ filemtime(public_path('assets/css/docente/comunicados.css')) }}">
@endpush

@section('content')
<div class="com-wrap app-anim" id="com-app">

    <header class="com-header">
        <div class="com-header-txt">
            <h2 class="com-title">Comunicados</h2>
            <p class="com-sub">Los avisos que te mandan el administrativo de tu sede y el Admin de ESAT, y los que tú publicas a tus grupos.</p>
        </div>
        <button type="button" class="com-btn com-btn-primary" id="com-nuevo">
            <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo comunicado
        </button>
    </header>

    <div class="com-toolbar app-anim app-anim-d1">
        <div class="com-vistas" role="tablist" aria-label="Comunicados">
            <button type="button" class="com-vista-btn is-active" role="tab" id="com-tab-recibidos" aria-selected="true" aria-controls="com-panel-recibidos" data-vista="recibidos">
                Recibidos <span class="com-vista-num" id="com-num-sinleer"></span>
            </button>
            <button type="button" class="com-vista-btn" role="tab" id="com-tab-mios" aria-selected="false" aria-controls="com-panel-mios" data-vista="mios" tabindex="-1">
                Mis publicaciones
            </button>
        </div>
        <p class="com-resumen" id="com-resumen" aria-live="polite"></p>
    </div>

    {{-- Recibidos: lista + lector --}}
    <div class="com-panel app-anim app-anim-d2" id="com-panel-recibidos" role="tabpanel" aria-labelledby="com-tab-recibidos">
        <div class="com-layout" id="com-layout">
            <div class="com-col-lista">
                <ul class="com-lista" id="com-lista"></ul>
                <p class="com-lista-vacia" id="com-lista-vacia" hidden>Todavía no te han llegado comunicados.</p>
            </div>
            <article class="com-lector" id="com-lector" aria-live="polite" tabindex="-1"></article>
        </div>
    </div>

    {{-- Mis publicaciones: tarjetas --}}
    <div class="com-panel" id="com-panel-mios" role="tabpanel" aria-labelledby="com-tab-mios" hidden>
        <div class="com-grid" id="com-grid"></div>
        <div class="com-vacio" id="com-vacio" hidden>
            <p class="com-vacio-ttl">Todavía no has publicado comunicados</p>
            <p class="com-vacio-sub">Con «Nuevo comunicado» les escribes a uno o varios de tus grupos: lo ven en su panel y aquí sabrás quién lo leyó.</p>
        </div>
    </div>
</div>

{{-- ═══════════ CAJÓN: ficha o formulario (lo arma comunicados.js) ═══════════ --}}
<div class="com-drawer" id="com-drawer" aria-hidden="true">
    <div class="com-drawer-overlay" data-cerrar></div>
    <div class="com-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="com-dw-titulo">
        <div class="com-drawer-head">
            <div class="com-drawer-head-info">
                <h3 class="com-drawer-title" id="com-dw-titulo"></h3>
                <div class="com-drawer-sub" id="com-dw-sub"></div>
            </div>
            <button type="button" class="com-icon-btn" id="com-dw-cerrar" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="com-drawer-body" id="com-dw-body" tabindex="0" role="region" aria-label="Contenido"></div>
        <div class="com-drawer-foot" id="com-dw-foot" hidden></div>
    </div>
</div>

{{-- Confirmación (archivar con motivo / eliminar borrador) --}}
<div class="com-dialogo" id="com-dialogo" hidden>
    <div class="com-dialogo-fondo" data-no></div>
    <div class="com-dialogo-caja" role="alertdialog" aria-modal="true" aria-labelledby="com-dlg-titulo" aria-describedby="com-dlg-txt">
        <h3 class="com-dialogo-titulo" id="com-dlg-titulo"></h3>
        <p class="com-dialogo-txt" id="com-dlg-txt"></p>
        <div class="com-campo" id="com-dlg-motivo-campo" hidden>
            <label for="com-dlg-motivo">Motivo</label>
            <textarea class="com-input" id="com-dlg-motivo" maxlength="255" rows="3" placeholder="Ej.: La práctica se aplazó"></textarea>
        </div>
        <p class="com-error" id="com-dlg-error" role="alert"></p>
        <div class="com-dialogo-acciones">
            <button type="button" class="com-btn com-btn-ghost" data-no>Volver</button>
            <button type="button" class="com-btn com-btn-peligro" id="com-dlg-si"></button>
        </div>
    </div>
</div>

<div class="com-toast" id="com-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    @php
        $comServidor = ['datos' => $datos, 'rutas' => [
            'crear'    => route('docente.comunicados.store'),
            'editar'   => route('docente.comunicados.update', ['comunicado' => '__ID__']),
            'archivar' => route('docente.comunicados.archivar', ['comunicado' => '__ID__']),
            'eliminar' => route('docente.comunicados.destroy', ['comunicado' => '__ID__']),
            'leido'    => route('docente.comunicados.leido', ['comunicado' => '__ID__']),
        ]];
    @endphp
    <script>
        window.COM_SERVIDOR = @json($comServidor);
    </script>
    <script src="{{ asset('assets/js/docente/comunicados.js') }}?v={{ filemtime(public_path('assets/js/docente/comunicados.js')) }}" defer></script>
@endpush
