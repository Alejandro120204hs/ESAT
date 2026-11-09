{{-- Portada de una subpágina. Recibe $hero: nombre, lema, intro, foto, foto_clase,
     icono, tema_wa, detalle (texto bajo los botones, opcional) y secundario
     ([texto, url] de un segundo botón, opcional). Sin foto, la portada es un
     campo azul con el texto (nunca un aviso de "foto pendiente"). --}}
@php $fotoHero = $foto($hero['foto'] ?? null); @endphp
<section class="sub-hero {{ $fotoHero ? '' : 'sin-foto' }} {{ mb_strlen($hero['nombre']) > 26 ? 'es-largo' : '' }}" id="portada">
    <div class="sub-hero-marco" data-parallax-marco>
        @if ($fotoHero)
            <img src="{{ $fotoHero['src'] }}" @if ($fotoHero['srcset']) srcset="{{ $fotoHero['srcset'] }}" sizes="(max-width: 1440px) 100vw, 1440px" @endif
                 class="sub-hero-foto {{ ($hero['foto_clase'] ?? null) === 'program-hero-photo-lower' ? 'es-baja' : '' }}" alt="" fetchpriority="high" decoding="async" data-parallax="0.07">
        @endif
        <div class="sub-hero-velo" aria-hidden="true"></div>

        <div class="sub-hero-texto">
            <h1 data-revelar>{{ $hero['nombre'] }}</h1>
            <p class="sub-hero-lema" data-revelar data-retraso="0.08">{{ $hero['lema'] }}</p>
            <p class="sub-hero-intro" data-revelar data-retraso="0.14">{{ $hero['intro'] }}</p>
            <div class="sub-hero-acciones" data-revelar data-retraso="0.22">
                <a href="{{ $wa($hero['tema_wa']) }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                    <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                    Quiero inscribirme
                </a>
                @isset($hero['secundario'])
                    <a href="{{ $hero['secundario'][1] }}" class="btn btn-outline-light btn-lg">
                        {{ $hero['secundario'][0] }}
                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                    </a>
                @endisset
            </div>
        </div>
    </div>
</section>
