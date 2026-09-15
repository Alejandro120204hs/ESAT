@extends('superadmin.layout')

@section('title', 'Mi perfil')
@section('page-title', 'Mi perfil')
@section('content-class', 'app-content-wide')

@section('content')
    <div class="app-profile-hero">
        <span class="app-profile-hero-avatar">{{ strtoupper(substr($user->nombres ?? $user->email, 0, 1)) }}</span>
        <div class="app-profile-hero-text">
            <h2>{{ $user->name }}</h2>
            <span class="app-profile-hero-role">{{ $user->role->label() }}</span>
            <p class="app-profile-hero-meta">{{ $user->email }} @if($user->lugar_nacimiento) · {{ $user->lugar_nacimiento }} @endif</p>
        </div>
    </div>

    @if (session('status') === 'profile-updated')
        <div class="app-alert app-alert-success">Tus datos se guardaron correctamente.</div>
    @endif

    <div class="app-profile-grid">
        <aside class="app-profile-side">
            <div class="app-panel app-identity-panel">
                <h3>Identidad</h3>
                <p class="app-identity-note">Fijada al crear la cuenta.</p>

                <dl class="app-identity-list">
                    <div>
                        <dt>Documento</dt>
                        <dd>{{ $user->tipo_documento }} {{ $user->numero_documento }}</dd>
                    </div>
                    <div>
                        <dt>Nacimiento</dt>
                        <dd>{{ $user->fecha_nacimiento?->translatedFormat('d \d\e F \d\e Y') }}</dd>
                    </div>
                    <div>
                        <dt>Lugar de nacimiento</dt>
                        <dd>{{ $user->lugar_nacimiento }}</dd>
                    </div>
                    <div>
                        <dt>Género</dt>
                        <dd>{{ ucfirst($user->genero ?? '') }}</dd>
                    </div>
                </dl>
            </div>
        </aside>

        <div class="app-profile-main">
            <div class="app-panel app-form-panel">
                <div class="app-form-header">
                    <h3>Información de contacto</h3>
                    <p>Nombre, correo y datos de contacto.</p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="app-form">
                    @csrf
                    @method('PATCH')

                    <div class="app-form-grid">
                        <div class="app-field">
                            <label for="nombres">Nombres</label>
                            <input id="nombres" name="nombres" type="text" value="{{ old('nombres', $user->nombres) }}" required autofocus>
                            @error('nombres') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="app-field">
                            <label for="apellidos">Apellidos</label>
                            <input id="apellidos" name="apellidos" type="text" value="{{ old('apellidos', $user->apellidos) }}" required>
                            @error('apellidos') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="app-field">
                            <label for="email">Correo electrónico</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                            @error('email') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="app-field">
                            <label for="telefono">Teléfono</label>
                            <input id="telefono" name="telefono" type="text" value="{{ old('telefono', $user->telefono) }}">
                            @error('telefono') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="app-field app-field-wide">
                            <label for="direccion">Dirección de residencia</label>
                            <input id="direccion" name="direccion" type="text" value="{{ old('direccion', $user->direccion) }}">
                            @error('direccion') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="app-form-actions">
                        <button type="submit" class="app-btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>

            <div class="app-panel app-form-panel">
                <div class="app-form-header">
                    <h3>Cambiar contraseña</h3>
                    <p>Usa una contraseña larga y que no uses en otros sitios.</p>
                </div>

                <form method="POST" action="{{ route('password.update') }}" class="app-form">
                    @csrf
                    @method('PUT')

                    <div class="app-form-grid">
                        <div class="app-field app-field-wide">
                            <label for="current_password">Contraseña actual</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                            @error('current_password', 'updatePassword') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="app-field">
                            <label for="password">Nueva contraseña</label>
                            <input id="password" name="password" type="password" autocomplete="new-password">
                            @error('password', 'updatePassword') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="app-field">
                            <label for="password_confirmation">Confirmar contraseña</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                            @error('password_confirmation', 'updatePassword') <span class="app-field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="app-form-actions">
                        <button type="submit" class="app-btn-primary">Actualizar contraseña</button>
                        @if (session('status') === 'password-updated')
                            <span class="app-form-saved">Guardado.</span>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
