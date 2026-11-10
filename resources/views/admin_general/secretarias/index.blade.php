{{-- Secretarías (Admin de ESAT general). Datos reales: SecretariaController@index los pasa en window.GEN_DATOS.
     Cuentas rol admin de cada sede: crear/editar (SecretariaRequest), activar/desactivar y restablecer la clave
     (vuelve al número de documento y cierra sus sesiones). Todo queda en la auditoría. secretarias.js escapa todo. --}}
@extends('admin_general.layout')

@section('title', 'Secretarías')
@section('active', 'secretarias')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/secretarias.css') }}?v={{ filemtime(public_path('assets/css/admin_general/secretarias.css')) }}">
@endpush

@section('content')
<div class="sec-wrap app-anim">

    <div class="sec-header">
        <div>
            <h1 class="sec-title">Secretarías</h1>
            <p class="sec-sub">Las cuentas de secretaría de cada sede: quién maneja matrículas, pagos y grupos. Crea una cuenta, corrige sus datos, desactívala si la persona ya no trabaja en ESAT o restablece su clave.</p>
        </div>
        <button type="button" class="sec-btn sec-btn-primary" id="sec-nueva">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva secretaría
        </button>
    </div>

    <div class="sec-kpis" id="sec-kpis"></div>

    <div class="sec-filters">
        <div class="sec-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="sec-buscar" placeholder="Buscar por nombre, documento o correo..." autocomplete="off" aria-label="Buscar secretaría">
        </div>
        @foreach ([['sec-cb-sede', 'Todas las sedes'], ['sec-cb-estado', 'Todos los estados']] as [$id, $texto])
        <div class="app-combobox" id="{{ $id }}" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="{{ $texto }}">
                <span data-combobox-label>{{ $texto }}</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
            <input type="hidden" data-combobox-value>
        </div>
        @endforeach
        <span class="sec-count" id="sec-count"></span>
    </div>

    <div class="sec-table-wrap">
        <table class="sec-table">
            <thead>
                <tr>
                    <th scope="col">Secretaría</th>
                    <th scope="col">Sede</th>
                    <th scope="col">Contacto</th>
                    <th scope="col">Último ingreso</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="sec-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="sec-body"></tbody>
        </table>
        <p class="sec-empty" id="sec-empty" hidden>No hay cuentas que coincidan con la búsqueda.</p>
    </div>

    <nav class="sec-pagination" id="sec-paginacion" aria-label="Páginas" hidden></nav>
</div>

