<!-- ============ HEADER ============ -->
<header class="site-header" id="site-header">
    <div class="header-inner">
        <a href="{{ route('home') }}#inicio" class="brand" aria-label="ESAT — Inicio">
            <img src="{{ asset('assets/img/logo.png') }}" class="brand-logo" alt="ESAT — Educación, Salud, Arte y Turismo">
        </a>

        <nav class="main-nav" aria-label="Navegación principal">
            <ul>
                <li><a href="{{ route('home') }}#inicio">Inicio</a></li>
                <li class="has-mega">
                    <button class="mega-trigger" id="megaTrigger" aria-expanded="false" aria-controls="mega-programas">
                        Programas Técnicos
                        <svg viewBox="0 0 24 24" class="chev"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </li>
                <li><a href="{{ route('home') }}#areas-formacion">Cursos Obligatorios en Salud</a></li>
                <li class="has-dropdown">
                    <button class="dropdown-trigger" id="eduTrigger" aria-expanded="false" aria-controls="edu-dropdown">
                        Educación Continuada
                        <svg viewBox="0 0 24 24" class="chev"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="simple-dropdown" id="edu-dropdown">
                        @foreach ($educacionContinuada as $item)
                            <a href="{{ $wa('Educación Continuada — ' . $item) }}" target="_blank" rel="noopener">{{ $item }}</a>
                        @endforeach
                    </div>
                </li>
                <li><a href="{{ route('home') }}#contacto">Contacto</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <a href="{{ route('home') }}#contacto" class="btn btn-ghost btn-sm cert-cta">
                <svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg>
                Mi certificado
            </a>
            <a href="{{ route('home') }}#areas-formacion" class="btn btn-accent btn-sm">Ver programas</a>
            <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="mobileNav" aria-label="Abrir menú">
                <svg viewBox="0 0 24 24"><use href="#ico-menu"/></svg>
            </button>
        </div>
    </div>

    <!-- Mega menú de escritorio -->
    <div class="mega-panel" id="mega-programas">
        <div class="mega-panel-inner">
            <div class="mega-grid">

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-salud"/></svg><h3>Escuela de Salud</h3></div>
                    <ul>
                        <li><a href="{{ route('programas.servicios-geriatricos') }}">Servicios Geriátricos</a></li>
                        <li><a href="{{ $wa('Seguridad Ocupacional y Laboral') }}" target="_blank" rel="noopener">Seguridad Ocupacional y Laboral</a></li>
                        <li><a href="{{ $wa('Auxiliar de Psiquiatría') }}" target="_blank" rel="noopener">Auxiliar de Psiquiatría</a></li>
                        <li><a href="{{ $wa('Camillero Hospitalario') }}" target="_blank" rel="noopener">Camillero Hospitalario</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-cocina"/></svg><h3>Cocina y Turismo</h3></div>
                    <ul>
                        <li><a href="{{ $wa('Servicios Hoteleros y Turísticos') }}" target="_blank" rel="noopener">Servicios Hoteleros y Turísticos</a></li>
                        <li><a href="{{ $wa('Cocina Nacional e Internacional') }}" target="_blank" rel="noopener">Cocina Nacional e Internacional</a></li>
                        <li><a href="{{ $wa('Sommelier') }}" target="_blank" rel="noopener">Sommelier</a></li>
                        <li><a href="{{ $wa('Inspector de Calidad de Alimentos y Bebidas') }}" target="_blank" rel="noopener">Inspector de Calidad de Alimentos y Bebidas</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-admin"/></svg><h3>Escuela Administrativa</h3></div>
                    <ul>
                        <li><a href="{{ $wa('Auditoría y Facturación de Cuentas Médicas') }}" target="_blank" rel="noopener">Auditoría y Facturación de Cuentas Médicas</a></li>
                        <li><a href="{{ $wa('Agente de Tránsito') }}" target="_blank" rel="noopener">Agente de Tránsito</a></li>
                        <li><a href="{{ $wa('Auxiliar Contable y Administrativo') }}" target="_blank" rel="noopener">Auxiliar Contable y Administrativo</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-deporte"/></svg><h3>Deporte y Cultura</h3></div>
                    <ul>
                        <li><a href="{{ $wa('Salvamento Acuático') }}" target="_blank" rel="noopener">Salvamento Acuático</a></li>
                        <li><a href="{{ $wa('Gestión y Promoción Artística') }}" target="_blank" rel="noopener">Gestión y Promoción Artística</a></li>
                        <li><a href="{{ $wa('Servicios de Recreación y Deportes') }}" target="_blank" rel="noopener">Servicios de Recreación y Deportes</a></li>
                    </ul>
                </div>

                <div class="mega-col mega-col-wide">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-ciencias"/></svg><h3>Escuela Ciencias</h3></div>
                    <ul class="mega-two-col">
                        <li><a href="{{ $wa('Producción Agropecuaria y Zootecnia') }}" target="_blank" rel="noopener">Producción Agropecuaria y Zootecnia</a></li>
                        <li><a href="{{ $wa('Asistente de Veterinaria y Zootecnia') }}" target="_blank" rel="noopener">Asistente de Veterinaria y Zootecnia</a></li>
                        <li><a href="{{ $wa('Obras Civiles y Arquitectura') }}" target="_blank" rel="noopener">Obras Civiles y Arquitectura</a></li>
                        <li><a href="{{ $wa('Criminalística, Investigación Judicial y Ciencias Forenses') }}" target="_blank" rel="noopener">Criminalística, Investigación Judicial y Ciencias Forenses</a></li>
                        <li><a href="{{ $wa('Energías Renovables') }}" target="_blank" rel="noopener">Energías Renovables</a></li>
                        <li><a href="{{ $wa('Asistente Lab. Clínico Veterinario') }}" target="_blank" rel="noopener">Asistente Lab. Clínico Veterinario</a></li>
                        <li><a href="{{ $wa('Electricista') }}" target="_blank" rel="noopener">Electricista</a></li>
                        <li><a href="{{ $wa('Electromecánica') }}" target="_blank" rel="noopener">Electromecánica</a></li>
                        <li><a href="{{ $wa('Mecánica y Electrónica de Motos') }}" target="_blank" rel="noopener">Mecánica y Electrónica de Motos</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-idiomas"/></svg><h3>Educación e Idiomas</h3></div>
                    <ul>
                        <li><a href="{{ $wa('Inglés A1-A2-B1-B2') }}" target="_blank" rel="noopener">Inglés A1–A2–B1–B2</a></li>
                        <li><a href="{{ $wa('Francés A1-A2-B1-B2') }}" target="_blank" rel="noopener">Francés A1–A2–B1–B2</a></li>
                        <li><a href="{{ $wa('Asistente de Preescolar') }}" target="_blank" rel="noopener">Asistente de Preescolar</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-belleza"/></svg><h3>Escuela de Belleza</h3></div>
                    <ul>
                        <li><a href="{{ $wa('Barbería') }}" target="_blank" rel="noopener">Barbería</a></li>
                    </ul>
                </div>

            </div>

            <div class="mega-panel-foot">
                <p>¿No encuentras tu programa? Escríbenos y te asesoramos sin costo.</p>
                <a href="{{ $wa('información general sobre programas técnicos') }}" target="_blank" rel="noopener" class="btn btn-accent btn-sm">Hablar con un asesor</a>
            </div>
        </div>
    </div>
