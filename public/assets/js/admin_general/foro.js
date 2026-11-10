/* =========================================================
   ESAT — Panel Admin (ESAT general): Foro
   Los foros de todas las sedes: lista con filtros, el tema con sus mensajes, moderación (ocultar con motivo,
   restaurar, descartar reportes, fijar, cerrar/reabrir) y participación como Admin (responder, abrir temas).
   Los datos llegan en window.GEN_DATOS (AdminGeneral ForoController@index); cada acción va por fetch a /general/foro
   (las reglas de la secretaría) y recarga con el aviso. Los mensajes se pintan escapados (nunca como HTML).
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 10;
    var ICON = {
        tema: '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        bandera: '<svg viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        ojo: '<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
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

    var toastEl = $('for-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="for-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="for-pg-gap">…</span>'
                    : '<button type="button" class="for-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="for-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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
    var CATEGORIAS = D.categorias;
    var ROL = { estudiante: 'Estudiante', docente: 'Docente', acudiente: 'Acudiente', secretaria: 'Secretaría', admin: 'Admin' };
    function sede(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function tema(id) { return D.foro.find(function (t) { return t.id === +id; }); }
    function primero(t) { return t.mensajes[0]; }
    function temaOculto(t) { return !!primero(t).oculto; }
    function pendientes(t) { return t.mensajes.reduce(function (n, m) { return n + m.reportes.filter(function (r) { return r.estado === 'pendiente'; }).length; }, 0); }
    function ocultos(t) { return t.mensajes.filter(function (m) { return m.oculto; }).length; }
    function ultima(t) { return t.mensajes.reduce(function (f, m) { return m.fecha > f ? m.fecha : f; }, primero(t).fecha); }
    function fechaHora(f) { var d = new Date(f); return fechaLarga(f) + ', ' + d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' }); }
    function iniciales(n) { var p = String(n || '').split(/\s+/); return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase(); }
    function accion(metodo, url, cuerpo, alFallar) {
        return enviar(metodo, url, cuerpo).then(function (r) { recargarConAviso(r.mensaje); }).catch(function (err) { if (alFallar) alFallar(); toast(err.message); });
    }

    /* ── Sede y filtros ─────────────────────────── */
    var sel = '', pagina = 1, filtrados = [];
    try { sel = localStorage.getItem('esat.general.foro.sede') || ''; } catch (e) {}
    if (sel && !sede(sel)) sel = '';
    var chips = $('for-chips');
    D.sedes.forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'for-chip'; b.setAttribute('role', 'radio'); b.dataset.sede = s.id; b.textContent = s.nombre;
        chips.appendChild(b);
    });
    chips.addEventListener('click', function (e) {
        var b = e.target.closest('.for-chip'); if (!b) return;
        sel = b.dataset.sede || ''; try { localStorage.setItem('esat.general.foro.sede', sel); } catch (err) {}
        pintar();
    });
    var cbCat = $('for-cb-categoria'), cbEst = $('for-cb-estado');
    initCombobox(cbCat); initCombobox(cbEst);
    cbCat.setOptions([{ value: '', label: 'Todas las categorías' }].concat(CATEGORIAS));
    cbEst.setOptions([{ value: '', label: 'Todos los temas' }, { value: 'reportes', label: 'Con reportes por atender' }, { value: 'fijados', label: 'Fijados' }, { value: 'cerrados', label: 'Cerrados' }, { value: 'ocultos', label: 'Con mensajes ocultos' }, { value: 'admin', label: 'Donde participé' }]);
    cbCat.setValue('', 'Todas las categorías', { silent: true }); cbEst.setValue('', 'Todos los temas', { silent: true });
    if (/[?&]reportes=1/.test(location.search)) cbEst.setValue('reportes', 'Con reportes por atender', { silent: true });
    $('for-buscar').addEventListener('input', function () { pagina = 1; pintarTabla(); });
    [cbCat, cbEst].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', function () { pagina = 1; pintarTabla(); }); });

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var l = D.foro.filter(function (t) { return !sel || String(t.sede_id) === sel; });
        var semana = l.filter(function (t) { return Date.now() - new Date(ultima(t)) < 7 * 86400000; }).length;
        var rep = l.reduce(function (n, t) { return n + pendientes(t); }, 0), oc = l.reduce(function (n, t) { return n + ocultos(t); }, 0);
        var k = [['blue', 'tema', num(l.length), l.length === 1 ? 'Tema' : 'Temas'], ['green', 'reloj', num(semana), 'Con actividad esta semana'],
            [rep ? 'red' : 'green', 'bandera', num(rep), rep === 1 ? 'Reporte por atender' : 'Reportes por atender'], ['orange', 'ojo', num(oc), oc === 1 ? 'Mensaje oculto' : 'Mensajes ocultos']];
        $('for-kpis').innerHTML = k.map(function (x) { return '<div class="for-kpi for-kpi-' + x[0] + '"><div class="for-kpi-icon">' + ICON[x[1]] + '</div><div><div class="for-kpi-val">' + esc(x[2]) + '</div><div class="for-kpi-lbl">' + esc(x[3]) + '</div></div></div>'; }).join('');
    }

    /* ── Tabla ──────────────────────────────────── */
    function pintarTabla() {
        var q = normal($('for-buscar').value.trim()), cat = cbCat.value(), est = cbEst.value();
        filtrados = D.foro.filter(function (t) {
            if (sel && String(t.sede_id) !== sel) return false;
            if (cat && t.categoria !== cat) return false;
            if (est === 'reportes' && !pendientes(t)) return false;
            if (est === 'fijados' && !t.fijado) return false;
            if (est === 'cerrados' && !t.cerrado) return false;
            if (est === 'ocultos' && !ocultos(t)) return false;
            if (est === 'admin' && !t.mensajes.some(function (m) { return m.rol === 'admin'; })) return false;
            return !q || normal(t.titulo + ' ' + t.mensajes.map(function (m) { return m.texto; }).join(' ')).indexOf(q) !== -1;
        }).sort(function (a, b) { return (pendientes(b) > 0) - (pendientes(a) > 0) || (b.fijado - a.fijado) || (new Date(ultima(b)) - new Date(ultima(a))); });
        var desde = (pagina - 1) * POR_PAGINA;
        $('for-count').textContent = filtrados.length + (filtrados.length === 1 ? ' tema' : ' temas');
        $('for-empty').hidden = filtrados.length > 0;
        $('for-body').innerHTML = filtrados.slice(desde, desde + POR_PAGINA).map(function (t) {
            var p = pendientes(t), m0 = primero(t), marcas = [];
            if (p) marcas.push('<span class="app-status-tag app-status-danger">' + p + (p === 1 ? ' reporte' : ' reportes') + '</span>');
            if (t.fijado) marcas.push('<span class="for-tag">Fijado</span>');
            if (t.cerrado) marcas.push('<span class="for-tag">Cerrado</span>');
            if (temaOculto(t)) marcas.push('<span class="app-status-tag app-status-muted">Tema oculto</span>');
            else if (ocultos(t)) marcas.push('<span class="for-tag">' + ocultos(t) + ' oculto' + (ocultos(t) === 1 ? '' : 's') + '</span>');
            return '<tr class="for-row" tabindex="0" data-id="' + t.id + '"><td><div class="for-strong">' + esc(t.titulo) + '</div><div class="for-muted">' + esc(t.categoria) + '</div></td>' +
                '<td>' + esc(sede(t.sede_id).nombre) + '</td><td>' + esc(m0.autor) + '<div class="for-muted">' + esc(ROL[m0.rol]) + '</div></td>' +
                '<td class="for-num">' + t.mensajes.length + '</td><td>' + esc(hace(ultima(t)).charAt(0).toUpperCase() + hace(ultima(t)).slice(1)) + '</td>' +
                '<td><div class="for-tags">' + (marcas.join('') || '<span class="for-muted">—</span>') + '</div></td></tr>';
        }).join('');
        paginar($('for-paginacion'), filtrados.length, pagina, function (p) { pagina = p; pintarTabla(); });
    }
    $('for-body').addEventListener('click', function (e) { var tr = e.target.closest('tr[data-id]'); if (tr) abrirTema(+tr.dataset.id); });
    $('for-body').addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirTema(+e.target.dataset.id); } });

    /* ── El tema (drawer) ───────────────────────── */
    var drawer = $('for-drawer'), actual = null, volverDrawer = null;
    function pintarHilo() {
        var t = actual;
        $('for-dw-titulo').textContent = t.titulo;
        $('for-dw-sub').textContent = 'Foro de la sede ' + sede(t.sede_id).nombre + ' · ' + t.categoria + (t.cerrado ? ' · Cerrado' : '') + (t.fijado ? ' · Fijado' : '');
        $('for-dw-fijar').textContent = t.fijado ? 'Quitar de fijados' : 'Fijar arriba';
        $('for-dw-cerrar').textContent = t.cerrado ? 'Reabrir tema' : 'Cerrar tema';
        $('for-hilo').innerHTML = t.mensajes.map(function (m, i) {
            var pend = m.reportes.filter(function (r) { return r.estado === 'pendiente'; });
            var acciones = m.oculto ? '<button type="button" class="for-btn for-btn-ghost for-btn-sm" data-restaurar="' + m.id + '">Restaurar</button>'
                : '<button type="button" class="for-btn for-btn-ghost for-btn-sm" data-ocultar="' + m.id + '">Ocultar</button>' + (pend.length ? '<button type="button" class="for-btn for-btn-ghost for-btn-sm" data-descartar="' + m.id + '">Descartar reportes</button>' : '');
            return '<li class="for-msj' + (m.oculto ? ' is-oculto' : '') + (pend.length ? ' is-reportado' : '') + (m.rol === 'admin' ? ' is-admin' : '') + '">' +
                '<div class="for-msj-av for-rol-' + esc(m.rol) + '">' + esc(iniciales(m.autor)) + '</div><div class="for-msj-cuerpo">' +
                '<div class="for-msj-cab"><span class="for-strong">' + esc(m.autor) + '</span><span class="for-tag">' + esc(ROL[m.rol]) + '</span>' + (i === 0 ? '<span class="for-muted">abrió el tema</span>' : '') +
                '<span class="for-muted for-msj-fecha">' + esc(fechaHora(m.fecha)) + '</span></div>' +
                '<p class="for-msj-texto">' + esc(m.texto) + '</p>' +
                (m.oculto ? '<p class="for-msj-aviso">Oculto por ' + esc(m.oculto.por) + ': ' + esc(m.oculto.motivo) + (i === 0 ? '. Al ocultar el primer mensaje se oculta todo el tema.' : '') + '</p>' : '') +
                (pend.length ? '<p class="for-msj-reporte">' + ICON.bandera + 'Reportado ' + pend.length + (pend.length === 1 ? ' vez' : ' veces') + ': ' + esc(pend.map(function (r) { return r.motivo; }).join(', ')) + '</p>' : '') +
                '<div class="for-msj-acciones">' + acciones + '</div></div></li>';
        }).join('');
        $('for-r-texto').disabled = t.cerrado; $('for-r-enviar').disabled = t.cerrado; $('for-cerrado-aviso').hidden = !t.cerrado;
    }
    function abrirTema(id) {
        actual = tema(id); if (!actual) return;
        if (!drawer.classList.contains('is-open')) volverDrawer = document.activeElement;
        $('for-r-texto').value = ''; $('for-e-respuesta').hidden = true;
        pintarHilo();
        drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('.for-close').focus();
    }
    function cerrarDrawer() { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); if (volverDrawer && document.contains(volverDrawer)) volverDrawer.focus(); }
    drawer.querySelectorAll('[data-cerrar-drawer]').forEach(function (b) { b.addEventListener('click', cerrarDrawer); });
    function msj(id) { return actual.mensajes.find(function (m) { return m.id === +id; }); }

    $('for-dw-fijar').addEventListener('click', function () {
        var b = this; b.disabled = true;
        accion('PATCH', '/general/foro/temas/' + actual.id + '/estado', { fijado: !actual.fijado }, function () { b.disabled = false; });
    });
    $('for-dw-cerrar').addEventListener('click', function () {
        var b = this; b.disabled = true;
        accion('PATCH', '/general/foro/temas/' + actual.id + '/estado', { cerrado: !actual.cerrado }, function () { b.disabled = false; });
    });
    $('for-hilo').addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        if (b.dataset.ocultar) { pedirOcultar(msj(b.dataset.ocultar)); return; }
        var listo = function () { b.disabled = false; };
        if (b.dataset.restaurar) { b.disabled = true; accion('PATCH', '/general/foro/mensajes/' + b.dataset.restaurar + '/restaurar', null, listo); }
        if (b.dataset.descartar) { b.disabled = true; accion('PATCH', '/general/foro/mensajes/' + b.dataset.descartar + '/descartar', null, listo); }
    });

    /* ── Modales ────────────────────────────────── */
    var modalAbierto = null, volverModal = null;
    function abrirModal(id, foco) { volverModal = document.activeElement; modalAbierto = $(id); modalAbierto.hidden = false; (foco || modalAbierto.querySelector('input, select, textarea, button')).focus(); }
    function cerrarModal() { if (!modalAbierto) return; modalAbierto.hidden = true; modalAbierto = null; if (volverModal && document.contains(volverModal)) volverModal.focus(); }
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || document.querySelector('.app-combobox.is-open')) return;
        if (modalAbierto) cerrarModal(); else if (drawer.classList.contains('is-open')) cerrarDrawer();
    });

    var ocultando = null;
    function pedirOcultar(m) {
        ocultando = m;
        var pend = m.reportes.filter(function (r) { return r.estado === 'pendiente'; });
        $('for-oc-texto').textContent = 'Nadie más verá el mensaje de ' + m.autor + (m === primero(actual) ? '. Es el que abre el tema: se oculta todo el tema.' : '.') + ' Los reportes pendientes quedan atendidos.';
        $('for-oc-motivo').value = pend.length ? pend[0].motivo : ''; $('for-e-motivo').hidden = true; $('for-oc-motivo').classList.remove('is-invalid');
        abrirModal('for-ocultar', $('for-oc-motivo'));
    }
    $('for-ocultar-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var motivo = $('for-oc-motivo').value.trim();
        if (motivo.length < 5) { $('for-e-motivo').textContent = 'Escribe el motivo (mínimo 5 caracteres).'; $('for-e-motivo').hidden = false; $('for-oc-motivo').classList.add('is-invalid'); $('for-oc-motivo').focus(); return; }
        var b = this.querySelector('[type=submit]'); if (b) b.disabled = true;
        accion('PATCH', '/general/foro/mensajes/' + ocultando.id + '/ocultar', { motivo: motivo }, function () { if (b) b.disabled = false; });
    });
    $('for-oc-motivo').addEventListener('input', function () { $('for-e-motivo').hidden = true; this.classList.remove('is-invalid'); });

    /* Responder */
    $('for-responder').addEventListener('submit', function (e) {
        e.preventDefault();
        var t = actual, texto = $('for-r-texto').value.trim();
        if (t.cerrado) return;
        if (texto.length < 2) { $('for-e-respuesta').textContent = 'Escribe la respuesta.'; $('for-e-respuesta').hidden = false; $('for-r-texto').focus(); return; }
        var b = $('for-r-enviar'); b.disabled = true;
        accion('POST', '/general/foro/temas/' + t.id + '/responder', { texto: texto }, function () { b.disabled = false; });
    });
    $('for-r-texto').addEventListener('input', function () { $('for-e-respuesta').hidden = true; });

    /* Nuevo tema */
    $('for-f-sede').innerHTML = '<option value="">Selecciona</option>' + D.sedes.map(function (s) { return '<option value="' + s.id + '">' + esc(s.nombre) + '</option>'; }).join('');
    $('for-f-categoria').innerHTML = '<option value="">Selecciona</option>' + CATEGORIAS.map(function (c) { return '<option>' + esc(c) + '</option>'; }).join('');
    function errF(c, m) { var e = document.querySelector('#for-form [data-error="' + c + '"]'); e.textContent = m || ''; e.hidden = !m; $('for-f-' + c).classList.toggle('is-invalid', !!m); }
    ['sede', 'categoria', 'titulo', 'texto'].forEach(function (c) { $('for-f-' + c).addEventListener('input', function () { errF(c, ''); }); });
    $('for-nuevo').addEventListener('click', function () {
        $('for-f-sede').value = sel || ''; $('for-f-categoria').value = ''; $('for-f-titulo').value = ''; $('for-f-texto').value = ''; $('for-f-fijar').checked = false;
        ['sede', 'categoria', 'titulo', 'texto'].forEach(function (c) { errF(c, ''); });
        abrirModal('for-modal', sel ? $('for-f-categoria') : $('for-f-sede'));
    });
    $('for-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var s = $('for-f-sede').value, c = $('for-f-categoria').value, ti = $('for-f-titulo').value.trim(), tx = $('for-f-texto').value.trim();
        errF('sede', s ? '' : 'Elige la sede.'); errF('categoria', c ? '' : 'Elige la categoría.');
        errF('titulo', ti.length < 5 ? 'Escribe un título de al menos 5 caracteres.' : ''); errF('texto', tx.length < 10 ? 'Escribe el mensaje (mínimo 10 caracteres).' : '');
        var malo = document.querySelector('#for-form .for-field-error:not([hidden])');
        if (malo) { malo.parentNode.querySelector('input, select, textarea').focus(); return; }
        var btn = this.querySelector('[type=submit]'); if (btn) btn.disabled = true;
        enviar('POST', '/general/foro/temas', { sede_id: +s, categoria: c, titulo: ti, texto: tx, fijado: $('for-f-fijar').checked })
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (fallo) {
                if (btn) btn.disabled = false;
                var CAMPO = { sede_id: 'sede', categoria: 'categoria', titulo: 'titulo', texto: 'texto' };
                var k = Object.keys(fallo.errores || {}).find(function (x) { return CAMPO[x]; });
                if (k) errF(CAMPO[k], fallo.errores[k][0]); else toast(fallo.message);
            });
    });

    function pintar() {
        chips.querySelectorAll('.for-chip').forEach(function (c) { var on = (c.dataset.sede || '') === sel; c.classList.toggle('is-active', on); c.setAttribute('aria-checked', on); });
        pintarKpis(); pagina = 1; pintarTabla();
    }
    pintar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
}());
