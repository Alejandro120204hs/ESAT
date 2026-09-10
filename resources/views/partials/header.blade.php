<!-- ============ HEADER ============ -->
<header class="site-header" id="site-header">
    <div class="header-inner">
        <a href="{{ route('home') }}#inicio" class="brand" aria-label="ESAT — Inicio">
            <img src="{{ asset('assets/img/logo.png') }}" class="brand-logo" alt="ESAT — Educación, Salud, Arte y Turismo">
        </a>

        @php
            // Educación Continuada categories with a real page get an
            // internal link instead of a WhatsApp one.
            $continuadaRoutes = [
                'Salud Ocupacional' => route('continuada.salud-ocupacional'),
                'Áreas Sociales' => route('continuada.areas-sociales'),
                'Pedagogía' => route('continuada.pedagogia'),
                'Administración y Gerencia' => route('continuada.administracion-y-gerencia'),
                'Edificios y Afines' => route('continuada.edificios-y-afines'),
                'Hotelería y Turismo' => route('continuada.hoteleria-y-turismo'),
                'Salud' => route('continuada.salud'),
            ];
        @endphp
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
                            <a href="{{ $continuadaRoutes[$item] }}">{{ $item }}</a>
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
                        <li><a href="{{ route('programas.seguridad-ocupacional-y-laboral') }}">Seguridad Ocupacional y Laboral</a></li>
                        <li><a href="{{ route('programas.auxiliar-de-psiquiatria') }}">Auxiliar de Psiquiatría</a></li>
                        <li><a href="{{ route('programas.camillero-hospitalario') }}">Camillero Hospitalario</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-cocina"/></svg><h3>Cocina y Turismo</h3></div>
                    <ul>
                        <li><a href="{{ route('programas.servicios-hoteleros-y-turisticos') }}">Servicios Hoteleros y Turísticos</a></li>
                        <li><a href="{{ route('programas.cocina-nacional-e-internacional') }}">Cocina Nacional e Internacional</a></li>
                        <li><a href="{{ route('programas.sommelier') }}">Sommelier</a></li>
                        <li><a href="{{ route('programas.inspector-de-calidad-de-alimentos-y-bebidas') }}">Inspector de Calidad de Alimentos y Bebidas</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-admin"/></svg><h3>Escuela Administrativa</h3></div>
                    <ul>
                        <li><a href="{{ route('programas.auditoria-y-facturacion-de-cuentas-medicas') }}">Auditoría y Facturación de Cuentas Médicas</a></li>
                        <li><a href="{{ route('programas.agente-de-transito') }}">Agente de Tránsito</a></li>
                        <li><a href="{{ route('programas.auxiliar-contable-y-administrativo') }}">Auxiliar Contable y Administrativo</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-deporte"/></svg><h3>Deporte y Cultura</h3></div>
                    <ul>
                        <li><a href="{{ route('programas.salvamento-acuatico') }}">Salvamento Acuático</a></li>
                        <li><a href="{{ route('programas.gestion-y-promocion-artistica') }}">Gestión y Promoción Artística</a></li>
                        <li><a href="{{ route('programas.servicios-de-recreacion-y-deportes') }}">Servicios de Recreación y Deportes</a></li>
                    </ul>
                </div>

                <div class="mega-col mega-col-wide">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-ciencias"/></svg><h3>Escuela Ciencias</h3></div>
                    <ul class="mega-two-col">
                        <li><a href="{{ route('programas.produccion-agropecuaria-y-zootecnia') }}">Producción Agropecuaria y Zootecnia</a></li>
                        <li><a href="{{ route('programas.asistente-de-veterinaria-y-zootecnia') }}">Asistente de Veterinaria y Zootecnia</a></li>
                        <li><a href="{{ route('programas.obras-civiles-y-arquitectura') }}">Obras Civiles y Arquitectura</a></li>
                        <li><a href="{{ route('programas.criminalistica-investigacion-judicial-y-ciencias-forenses') }}">Criminalística, Investigación Judicial y Ciencias Forenses</a></li>
                        <li><a href="{{ route('programas.energias-renovables-y-alternativas') }}">Energías Renovables</a></li>
                        <li><a href="{{ route('programas.asistente-de-laboratorio-clinico-veterinario') }}">Asistente Lab. Clínico Veterinario</a></li>
                        <li><a href="{{ route('programas.electricista') }}">Electricista</a></li>
                        <li><a href="{{ route('programas.electromecanica') }}">Electromecánica</a></li>
                        <li><a href="{{ route('programas.mecanica-y-electronica-de-motos') }}">Mecánica y Electrónica de Motos</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-idiomas"/></svg><h3>Educación e Idiomas</h3></div>
                    <ul>
                        <li><a href="{{ route('programas.ingles-a1-a2-b1-b2') }}">Inglés A1–A2–B1–B2</a></li>
                        <li><a href="{{ route('programas.frances-a1-a2-b1-b2') }}">Francés A1–A2–B1–B2</a></li>
                        <li><a href="{{ route('programas.asistente-de-preescolar') }}">Asistente de Preescolar</a></li>
                    </ul>
                </div>

                <div class="mega-col">
                    <div class="mega-col-head"><svg viewBox="0 0 24 24"><use href="#ico-belleza"/></svg><h3>Escuela de Belleza</h3></div>
                    <ul>
                        <li><a href="{{ route('programas.barberia') }}">Barbería</a></li>
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
                        'Seguridad Ocupacional y Laboral' => route('programas.seguridad-ocupacional-y-laboral'),
                        'Auxiliar de Psiquiatría' => route('programas.auxiliar-de-psiquiatria'),
                        'Camillero Hospitalario' => route('programas.camillero-hospitalario'),
                        'Servicios Hoteleros y Turísticos' => route('programas.servicios-hoteleros-y-turisticos'),
                        'Cocina Nacional e Internacional' => route('programas.cocina-nacional-e-internacional'),
                        'Sommelier' => route('programas.sommelier'),
                        'Inspector de Calidad de Alimentos y Bebidas' => route('programas.inspector-de-calidad-de-alimentos-y-bebidas'),
                        'Auditoría y Facturación de Cuentas Médicas' => route('programas.auditoria-y-facturacion-de-cuentas-medicas'),
                        'Agente de Tránsito' => route('programas.agente-de-transito'),
                        'Auxiliar Contable y Administrativo' => route('programas.auxiliar-contable-y-administrativo'),
                        'Salvamento Acuático' => route('programas.salvamento-acuatico'),
                        'Gestión y Promoción Artística' => route('programas.gestion-y-promocion-artistica'),
                        'Servicios de Recreación y Deportes' => route('programas.servicios-de-recreacion-y-deportes'),
                        'Producción Agropecuaria y Zootecnia' => route('programas.produccion-agropecuaria-y-zootecnia'),
                        'Asistente de Veterinaria y Zootecnia' => route('programas.asistente-de-veterinaria-y-zootecnia'),
                        'Obras Civiles y Arquitectura' => route('programas.obras-civiles-y-arquitectura'),
                        'Criminalística, Investigación Judicial y Ciencias Forenses' => route('programas.criminalistica-investigacion-judicial-y-ciencias-forenses'),
                        'Energías Renovables' => route('programas.energias-renovables-y-alternativas'),
                        'Asistente Lab. Clínico Veterinario' => route('programas.asistente-de-laboratorio-clinico-veterinario'),
                        'Electricista' => route('programas.electricista'),
                        'Electromecánica' => route('programas.electromecanica'),
                        'Mecánica y Electrónica de Motos' => route('programas.mecanica-y-electronica-de-motos'),
                        'Inglés A1–A2–B1–B2' => route('programas.ingles-a1-a2-b1-b2'),
                        'Francés A1–A2–B1–B2' => route('programas.frances-a1-a2-b1-b2'),
                        'Asistente de Preescolar' => route('programas.asistente-de-preescolar'),
                        'Barbería' => route('programas.barberia'),
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
                        <li><a href="{{ $continuadaRoutes[$item] }}">{{ $item }}</a></li>
                    @endforeach
                </ul>
            </div></div>
        </div>

        <a href="{{ route('home') }}#contacto" class="mobile-link">Contacto</a>

        <a href="{{ route('home') }}#areas-formacion" class="btn btn-accent mobile-cta">Ver programas técnicos</a>
    </div>
</div>
<div class="mobile-nav-backdrop" id="mobileNavBackdrop"></div>
