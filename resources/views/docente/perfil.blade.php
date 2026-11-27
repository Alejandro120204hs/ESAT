{{-- Mi perfil del docente (ProfileController@edit). DATOS REALES de la cuenta: el diseño del perfil del Estudiante (el
     mismo de la Secretaría y del Admin), guarda con ProfileController@update (ProfileUpdateRequest) y la clave con
     PasswordController@update. Su nombre, su documento y sus datos en la sede (título, especialidad, escuela y
     vinculación) los registra la secretaría (salen en planillas, certificados y en la nómina): aquí solo se muestran, y
     ProfileUpdateRequest no le deja cambiar el nombre; el docente cambia su correo (con el que ingresa), su celular y su
     dirección. --}}
@extends('docente.layout')

@section('title', 'Mi perfil')
@section('active', 'perfil')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/docente/perfil.css') }}?v={{ filemtime(public_path('assets/css/docente/perfil.css')) }}">
@endpush

@section('content')
@php
    $ini = mb_strtoupper(mb_substr($user->nombres ?? '', 0, 1) . mb_substr($user->apellidos ?? '', 0, 1)) ?: '?';
    $nombreCompleto = trim(($user->nombres ?? '') . ' ' . ($user->apellidos ?? ''));
    $perfil = $user->docentePerfil;
    $vinculacion = $perfil ? (\App\Models\Academico\DocentePerfil::VINCULACIONES[$perfil->vinculacion] ?? null) : null;
@endphp

<div class="prf-hero">
    <div class="prf-hero-bg"></div>
    <img class="prf-hero-mark" src="{{ asset('assets/img/favicon.png') }}" alt="">
    <div class="prf-hero-inner">
        <div class="prf-hero-avatar">{{ $ini }}</div>
        <div class="prf-hero-info">
            <h1 class="prf-hero-name">{{ $nombreCompleto }}</h1>
            <div class="prf-hero-chips">
                <span class="prf-chip prf-chip-role">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-3.5 3.5-6 8-6s8 2.5 8 6"/></svg>
                    {{ $user->role->label() }}
                </span>
                @if ($user->sede)
                <span class="prf-chip prf-chip-sede">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                    Sede {{ $user->sede->nombre }}
                </span>
                @endif
                @if ($user->numero_documento)
                <span class="prf-chip prf-chip-doc">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h6"/></svg>
                    {{ $user->tipo_documento ?? 'CC' }} {{ $user->numero_documento }}
                </span>
                @endif
            </div>
        </div>
    </div>
</div>

@if (session('status') === 'profile-updated')
    <div class="prf-alert prf-alert-ok" role="status">
        <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
        Tus datos se guardaron.
    </div>
@endif

