{{-- Estudiante › Mi programa (Estudiante/Programa/ProgramaController): la malla, las notas y la asistencia reales de su
     matrícula (PanelEstudianteService::datos). Solo consulta: las notas y la asistencia las pone el docente (mismas reglas
     de CalificacionService y AsistenciaService). El boletín se imprime aquí mismo (documento.js) con los datos legales
     reales de ESAT. Enlaces: ?vista=asistencia abre esa pestaña. Componentes copiados del Docente: malla de Mis grupos
     y calendario de Asistencia (DESIGN-PANELES.md). --}}
@extends('estudiante.layout')

@section('title', 'Mi programa')
@section('active', 'programa')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/programa.css') }}?v={{ filemtime(public_path('assets/css/estudiante/programa.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/documento.css') }}?v={{ filemtime(public_path('assets/css/estudiante/documento.css')) }}">
@endpush

@section('content')
<div class="mpr-wrap app-anim" id="mpr-app" data-url-aula="{{ route('estudiante.aula') }}">

    <header class="mpr-header">
        <div class="mpr-header-txt">
            <h2 class="mpr-title">Mi programa</h2>
            <p class="mpr-sub" id="mpr-sub"></p>
        </div>
        <button type="button" class="mpr-btn mpr-btn-ghost" id="mpr-imprimir">
            <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimir boletín
        </button>
    </header>

    <div class="mpr-vacio" id="mpr-vacio" hidden>
        <p class="mpr-vacio-ttl">No tienes una matrícula vigente</p>
        <p class="mpr-vacio-sub">Cuando la secretaría de tu sede te matricule en un programa, aquí verás tus notas por módulo y tu asistencia.</p>
    </div>

    <div class="mpr-toolbar app-anim app-anim-d1">
        <div class="mpr-vistas" role="tablist" aria-label="Mi programa">
            <button type="button" class="mpr-vista-btn is-active" role="tab" id="mpr-tab-notas" aria-selected="true" aria-controls="mpr-panel-notas" data-vista="notas">Notas</button>
            <button type="button" class="mpr-vista-btn" role="tab" id="mpr-tab-asistencia" aria-selected="false" aria-controls="mpr-panel-asistencia" data-vista="asistencia" tabindex="-1">Asistencia</button>
        </div>
        <p class="mpr-resumen" id="mpr-resumen" aria-live="polite"></p>
    </div>

    {{-- Notas por módulo --}}
    <section class="mpr-panel app-anim app-anim-d2" id="mpr-panel-notas" role="tabpanel" aria-labelledby="mpr-tab-notas">
        <ol class="mpr-malla" id="mpr-malla"></ol>
        <p class="mpr-pie">Se aprueba con 3,0. Si pierdes un módulo tienes una habilitación, después de que el módulo termina. Las notas las pone tu docente; las de las evaluaciones y actividades del Aula virtual son aparte.</p>
    </section>

    {{-- Asistencia --}}
    <section class="mpr-panel app-anim app-anim-d2" id="mpr-panel-asistencia" role="tabpanel" aria-labelledby="mpr-tab-asistencia" hidden>
        <div class="mpr-card mpr-asis-top" id="mpr-asis-top"></div>
        <div class="mpr-asis-cols">
            <div class="mpr-card mpr-cal">
                <div class="mpr-cal-nav">
                    <button type="button" class="mpr-icon-btn" id="mpr-cal-ant" aria-label="Mes anterior">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <div class="mpr-cal-mes" aria-live="polite"><b id="mpr-cal-mes"></b><small id="mpr-cal-cuenta"></small></div>
                    <button type="button" class="mpr-icon-btn" id="mpr-cal-sig" aria-label="Mes siguiente">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
                <div class="mpr-cal-grid" id="mpr-cal-grid"></div>
                <div class="mpr-leyenda" aria-hidden="true">
                    <span><i class="mpr-ley mpr-ley-P"></i>Presente</span>
                    <span><i class="mpr-ley mpr-ley-T"></i>Tarde</span>
                    <span><i class="mpr-ley mpr-ley-A"></i>Ausente</span>
                    <span><i class="mpr-ley mpr-ley-E"></i>Con excusa</span>
                    <span><i class="mpr-ley mpr-ley-sin"></i>Sin registro</span>
                    <span><i class="mpr-ley mpr-ley-fut"></i>Por venir</span>
                </div>
                <div class="mpr-detalle" id="mpr-detalle" aria-live="polite"></div>
            </div>
            <div class="mpr-card mpr-novedades">
                <h3 class="mpr-card-title">Faltas, llegadas tarde y excusas</h3>
                <p class="mpr-card-sub">De la más reciente a la más antigua.</p>
                <ul class="mpr-nov-lista" id="mpr-nov-lista"></ul>
                <button type="button" class="mpr-mas" id="mpr-nov-mas" aria-expanded="false" hidden></button>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
    @include('estudiante.partials.datos')
    <script src="{{ asset('assets/js/estudiante/documento.js') }}?v={{ filemtime(public_path('assets/js/estudiante/documento.js')) }}"></script>
    <script src="{{ asset('assets/js/estudiante/programa.js') }}?v={{ filemtime(public_path('assets/js/estudiante/programa.js')) }}"></script>
@endpush
