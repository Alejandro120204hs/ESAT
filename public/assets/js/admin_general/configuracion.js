/* =========================================================
   ESAT — Panel Admin (ESAT general): Configuración
   Datos de la institución, cuota del sistema y reglas de acceso (cada formulario con validación, Deshacer y
   confirmación que muestra qué cambia), y desbloqueo de cuentas. Los datos llegan en window.GEN_DATOS (ConfiguracionController@index);
   mientras no hay backend. Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    /* ── Utilidades ─────────────────────────────── */
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function num(n) { return Math.round(n || 0).toLocaleString('es-CO'); }
    function $(id) { return document.getElementById(id); }
    function normal(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    function fechaLarga(f) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f); return d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear(); }
    function hace(iso) {
        var d = Math.floor((Date.now() - new Date(iso)) / 86400000);
        return d <= 0 ? 'hoy' : d === 1 ? 'ayer' : 'hace ' + d + ' días';
    }

    var toastEl = $('cfg-toast'), toastT;
    function toast(msg) {
        clearTimeout(toastT);
        toastEl.textContent = msg; toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        toastT = setTimeout(function () { toastEl.classList.remove('is-visible'); setTimeout(function () { toastEl.hidden = true; }, 220); }, 3800);
    }

    /* ── Guardar en el servidor (fetch con CSRF). 401/419: la sesión se cerró → recarga y el login explica el motivo ── */
    var CSRF = document.querySelector('meta[name="csrf-token"]').content, AVISO = 'esat.general.aviso';
    function enviar(metodo, url, cuerpo) {
        var esForm = cuerpo instanceof FormData;
        var h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };
        if (cuerpo && !esForm) h['Content-Type'] = 'application/json';
        return fetch(url, { method: metodo, headers: h, body: cuerpo ? (esForm ? cuerpo : JSON.stringify(cuerpo)) : undefined })
            .catch(function () { throw new Error('No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.'); })
            .then(function (r) {
                if (r.status === 401 || r.status === 419) { location.reload(); return new Promise(function () {}); }
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (r.ok) return j;
                    var errores = j.errors || null, primero = errores && errores[Object.keys(errores)[0]];
                    var err = new Error((primero && primero[0]) || j.message || 'No se pudo guardar. Intenta de nuevo.');
                    err.errores = errores;
                    throw err;
                });
            });
    }
    /* Después de guardar se recarga la página (los datos y las cifras vuelven del servidor) y se muestra el aviso */
    function recargarConAviso(msg) {
        try { sessionStorage.setItem(AVISO, msg || 'Listo.'); } catch (e) { /* sin almacenamiento: recarga sin aviso */ }
        var u = new URL(location.href);
        ['nueva'].forEach(function (k) { u.searchParams.delete(k); });
        location.replace(u.toString());
    }
    function avisoPendiente() {
        try { var m = sessionStorage.getItem(AVISO); sessionStorage.removeItem(AVISO); return m; } catch (e) { return null; }
    }

    function plata(n) { return '$ ' + num(n); }
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function digitos(t) { return String(t || '').replace(/\D/g, ''); }

    /* ── Pestañas ───────────────────────────────── */
    var TAB_KEY = 'esat.general.configuracion.tab', ptab = 'institucion';
    try { ptab = localStorage.getItem(TAB_KEY) || 'institucion'; } catch (e) {}
    function mostrarTab() {
        document.querySelectorAll('.cfg-ptab').forEach(function (b) { var on = b.dataset.ptab === ptab; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        document.querySelectorAll('.cfg-ppanel').forEach(function (p) { p.hidden = p.dataset.ppanel !== ptab; });
    }
    document.querySelectorAll('.cfg-ptab').forEach(function (b) {
        b.addEventListener('click', function () { ptab = b.dataset.ptab; try { localStorage.setItem(TAB_KEY, ptab); } catch (e) {} mostrarTab(); });
    });

    /* ── Confirmación ───────────────────────────── */
    var modal = $('cfg-confirmar'), volver = null, alConfirmar = null;
    function confirmar(titulo, texto, cambios, boton, accion) {
        volver = document.activeElement;
        $('cfg-conf-titulo').textContent = titulo; $('cfg-conf-texto').textContent = texto; $('cfg-conf-ok').textContent = boton;
        $('cfg-conf-cambios').innerHTML = cambios.map(function (c) { return '<li><strong>' + esc(c[0]) + ':</strong> <del>' + esc(c[1] || 'vacío') + '</del> → ' + esc(c[2] || 'vacío') + '</li>'; }).join('');
        alConfirmar = accion; modal.hidden = false; $('cfg-conf-ok').focus();
    }
    function cerrar() { if (modal.hidden) return; modal.hidden = true; if (volver && document.contains(volver)) volver.focus(); }
    modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrar); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });
    $('cfg-conf-ok').addEventListener('click', function () {
        var b = this; b.disabled = true;
        alConfirmar().then(function (r) { recargarConAviso(r.mensaje); }).catch(function (err) { b.disabled = false; cerrar(); toast(err.message); });
    });

    /* ── Formularios (cada uno con sus campos, validación y lo que se guarda) ── */
    function err(form, campo, msg) {
        var e = form.querySelector('[data-error="' + campo + '"]'); if (!e) return; e.textContent = msg || ''; e.hidden = !msg;
        var input = e.parentNode.querySelector('input'); if (input) input.classList.toggle('is-invalid', !!msg);
    }
    function formulario(id, campos) {
        var form = $(id), ref = {};
        function leer() { var v = {}; campos.forEach(function (c) { v[c.k] = c.leer ? c.leer($(c.id).value) : $(c.id).value.trim(); }); return v; }
        function cargar() { campos.forEach(function (c) { $(c.id).value = c.mostrar ? c.mostrar(c.desde()) : (c.desde() == null ? '' : c.desde()); }); ref = leer(); cambio(); }
        function difs() { var v = leer(); return campos.filter(function (c) { return String(v[c.k]) !== String(ref[c.k]); }); }
        function cambio() {
            var hay = difs().length > 0;
            form.querySelector('[data-sin-cambios]').hidden = hay;
            form.querySelector('[data-deshacer]').disabled = !hay;
            form.querySelector('[type=submit]').disabled = !hay;
        }
        form.addEventListener('input', function (e) { var c = campos.find(function (x) { return x.id === e.target.id; }); if (c) err(form, c.err || c.k, ''); cambio(); });
        form.querySelector('[data-deshacer]').addEventListener('click', function () { campos.forEach(function (c) { err(form, c.err || c.k, ''); }); cargar(); });
        return { form: form, leer: leer, cargar: cargar, difs: difs, ref: function () { return ref; } };
    }
    function fechaTexto(f) { return f ? fechaLarga(f) : ''; }

    /* Institución */
    var C = function () { return D.configuracion; };
    var inst = formulario('cfg-f-institucion', [
        { k: 'nombre', id: 'cfg-nombre', etiqueta: 'Nombre', desde: function () { return C().institucion.nombre; } },
        { k: 'sigla', id: 'cfg-sigla', etiqueta: 'Sigla', desde: function () { return C().institucion.sigla; } },
        { k: 'nit', id: 'cfg-nit', etiqueta: 'NIT', desde: function () { return C().institucion.nit; } },
        { k: 'licencia', id: 'cfg-licencia', etiqueta: 'Licencia', desde: function () { return C().institucion.licencia; } },
        { k: 'fecha_licencia', err: 'fecha', id: 'cfg-fecha', etiqueta: 'Fecha de la licencia', desde: function () { return C().institucion.fecha_licencia; }, texto: fechaTexto },
        { k: 'entidad', id: 'cfg-entidad', etiqueta: 'Entidad', desde: function () { return C().institucion.entidad; } },
        { k: 'telefono', err: 'tel', id: 'cfg-tel', etiqueta: 'Teléfono', desde: function () { return C().institucion.telefono; }, leer: digitos },
        { k: 'telefono2', err: 'tel2', id: 'cfg-tel2', etiqueta: 'Otro teléfono', desde: function () { return C().institucion.telefono2; }, leer: digitos },
        { k: 'correo', id: 'cfg-correo', etiqueta: 'Correo', desde: function () { return C().institucion.correo; }, leer: function (v) { return v.trim().toLowerCase(); } }
    ]);
    function telValido(t) { return /^3\d{9}$/.test(t) || /^60\d{8}$/.test(t); }
    inst.form.addEventListener('submit', function (e) {
        e.preventDefault();
        var v = inst.leer(), f = inst.form;
        err(f, 'nombre', v.nombre.length < 5 ? 'Escribe el nombre completo.' : '');
        err(f, 'sigla', !v.sigla ? 'Escribe la sigla.' : '');
        err(f, 'nit', !/^\d{9}-\d$/.test(v.nit) ? 'Escribe el NIT así: 900820764-1.' : '');
        err(f, 'licencia', !/^\d{1,10}$/.test(v.licencia) ? 'Escribe el número de la licencia.' : '');
        err(f, 'fecha', !v.fecha_licencia || v.fecha_licencia > new Date().toISOString().slice(0, 10) ? 'Escribe una fecha que no sea futura.' : '');
        err(f, 'entidad', v.entidad.length < 5 ? 'Escribe la entidad.' : '');
        err(f, 'tel', !telValido(v.telefono) ? 'Celular de 10 dígitos que empiece por 3, o fijo que empiece por 60.' : '');
        err(f, 'tel2', v.telefono2 && !telValido(v.telefono2) ? 'Revisa el número o déjalo vacío.' : '');
        err(f, 'correo', v.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.correo) ? 'Escribe un correo válido o déjalo vacío.' : '');
        var malo = f.querySelector('.cfg-field-error:not([hidden])');
        if (malo) { malo.parentNode.querySelector('input').focus(); return; }
        var ref = inst.ref(), cambios = inst.difs().map(function (c) { return [c.etiqueta, c.texto ? c.texto(ref[c.k]) : ref[c.k], c.texto ? c.texto(v[c.k]) : v[c.k]]; });
        confirmar('¿Guardar los datos de la institución?', 'Los recibos y documentos que se impriman desde ahora en todas las sedes saldrán con estos datos.', cambios, 'Guardar', function () {
            var nueva = v;
            return enviar('PUT', '/general/configuracion/institucion', nueva);
        });
    });

    /* Cobros */
    var cobros = formulario('cfg-f-cobros', [
        { k: 'cuota', id: 'cfg-cuota', etiqueta: 'Cuota del sistema', desde: function () { return C().cuota_sistema; }, mostrar: function (v) { return num(v); }, leer: function (t) { return digitos(t) === '' ? '' : +digitos(t); }, texto: plata }
    ]);
    function ejemplo() {
        var v = cobros.leer().cuota;
        $('cfg-ejemplo').innerHTML = v === '' ? 'Escribe el valor.' :
            'Ejemplo: si la cuota de un programa es de <strong>' + esc(plata(250000)) + '</strong>, el estudiante paga <strong>' + esc(plata(250000 + v)) + '</strong> al mes. ' +
            'Con los ' + num(D.activos) + ' estudiantes activos de hoy, son unos <strong>' + esc(plata(v * D.activos)) + '</strong> al mes para el sistema.';
    }
    $('cfg-cuota').addEventListener('input', function () { var d = digitos(this.value); this.value = d ? num(+d) : ''; ejemplo(); });
    cobros.form.addEventListener('submit', function (e) {
        e.preventDefault();
        var v = cobros.leer().cuota, ant = cobros.ref().cuota;
        err(cobros.form, 'cuota', v === '' ? 'Escribe el valor.' : v > 100000 ? 'Revisa el valor: es muy alto para una cuota del sistema.' : v % 100 ? 'Usa un valor redondo (múltiplo de $ 100).' : '');
        if (v === '' || v > 100000 || v % 100) { $('cfg-cuota').focus(); return; }
        confirmar('¿Cambiar la cuota del sistema?', 'Aplica a las cuotas que se generen desde ahora en todas las sedes. Las cuotas ya generadas no cambian.', [['Cuota del sistema', plata(ant), plata(v)]], 'Cambiar cuota', function () {
            return enviar('PUT', '/general/configuracion/cobros', { cuota_sistema: v });
        });
    });

    /* Seguridad */
    var seg = formulario('cfg-f-seguridad', [
        { k: 'intentos_maximos', err: 'intentos', id: 'cfg-intentos', etiqueta: 'Intentos fallidos', desde: function () { return C().seguridad.intentos_maximos; }, leer: function (t) { return t === '' ? '' : +t; } },
        { k: 'dias_inactividad', err: 'inactividad', id: 'cfg-inactividad', etiqueta: 'Días sin entrar', desde: function () { return C().seguridad.dias_inactividad; }, leer: function (t) { return t === '' ? '' : +t; } }
    ]);
    seg.form.addEventListener('submit', function (e) {
        e.preventDefault();
        var v = seg.leer();
        err(seg.form, 'intentos', !(Number.isInteger(v.intentos_maximos) && v.intentos_maximos >= 3 && v.intentos_maximos <= 10) ? 'Escribe un número entero entre 3 y 10.' : '');
        err(seg.form, 'inactividad', !(Number.isInteger(v.dias_inactividad) && v.dias_inactividad >= 30 && v.dias_inactividad <= 365) ? 'Escribe un número entero entre 30 y 365.' : '');
        var malo = seg.form.querySelector('.cfg-field-error:not([hidden])');
        if (malo) { malo.parentNode.querySelector('input').focus(); return; }
        var ref = seg.ref(), cambios = seg.difs().map(function (c) { return [c.etiqueta, String(ref[c.k]), String(v[c.k])]; });
        confirmar('¿Guardar las reglas de acceso?', 'Aplican desde ya a todas las cuentas de todas las sedes.', cambios, 'Guardar', function () {
            return enviar('PUT', '/general/configuracion/seguridad', v);
        });
    });

    /* ── Cuentas bloqueadas ─────────────────────── */
    var MOTIVO = D.configuracion.motivos;
    function pintarBloqueadas() {
        var l = C().bloqueadas || [];
        var n = $('cfg-num-bloq'); n.textContent = l.length; n.hidden = !l.length;
        $('cfg-bloq-empty').hidden = l.length > 0;
        $('cfg-bloq-body').innerHTML = l.map(function (b) {
            var s = sedePorId(b.sede_id);
            return '<tr><td><div class="cfg-strong">' + esc(b.nombre) + '</div><div class="cfg-muted">' + esc(b.rol) + '</div></td><td>' + esc(s ? s.nombre : '—') + '</td>' +
                '<td>' + esc(MOTIVO[b.motivo] || b.motivo) + '</td><td>' + esc(fechaLarga(b.desde)) + '<div class="cfg-muted">' + esc(hace(b.desde)) + '</div></td>' +
                '<td class="cfg-actions"><button type="button" class="cfg-btn cfg-btn-ghost cfg-btn-sm" data-desbloquear="' + b.id + '">Desbloquear</button></td></tr>';
        }).join('');
    }
    $('cfg-bloq-body').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-desbloquear]'); if (!btn) return;
        var b = C().bloqueadas.find(function (x) { return x.id === +btn.dataset.desbloquear; }), s = sedePorId(b.sede_id);
        confirmar('¿Desbloquear la cuenta de ' + b.nombre + '?', 'Podrá entrar otra vez con su correo y su clave. Si no recuerda la clave, la secretaría de la sede puede restablecerla.', [], 'Desbloquear', function () {
            return enviar('PATCH', '/general/configuracion/bloqueadas/' + b.id);
        });
    });

    /* ── KPI, vista previa y resumen de bloqueos ── */
    var ICON = {
        plata: '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        llave: '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        bloqueo: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>'
    };
    function pintarKpis() {
        var c = C(), n = (c.bloqueadas || []).length;
        var k = [['green', 'plata', plata(c.cuota_sistema), 'Cuota del sistema por mes'], ['blue', 'llave', c.seguridad.intentos_maximos + ' intentos', 'Antes de bloquear una cuenta'],
            ['orange', 'reloj', c.seguridad.dias_inactividad + ' días', 'Sin entrar para bloquear'], [n ? 'red' : 'green', 'bloqueo', num(n), n === 1 ? 'Cuenta bloqueada' : 'Cuentas bloqueadas']];
        $('cfg-kpis').innerHTML = k.map(function (x) {
            return '<div class="cfg-kpi cfg-kpi-' + x[0] + '"><div class="cfg-kpi-icon">' + ICON[x[1]] + '</div><div><div class="cfg-kpi-val">' + esc(x[2]) + '</div><div class="cfg-kpi-lbl">' + esc(x[3]) + '</div></div></div>';
        }).join('');
    }
    function previa() {
        var v = inst.leer(), falta = function (t, que) { return t ? esc(t) : '<span class="cfg-previa-falta">' + esc(que) + '</span>'; };
        var tels = [v.telefono, v.telefono2].filter(Boolean).map(function (t) { return t.length === 10 ? t.slice(0, 3) + ' ' + t.slice(3, 6) + ' ' + t.slice(6) : t; });
        $('cfg-previa').innerHTML = '<div class="cfg-previa-inst">' + falta(v.nombre, 'Falta el nombre') + '</div>' +
            '<div class="cfg-previa-sub">NIT ' + falta(v.nit, 'sin NIT') + ' · Licencia de funcionamiento ' + falta(v.licencia, 'sin número') +
            (v.fecha_licencia ? ' de ' + esc(fechaLarga(v.fecha_licencia)) : '') + '<br>' + falta(v.entidad, 'Falta la entidad') +
            (tels.length || v.correo ? '<br>' + esc(tels.join(' · ')) + (tels.length && v.correo ? ' · ' : '') + esc(v.correo) : '') + '</div>' +
            '<div class="cfg-previa-linea"></div><div class="cfg-previa-doc">Recibo de caja RC-' + new Date().getFullYear() + '-00000</div>';
    }
    function pintarMotivos() {
        var l = C().bloqueadas || [];
        var filas = Object.keys(MOTIVO).map(function (m) { return [MOTIVO[m], l.filter(function (b) { return b.motivo === m; }).length]; });
        $('cfg-motivos').innerHTML = filas.map(function (f) { return '<li><span>' + esc(f[0]) + '</span><strong>' + f[1] + '</strong></li>'; }).join('');
    }
    function refrescar() { pintarKpis(); previa(); pintarMotivos(); }
    inst.form.addEventListener('input', previa);
    inst.form.querySelector('[data-deshacer]').addEventListener('click', previa);
    document.querySelectorAll('[data-ir]').forEach(function (b) {
        b.addEventListener('click', function () { ptab = b.dataset.ir; try { localStorage.setItem(TAB_KEY, ptab); } catch (e) {} mostrarTab(); });
    });

    inst.cargar(); cobros.cargar(); seg.cargar(); ejemplo(); pintarBloqueadas(); mostrarTab(); refrescar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
}());
