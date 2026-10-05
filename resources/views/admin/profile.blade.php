@extends('admin.layout')

@section('title', 'Mi perfil')
@section('page-title', 'Mi perfil')
@section('active', 'perfil')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/profile.css') }}?v={{ filemtime(public_path('assets/css/admin/profile.css')) }}">
@endpush

@section('content')

@php
    $ini = mb_strtoupper(
        mb_substr($user->nombres ?? $user->name ?? '', 0, 1) .
        mb_substr($user->apellidos ?? '', 0, 1)
    ) ?: '?';
    $nombreCompleto = trim(($user->nombres ?? $user->name ?? '') . ' ' . ($user->apellidos ?? ''));
@endphp

{{-- ── Banner hero ────────────────────────────────────── --}}
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
                @if($user->sede)
                <span class="prf-chip prf-chip-sede">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                    Sede {{ $user->sede->nombre }}
                </span>
                @endif
                @if($user->numero_documento)
                <span class="prf-chip prf-chip-doc">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h6"/></svg>
                    {{ $user->tipo_documento ?? 'CC' }} {{ $user->numero_documento }}
                </span>
                @endif
                <span class="prf-chip prf-chip-email">
                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    {{ $user->email }}
                </span>
            </div>
        </div>
    </div>
</div>

@if (session('status') === 'profile-updated')
    <div class="prf-alert prf-alert-ok">
        <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
        Tus datos se guardaron correctamente.
    </div>
@endif

{{-- ── Cuerpo: dos columnas ───────────────────────────── --}}
<div class="prf-body">

    {{-- Información personal --}}
    <div class="prf-card">
        <div class="prf-card-head">
            <div class="prf-card-icon prf-card-icon-blue">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-3.5 3.5-6 8-6s8 2.5 8 6"/></svg>
            </div>
            <div>
                <div class="prf-card-title">Información personal</div>
                <div class="prf-card-sub">Nombre, correo y datos de contacto</div>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="prf-form">
            @csrf
            @method('PATCH')

            <div class="prf-row">
                <div class="prf-field">
                    <label for="nombres">Nombres</label>
                    <input id="nombres" name="nombres" type="text"
                           value="{{ old('nombres', $user->nombres) }}" required autofocus>
                    @error('nombres') <span class="prf-err">{{ $message }}</span> @enderror
                </div>
                <div class="prf-field">
                    <label for="apellidos">Apellidos</label>
                    <input id="apellidos" name="apellidos" type="text"
                           value="{{ old('apellidos', $user->apellidos) }}" required>
                    @error('apellidos') <span class="prf-err">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="prf-row">
                <div class="prf-field">
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email"
                           value="{{ old('email', $user->email) }}" required>
                    @error('email') <span class="prf-err">{{ $message }}</span> @enderror
                </div>
                <div class="prf-field">
                    <label for="telefono">Teléfono</label>
                    <input id="telefono" name="telefono" type="text"
                           value="{{ old('telefono', $user->telefono) }}"
                           placeholder="Ej: 3001234567">
                    @error('telefono') <span class="prf-err">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="prf-field">
                <label for="direccion">Dirección de residencia</label>
                <input id="direccion" name="direccion" type="text"
                       value="{{ old('direccion', $user->direccion) }}"
                       placeholder="Calle, carrera, barrio...">
                @error('direccion') <span class="prf-err">{{ $message }}</span> @enderror
            </div>

            <div class="prf-actions">
                <button type="submit" class="prf-btn">
                    <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Guardar cambios
                </button>
            </div>
        </form>
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
                    <input id="current_password" name="current_password" type="password"
                           autocomplete="current-password" placeholder="••••••••">
                </div>
                @error('current_password', 'updatePassword') <span class="prf-err">{{ $message }}</span> @enderror
            </div>

            <div class="prf-field">
                <label for="password">Nueva contraseña</label>
                <div class="prf-input-wrap">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <input id="password" name="password" type="password"
                           autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                </div>
                @error('password', 'updatePassword') <span class="prf-err">{{ $message }}</span> @enderror
            </div>

            <div class="prf-field">
                <label for="password_confirmation">Confirmar contraseña</label>
                <div class="prf-input-wrap">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <input id="password_confirmation" name="password_confirmation" type="password"
                           autocomplete="new-password" placeholder="Repite la nueva contraseña">
                </div>
                @error('password_confirmation', 'updatePassword') <span class="prf-err">{{ $message }}</span> @enderror
            </div>

            <div class="prf-tip">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Usa letras, números y símbolos. No reutilices contraseñas de otros servicios.
            </div>

            <div class="prf-actions">
                <button type="submit" class="prf-btn prf-btn-outline">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Actualizar contraseña
                </button>
                @if (session('status') === 'password-updated')
                    <span class="prf-saved">
                        <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                        Guardado
                    </span>
                @endif
            </div>
        </form>
    </div>

</div>
@endsection
