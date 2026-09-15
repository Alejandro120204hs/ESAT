<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel Superadmin') · ESAT</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/superadmin.css') }}">
</head>
<body class="app-page">
    @include('partials.icon-sprite')

    <div class="app-shell">
        <span class="app-sidebar-backdrop" aria-hidden="true"></span>

        @include('superadmin.partials.sidebar', ['active' => trim($__env->yieldContent('active'))])

        <!-- ============ CONTENIDO ============ -->
        <div class="app-main">
            @include('superadmin.partials.topbar', ['title' => trim($__env->yieldContent('page-title')) ?: 'Panel Superadmin'])

            <main class="app-content {{ trim($__env->yieldContent('content-class')) }}">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="{{ asset('assets/js/superadmin/superadmin.js') }}" defer></script>
</body>
</html>