<div class="prf-body">
    <div class="prf-col">
        {{-- Datos de contacto --}}
        <div class="prf-card">
            <div class="prf-card-head">
                <div class="prf-card-icon prf-card-icon-blue">
                    <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div>
                    <div class="prf-card-title">Datos de contacto</div>
                    <div class="prf-card-sub">Por aquí te contacta la sede para avisos de tus grupos y horarios.</div>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="prf-form">
                @csrf
                @method('PATCH')

                <div class="prf-field">
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email"
                           data-actual="{{ $user->email }}"
                           aria-describedby="email-ayuda @error('email') email-err @enderror" @error('email') aria-invalid="true" @enderror>
                    <span class="prf-ayuda" id="email-ayuda">Es tu usuario para ingresar a ESAT.</span>
                    @error('email') <span class="prf-err" id="email-err">{{ $message }}</span> @enderror
                </div>

                {{-- Cambiar el correo (que es el usuario) pide la contraseña actual: una sesión abierta en otro computador no basta --}}
                <div class="prf-field" id="prf-clave-correo" @unless ($errors->has('clave_actual') || old('email', $user->email) !== $user->email) hidden @endunless>
                    <label for="clave_actual">Tu contraseña actual</label>
                    <input id="clave_actual" name="clave_actual" type="password" autocomplete="current-password"
                           aria-describedby="clave-actual-ayuda @error('clave_actual') clave-actual-err @enderror" @error('clave_actual') aria-invalid="true" @enderror>
                    <span class="prf-ayuda" id="clave-actual-ayuda">Para cambiar tu correo, confirma que eres tú.</span>
                    @error('clave_actual') <span class="prf-err" id="clave-actual-err">{{ $message }}</span> @enderror
                </div>

                <div class="prf-row">
                    <div class="prf-field">
                        <label for="telefono">Celular</label>
                        <input id="telefono" name="telefono" type="tel" inputmode="numeric" value="{{ old('telefono', $user->telefono) }}"
                               placeholder="Ej.: 3001234567" autocomplete="tel" @error('telefono') aria-invalid="true" aria-describedby="telefono-err" @enderror>
                        @error('telefono') <span class="prf-err" id="telefono-err">{{ $message }}</span> @enderror
                    </div>
                    <div class="prf-field">
                        <label for="direccion">Dirección de residencia</label>
                        <input id="direccion" name="direccion" type="text" value="{{ old('direccion', $user->direccion) }}"
                               placeholder="Calle, carrera, barrio..." autocomplete="street-address" @error('direccion') aria-invalid="true" aria-describedby="direccion-err" @enderror>
                        @error('direccion') <span class="prf-err" id="direccion-err">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="prf-tip">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    Tu nombre y tu documento salen en las planillas y certificados: si hay que corregirlos, pídelo en la administración de tu sede.
                </div>

                <div class="prf-actions">
                    <button type="submit" class="prf-btn">
                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        {{-- Datos en la sede (consulta) --}}
        <div class="prf-card">
            <div class="prf-card-head">
                <div class="prf-card-icon prf-card-icon-blue">
                    <svg viewBox="0 0 24 24"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
                </div>
                <div>
                    <div class="prf-card-title">Tus datos en la sede</div>
                    <div class="prf-card-sub">Los registra el administrativo de tu sede.</div>
                </div>
            </div>
            <div class="prf-form">
                <dl class="prf-datos">
                    <div><dt>Título profesional</dt><dd>{{ $user->profesion ?: '—' }}</dd></div>
                    <div><dt>Especialidad</dt><dd>{{ $user->titulo_academico ?: '—' }}</dd></div>
                    <div><dt>Escuela</dt><dd>{{ $perfil?->escuela?->nombre ?? '—' }}</dd></div>
                    <div><dt>Vinculación</dt><dd>{{ $vinculacion ?? '—' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Seguridad --}}
    <div class="prf-card">
        <div class="prf-card-head">
            <div class="prf-card-icon prf-card-icon-orange">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div>
                <div class="prf-card-title">Seguridad</div>
                <div class="prf-card-sub">Cambia tu contraseña de acceso</div>
            </div>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="prf-form">
            @csrf
            @method('PUT')

            <div class="prf-field">
                <label for="current_password">Contraseña actual</label>
                <div class="prf-input-wrap">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" placeholder="••••••••"
                           @error('current_password', 'updatePassword') aria-invalid="true" aria-describedby="current-password-err" @enderror>
                </div>
                @error('current_password', 'updatePassword') <span class="prf-err" id="current-password-err">{{ $message }}</span> @enderror
            </div>

            <div class="prf-field">
                <label for="password">Nueva contraseña</label>
                <div class="prf-input-wrap">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres"
                           @error('password', 'updatePassword') aria-invalid="true" aria-describedby="password-err" @enderror>
                </div>
                @error('password', 'updatePassword') <span class="prf-err" id="password-err">{{ $message }}</span> @enderror
            </div>

            <div class="prf-field">
                <label for="password_confirmation">Confirmar contraseña</label>
                <div class="prf-input-wrap">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repite la nueva contraseña"
                           @error('password_confirmation', 'updatePassword') aria-invalid="true" aria-describedby="password-confirmation-err" @enderror>
                </div>
                @error('password_confirmation', 'updatePassword') <span class="prf-err" id="password-confirmation-err">{{ $message }}</span> @enderror
            </div>

            <div class="prf-tip">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Tu primera contraseña es tu número de documento: cámbiala por una que solo tú sepas.
            </div>

            <div class="prf-actions">
                <button type="submit" class="prf-btn prf-btn-outline">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Actualizar contraseña
                </button>
                @if (session('status') === 'password-updated')
                    <span class="prf-saved" role="status">
                        <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                        Guardada
                    </span>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // La contraseña actual se pide solo si cambia el correo (ProfileUpdateRequest)
    (function () {
        var correo = document.getElementById('email'), caja = document.getElementById('prf-clave-correo');
        if (!correo || !caja) return;
        correo.addEventListener('input', function () {
            caja.hidden = correo.value.trim().toLowerCase() === String(correo.dataset.actual).toLowerCase() && !caja.querySelector('.prf-err');
        });
    })();
</script>
@endpush
