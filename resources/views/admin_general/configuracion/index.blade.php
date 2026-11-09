{{-- Configuración (Admin de ESAT general) — SOLO FRONTEND con datos de ejemplo (public/assets/js/admin_general/datos-ejemplo.js).
     Datos de la institución REALES (nombre, NIT, licencia 5850 del 23-09-2019, Secretaría de Educación de Cundinamarca,
     teléfonos del sitio público; el correo no se conoce y queda vacío). Cuota del sistema real ($ 2.000, configuraciones).
     Las cuentas bloqueadas son ficticias. Pasa del Superadmin. Guardar usa GEN_API (solo en la pestaña). --}}
@extends('admin_general.layout')

@section('title', 'Configuración')
@section('active', 'configuracion')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin_general/configuracion.css') }}?v={{ filemtime(public_path('assets/css/admin_general/configuracion.css')) }}">
@endpush

@section('content')
<div class="cfg-wrap app-anim">

    <div class="cfg-header">
        <div>
            <h1 class="cfg-title">Configuración</h1>
            <p class="cfg-sub">Los datos de ESAT que salen en recibos, constancias y reportes; la cuota del sistema que se cobra a cada estudiante, y las reglas de acceso a la plataforma.</p>
        </div>
    </div>

    <div class="cfg-kpis" id="cfg-kpis"></div>

    <div class="cfg-ptabs" role="tablist">
        <button type="button" class="cfg-ptab is-active" role="tab" data-ptab="institucion" aria-selected="true">Institución</button>
        <button type="button" class="cfg-ptab" role="tab" data-ptab="cobros" aria-selected="false">Cobros</button>
        <button type="button" class="cfg-ptab" role="tab" data-ptab="seguridad" aria-selected="false">Seguridad</button>
        <button type="button" class="cfg-ptab" role="tab" data-ptab="bloqueadas" aria-selected="false">Cuentas bloqueadas<span class="cfg-ptab-num" id="cfg-num-bloq"></span></button>
    </div>

    {{-- ─── Institución ─── --}}
    <section class="cfg-ppanel" data-ppanel="institucion" aria-label="Institución">
    <div class="cfg-grid">
    <form class="cfg-card" id="cfg-f-institucion" novalidate>
        <div class="cfg-card-head">
            <div class="cfg-card-icon cfg-card-icon-blue"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg></div>
            <div>
                <h2 class="cfg-card-title">Datos de la institución</h2>
                <p class="cfg-card-sub">Salen en el encabezado de recibos, constancias, paz y salvo y reportes de todas las sedes.</p>
            </div>
        </div>
        <div class="cfg-card-body">
            <div class="cfg-fields">
                <div class="cfg-field cfg-field-full"><label for="cfg-nombre">Nombre de la institución</label><input type="text" id="cfg-nombre" maxlength="120"><span class="cfg-field-error" data-error="nombre" hidden></span></div>
                <div class="cfg-field"><label for="cfg-sigla">Sigla</label><input type="text" id="cfg-sigla" maxlength="15"><span class="cfg-field-error" data-error="sigla" hidden></span></div>
                <div class="cfg-field"><label for="cfg-nit">NIT</label><input type="text" id="cfg-nit" maxlength="15" placeholder="900000000-0"><span class="cfg-hint">Nueve dígitos, guion y el dígito de verificación.</span><span class="cfg-field-error" data-error="nit" hidden></span></div>
                <div class="cfg-field"><label for="cfg-licencia">Licencia de funcionamiento</label><input type="text" id="cfg-licencia" maxlength="20"><span class="cfg-field-error" data-error="licencia" hidden></span></div>
                <div class="cfg-field"><label for="cfg-fecha">Fecha de la licencia</label><input type="date" id="cfg-fecha"><span class="cfg-field-error" data-error="fecha" hidden></span></div>
                <div class="cfg-field cfg-field-full"><label for="cfg-entidad">Entidad que la otorgó</label><input type="text" id="cfg-entidad" maxlength="120"><span class="cfg-field-error" data-error="entidad" hidden></span></div>
                <div class="cfg-field"><label for="cfg-tel">Teléfono principal</label><input type="tel" id="cfg-tel" inputmode="numeric" maxlength="14"><span class="cfg-field-error" data-error="tel" hidden></span></div>
                <div class="cfg-field"><label for="cfg-tel2">Otro teléfono (opcional)</label><input type="tel" id="cfg-tel2" inputmode="numeric" maxlength="14"><span class="cfg-field-error" data-error="tel2" hidden></span></div>
                <div class="cfg-field cfg-field-full"><label for="cfg-correo">Correo de contacto (opcional)</label><input type="email" id="cfg-correo" maxlength="120" placeholder="Todavía no está registrado"><span class="cfg-field-error" data-error="correo" hidden></span></div>
            </div>
        </div>
        <div class="cfg-card-foot">
            <span class="cfg-sin-cambios" data-sin-cambios>Sin cambios por guardar</span>
            <button type="button" class="cfg-btn cfg-btn-ghost" data-deshacer>Deshacer</button>
            <button type="submit" class="cfg-btn cfg-btn-primary">Guardar cambios</button>
        </div>
    </form>
    <aside class="cfg-card">
        <div class="cfg-card-head">
            <div class="cfg-card-icon cfg-card-icon-orange"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div>
                <h2 class="cfg-card-title">Así sale en los documentos</h2>
                <p class="cfg-card-sub">Vista previa del encabezado, con lo que tienes escrito.</p>
            </div>
        </div>
        <div class="cfg-card-body"><div class="cfg-previa" id="cfg-previa" aria-live="polite"></div></div>
    </aside>
    </div>
    </section>

    {{-- ─── Cobros ─── --}}
    <section class="cfg-ppanel" data-ppanel="cobros" aria-label="Cobros" hidden>
    <div class="cfg-grid">
    <form class="cfg-card" id="cfg-f-cobros" novalidate>
        <div class="cfg-card-head">
            <div class="cfg-card-icon cfg-card-icon-green"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div>
                <h2 class="cfg-card-title">Cuota del sistema</h2>
                <p class="cfg-card-sub">Es igual para todos los programas y todas las sedes. Se suma a cada cuota de cada estudiante y no tiene descuento por beca o convenio.</p>
            </div>
        </div>
        <div class="cfg-card-body">
            <div class="cfg-fields">
                <div class="cfg-field">
                    <label for="cfg-cuota">Valor por mes</label>
                    <div class="cfg-prefijo"><span>$</span><input type="text" id="cfg-cuota" inputmode="numeric" maxlength="9"></div>
                    <span class="cfg-hint">En pesos, sin centavos.</span>
                    <span class="cfg-field-error" data-error="cuota" hidden></span>
                </div>
            </div>
        </div>
        <div class="cfg-card-foot">
            <span class="cfg-sin-cambios" data-sin-cambios>Sin cambios por guardar</span>
            <button type="button" class="cfg-btn cfg-btn-ghost" data-deshacer>Deshacer</button>
            <button type="submit" class="cfg-btn cfg-btn-primary">Guardar cuota</button>
        </div>
    </form>
    <aside class="cfg-card">
        <div class="cfg-card-head">
            <div class="cfg-card-icon cfg-card-icon-orange"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg></div>
            <div>
                <h2 class="cfg-card-title">Cuánto representa</h2>
                <p class="cfg-card-sub">Con el valor que tienes escrito.</p>
            </div>
        </div>
        <div class="cfg-card-body"><div class="cfg-ejemplo" id="cfg-ejemplo" aria-live="polite"></div>
            <ul class="cfg-reglas"><li>Aplica a las cuotas que se generen desde que lo guardes, en todas las sedes.</li><li>Las cuotas que ya están generadas no cambian.</li><li>El cambio queda en la auditoría con tu nombre.</li></ul>
        </div>
    </aside>
    </div>
    </section>

    {{-- ─── Seguridad ─── --}}
    <section class="cfg-ppanel" data-ppanel="seguridad" aria-label="Seguridad" hidden>
    <div class="cfg-grid">
    <form class="cfg-card" id="cfg-f-seguridad" novalidate>
        <div class="cfg-card-head">
            <div class="cfg-card-icon cfg-card-icon-blue"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <div>
                <h2 class="cfg-card-title">Acceso a la plataforma</h2>
                <p class="cfg-card-sub">Aplica a todas las cuentas de todas las sedes (secretarías, docentes, estudiantes y acudientes).</p>
            </div>
        </div>
        <div class="cfg-card-body">
            <div class="cfg-fields">
                <div class="cfg-field">
                    <label for="cfg-intentos">Intentos fallidos antes de bloquear</label>
                    <input type="number" id="cfg-intentos" min="3" max="10" step="1">
                    <span class="cfg-hint">Entre 3 y 10. Al llegar al tope, la cuenta queda bloqueada hasta que la desbloquees.</span>
                    <span class="cfg-field-error" data-error="intentos" hidden></span>
                </div>
                <div class="cfg-field">
                    <label for="cfg-inactividad">Días sin entrar para bloquear</label>
                    <input type="number" id="cfg-inactividad" min="30" max="365" step="1">
                    <span class="cfg-hint">Entre 30 y 365 días. No aplica a tu cuenta.</span>
                    <span class="cfg-field-error" data-error="inactividad" hidden></span>
                </div>
            </div>
        </div>
        <div class="cfg-card-foot">
            <span class="cfg-sin-cambios" data-sin-cambios>Sin cambios por guardar</span>
            <button type="button" class="cfg-btn cfg-btn-ghost" data-deshacer>Deshacer</button>
            <button type="submit" class="cfg-btn cfg-btn-primary">Guardar reglas</button>
        </div>
    </form>
    <aside class="cfg-card">
        <div class="cfg-card-head">
            <div class="cfg-card-icon cfg-card-icon-orange"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg></div>
            <div>
                <h2 class="cfg-card-title">Cuentas bloqueadas hoy</h2>
                <p class="cfg-card-sub">Por qué están bloqueadas las cuentas de todas las sedes.</p>
            </div>
        </div>
        <div class="cfg-card-body"><ul class="cfg-motivos" id="cfg-motivos"></ul>
            <button type="button" class="cfg-btn cfg-btn-ghost cfg-btn-sm cfg-ir-bloq" data-ir="bloqueadas">Ver cuentas bloqueadas</button>
        </div>
    </aside>
    </div>
    </section>

    {{-- ─── Cuentas bloqueadas ─── --}}
    <section class="cfg-ppanel" data-ppanel="bloqueadas" hidden aria-label="Cuentas bloqueadas">
        <div class="cfg-table-wrap">
            <table class="cfg-table">
                <thead><tr><th scope="col">Cuenta</th><th scope="col">Sede</th><th scope="col">Motivo</th><th scope="col">Desde</th><th scope="col"><span class="cfg-sr">Acciones</span></th></tr></thead>
                <tbody id="cfg-bloq-body"></tbody>
            </table>
            <p class="cfg-empty" id="cfg-bloq-empty" hidden>No hay cuentas bloqueadas.</p>
        </div>
    </section>
