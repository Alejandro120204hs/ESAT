{{-- Barra fija de inscripción: aparece cuando la portada sale de la pantalla y se
     esconde al llegar al cierre del footer. Mientras está oculta es inert. --}}
<div class="sub-barra" id="subBarra" inert>
    <div class="section-inner">
        <p><span>{{ $antes }}</span><strong>{{ $nombre }}</strong></p>
        <a href="{{ $wa($tema) }}" target="_blank" rel="noopener" class="btn btn-accent btn-sm">
            <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
            Quiero inscribirme
        </a>
    </div>
</div>
