{{-- Datos del estudiante y su matrícula en los documentos imprimibles (espera $m con estudiante, programa y curso). --}}
@php $e = $m->estudiante; @endphp
<section class="rc-datos">
    <div><span>Estudiante</span><strong>{{ $e->name }}</strong></div>
    <div><span>Documento</span><strong>{{ $e->tipo_documento }} {{ ctype_digit((string) $e->numero_documento) ? number_format((int) $e->numero_documento, 0, ',', '.') : $e->numero_documento }}</strong></div>
    <div><span>Programa</span><strong>{{ $m->programa->nombre }}</strong></div>
    <div><span>Grupo</span><strong>{{ $m->curso ? $m->curso->nombre.' · '.$m->curso->codigo.' · '.$m->curso->jornada : '—' }}</strong></div>
    <div><span>Matrícula</span><strong>{{ $m->codigo }}</strong></div>
</section>
