@extends('layouts.app')

@section('content')
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
                <h1>Tu futuro empieza con la formación correcta</h1>
                <p class="hero-sub">Formación técnica certificada, práctica desde el primer día y con proyección real. Siete escuelas, cuatro sedes, un mismo propósito: que crezcas en lo que te apasiona.</p>
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
            <img src="{{ asset('assets/img/favicon.png') }}" class="section-watermark" alt="" aria-hidden="true">
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
            <img src="{{ asset('assets/img/favicon.png') }}" class="section-watermark" alt="" aria-hidden="true">
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

        <!-- ============ NEWSLETTER + CERTIFICADOS ============ -->
        <section class="newsletter-band" id="contacto">
            <svg class="newsletter-band-deco" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-mail"/></svg>
            <div class="section-inner newsletter-inner" data-aos="fade-up">
                <div class="newsletter-copy">
                    <span class="cta-card-icon"><svg viewBox="0 0 24 24"><use href="#ico-mail"/></svg></span>
                    <h2>Novedades académicas</h2>
                    <p>Recibe convocatorias, fechas de apertura y noticias de las siete escuelas directamente en tu correo.</p>
                </div>
                <div class="newsletter-action">
                    <form class="newsletter-form" id="newsletterForm" novalidate>
                        <label for="newsletterEmail" class="sr-only">Correo electrónico</label>
                        <input type="email" id="newsletterEmail" name="email" placeholder="tucorreo@ejemplo.com" required autocomplete="email">
                        <button type="submit" class="btn btn-accent btn-sm">Suscribirme</button>
                    </form>
                    <p class="form-note" id="newsletterNote" role="status" aria-live="polite"></p>
                </div>
            </div>
        </section>
@endsection