{{-- ─── Modal: crear / editar (3 pasos) ─── --}}
<div class="sec-modal" id="sec-modal" hidden>
    <div class="sec-modal-overlay" data-cerrar-modal></div>
    <form class="sec-modal-panel" id="sec-form" role="dialog" aria-modal="true" aria-labelledby="sec-modal-titulo" novalidate>
        <div class="sec-modal-head">
            <div>
                <h2 class="sec-modal-title" id="sec-modal-titulo">Nueva secretaría</h2>
                <p class="sec-modal-sub" id="sec-modal-sub">Paso 1 de 3</p>
            </div>
            <button type="button" class="sec-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sec-steps" aria-hidden="true">
            <div class="sec-step" data-step="1"><div class="sec-step-dot">1</div><span>Datos personales</span></div>
            <div class="sec-step-line"></div>
            <div class="sec-step" data-step="2"><div class="sec-step-dot">2</div><span>Identificación y contacto</span></div>
            <div class="sec-step-line"></div>
            <div class="sec-step" data-step="3"><div class="sec-step-dot">3</div><span>Sede y confirmación</span></div>
        </div>
        <div class="sec-modal-body">
            <div class="sec-paso" data-paso="1">
                <div class="sec-fields">
                    <div class="sec-field">
                        <label for="sec-f-nombres">Nombres</label>
                        <input type="text" id="sec-f-nombres" maxlength="60" placeholder="Ej. Andrea" autocomplete="off">
                        <span class="sec-field-error" data-error="nombres" hidden></span>
                    </div>
                    <div class="sec-field">
                        <label for="sec-f-apellidos">Apellidos</label>
                        <input type="text" id="sec-f-apellidos" maxlength="60" placeholder="Ej. Ramírez Soto" autocomplete="off">
                        <span class="sec-field-error" data-error="apellidos" hidden></span>
                    </div>
                    <div class="sec-field">
                        <label for="sec-f-genero">Género</label>
                        <select id="sec-f-genero">
                            <option value="">Selecciona</option>
                            <option value="femenino">Femenino</option>
                            <option value="masculino">Masculino</option>
                            <option value="otro">Otro</option>
                        </select>
                        <span class="sec-field-error" data-error="genero" hidden></span>
                    </div>
                    <div class="sec-field">
                        <label for="sec-f-nacimiento">Fecha de nacimiento</label>
                        <input type="date" id="sec-f-nacimiento">
                        <span class="sec-hint" id="sec-edad"></span>
                        <span class="sec-field-error" data-error="nacimiento" hidden></span>
                    </div>
                </div>
            </div>
            <div class="sec-paso" data-paso="2" hidden>
                <div class="sec-fields">
                    <div class="sec-field">
                        <label for="sec-f-tipo">Tipo de documento</label>
                        <select id="sec-f-tipo">
                            <option value="CC">Cédula de ciudadanía</option>
                            <option value="CE">Cédula de extranjería</option>
                            <option value="PA">Pasaporte</option>
                            <option value="PPT">Permiso por protección temporal</option>
                        </select>
                    </div>
                    <div class="sec-field">
                        <label for="sec-f-documento">Número de documento</label>
                        <input type="text" id="sec-f-documento" inputmode="numeric" maxlength="12" placeholder="Solo números" autocomplete="off">
                        <span class="sec-field-error" data-error="documento" hidden></span>
                    </div>
                    <div class="sec-field">
                        <label for="sec-f-telefono">Celular</label>
                        <input type="tel" id="sec-f-telefono" inputmode="numeric" maxlength="14" placeholder="3001234567" autocomplete="off">
                        <span class="sec-field-error" data-error="telefono" hidden></span>
                    </div>
                    <div class="sec-field">
                        <label for="sec-f-correo">Correo (es su usuario)</label>
                        <input type="email" id="sec-f-correo" maxlength="120" placeholder="nombre@esat.edu.co" autocomplete="off">
                        <span class="sec-field-error" data-error="correo" hidden></span>
                    </div>
                </div>
            </div>
            <div class="sec-paso" data-paso="3" hidden>
                <div class="sec-fields">
                    <div class="sec-field sec-field-full">
                        <span class="sec-label" id="sec-l-sede">Sede donde trabaja</span>
                        <div class="app-combobox" id="sec-cb-form-sede">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger aria-labelledby="sec-l-sede">
                                <span data-combobox-label>Selecciona una sede</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>
                            <input type="hidden" data-combobox-value>
                        </div>
                        <span class="sec-field-error" data-error="sede" hidden></span>
                    </div>
                </div>
                <div class="sec-resumen" id="sec-resumen"></div>
                <p class="sec-nota" id="sec-nota-clave">La clave inicial es su número de documento. Al entrar por primera vez, el sistema le pedirá cambiarla.</p>
            </div>
        </div>
        <div class="sec-modal-foot sec-modal-foot-split">
            <button type="button" class="sec-btn sec-btn-ghost" id="sec-restablecer" hidden>Restablecer clave</button>
            <div>
                <button type="button" class="sec-btn sec-btn-ghost" id="sec-atras">Atrás</button>
                <button type="submit" class="sec-btn sec-btn-primary" id="sec-siguiente">Siguiente</button>
            </div>
        </div>
    </form>
</div>

{{-- ─── Modal: confirmar ─── --}}
<div class="sec-modal" id="sec-confirmar" hidden>
    <div class="sec-modal-overlay" data-cerrar-modal></div>
    <div class="sec-modal-panel sec-modal-sm" role="alertdialog" aria-modal="true" aria-labelledby="sec-conf-titulo" aria-describedby="sec-conf-texto">
        <div class="sec-modal-head">
            <h2 class="sec-modal-title" id="sec-conf-titulo"></h2>
            <button type="button" class="sec-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sec-modal-body"><p class="sec-modal-text" id="sec-conf-texto"></p><p class="sec-nota" id="sec-conf-aviso" hidden></p></div>
        <div class="sec-modal-foot">
            <button type="button" class="sec-btn sec-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="button" class="sec-btn sec-btn-primary" id="sec-conf-ok"></button>
        </div>
    </div>
</div>

<div class="sec-toast" id="sec-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script>window.GEN_DATOS = @json($datos);</script>
    <script src="{{ asset('assets/js/admin_general/secretarias.js') }}?v={{ filemtime(public_path('assets/js/admin_general/secretarias.js')) }}" defer></script>
@endpush
