{{-- Plantilla de los documentos imprimibles de Matrículas (recibo, comprobante de devolución,
     constancia de matrícula y paz y salvo): encabezado con los datos legales reales de ESAT (NIT,
     licencia) y la sede desde la BD; cada documento pone su título, su número y su contenido. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — ESAT</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin/documento.css') }}?v={{ filemtime(public_path('assets/css/admin/documento.css')) }}">
    <script src="{{ asset('assets/js/admin/documento.js') }}?v={{ filemtime(public_path('assets/js/admin/documento.js')) }}" defer></script>
</head>
<body>

<div class="rc-acciones">
    <button type="button" class="rc-btn" id="rc-imprimir">
        <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir o guardar en PDF
    </button>
</div>

<main class="rc-hoja @yield('hoja-clase')">
    @yield('sello')

    <header class="rc-cabecera">
        <div class="rc-marca">
            <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT">
            <div>
                <strong>Escuela Nacional de Educación ESAT</strong>
                <span>NIT 900820764-1 · Lic. Funcionamiento 5850</span>
                @if ($m->sede)
                    <span>Sede {{ $m->sede->nombre }}@if ($m->sede->direccion) · {{ $m->sede->direccion }}@endif @if ($m->sede->ciudad) · {{ $m->sede->ciudad }}@endif</span>
                @endif
            </div>
        </div>
        <div class="rc-numero">
            @yield('numero')
        </div>
    </header>

    @yield('contenido')
</main>
</body>
</html>
