@extends('layouts.app')

{{-- Página de una línea de Educación Continuada. El contenido sale de
     config/contenido.php (continuada.{slug}); aquí solo va la forma. --}}

@section('title', $linea['titulo_seo'])
@section('meta_description', $linea['descripcion_seo'])

@section('cierre_titulo', $linea['cierre_titulo'])
@section('cierre_texto', $linea['cierre_texto'])
@section('cierre_tema', $linea['tema_wa'])
@section('cierre_boton', 'Quiero inscribirme')

@push('styles')
    <link rel="stylesheet" href="{{ $recurso('assets/css/sitio/subpaginas.css') }}">
@endpush
@push('scripts')
    <script src="{{ $recurso('assets/js/sitio/subpaginas.js') }}" defer></script>
@endpush

@php
    $otras = array_values(array_filter($sitio['continuada'], fn ($c) => $c[1] !== $slug));
@endphp

@section('content')
    @include('paginas.partes.migas', ['migas' => [
        ['Inicio', route('home')],
        ['Educación Continuada', route('home') . '#areas-formacion'],
        [$linea['nombre'], null],
    ]])

    @include('paginas.partes.hero', ['hero' => $linea + ($linea['cursos'] ? ['secundario' => ['Ver diplomados y cursos', '#cursos']] : [])])

    @include('paginas.partes.barra', ['antes' => 'Educación Continuada', 'nombre' => $linea['nombre'], 'tema' => $linea['tema_wa']])

    {{-- ============ DIPLOMADOS Y CURSOS ============ --}}
    @if ($linea['cursos'])
        <section class="sub-cursos" id="cursos">
            <div class="section-inner sub-formacion-grid">
                <div class="sub-formacion-cabeza" data-revelar>
                    <h2>{{ $linea['cursos_encabezado'][0] }}</h2>
                    <p>{{ $linea['cursos_encabezado'][1] }}</p>
                    <p class="sub-cursos-total">{{ count($linea['cursos']) }} {{ count($linea['cursos']) === 1 ? 'opción' : 'opciones' }}</p>
                </div>
                <ul class="sub-cursos-lista" data-revelar="escalonar">
                    @foreach ($linea['cursos'] as $curso)
                        <li>
                            <span class="sub-curso-nodo"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-cert"/></svg></span>
                            <span class="sub-curso-nombre">{{ $curso }}</span>
                            <a href="{{ $wa('Educación Continuada — ' . $curso) }}" target="_blank" rel="noopener" class="sub-curso-enlace" aria-label="Preguntar por {{ $curso }} en WhatsApp">
                                <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @include('paginas.partes.respaldo')

    {{-- ============ OTRAS LÍNEAS DE EDUCACIÓN CONTINUADA ============ --}}
    <section class="sub-otras-lineas">
        <div class="section-inner">
            <h2 data-revelar>Otras líneas de Educación Continuada</h2>
            <ul class="sub-chips" data-revelar="escalonar">
                @foreach ($otras as [$nombre, $otra])
                    <li><a href="{{ route('continuada.' . $otra) }}">{{ $nombre }} <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg></a></li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
