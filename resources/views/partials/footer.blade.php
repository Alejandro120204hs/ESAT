{{-- Pie del sitio público. Sedes, contacto y redes salen de config/sitio.php.
     Los datos legales son reales y van exactos. --}}
<footer class="pie">
    <div class="pie-cierre" id="contacto">
        <img src="{{ asset('assets/img/favicon.png') }}" class="pie-marca-agua" alt="" aria-hidden="true" width="460" height="460">
        <div class="section-inner">
            {{-- Una página puede cambiar este cierre con @section('cierre_titulo'),
                 'cierre_texto', 'cierre_tema' (tema del WhatsApp) y 'cierre_boton'. --}}
            <div data-revelar>
                @hasSection('cierre_titulo')
                    <h2>@yield('cierre_titulo')</h2>
                    <p>@yield('cierre_texto')</p>
                @else
                    <h2>Tu formación técnica empieza con <span class="resalte">una conversación</span></h2>
                    <p>Escríbenos y un asesor te ayuda a elegir el programa y la sede que mejor te queda.</p>
                @endif
            </div>
            <div class="pie-cierre-acciones" data-revelar>
                <a href="{{ $wa(html_entity_decode(trim($__env->yieldContent('cierre_tema', 'información general sobre ESAT')), ENT_QUOTES)) }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg">
                    <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                    @yield('cierre_boton', 'Habla con un asesor')
                </a>
                <a href="{{ route('home') }}#escuelas" class="btn btn-outline-light btn-lg">
                    Ver programas
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                </a>
            </div>
        </div>
    </div>

    <div class="section-inner pie-grid">
        <div class="pie-marca">
            <a href="{{ route('home') }}" aria-label="ESAT — Inicio">
                <img src="{{ asset('assets/img/logo-negativo.png') }}" alt="ESAT — Educación, Salud, Arte y Turismo" width="200" height="72">
            </a>
            <p class="pie-legal">
                Escuela Nacional de Educación ESAT · Lic. de Funcionamiento 5850 del 23 de septiembre de 2019, Secretaría de Educación de Cundinamarca.<br>
                NIT 900820764-1 · Decreto 4904 de 2009, Ministerio de Educación Nacional.
            </p>
            @if (count($sitio['redes']))
                <ul class="pie-redes">
                    @foreach ($sitio['redes'] as $red)
                        <li><a href="{{ $red['url'] }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($red['red']) }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-{{ ['facebook' => 'fb', 'instagram' => 'ig', 'x' => 'x', 'youtube' => 'yt'][$red['red']] ?? 'arrow' }}"/></svg>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <nav class="pie-col" aria-label="Enlaces del sitio">
            <h3>Explora</h3>
            <ul>
                <li><a href="{{ route('home') }}#escuelas">Programas técnicos</a></li>
                <li><a href="{{ route('home') }}#areas-formacion">Cursos obligatorios en salud</a></li>
                <li><a href="{{ route('home') }}#nuevas-carreras">Nuevas carreras</a></li>
                <li><a href="{{ route('home') }}#sedes">Sedes</a></li>
                <li><a href="{{ route('home') }}#convocatorias">Convocatorias</a></li>
                <li><a href="#contacto">Contacto</a></li>
            </ul>
        </nav>

        <div class="pie-col">
            <h3>Sedes</h3>
            <ul class="pie-sedes">
                @foreach ($sitio['sedes'] as $sede)
                    <li class="pie-sede">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-pin"/></svg>
                        <strong>{{ $sede['nombre'] }}</strong>
                        <span>{{ $sede['direccion'] }}</span>
                    </li>
                @endforeach
            </ul>
            @if (! empty($sitio['franquicias']))
                {{-- Cada municipio con espacios que no se parten ("San Francisco" no se corta entre líneas) --}}
                <p class="pie-franquicias"><strong>Sedes de franquicia</strong> {{ implode(' · ', array_map(fn ($m) => str_replace(' ', "\u{00A0}", $m), $sitio['franquicias'])) }}</p>
            @endif
            {{-- En el inicio el mapa grande está en la sección Sedes. --}}
            @unless (request()->routeIs('home'))
            <div class="footer-sedes-map-wrap">
                <div id="footerSedesMap" class="footer-sedes-map" data-points="{{ json_encode(array_map(fn ($s) => ['name' => $s['nombre'], 'address' => $s['direccion'], 'lat' => $s['lat'], 'lng' => $s['lng']], $sitio['sedes'])) }}" aria-hidden="true"></div>
                <div class="footer-sedes-scrim" aria-hidden="true"></div>
            </div>
            @endunless
        </div>

        <div class="pie-col">
            <h3>Contacto</h3>
            <ul>
                @foreach ($sitio['whatsapp'] as [$numero, $visible])
                    <li><a href="{{ $wa('información general sobre ESAT', $numero) }}" target="_blank" rel="noopener">
                        <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                        {{ $visible }}
                    </a></li>
                @endforeach
                <li><a href="{{ $wa('consultar mi certificado') }}" target="_blank" rel="noopener">¿Eres egresado? Verifica tu certificado</a></li>
                <li><a href="{{ route('login') }}">Iniciar sesión en la plataforma</a></li>
            </ul>
        </div>
    </div>

    <div class="pie-base">
        <div class="section-inner">
            <p>&copy; {{ date('Y') }} Escuela Nacional de Educación ESAT. Todos los derechos reservados.</p>
            <p>NIT 900820764-1</p>
        </div>
    </div>
</footer>

<a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener" class="fab-whatsapp" aria-label="Escríbenos por WhatsApp">
    <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
    <span>Escríbenos</span>
</a>
