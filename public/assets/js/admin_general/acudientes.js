/* =========================================================
   ESAT — Panel Admin (ESAT general): Acudientes
   Acudientes de todas las sedes: crear y editar en 3 pasos (eligiendo la sede y, si se quiere, sus estudiantes), vincular y desvincular estudiantes (un acudiente por estudiante; un menor nunca queda sin acudiente), activar/desactivar la cuenta y restablecer la clave.
   Los datos llegan en window.GEN_DATOS (AdminGeneral AcudienteController@index); guardar va por fetch a /general/acudientes
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

    var toastEl = $('acu-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="acu-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="acu-pg-gap">…</span>'
                    : '<button type="button" class="acu-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="acu-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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

    /* ── Datos ──────────────────────────────────── */
    var PARENTESCOS = D.opciones.parentescos;
    var EST = { activo: 'Activo', aplazado: 'Aplazado', retirado: 'Retirado', graduado: 'Graduado', sin_matricula: 'Sin matrícula' };
    function sede(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function nombre(x) { return x.nombres + ' ' + x.apellidos; }
    function iniciales(x) { return ((x.nombres || '').charAt(0) + (x.apellidos || '').charAt(0)).toUpperCase(); }
    function aCargo(a) { return D.estudiantes.filter(function (e) { return e.acudiente_id === a.id; }); }
    function acudiente(id) { return D.acudientes.find(function (a) { return a.id === +id; }); }
    function opcionesParentesco(sel) { $(sel).innerHTML = '<option value="">Parentesco</option>' + PARENTESCOS.map(function (p) { return '<option>' + p + '</option>'; }).join(''); }
    /* Estudiantes de la sede que se pueden vincular (los menores sin acudiente primero) */
    function opcionesEstudiante(selId, sedeId, excluir) {
        var l = D.estudiantes.filter(function (e) { return e.sede_id === +sedeId && e.estado !== 'retirado' && e.estado !== 'graduado' && excluir.indexOf(e.id) < 0; })
            .sort(function (a, b) { return (!!a.acudiente - !!b.acudiente) || (a.edad - b.edad) || nombre(a).localeCompare(nombre(b), 'es'); });
        $(selId).innerHTML = '<option value="">' + (sedeId ? 'Elige un estudiante de la sede' : 'Elige primero la sede') + '</option>' + l.map(function (e) {
            return '<option value="' + e.id + '">' + esc(nombre(e) + ' · ' + e.edad + ' años' + (e.acudiente ? ' · acudiente: ' + e.acudiente.nombre : ' · sin acudiente')) + '</option>';
        }).join('');
    }
    function ultimo(a) {
        if (!a.ultimo_acceso) return '<span class="acu-warn">Nunca ha ingresado</span>';
        var t = hace(a.ultimo_acceso); return esc(t.charAt(0).toUpperCase() + t.slice(1));
    }

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var act = D.acudientes.filter(function (a) { return a.activo; }).length, nunca = D.acudientes.filter(function (a) { return a.activo && !a.ultimo_acceso; }).length;
        var menores = D.estudiantes.filter(function (e) { return e.edad < 18 && e.estado !== 'retirado' && e.estado !== 'graduado'; });
        var sin = menores.filter(function (e) { return !e.acudiente; }).length;
        var k = [['blue', 'docente', num(D.acudientes.length), 'Acudientes'], ['green', 'ok', num(act), act === 1 ? 'Cuenta activa' : 'Cuentas activas'],
            ['orange', 'reloj', num(nunca), 'Nunca han ingresado'], [sin ? 'red' : 'green', 'estudiantes', num(sin), 'Menores sin acudiente']];
        $('acu-kpis').innerHTML = k.map(function (x) { return '<div class="acu-kpi acu-kpi-' + x[0] + '"><div class="acu-kpi-icon">' + ICON[x[1]] + '</div><div><div class="acu-kpi-val">' + esc(x[2]) + '</div><div class="acu-kpi-lbl">' + esc(x[3]) + '</div></div></div>'; }).join('');
    }

    /* ── Filtros y tabla ────────────────────────── */
    var cbSede = $('acu-cb-sede'), cbEst = $('acu-cb-estado');
    initCombobox(cbSede); initCombobox(cbEst);
    cbSede.setOptions([{ value: '', label: 'Todas las sedes' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: s.nombre }; })));
    cbEst.setOptions([{ value: '', label: 'Todos los estados' }, { value: 'activo', label: 'Cuentas activas' }, { value: 'inactivo', label: 'Cuentas inactivas' }, { value: 'nunca', label: 'Nunca han ingresado' }, { value: 'sin', label: 'Sin estudiantes a cargo' }]);
    cbSede.setValue('', 'Todas las sedes', { silent: true }); cbEst.setValue('', 'Todos los estados', { silent: true });
    var pagina = 1, filtrados = [];
    function filtrar() {
        var q = normal($('acu-buscar').value.trim()), s = cbSede.value(), st = cbEst.value();
        filtrados = D.acudientes.filter(function (a) {
            if (q && normal(nombre(a) + ' ' + a.numero_documento + ' ' + a.email + ' ' + aCargo(a).map(nombre).join(' ')).indexOf(q) === -1) return false;
            if (s && String(a.sede_id) !== s) return false;
            if (st === 'activo' && !a.activo) return false;
            if (st === 'inactivo' && a.activo) return false;
            if (st === 'nunca' && (a.ultimo_acceso || !a.activo)) return false;
            if (st === 'sin' && aCargo(a).length) return false;
            return true;
        }).sort(function (a, b) { return (b.activo - a.activo) || nombre(a).localeCompare(nombre(b), 'es'); });
        pagina = 1; pintarTabla();
    }
    function pintarTabla() {
        var desde = (pagina - 1) * POR_PAGINA;
        $('acu-count').textContent = filtrados.length + (filtrados.length === 1 ? ' acudiente' : ' acudientes');
        $('acu-empty').hidden = filtrados.length > 0;
        $('acu-body').innerHTML = filtrados.slice(desde, desde + POR_PAGINA).map(function (a) {
            var est = aCargo(a);
            return '<tr class="acu-row" tabindex="0" data-id="' + a.id + '"><td><div class="acu-cell"><div class="acu-avatar-txt' + (a.activo ? '' : ' is-off') + '">' + esc(iniciales(a)) + '</div>' +
                '<div><div class="acu-strong">' + esc(nombre(a)) + '</div><div class="acu-muted">' + esc(a.tipo_documento + ' ' + a.numero_documento) + '</div></div></div></td>' +
                '<td>' + esc(sede(a.sede_id).nombre) + '</td><td>' + esc(a.email) + '<div class="acu-muted">' + fmtTel(a.telefono) + '</div></td>' +
                '<td>' + (est.length ? est.map(function (e) { return '<div>' + esc(nombre(e)) + ' <span class="acu-muted">· ' + esc(e.parentesco || '') + '</span></div>'; }).join('') : '<span class="acu-muted">Ninguno</span>') + '</td>' +
                '<td>' + ultimo(a) + '</td><td>' + (a.activo ? '<span class="app-status-tag app-status-active">Activa</span>' : '<span class="app-status-tag app-status-muted">Inactiva</span>') + '</td>' +
                '<td class="acu-actions"><button type="button" class="acu-icon-btn" data-editar="' + a.id + '" aria-label="Editar a ' + esc(nombre(a)) + '">' + ICON.editar + '</button></td></tr>';
        }).join('');
        paginar($('acu-paginacion'), filtrados.length, pagina, function (p) { pagina = p; pintarTabla(); });
    }
    $('acu-buscar').addEventListener('input', filtrar);
    [cbSede, cbEst].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', filtrar); });
    $('acu-body').addEventListener('click', function (e) {
        var ed = e.target.closest('[data-editar]'); if (ed) { e.stopPropagation(); abrirForm(+ed.dataset.editar); return; }
        var tr = e.target.closest('tr[data-id]'); if (tr) abrirDrawer(+tr.dataset.id);
    });
    $('acu-body').addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirDrawer(+e.target.dataset.id); } });
    $('acu-exportar').addEventListener('click', function () {
        descargarCsv('acudientes-esat', [['Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Sede', 'Celular', 'Correo', 'Ocupación', 'Estudiantes a cargo', 'Cuenta', 'Último ingreso']]
            .concat(filtrados.map(function (a) { return [a.nombres, a.apellidos, a.tipo_documento, a.numero_documento, sede(a.sede_id).nombre, a.telefono, a.email, a.profesion, aCargo(a).map(function (e) { return nombre(e) + ' (' + (e.parentesco || '') + ')'; }).join(', '), a.activo ? 'Activa' : 'Inactiva', a.ultimo_acceso ? fechaLarga(a.ultimo_acceso) : 'Nunca']; })));
    });

    /* ── Drawer ─────────────────────────────────── */
    var drawer = $('acu-drawer'), actual = null, volverDrawer = null;
    function tab(n) {
        drawer.querySelectorAll('.acu-tab').forEach(function (b) { var on = b.dataset.tab === n; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        drawer.querySelectorAll('.acu-panel').forEach(function (p) { p.hidden = p.dataset.panel !== n; });
    }
    drawer.querySelectorAll('.acu-tab').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });
    opcionesParentesco('acu-v-parentesco'); opcionesParentesco('acu-f-parentesco');
    function abrirDrawer(id, pestana) {
        var a = acudiente(id); if (!a) return;
        if (!drawer.classList.contains('is-open')) volverDrawer = document.activeElement;
        actual = a;
        $('acu-dw-nombre').textContent = nombre(a);
        $('acu-dw-sub').innerHTML = esc(a.tipo_documento + ' ' + a.numero_documento + ' · Sede ' + sede(a.sede_id).nombre) + ' ' + (a.activo ? '<span class="app-status-tag app-status-active">Activa</span>' : '<span class="app-status-tag app-status-muted">Inactiva</span>');
        $('acu-dw-datos').innerHTML = '<div class="acu-info">' + [['Correo (usuario)', esc(a.email)], ['Celular', fmtTel(a.telefono)], ['Ocupación', esc(a.profesion || '—')], ['Residencia', esc(a.ciudad + ', ' + a.departamento)],
            ['Dirección', esc(a.direccion || '—')], ['Último ingreso', a.ultimo_acceso ? esc(fechaLarga(a.ultimo_acceso)) : 'Nunca ha ingresado']]
            .map(function (x) { return '<div><span class="acu-info-lbl">' + esc(x[0]) + '</span><span class="acu-info-val">' + x[1] + '</span></div>'; }).join('') + '</div>';
        pintarEstudiantesDrawer();
        $('acu-dw-estado').textContent = a.activo ? 'Desactivar cuenta' : 'Activar cuenta';
        tab(pestana || 'datos');
        drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('.acu-close').focus();
    }
    function pintarEstudiantesDrawer() {
        var a = actual, est = aCargo(a);
        $('acu-dw-estudiantes').innerHTML = est.length ? est.map(function (e) {
            var menor = e.edad < 18;
            return '<li><div><div class="acu-strong">' + esc(nombre(e)) + '</div><div class="acu-muted">' + esc((e.parentesco || '') + ' · ' + e.edad + ' años · ' + EST[e.estado] + (e.programa ? ' · ' + e.programa.replace('Técnico Laboral en ', '') : '')) + '</div></div>' +
                '<button type="button" class="acu-btn acu-btn-ghost acu-btn-sm" data-desvincular="' + e.id + '"' + (menor ? ' disabled title="Es menor de edad: para cambiarle el acudiente, vincúlalo a otro."' : '') + '>Desvincular</button></li>';
        }).join('') : '<li class="acu-list-empty">No tiene estudiantes a cargo.</li>';
        opcionesEstudiante('acu-v-estudiante', a.sede_id, est.map(function (e) { return e.id; }));
        $('acu-v-aviso').hidden = true;
    }
    function cerrarDrawer() { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); if (volverDrawer && document.contains(volverDrawer)) volverDrawer.focus(); }
    drawer.querySelectorAll('[data-cerrar-drawer]').forEach(function (b) { b.addEventListener('click', cerrarDrawer); });
    $('acu-v-estudiante').addEventListener('change', function () {
        var e = D.estudiantes.find(function (x) { return x.id === +this.value; }, this);
        $('acu-v-aviso').hidden = !(e && e.acudiente);
        if (e && e.acudiente) $('acu-v-aviso').textContent = 'Hoy su acudiente es ' + e.acudiente.nombre + '. Al vincularlo aquí, lo reemplaza.';
    });
    $('acu-v-vincular').addEventListener('click', function () {
        var a = actual, eid = +$('acu-v-estudiante').value, par = $('acu-v-parentesco').value;
        if (!eid || !par) { toast('Elige el estudiante y el parentesco.'); return; }
        var e = D.estudiantes.find(function (x) { return x.id === eid; });
        var btn = this; btn.disabled = true;
        enviar('POST', '/general/acudientes/' + a.id + '/vincular', { estudiante_id: e.id, parentesco: par })
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (err) { btn.disabled = false; toast(err.message); });
    });
    $('acu-dw-estudiantes').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-desvincular]'); if (!b || b.disabled) return;
        var a = actual, e = D.estudiantes.find(function (x) { return x.id === +b.dataset.desvincular; });
        confirmar('¿Desvincular a ' + nombre(e) + '?', 'Es mayor de edad, así que puede quedar sin acudiente. ' + nombre(a) + ' ya no verá su información.', 'Desvincular', function () {
            return enviar('DELETE', '/general/acudientes/' + a.id + '/estudiantes/' + e.id).then(function (r) { recargarConAviso(r.mensaje); });
        });
    });

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
    function confirmar(titulo, texto, boton, accion) { $('acu-conf-titulo').textContent = titulo; $('acu-conf-texto').textContent = texto; $('acu-conf-ok').textContent = boton; alConfirmar = accion; abrirModal('acu-confirmar', $('acu-conf-ok')); }
    $('acu-conf-ok').addEventListener('click', function () { var b = this; b.disabled = true; alConfirmar().catch(function (err) { b.disabled = false; cerrarModal(); toast(err.message); }); });

    $('acu-dw-estado').addEventListener('click', function () {
        var a = actual;
        confirmar(a.activo ? '¿Desactivar la cuenta de ' + nombre(a) + '?' : '¿Activar la cuenta de ' + nombre(a) + '?',
            a.activo ? 'No podrá entrar a ver las notas, la asistencia y los pagos de sus estudiantes. Sigue siendo su acudiente.' : 'Podrá entrar otra vez con su correo y su clave.',
            a.activo ? 'Desactivar' : 'Activar', function () {
                return enviar('PATCH', '/general/acudientes/' + a.id + '/estado').then(function (r) { recargarConAviso(r.mensaje); });
            });
    });
    $('acu-dw-clave').addEventListener('click', function () {
        var a = actual;
        confirmar('¿Restablecer la clave de ' + nombre(a) + '?', 'Su clave volverá a ser su número de documento (' + a.numero_documento + ') y se cerrarán sus sesiones abiertas.', 'Restablecer clave', function () {
            return enviar('PATCH', '/general/acudientes/' + a.id + '/clave').then(function (r) { recargarConAviso(r.mensaje); });
        });
    });

    /* ── Formulario (3 pasos) ───────────────────── */
    var cbDepto = $('acu-cb-depto'), cbCiudad = $('acu-cb-ciudad');
    initCombobox(cbDepto); initCombobox(cbCiudad);
    var colombia = [], listo = fetch('/assets/data/co-departamentos-ciudades.json').then(function (r) { return r.json(); })
        .then(function (d) { colombia = d; cbDepto.setOptions(d.map(function (x) { return x.departamento; })); })
        .catch(function () { toast('No se pudo cargar la lista de departamentos. Recarga la página.'); });
    function ciudadesDe(dep) { var x = colombia.find(function (c) { return c.departamento === dep; }); return x ? x.ciudades : []; }
    cbDepto.querySelector('[data-combobox-value]').addEventListener('change', function () { cbCiudad.reset('Selecciona una ciudad'); cbCiudad.setOptions(ciudadesDe(cbDepto.value())); err('depto', ''); });
    cbCiudad.querySelector('[data-combobox-value]').addEventListener('change', function () { err('ciudad', ''); });
    $('acu-f-sede').innerHTML = '<option value="">Selecciona</option>' + D.sedes.map(function (s) { return '<option value="' + s.id + '">' + esc(s.nombre) + '</option>'; }).join('');

    var paso = 1, editando = null, elegidos = [], CAMPOS = { 1: ['nombres', 'apellidos', 'documento', 'genero', 'profesion'], 2: ['telefono', 'correo', 'depto', 'ciudad', 'direccion'], 3: ['sede'] };
    function f(id) { return $('acu-f-' + id); }
    function err(c, m) {
        var e = document.querySelector('#acu-form [data-error="' + c + '"]'); if (!e) return;
        e.textContent = m || ''; e.hidden = !m;
        if (c === 'depto') cbDepto.classList.toggle('is-invalid', !!m); else if (c === 'ciudad') cbCiudad.classList.toggle('is-invalid', !!m); else if (f(c)) f(c).classList.toggle('is-invalid', !!m);
    }
    function validar(p) {
        var v = function (id) { return f(id).value.trim(); };
        if (p === 1) {
            err('nombres', v('nombres').length < 2 ? 'Escribe los nombres.' : ''); err('apellidos', v('apellidos').length < 2 ? 'Escribe los apellidos.' : '');
            var doc = v('documento').replace(/\D/g, '');
            err('documento', !/^\d{6,11}$/.test(doc) ? 'Escribe el documento (solo números, de 6 a 11).' : '');
            err('genero', v('genero') ? '' : 'Elige el género.'); err('profesion', v('profesion') && v('profesion').length < 3 ? 'Escribe la ocupación o déjala vacía.' : '');
        }
        if (p === 2) {
            var tel = v('telefono').replace(/\D/g, ''), correo = v('correo').toLowerCase();
            err('telefono', !/^3\d{9}$/.test(tel) ? 'Escribe un celular de 10 dígitos que empiece por 3.' : '');
            err('correo', !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(correo) ? 'Escribe el correo: es su usuario para entrar.' : '');
            err('depto', cbDepto.value() ? '' : 'Elige el departamento.'); err('ciudad', cbCiudad.value() ? '' : 'Elige la ciudad.');
            err('direccion', v('direccion').length < 5 ? 'Escribe la dirección.' : '');
        }
        if (p === 3) {
            err('sede', v('sede') ? '' : 'Elige la sede.');
            if (!editando && !v('sede')) { /* ya marcado */ }
            else if (!editando && !elegidos.length) err('sede', 'Agrega al menos un estudiante a cargo: el acudiente se registra con sus estudiantes.');
            if (editando && +v('sede') !== editando.sede_id && aCargo(editando).length) err('sede', 'Tiene estudiantes a cargo en ' + sede(editando.sede_id).nombre + ': desvincúlalos o pásalos a otro acudiente antes de cambiarlo de sede.');
        }
        var malo = CAMPOS[p].find(function (c) { var e = document.querySelector('#acu-form [data-error="' + c + '"]'); return e && !e.hidden; });
        if (malo) { if (malo === 'depto') cbDepto.querySelector('button').focus(); else if (malo === 'ciudad') cbCiudad.querySelector('button').focus(); else f(malo).focus(); }
        return !malo;
    }
    function pintarElegidos() {
        $('acu-f-elegidos').innerHTML = elegidos.map(function (x, i) {
            var e = D.estudiantes.find(function (y) { return y.id === x.id; });
            return '<li><span>' + esc(nombre(e) + ' · ' + x.parentesco) + (e.acudiente ? ' <span class="acu-muted">(reemplaza a ' + esc(e.acudiente.nombre) + ')</span>' : '') + '</span><button type="button" class="acu-icon-btn" data-quitar="' + i + '" aria-label="Quitar">' + ICON.quitar + '</button></li>';
        }).join('');
        opcionesEstudiante('acu-f-estudiante', f('sede').value, elegidos.map(function (x) { return x.id; }));
        resumen();
    }
    f('sede').addEventListener('change', function () { elegidos = []; pintarElegidos(); err('sede', ''); });
    $('acu-f-agregar').addEventListener('click', function () {
        var id = +f('estudiante').value, par = f('parentesco').value;
        if (!id || !par) { toast('Elige el estudiante y el parentesco.'); return; }
        elegidos.push({ id: id, parentesco: par }); f('parentesco').value = ''; pintarElegidos();
    });
    $('acu-f-elegidos').addEventListener('click', function (e) { var b = e.target.closest('[data-quitar]'); if (b) { elegidos.splice(+b.dataset.quitar, 1); pintarElegidos(); } });
    function resumen() {
        var s = sede(f('sede').value);
        $('acu-resumen').innerHTML = [['Nombre', f('nombres').value.trim() + ' ' + f('apellidos').value.trim()], ['Documento', f('tipo').value + ' ' + f('documento').value.replace(/\D/g, '')],
            ['Correo', f('correo').value.trim().toLowerCase()], ['Sede', s ? s.nombre : 'Sin elegir']]
            .map(function (x) { return '<div><span class="acu-info-lbl">' + esc(x[0]) + '</span><span class="acu-info-val">' + esc(x[1]) + '</span></div>'; }).join('');
    }
    function irPaso(p) {
        paso = p;
        document.querySelectorAll('#acu-form .acu-paso').forEach(function (el) { el.hidden = +el.dataset.paso !== p; });
        document.querySelectorAll('#acu-form .acu-step').forEach(function (el) { var n = +el.dataset.step; el.classList.toggle('is-active', n === p); el.classList.toggle('is-done', n < p); });
        $('acu-modal-sub').textContent = 'Paso ' + p + ' de 3';
        $('acu-atras').hidden = p === 1;
        $('acu-siguiente').textContent = p < 3 ? 'Siguiente' : editando ? 'Guardar cambios' : 'Registrar acudiente';
        if (p === 3) pintarElegidos();
    }
    document.querySelectorAll('#acu-form input, #acu-form select').forEach(function (i) { var k = (i.id || '').replace('acu-f-', ''); if (k) i.addEventListener('input', function () { err(k, ''); }); });
    function abrirForm(id) {
        editando = id ? acudiente(id) : null;
        var a = editando || { nombres: '', apellidos: '', tipo_documento: 'CC', numero_documento: '', genero: '', profesion: '', telefono: '', email: '', departamento: '', ciudad: '', direccion: '', sede_id: '' };
        f('nombres').value = a.nombres; f('apellidos').value = a.apellidos; f('tipo').value = a.tipo_documento; f('documento').value = a.numero_documento;
        f('genero').value = a.genero || ''; f('profesion').value = a.profesion || ''; f('telefono').value = a.telefono; f('correo').value = a.email; f('direccion').value = a.direccion;
        f('sede').value = a.sede_id || '';
        elegidos = [];
        $('acu-f-bloque-est').hidden = !!editando;   // al editar, los estudiantes se manejan desde la ficha
        cbDepto.setValue('', 'Selecciona un departamento', { silent: true }); cbCiudad.reset('Elige el departamento primero');
        if (a.departamento) listo.then(function () { cbDepto.setValue(a.departamento, a.departamento, { silent: true }); cbCiudad.setOptions(ciudadesDe(a.departamento)); cbCiudad.setValue(a.ciudad, a.ciudad, { silent: true }); });
        document.querySelectorAll('#acu-form .acu-field-error').forEach(function (e) { e.hidden = true; });
        document.querySelectorAll('#acu-form .is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        $('acu-modal-titulo').textContent = editando ? 'Editar a ' + nombre(editando) : 'Nuevo acudiente';
        $('acu-nota-clave').hidden = !!editando;
        abrirModal('acu-modal', f('nombres'));
        irPaso(1);
    }
    $('acu-nuevo').addEventListener('click', function () { abrirForm(null); });
    $('acu-dw-editar').addEventListener('click', function () { abrirForm(actual.id); });
    $('acu-atras').addEventListener('click', function () { if (paso > 1) irPaso(paso - 1); });
    $('acu-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validar(paso)) return;
        if (paso < 3) { irPaso(paso + 1); return; }
        var cuerpo = { nombres: f('nombres').value.trim(), apellidos: f('apellidos').value.trim(), tipo_documento: f('tipo').value, numero_documento: f('documento').value,
            genero: f('genero').value || null, profesion: f('profesion').value.trim(), telefono: f('telefono').value, email: f('correo').value.trim(),
            departamento_residencia: cbDepto.value(), ciudad_residencia: cbCiudad.value(), direccion: f('direccion').value.trim(), sede_id: +f('sede').value,
            /* Al editar se mandan los que tiene hoy: los estudiantes se cambian desde la ficha */
            estudiantes: editando ? aCargo(editando).map(function (e) { return { id: e.id, parentesco: e.parentesco }; }) : elegidos };
        var btn = $('acu-siguiente'); btn.disabled = true;
        enviar(editando ? 'PUT' : 'POST', editando ? '/general/acudientes/' + editando.id : '/general/acudientes', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (fallo) {
                btn.disabled = false;
                var CAMPO = { nombres: 'nombres', apellidos: 'apellidos', tipo_documento: 'documento', numero_documento: 'documento', genero: 'genero', profesion: 'profesion',
                    telefono: 'telefono', email: 'correo', departamento_residencia: 'depto', ciudad_residencia: 'ciudad', direccion: 'direccion', sede_id: 'sede', estudiantes: 'sede' };
                var k = Object.keys(fallo.errores || {}).map(function (c) { return c.indexOf('estudiantes') === 0 ? 'estudiantes' : c; }).find(function (c) { return CAMPO[c]; });
                if (!k) { toast(fallo.message); return; }
                var c = CAMPO[k], p = +Object.keys(CAMPOS).find(function (n) { return CAMPOS[n].indexOf(c) >= 0; });
                var msg = (fallo.errores[k] || fallo.errores[Object.keys(fallo.errores)[0]])[0];
                irPaso(p); err(c, msg);
            });
    });

    pintarKpis();
    filtrar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
}());
