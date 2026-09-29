@extends('superadmin.layout')

@section('title', 'Configuración')
@section('page-title', 'Configuración')
@section('active', 'configuracion')
@section('content-class', 'app-cfg-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin/configuracion.css') }}">
@endpush

@section('content')
    {{--
        Vista previa de interfaz — solo frontend, sin conexión a base de datos.
        Nombre y NIT son datos reales de ESAT (Lic. 5850, NIT 900820764-1).
        Teléfonos, correo y dirección son ficticios (datos de ejemplo para previsualizar).
        Los valores de seguridad son defaults sugeridos, no reflejan configuración
        real del sistema todavía — falta backend completo.
    --}}

    <div class="cfg-inner">

    <p class="app-page-intro">Configura los datos generales de la institución y los parámetros de seguridad del sistema.</p>

    <!-- Pestañas -->
    <div class="cfg-tabs" role="tablist" aria-label="Secciones de configuración">
        <button type="button" class="cfg-tab is-active" data-tab="institucion"
                role="tab" aria-selected="true" aria-controls="tab-institucion">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Institución
        </button>
        <button type="button" class="cfg-tab" data-tab="seguridad"
                role="tab" aria-selected="false" aria-controls="tab-seguridad">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
            Seguridad
        </button>
        <button type="button" class="cfg-tab" data-tab="bloqueados"
                role="tab" aria-selected="false" aria-controls="tab-bloqueados">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Accesos bloqueados
            <span class="cfg-tab-badge" id="cfg-bloqueados-badge">3</span>
        </button>
    </div>

    <!-- ========== TAB: INSTITUCIÓN ========== -->
    <div class="cfg-panel" id="tab-institucion" role="tabpanel">

        <!-- Identidad legal -->
        <div class="cfg-card">
            <p class="app-section-label">Identidad legal</p>
            <div class="cfg-grid">
                <div class="cfg-field cfg-field-full">
                    <label class="cfg-label" for="cfg-nombre">Nombre / razón social</label>
                    <input type="text" id="cfg-nombre" class="cfg-input"
                           value="Escuela Nacional de Educación ESAT">
                </div>
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-nit">NIT</label>
                    <input type="text" id="cfg-nit" class="cfg-input" value="900820764-1">
                </div>
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-lic">Licencia de funcionamiento</label>
                    <input type="text" id="cfg-lic" class="cfg-input" value="5850">
                </div>
            </div>
        </div>

        <!-- Logo del sistema -->
        <div class="cfg-card cfg-card-logo">
            <div class="cfg-logo-area">
                <div class="cfg-logo-preview">
                    <div class="cfg-logo-preview-inner">
                        <img id="cfg-logo-img" src="{{ asset('assets/img/logo-negativo.png') }}" alt="Logo ESAT">
                    </div>
                    <span class="cfg-logo-preview-label">Vista previa</span>
                </div>
                <div class="cfg-logo-meta">
                    <p class="app-section-label cfg-logo-section-label">Logo del sistema</p>
                    <div class="cfg-logo-file-row">
                        <div class="cfg-logo-file-icon">
                            <svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><polyline points="13 2 13 9 20 9"/></svg>
                        </div>
                        <div>
                            <p class="cfg-logo-filename" id="cfg-logo-filename">logo-negativo.png</p>
                            <p class="cfg-logo-subtext">Imagen actual del sistema</p>
                        </div>
                    </div>
                    <p class="cfg-logo-hint">Formatos admitidos: PNG · SVG · Tamaño máximo: 512 KB<br>Se recomienda fondo transparente. El logo se muestra en el sidebar del panel sobre fondo oscuro.</p>
                    <div class="cfg-logo-actions">
                        <button type="button" class="cfg-upload-btn" id="cfg-logo-trigger">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Cambiar logo
                        </button>
                    </div>
                    <input type="file" id="cfg-logo-input" accept="image/png,image/svg+xml"
                           class="cfg-file-input" tabindex="-1" aria-labelledby="cfg-logo-trigger">
                </div>
            </div>
        </div>

        <!-- Contacto institucional -->
        <div class="cfg-card">
            <p class="app-section-label">Contacto institucional</p>
            <div class="cfg-grid">
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-tel1">Teléfono principal</label>
                    <div class="cfg-input-icon-wrap">
                        <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.8 19.8 0 0 1 1.61 3.38 2 2 0 0 1 3.6 1.2h3a2 2 0 0 1 2 1.72c.13 1 .38 1.97.74 2.91a2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6.08 6.08l1.06-1.06a2 2 0 0 1 2.11-.45c.94.36 1.91.61 2.91.74A2 2 0 0 1 22 16.92Z"/></svg>
                        <input type="tel" id="cfg-tel1" class="cfg-input cfg-input-icon" value="601 234 5678">
                    </div>
                </div>
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-tel2">Teléfono secundario</label>
                    <div class="cfg-input-icon-wrap">
                        <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.8 19.8 0 0 1 1.61 3.38 2 2 0 0 1 3.6 1.2h3a2 2 0 0 1 2 1.72c.13 1 .38 1.97.74 2.91a2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6.08 6.08l1.06-1.06a2 2 0 0 1 2.11-.45c.94.36 1.91.61 2.91.74A2 2 0 0 1 22 16.92Z"/></svg>
                        <input type="tel" id="cfg-tel2" class="cfg-input cfg-input-icon" value="314 567 8901">
                    </div>
                </div>
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-email">Correo institucional</label>
                    <div class="cfg-input-icon-wrap">
                        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/></svg>
                        <input type="email" id="cfg-email" class="cfg-input cfg-input-icon" value="info@esatcolombia.edu.co">
                    </div>
                </div>
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-dir">Dirección sede principal</label>
                    <div class="cfg-input-icon-wrap">
                        <svg viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        <input type="text" id="cfg-dir" class="cfg-input cfg-input-icon" value="Calle 72 # 11-35, Bogotá D.C.">
                    </div>
                </div>
            </div>
        </div>

        <div class="cfg-footer">
            <button type="button" class="cfg-btn-primary" data-save="institucion">Guardar cambios</button>
        </div>
    </div>

    <!-- ========== TAB: SEGURIDAD ========== -->
    <div class="cfg-panel" id="tab-seguridad" role="tabpanel" hidden>

        <!-- Contraseñas -->
        <div class="cfg-card">
            <p class="app-section-label">Contraseñas</p>
            <div class="cfg-grid cfg-grid-single">
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-pwd-len">Longitud mínima</label>
                    <p class="cfg-field-hint">Cantidad mínima de caracteres para cualquier contraseña del sistema. La contraseña inicial de cada usuario es su número de documento, que suele tener 8 o más dígitos.</p>
                    <div class="cfg-stepper-row">
                        <button type="button" class="cfg-step-btn" data-step="-1" data-target="cfg-pwd-len" aria-label="Disminuir">−</button>
                        <input type="number" id="cfg-pwd-len" class="cfg-input cfg-input-num" value="8" min="6" max="32" aria-label="Longitud mínima de contraseña">
                        <button type="button" class="cfg-step-btn" data-step="1" data-target="cfg-pwd-len" aria-label="Aumentar">+</button>
                        <span class="cfg-stepper-unit">caracteres</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sesiones -->
        <div class="cfg-card">
            <p class="app-section-label">Sesiones activas</p>
            <div class="cfg-grid">
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-inactividad">Tiempo de inactividad</label>
                    <p class="cfg-field-hint">El sistema cierra la sesión automáticamente si el usuario lleva este tiempo sin actividad.</p>
                    <div class="cfg-stepper-row">
                        <button type="button" class="cfg-step-btn" data-step="-5" data-target="cfg-inactividad" aria-label="Disminuir">−</button>
                        <input type="number" id="cfg-inactividad" class="cfg-input cfg-input-num" value="30" min="5" max="480" aria-label="Minutos de inactividad">
                        <button type="button" class="cfg-step-btn" data-step="5" data-target="cfg-inactividad" aria-label="Aumentar">+</button>
                        <span class="cfg-stepper-unit">minutos</span>
                    </div>
                </div>
                <div class="cfg-field">
                    <label class="cfg-label" for="cfg-intentos">Intentos fallidos de acceso</label>
                    <p class="cfg-field-hint">Bloquea la cuenta tras este número de intentos de inicio de sesión fallidos consecutivos.</p>
                    <div class="cfg-stepper-row">
                        <button type="button" class="cfg-step-btn" data-step="-1" data-target="cfg-intentos" aria-label="Disminuir">−</button>
                        <input type="number" id="cfg-intentos" class="cfg-input cfg-input-num" value="5" min="2" max="20" aria-label="Intentos fallidos">
                        <button type="button" class="cfg-step-btn" data-step="1" data-target="cfg-intentos" aria-label="Aumentar">+</button>
                        <span class="cfg-stepper-unit">intentos</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="cfg-footer">
            <button type="button" class="cfg-btn-primary" data-save="seguridad">Guardar cambios</button>
        </div>
    </div>

    <!-- ========== TAB: ACCESOS BLOQUEADOS ========== -->
    <div class="cfg-panel" id="tab-bloqueados" role="tabpanel" hidden>
        @php
        $bloqueados = [
            [
                'nombre'  => 'Valentina Ríos Mora',
                'rol'     => 'Estudiante',
                'sede'    => 'Bogotá',
                'motivo'  => 'Intentos fallidos',
                'detalle' => '5 intentos incorrectos seguidos',
                'fecha'   => '2026-09-28 09:14',
            ],
            [
                'nombre'  => 'Juan Camilo Herrera',
                'rol'     => 'Docente',
                'sede'    => 'Guaduas',
                'motivo'  => 'Inactividad',
                'detalle' => 'Sin actividad por más de 60 días',
                'fecha'   => '2026-09-25 17:02',
            ],
            [
                'nombre'  => 'Paola Suárez Castro',
                'rol'     => 'Estudiante',
                'sede'    => 'Sasaima',
                'motivo'  => 'Intentos fallidos',
                'detalle' => '5 intentos incorrectos seguidos',
                'fecha'   => '2026-09-29 08:47',
            ],
        ];
        @endphp

        <div class="cfg-card cfg-card-bloqueados">

            <!-- Encabezado -->
            <div class="cfg-bloq-header">
                <div>
                    <p class="app-section-label cfg-bloq-section-label">Accesos bloqueados</p>
                    <p class="cfg-bloq-subtitle">Usuarios que no pueden iniciar sesión. Puedes desbloquearlos individualmente.</p>
                </div>
            </div>

            <!-- Estado vacío (oculto mientras haya filas) -->
            <div class="cfg-bloq-empty" id="cfg-bloq-empty" hidden>
                <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                <p>Todo bien — no hay accesos bloqueados en este momento.</p>
            </div>

            <!-- Tabla -->
            <div class="cfg-table-scroll" id="cfg-bloq-table-wrap">
                <table class="cfg-table" id="cfg-bloq-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Sede</th>
                            <th>Motivo</th>
                            <th>Fecha de bloqueo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bloqueados as $b)
                        <tr class="cfg-bloq-row">
                            <td>
                                <span class="cfg-bloq-avatar">{{ strtoupper(substr($b['nombre'], 0, 1)) }}</span>
                                <span class="cfg-bloq-name">{{ $b['nombre'] }}</span>
                            </td>
                            <td><span class="cfg-role-chip cfg-role-{{ strtolower($b['rol']) }}">{{ $b['rol'] }}</span></td>
                            <td class="cfg-bloq-sede">{{ $b['sede'] }}</td>
                            <td>
                                <span class="cfg-motivo-chip cfg-motivo-{{ $b['motivo'] === 'Intentos fallidos' ? 'intentos' : 'inactividad' }}">
                                    {{ $b['motivo'] === 'Intentos fallidos' ? '🔒' : '⏱' }}
                                    {{ $b['motivo'] }}
                                </span>
                                <p class="cfg-bloq-detalle">{{ $b['detalle'] }}</p>
                            </td>
                            <td class="cfg-bloq-fecha">{{ $b['fecha'] }}</td>
                            <td class="cfg-bloq-accion">
                                <button type="button" class="cfg-desbloquear-btn" data-unlock>
                                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                    Desbloquear
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </div>{{-- /.cfg-inner --}}

    <!-- Toast de confirmación (fuera del wrapper para que no herede max-width) -->
    <div class="cfg-toast" id="cfg-toast" hidden role="status" aria-live="polite">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Cambios guardados correctamente
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/superadmin/configuracion.js') }}" defer></script>
@endpush
