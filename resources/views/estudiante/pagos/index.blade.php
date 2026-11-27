{{-- Estudiante › Mis pagos (Estudiante/Pagos/PagoController): cobros y recibos reales de su matrícula (PagoService). Solo
     consulta (se paga en la sede), salvo elegir el plan de pago (mensual o por periodo) una sola vez; si no lo elige, la
     Secretaría lo puede elegir desde Matrículas. Al elegirlo tarde, las cuotas cuentan desde el inicio de clases (24-11-
     2026). "A pagar este mes" = vencido + lo que vence este mes. Los recibos se imprimen aquí mismo (documento.js). --}}
@extends('estudiante.layout')

@section('title', 'Mis pagos')
@section('active', 'pagos')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/pagos.css') }}?v={{ filemtime(public_path('assets/css/estudiante/pagos.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/estudiante/documento.css') }}?v={{ filemtime(public_path('assets/css/estudiante/documento.css')) }}">
@endpush

@section('content')
<div class="pag-wrap app-anim" id="pag-app">

    <header class="pag-header">
        <h2 class="pag-title">Mis pagos</h2>
        <p class="pag-sub">Tu plan, tus cuotas y tus recibos. Los pagos se hacen en la administración de tu sede: aquí ves lo que debes y lo que has pagado.</p>
    </header>

    <div class="pag-vacio" id="pag-vacio" hidden>
        <p class="pag-vacio-ttl">No tienes una matrícula vigente</p>
        <p class="pag-vacio-sub">Cuando el administrativo de tu sede te matricule en una carrera, aquí verás tus cobros y tus recibos.</p>
    </div>

    {{-- Elegir el plan (solo si todavía no lo eligió) --}}
    <section class="pag-card pag-plan app-anim app-anim-d1" id="pag-plan" aria-labelledby="pag-plan-titulo" hidden>
        <div class="pag-plan-head">
            <h3 class="pag-card-title" id="pag-plan-titulo">Elige tu plan de pago</h3>
            <p class="pag-card-sub">Cómo pagas el valor de tu carrera. Se elige una sola vez: después no se puede cambiar.</p>
        </div>
        <fieldset class="pag-opciones" id="pag-opciones">
            <legend class="sr-solo">Plan de pago</legend>
        </fieldset>
        <div id="pag-plan-aviso"></div>
        <div class="pag-plan-pie">
            <button type="button" class="pag-btn pag-btn-primary" id="pag-elegir" disabled>Elegir este plan</button>
        </div>
    </section>

    {{-- Estado de cuenta --}}
    <section class="pag-card pag-cuenta app-anim app-anim-d1" id="pag-cuenta" aria-label="Estado de cuenta"></section>

    <div class="pag-toolbar app-anim app-anim-d2" id="pag-toolbar">
        <div class="pag-vistas" role="tablist" aria-label="Mis pagos">
            <button type="button" class="pag-vista-btn is-active" role="tab" id="pag-tab-cuotas" aria-selected="true" aria-controls="pag-panel-cuotas" data-vista="cuotas">Cobros</button>
            <button type="button" class="pag-vista-btn" role="tab" id="pag-tab-recibos" aria-selected="false" aria-controls="pag-panel-recibos" data-vista="recibos" tabindex="-1">
                Recibos <span class="pag-vista-num" id="pag-num-recibos"></span>
            </button>
        </div>
    </div>

    <section class="pag-panel app-anim app-anim-d2" id="pag-panel-cuotas" role="tabpanel" aria-labelledby="pag-tab-cuotas">
        <div class="pag-tabla-wrap">
            <table class="pag-tabla">
                <thead><tr><th scope="col">Concepto</th><th scope="col">Vence</th><th scope="col" class="pag-num">Valor</th><th scope="col" class="pag-num">Pagado</th><th scope="col">Estado</th></tr></thead>
                <tbody id="pag-cobros"></tbody>
            </table>
        </div>
    </section>

    <section class="pag-panel app-anim app-anim-d2" id="pag-panel-recibos" role="tabpanel" aria-labelledby="pag-tab-recibos" hidden>
        <ul class="pag-recibos" id="pag-recibos"></ul>
    </section>

    <p class="pag-pie">Pagas en la administración de tu sede en efectivo, transferencia, Nequi, Daviplata o tarjeta; si no es en efectivo, lleva o envía el comprobante para que registren tu pago. ¿Necesitas una constancia de matrícula o el paz y salvo? Pídelos en la administración.</p>
</div>

{{-- Confirmación del plan --}}
<div class="pag-dialogo" id="pag-dialogo" hidden>
    <div class="pag-dialogo-fondo" data-no></div>
    <div class="pag-dialogo-caja" role="alertdialog" aria-modal="true" aria-labelledby="pag-dlg-titulo" aria-describedby="pag-dlg-txt">
        <h3 class="pag-dialogo-titulo" id="pag-dlg-titulo"></h3>
        <p class="pag-dialogo-txt" id="pag-dlg-txt"></p>
        <div class="pag-dialogo-acciones">
            <button type="button" class="pag-btn pag-btn-ghost" data-no>Volver</button>
            <button type="button" class="pag-btn pag-btn-primary" id="pag-dlg-si">Sí, elegir</button>
        </div>
    </div>
</div>

<div class="pag-toast" id="pag-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    @include('estudiante.partials.datos')
    <script src="{{ asset('assets/js/estudiante/documento.js') }}?v={{ filemtime(public_path('assets/js/estudiante/documento.js')) }}"></script>
    <script src="{{ asset('assets/js/estudiante/pagos.js') }}?v={{ filemtime(public_path('assets/js/estudiante/pagos.js')) }}"></script>
@endpush
