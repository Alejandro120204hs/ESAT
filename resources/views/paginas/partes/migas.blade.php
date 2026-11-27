{{-- Ruta de navegación. $migas = [[texto, url], ...]; la última es la página actual. --}}
<nav class="migas" aria-label="Estás en">
    <ol class="section-inner">
        @foreach ($migas as [$texto, $url])
            @if ($loop->last)
                <li aria-current="page">{{ $texto }}</li>
            @else
                <li><a href="{{ $url }}">{{ $texto }}</a><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-chev"/></svg></li>
            @endif
        @endforeach
    </ol>
</nav>
