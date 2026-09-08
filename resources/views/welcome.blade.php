<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>ESAT · Escuela Nacional de Educación — Formación Técnica Certificada</title>
    <meta name="description" content="ESAT forma técnicos laborales certificados en salud, cocina y turismo, áreas administrativas, deporte, ciencias, idiomas y belleza. Sedes en Bogotá, Sasaima, Guaduas y La Dorada.">

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="{{ asset('assets/css/css_website.css') }}">
</head>
<body>

    <!-- ============ SPRITE DE ÍCONOS ============ -->
    <svg class="icon-sprite" aria-hidden="true" focusable="false">
        <defs>
            <symbol id="ico-salud" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><path d="M12 8v8M8 12h8"/></symbol>
            <symbol id="ico-cocina" viewBox="0 0 24 24"><path d="M6 3v6a3 3 0 0 0 3 3v9M9 3v9M12 3v6a3 3 0 0 0 3-3V3M18 3c-1.7 1.6-2 3.4-2 6s.9 4 2 4v8"/></symbol>
            <symbol id="ico-admin" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/><rect x="3" y="4" width="18" height="16" rx="2"/></symbol>
            <symbol id="ico-deporte" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M6.3 6.3l2.1 2.1M15.6 15.6l2.1 2.1M17.7 6.3l-2.1 2.1M8.4 15.6l-2.1 2.1"/></symbol>
            <symbol id="ico-ciencias" viewBox="0 0 24 24"><path d="M9 3h6M10 3v5.5L5.5 17a2 2 0 0 0 1.8 3h9.4a2 2 0 0 0 1.8-3L14 8.5V3"/><path d="M8 14h8"/></symbol>
            <symbol id="ico-idiomas" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 4 5.8 4 9s-1.5 6.4-4 9c-2.5-2.6-4-5.8-4-9s1.5-6.4 4-9Z"/></symbol>
            <symbol id="ico-belleza" viewBox="0 0 24 24"><circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><path d="M20 5 7.8 15M20 19 7.8 9"/></symbol>
            <symbol id="ico-check" viewBox="0 0 24 24"><path d="M4 12.5 9.5 18 20 6"/></symbol>
            <symbol id="ico-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
            <symbol id="ico-whatsapp" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21h.01c5.45 0 9.9-4.45 9.9-9.91C21.95 6.45 17.5 2 12.04 2Zm5.71 14.09c-.24.68-1.19 1.24-1.95 1.4-.53.11-1.22.2-3.55-.76-2.98-1.24-4.9-4.26-5.05-4.46-.15-.2-1.21-1.61-1.21-3.07s.75-2.18 1.02-2.48c.24-.27.53-.34.71-.34h.51c.16 0 .38-.06.6.46.24.57.81 1.99.88 2.13.07.15.11.32.02.51-.09.19-.14.31-.28.48-.14.17-.29.38-.42.51-.14.14-.28.29-.12.57.16.28.71 1.17 1.53 1.89 1.05.94 1.94 1.23 2.22 1.37.28.14.45.12.61-.07.16-.19.7-.81.89-1.09.19-.28.37-.23.62-.14.25.09 1.6.76 1.87.9.28.14.46.21.53.32.07.11.07.65-.17 1.33Z" fill="currentColor" stroke="none"/></symbol>
            <symbol id="ico-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.3 7-11.5A7 7 0 0 0 5 9.5C5 14.7 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></symbol>
            <symbol id="ico-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 6.5 8 6 8-6"/></symbol>
            <symbol id="ico-cert" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="14" rx="1.5"/><path d="M8 7h8M8 10.5h8M8 14h5"/><path d="M9 21v-3.5l3-1.5 3 1.5V21l-3-1.6Z"/></symbol>
            <symbol id="ico-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
            <symbol id="ico-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
            <symbol id="ico-fb" viewBox="0 0 24 24"><path d="M14 9h3V6h-3c-1.7 0-3 1.3-3 3v2H9v3h2v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1Z" fill="currentColor" stroke="none"/></symbol>
            <symbol id="ico-x" viewBox="0 0 24 24"><path d="M5 4l14 16M19 4 5 20"/></symbol>
            <symbol id="ico-ig" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="3.6"/><circle cx="16.6" cy="7.4" r="1"/></symbol>
            <symbol id="ico-yt" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M11 10.2v3.6l3.2-1.8Z" fill="currentColor" stroke="none"/></symbol>
            <symbol id="ico-vet" viewBox="0 0 24 24"><ellipse cx="12" cy="15.5" rx="5" ry="4.2"/><circle cx="5.5" cy="8.5" r="1.8"/><circle cx="10" cy="5" r="1.8"/><circle cx="14" cy="5" r="1.8"/><circle cx="18.5" cy="8.5" r="1.8"/></symbol>
            <symbol id="ico-tool" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></symbol>
            <symbol id="ico-ai" viewBox="0 0 24 24"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.5 5.5l1.8 1.8M16.7 16.7l1.8 1.8M18.5 5.5l-1.8 1.8M7.3 16.7l-1.8 1.8"/></symbol>
            <symbol id="ico-megaphone" viewBox="0 0 24 24"><path d="M3 10.5v3a1 1 0 0 0 1 1h1.7l4.5 3.8a.5.5 0 0 0 .8-.4V6.1a.5.5 0 0 0-.8-.4L5.7 9.5H4a1 1 0 0 0-1 1Z"/><path d="M14.5 8.2a4.2 4.2 0 0 1 0 7.6M17.3 5.3a8.2 8.2 0 0 1 0 13.4"/></symbol>
            <symbol id="ico-heart" viewBox="0 0 24 24"><path d="M12 20.2s-7.3-4.4-9.6-9.1A5.4 5.4 0 0 1 12 6.4a5.4 5.4 0 0 1 9.6 4.7c-2.3 4.7-9.6 9.1-9.6 9.1Z"/></symbol>
            <symbol id="ico-scale" viewBox="0 0 24 24"><path d="M12 3v18M7 21h10M4 7h6M14 7h6M4 7l-2.5 5a2.5 2.5 0 0 0 5 0L4 7ZM20 7l-2.5 5a2.5 2.5 0 0 0 5 0L20 7Z"/></symbol>
            <symbol id="ico-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"/></symbol>
            <symbol id="ico-building" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M9 16h1M14 16h1"/></symbol>
            <symbol id="ico-leaf" viewBox="0 0 24 24"><path d="M20 4C10 4 4 10 4 20h2C16 20 20 14 20 4Z"/><path d="M6 18C10 14 14 10 18 6"/></symbol>
            <symbol id="ico-palette" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1 .8-1.9 1.9-1.9H17a4 4 0 0 0 4-4c0-4.4-4-7.5-9-7.5Z"/><circle cx="7.5" cy="10.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="9.5" cy="7" r="1.1" fill="currentColor" stroke="none"/><circle cx="14" cy="6.5" r="1.1" fill="currentColor" stroke="none"/></symbol>
        </defs>
    </svg>

    @php
        $wa = function (string $topic, string $number = '573152957588') {
            $msg = "Hola ESAT, quiero información sobre: {$topic}";
            return 'https://wa.me/' . $number . '?text=' . rawurlencode($msg);
        };
        $educacionContinuada = [
            'Salud Ocupacional',
            'Áreas Sociales',
            'Pedagogía',
            'Administración y Gerencia',
            'Edificios y Afines',
            'Hotelería y Turismo',
            'Salud',
        ];
    @endphp

    <!-- ============ HEADER ============ -->
    <header class="site-header" id="site-header">
        <div class="header-inner">
            <a href="#inicio" class="brand" aria-label="ESAT — Inicio">
                <img src="{{ asset('assets/img/logo.png') }}" class="brand-logo" alt="ESAT — Educación, Salud, Arte y Turismo">
            </a>

            <nav class="main-nav" aria-label="Navegación principal">
                <ul>
                    <li><a href="#inicio">Inicio</a></li>
                    <li class="has-mega">
                        <button class="mega-trigger" id="megaTrigger" aria-expanded="false" aria-controls="mega-programas">
                            Programas Técnicos
                            <svg viewBox="0 0 24 24" class="chev"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                    </li>
                    <li><a href="#areas-formacion">Cursos Obligatorios en Salud</a></li>
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
                    <li><a href="#contacto">Contacto</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <a href="#contacto" class="btn btn-ghost btn-sm cert-cta">
                    <svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg>
                    Mi certificado
                </a>
                <a href="#areas-formacion" class="btn btn-accent btn-sm">Ver programas</a>
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
                            <li><a href="{{ $wa('Servicios Geriátricos') }}" target="_blank" rel="noopener">Servicios Geriátricos</a></li>
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

            <a href="#inicio" class="mobile-link">Inicio</a>

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
                    @endphp
                    @foreach ($schools as [$name, $icon, $programs])
                        <div class="mobile-school">
                            <div class="mobile-school-head"><svg viewBox="0 0 24 24"><use href="#{{ $icon }}"/></svg>{{ $name }}</div>
                            <ul>
                                @foreach ($programs as $p)
                                    <li><a href="{{ $wa($p) }}" target="_blank" rel="noopener">{{ $p }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div></div>
            </div>

            <a href="#areas-formacion" class="mobile-link">Cursos Obligatorios en Salud</a>

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

            <a href="#contacto" class="mobile-link">Contacto</a>

            <a href="#areas-formacion" class="btn btn-accent mobile-cta">Ver programas técnicos</a>
        </div>
    </div>
    <div class="mobile-nav-backdrop" id="mobileNavBackdrop"></div>

    <main>
        <!-- ============ HERO ============ -->
        <section class="hero" id="inicio">
            <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-pause="false" data-bs-interval="4500" aria-hidden="true">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="{{ asset('assets/img/imagenhero1.png') }}" class="d-block w-100" alt="">
                    </div>
                    <div class="carousel-item">
                        <img src="{{ asset('assets/img/imagenhero2.png') }}" class="d-block w-100" alt="">
                    </div>
                    <div class="carousel-item">
                        <img src="{{ asset('assets/img/imagenhero3.png') }}" class="d-block w-100" alt="">
                    </div>
                </div>
                <div class="carousel-indicators hero-carousel-indicators">
                    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Foto 1"></button>
                    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Foto 2"></button>
                    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Foto 3"></button>
                </div>
            </div>
            <div class="hero-scrim" aria-hidden="true"></div>

            <div class="hero-content">
                <p class="hero-eyebrow-line"><span>Licencia de Funcionamiento 5850</span><span class="dot"></span><span>Secretaría de Educación de Cundinamarca</span></p>
                <h1>Fórmate para el mundo laboral que te espera</h1>
                <p class="hero-sub">Formación técnica certificada, práctica desde el primer día y con salida real al empleo. Siete escuelas, cuatro sedes, un mismo propósito: que trabajes en lo que te apasiona.</p>
                <div class="hero-actions">
                    <a href="#areas-formacion" class="btn btn-accent btn-lg">
                        Ver programas técnicos
                        <svg viewBox="0 0 24 24"><use href="#ico-arrow"/></svg>
                    </a>
                    <a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg">
                        <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                        Habla con un asesor
                    </a>
                </div>
            </div>

            <button class="scroll-cue" id="scrollCue" aria-label="Bajar a la siguiente sección">
                <span></span>
            </button>
        </section>

        <!-- ============ POR QUÉ ESTUDIAR EN ESAT ============ -->
        <section class="why" id="por-que-esat">
            <div class="section-inner why-grid">
                <div class="why-lead" data-aos="fade-up">
                    <h2>Por qué estudiar en ESAT</h2>
                    <p>Formamos técnicos que el sector productivo necesita: talento preparado para aprender haciendo, certificarse con respaldo oficial y acceder al mundo laboral con las competencias que realmente demanda. Nuestros docentes cuentan con experiencia en el sector, conectando la formación con la práctica, mientras nuestras modalidades se adaptan a las diferentes necesidades y contextos de nuestros estudiantes.
                        </p>
                    <dl class="why-stats">
                        <div><dt>7</dt><dd>Escuelas</dd></div>
                        <div><dt>20+</dt><dd>Programas técnicos</dd></div>
                        <div><dt>4</dt><dd>Sedes</dd></div>
                    </dl>
                </div>
                <ul class="why-list">
                    <li data-aos="fade-up" data-aos-delay="0">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-tool"/></svg></span>
                        <div><h3>Formación práctica orientada al empleo</h3><p>Talleres, prácticas y casos reales desde el primer módulo, no al final de la carrera.</p></div>
                    </li>
                    <li data-aos="fade-up" data-aos-delay="80">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg></span>
                        <div><h3>Certificaciones reconocidas</h3><p>Programas amparados por la Secretaría de Educación de Cundinamarca y el Ministerio de Educación Nacional.</p></div>
                    </li>
                    <li data-aos="fade-up" data-aos-delay="160">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-admin"/></svg></span>
                        <div><h3>Docentes con experiencia real</h3><p>Instructores que ejercen o ejercieron el oficio que enseñan.</p></div>
                    </li>
                    <li data-aos="fade-up" data-aos-delay="240">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-idiomas"/></svg></span>
                        <div><h3>Modalidades flexibles</h3><p>Presencial en cualquiera de nuestras cuatro sedes, o virtual desde donde estés.</p></div>
                    </li>
                    <li data-aos="fade-up" data-aos-delay="320">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-arrow"/></svg></span>
                        <div><h3>Acceso rápido al mercado laboral</h3><p>Programas técnicos diseñados para que empieces a trabajar en meses, no en años.</p></div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============ ÁREAS DE FORMACIÓN ============ -->
        <section class="areas" id="areas-formacion">
            <div class="section-inner">
                <div class="section-head" data-aos="fade-up">
                    <h2>Áreas de formación</h2>
                    <p>Tres puertas de entrada, un mismo estándar de calidad.</p>
                </div>

                <div class="areas-grid">
                    <article class="area-panel area-panel-main" data-aos="fade-up">
                        <img src="{{ asset('assets/img/programas-tecnicos.png') }}" class="area-panel-photo" alt="" aria-hidden="true">
                        <div class="area-panel-scrim" aria-hidden="true"></div>
                        <div class="area-panel-content">
                            <svg class="area-icon" viewBox="0 0 24 24"><use href="#ico-tool"/></svg>
                            <h3>Programas Técnicos</h3>
                            <p>Siete escuelas, más de veinte programas técnicos laborales con salida directa al sector salud, gastronómico, administrativo, deportivo, científico, educativo y estético.</p>
                            <a href="{{ $wa('Programas Técnicos') }}" target="_blank" rel="noopener" class="area-link">Explorar programas <svg viewBox="0 0 24 24"><use href="#ico-arrow"/></svg></a>
                        </div>
                    </article>

                    <article class="area-panel" data-aos="fade-up" data-aos-delay="100">
                        <img src="{{ asset('assets/img/cursos-salud.jpeg') }}" class="area-panel-photo" alt="" aria-hidden="true">
                        <div class="area-panel-scrim" aria-hidden="true"></div>
                        <div class="area-panel-content">
                            <svg class="area-icon" viewBox="0 0 24 24"><use href="#ico-salud"/></svg>
                            <h3>Cursos Obligatorios en Salud</h3>
                            <p>Certificaciones exigidas por ley para operar en entornos de salud y seguridad laboral.</p>
                            <a href="{{ $wa('Cursos Obligatorios en Salud') }}" target="_blank" rel="noopener" class="area-link">Ver cursos <svg viewBox="0 0 24 24"><use href="#ico-arrow"/></svg></a>
                        </div>
                    </article>

                    <article class="area-panel" data-aos="fade-up" data-aos-delay="200">
                        <img src="{{ asset('assets/img/educacion.png') }}" class="area-panel-photo" alt="" aria-hidden="true">
                        <div class="area-panel-scrim" aria-hidden="true"></div>
                        <div class="area-panel-content">
                            <svg class="area-icon" viewBox="0 0 24 24"><use href="#ico-cert"/></svg>
                            <h3>Educación Continuada</h3>
                            <p>Actualización constante para quienes ya trabajan y necesitan mantenerse vigentes en su oficio.</p>
                            <a href="{{ $wa('Educación Continuada') }}" target="_blank" rel="noopener" class="area-link">Ver programas <svg viewBox="0 0 24 24"><use href="#ico-arrow"/></svg></a>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- ============ NUEVAS CARRERAS TÉCNICAS ============ -->
        <section class="new-careers" id="nuevas-carreras">
            <div class="section-inner">
                <div class="section-head" data-aos="fade-up">
                    <h2>Nuevas carreras técnicas</h2>
                    <p>Doble titulación: un mismo esfuerzo, dos certificaciones.</p>
                </div>

                @php
                    $careers = [
                        ['Inteligencia Artificial (IA)', 'Desarrolla soluciones inteligentes para el mundo del mañana.', 'ico-ai', 'ia.png'],
                        ['Marketing y Redes Sociales', 'Conecta marcas, crea contenido y genera impacto digital.', 'ico-megaphone', 'marketing.jpeg'],
                        ['Podología y Estética de Pies', 'Cuida la salud y belleza desde la base.', 'ico-belleza', 'pdologia.png'],
                        ['Enfermería Veterinaria', 'Brinda atención y bienestar a nuestros mejores amigos.', 'ico-vet', 'veterinaria.png'],
                        ['Operación y Mantenimiento de Maquinaria Pesada', 'Maneja tecnología de alto impacto y construye el futuro.', 'ico-tool', 'maquinaria.png'],
                        ['Servicio Social y Comunitario', 'Sé agente de cambio y transforma tu comunidad.', 'ico-heart', 'servicio-social.png'],
                    ];
                @endphp

                <!-- Carrusel: 3 tarjetas visibles en desktop, 2 en tablet, 1 en mobile;
                     avanza de a una con las flechas. -->
                <div class="careers-carousel" id="careersCarousel" data-aos="fade-up">
                    <div class="careers-carousel-row">
                        <button type="button" class="careers-carousel-control careers-carousel-prev" aria-label="Carrera anterior">
                            <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <div class="careers-carousel-viewport">
                            <div class="careers-carousel-track">
                                @foreach ($careers as [$title, $desc, $icon, $photo])
                                    <article class="career-card career-carousel-card">
                                        <img src="{{ asset('assets/img/' . $photo) }}" class="career-card-photo" alt="" aria-hidden="true">
                                        <div class="career-card-scrim" aria-hidden="true"></div>
                                        <span class="badge badge-double career-badge">Doble Titulación</span>
                                        <div class="career-content">
                                            <svg class="career-icon" viewBox="0 0 24 24"><use href="#{{ $icon }}"/></svg>
                                            <h3>{{ $title }}</h3>
                                            <p>{{ $desc }}</p>
                                            <a href="{{ $wa($title) }}" target="_blank" rel="noopener" class="career-link">
                                                Más información <svg viewBox="0 0 24 24"><use href="#ico-arrow"/></svg>
                                            </a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                        <button type="button" class="careers-carousel-control careers-carousel-next" aria-label="Siguiente carrera">
                            <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="careers-carousel-dots"></div>
                </div>
            </div>
        </section>

        <!-- ============ CONVENIOS INSTITUCIONALES ============ -->
        <section class="convenios">
            <div class="section-inner">
                <div class="section-head" data-aos="fade-up">
                    <h2>Convenios institucionales</h2>
                    <p>Alianzas públicas y privadas que respaldan nuestra formación en toda la región.</p>
                </div>

                <!-- Alcaldías: panel ancho — los chips necesitan espacio horizontal,
                     no una columna alta, para no dejar espacio muerto. -->
                <div class="convenio-hero convenio-accent-orange" data-aos="fade-up">
                    <div class="convenio-hero-text">
                        <span class="badge badge-double convenio-hero-badge">8 municipios aliados</span>
                        <h3>Alcaldías municipales</h3>
                        <p>Cada alcaldía respalda espacios de práctica, convocatorias y proyección social para nuestros estudiantes en su municipio.</p>
                    </div>
                    <ul class="chip-row chip-row-pinned">
                        @foreach (['Villeta','Cachipay','Albán','La Vega','Supatá','Anolaima','Guaduas','La Dorada'] as $municipio)
                            <li><svg viewBox="0 0 24 24"><use href="#ico-pin"/></svg>{{ $municipio }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="convenios-grid">
                    <div class="convenio-block convenio-accent-green" data-aos="fade-up">
                        <h3>Entidades públicas y privadas</h3>
                        <ul class="entity-list">
                            <li><svg viewBox="0 0 24 24"><use href="#ico-scale"/></svg><span>Fiscalía y CTI de Villeta</span></li>
                            <li><svg viewBox="0 0 24 24"><use href="#ico-shield"/></svg><span>INPEC — Honda, Norcasia y Puerto Triunfo</span></li>
                            <li><svg viewBox="0 0 24 24"><use href="#ico-building"/></svg><span>FTI Bogotá</span></li>
                            <li><svg viewBox="0 0 24 24"><use href="#ico-salud"/></svg><span>Todo en Salud a la Mano S.A.S.</span></li>
                        </ul>
                    </div>

                    <div class="convenio-block convenio-accent-navy" data-aos="fade-up" data-aos-delay="100">
                        <h3>Otras alianzas</h3>
                        <ul class="simple-list">
                            <li><svg viewBox="0 0 24 24"><use href="#ico-vet"/></svg><span>Clínicas veterinarias</span></li>
                            <li><svg viewBox="0 0 24 24"><use href="#ico-leaf"/></svg><span>Granjas especializadas</span></li>
                            <li><svg viewBox="0 0 24 24"><use href="#ico-building"/></svg><span>Empresas de obras civiles</span></li>
                            <li><svg viewBox="0 0 24 24"><use href="#ico-palette"/></svg><span>Centros artísticos</span></li>
                            <li><svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg><span>Universidades</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ PRÓXIMAS CONVOCATORIAS ============ -->
        <section class="convocatorias" id="convocatorias">
            <div class="section-inner">
                <div class="section-head" data-aos="fade-up">
                    <h2>Próximas convocatorias</h2>
                    <p>Cupos abiertos — asegura el tuyo antes del cierre de inscripciones.</p>
                </div>

                <div class="convocatorias-row">
                    @php
                        $calls = [
                            ['Salud', 'ico-salud', 'Curso de Actualización en Atención al Paciente', 'Para personal asistencial que busca renovar su certificación.'],
                            ['Salud', 'ico-salud', 'Curso Obligatorio Salud Ocupacional', 'Requisito legal para operar en entornos laborales regulados.'],
                            ['Administrativo', 'ico-admin', 'Programa Técnico Laboral en el Área Administrativa', 'Formación integral en gestión y procesos de oficina.'],
                            ['Continuada', 'ico-cert', 'Educación Continuada en Normativa y Procedimientos', 'Actualización para profesionales en ejercicio.'],
                        ];
                    @endphp
                    @foreach ($calls as [$tag, $icon, $title, $desc])
                        <article class="call-card call-accent-{{ Str::slug($tag) }}" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                            <div class="call-card-top">
                                <svg class="call-card-icon" viewBox="0 0 24 24"><use href="#{{ $icon }}"/></svg>
                                <span class="badge badge-tag tag-{{ Str::slug($tag) }}">{{ $tag }}</span>
                            </div>
                            <h3>{{ $title }}</h3>
                            <p>{{ $desc }}</p>
                            <a href="{{ $wa($title) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Ver más</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- ============ SEDES ============ -->
        <section class="sedes" id="sedes">
            <div class="section-inner sedes-grid">
                <div class="sedes-map" data-aos="zoom-in" aria-hidden="true">
                    <svg viewBox="0 0 420 460" class="map-svg">
                        <path class="map-route" d="M120 90 L300 130 L340 300 L160 380 L120 90"/>
                        <circle class="map-node" cx="120" cy="90" r="7"/>
                        <circle class="map-node" cx="300" cy="130" r="7"/>
                        <circle class="map-node" cx="340" cy="300" r="7"/>
                        <circle class="map-node" cx="160" cy="380" r="7"/>
                        <circle class="map-node-pulse" cx="120" cy="90" r="7"/>
                        <circle class="map-node-pulse" cx="300" cy="130" r="7"/>
                        <circle class="map-node-pulse" cx="340" cy="300" r="7"/>
                        <circle class="map-node-pulse" cx="160" cy="380" r="7"/>
                    </svg>
                </div>

                <div class="sedes-list">
                    <div class="section-head section-head-light" data-aos="fade-up">
                        <h2>Nuestras sedes</h2>
                        <p>Cuatro puntos de encuentro en Cundinamarca y Caldas.</p>
                    </div>

                    <ul>
                        <li data-aos="fade-up" data-aos-delay="0">
                            <svg viewBox="0 0 24 24"><use href="#ico-pin"/></svg>
                            <div><h3>Bogotá</h3><p>Cra 52 #71-18</p></div>
                        </li>
                        <li data-aos="fade-up" data-aos-delay="80">
                            <svg viewBox="0 0 24 24"><use href="#ico-pin"/></svg>
                            <div><h3>Sasaima</h3><p>Urbanización San José, Cra 2 #8-84, Sasaima, Cundinamarca</p></div>
                        </li>
                        <li data-aos="fade-up" data-aos-delay="160">
                            <svg viewBox="0 0 24 24"><use href="#ico-pin"/></svg>
                            <div><h3>Guaduas</h3><p>Cra 2 No. 1-83, Calle de la Pola, Cundinamarca</p></div>
                        </li>
                        <li data-aos="fade-up" data-aos-delay="240">
                            <svg viewBox="0 0 24 24"><use href="#ico-pin"/></svg>
                            <div><h3>La Dorada</h3><p>Calle 9 No. 7-55, Barrio Magdalena, La Dorada, Caldas</p></div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ============ NEWSLETTER + CERTIFICADOS ============ -->
        <section class="cta-band" id="contacto">
            <div class="section-inner cta-grid">
                <div class="cta-card" data-aos="fade-up">
                    <svg viewBox="0 0 24 24"><use href="#ico-mail"/></svg>
                    <h3>Novedades académicas</h3>
                    <p>Recibe convocatorias, fechas de apertura y noticias de las siete escuelas directamente en tu correo.</p>
                    <form class="newsletter-form" id="newsletterForm" novalidate>
                        <label for="newsletterEmail" class="sr-only">Correo electrónico</label>
                        <input type="email" id="newsletterEmail" name="email" placeholder="tucorreo@ejemplo.com" required autocomplete="email">
                        <button type="submit" class="btn btn-accent btn-sm">Suscribirme</button>
                    </form>
                    <p class="form-note" id="newsletterNote" role="status" aria-live="polite"></p>
                </div>

                <div class="cta-card cta-card-dark" data-aos="fade-up" data-aos-delay="100">
                    <svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg>
                    <h3>¿Ya eres egresado?</h3>
                    <p>Consulta y verifica tu certificado técnico directamente con nuestro equipo de registro académico.</p>
                    <a href="{{ $wa('consultar mi certificado') }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm">Ver mi certificado online</a>
                </div>
            </div>
        </section>
    </main>

    <!-- ============ FOOTER ============ -->
    <footer class="site-footer">
        <div class="section-inner footer-grid">
            <div class="footer-brand">
                <a href="#inicio" class="brand brand-footer" aria-label="ESAT — Inicio">
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
                    <li><a href="#areas-formacion">Programas técnicos</a></li>
                    <li><a href="#nuevas-carreras">Nuevas carreras</a></li>
                    <li><a href="#convocatorias">Convocatorias</a></li>
                    <li><a href="#sedes">Sedes</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Sedes</h4>
                <ul>
                    <li>Bogotá — Cra 52 #71-18</li>
                    <li>Sasaima — Cundinamarca</li>
                    <li>Guaduas — Cundinamarca</li>
                    <li>La Dorada — Caldas</li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contacto</h4>
                <ul>
                    <li><a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener">315 295 75 88</a></li>
                    <li><a href="{{ $wa('información general sobre ESAT', '573123773144') }}" target="_blank" rel="noopener">312 377 31 44</a></li>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script src="{{ asset('assets/js/js_website.js') }}" defer></script>
</body>
</html>