</header>

<!-- Menú móvil -->
<div class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-inner">
        <button class="mobile-nav-close" id="mobileNavClose" aria-label="Cerrar menú"><svg viewBox="0 0 24 24"><use href="#ico-close"/></svg></button>

        <a href="{{ route('home') }}#inicio" class="mobile-link">Inicio</a>

        <div class="mobile-accordion">
            <button class="mobile-accordion-trigger" aria-expanded="false">
                Programas Técnicos
                <svg viewBox="0 0 24 24" class="chev"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="mobile-accordion-panel"><div class="mobile-accordion-panel-inner">
                @php
                    $schools = [
                        ['Escuela de Salud', 'ico-salud', ['Servicios Geriátricos','Seguridad Ocupacional y Laboral','Auxiliar de Psiquiatría','Camillero Hospitalario']],
                        ['Cocina y Turismo', 'ico-cocina', ['Servicios Hoteleros y Turísticos','Cocina Nacional e Internacional','Sommelier','Inspector de Calidad de Alimentos y Bebidas']],
                        ['Escuela Administrativa', 'ico-admin', ['Auditoría y Facturación de Cuentas Médicas','Agente de Tránsito','Auxiliar Contable y Administrativo']],
                        ['Deporte y Cultura', 'ico-deporte', ['Salvamento Acuático','Gestión y Promoción Artística','Servicios de Recreación y Deportes']],
                        ['Escuela Ciencias', 'ico-ciencias', ['Producción Agropecuaria y Zootecnia','Asistente de Veterinaria y Zootecnia','Obras Civiles y Arquitectura','Criminalística, Investigación Judicial y Ciencias Forenses','Energías Renovables','Asistente Lab. Clínico Veterinario','Electricista','Electromecánica','Mecánica y Electrónica de Motos']],
                        ['Educación e Idiomas', 'ico-idiomas', ['Inglés A1–A2–B1–B2','Francés A1–A2–B1–B2','Asistente de Preescolar']],
                        ['Escuela de Belleza', 'ico-belleza', ['Barbería']],
                    ];
                    // Programs with a real page get an internal link instead of a WhatsApp one.
                    $programRoutes = [
                        'Servicios Geriátricos' => route('programas.servicios-geriatricos'),
                    ];
                @endphp
                @foreach ($schools as [$name, $icon, $programs])
                    <div class="mobile-school">
                        <div class="mobile-school-head"><svg viewBox="0 0 24 24"><use href="#{{ $icon }}"/></svg>{{ $name }}</div>
                        <ul>
                            @foreach ($programs as $p)
                                @if (isset($programRoutes[$p]))
                                    <li><a href="{{ $programRoutes[$p] }}">{{ $p }}</a></li>
                                @else
                                    <li><a href="{{ $wa($p) }}" target="_blank" rel="noopener">{{ $p }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div></div>
        </div>

        <a href="{{ route('home') }}#areas-formacion" class="mobile-link">Cursos Obligatorios en Salud</a>

        <div class="mobile-accordion">
            <button class="mobile-accordion-trigger" aria-expanded="false">
                Educación Continuada
                <svg viewBox="0 0 24 24" class="chev"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="mobile-accordion-panel"><div class="mobile-accordion-panel-inner">
                <ul class="mobile-simple-list">
                    @foreach ($educacionContinuada as $item)
                        <li><a href="{{ $wa('Educación Continuada — ' . $item) }}" target="_blank" rel="noopener">{{ $item }}</a></li>
                    @endforeach
                </ul>
            </div></div>
        </div>

        <a href="{{ route('home') }}#contacto" class="mobile-link">Contacto</a>

        <a href="{{ route('home') }}#areas-formacion" class="btn btn-accent mobile-cta">Ver programas técnicos</a>
    </div>
</div>
<div class="mobile-nav-backdrop" id="mobileNavBackdrop"></div>
