{{-- Panel Admin › Matrículas › Recibo de un pago (PagoController::recibo), listo para imprimir o
     guardar en PDF. Un recibo anulado se sigue pudiendo consultar, marcado como ANULADO con su motivo. --}}
@extends('admin.matriculas.documento')

@php
    $pesos = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = fn ($f) => $f ? $f->day.' de '.$meses[$f->month - 1].' de '.$f->year : '—';
@endphp

@section('title', 'Recibo '.$recaudo->recibo)
@section('hoja-clase', $recaudo->anulado() ? 'is-anulado' : '')

@section('sello')
    @if ($recaudo->anulado())
        <div class="rc-sello" aria-hidden="true">Anulado</div>
    @endif
@endsection

@section('numero')
    <span>Recibo de pago</span>
    <strong>{{ $recaudo->recibo }}</strong>
    <span>{{ $fecha($recaudo->fecha_pago) }}</span>
@endsection

@section('contenido')
    @if ($recaudo->anulado())
        <div class="rc-aviso">
            <strong>Este recibo fue anulado el {{ $fecha($recaudo->anulado_en) }}@if ($recaudo->anuladoPor) por {{ $recaudo->anuladoPor->name }}@endif.</strong>
            <span>Motivo: {{ $recaudo->motivo_anulacion }}</span>
        </div>
    @endif

    @include('admin.matriculas.partials.documento-estudiante')

    <table class="rc-tabla">
        <thead>
            <tr><th>Concepto</th><th class="rc-col-extra">Vence</th><th class="rc-col-extra">Detalle</th><th class="rc-num">Valor pagado</th></tr>
        </thead>
        <tbody>
            @foreach ($lineas as $l)
                <tr>
                    <td><strong>{{ $l['concepto'] }}</strong></td>
                    <td class="rc-col-extra">{{ $l['vence'] ? $l['vence']->format('d/m/Y') : 'Al reintegrarse' }}</td>
                    <td class="rc-col-extra">{{ $l['abono'] ? 'Abono (valor del cobro '.$pesos($l['valor']).')' : 'Pago completo' }}</td>
                    <td class="rc-num">{{ $pesos($l['monto']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            {{-- Sin colspan: en celular se ocultan las mismas columnas en todas las filas --}}
            <tr><td>Total recibido</td><td class="rc-col-extra"></td><td class="rc-col-extra"></td><td class="rc-num">{{ $pesos($recaudo->valor) }}</td></tr>
        </tfoot>
    </table>

    <section class="rc-datos rc-datos-pago">
        <div><span>Medio de pago</span><strong>{{ $recaudo->metodo_pago }}</strong></div>
        <div><span>Referencia</span><strong>{{ $recaudo->referencia ?: '—' }}</strong></div>
        <div><span>Recibió</span><strong>{{ $recaudo->registradoPor?->name ?? '—' }}</strong></div>
        @if ($recaudo->observacion)
            <div class="rc-full"><span>Observación</span><strong>{{ $recaudo->observacion }}</strong></div>
        @endif
    </section>

    <footer class="rc-pie">
        <div class="rc-firma"><span></span>Firma y sello de quien recibe</div>
        <p>Conserve este recibo como soporte de su pago.</p>
    </footer>
@endsection
