@extends('layouts.app')

{{-- Página de un programa técnico. El contenido sale de config/contenido.php
     (programas.{slug}) y la escuela de config/sitio.php: aquí solo va la forma. --}}

@section('title', $programa['titulo_seo'])
@section('meta_description', $programa['descripcion_seo'])

{{-- El cierre del footer se vuelve la invitación a inscribirse en este programa. --}}
@section('cierre_titulo', $programa['cierre_titulo'])
@section('cierre_texto', $programa['cierre_texto'])
@section('cierre_tema', $programa['tema_wa'])
@section('cierre_boton', 'Quiero inscribirme')

@push('styles')
    <link rel="stylesheet" href="{{ $recurso('assets/css/sitio/subpaginas.css') }}">
@endpush
@push('scripts')
    <script src="{{ $recurso('assets/js/sitio/subpaginas.js') }}" defer></script>
@endpush

@php
    [$practicaFoto, $practicaTitulo, $practicaTexto] = $programa['practica'];
    $fotoPractica = $foto($practicaFoto);
@endphp

@section('content')
    @include('paginas.partes.migas', ['migas' => [
        ['Inicio', route('home')],
        [$escuela['nombre'], route('escuelas.' . $escuela['slug'])],
        [$programa['nombre'], null],
    ]])

    @include('paginas.partes.hero', ['hero' => ['foto' => $programa['foto_portada']] + $programa + ['secundario' => ['Ver el programa', '#formacion']]])

    @include('paginas.partes.barra', ['antes' => $escuela['nombre'], 'nombre' => $programa['nombre'], 'tema' => $programa['tema_wa']])

    {{-- ============ FORMACIÓN PRÁCTICA ============ --}}
    <section class="sub-formacion" id="formacion">
        <div class="section-inner sub-formacion-grid">
            <div class="sub-formacion-cabeza" data-revelar>
                <h2>{{ $programa['formacion'][0] }}</h2>
                <p>{{ $programa['formacion'][1] }}</p>
            </div>
            <ul class="sub-competencias" data-revelar="escalonar">
                @foreach ($programa['competencias'] as [$icono, $titulo, $texto])
                    <li>
                        <span class="sub-competencia-nodo"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $icono }}"/></svg></span>
                        <div>
                            <h3>{{ $titulo }}</h3>
                            <p>{{ $texto }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ============ PROYECCIÓN ============ --}}
    <section class="sub-proyeccion">
        <div class="section-inner">
            <div class="section-head es-dividida" data-revelar>
                <h2>{{ $programa['proyeccion'][0] }}</h2>
                <p>{{ $programa['proyeccion'][1] }}</p>
            </div>
            <div class="sub-salidas">
                @foreach ($programa['salidas'] as [$titulo, $texto, $icono, $acento, $archivo])
                    @php $f = $foto($archivo); @endphp
                    <article class="sub-salida acento-{{ $acento }} {{ $f ? '' : 'sin-foto' }}" data-revelar="mascara" data-retraso="{{ $loop->index * 0.1 }}">
                        @if ($f)
                            <img src="{{ $f['src'] }}" @if ($f['srcset']) srcset="{{ $f['srcset'] }}" sizes="(max-width: 900px) 100vw, 33vw" @endif alt="" loading="lazy" decoding="async">
                        @endif
                        <div class="sub-salida-velo" aria-hidden="true"></div>
                        <div class="sub-salida-cuerpo">
                            <span class="sub-salida-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $icono }}"/></svg></span>
                            <h3>{{ $titulo }}</h3>
                            <p>{{ $texto }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ PRÁCTICA ============
         La foto se abre con el scroll, de un marco angosto a todo el ancho. --}}
    <section class="sub-practica">
        <div class="sub-practica-marco {{ $fotoPractica ? '' : 'sin-foto' }}" id="practicaMarco">
            @if ($fotoPractica)
                <img src="{{ $fotoPractica['src'] }}" @if ($fotoPractica['srcset']) srcset="{{ $fotoPractica['srcset'] }}" sizes="100vw" @endif alt="" loading="lazy" decoding="async">
            @endif
            <div class="sub-practica-velo" aria-hidden="true"></div>
            <div class="section-inner sub-practica-texto" data-revelar>
                <h2>{{ $practicaTitulo }}</h2>
                <p>{{ $practicaTexto }}</p>
            </div>
        </div>
    </section>

    @include('paginas.partes.respaldo')

    {{-- ============ MÁS PROGRAMAS DE LA ESCUELA ============ --}}
    @if (count($hermanos))
        <section class="sub-otros">
            <div class="section-inner">
                <div class="sub-otros-cabeza" data-revelar>
                    <h2>Más programas de la {{ $escuela['nombre'] }}</h2>
                    <a href="{{ route('escuelas.' . $escuela['slug']) }}" class="btn btn-outline">
                        Ver la escuela
                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                    </a>
                </div>
                <ul class="sub-otros-lista" data-revelar="escalonar">
                    @foreach ($hermanos as $o)
                        @php $fo = $foto($o['foto_portada']); @endphp
                        <li>
                            <a href="{{ route('programas.' . $o['slug']) }}" class="sub-otro">
                                <span class="sub-otro-foto">
                                    @if ($fo)
                                        <img src="{{ $fo['src'] }}" @if ($fo['srcset']) srcset="{{ $fo['srcset'] }}" sizes="160px" @endif alt="" loading="lazy" decoding="async">
                                    @else
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $escuela['icono'] }}"/></svg>
                                    @endif
                                </span>
                                <span class="sub-otro-texto">
                                    <strong>{{ $o['nombre'] }}</strong>
                                    <span>{{ $o['lema'] }}</span>
                                </span>
                                <svg class="sub-otro-flecha" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
