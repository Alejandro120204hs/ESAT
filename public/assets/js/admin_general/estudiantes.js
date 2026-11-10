/* =========================================================
   ESAT — Panel Admin (ESAT general): Estudiantes
   Consulta de los estudiantes de todas las sedes: filtros (sede, programa, matrícula, pagos), KPI, ficha
   (datos, matrícula, pagos) y exportar a Excel. No edita: para eso el Admin entra a la sede.
   Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 15;
    var ICON = {
        ver: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        estudiantes: '<svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/></svg>',
        ok: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
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

    var toastEl = $('est-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="est-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="est-pg-gap">…</span>'
                    : '<button type="button" class="est-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="est-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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

    function plata(n) { return '$ ' + num(n); }
    var MES_C = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
    function fechaMini(f) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f); return d.getDate() + ' ' + MES_C[d.getMonth()] + ' ' + d.getFullYear(); }
    var MES_C = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
    function fechaMini(f) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f); return d.getDate() + ' ' + MES_C[d.getMonth()] + ' ' + d.getFullYear(); }
    /* Exportar a Excel: CSV con ; y BOM para las tildes; neutraliza celdas que empiezan con = + - @ */
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

    /* ── Datos ──────────────────────────────────── */
    var ESTADO = { activo: ['Activo', 'active'], aplazado: ['Aplazado', 'warn'], retirado: ['Retirado', 'muted'], graduado: ['Graduado', 'active'], sin_matricula: ['Sin matrícula', 'muted'] };
    var PAGO = { al_dia: ['Al día', 'active'], pendiente: ['Debe este mes', 'warn'], vencido: ['En mora', 'danger'], sin_plan: ['Sin plan de pago', 'warn'], sin_matricula: ['—', 'muted'] };
    var VIGENTES = ['activo', 'aplazado'];
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function nombre(e) { return e.nombres + ' ' + e.apellidos; }
    function iniciales(e) { return (e.nombres.charAt(0) + e.apellidos.charAt(0)).toUpperCase(); }
    function tag(par) { return '<span class="app-status-tag app-status-' + par[1] + '">' + esc(par[0]) + '</span>'; }
    function fmtTel(t) { var d = String(t || '').replace(/\D/g, ''); return d.length === 10 ? d.slice(0, 3) + ' ' + d.slice(3, 6) + ' ' + d.slice(6) : esc(t); }
    function pagoDe(e) {
        if (e.estado === 'sin_matricula') return '<span class="est-muted">—</span>';
        if (!VIGENTES.includes(e.estado)) return '<span class="est-muted">Matrícula cerrada</span>';
        var p = PAGO[e.pago] || PAGO.al_dia;
        var sub = e.pago === 'vencido' ? plata(e.saldo_vencido) + ' vencidos' : e.pago === 'pendiente' ? plata(e.por_pagar_mes) + ' este mes' : '';
        return tag(p) + (sub ? '<div class="est-muted">' + esc(sub) + '</div>' : '');
    }

    /* ── KPI (de lo filtrado por sede y programa) ── */
    function pintarKpis(base) {
        var act = base.filter(function (e) { return e.estado === 'activo'; });
        var mora = act.filter(function (e) { return e.pago === 'vencido'; });
        var k = [
            ['blue', 'estudiantes', num(act.length), 'Estudiantes activos'],
            ['green', 'ok', num(act.filter(function (e) { return e.pago === 'al_dia'; }).length), 'Al día con los pagos'],
            ['orange', 'reloj', num(act.filter(function (e) { return e.pago === 'pendiente' || e.pago === 'sin_plan'; }).length), 'Deben este mes o sin plan'],
            ['red', 'alerta', num(mora.length), 'En mora · ' + plata(mora.reduce(function (t, e) { return t + e.saldo_vencido; }, 0))]
        ];
        $('est-kpis').innerHTML = k.map(function (x) {
            return '<div class="est-kpi est-kpi-' + x[0] + '"><div class="est-kpi-icon">' + ICON[x[1]] + '</div><div><div class="est-kpi-val">' + x[2] + '</div><div class="est-kpi-lbl">' + esc(x[3]) + '</div></div></div>';
        }).join('');
    }

    /* ── Filtros y tabla ────────────────────────── */
    var cbSede = $('est-cb-sede'), cbProg = $('est-cb-programa'), cbEstado = $('est-cb-estado'), cbPago = $('est-cb-pago');
    [cbSede, cbProg, cbEstado, cbPago].forEach(initCombobox);
    cbSede.setOptions([{ value: '', label: 'Todas las sedes' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: s.nombre }; })));
    function opcionesPrograma() {
        var s = cbSede.value() ? sedePorId(cbSede.value()) : null;
        var progs = D.programas.filter(function (p) { return !s || (s.programas || []).indexOf(p.id) >= 0; });
        cbProg.setOptions([{ value: '', label: 'Todos los programas' }].concat(progs.map(function (p) { return { value: String(p.id), label: p.nombre.replace('Técnico Laboral en ', '') }; })));
    }
    opcionesPrograma();
    cbEstado.setOptions([{ value: 'vigentes', label: 'Matrículas vigentes' }, { value: '', label: 'Todos' }, { value: 'activo', label: 'Activos' }, { value: 'aplazado', label: 'Aplazados' },
        { value: 'retirado', label: 'Retirados' }, { value: 'graduado', label: 'Graduados' }, { value: 'sin_matricula', label: 'Sin matrícula' }]);
    cbPago.setOptions([{ value: '', label: 'Todos los pagos' }, { value: 'al_dia', label: 'Al día' }, { value: 'pendiente', label: 'Deben este mes' }, { value: 'vencido', label: 'En mora' }, { value: 'sin_plan', label: 'Sin plan de pago' }]);
    cbSede.setValue('', 'Todas las sedes', { silent: true }); cbProg.setValue('', 'Todos los programas', { silent: true });
    cbEstado.setValue('vigentes', 'Matrículas vigentes', { silent: true }); cbPago.setValue('', 'Todos los pagos', { silent: true });

    var pagina = 1, filtrados = [];
    function filtrar() {
        var q = normal($('est-buscar').value.trim()), sede = cbSede.value(), prog = cbProg.value(), est = cbEstado.value(), pago = cbPago.value();
        var base = D.estudiantes.filter(function (e) { return (!sede || String(e.sede_id) === sede) && (!prog || String(e.programa_id) === prog); });
        pintarKpis(base);
        filtrados = base.filter(function (e) {
            if (q && normal(nombre(e) + ' ' + e.numero_documento).indexOf(q) === -1) return false;
            if (est === 'vigentes' && VIGENTES.indexOf(e.estado) < 0) return false;
            if (est && est !== 'vigentes' && e.estado !== est) return false;
            if (pago && (e.pago !== pago || VIGENTES.indexOf(e.estado) < 0)) return false;
            return true;
        }).sort(function (a, b) {
            var orden = { vencido: 0, pendiente: 1, sin_plan: 2, al_dia: 3 };
            return ((orden[a.pago] != null ? orden[a.pago] : 4) - (orden[b.pago] != null ? orden[b.pago] : 4)) || (b.saldo_vencido - a.saldo_vencido) || nombre(a).localeCompare(nombre(b), 'es');
        });
        pagina = 1; pintarTabla();
    }
    function pintarTabla() {
        var desde = (pagina - 1) * POR_PAGINA;
        $('est-count').textContent = num(filtrados.length) + (filtrados.length === 1 ? ' estudiante' : ' estudiantes');
        $('est-empty').hidden = filtrados.length > 0;
        $('est-body').innerHTML = filtrados.slice(desde, desde + POR_PAGINA).map(function (e) {
            var s = sedePorId(e.sede_id);
            return '<tr class="est-row" tabindex="0" data-id="' + e.id + '">' +
                '<td><div class="est-cell"><div class="est-avatar-txt">' + esc(iniciales(e)) + '</div><div><div class="est-strong">' + esc(nombre(e)) + '</div><div class="est-muted">' + esc(e.tipo_documento + ' ' + e.numero_documento) + (e.acudiente ? ' · menor de edad' : '') + '</div></div></div></td>' +
                '<td>' + esc(s ? s.nombre : '—') + '</td>' +
                '<td><div class="est-prog" title="' + esc(e.programa || '') + '">' + esc(e.programa ? e.programa.replace('Técnico Laboral en ', '') : '—') + '</div>' + (e.grupo ? '<div class="est-muted">' + esc(e.grupo) + '</div>' : '') + '</td>' +
                '<td>' + tag(ESTADO[e.estado]) + '</td>' +
                '<td>' + pagoDe(e) + '</td>' +
                '<td class="est-actions"><button type="button" class="est-icon-btn" data-ver="' + e.id + '" aria-label="Ver a ' + esc(nombre(e)) + '">' + ICON.ver + '</button></td></tr>';
        }).join('');
        paginar($('est-paginacion'), filtrados.length, pagina, function (p) { pagina = p; pintarTabla(); $('est-buscar').scrollIntoView({ block: 'start', behavior: 'smooth' }); });
    }
    $('est-buscar').addEventListener('input', filtrar);
    cbSede.querySelector('[data-combobox-value]').addEventListener('change', function () { opcionesPrograma(); cbProg.setValue('', 'Todos los programas', { silent: true }); filtrar(); });
    [cbProg, cbEstado, cbPago].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', filtrar); });
    $('est-body').addEventListener('click', function (e) { var tr = e.target.closest('tr[data-id]'); if (tr) abrirDrawer(+tr.dataset.id); });
    $('est-body').addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirDrawer(+e.target.dataset.id); }
    });

    $('est-exportar').addEventListener('click', function () {
        var filas = [['Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Sede', 'Programa', 'Grupo', 'Estado de la matrícula', 'Fecha de matrícula', 'Pagos',
            'Saldo vencido', 'Por pagar este mes', 'Celular', 'Correo', 'Acudiente', 'Celular del acudiente']].concat(filtrados.map(function (e) {
            var s = sedePorId(e.sede_id);
            return [e.nombres, e.apellidos, e.tipo_documento, e.numero_documento, s ? s.nombre : '', e.programa || '', e.grupo || '', ESTADO[e.estado][0], e.fecha_matricula || '',
                VIGENTES.indexOf(e.estado) >= 0 ? (PAGO[e.pago] || PAGO.al_dia)[0] : '', e.saldo_vencido || 0, e.por_pagar_mes || 0, e.telefono, e.email,
                e.acudiente ? e.acudiente.nombre + ' (' + e.acudiente.parentesco + ')' : '', e.acudiente ? e.acudiente.telefono : ''];
        }));
        descargarCsv('estudiantes-esat', filas);
    });

    /* ── Drawer ─────────────────────────────────── */
    var drawer = $('est-drawer'), actual = null, volverDrawer = null;
    function tab(n) {
        drawer.querySelectorAll('.est-tab').forEach(function (b) { var on = b.dataset.tab === n; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        drawer.querySelectorAll('.est-panel').forEach(function (p) { p.hidden = p.dataset.panel !== n; });
    }
    drawer.querySelectorAll('.est-tab').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });
    function info(filas) {
        return '<div class="est-info">' + filas.map(function (x) {
            return '<div' + (x[2] ? ' class="est-info-full"' : '') + '><span class="est-info-lbl">' + esc(x[0]) + '</span><span class="est-info-val">' + (x[3] ? x[1] : esc(x[1])) + '</span></div>';
        }).join('') + '</div>';
    }
    function abrirDrawer(id) {
        var e = D.estudiantes.find(function (x) { return x.id === id; });
        if (!e) return;
        actual = e; volverDrawer = document.activeElement;
        var s = sedePorId(e.sede_id), G = { femenino: 'Femenino', masculino: 'Masculino', otro: 'Otro' };
        $('est-dw-avatar').textContent = iniciales(e);
        $('est-dw-nombre').textContent = nombre(e);
        $('est-dw-doc').textContent = e.tipo_documento + ' ' + e.numero_documento + ' · Sede ' + (s ? s.nombre : '');
        $('est-dw-badges').innerHTML = tag(ESTADO[e.estado]) + (VIGENTES.indexOf(e.estado) >= 0 && e.estado !== 'sin_matricula' ? ' ' + tag(PAGO[e.pago] || PAGO.al_dia) : '');

        $('est-dw-datos').innerHTML = '<h3 class="est-seccion">Datos personales</h3>' + info([
            ['Fecha de nacimiento', e.fecha_nacimiento ? fechaLarga(e.fecha_nacimiento) + ' (' + e.edad + ' años)' : '—'], ['Género', G[e.genero] || ''],
            ['Celular', fmtTel(e.telefono), false, true], ['Correo (usuario)', e.email], ['Nació en', [e.lugar_nacimiento, e.departamento_nacimiento].filter(Boolean).join(', ') || '—'],
            ['Vive en', [e.direccion, e.ciudad, e.departamento].filter(Boolean).join(', ') || '—', true]]) +
            '<h3 class="est-seccion">Acudiente</h3>' + (e.acudiente
                ? info([['Nombre', e.acudiente.nombre], ['Parentesco', e.acudiente.parentesco], ['Celular', fmtTel(e.acudiente.telefono), false, true]])
                : '<p class="est-muted">Es mayor de edad y no tiene acudiente registrado.</p>');

        if (e.estado === 'sin_matricula') {
            $('est-dw-matricula').innerHTML = '<div class="est-aviso">' + ICON.info + '<span>Está registrado pero todavía no tiene matrícula. La secretaría lo matricula cuando entregue todos los documentos.</span></div>';
        } else {
            var cierre = e.estado_fecha ? [[ESTADO[e.estado][0] + ' desde', fechaLarga(e.estado_fecha)], ['Motivo', e.estado_motivo]] : [];
            $('est-dw-matricula').innerHTML = '<h3 class="est-seccion">Matrícula</h3>' + info([
                ['Programa', e.programa, true], ['Grupo', e.grupo || '—'], ['Fecha de matrícula', e.fecha_matricula ? fechaLarga(e.fecha_matricula) : '—'],
                ['Plan de pago', e.plan === 'mensual' ? 'Mensual' : e.plan === 'periodo' ? 'Por periodo' : 'Sin elegir (lo elige el estudiante)']].concat(cierre)) +
                '<h3 class="est-seccion">Rendimiento</h3>' + info([
                    ['Asistencia', e.asistencia == null ? 'Sin clases registradas' : e.asistencia + ' %<div class="est-barra"><span class="' + (e.asistencia < 80 ? 'is-baja' : '') + '" style="width:' + e.asistencia + '%"></span></div>', false, e.asistencia != null],
                    ['Promedio de notas', e.promedio == null ? 'Sin notas todavía' : e.promedio.toFixed(1).replace('.', ',') + ' de 5,0']]);
        }

        var recibos = D.recaudos.filter(function (r) { return r.est_id === e.id; }).sort(function (a, b) { return new Date(b.fecha) - new Date(a.fecha); });
        var vencidas = e.cuotas_vencidas || [];
        $('est-dw-pagos').innerHTML =
            (vencidas.length ? '<h3 class="est-seccion">Cuotas vencidas · ' + esc(plata(e.saldo_vencido)) + '</h3><table class="est-mini"><thead><tr><th>Venció</th><th class="est-num">Valor</th></tr></thead><tbody>' +
                vencidas.map(function (c) { return '<tr><td>' + esc(fechaMini(c.vence)) + '</td><td class="est-num">' + esc(plata(c.valor)) + '</td></tr>'; }).join('') + '</tbody></table>' : '') +
            (e.pago === 'pendiente' ? '<p class="est-aviso" style="margin-top:14px">' + ICON.info + '<span>Este mes debe pagar ' + esc(plata(e.por_pagar_mes)) + ' antes del día 10.</span></p>' : '') +
            '<h3 class="est-seccion">Pagos recibidos</h3>' + (recibos.length
                ? '<table class="est-mini"><thead><tr><th>Recibo</th><th>Fecha</th><th>Concepto</th><th class="est-num">Valor</th></tr></thead><tbody>' +
                  recibos.map(function (r) {
                      return '<tr' + (r.anulado ? ' class="est-anulado" title="Anulado: ' + esc(r.motivo) + '"' : '') + '><td>' + esc(r.numero) + '</td><td>' + esc(fechaMini(r.fecha)) + '</td><td>' + esc(r.concepto) + (r.anulado ? ' (anulado)' : '') + '</td><td class="est-num">' + esc(plata(r.valor)) + '</td></tr>';
                  }).join('') + '</tbody></table>'
                : '<p class="est-muted">No tiene pagos registrados.</p>');
        tab('datos');
        drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('.est-close').focus();
    }
    function cerrarDrawer() {
        drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true');
        if (volverDrawer && document.contains(volverDrawer)) volverDrawer.focus();
    }
    drawer.querySelectorAll('[data-cerrar-drawer]').forEach(function (b) { b.addEventListener('click', cerrarDrawer); });


    /* ── Entrar a la sede: matricular y cobrar se hace con las pantallas de la secretaría (EntrarSedeController) ── */
    function entrarASede(id, destino) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = '/general/sedes/' + encodeURIComponent(id) + '/entrar';
        [['_token', document.querySelector('meta[name="csrf-token"]').content], ['destino', destino || 'dashboard']].forEach(function (c) {
            var i = document.createElement('input'); i.type = 'hidden'; i.name = c[0]; i.value = c[1]; f.appendChild(i);
        });
        document.body.appendChild(f); f.submit();
    }

    var modal = $('est-entrar'), volverModal = null;
    function cerrarModal() { if (modal.hidden) return; modal.hidden = true; if (volverModal) volverModal.focus(); }
    $('est-dw-entrar').addEventListener('click', function () {
        var s = sedePorId(actual.sede_id);
        volverModal = document.activeElement;
        $('est-entrar-titulo').textContent = 'Entrar a ' + s.nombre;
        $('est-entrar-sub').textContent = 'Ficha de ' + nombre(actual);
        modal.hidden = false; $('est-entrar-ok').focus();
    });
    $('est-entrar-ok').addEventListener('click', function () { this.disabled = true; entrarASede(actual.sede_id, 'matriculas'); });
    modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || document.querySelector('.app-combobox.is-open')) return;
        if (!formModal.hidden) cerrarForm(); else if (!modal.hidden) cerrarModal(); else if (drawer.classList.contains('is-open')) cerrarDrawer();
    });

    /* ── Registrar / editar a la persona (2 pasos), con las reglas de la secretaría (EstudianteRequest) ──
       La unicidad del documento y del correo la revisa el servidor (no se mandan los datos de otras cuentas). */
    var PARENTESCOS = D.opciones.parentescos;
    var formModal = $('est-modal'), volverForm = null, editando = null, paso = 1;
    function f(id) { return $('est-f-' + id); }
    function edadDe(fecha) {
        if (!fecha) return null;
        var n = new Date(fecha + 'T12:00:00'), h = new Date(), e = h.getFullYear() - n.getFullYear();
        if (h.getMonth() < n.getMonth() || (h.getMonth() === n.getMonth() && h.getDate() < n.getDate())) e--;
        return e;
    }
    function errF(c, m) {
        var e = formModal.querySelector('[data-error="' + c + '"]'); if (!e) return;
        e.textContent = m || ''; e.hidden = !m; if (f(c)) f(c).classList.toggle('is-invalid', !!m);
    }
    function opciones(select, lista, vacio) {
        select.innerHTML = '<option value="">' + esc(vacio) + '</option>' + lista.map(function (x) { return '<option>' + esc(x) + '</option>'; }).join('');
    }
    f('sede').innerHTML = '<option value="">Selecciona</option>' + D.sedes.map(function (s) { return '<option value="' + s.id + '">' + esc(s.nombre) + '</option>'; }).join('');
    opciones(f('parentesco'), PARENTESCOS, 'Selecciona');

    /* Departamento → ciudad (listado real de Colombia, el mismo de los combobox de la secretaría) */
    var colombia = [], colombiaLista = fetch('/assets/data/co-departamentos-ciudades.json').then(function (r) { return r.json(); })
        .then(function (d) { colombia = d; var deptos = d.map(function (x) { return x.departamento; }); opciones(f('depto-nac'), deptos, 'Selecciona'); opciones(f('depto'), deptos, 'Selecciona'); })
        .catch(function () { toast('No se pudo cargar la lista de departamentos. Recarga la página.'); });
    function ciudadesDe(depto) { var d = colombia.find(function (x) { return x.departamento === depto; }); return d ? d.ciudades : []; }
    function enlazar(depto, ciudad) {
        f(depto).addEventListener('change', function () { opciones(f(ciudad), ciudadesDe(this.value), this.value ? 'Selecciona' : 'Elige el departamento primero'); });
    }
    enlazar('depto-nac', 'ciudad-nac'); enlazar('depto', 'ciudad');
    function ponerLugar(depto, ciudad, d, c) {
        f(depto).value = d || '';
        opciones(f(ciudad), ciudadesDe(d), d ? 'Selecciona' : 'Elige el departamento primero');
        f(ciudad).value = c || '';
    }

    function opcionesAcudiente() {
        var sid = +f('sede').value, menor = edadDe(f('nacimiento').value) < 18;
        var l = D.acudientes.filter(function (a) { return a.sede_id === sid; }).sort(function (a, b) { return (a.nombres + a.apellidos).localeCompare(b.nombres + b.apellidos, 'es'); });
        var actualId = f('acudiente').value;
        f('acudiente').innerHTML = '<option value="">' + (sid ? (menor ? 'Elige el acudiente' : 'Sin acudiente') : 'Elige primero la sede') + '</option>' +
            l.map(function (a) { return '<option value="' + a.id + '">' + esc(a.nombres + ' ' + a.apellidos + ' · ' + a.tipo_documento + ' ' + a.numero_documento) + '</option>'; }).join('') +
            (sid && !editando ? '<option value="nuevo">Registrar un acudiente nuevo</option>' : '');
        f('acudiente').value = actualId === 'nuevo' && !editando ? 'nuevo' : l.some(function (a) { return String(a.id) === actualId; }) ? actualId : '';
        $('est-acu-req').textContent = menor ? '(obligatorio: es menor de edad)' : '(opcional)';
        mostrarNuevo();
    }
    function mostrarNuevo() {
        var nuevo = f('acudiente').value === 'nuevo';
        $('est-acu-nuevo').hidden = !nuevo;
        $('est-acu-ayuda').hidden = !!editando || nuevo;
    }
    f('acudiente').addEventListener('change', mostrarNuevo);
    f('sede').addEventListener('change', opcionesAcudiente);
    f('nacimiento').addEventListener('input', function () {
        var e = edadDe(this.value); $('est-edad').textContent = e != null && e >= 0 ? e + ' años' + (e < 18 ? ' · menor de edad' : '') : '';
        if (e != null && e < 18 && f('tipo').value === 'CC') f('tipo').value = 'TI';
        if (e != null && e >= 18 && f('tipo').value === 'TI') f('tipo').value = 'CC';
        opcionesAcudiente();
    });
    formModal.querySelectorAll('input, select').forEach(function (i) { var k = (i.id || '').replace('est-f-', ''); if (k) { i.addEventListener('input', function () { errF(k, ''); }); i.addEventListener('change', function () { errF(k, ''); }); } });

    var CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    function validarPaso(p) {
        var v = function (id) { return f(id).value.trim(); };
        var campos = p === 1 ? ['sede', 'nombres', 'apellidos', 'tipo', 'documento', 'nacimiento', 'genero', 'depto-nac', 'ciudad-nac']
            : ['telefono', 'correo', 'direccion', 'depto', 'ciudad', 'acudiente', 'parentesco', 'a-documento', 'a-telefono', 'a-nombres', 'a-apellidos', 'a-correo'];
        if (p === 1) {
            errF('sede', v('sede') ? '' : 'Elige la sede.');
            if (editando && +v('sede') !== editando.sede_id && VIGENTES.indexOf(editando.estado) >= 0) errF('sede', 'Tiene matrícula vigente en ' + sedePorId(editando.sede_id).nombre + ': no se cambia de sede desde aquí.');
            errF('nombres', v('nombres').length < 2 ? 'Escribe los nombres.' : ''); errF('apellidos', v('apellidos').length < 2 ? 'Escribe los apellidos.' : '');
            errF('documento', !/^\d{6,11}$/.test(v('documento').replace(/\D/g, '')) ? 'Escribe el documento (solo números, de 6 a 11).' : '');
            var e = edadDe(v('nacimiento'));
            errF('nacimiento', !v('nacimiento') ? 'Escribe la fecha de nacimiento.' : e < 13 ? 'Debe tener al menos 13 años.' : e > 80 ? 'Revisa la fecha.' : '');
            errF('tipo', e != null && f('tipo').value === 'TI' && e >= 18 ? 'Es mayor de edad: usa cédula u otro documento.' : e != null && f('tipo').value === 'CC' && e < 18 ? 'Un menor se identifica con tarjeta de identidad.' : '');
            errF('genero', v('genero') ? '' : 'Elige el género.');
            errF('depto-nac', v('depto-nac') ? '' : 'Elige el departamento.');
            errF('ciudad-nac', v('ciudad-nac') ? '' : 'Elige la ciudad.');
        } else {
            var menor = edadDe(v('nacimiento')) < 18, acu = v('acudiente');
            errF('telefono', !/^3\d{9}$/.test(v('telefono').replace(/\D/g, '')) ? 'Escribe un celular de 10 dígitos que empiece por 3.' : '');
            errF('correo', !CORREO.test(v('correo').toLowerCase()) ? 'Escribe el correo: es su usuario para entrar.' : '');
            errF('direccion', v('direccion').length < 5 ? 'Escribe la dirección completa.' : '');
            errF('depto', v('depto') ? '' : 'Elige el departamento.');
            errF('ciudad', v('ciudad') ? '' : 'Elige la ciudad o municipio.');
            errF('acudiente', menor && !acu ? 'Es menor de edad: necesita un acudiente.' : '');
            errF('parentesco', acu && !v('parentesco') ? 'Elige el parentesco.' : '');
            var nuevo = acu === 'nuevo';
            errF('a-documento', nuevo && !/^\d{6,11}$/.test(v('a-documento').replace(/\D/g, '')) ? 'Escribe la cédula (de 6 a 11 dígitos).' : nuevo && v('a-documento').replace(/\D/g, '') === v('documento').replace(/\D/g, '') ? 'No puede ser el mismo estudiante.' : '');
            errF('a-telefono', nuevo && !/^3\d{9}$/.test(v('a-telefono').replace(/\D/g, '')) ? 'Celular de 10 dígitos que empiece por 3.' : '');
            errF('a-nombres', nuevo && v('a-nombres').length < 2 ? 'Escribe los nombres.' : '');
            errF('a-apellidos', nuevo && v('a-apellidos').length < 2 ? 'Escribe los apellidos.' : '');
            errF('a-correo', nuevo && !CORREO.test(v('a-correo').toLowerCase()) ? 'Escribe el correo del acudiente.' : '');
        }
        var malo = campos.find(function (c) { var e = formModal.querySelector('[data-error="' + c + '"]'); return e && !e.hidden; });
        if (malo) f(malo).focus();
        return !malo;
    }
    function irPaso(p) {
        paso = p;
        formModal.querySelectorAll('.est-paso').forEach(function (el) { el.hidden = +el.dataset.paso !== p; });
        $('est-modal-sub').textContent = 'Paso ' + p + ' de 2 · ' + (p === 1 ? 'Datos personales' : 'Residencia, contacto y acudiente');
        $('est-atras').hidden = p === 1;
        $('est-siguiente').textContent = p === 1 ? 'Siguiente' : editando ? 'Guardar cambios' : 'Registrar estudiante';
        if (p === 2) opcionesAcudiente();
    }
    function abrirForm(e) {
        editando = e || null;
        var x = e || { sede_id: '', nombres: '', apellidos: '', tipo_documento: 'CC', numero_documento: '', fecha_nacimiento: '', genero: '', telefono: '', email: '',
            direccion: '', departamento: '', ciudad: '', departamento_nacimiento: '', lugar_nacimiento: '', acudiente_id: '', parentesco: '' };
        f('sede').value = x.sede_id || ''; f('nombres').value = x.nombres; f('apellidos').value = x.apellidos; f('tipo').value = x.tipo_documento || 'CC';
        f('documento').value = x.numero_documento; f('nacimiento').value = x.fecha_nacimiento || ''; f('nacimiento').max = new Date().toISOString().slice(0, 10);
        f('genero').value = x.genero || ''; f('telefono').value = x.telefono; f('correo').value = x.email; f('direccion').value = x.direccion || '';
        ['a-documento', 'a-telefono', 'a-nombres', 'a-apellidos', 'a-correo'].forEach(function (c) { f(c).value = ''; });
        colombiaLista.then(function () { ponerLugar('depto-nac', 'ciudad-nac', x.departamento_nacimiento, x.lugar_nacimiento); ponerLugar('depto', 'ciudad', x.departamento, x.ciudad); });
        f('acudiente').value = ''; opcionesAcudiente(); f('acudiente').value = x.acudiente_id || ''; f('parentesco').value = x.parentesco || ''; mostrarNuevo();
        $('est-edad').textContent = ''; if (x.fecha_nacimiento) f('nacimiento').dispatchEvent(new Event('input'));
        f('acudiente').value = x.acudiente_id || ''; mostrarNuevo();
        formModal.querySelectorAll('.est-field-error').forEach(function (el) { el.hidden = true; });
        formModal.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        $('est-modal-titulo').textContent = editando ? 'Editar a ' + nombre(editando) : 'Nuevo estudiante';
        $('est-nota-clave').hidden = !!editando;
        volverForm = document.activeElement; formModal.hidden = false; f('sede').focus();
        irPaso(1);
    }
    function cerrarForm() { if (formModal.hidden) return; formModal.hidden = true; if (volverForm && document.contains(volverForm)) volverForm.focus(); }
    formModal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarForm); });
    $('est-nuevo').addEventListener('click', function () { abrirForm(null); });
    $('est-dw-editar').addEventListener('click', function () { abrirForm(actual); });
    $('est-atras').addEventListener('click', function () { irPaso(1); });

    /* Error del servidor → su campo (y su paso) */
    var CAMPO = { sede_id: ['sede', 1], nombres: ['nombres', 1], apellidos: ['apellidos', 1], tipo_documento: ['tipo', 1], numero_documento: ['documento', 1],
        fecha_nacimiento: ['nacimiento', 1], genero: ['genero', 1], departamento_nacimiento: ['depto-nac', 1], lugar_nacimiento: ['ciudad-nac', 1],
        telefono: ['telefono', 2], email: ['correo', 2], direccion: ['direccion', 2], departamento_residencia: ['depto', 2], ciudad_residencia: ['ciudad', 2],
        acudiente: ['acudiente', 2], acudiente_id: ['acudiente', 2], 'acudiente.id': ['acudiente', 2], parentesco: ['parentesco', 2],
        'acudiente.documento': ['a-documento', 2], 'acudiente.telefono': ['a-telefono', 2], 'acudiente.nombres': ['a-nombres', 2],
        'acudiente.apellidos': ['a-apellidos', 2], 'acudiente.email': ['a-correo', 2] };
    $('est-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!validarPaso(paso)) return;
        if (paso === 1) { irPaso(2); return; }
        var v = function (id) { return f(id).value.trim(); }, acu = v('acudiente');
        var cuerpo = { sede_id: +v('sede'), nombres: v('nombres'), apellidos: v('apellidos'), tipo_documento: v('tipo'), numero_documento: v('documento'),
            fecha_nacimiento: v('nacimiento'), genero: v('genero'), departamento_nacimiento: v('depto-nac'), lugar_nacimiento: v('ciudad-nac'),
            telefono: v('telefono'), email: v('correo'), direccion: v('direccion'), departamento_residencia: v('depto'), ciudad_residencia: v('ciudad'),
            parentesco: acu ? v('parentesco') : null };
        if (editando) cuerpo.acudiente_id = acu ? +acu : null;
        else if (acu) {
            cuerpo.con_acudiente = true;
            cuerpo.acudiente = acu === 'nuevo'
                ? { documento: v('a-documento'), telefono: v('a-telefono'), nombres: v('a-nombres'), apellidos: v('a-apellidos'), email: v('a-correo') }
                : { id: +acu };
        }
        var btn = $('est-siguiente'); btn.disabled = true;
        enviar(editando ? 'PUT' : 'POST', editando ? '/general/estudiantes/' + editando.id : '/general/estudiantes', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (fallo) {
                btn.disabled = false;
                var k = Object.keys(fallo.errores || {}).find(function (c) { return CAMPO[c]; });
                if (!k) { toast(fallo.message); return; }
                irPaso(CAMPO[k][1]); errF(CAMPO[k][0], fallo.errores[k][0]); f(CAMPO[k][0]).focus();
            });
    });

    filtrar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
}());
