<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ESAT') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-navy-3 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#EEF2F7]">
            <div>
                <a href="{{ route('home') }}">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT" class="h-14 w-auto">
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-sm overflow-hidden sm:rounded-2xl border border-navy/10">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
