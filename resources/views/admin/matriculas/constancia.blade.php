{{-- Panel Admin › Matrículas › Constancia de matrícula (DocumentoController::constancia), solo de una
     matrícula activa o aplazada. Se expide a solicitud del estudiante; la firma la pone la sede. --}}
@extends('admin.matriculas.documento')

@php
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = fn ($f) => $f ? $f->day.' de '.$meses[$f->month - 1].' de '.$f->year : '—';
    $e   = $m->estudiante;
    $a   = $e->genero === 'femenino' ? 'a' : 'o';
    $doc = ctype_digit((string) $e->numero_documento) ? number_format((int) $e->numero_documento, 0, ',', '.') : $e->numero_documento;
    $tipoDoc = mb_strtolower(\App\Http\Requests\Admin\Estudiantes\EstudianteRequest::TIPOS_DOCUMENTO[$e->tipo_documento] ?? $e->tipo_documento);
@endphp

@section('title', 'Constancia de matrícula '.$m->codigo)

@section('numero')
    <span>Constancia de matrícula</span>
    <strong>{{ $m->codigo }}</strong>
    <span>Expedida el {{ $fecha(today()) }}</span>
@endsection

@section('contenido')
    <section class="rc-texto">
        <p class="rc-texto-titulo">Hace constar que</p>
        <p>
            <strong>{{ $e->name }}</strong>, identificad{{ $a }} con {{ $tipoDoc }}
            n.º <strong>{{ $doc }}</strong>,
            @if ($m->estado === 'aplazado')
                tiene matrícula vigente en el programa <strong>{{ $m->programa->nombre }}</strong>, la cual se encuentra
                <strong>aplazada</strong> desde el {{ $fecha($m->estado_fecha) }}.
            @else
                se encuentra matriculad{{ $a }} en el programa <strong>{{ $m->programa->nombre }}</strong>,
                @if ($m->curso)
                    {{ $m->curso->nombre }} ({{ $m->curso->codigo }}), jornada {{ mb_strtolower($m->curso->jornada) }},
                    con inicio de clases el {{ $fecha($m->curso->fecha_inicio) }} y finalización prevista el {{ $fecha($m->curso->fecha_fin) }}.
                @endif
            @endif
        </p>
        <p>Código de matrícula {{ $m->codigo }}, registrada el {{ $fecha($m->fecha_inicio) }}@if ($m->sede) en la sede {{ $m->sede->nombre }}@endif.</p>
        <p>La presente se expide a solicitud de la persona interesada el {{ $fecha(today()) }}.</p>
    </section>

    <footer class="rc-pie">
        <div class="rc-firma"><span></span>Firma y sello · Administración de la sede</div>
        <p>Documento válido con firma y sello de la institución.</p>
    </footer>
@endsection
