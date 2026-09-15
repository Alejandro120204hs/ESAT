<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · ESAT</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/login/login.css') }}">
</head>
<body class="login-page">
    @include('partials.icon-sprite')

    <div class="login-shell">

        <!-- ============ PANEL VISUAL ============ -->
        <aside class="login-visual">
            <div class="login-visual-brand">
                <a href="{{ route('home') }}">
                    <img src="{{ asset('assets/img/logo-negativo.png') }}" alt="ESAT">
                </a>
            </div>

            <div class="login-visual-photo">
                <img src="{{ asset('assets/img/favicon.png') }}" class="login-visual-watermark" alt="" aria-hidden="true">
                <img src="{{ asset('assets/img/login.png') }}" class="login-visual-photo-img" alt="Estudiante ESAT">
            </div>

            <p class="login-visual-copy">© {{ date('Y') }} Escuela Nacional de Educación ESAT. Todos los derechos reservados.</p>
        </aside>

        <!-- ============ FORMULARIO ============ -->
        <main class="login-form-panel">
            <div class="login-form-wrap">
                <div class="login-form-card">
                    <div class="login-form-mobile-brand">
                        <a href="{{ route('home') }}">
                            <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT">
                        </a>
                    </div>

                    <a href="{{ route('home') }}" class="login-back" aria-label="Volver al sitio">
                        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                    </a>

                    <h1>Iniciar sesión</h1>
                    <p>Accede a tu cuenta de ESAT.</p>

                    @if (session('status'))
                        <div class="login-status">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="login-field">
                            <label for="email">Correo electrónico</label>
                            <div class="login-field-input-wrap">
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="tu@correo.com">
                            </div>
                            @error('email')
                                <p class="login-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="login-field">
                            <label for="password">Contraseña</label>
                            <div class="login-field-input-wrap">
                                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                                <button type="button" class="login-toggle-password" aria-label="Mostrar contraseña">
                                    <svg class="icon-on" viewBox="0 0 24 24"><path d="M1.5 12s4-7.5 10.5-7.5S22.5 12 22.5 12s-4 7.5-10.5 7.5S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-off" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.24 4.24M9.17 5.32A10.9 10.9 0 0 1 12 4.5c6.5 0 10.5 7.5 10.5 7.5a13.4 13.4 0 0 1-3.1 4.02M6.6 6.6C3.6 8.3 1.5 12 1.5 12s4 7.5 10.5 7.5a10.7 10.7 0 0 0 4.24-.86"/></svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="login-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="login-row">
                            <label class="login-remember">
                                <input type="checkbox" name="remember">
                                Recordarme
                            </label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                            @endif
                        </div>

                        <button type="submit" class="login-submit">Iniciar sesión</button>
                    </form>
                </div>

                <div class="login-form-footer">
                    <div class="login-form-social">
                        <a href="#" class="social-fb" aria-label="Facebook"><svg viewBox="0 0 24 24"><use href="#ico-fb"/></svg></a>
                        <a href="#" class="social-x" aria-label="Twitter / X"><svg viewBox="0 0 24 24"><use href="#ico-x"/></svg></a>
                        <a href="#" class="social-ig" aria-label="Instagram"><svg viewBox="0 0 24 24"><use href="#ico-ig"/></svg></a>
                        <a href="#" class="social-yt" aria-label="YouTube"><svg viewBox="0 0 24 24"><use href="#ico-yt"/></svg></a>
                    </div>
                    <div class="login-form-contact">
                        <a href="{{ $wa('información general sobre ESAT') }}" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24"><path d="M4.5 3h3.6l1.6 4.5-2.2 1.6a12.5 12.5 0 0 0 5.9 5.9l1.6-2.2 4.5 1.6v3.6a2 2 0 0 1-2.2 2C9.6 19.6 4.4 14.4 3 6.7a3 3 0 0 1-.1-.7A2 2 0 0 1 4.5 3Z"/></svg>
                            315 295 75 88
                        </a>
                        <a href="{{ $wa('información general sobre ESAT', '573123773144') }}" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24"><path d="M4.5 3h3.6l1.6 4.5-2.2 1.6a12.5 12.5 0 0 0 5.9 5.9l1.6-2.2 4.5 1.6v3.6a2 2 0 0 1-2.2 2C9.6 19.6 4.4 14.4 3 6.7a3 3 0 0 1-.1-.7A2 2 0 0 1 4.5 3Z"/></svg>
                            312 377 31 44
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="{{ asset('assets/js/login/login.js') }}" defer></script>
</body>
</html>
