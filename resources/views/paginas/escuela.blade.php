@extends('layouts.app')

{{-- Página de una escuela: sus programas con la foto, el lema y la entrada de
     cada uno (los mismos textos de las páginas de programa, en
     config/contenido.php). Escuela y orden de programas: config/sitio.php. --}}

@php
    $n = count($programas);
    $otrasEscuelas = array_values(array_filter($sitio['escuelas'], fn ($e) => $e['slug'] !== $escuela['slug']));
@endphp

@section('title', $escuela['nombre'] . ' · ESAT — Formación Técnica Certificada')
@section('meta_description', $escuela['nombre'] . ' en ESAT: ' . implode(', ', array_column($programas, 'nombre')) . '. Sedes en Bogotá, Sasaima, Guaduas y La Dorada.')

@push('styles')
    <link rel="stylesheet" href="{{ $recurso('assets/css/sitio/subpaginas.css') }}">
@endpush
@push('scripts')
    <script src="{{ $recurso('assets/js/sitio/subpaginas.js') }}" defer></script>
@endpush

@section('content')
    @include('paginas.partes.migas', ['migas' => [
        ['Inicio', route('home')],
        ['Escuelas', route('home') . '#escuelas'],
        [$escuela['nombre'], null],
    ]])

    @include('paginas.partes.hero', ['hero' => [
        'nombre'     => $escuela['nombre'],
        'lema'       => $n . ' ' . ($n === 1 ? 'programa técnico laboral' : 'programas técnicos laborales'),
        'intro'      => 'Conoce cada programa y escríbenos para inscribirte: te contamos fechas, sedes y requisitos.',
        'foto'       => $escuela['foto'] . '.webp',
        'icono'      => $escuela['icono'],
        'tema_wa'    => $escuela['nombre'],
        'secundario' => ['Ver los programas', '#programas'],
    ]])

    {{-- ============ PROGRAMAS DE LA ESCUELA ============ --}}
    <section class="sub-escuela-programas" id="programas">
        <div class="section-inner">
            {{-- Con foto: fila de dos columnas que alternan lado. Sin foto todavía:
                 fila solo de texto, con el ícono de la escuela al lado del nombre. --}}
            @php $conFoto = 0; @endphp
            @foreach ($programas as $p)
                @php $fp = $foto($p['foto_portada']); if ($fp) { $conFoto++; } @endphp
                <article class="sub-fila {{ $fp ? ($conFoto % 2 === 0 ? 'es-invertida' : '') : 'sin-foto' }}">
                    @if ($fp)
                        <a href="{{ route('programas.' . $p['slug']) }}" class="sub-fila-foto" data-revelar="mascara" tabindex="-1" aria-hidden="true">
                            <img src="{{ $fp['src'] }}" @if ($fp['srcset']) srcset="{{ $fp['srcset'] }}" sizes="(max-width: 900px) 100vw, 50vw" @endif alt="" loading="lazy" decoding="async" data-parallax="0.06">
                        </a>
                    @endif
                    <div class="sub-fila-texto" data-revelar>
                        <h2>
                            @unless ($fp)
                                <span class="sub-fila-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $escuela['icono'] }}"/></svg></span>
                            @endunless
                            <a href="{{ route('programas.' . $p['slug']) }}">{{ $p['nombre'] }}</a>
                        </h2>
                        <p class="sub-fila-lema">{{ $p['lema'] }}</p>
                        <p>{{ $p['intro'] }}</p>
                        <ul class="sub-fila-claves">
                            @foreach ($p['competencias'] as [, $titulo])
                                <li>{{ $titulo }}</li>
                            @endforeach
                        </ul>
                        <div class="sub-fila-acciones">
                            <a href="{{ route('programas.' . $p['slug']) }}" class="btn btn-navy">
                                Ver el programa
                                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                            </a>
                            <a href="{{ $wa($p['tema_wa']) }}" target="_blank" rel="noopener" class="btn btn-outline">
                                <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                                Preguntar
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    @include('paginas.partes.respaldo')

    {{-- ============ OTRAS ESCUELAS ============ --}}
    <section class="sub-otras-lineas">
        <div class="section-inner">
            <h2 data-revelar>Otras escuelas de ESAT</h2>
            <ul class="sub-chips" data-revelar="escalonar">
                @foreach ($otrasEscuelas as $otra)
                    <li><a href="{{ route('escuelas.' . $otra['slug']) }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $otra['icono'] }}"/></svg>
                        {{ $otra['nombre'] }}
                    </a></li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
