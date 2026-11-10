/* =========================================================
   ESAT — Panel Admin (ESAT general): Secretarías
   Cuentas de secretaría (rol admin) de todas las sedes: lista con filtros, crear/editar en 3 pasos,
   activar/desactivar (con confirmación) y restablecer la clave. Los datos llegan en window.GEN_DATOS
   (SecretariaController@index); guardar va por fetch a /general/secretarias (SecretariaRequest) y recarga con el aviso.
   Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 10;
    var ICON = {
        editar: '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        sedes: '<svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>',
        persona: '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>'
    };

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

    var toastEl = $('sec-toast'), toastT;
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

    /* Paginación: botones numerados con saltos (…) */
    function paginar(nav, total, pagina, alIr) {
        var paginas = Math.ceil(total / POR_PAGINA);
        nav.hidden = paginas <= 1;
        if (paginas <= 1) { nav.innerHTML = ''; return; }
        var r = [1];
        if (pagina > 3) r.push('…');
        for (var i = Math.max(2, pagina - 1); i <= Math.min(paginas - 1, pagina + 1); i++) r.push(i);
        if (pagina < paginas - 2) r.push('…');
        r.push(paginas);
        nav.innerHTML = '<button type="button" class="sec-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="sec-pg-gap">…</span>'
                    : '<button type="button" class="sec-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="sec-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
        nav.onclick = function (e) { var b = e.target.closest('[data-pg]'); if (b && !b.disabled) alIr(+b.dataset.pg); };
    }

    /* ── Combobox (copiado del Admin: abre hacia donde quepa, alScroll ignora el scroll de su lista) ── */
    function initCombobox(root) {
        var trigger = root.querySelector('[data-combobox-trigger]'), label = root.querySelector('[data-combobox-label]');
        var panel = root.querySelector('[data-combobox-panel]'), searchInput = root.querySelector('[data-combobox-search]');
        var list = root.querySelector('[data-combobox-list]'), hidden = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;
        function posicionar() {
            var rect = trigger.getBoundingClientRect(), margen = 12;
            panel.style.left = rect.left + 'px'; panel.style.width = rect.width + 'px';
            var abajo = window.innerHeight - rect.bottom - 6 - margen, arriba = rect.top - 6 - margen;
            list.style.maxHeight = '';
            var extra = panel.offsetHeight - list.offsetHeight, tope = 320;
            var necesario = Math.min(list.scrollHeight, tope) + extra;
            var haciaArriba = abajo < necesario && arriba > abajo, espacio = haciaArriba ? arriba : abajo;
            panel.style.top = haciaArriba ? '' : (rect.bottom + 6) + 'px';
            panel.style.bottom = haciaArriba ? (window.innerHeight - rect.top + 6) + 'px' : '';
            list.style.maxHeight = Math.max(Math.min(tope, espacio - extra), 40) + 'px';
        }
        var esAbsoluto = root.dataset.position === 'absolute';   // filtros: el CSS lo pone debajo del botón
        function alScroll(e) { if (panel.contains(e.target)) return; posicionar(); }
        function abrir() {
            panel.hidden = false; root.classList.add('is-open');
            if (!esAbsoluto) { posicionar(); window.addEventListener('scroll', alScroll, true); window.addEventListener('resize', posicionar); }
            if (searchInput) { searchInput.value = ''; filtrar(''); searchInput.focus(); }
        }
        function cerrar() {
            if (panel.hidden) return;
            panel.hidden = true; root.classList.remove('is-open');
            if (!esAbsoluto) { window.removeEventListener('scroll', alScroll, true); window.removeEventListener('resize', posicionar); }
        }
        function filtrar(q) {
            var qq = normal(q.trim()), vis = 0;
            list.querySelectorAll('li[data-value]').forEach(function (li) { var ok = !qq || normal(li.textContent).indexOf(qq) !== -1; li.hidden = !ok; if (ok) vis++; });
            var vacio = list.querySelector('.app-combobox-empty'); if (vacio) vacio.hidden = vis > 0;
        }
        trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(); });
        if (searchInput) searchInput.addEventListener('input', function () { filtrar(searchInput.value); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });
        root.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { e.stopPropagation(); cerrar(); trigger.focus(); } });
        root.setOptions = function (ops) {
            list.innerHTML = '';
            ops.forEach(function (o) {
                var val = typeof o === 'object' ? o.value : o, lbl = typeof o === 'object' ? o.label : o;
                var li = document.createElement('li');
                li.setAttribute('role', 'option'); li.dataset.value = val; li.tabIndex = -1;
                li.innerHTML = '<span></span><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>';
                li.firstChild.textContent = lbl;
                li.addEventListener('click', function () { root.setValue(val, lbl); cerrar(); trigger.focus(); });
                li.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); li.click(); } });
                list.appendChild(li);
            });
            var vacio = document.createElement('li');
            vacio.className = 'app-combobox-empty'; vacio.hidden = ops.length > 0; vacio.textContent = 'Sin resultados';
            list.appendChild(vacio);
        };
        root.setValue = function (val, lbl, opts) {
            hidden.value = val || ''; label.textContent = lbl || val || placeholder;
            root.classList.remove('is-invalid');
            list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.toggle('is-selected', li.dataset.value === String(val)); });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        root.reset = function (ph) { if (ph) placeholder = ph; list.innerHTML = ''; hidden.value = ''; label.textContent = placeholder; };
        root.value = function () { return hidden.value; };
        root.isOpen = function () { return !panel.hidden; };
    }

    /* ── Datos ──────────────────────────────────── */
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function nombre(x) { return x.nombres + ' ' + x.apellidos; }
    function iniciales(x) { return ((x.nombres || '').charAt(0) + (x.apellidos || '').charAt(0)).toUpperCase(); }
    function dias(iso) { return Math.floor((Date.now() - new Date(iso)) / 86400000); }
    function edad(f) {
        if (!f) return null;
        var n = new Date(f + 'T12:00:00'), h = new Date(), e = h.getFullYear() - n.getFullYear();
        if (h.getMonth() < n.getMonth() || (h.getMonth() === n.getMonth() && h.getDate() < n.getDate())) e--;
        return e;
    }
    function fmtTel(t) { var d = String(t || '').replace(/\D/g, ''); return d.length === 10 ? d.slice(0, 3) + ' ' + d.slice(3, 6) + ' ' + d.slice(6) : esc(t); }
    function ultimo(x) {
        if (!x.ultimo_acceso) return '<span class="sec-warn">Nunca ha ingresado</span><div class="sec-muted">Cuenta creada ' + esc(hace(x.creada)) + '</div>';
        var d = dias(x.ultimo_acceso);
        return (d >= 30 && x.activo ? '<span class="sec-warn">' : '<span>') + esc(hace(x.ultimo_acceso).charAt(0).toUpperCase() + hace(x.ultimo_acceso).slice(1)) + '</span><div class="sec-muted">' + esc(fechaLarga(x.ultimo_acceso)) + '</div>';
    }

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var act = D.secretarias.filter(function (x) { return x.activo; });
        var sinSec = D.sedes.filter(function (s) { return !act.some(function (x) { return x.sede_id === s.id; }); }).length;
        var quietas = act.filter(function (x) { return !x.ultimo_acceso || dias(x.ultimo_acceso) >= 30; }).length;
        var k = [['blue', 'persona', num(D.secretarias.length), 'Cuentas'], ['green', 'persona', num(act.length), act.length === 1 ? 'Activa' : 'Activas'],
            [sinSec ? 'red' : 'green', 'sedes', num(sinSec), sinSec === 1 ? 'Sede sin secretaría' : 'Sedes sin secretaría'],
            [quietas ? 'orange' : 'green', 'alerta', num(quietas), 'Sin entrar en 30 días']];
        $('sec-kpis').innerHTML = k.map(function (x) {
            return '<div class="sec-kpi sec-kpi-' + x[0] + '"><div class="sec-kpi-icon">' + ICON[x[1]] + '</div><div><div class="sec-kpi-val">' + x[2] + '</div><div class="sec-kpi-lbl">' + x[3] + '</div></div></div>';
        }).join('');
    }

    /* ── Filtros y tabla ────────────────────────── */
    var cbSede = $('sec-cb-sede'), cbEstado = $('sec-cb-estado');
    initCombobox(cbSede); initCombobox(cbEstado);
    cbSede.setOptions([{ value: '', label: 'Todas las sedes' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: s.nombre }; })));
    cbEstado.setOptions([{ value: '', label: 'Todos los estados' }, { value: 'activa', label: 'Activas' }, { value: 'inactiva', label: 'Inactivas' }, { value: 'quieta', label: 'Sin entrar hace 30 días o más' }]);
    cbSede.setValue('', 'Todas las sedes', { silent: true }); cbEstado.setValue('', 'Todos los estados', { silent: true });
    var sedeUrl = (location.search.match(/[?&]sede=(\d+)/) || [])[1];
    if (sedeUrl && sedePorId(sedeUrl)) cbSede.setValue(sedeUrl, sedePorId(sedeUrl).nombre, { silent: true });

    var pagina = 1, filtradas = [];
    function filtrar() {
        var q = normal($('sec-buscar').value.trim()), sede = cbSede.value(), est = cbEstado.value();
        filtradas = D.secretarias.filter(function (x) {
            if (q && normal(nombre(x) + ' ' + x.numero_documento + ' ' + x.email).indexOf(q) === -1) return false;
            if (sede && String(x.sede_id) !== sede) return false;
            if (est === 'activa' && !x.activo) return false;
            if (est === 'inactiva' && x.activo) return false;
            if (est === 'quieta' && !(x.activo && (!x.ultimo_acceso || dias(x.ultimo_acceso) >= 30))) return false;
            return true;
        }).sort(function (a, b) { return (b.activo - a.activo) || nombre(a).localeCompare(nombre(b), 'es'); });
        pagina = 1; pintarTabla();
    }
    function pintarTabla() {
        var desde = (pagina - 1) * POR_PAGINA;
        $('sec-count').textContent = filtradas.length + (filtradas.length === 1 ? ' cuenta' : ' cuentas');
        $('sec-empty').hidden = filtradas.length > 0;
        $('sec-body').innerHTML = filtradas.slice(desde, desde + POR_PAGINA).map(function (x) {
            var s = sedePorId(x.sede_id);
            return '<tr class="sec-row" tabindex="0" data-id="' + x.id + '">' +
                '<td><div class="sec-cell"><div class="sec-avatar-txt' + (x.activo ? '' : ' is-off') + '">' + esc(iniciales(x)) + '</div><div><div class="sec-strong">' + esc(nombre(x)) + '</div><div class="sec-muted">' + esc(x.tipo_documento + ' ' + x.numero_documento) + '</div></div></div></td>' +
                '<td>' + esc(s ? s.nombre : '—') + '</td>' +
                '<td>' + esc(x.email) + '<div class="sec-muted">' + fmtTel(x.telefono) + '</div></td>' +
                '<td>' + ultimo(x) + '</td>' +
                '<td><button type="button" class="sec-switch" role="switch" aria-checked="' + x.activo + '" data-switch="' + x.id + '" aria-label="Cuenta de ' + esc(nombre(x)) + ' ' + (x.activo ? 'activa' : 'inactiva') + '"><span class="sec-switch-track"></span>' + (x.activo ? 'Activa' : 'Inactiva') + '</button></td>' +
                '<td class="sec-actions"><button type="button" class="sec-icon-btn" data-editar="' + x.id + '" aria-label="Editar ' + esc(nombre(x)) + '">' + ICON.editar + '</button></td></tr>';
        }).join('');
        paginar($('sec-paginacion'), filtradas.length, pagina, function (p) { pagina = p; pintarTabla(); });
    }
    $('sec-buscar').addEventListener('input', filtrar);
    [cbSede, cbEstado].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', filtrar); });
    $('sec-body').addEventListener('click', function (e) {
        var sw = e.target.closest('[data-switch]');
        if (sw) { e.stopPropagation(); pedirCambioEstado(+sw.dataset.switch); return; }
        var tr = e.target.closest('tr[data-id]');
        if (tr) abrirForm(+tr.dataset.id);
    });
    $('sec-body').addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirForm(+e.target.dataset.id); }
    });

    /* ── Modales ────────────────────────────────── */
    var modalAbierto = null, volverModal = null, pila = [];
    function abrirModal(id, foco) {
        if (modalAbierto) pila.push(modalAbierto); else volverModal = document.activeElement;
        modalAbierto = $(id); modalAbierto.hidden = false;
        (foco || modalAbierto.querySelector('input, select, button')).focus();
    }
    function cerrarModal() {
        if (!modalAbierto) return;
        modalAbierto.hidden = true;
        modalAbierto = pila.pop() || null;
        if (modalAbierto) { var b = modalAbierto.querySelector('button'); if (b) b.focus(); return; }
        if (volverModal && document.contains(volverModal)) volverModal.focus();
    }
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalAbierto && !document.querySelector('.app-combobox.is-open')) cerrarModal();
    });

    var alConfirmar = null;
    function confirmar(titulo, texto, boton, aviso, accion) {
        $('sec-conf-titulo').textContent = titulo; $('sec-conf-texto').textContent = texto; $('sec-conf-ok').textContent = boton;
        $('sec-conf-aviso').textContent = aviso || ''; $('sec-conf-aviso').hidden = !aviso;
        alConfirmar = accion;
        abrirModal('sec-confirmar', $('sec-conf-ok'));
    }
    $('sec-conf-ok').addEventListener('click', function () {
        var b = this; b.disabled = true;
        Promise.resolve(alConfirmar && alConfirmar()).catch(function (err) { b.disabled = false; cerrarModal(); toast(err.message); });
    });

    /* ── Activar / desactivar ───────────────────── */
    function pedirCambioEstado(id) {
        var x = D.secretarias.find(function (y) { return y.id === id; }), s = sedePorId(x.sede_id);
        if (x.activo) {
            var otras = D.secretarias.filter(function (y) { return y.activo && y.sede_id === x.sede_id && y.id !== x.id; }).length;
            confirmar('¿Desactivar la cuenta de ' + nombre(x) + '?',
                'No podrá volver a entrar y se cerrará su sesión si la tiene abierta. Lo que registró sigue en la auditoría.',
                'Desactivar cuenta',
                otras ? '' : 'La sede ' + s.nombre + ' se queda sin secretaría activa: tendrás que registrar sus matrículas y pagos entrando a la sede, o crear otra cuenta.',
                function () {
                    return enviar('PATCH', '/general/secretarias/' + x.id + '/estado').then(function (r) { recargarConAviso(r.mensaje); });
                });
        } else {
            confirmar('¿Activar la cuenta de ' + nombre(x) + '?', 'Podrá entrar otra vez con su correo y su clave a la sede ' + s.nombre + '.', 'Activar cuenta', '',
                function () {
                    return enviar('PATCH', '/general/secretarias/' + x.id + '/estado').then(function (r) { recargarConAviso(r.mensaje); });
                });
        }
    }

    /* ── Formulario en 3 pasos ──────────────────── */
    var cbFormSede = $('sec-cb-form-sede');
    initCombobox(cbFormSede);
    cbFormSede.setOptions(D.sedes.map(function (s) { return { value: String(s.id), label: s.nombre + ' · ' + s.ciudad }; }));
    var paso = 1, editando = null;
    var CAMPOS = { 1: ['nombres', 'apellidos', 'genero', 'nacimiento'], 2: ['documento', 'telefono', 'correo'], 3: ['sede'] };
    function f(id) { return $('sec-f-' + id); }
    function err(campo, msg) {
        var e = document.querySelector('#sec-form [data-error="' + campo + '"]');
        e.textContent = msg || ''; e.hidden = !msg;
        if (campo === 'sede') cbFormSede.classList.toggle('is-invalid', !!msg); else f(campo).classList.toggle('is-invalid', !!msg);
    }
    function validar(p) {
        var v = function (id) { return f(id).value.trim(); };
        if (p === 1) {
            err('nombres', v('nombres').length < 2 ? 'Escribe los nombres.' : '');
            err('apellidos', v('apellidos').length < 2 ? 'Escribe los apellidos.' : '');
            err('genero', v('genero') ? '' : 'Elige el género.');
            var e = edad(v('nacimiento'));
            err('nacimiento', !v('nacimiento') ? 'Escribe la fecha de nacimiento.' : e < 18 ? 'Debe ser mayor de edad.' : e > 90 ? 'Revisa la fecha.' : '');
        }
        if (p === 2) {
            var doc = v('documento').replace(/\D/g, ''), tel = v('telefono').replace(/\D/g, ''), correo = v('correo').toLowerCase();
            err('documento', !/^\d{6,12}$/.test(doc) ? 'Escribe el número de documento (solo números, de 6 a 12).' : '');
            err('telefono', !/^3\d{9}$/.test(tel) ? 'Escribe un celular de 10 dígitos que empiece por 3.' : '');
            err('correo', !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(correo) ? 'Escribe un correo válido.' : '');
        }
        if (p === 3) err('sede', cbFormSede.value() ? '' : 'Elige la sede.');
        var malo = CAMPOS[p].find(function (c) { return !document.querySelector('#sec-form [data-error="' + c + '"]').hidden; });
        if (malo) { if (malo === 'sede') cbFormSede.querySelector('button').focus(); else f(malo).focus(); }
        return !malo;
    }
    function resumen() {
        var s = sedePorId(cbFormSede.value()), g = { femenino: 'Femenino', masculino: 'Masculino', otro: 'Otro' };
        var filas = [['Nombre', f('nombres').value.trim() + ' ' + f('apellidos').value.trim()], ['Documento', f('tipo').value + ' ' + f('documento').value.replace(/\D/g, '')],
            ['Celular', f('telefono').value.replace(/\D/g, '')], ['Correo', f('correo').value.trim().toLowerCase()],
            ['Género', g[f('genero').value] || ''], ['Edad', edad(f('nacimiento').value) + ' años'], ['Sede', s ? s.nombre : 'Sin elegir']];
        $('sec-resumen').innerHTML = filas.map(function (x) { return '<div><span class="sec-info-lbl">' + esc(x[0]) + '</span><span class="sec-info-val">' + esc(x[1]) + '</span></div>'; }).join('');
    }
    function irPaso(p) {
        paso = p;
        document.querySelectorAll('#sec-form .sec-paso').forEach(function (el) { el.hidden = +el.dataset.paso !== p; });
        document.querySelectorAll('#sec-form .sec-step').forEach(function (el) {
            var n = +el.dataset.step; el.classList.toggle('is-active', n === p); el.classList.toggle('is-done', n < p);
        });
        $('sec-modal-sub').textContent = 'Paso ' + p + ' de 3';
        $('sec-atras').hidden = p === 1;
        $('sec-siguiente').textContent = p < 3 ? 'Siguiente' : editando ? 'Guardar cambios' : 'Crear cuenta';
        if (p === 3) resumen();
        var primero = document.querySelector('#sec-form .sec-paso[data-paso="' + p + '"] input:not([type=hidden]), #sec-form .sec-paso[data-paso="' + p + '"] select, #sec-form .sec-paso[data-paso="' + p + '"] .app-combobox-trigger');
        if (primero && modalAbierto) primero.focus();
    }
    f('nacimiento').addEventListener('input', function () { var e = edad(this.value); $('sec-edad').textContent = e != null && e >= 0 ? e + ' años' : ''; });
    cbFormSede.querySelector('[data-combobox-value]').addEventListener('change', function () { err('sede', ''); resumen(); });
    function abrirForm(id, sedeInicial) {
        editando = id ? D.secretarias.find(function (x) { return x.id === id; }) : null;
        var x = editando || {};
        f('nombres').value = x.nombres || ''; f('apellidos').value = x.apellidos || ''; f('genero').value = x.genero || '';
        f('nacimiento').value = x.fecha_nacimiento || ''; f('tipo').value = x.tipo_documento || 'CC'; f('documento').value = x.numero_documento || '';
        f('telefono').value = x.telefono || ''; f('correo').value = x.email || '';
        f('nacimiento').max = new Date().toISOString().slice(0, 10);
        f('nacimiento').dispatchEvent(new Event('input'));
        var sid = x.sede_id || sedeInicial;
        if (sid && sedePorId(sid)) cbFormSede.setValue(String(sid), sedePorId(sid).nombre + ' · ' + sedePorId(sid).ciudad, { silent: true });
        else cbFormSede.setValue('', 'Selecciona una sede', { silent: true });
        ['nombres', 'apellidos', 'genero', 'nacimiento', 'documento', 'telefono', 'correo', 'sede'].forEach(function (c) { err(c, ''); });
        $('sec-modal-titulo').textContent = editando ? 'Editar a ' + nombre(x) : 'Nueva secretaría';
        $('sec-nota-clave').hidden = !!editando;
        $('sec-restablecer').hidden = !editando;
        abrirModal('sec-modal', f('nombres'));
        irPaso(1);
    }
    $('sec-nueva').addEventListener('click', function () { abrirForm(null); });
    ['nombres', 'apellidos', 'genero', 'nacimiento', 'documento', 'telefono', 'correo'].forEach(function (c) { f(c).addEventListener('input', function () { err(c, ''); }); f(c).addEventListener('change', function () { err(c, ''); }); });
    $('sec-atras').addEventListener('click', function () { if (paso > 1) irPaso(paso - 1); });
    $('sec-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validar(paso)) return;
        if (paso < 3) { irPaso(paso + 1); return; }
        var reg = {
            nombres: f('nombres').value.trim(), apellidos: f('apellidos').value.trim(), genero: f('genero').value, fecha_nacimiento: f('nacimiento').value,
            tipo_documento: f('tipo').value, numero_documento: f('documento').value.replace(/\D/g, ''), telefono: f('telefono').value.replace(/\D/g, ''),
            email: f('correo').value.trim().toLowerCase(), sede_id: +cbFormSede.value()
        };
        var btn = $('sec-siguiente'); btn.disabled = true;
        enviar(editando ? 'PUT' : 'POST', editando ? '/general/secretarias/' + editando.id : '/general/secretarias', reg)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (fallo) {
                btn.disabled = false;
                /* Lleva al paso del primer campo con error */
                var CAMPO = { nombres: 'nombres', apellidos: 'apellidos', genero: 'genero', fecha_nacimiento: 'nacimiento', numero_documento: 'documento',
                    tipo_documento: 'documento', telefono: 'telefono', email: 'correo', sede_id: 'sede' };
                var k = Object.keys(fallo.errores || {}).find(function (c) { return CAMPO[c]; });
                if (!k) { toast(fallo.message); return; }
                var c = CAMPO[k], p = +Object.keys(CAMPOS).find(function (n) { return CAMPOS[n].indexOf(c) >= 0; });
                irPaso(p); err(c, fallo.errores[k][0]);
            });
    });
    $('sec-restablecer').addEventListener('click', function () {
        var x = editando;
        confirmar('¿Restablecer la clave de ' + nombre(x) + '?', 'Su clave volverá a ser su número de documento (' + x.numero_documento + ') y se cerrarán sus sesiones abiertas.', 'Restablecer clave', '',
            function () {
                return enviar('PATCH', '/general/secretarias/' + x.id + '/clave').then(function (r) { recargarConAviso(r.mensaje); });
            });
    });

    pintarKpis();
    filtrar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
    var nuevaEn = (location.search.match(/[?&]nueva=(\d+)/) || [])[1];
    if (nuevaEn) abrirForm(null, +nuevaEn);
}());
