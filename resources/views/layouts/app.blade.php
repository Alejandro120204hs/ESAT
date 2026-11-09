<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'ESAT · Escuela Nacional de Educación — Formación Técnica Certificada')</title>
    <meta name="description" content="@yield('meta_description', 'ESAT forma técnicos laborales certificados en salud, cocina y turismo, áreas administrativas, deporte, ciencias, idiomas y belleza. Sedes en Bogotá, Sasaima, Guaduas y La Dorada.')">
    <meta name="theme-color" content="#10284A">

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ $recurso('assets/css/sitio/base.css') }}">
    @stack('styles')
</head>
<body>
<!--
THESIS: ESAT como institución verificable: bloques limpios sobre blanco, las fotos de ESAT en marcos redondeados y cifras contadas de sus datos que suben con el scroll. Rechaza el hero oscuro a pantalla completa con texto centrado.
OWN-WORLD: blanco con franjas azul claro (#F2F5FA); el azul marino #10284A ocupa bandas enteras (cifras, nuevas carreras, cierre y footer); naranja #D97B2E = acción y avance: CTA en píldora, palabra resaltada, línea de progreso y espina que se llenan con el scroll; verde solo para WhatsApp; íconos y estados en azul; radios de 24-32 px; Archivo 800 + Public Sans.
STORY: el visitante ve que ESAT tiene licencia, 7 escuelas y 4 sedes; lo cree por la licencia, los convenios y las sedes con dirección; actúa con "Ver programas" o escribiendo por WhatsApp.
FIRST VIEWPORT: barra de servicio con la licencia; cabecera blanca; foto redondeada de ESAT (~70 % del alto) con el titular abajo a la izquierda sobre velo azul y dos CTA; tarjeta azul de cifras montada sobre su borde inferior. En celular la foto va arriba, entera, y el titular y los CTA debajo sobre azul.
FORM: fijada por el usuario, sin sorteo de concepto: "que se tome de referencia a la web de fundetec https://fundetec.edu.co/ obviamente respetando el contenido actual de nuestra landing", con la respuesta "Paleta de ESAT" y efectos de scroll "Medios". Movimiento: Lenis + GSAP, entradas expo.out, parallax, contadores y pista horizontal de nuevas carreras.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
-->
    <a href="#contenido" class="saltar">Saltar al contenido</a>

    @include('partials.icon-sprite')

    @include('partials.header')

    <main id="contenido" class="contenido-sitio">
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/ScrollTrigger.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lenis@1.3.4/dist/lenis.min.js"></script>
    <script src="{{ $recurso('assets/js/sitio/motor.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
