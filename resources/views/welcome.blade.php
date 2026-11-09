@extends('layouts.app')

{{-- Inicio del sitio público. El contenido es el que ya tenía la página: beneficios, áreas,
     nuevas carreras, convenios y convocatorias. Escuelas, programas y sedes salen de
     config/sitio.php, y las cifras de la barra se cuentan de esos mismos datos (escuelas,
     programas y sedes) más los municipios de Convenios: no hay cifras inventadas. Faltan, por
     parte de ESAT, estudiantes activos y egresados, y una foto por sede. --}}

@push('styles')
    <link rel="preload" as="image" href="{{ asset('assets/img/imagenhero1.webp') }}"
          imagesrcset="{{ asset('assets/img/imagenhero1-960.webp') }} 960w, {{ asset('assets/img/imagenhero1.webp') }} 1920w" imagesizes="(max-width: 1440px) 100vw, 1440px" fetchpriority="high">
    <link rel="stylesheet" href="{{ $recurso('assets/css/sitio/inicio.css') }}">
@endpush
@push('scripts')
    <script src="{{ $recurso('assets/js/sitio/inicio.js') }}" defer></script>
@endpush

@php
    $escuelas = $sitio['escuelas'];
    $totalProgramas = array_sum(array_map(fn ($e) => count($e['programas']), $escuelas));
    $municipios = ['Villeta', 'Cachipay', 'Albán', 'La Vega', 'Supatá', 'Anolaima', 'Guaduas', 'La Dorada'];
    $img = fn (string $nombre) => asset('assets/img/' . $nombre . '.webp');
    $srcset = fn (string $nombre) => asset('assets/img/' . $nombre . '-960.webp') . ' 960w, ' . asset('assets/img/' . $nombre . '.webp') . ' 1920w';
@endphp

