<!-- ============ FOOTER ============ -->
<footer class="site-footer">
    <div class="section-inner footer-grid">
        <div class="footer-brand">
            <a href="{{ route('home') }}#inicio" class="brand brand-footer" aria-label="ESAT — Inicio">
                <img src="{{ asset('assets/img/logo-negativo.png') }}" class="brand-logo" alt="ESAT — Educación, Salud, Arte y Turismo">
            </a>
            <p class="footer-legal">
                Escuela Nacional de Educación ESAT · Lic. de Funcionamiento 5850 del 23 de septiembre de 2019, Secretaría de Educación de Cundinamarca.<br>
                NIT 900820764-1 · Decreto 4904 de 2009, Ministerio de Educación Nacional.
            </p>
            <ul class="social-row">
                <li><a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><use href="#ico-fb"/></svg></a></li>
                <li><a href="#" aria-label="Twitter / X"><svg viewBox="0 0 24 24"><use href="#ico-x"/></svg></a></li>
                <li><a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><use href="#ico-ig"/></svg></a></li>
                <li><a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24"><use href="#ico-yt"/></svg></a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Enlaces rápidos</h4>
            <ul>
                <li><a href="{{ route('home') }}#areas-formacion">Programas técnicos</a></li>
                <li><a href="{{ route('home') }}#nuevas-carreras">Nuevas carreras</a></li>
                <li><a href="{{ route('home') }}#convocatorias">Convocatorias</a></li>
                <li><a href="{{ route('home') }}#contacto">Contacto</a></li>
            </ul>
        </div>

        <div class="footer-col">
            @php
                $sedes = [
                    ['name' => 'Bogotá', 'address' => 'Cra 52 #71-18', 'lat' => 4.6682, 'lng' => -74.0817],
                    ['name' => 'Sasaima', 'address' => 'Urbanización San José, Cra 2 #8-84, Sasaima, Cundinamarca', 'lat' => 5.0128, 'lng' => -74.4361],
                    ['name' => 'Guaduas', 'address' => 'Cra 2 No. 1-83, Calle de la Pola, Cundinamarca', 'lat' => 5.0700, 'lng' => -74.5928],
                    ['name' => 'La Dorada', 'address' => 'Calle 9 No. 7-55, Barrio Magdalena, La Dorada, Caldas', 'lat' => 5.4544, 'lng' => -74.6379],
                ];
            @endphp
            <h4>Sedes</h4>
            <div class="footer-sedes-map-wrap">
                <div id="footerSedesMap" class="footer-sedes-map" data-points="{{ json_encode($sedes) }}" aria-label="Mapa de ubicación de las cuatro sedes de ESAT"></div>
                <div class="footer-sedes-scrim" aria-hidden="true"></div>
            </div>
            <ul class="sr-only">
                @foreach ($sedes as $sede)
                    <li>{{ $sede['name'] }} — {{ $sede['address'] }}</li>
                @endforeach
            </ul>
        </div>

        <div class="footer-col">
            <h4>Contacto</h4>
            <ul>
                <li><a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener">315 295 75 88</a></li>
                <li><a href="{{ $wa('información general sobre ESAT', '573123773144') }}" target="_blank" rel="noopener">312 377 31 44</a></li>
                <li><a href="{{ $wa('consultar mi certificado') }}" target="_blank" rel="noopener">¿Eres egresado? Verifica tu certificado</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="section-inner">
            <p>&copy; {{ date('Y') }} Escuela Nacional de Educación ESAT. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>

<a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener" class="whatsapp-fab" aria-label="Escríbenos por WhatsApp">
    <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
</a>
