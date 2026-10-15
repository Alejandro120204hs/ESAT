{{-- Panel Admin › Matrículas › Comprobante de devolución de dinero adelantado
     (PagoController::comprobanteDevolucion), listo para imprimir y firmar por quien recibe. --}}
@extends('admin.matriculas.documento')

@php
    $pesos = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = fn ($f) => $f ? $f->day.' de '.$meses[$f->month - 1].' de '.$f->year : '—';
@endphp

@section('title', 'Devolución '.$devolucion->comprobante)

@section('numero')
    <span>Comprobante de devolución</span>
    <strong>{{ $devolucion->comprobante }}</strong>
    <span>{{ $fecha($devolucion->fecha) }}</span>
@endsection

@section('contenido')
    @include('admin.matriculas.partials.documento-estudiante')

    <table class="rc-tabla">
        <thead>
            <tr><th>Pagado por adelantado de</th><th class="rc-col-extra">Vencía</th><th class="rc-col-extra"></th><th class="rc-num">Valor devuelto</th></tr>
        </thead>
        <tbody>
            @foreach ($lineas as $l)
                <tr>
                    <td><strong>{{ $l['concepto'] }}</strong></td>
                    <td class="rc-col-extra">{{ $l['vence'] ? $l['vence']->format('d/m/Y') : '—' }}</td>
                    <td class="rc-col-extra"></td>
                    <td class="rc-num">{{ $pesos($l['monto']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td>Total devuelto</td><td class="rc-col-extra"></td><td class="rc-col-extra"></td><td class="rc-num">{{ $pesos($devolucion->valor) }}</td></tr>
        </tfoot>
    </table>

    <section class="rc-datos rc-datos-pago">
        <div><span>Medio de devolución</span><strong>{{ $devolucion->metodo_pago }}</strong></div>
        <div><span>Referencia</span><strong>{{ $devolucion->referencia ?: '—' }}</strong></div>
        <div><span>Registró</span><strong>{{ $devolucion->registradoPor?->name ?? '—' }}</strong></div>
        <div class="rc-full"><span>Motivo</span><strong>{{ $devolucion->motivo }}</strong></div>
    </section>

    <footer class="rc-pie rc-pie-doble">
        <div class="rc-firma"><span></span>Entrega (firma y sello de ESAT)</div>
        <div class="rc-firma"><span></span>Recibe: {{ $m->estudiante->name }}</div>
    </footer>
@endsection