@section('content')
    {{-- ============ PORTADA ============ --}}
    <section class="ini-hero" id="inicio" aria-label="Presentación">
        <div class="ini-hero-marco" data-parallax-marco>
            <div class="ini-hero-fotos" aria-hidden="true">
                @foreach ([1, 2, 3] as $i)
                    <img src="{{ $img('imagenhero' . $i) }}" srcset="{{ $srcset('imagenhero' . $i) }}" sizes="(max-width: 1440px) 100vw, 1440px"
                         alt="" class="{{ $i === 1 ? 'is-activa' : '' }}" @if ($i === 1) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                @endforeach
            </div>
            <div class="ini-hero-velo" aria-hidden="true"></div>

            <div class="ini-hero-texto">
                <h1 data-revelar>Tu futuro empieza con la <span class="resalte">formación correcta</span></h1>
                <p class="ini-hero-sub" data-revelar data-retraso="0.12">Formación técnica certificada, práctica desde el primer día y con proyección real. Siete escuelas, cuatro sedes, un mismo propósito: que crezcas en lo que te apasiona.</p>
                <div class="ini-hero-acciones" data-revelar data-retraso="0.22">
                    <a href="#escuelas" class="btn btn-accent btn-lg">
                        Ver programas técnicos
                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                    </a>
                    <a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg">
                        <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                        Habla con un asesor
                    </a>
                </div>
            </div>

            <div class="ini-hero-pasos" id="heroPasos" role="group" aria-label="Fotos de la portada">
                @foreach ([1, 2, 3] as $i)
                    <button type="button" aria-label="Ver foto {{ $i }}" @if ($i === 1) aria-current="true" @endif><span></span></button>
                @endforeach
            </div>
        </div>

        <div class="section-inner">
            <dl class="ini-cifras" data-revelar>
                <div>
                    <dt>Escuelas técnicas</dt>
                    <dd><span aria-hidden="true" data-contar="{{ count($escuelas) }}">{{ count($escuelas) }}</span><span class="sr-only">{{ count($escuelas) }}</span></dd>
                </div>
                <div>
                    <dt>Programas técnicos laborales</dt>
                    <dd><span aria-hidden="true" data-contar="{{ $totalProgramas }}">{{ $totalProgramas }}</span><span class="sr-only">{{ $totalProgramas }}</span></dd>
                </div>
                <div>
                    <dt>Sedes en Cundinamarca y Caldas</dt>
                    <dd><span aria-hidden="true" data-contar="{{ count($sitio['sedes']) }}">{{ count($sitio['sedes']) }}</span><span class="sr-only">{{ count($sitio['sedes']) }}</span></dd>
                </div>
                <div>
                    <dt>Municipios aliados</dt>
                    <dd><span aria-hidden="true" data-contar="{{ count($municipios) }}">{{ count($municipios) }}</span><span class="sr-only">{{ count($municipios) }}</span></dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ============ POR QUÉ ESTUDIAR EN ESAT ============ --}}
    <section class="ini-porque" id="por-que-esat">
        <div class="section-inner ini-porque-grid">
            <figure class="ini-porque-foto" data-revelar="mascara">
                <div class="ini-porque-foto-marco" data-parallax-marco>
                    <img src="{{ $img('turismo') }}" srcset="{{ $srcset('turismo') }}" sizes="(max-width: 900px) 100vw, 46vw" alt="Estudiantes de ESAT en una práctica de campo" loading="lazy" decoding="async" data-parallax="0.06">
                </div>
                <figcaption class="ini-porque-sello">
                    <span class="ini-porque-sello-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-cert"/></svg></span>
                    <span><strong>Licencia de Funcionamiento 5850</strong>Secretaría de Educación de Cundinamarca</span>
                </figcaption>
            </figure>

            <div class="ini-porque-texto">
                <h2 data-revelar>Por qué estudiar en <span class="resalte">ESAT</span></h2>
                <p class="ini-porque-intro" data-revelar>Formamos técnicos que el sector productivo necesita: talento preparado para aprender haciendo, certificarse con respaldo oficial y acceder al mundo laboral con las competencias que realmente demanda. Nuestros docentes cuentan con experiencia en el sector, conectando la formación con la práctica, mientras nuestras modalidades se adaptan a las diferentes necesidades y contextos de nuestros estudiantes.</p>

                <div class="ini-porque-recorrido">
                    <span class="ini-porque-espina" aria-hidden="true"><span id="porqueEspina"></span></span>
                    <ul class="ini-porque-lista" id="porqueLista">
                        @foreach ([
                            ['ico-tool', 'Formación práctica orientada al empleo', 'Talleres, prácticas y casos reales desde el primer módulo, no al final de la carrera.'],
                            ['ico-cert', 'Certificaciones reconocidas', 'Programas amparados por la Secretaría de Educación de Cundinamarca y el Ministerio de Educación Nacional.'],
                            ['ico-admin', 'Docentes con experiencia real', 'Instructores que ejercen o ejercieron el oficio que enseñan.'],
                            ['ico-idiomas', 'Modalidades flexibles', 'Presencial en cualquiera de nuestras cuatro sedes, o virtual desde donde estés.'],
                            ['ico-arrow', 'Acceso rápido al mercado laboral', 'Programas técnicos diseñados para que empieces a trabajar en meses, no en años.'],
                        ] as [$icono, $titulo, $texto])
                            <li>
                                <span class="ini-porque-nodo"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $icono }}"/></svg></span>
                                <div>
                                    <h3>{{ $titulo }}</h3>
                                    <p>{{ $texto }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ ÁREAS DE FORMACIÓN ============ --}}
    <section class="ini-areas" id="areas-formacion">
        <div class="section-inner">
            <div class="section-head es-dividida" data-revelar>
                <h2>Áreas de formación</h2>
                <p>Tres puertas de entrada, un mismo estándar de calidad.</p>
            </div>

            <div class="ini-areas-grid">
                <article class="ini-area ini-area-principal" data-revelar="mascara">
                    <img src="{{ $img('programas-tecnicos') }}" srcset="{{ $srcset('programas-tecnicos') }}" sizes="(max-width: 900px) 100vw, 60vw" alt="" loading="lazy" decoding="async">
                    <div class="ini-area-velo" aria-hidden="true"></div>
                    <div class="ini-area-cuerpo">
                        <span class="ini-area-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-tool"/></svg></span>
                        <h3>Programas Técnicos</h3>
                        <p>Siete escuelas, más de veinte programas técnicos laborales con salida directa al sector salud, gastronómico, administrativo, deportivo, científico, educativo y estético.</p>
                        <a href="#escuelas" class="ini-area-enlace">Explorar programas <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg></a>
                    </div>
                </article>

                <article class="ini-area" data-revelar="mascara" data-retraso="0.1">
                    <img src="{{ $img('cursos-salud') }}" srcset="{{ $srcset('cursos-salud') }}" sizes="(max-width: 900px) 100vw, 40vw" alt="" loading="lazy" decoding="async">
                    <div class="ini-area-velo" aria-hidden="true"></div>
                    <div class="ini-area-cuerpo">
                        <span class="ini-area-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-salud"/></svg></span>
                        <h3>Cursos Obligatorios en Salud</h3>
                        <p>Certificaciones exigidas por ley para operar en entornos de salud y seguridad laboral.</p>
                        <a href="{{ $wa('Cursos Obligatorios en Salud') }}" target="_blank" rel="noopener" class="ini-area-enlace">Ver cursos <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg></a>
                    </div>
                </article>

                <article class="ini-area" data-revelar="mascara" data-retraso="0.2">
                    <img src="{{ $img('educacion') }}" srcset="{{ $srcset('educacion') }}" sizes="(max-width: 900px) 100vw, 40vw" alt="" loading="lazy" decoding="async">
                    <div class="ini-area-velo" aria-hidden="true"></div>
                    <div class="ini-area-cuerpo">
                        <span class="ini-area-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-cert"/></svg></span>
                        <h3>Educación Continuada</h3>
                        <p>Actualización constante para quienes ya trabajan y necesitan mantenerse vigentes en su oficio.</p>
                        <ul class="ini-area-chips">
                            @foreach ($sitio['continuada'] as [$nombre, $slug])
                                <li><a href="{{ route('continuada.' . $slug) }}">{{ $nombre }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            </div>
        </div>
    </section>

    {{-- ============ ESCUELAS ============ --}}
    <section class="ini-escuelas" id="escuelas">
        <div class="section-inner">
            <div class="section-head es-dividida" data-revelar>
                <h2>{{ count($escuelas) }} escuelas, {{ $totalProgramas }} programas técnicos</h2>
                <p>Elige tu escuela y entra directo al programa que te interesa.</p>
            </div>

            <div class="ini-escuelas-grid">
                @foreach ($escuelas as $escuela)
                    @php $n = count($escuela['programas']); @endphp
                    <article class="escuela {{ $n > 6 ? 'es-ancha' : '' }}" data-revelar data-retraso="{{ ($loop->index % 4) * 0.06 }}">
                        <div class="escuela-foto">
                            <img src="{{ $img($escuela['foto']) }}" srcset="{{ $srcset($escuela['foto']) }}" sizes="(max-width: 600px) 100vw, (max-width: 1240px) 50vw, 33vw"
                                 alt="" loading="lazy" decoding="async">
                        </div>
                        <div class="escuela-cuerpo">
                            <div class="escuela-cabeza">
                                <span class="escuela-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $escuela['icono'] }}"/></svg></span>
                                <div>
                                    <h3><a href="{{ route('escuelas.' . $escuela['slug']) }}">{{ $escuela['nombre'] }}</a></h3>
                                    <p>{{ $n }} {{ $n === 1 ? 'programa' : 'programas' }}</p>
                                </div>
                            </div>
                            <ul class="escuela-programas">
                                @foreach ($escuela['programas'] as [$nombre, $slug])
                                    <li><a href="{{ route('programas.' . $slug) }}">
                                        <span>{{ $nombre }}</span>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                                    </a></li>
                                @endforeach
                            </ul>
                        </div>
                    </article>
                @endforeach

                <aside class="escuelas-asesor" data-revelar>
                    <div>
                        <h3>¿No encuentras tu programa?</h3>
                        <p>Escríbenos y te asesoramos sin costo.</p>
                    </div>
                    <a href="{{ $wa('información general sobre programas técnicos') }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg">
                        <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                        Hablar con un asesor
                    </a>
                </aside>
            </div>
        </div>
    </section>

    {{-- ============ NUEVAS CARRERAS TÉCNICAS ============
         En escritorio la sección se queda fija y las tarjetas pasan en
         horizontal con el scroll; en celular se deslizan con el dedo. --}}
    <section class="ini-carreras" id="nuevas-carreras">
        <div class="ini-carreras-fijo" id="carrerasFijo">
            <div class="section-inner ini-carreras-cabeza">
                <div class="section-head section-head-light" data-revelar>
                    <h2>Nuevas carreras técnicas</h2>
                    <p>Doble titulación: un mismo esfuerzo, dos certificaciones.</p>
                </div>
            </div>

            <div class="ini-carreras-ventana" id="carrerasVentana" tabindex="0" role="region" aria-label="Nuevas carreras técnicas">
                <ul class="ini-carreras-pista" id="carrerasPista">
                    @foreach ([
                        ['Inteligencia Artificial (IA)', 'Desarrolla soluciones inteligentes para el mundo del mañana.', 'ico-ai', 'ia'],
                        ['Marketing y Redes Sociales', 'Conecta marcas, crea contenido y genera impacto digital.', 'ico-megaphone', 'marketing'],
                        ['Podología y Estética de Pies', 'Cuida la salud y belleza desde la base.', 'ico-belleza', 'pdologia'],
                        ['Enfermería Veterinaria', 'Brinda atención y bienestar a nuestros mejores amigos.', 'ico-vet', 'veterinaria'],
                        ['Operación y Mantenimiento de Maquinaria Pesada', 'Maneja tecnología de alto impacto y construye el futuro.', 'ico-tool', 'maquinaria'],
                        ['Servicio Social y Comunitario', 'Sé agente de cambio y transforma tu comunidad.', 'ico-heart', 'servicio-social'],
                    ] as [$titulo, $texto, $icono, $foto])
                        <li class="carrera">
                            <div class="carrera-foto">
                                <img src="{{ $img($foto) }}" srcset="{{ $srcset($foto) }}" sizes="(max-width: 600px) 82vw, 380px" alt="" loading="lazy" decoding="async">
                            </div>
                            <div class="carrera-cuerpo">
                                <span class="carrera-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $icono }}"/></svg></span>
                                <h3>{{ $titulo }}</h3>
                                <p>{{ $texto }}</p>
                                <a href="{{ $wa($titulo) }}" target="_blank" rel="noopener" class="carrera-enlace">
                                    Más información
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="section-inner ini-carreras-riel">
                <span class="ini-carreras-avance" aria-hidden="true"><span id="carrerasAvance"></span></span>
                <div class="ini-carreras-flechas">
                    <button type="button" class="ini-flecha" id="carrerasAntes" aria-label="Carrera anterior"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>
                    <button type="button" class="ini-flecha" id="carrerasDespues" aria-label="Siguiente carrera"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ SEDES ============
         Las tarjetas mueven el mapa: al pasar por cada una (o al tocarla)
         el mapa vuela a esa sede. Falta una foto real de cada sede. --}}
    <section class="ini-sedes" id="sedes">
        <div class="section-inner">
            <div class="section-head es-dividida" data-revelar>
                <h2>Cuatro sedes, una misma formación</h2>
                <p>Estudia cerca de casa, en Cundinamarca y Caldas.</p>
            </div>
        </div>
        <div class="section-inner ini-sedes-grid">
            <div class="ini-sedes-mapa-col">
                <div class="ini-sedes-mapa" id="sedesMapa" aria-hidden="true"
                     data-sedes="{{ json_encode(array_map(fn ($s) => ['nombre' => $s['nombre'], 'lat' => $s['lat'], 'lng' => $s['lng']], $sitio['sedes'])) }}"></div>
            </div>

            <div class="ini-sedes-lista">
                @foreach ($sitio['sedes'] as $sede)
                    <article class="sede" data-sede="{{ $loop->index }}">
                        <h3>{{ $sede['nombre'] }}</h3>
                        <p class="sede-direccion"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-pin"/></svg>{{ $sede['direccion'] }}</p>
                        <div class="sede-acciones">
                            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ $sede['lat'] }},{{ $sede['lng'] }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                Cómo llegar
                                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                            </a>
                            <a href="{{ $wa('la sede ' . $sede['nombre']) }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                                <svg class="ico-relleno" viewBox="0 0 448 512" aria-hidden="true"><use href="#ico-whatsapp"/></svg>
                                Escribir
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ CONVENIOS INSTITUCIONALES ============ --}}
    <section class="ini-convenios" id="convenios">
        <div class="section-inner">
            <div class="section-head es-dividida" data-revelar>
                <h2>Convenios institucionales</h2>
                <p>Alianzas públicas y privadas que respaldan nuestra formación en toda la región.</p>
            </div>
        </div>

        <div class="cinta" id="cintaMunicipios">
            <ul class="cinta-pista" aria-label="Alcaldías municipales aliadas">
                @foreach ($municipios as $municipio)
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-pin"/></svg>{{ $municipio }}</li>
                @endforeach
            </ul>
            <ul class="cinta-pista" aria-hidden="true">
                @foreach ($municipios as $municipio)
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-pin"/></svg>{{ $municipio }}</li>
                @endforeach
            </ul>
        </div>

        <div class="section-inner ini-convenios-grid" data-revelar="escalonar">
            <div class="convenio convenio-alcaldias">
                <h3>Alcaldías municipales</h3>
                <p class="convenio-dato">{{ count($municipios) }} municipios aliados</p>
                <p>Cada alcaldía respalda espacios de práctica, convocatorias y proyección social para nuestros estudiantes en su municipio.</p>
            </div>
            <div class="convenio">
                <h3>Entidades públicas y privadas</h3>
                <ul>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-scale"/></svg>Fiscalía y CTI de Villeta</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-shield"/></svg>INPEC — Honda, Norcasia y Puerto Triunfo</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-building"/></svg>FTI Bogotá</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-salud"/></svg>Todo en Salud a la Mano S.A.S.</li>
                </ul>
            </div>
            <div class="convenio">
                <h3>Otras alianzas</h3>
                <ul>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-vet"/></svg>Clínicas veterinarias</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-leaf"/></svg>Granjas especializadas</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-building"/></svg>Empresas de obras civiles</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-palette"/></svg>Centros artísticos</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-cert"/></svg>Universidades</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- ============ PRÓXIMAS CONVOCATORIAS ============ --}}
    <section class="ini-convocatorias" id="convocatorias">
        <div class="section-inner">
            <div class="section-head es-dividida" data-revelar>
                <h2>Próximas convocatorias</h2>
                <p>Cupos abiertos — asegura el tuyo antes del cierre de inscripciones.</p>
            </div>

            <ul class="convocatorias" data-revelar="escalonar">
                @foreach ([
                    ['Salud', 'ico-salud', 'Curso de Actualización en Atención al Paciente', 'Para personal asistencial que busca renovar su certificación.'],
                    ['Salud', 'ico-salud', 'Curso Obligatorio Salud Ocupacional', 'Requisito legal para operar en entornos laborales regulados.'],
                    ['Administrativo', 'ico-admin', 'Programa Técnico Laboral en el Área Administrativa', 'Formación integral en gestión y procesos de oficina.'],
                    ['Continuada', 'ico-cert', 'Educación Continuada en Normativa y Procedimientos', 'Actualización para profesionales en ejercicio.'],
                ] as [$etiqueta, $icono, $titulo, $texto])
                    <li class="convocatoria">
                        <span class="convocatoria-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $icono }}"/></svg></span>
                        <div class="convocatoria-texto">
                            <h3>{{ $titulo }}</h3>
                            <p class="convocatoria-categoria">{{ $etiqueta }}</p>
                            <p>{{ $texto }}</p>
                        </div>
                        <a href="{{ $wa($titulo) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                            Ver más
                            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-arrow"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ============ NOVEDADES ACADÉMICAS ============
         Pendiente con ESAT: el formulario no guarda el correo en ningún lado
         (solo muestra la confirmación). Decidir si se conecta o se cambia. --}}
    <section class="ini-novedades" id="novedades">
        <div class="section-inner">
            <div class="ini-novedades-caja" data-revelar>
                <div class="ini-novedades-texto">
                    <span class="ini-novedades-icono"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-mail"/></svg></span>
                    <div>
                        <h2>Novedades académicas</h2>
                        <p>Recibe convocatorias, fechas de apertura y noticias de las siete escuelas directamente en tu correo.</p>
                    </div>
                </div>
                <div class="ini-novedades-accion">
                    <form class="ini-novedades-form" id="newsletterForm" novalidate>
                        <label for="newsletterEmail" class="sr-only">Correo electrónico</label>
                        <input type="email" id="newsletterEmail" name="email" placeholder="tucorreo@ejemplo.com" required autocomplete="email">
                        <button type="submit" class="btn btn-accent">Suscribirme</button>
                    </form>
                    <p class="ini-novedades-nota" id="newsletterNote" role="status" aria-live="polite"></p>
                </div>
            </div>
        </div>
    </section>
@endsection
