/* =========================================================
   ESAT — Panel Admin (ESAT general): Docentes
   Docentes de todas las sedes: crear y editar en 3 pasos (eligiendo la sede), activar/desactivar (no con grupos vigentes) y restablecer la clave, con las reglas de la secretaría.
   Los datos llegan en window.GEN_DATOS (AdminGeneral DocenteController@index); guardar va por fetch a /general/docentes
   (las reglas de la secretaría, en la sede elegida) y recarga con el aviso. Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 12;
    var ICON = {
        docente: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/></svg>',
        ok: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        estudiantes: '<svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/></svg>',
        quitar: '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        editar: '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
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

    var toastEl = $('dcn-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="dcn-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="dcn-pg-gap">…</span>'
                    : '<button type="button" class="dcn-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="dcn-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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

    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    function descargarCsv(base, filas) {
        var csv = '﻿' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
        var h = new Date(), dd = function (n) { return (n < 10 ? '0' : '') + n; };
        var nombre = base + '-' + h.getFullYear() + '-' + dd(h.getMonth() + 1) + '-' + dd(h.getDate()) + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a'); a.href = url; a.download = nombre;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        toast('Se descargó ' + nombre + ' con ' + (filas.length - 1) + (filas.length === 2 ? ' fila.' : ' filas.'));
    }
    function fmtTel(t) { var d = String(t || '').replace(/\D/g, ''); return d.length === 10 ? d.slice(0, 3) + ' ' + d.slice(3, 6) + ' ' + d.slice(6) : esc(t); }
    function edad(f) {
        if (!f) return null;
        var n = new Date(f + 'T12:00:00'), h = new Date(), e = h.getFullYear() - n.getFullYear();
        if (h.getMonth() < n.getMonth() || (h.getMonth() === n.getMonth() && h.getDate() < n.getDate())) e--;
        return e;
    }

    /* ── Datos ──────────────────────────────────── */
    var VINC = { tiempo_completo: 'Tiempo completo', medio_tiempo: 'Medio tiempo', hora_catedra: 'Hora cátedra' };
    var ESTADO = { activo: ['Activo', 'active'], en_proceso: ['En proceso', 'warn'], inactivo: ['Inactivo', 'muted'] };
    var G = { femenino: 'Femenino', masculino: 'Masculino', otro: 'Otro' };
    function sede(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function escuela(id) { return D.escuelas.find(function (e) { return e.id === +id; }); }
    function programa(id) { return D.programas.find(function (p) { return p.id === +id; }); }
    function nombre(d) { return d.nombres + ' ' + d.apellidos; }
    function iniciales(d) { return ((d.nombres || '').charAt(0) + (d.apellidos || '').charAt(0)).toUpperCase(); }
    function gruposDe(d, vig) { return D.grupos.filter(function (g) { return g.docente_id === d.id && (!vig || g.estado !== 'finalizado'); }); }
    function tag(par) { return '<span class="app-status-tag app-status-' + par[1] + '">' + esc(par[0]) + '</span>'; }

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var act = D.docentes.filter(function (d) { return d.estado === 'activo'; });
        var sinGrupo = act.filter(function (d) { return !gruposDe(d, true).length; }).length;
        var k = [['blue', 'docente', num(D.docentes.length), 'Docentes'], ['green', 'ok', num(act.length), act.length === 1 ? 'Activo' : 'Activos'],
            ['blue', 'reloj', num(act.filter(function (d) { return d.vinculacion === 'tiempo_completo'; }).length), 'Tiempo completo'],
            [sinGrupo ? 'red' : 'green', 'alerta', num(sinGrupo), 'Activos sin grupos vigentes']];
        $('dcn-kpis').innerHTML = k.map(function (x) { return '<div class="dcn-kpi dcn-kpi-' + x[0] + '"><div class="dcn-kpi-icon">' + ICON[x[1]] + '</div><div><div class="dcn-kpi-val">' + esc(x[2]) + '</div><div class="dcn-kpi-lbl">' + esc(x[3]) + '</div></div></div>'; }).join('');
    }

    /* ── Filtros y tabla ────────────────────────── */
    var cbSede = $('dcn-cb-sede'), cbEsc = $('dcn-cb-escuela'), cbEst = $('dcn-cb-estado');
    [cbSede, cbEsc, cbEst].forEach(initCombobox);
    cbSede.setOptions([{ value: '', label: 'Todas las sedes' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: s.nombre }; })));
    cbEsc.setOptions([{ value: '', label: 'Todas las escuelas' }].concat(D.escuelas.map(function (e) { return { value: String(e.id), label: e.nombre }; })));
    cbEst.setOptions([{ value: '', label: 'Todos los estados' }, { value: 'activo', label: 'Activos' }, { value: 'en_proceso', label: 'En proceso' }, { value: 'inactivo', label: 'Inactivos' }]);
    cbSede.setValue('', 'Todas las sedes', { silent: true }); cbEsc.setValue('', 'Todas las escuelas', { silent: true }); cbEst.setValue('', 'Todos los estados', { silent: true });
    var pagina = 1, filtrados = [];
    function filtrar() {
        var q = normal($('dcn-buscar').value.trim()), s = cbSede.value(), e = cbEsc.value(), st = cbEst.value();
        filtrados = D.docentes.filter(function (d) {
            if (q && normal(nombre(d) + ' ' + d.numero_documento + ' ' + d.email).indexOf(q) === -1) return false;
            if (s && d.sedes.map(String).indexOf(s) < 0) return false;
            if (e && String(d.escuela_id) !== e) return false;
            if (st && d.estado !== st) return false;
            return true;
        }).sort(function (a, b) { return (a.estado === 'inactivo') - (b.estado === 'inactivo') || nombre(a).localeCompare(nombre(b), 'es'); });
        pagina = 1; pintarTabla();
    }
    function pintarTabla() {
        var desde = (pagina - 1) * POR_PAGINA;
        $('dcn-count').textContent = filtrados.length + (filtrados.length === 1 ? ' docente' : ' docentes');
        $('dcn-empty').hidden = filtrados.length > 0;
        $('dcn-body').innerHTML = filtrados.slice(desde, desde + POR_PAGINA).map(function (d) {
            var es = escuela(d.escuela_id);
            return '<tr class="dcn-row" tabindex="0" data-id="' + d.id + '"><td><div class="dcn-cell"><div class="dcn-avatar-txt' + (d.estado === 'inactivo' ? ' is-off' : '') + '">' + esc(iniciales(d)) + '</div>' +
                '<div><div class="dcn-strong">' + esc(nombre(d)) + '</div><div class="dcn-muted">' + esc(d.tipo_documento + ' ' + d.numero_documento) + '</div></div></div></td>' +
                '<td><div class="dcn-tags">' + d.sedes.map(function (id) { return '<span class="dcn-tag">' + esc(sede(id).nombre) + '</span>'; }).join('') + '</div></td>' +
                '<td>' + esc(es ? es.nombre : '—') + '</td><td>' + esc(VINC[d.vinculacion]) + '</td><td class="dcn-num">' + gruposDe(d, true).length + '</td>' +
                '<td>' + tag(ESTADO[d.estado]) + '</td><td class="dcn-actions"><button type="button" class="dcn-icon-btn" data-editar="' + d.id + '" aria-label="Editar a ' + esc(nombre(d)) + '">' + ICON.editar + '</button></td></tr>';
        }).join('');
        paginar($('dcn-paginacion'), filtrados.length, pagina, function (p) { pagina = p; pintarTabla(); });
    }
    $('dcn-buscar').addEventListener('input', filtrar);
    [cbSede, cbEsc, cbEst].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', filtrar); });
    $('dcn-body').addEventListener('click', function (e) {
        var ed = e.target.closest('[data-editar]'); if (ed) { e.stopPropagation(); abrirForm(+ed.dataset.editar); return; }
        var tr = e.target.closest('tr[data-id]'); if (tr) abrirDrawer(+tr.dataset.id);
    });
    $('dcn-body').addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirDrawer(+e.target.dataset.id); } });
    $('dcn-exportar').addEventListener('click', function () {
        descargarCsv('docentes-esat', [['Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Sedes', 'Escuela', 'Título profesional', 'Especialidad', 'Vinculación', 'Estado', 'Grupos vigentes', 'Celular', 'Correo', 'Departamento', 'Ciudad', 'Dirección']]
            .concat(filtrados.map(function (d) { var es = escuela(d.escuela_id); return [d.nombres, d.apellidos, d.tipo_documento, d.numero_documento, d.sedes.map(function (id) { return sede(id).nombre; }).join(', '), es ? es.nombre : '', d.profesion, d.especialidad, VINC[d.vinculacion], ESTADO[d.estado][0], gruposDe(d, true).length, d.telefono, d.email, d.departamento, d.ciudad, d.direccion]; })));
    });

    /* ── Drawer ─────────────────────────────────── */
    var drawer = $('dcn-drawer'), actual = null, volverDrawer = null;
    function tab(n) {
        drawer.querySelectorAll('.dcn-tab').forEach(function (b) { var on = b.dataset.tab === n; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        drawer.querySelectorAll('.dcn-panel').forEach(function (p) { p.hidden = p.dataset.panel !== n; });
    }
    drawer.querySelectorAll('.dcn-tab').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });
    function info(f) { return '<div class="dcn-info">' + f.map(function (x) { return '<div' + (x[2] ? ' class="dcn-info-full"' : '') + '><span class="dcn-info-lbl">' + esc(x[0]) + '</span><span class="dcn-info-val">' + (x[3] ? x[1] : esc(x[1])) + '</span></div>'; }).join('') + '</div>'; }
    function abrirDrawer(id, pestana) {
        var d = D.docentes.find(function (x) { return x.id === id; }); if (!d) return;
        if (!drawer.classList.contains('is-open')) volverDrawer = document.activeElement;
        actual = d;
        var es = escuela(d.escuela_id);
        $('dcn-dw-nombre').textContent = nombre(d);
        $('dcn-dw-sub').innerHTML = esc(d.tipo_documento + ' ' + d.numero_documento + ' · ' + d.sedes.map(function (x) { return 'Sede ' + sede(x).nombre; }).join(', ')) + ' ' + tag(ESTADO[d.estado]);
        $('dcn-dw-datos').innerHTML = info([['Correo (usuario)', d.email], ['Celular', fmtTel(d.telefono), false, true], ['Fecha de nacimiento', d.fecha_nacimiento ? fechaLarga(d.fecha_nacimiento) + ' (' + edad(d.fecha_nacimiento) + ' años)' : '—'],
            ['Género', G[d.genero] || '—'], ['Residencia', d.ciudad + ', ' + d.departamento], ['Dirección', d.direccion || '—']]);
        $('dcn-dw-perfil').innerHTML = info([['Título profesional', d.profesion || '—', true], ['Especialidad', d.especialidad || '—', true], ['Escuela', es ? es.nombre : '—'], ['Vinculación', VINC[d.vinculacion]]]);
        var gs = gruposDe(d);
        $('dcn-dw-grupos').innerHTML = gs.length ? '<ul class="dcn-list">' + gs.map(function (g) {
            var p = programa(g.programa_id), e = { en_clases: ['En clases', 'active'], por_iniciar: ['Por iniciar', 'warn'], finalizado: ['Finalizado', 'muted'] }[g.estado];
            return '<li><div><div class="dcn-strong">' + esc(p.nombre.replace('Técnico Laboral en ', '') + ' · ' + g.nombre) + '</div><div class="dcn-muted">' + esc(sede(g.sede_id).nombre + ' · ' + g.jornada + ' · ' + fechaLarga(g.inicio) + ' a ' + fechaLarga(g.fin)) + '</div></div>' + tag(e) + '</li>';
        }).join('') + '</ul><p class="dcn-hint dcn-hint-grupos">Los grupos y horarios se asignan en la sede (Grupos y Horarios de la secretaría).</p>' : '<p class="dcn-muted">No tiene grupos asignados.</p>';
        $('dcn-dw-estado').textContent = d.estado === 'inactivo' ? 'Activar' : 'Desactivar';
        tab(pestana || 'datos');
        drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('.dcn-close').focus();
    }
    function cerrarDrawer() { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); if (volverDrawer && document.contains(volverDrawer)) volverDrawer.focus(); }
    drawer.querySelectorAll('[data-cerrar-drawer]').forEach(function (b) { b.addEventListener('click', cerrarDrawer); });

    /* ── Modales ────────────────────────────────── */
    var modalAbierto = null, volverModal = null, pila = [];
    function abrirModal(id, foco) { if (modalAbierto) pila.push(modalAbierto); else volverModal = document.activeElement; modalAbierto = $(id); modalAbierto.hidden = false; (foco || modalAbierto.querySelector('input, select, button')).focus(); }
    function cerrarModal() { if (!modalAbierto) return; modalAbierto.hidden = true; modalAbierto = pila.pop() || null; if (!modalAbierto && volverModal && document.contains(volverModal)) volverModal.focus(); }
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || document.querySelector('.app-combobox.is-open')) return;
        if (modalAbierto) cerrarModal(); else if (drawer.classList.contains('is-open')) cerrarDrawer();
    });
    var alConfirmar = null;
    function confirmar(titulo, texto, boton, accion) { $('dcn-conf-titulo').textContent = titulo; $('dcn-conf-texto').textContent = texto; $('dcn-conf-ok').textContent = boton; alConfirmar = accion; abrirModal('dcn-confirmar', $('dcn-conf-ok')); }
    $('dcn-conf-ok').addEventListener('click', function () { var b = this; b.disabled = true; alConfirmar().catch(function (err) { b.disabled = false; cerrarModal(); toast(err.message); }); });

    $('dcn-dw-estado').addEventListener('click', function () {
        var d = actual;
        if (d.estado !== 'inactivo') {
            var gv = gruposDe(d, true);
            if (gv.length) { toast('No se puede desactivar: tiene ' + gv.length + ' grupo(s) vigente(s). Asígnales otro docente en Grupos de la sede primero.'); return; }
            confirmar('¿Desactivar a ' + nombre(d) + '?', 'No podrá entrar a la plataforma y no se le podrán asignar grupos. Su historial se conserva.', 'Desactivar', function () {
                return enviar('PATCH', '/general/docentes/' + d.id + '/estado').then(function (r) { recargarConAviso(r.mensaje); });
            });
        } else {
            confirmar('¿Activar a ' + nombre(d) + '?', 'Podrá entrar otra vez y recibir grupos.', 'Activar', function () {
                return enviar('PATCH', '/general/docentes/' + d.id + '/estado').then(function (r) { recargarConAviso(r.mensaje); });
            });
        }
    });
    $('dcn-dw-clave').addEventListener('click', function () {
        var d = actual;
        confirmar('¿Restablecer la clave de ' + nombre(d) + '?', 'Su clave volverá a ser su número de documento (' + d.numero_documento + ') y se cerrarán sus sesiones abiertas.', 'Restablecer clave', function () {
            return enviar('PATCH', '/general/docentes/' + d.id + '/clave').then(function (r) { recargarConAviso(r.mensaje); });
        });
    });

    /* ── Formulario (3 pasos) ───────────────────── */
    var cbDepto = $('dcn-cb-depto'), cbCiudad = $('dcn-cb-ciudad');
    initCombobox(cbDepto); initCombobox(cbCiudad);
    var colombia = [], listo = fetch('/assets/data/co-departamentos-ciudades.json').then(function (r) { return r.json(); })
        .then(function (d) { colombia = d; cbDepto.setOptions(d.map(function (x) { return x.departamento; })); })
        .catch(function () { toast('No se pudo cargar la lista de departamentos. Recarga la página.'); });
    function ciudadesDe(dep) { var x = colombia.find(function (c) { return c.departamento === dep; }); return x ? x.ciudades : []; }
    cbDepto.querySelector('[data-combobox-value]').addEventListener('change', function () { cbCiudad.reset('Selecciona una ciudad'); cbCiudad.setOptions(ciudadesDe(cbDepto.value())); err('depto', ''); });
    cbCiudad.querySelector('[data-combobox-value]').addEventListener('change', function () { err('ciudad', ''); });
    $('dcn-f-sede').innerHTML = '<option value="">Selecciona</option>' + D.sedes.map(function (s) { return '<option value="' + s.id + '">' + esc(s.nombre) + '</option>'; }).join('');
    $('dcn-f-escuela').innerHTML = '<option value="">Selecciona</option>' + D.escuelas.map(function (e) { return '<option value="' + e.id + '">' + esc(e.nombre) + '</option>'; }).join('');

    var paso = 1, editando = null, CAMPOS = { 1: ['nombres', 'apellidos', 'documento', 'nacimiento', 'genero'], 2: ['telefono', 'correo', 'depto', 'ciudad', 'direccion'], 3: ['sede', 'escuela', 'profesion', 'especialidad'] };
    function f(id) { return $('dcn-f-' + id); }
    function err(c, m) {
        var e = document.querySelector('#dcn-form [data-error="' + c + '"]'); if (!e) return;
        e.textContent = m || ''; e.hidden = !m;
        if (c === 'depto') cbDepto.classList.toggle('is-invalid', !!m); else if (c === 'ciudad') cbCiudad.classList.toggle('is-invalid', !!m); else if (f(c)) f(c).classList.toggle('is-invalid', !!m);
    }
    function validar(p) {
        var v = function (id) { return f(id).value.trim(); };
        if (p === 1) {
            err('nombres', v('nombres').length < 2 ? 'Escribe los nombres.' : ''); err('apellidos', v('apellidos').length < 2 ? 'Escribe los apellidos.' : '');
            var doc = v('documento').replace(/\D/g, '');
            err('documento', !/^\d{6,12}$/.test(doc) ? 'Escribe el documento (solo números, de 6 a 12).' : '');
            var e = edad(v('nacimiento'));
            err('nacimiento', !v('nacimiento') ? 'Escribe la fecha de nacimiento.' : e < 18 ? 'Debe ser mayor de edad.' : e > 85 ? 'Revisa la fecha.' : '');
            err('genero', v('genero') ? '' : 'Elige el género.');
        }
        if (p === 2) {
            var tel = v('telefono').replace(/\D/g, ''), correo = v('correo').toLowerCase();
            err('telefono', !/^3\d{9}$/.test(tel) ? 'Escribe un celular de 10 dígitos que empiece por 3.' : '');
            err('correo', !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(correo) ? 'Escribe un correo válido.' : '');
            err('depto', cbDepto.value() ? '' : 'Elige el departamento.'); err('ciudad', cbCiudad.value() ? '' : 'Elige la ciudad.');
            err('direccion', v('direccion').length < 5 ? 'Escribe la dirección.' : '');
        }
        if (p === 3) {
            err('sede', v('sede') ? '' : 'Elige la sede.'); err('escuela', v('escuela') ? '' : 'Elige la escuela.');
            err('profesion', v('profesion').length < 3 ? 'Escribe el título profesional.' : ''); err('especialidad', v('especialidad').length < 3 ? 'Escribe la especialidad.' : '');
            if (editando && +v('sede') !== editando.sedes[0] && gruposDe(editando, true).some(function (g) { return g.sede_id === editando.sedes[0]; })) err('sede', 'Tiene grupos vigentes en ' + sede(editando.sedes[0]).nombre + ': no se puede cambiar de sede todavía.');
        }
        var malo = CAMPOS[p].find(function (c) { var e = document.querySelector('#dcn-form [data-error="' + c + '"]'); return e && !e.hidden; });
        if (malo) { if (malo === 'depto') cbDepto.querySelector('button').focus(); else if (malo === 'ciudad') cbCiudad.querySelector('button').focus(); else f(malo).focus(); }
        return !malo;
    }
    function resumen() {
        var s = sede(f('sede').value), es = escuela(f('escuela').value);
        $('dcn-resumen').innerHTML = [['Nombre', f('nombres').value.trim() + ' ' + f('apellidos').value.trim()], ['Documento', f('tipo').value + ' ' + f('documento').value.replace(/\D/g, '')],
            ['Correo', f('correo').value.trim().toLowerCase()], ['Celular', f('telefono').value.replace(/\D/g, '')], ['Sede', s ? s.nombre : 'Sin elegir'], ['Escuela', es ? es.nombre : 'Sin elegir']]
            .map(function (x) { return '<div><span class="dcn-info-lbl">' + esc(x[0]) + '</span><span class="dcn-info-val">' + esc(x[1]) + '</span></div>'; }).join('');
    }
    ['sede', 'escuela'].forEach(function (k) { f(k).addEventListener('change', resumen); });
    function irPaso(p) {
        paso = p;
        document.querySelectorAll('#dcn-form .dcn-paso').forEach(function (el) { el.hidden = +el.dataset.paso !== p; });
        document.querySelectorAll('#dcn-form .dcn-step').forEach(function (el) { var n = +el.dataset.step; el.classList.toggle('is-active', n === p); el.classList.toggle('is-done', n < p); });
        $('dcn-modal-sub').textContent = 'Paso ' + p + ' de 3';
        $('dcn-atras').hidden = p === 1;
        $('dcn-siguiente').textContent = p < 3 ? 'Siguiente' : editando ? 'Guardar cambios' : 'Registrar docente';
        if (p === 3) resumen();
    }
    f('nacimiento').addEventListener('input', function () { var e = edad(this.value); $('dcn-edad').textContent = e != null && e >= 0 ? e + ' años' : ''; });
    document.querySelectorAll('#dcn-form input, #dcn-form select').forEach(function (i) { var k = (i.id || '').replace('dcn-f-', ''); if (k) i.addEventListener('input', function () { err(k, ''); }); });
    function abrirForm(id) {
        editando = id ? D.docentes.find(function (x) { return x.id === id; }) : null;
        var d = editando || { nombres: '', apellidos: '', tipo_documento: 'CC', numero_documento: '', fecha_nacimiento: '', genero: '', telefono: '', email: '', departamento: '', ciudad: '', direccion: '',
            sedes: [], escuela_id: '', profesion: '', especialidad: '', vinculacion: 'hora_catedra', estado: 'activo' };
        f('nombres').value = d.nombres; f('apellidos').value = d.apellidos; f('tipo').value = d.tipo_documento; f('documento').value = d.numero_documento;
        f('nacimiento').value = d.fecha_nacimiento; f('nacimiento').max = new Date().toISOString().slice(0, 10); f('nacimiento').dispatchEvent(new Event('input'));
        f('genero').value = d.genero; f('telefono').value = d.telefono; f('correo').value = d.email; f('direccion').value = d.direccion;
        f('sede').value = d.sedes[0] || ''; f('escuela').value = d.escuela_id || ''; f('profesion').value = d.profesion; f('especialidad').value = d.especialidad;
        f('vinculacion').value = d.vinculacion; f('estado').value = d.estado === 'en_proceso' ? 'en_proceso' : 'activo';
        f('estado').closest('.dcn-field').hidden = !!editando;   // el estado de alguien ya registrado se cambia con Activar/Desactivar
        cbDepto.setValue('', 'Selecciona un departamento', { silent: true }); cbCiudad.reset('Elige el departamento primero');
        if (d.departamento) listo.then(function () { cbDepto.setValue(d.departamento, d.departamento, { silent: true }); cbCiudad.setOptions(ciudadesDe(d.departamento)); cbCiudad.setValue(d.ciudad, d.ciudad, { silent: true }); });
        document.querySelectorAll('#dcn-form .dcn-field-error').forEach(function (e) { e.hidden = true; });
        document.querySelectorAll('#dcn-form .is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        $('dcn-modal-titulo').textContent = editando ? 'Editar a ' + nombre(editando) : 'Nuevo docente';
        $('dcn-nota-clave').hidden = !!editando;
        abrirModal('dcn-modal', f('nombres'));
        irPaso(1);
    }
    $('dcn-nuevo').addEventListener('click', function () { abrirForm(null); });
    $('dcn-dw-editar').addEventListener('click', function () { abrirForm(actual.id); });
    $('dcn-atras').addEventListener('click', function () { if (paso > 1) irPaso(paso - 1); });
    $('dcn-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validar(paso)) return;
        if (paso < 3) { irPaso(paso + 1); return; }
        var cuerpo = { nombres: f('nombres').value.trim(), apellidos: f('apellidos').value.trim(), tipo_documento: f('tipo').value, numero_documento: f('documento').value,
            fecha_nacimiento: f('nacimiento').value, genero: f('genero').value, telefono: f('telefono').value, email: f('correo').value.trim(),
            departamento_residencia: cbDepto.value(), ciudad_residencia: cbCiudad.value(), direccion: f('direccion').value.trim(), sede_id: +f('sede').value,
            escuela_id: +f('escuela').value, profesion: f('profesion').value.trim(), titulo_academico: f('especialidad').value.trim(), vinculacion: f('vinculacion').value,
            /* El estado de alguien ya registrado se cambia con Activar/Desactivar */
            estado: editando ? editando.estado : f('estado').value };
        var btn = $('dcn-siguiente'); btn.disabled = true;
        enviar(editando ? 'PUT' : 'POST', editando ? '/general/docentes/' + editando.id : '/general/docentes', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (fallo) {
                btn.disabled = false;
                var CAMPO = { nombres: 'nombres', apellidos: 'apellidos', tipo_documento: 'documento', numero_documento: 'documento', fecha_nacimiento: 'nacimiento', genero: 'genero',
                    telefono: 'telefono', email: 'correo', departamento_residencia: 'depto', ciudad_residencia: 'ciudad', direccion: 'direccion', sede_id: 'sede',
                    escuela_id: 'escuela', profesion: 'profesion', titulo_academico: 'especialidad', vinculacion: 'escuela', estado: 'sede' };
                var k = Object.keys(fallo.errores || {}).find(function (c) { return CAMPO[c]; });
                if (!k) { toast(fallo.message); return; }
                var c = CAMPO[k], p = +Object.keys(CAMPOS).find(function (n) { return CAMPOS[n].indexOf(c) >= 0; });
                irPaso(p); err(c, fallo.errores[k][0]);
            });
    });

    pintarKpis();
    filtrar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
}());
