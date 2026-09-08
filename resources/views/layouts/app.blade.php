<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'ESAT · Escuela Nacional de Educación — Formación Técnica Certificada')</title>
    <meta name="description" content="@yield('meta_description', 'ESAT forma técnicos laborales certificados en salud, cocina y turismo, áreas administrativas, deporte, ciencias, idiomas y belleza. Sedes en Bogotá, Sasaima, Guaduas y La Dorada.')">

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('assets/css/css_website.css') }}">
</head>
<body>

    @include('partials.icon-sprite')

    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('assets/js/js_website.js') }}" defer></script>
</body>
</html>
