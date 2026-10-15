{{-- Panel Admin › Matrículas › Paz y salvo (DocumentoController::pazYSalvo): solo se expide si la persona
     no debe nada en ninguna de sus matrículas de la sede. Lista las matrículas que cubre. --}}
@extends('admin.matriculas.documento')

@php
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = fn ($f) => $f ? $f->day.' de '.$meses[$f->month - 1].' de '.$f->year : '—';
    $e   = $m->estudiante;
    $a   = $e->genero === 'femenino' ? 'a' : 'o';
    $doc = ctype_digit((string) $e->numero_documento) ? number_format((int) $e->numero_documento, 0, ',', '.') : $e->numero_documento;
    $tipoDoc = mb_strtolower(\App\Http\Requests\Admin\Estudiantes\EstudianteRequest::TIPOS_DOCUMENTO[$e->tipo_documento] ?? $e->tipo_documento);
    $estados = ['activo' => 'Activa', 'aplazado' => 'Aplazada', 'retirado' => 'Retirada', 'graduado' => 'Graduada'];
@endphp

@section('title', 'Paz y salvo '.$e->name)

@section('numero')
    <span>Paz y salvo</span>
    <strong>{{ $doc }}</strong>
    <span>Expedido el {{ $fecha(today()) }}</span>
@endsection

@section('contenido')
    <section class="rc-texto">
        <p class="rc-texto-titulo">Hace constar que</p>
        <p>
            <strong>{{ $e->name }}</strong>, identificad{{ $a }} con {{ $tipoDoc }}
            n.º <strong>{{ $doc }}</strong>, se encuentra <strong>a paz y salvo</strong> por concepto de derechos de
            matrícula y cuotas de los programas en los que ha estado matriculad{{ $a }} en esta institución{{ $m->sede ? ', sede '.$m->sede->nombre : '' }},
            a la fecha de expedición de este documento.
        </p>
    </section>

    <table class="rc-tabla">
        <thead>
            <tr><th>Programa</th><th class="rc-col-extra">Matrícula</th><th class="rc-col-extra">Desde</th><th class="rc-num">Estado</th></tr>
        </thead>
        <tbody>
            @foreach ($matriculas as $x)
                <tr>
                    <td><strong>{{ $x->programa->nombre }}</strong></td>
                    <td class="rc-col-extra">{{ $x->codigo }}</td>
                    <td class="rc-col-extra">{{ $x->fecha_inicio->format('d/m/Y') }}</td>
                    <td class="rc-num">{{ $estados[$x->estado] ?? $x->estado }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="rc-texto">
        <p>La presente se expide a solicitud de la persona interesada el {{ $fecha(today()) }}.</p>
    </section>

    <footer class="rc-pie">
        <div class="rc-firma"><span></span>Firma y sello · Administración de la sede</div>
        <p>Documento válido con firma y sello de la institución.</p>
    </footer>
@endsection
