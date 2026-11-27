{{-- Estudiante › Aula virtual (Estudiante/Aula/AulaController): las evaluaciones y actividades que el docente publica
     para su grupo (AulaService). Reglas acordadas con el usuario: cada evaluación se responde una sola vez hasta su
     cierre, la nota sale al enviar (5,0 × aciertos ÷ preguntas, mínimo 1,0) y las correctas se muestran después del
     cierre; cada actividad recibe y cambia su archivo hasta la fecha límite (PDF, Word, JPG o PNG, hasta 10 MB) o hasta
     que la califiquen; sus notas son aparte de la nota del módulo, que pone el docente. Enlaces: ?id=evaluacion-ID o
     ?id=actividad-ID abre una, ?modulo=ID filtra por módulo. Cajón lateral, pestañas y chips copiados de Docente › Mis
     grupos (DESIGN-PANELES.md). --}}
@extends('estudiante.layout')

@section('title', 'Aula virtual')
@section('active', 'aula')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/aula.css') }}?v={{ filemtime(public_path('assets/css/estudiante/aula.css')) }}">
@endpush

@section('content')
<div class="aul-wrap app-anim" id="aul-app" data-url-programa="{{ route('estudiante.programa') }}">

    <header class="aul-header">
        <h2 class="aul-title">Aula virtual</h2>
        <p class="aul-sub">Las evaluaciones, actividades y material de clase que te deja tu docente. Cada una tiene su nota; la nota del módulo la sigue poniendo tu docente.</p>
    </header>

    <div class="aul-toolbar app-anim app-anim-d1">
        <div class="aul-vistas" role="tablist" aria-label="Aula virtual">
            <button type="button" class="aul-vista-btn is-active" role="tab" id="aul-tab-pendientes" aria-selected="true" aria-controls="aul-panel" data-vista="pendientes">
                Por hacer <span class="aul-vista-num" id="aul-num-pendientes"></span>
            </button>
            <button type="button" class="aul-vista-btn" role="tab" id="aul-tab-resultados" aria-selected="false" aria-controls="aul-panel" data-vista="resultados" tabindex="-1">
                Resultados <span class="aul-vista-num" id="aul-num-resultados"></span>
            </button>
            <button type="button" class="aul-vista-btn" role="tab" id="aul-tab-material" aria-selected="false" aria-controls="aul-panel" data-vista="material" tabindex="-1">
                Material <span class="aul-vista-num" id="aul-num-material"></span>
            </button>
        </div>
        <fieldset class="aul-chips" id="aul-modulos">
            <legend class="sr-solo">Filtrar por módulo</legend>
        </fieldset>
    </div>

    <section class="aul-panel app-anim app-anim-d2" id="aul-panel" role="tabpanel" aria-labelledby="aul-tab-pendientes">
        <p class="aul-resumen" id="aul-resumen"></p>
        <div class="aul-grid" id="aul-grid"></div>
        <div class="aul-empty" id="aul-empty" hidden>
            <div class="aul-empty-icono" aria-hidden="true"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <p class="aul-empty-ttl" id="aul-empty-ttl"></p>
            <p class="aul-empty-sub" id="aul-empty-sub"></p>
        </div>
    </section>
</div>

{{-- ═══════════ CAJÓN: responder / ver resultado / entregar (lo arma aula.js) ═══════════ --}}
<div class="aul-drawer" id="aul-drawer" aria-hidden="true">
    <div class="aul-drawer-overlay" data-cerrar></div>
    <div class="aul-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="aul-dw-titulo">
        <div class="aul-drawer-head">
            <div class="aul-drawer-head-left">
                <div class="aul-drawer-avatar" id="aul-dw-avatar" aria-hidden="true"></div>
                <div class="aul-drawer-head-info">
                    <h3 class="aul-drawer-title" id="aul-dw-titulo"></h3>
                    <div class="aul-drawer-sub" id="aul-dw-sub"></div>
                </div>
            </div>
            <button type="button" class="aul-icon-btn" id="aul-dw-cerrar" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="aul-drawer-body" id="aul-dw-body" tabindex="0" role="region" aria-label="Contenido"></div>
        <div class="aul-drawer-foot" id="aul-dw-foot" hidden></div>
    </div>
</div>

{{-- Confirmación antes de enviar (una evaluación se responde una sola vez) --}}
<div class="aul-dialogo" id="aul-dialogo" hidden>
    <div class="aul-dialogo-fondo" data-no></div>
    <div class="aul-dialogo-caja" role="alertdialog" aria-modal="true" aria-labelledby="aul-dlg-titulo" aria-describedby="aul-dlg-txt">
        <h3 class="aul-dialogo-titulo" id="aul-dlg-titulo"></h3>
        <p class="aul-dialogo-txt" id="aul-dlg-txt"></p>
        <div class="aul-dialogo-acciones">
            <button type="button" class="aul-btn aul-btn-ghost" data-no>Volver</button>
            <button type="button" class="aul-btn aul-btn-primary" id="aul-dlg-si"></button>
        </div>
    </div>
</div>

<div class="aul-toast" id="aul-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    @include('estudiante.partials.datos')
    <script src="{{ asset('assets/js/estudiante/aula.js') }}?v={{ filemtime(public_path('assets/js/estudiante/aula.js')) }}"></script>
@endpush