</div>

{{-- ─── Modal: confirmar ─── --}}
<div class="cfg-modal" id="cfg-confirmar" hidden>
    <div class="cfg-modal-overlay" data-cerrar-modal></div>
    <div class="cfg-modal-panel cfg-modal-sm" role="alertdialog" aria-modal="true" aria-labelledby="cfg-conf-titulo" aria-describedby="cfg-conf-texto">
        <div class="cfg-modal-head">
            <h2 class="cfg-modal-title" id="cfg-conf-titulo"></h2>
            <button type="button" class="cfg-close" data-cerrar-modal aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="cfg-modal-body"><p class="cfg-modal-text" id="cfg-conf-texto"></p><ul class="cfg-cambios" id="cfg-conf-cambios"></ul></div>
        <div class="cfg-modal-foot">
            <button type="button" class="cfg-btn cfg-btn-ghost" data-cerrar-modal>Cancelar</button>
            <button type="button" class="cfg-btn cfg-btn-primary" id="cfg-conf-ok"></button>
        </div>
    </div>
</div>

<div class="cfg-toast" id="cfg-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin_general/datos-ejemplo.js') }}?v={{ filemtime(public_path('assets/js/admin_general/datos-ejemplo.js')) }}"></script>
    <script src="{{ asset('assets/js/admin_general/configuracion.js') }}?v={{ filemtime(public_path('assets/js/admin_general/configuracion.js')) }}" defer></script>
@endpush
