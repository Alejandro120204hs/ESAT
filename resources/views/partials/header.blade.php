{{-- Cabecera del sitio público. Escuelas, programas y Educación Continuada
     salen de config/sitio.php ($sitio), igual que el footer y las páginas. --}}
@php
    [$waNumero, $waVisible] = $sitio['whatsapp'][0];
@endphp
<header class="cabecera" id="cabecera">
    <div class="cabecera-util">
        <div class="section-inner">
            <p class="cabecera-util-legal">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-shield"/></svg>
                Licencia de Funcionamiento 5850 · Secretaría de Educación de Cundinamarca
            </p>
            <div class="cabecera-util-acciones">
                <a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener">
                    <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                    {{ $waVisible }}
                </a>
                <a href="{{ $wa('consultar mi certificado') }}" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-cert"/></svg>
                    Mi certificado
                </a>
                <a href="{{ route('login') }}" class="util-ingreso">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-user"/></svg>
                    Iniciar sesión
                </a>
            </div>
        </div>
    </div>

    <div class="cabecera-barra">
        <div class="section-inner">
            <a href="{{ route('home') }}" class="marca" aria-label="ESAT — Inicio">
                <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT — Educación, Salud, Arte y Turismo" width="190" height="52">
            </a>

            <nav class="nav-principal" aria-label="Navegación principal">
                <ul>
                    <li><a href="{{ route('home') }}" class="nav-link" @if (request()->routeIs('home')) aria-current="page" @endif>Inicio</a></li>
                    <li>
                        <button type="button" class="nav-link" id="megaTrigger" aria-expanded="false" aria-controls="megamenu">
                            Programas Técnicos
                            <svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-chev"/></svg>
                        </button>
                    </li>
                    <li><a href="{{ route('home') }}#areas-formacion" class="nav-link">Cursos Obligatorios en Salud</a></li>
                    <li class="has-desplegable">
                        <button type="button" class="nav-link" aria-expanded="false" aria-controls="desplegable-continuada">
                            Educación Continuada
                            <svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-chev"/></svg>
                        </button>
                        <div class="desplegable" id="desplegable-continuada">
                            @foreach ($sitio['continuada'] as [$nombre, $slug])
                                <a href="{{ route('continuada.' . $slug) }}" @if (request()->routeIs('continuada.' . $slug)) aria-current="page" @endif>
                                    {{ $nombre }}
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </li>
                    <li><a href="#contacto" class="nav-link">Contacto</a></li>
                </ul>
            </nav>

            <div class="cabecera-acciones">
                <a href="{{ route('home') }}#escuelas" class="btn btn-accent btn-sm cab-cta">
                    Ver programas
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                </a>
                <a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener" class="btn-icono es-whatsapp" aria-label="Escríbenos por WhatsApp">
                    <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                </a>
                <button type="button" class="btn-icono" id="menuMovilAbrir" aria-expanded="false" aria-controls="menuMovil" aria-label="Abrir menú">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-menu"/></svg>
                </button>
            </div>
        </div>
        <span class="cabecera-progreso" id="cabeceraProgreso" aria-hidden="true"></span>

        <div class="megamenu" id="megamenu">
            <div class="megamenu-panel">
                <div class="megamenu-grid">
                    @foreach ($sitio['escuelas'] as $escuela)
                        <div class="megamenu-escuela {{ count($escuela['programas']) > 6 ? 'es-ancha' : '' }}">
                            <div class="megamenu-cabeza">
                                <span class="megamenu-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $escuela['icono'] }}"/></svg></span>
                                <div>
                                    <h3><a href="{{ route('escuelas.' . $escuela['slug']) }}">{{ $escuela['nombre'] }}</a></h3>
                                    <span>{{ count($escuela['programas']) }} {{ count($escuela['programas']) === 1 ? 'programa' : 'programas' }}</span>
                                </div>
                            </div>
                            <ul>
                                @foreach ($escuela['programas'] as [$nombre, $slug])
                                    <li><a href="{{ route('programas.' . $slug) }}">{{ $nombre }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <div class="megamenu-pie">
                    <p><strong>¿No encuentras tu programa?</strong> Escríbenos y te asesoramos sin costo.</p>
                    <a href="{{ $wa('información general sobre programas técnicos') }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                        <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                        Hablar con un asesor
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Menú móvil: hoja lateral con los mismos destinos que la cabecera. --}}
<div class="menu-movil-fondo" id="menuMovilFondo"></div>
<div class="menu-movil" id="menuMovil" role="dialog" aria-modal="true" aria-label="Menú del sitio">
    <div class="menu-movil-cabeza">
        <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT" width="160" height="44">
        <button type="button" class="btn-icono" id="menuMovilCerrar" aria-label="Cerrar menú">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-close"/></svg>
        </button>
    </div>
    <div class="menu-movil-cuerpo" data-lenis-prevent>
        <a href="{{ route('home') }}" class="menu-movil-enlace">Inicio</a>

        <div class="menu-movil-acordeon">
            <button type="button" aria-expanded="false">
                Programas Técnicos
                <svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-chev"/></svg>
            </button>
            <div class="menu-movil-panel"><div>
                @foreach ($sitio['escuelas'] as $escuela)
                    <div class="menu-movil-escuela">
                        <h3><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $escuela['icono'] }}"/></svg><a href="{{ route('escuelas.' . $escuela['slug']) }}">{{ $escuela['nombre'] }}</a></h3>
                        <ul>
                            @foreach ($escuela['programas'] as [$nombre, $slug])
                                <li><a href="{{ route('programas.' . $slug) }}">{{ $nombre }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div></div>
        </div>

        <a href="{{ route('home') }}#areas-formacion" class="menu-movil-enlace">Cursos Obligatorios en Salud</a>

        <div class="menu-movil-acordeon">
            <button type="button" aria-expanded="false">
                Educación Continuada
                <svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-chev"/></svg>
            </button>
            <div class="menu-movil-panel"><div>
                <ul class="menu-movil-lista">
                    @foreach ($sitio['continuada'] as [$nombre, $slug])
                        <li><a href="{{ route('continuada.' . $slug) }}">{{ $nombre }}</a></li>
                    @endforeach
                </ul>
            </div></div>
        </div>

        <a href="#contacto" class="menu-movil-enlace">Contacto</a>
        <a href="{{ $wa('consultar mi certificado') }}" target="_blank" rel="noopener" class="menu-movil-enlace">Mi certificado</a>
    </div>
    <div class="menu-movil-pie">
        <a href="{{ route('home') }}#escuelas" class="btn btn-accent">Ver programas técnicos</a>
        <div class="menu-movil-pie-fila">
            <a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener" class="btn btn-whatsapp">
                <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                WhatsApp
            </a>
            <a href="{{ route('login') }}" class="btn btn-outline">Iniciar sesión</a>
        </div>
    </div>
</div>
