/* =========================================================
   ESAT — Panel Admin (ESAT general): Comunicados
   Los comunicados de todo ESAT: lista con filtros, detalle, crear/editar/duplicar (todas o algunas sedes, por
   rol; publicar, programar o borrador), archivar con motivo y eliminar borradores propios. Los datos llegan en
   window.GEN_DATOS (AdminGeneral ComunicadoController@index); guardar va por fetch a /general/comunicados y recarga
   con el aviso. Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 10;
    var ICON = {
        enviar: '<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        borrador: '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        ojo: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
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

    var toastEl = $('com-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="com-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="com-pg-gap">…</span>'
                    : '<button type="button" class="com-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="com-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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
    var HOY = Date.now();
    var DESTINO = { todos: 'Todos', estudiantes: 'Estudiantes', docentes: 'Docentes', acudientes: 'Acudientes', grupo: 'Un grupo' };
    var ESTADO = { borrador: ['Borrador', 'muted'], programado: ['Programado', 'info'], publicado: ['Publicado', 'active'], archivado: ['Archivado', 'warn'] };
    var AUTOR = { admin: 'Admin', secretaria: 'Secretaría', docente: 'Docente' };
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function vivos() { return D.comunicados; }
    /* Un programado sale solo cuando llega su hora */
    function estado(c) { return c.estado === 'programado' && new Date(c.publicado_en).getTime() <= HOY ? 'publicado' : c.estado; }
    function sedesDe(c) { return c.sedes === 'todas' ? D.sedes.map(function (s) { return s.id; }) : c.sedes; }
    function paraTxt(c) {
        var sedes = c.sedes === 'todas' ? 'Todas las sedes' : c.sedes.map(function (id) { var s = sedePorId(id); return s ? s.nombre : ''; }).join(', ');
        return { sedes: sedes, a: c.destino === 'grupo' ? c.grupo : DESTINO[c.destino] };
    }
    /* Cuántos lo recibirían hoy: estudiantes con matrícula vigente, docentes y acudientes activos de cada sede (lo calcula el servidor) */
    function alcance(ids, destino) {
        return ids.reduce(function (n, id) {
            var a = D.alcance[id]; if (!a) return n;
            return n + (destino === 'todos' ? a.estudiantes + a.docentes + a.acudientes : (a[destino] || 0));
        }, 0);
    }
    function pct(c) { return c.destinatarios ? Math.round(c.leidos / c.destinatarios * 100) : 0; }
    function fechaHora(f) { var d = new Date(f); return fechaLarga(f) + ', ' + d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' }); }
    function tag(e) { return '<span class="app-status-tag app-status-' + ESTADO[e][1] + '">' + ESTADO[e][0] + '</span>'; }

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var l = vivos(), cuenta = function (e) { return l.filter(function (c) { return estado(c) === e; }).length; };
        var pub = l.filter(function (c) { return estado(c) === 'publicado' && c.destinatarios; });
        var lect = pub.length ? Math.round(pub.reduce(function (t, c) { return t + pct(c); }, 0) / pub.length) : 0;
        var k = [['green', 'enviar', num(cuenta('publicado')), 'Publicados'], ['blue', 'reloj', num(cuenta('programado')), 'Programados'],
            ['orange', 'borrador', num(cuenta('borrador')), cuenta('borrador') === 1 ? 'Borrador' : 'Borradores'], ['blue', 'ojo', lect + ' %', 'Leídos en promedio']];
        $('com-kpis').innerHTML = k.map(function (x) {
            return '<div class="com-kpi com-kpi-' + x[0] + '"><div class="com-kpi-icon">' + ICON[x[1]] + '</div><div><div class="com-kpi-val">' + esc(x[2]) + '</div><div class="com-kpi-lbl">' + esc(x[3]) + '</div></div></div>';
        }).join('');
    }

    /* ── Filtros y tabla ────────────────────────── */
    var cbSede = $('com-cb-sede'), cbEstado = $('com-cb-estado'), cbAutor = $('com-cb-autor');
    [cbSede, cbEstado, cbAutor].forEach(initCombobox);
    cbSede.setOptions([{ value: '', label: 'Todas las sedes' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: s.nombre }; })));
    cbEstado.setOptions([{ value: '', label: 'Todos los estados' }, { value: 'publicado', label: 'Publicados' }, { value: 'programado', label: 'Programados' }, { value: 'borrador', label: 'Borradores' }, { value: 'archivado', label: 'Archivados' }]);
    cbAutor.setOptions([{ value: '', label: 'Todos los autores' }, { value: 'admin', label: 'Admin' }, { value: 'secretaria', label: 'Secretarías' }, { value: 'docente', label: 'Docentes' }]);
    cbSede.setValue('', 'Todas las sedes', { silent: true }); cbEstado.setValue('', 'Todos los estados', { silent: true }); cbAutor.setValue('', 'Todos los autores', { silent: true });

    var pagina = 1, filtrados = [];
    function filtrar() {
        var q = normal($('com-buscar').value.trim()), sede = cbSede.value(), est = cbEstado.value(), autor = cbAutor.value();
        filtrados = vivos().filter(function (c) {
            if (q && normal(c.titulo + ' ' + c.mensaje).indexOf(q) === -1) return false;
            if (sede && sedesDe(c).map(String).indexOf(sede) < 0) return false;
            if (est && estado(c) !== est) return false;
            if (autor && c.autor.tipo !== autor) return false;
            return true;
        }).sort(function (a, b) {
            var orden = { borrador: 0, programado: 1, publicado: 2, archivado: 3 };
            return (orden[estado(a)] - orden[estado(b)]) || (new Date(b.publicado_en || b.creado) - new Date(a.publicado_en || a.creado));
        });
        pagina = 1; pintarTabla();
    }
    function pintarTabla() {
        var desde = (pagina - 1) * POR_PAGINA;
        $('com-count').textContent = filtrados.length + (filtrados.length === 1 ? ' comunicado' : ' comunicados');
        $('com-empty').hidden = filtrados.length > 0;
        $('com-body').innerHTML = filtrados.slice(desde, desde + POR_PAGINA).map(function (c) {
            var e = estado(c), p = paraTxt(c);
            var fecha = e === 'borrador' ? 'Creado ' + fechaLarga(c.creado) : e === 'programado' ? 'Sale el ' + fechaHora(c.publicado_en) : fechaLarga(c.publicado_en);
            var lectura = e === 'publicado' || e === 'archivado'
                ? '<div class="com-lectura" title="' + num(c.leidos) + ' de ' + num(c.destinatarios) + ' lo leyeron"><div class="com-lectura-bg"><span style="width:' + pct(c) + '%"></span></div><span class="com-lectura-pct">' + pct(c) + ' %</span></div>'
                : '<span class="com-muted">—</span>';
            return '<tr class="com-row" tabindex="0" data-id="' + c.id + '">' +
                '<td class="com-titulo-cell"><div class="com-strong">' + esc(c.titulo) + '</div><div class="com-extracto">' + esc(c.mensaje) + '</div></td>' +
                '<td>' + esc(p.a) + '<div class="com-muted">' + esc(p.sedes) + '</div></td>' +
                '<td><span class="' + (c.autor.tipo === 'admin' ? 'com-autor-admin' : '') + '">' + esc(AUTOR[c.autor.tipo]) + '</span><div class="com-muted">' + esc(c.autor.nombre) + '</div></td>' +
                '<td>' + tag(e) + '</td><td class="com-fecha">' + esc(fecha) + '</td><td>' + lectura + '</td></tr>';
        }).join('');
        paginar($('com-paginacion'), filtrados.length, pagina, function (p) { pagina = p; pintarTabla(); });
    }
    $('com-buscar').addEventListener('input', filtrar);
    [cbSede, cbEstado, cbAutor].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', filtrar); });
    $('com-body').addEventListener('click', function (e) { var tr = e.target.closest('tr[data-id]'); if (tr) abrirDrawer(+tr.dataset.id); });
    $('com-body').addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirDrawer(+e.target.dataset.id); }
    });

    /* ── Drawer ─────────────────────────────────── */
    var drawer = $('com-drawer'), actual = null, volverDrawer = null;
    function porId(id) { return D.comunicados.find(function (c) { return c.id === id; }); }
    function abrirDrawer(id) {
        actual = porId(id); if (!actual) return;
        if (!drawer.classList.contains('is-open')) volverDrawer = document.activeElement;
        var c = actual, e = estado(c), p = paraTxt(c), propio = c.autor.tipo === 'admin';
        $('com-dw-titulo').textContent = c.titulo;
        $('com-dw-sub').innerHTML = tag(e) + ' <span class="com-dw-autor">' + esc(AUTOR[c.autor.tipo] + ' · ' + c.autor.nombre) + '</span>';
        var filas = [['Sedes', p.sedes], ['Para', p.a]];
        var archivadas = (c.archivado_en_sedes || []).map(function (id) { var s = sedePorId(id); return s ? s.nombre : ''; }).filter(Boolean);
        if (archivadas.length) filas.push(['Archivado por la secretaría en', archivadas.join(', ')]);
        if (e === 'programado') filas.push(['Sale el', fechaHora(c.publicado_en)]);
        else if (e !== 'borrador') filas.push(['Publicado', fechaHora(c.publicado_en)]);
        filas.push(['Creado', fechaLarga(c.creado)]);
        if (e === 'publicado' || e === 'archivado') filas.push(['Lo leyeron', num(c.leidos) + ' de ' + num(c.destinatarios) + ' (' + pct(c) + ' %)']);
        else filas.push(['Lo recibirían hoy', num(alcance(sedesDe(c), c.destino === 'grupo' ? 'estudiantes' : c.destino)) + ' personas']);
        $('com-dw-info').innerHTML = filas.map(function (x) { return '<div><span class="com-info-lbl">' + esc(x[0]) + '</span><span class="com-info-val">' + esc(x[1]) + '</span></div>'; }).join('');
        $('com-dw-mensaje').textContent = c.mensaje;
        $('com-dw-motivo').hidden = e !== 'archivado';
        $('com-dw-motivo').textContent = 'Archivado: ' + (c.motivo_archivo || '');
        $('com-dw-editar').hidden = !(propio && e !== 'archivado');
        $('com-dw-duplicar').hidden = !propio;
        $('com-dw-archivar').hidden = e === 'archivado' || e === 'borrador';
        $('com-dw-eliminar').hidden = !(propio && e === 'borrador');
        drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('.com-close').focus();
    }
    function cerrarDrawer() {
        drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true');
        if (volverDrawer && document.contains(volverDrawer)) volverDrawer.focus();
    }
    drawer.querySelectorAll('[data-cerrar-drawer]').forEach(function (b) { b.addEventListener('click', cerrarDrawer); });

    /* ── Modales ────────────────────────────────── */
    var modalAbierto = null, volverModal = null;
    function abrirModal(id, foco) { volverModal = document.activeElement; modalAbierto = $(id); modalAbierto.hidden = false; (foco || modalAbierto.querySelector('input, textarea, button')).focus(); }
    function cerrarModal() { if (!modalAbierto) return; modalAbierto.hidden = true; modalAbierto = null; if (volverModal && document.contains(volverModal)) volverModal.focus(); }
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || document.querySelector('.app-combobox.is-open')) return;
        if (modalAbierto) cerrarModal(); else if (drawer.classList.contains('is-open')) cerrarDrawer();
    });

    /* ── Archivar / eliminar ────────────────────── */
    var accion = null;
    function confirmar(tipo) {
        accion = tipo;
        var c = actual;
        $('com-conf-titulo').textContent = tipo === 'archivar' ? '¿Archivar «' + c.titulo + '»?' : '¿Eliminar el borrador «' + c.titulo + '»?';
        $('com-conf-texto').textContent = tipo === 'archivar'
            ? 'Deja de verse en los paneles de los destinatarios. Queda aquí como archivado, con el motivo.'
            : 'El borrador se borra y no se puede recuperar.';
        $('com-conf-motivo-wrap').hidden = tipo !== 'archivar';
        $('com-conf-motivo').value = ''; $('com-e-motivo').hidden = true; $('com-conf-motivo').classList.remove('is-invalid');
        $('com-conf-ok').textContent = tipo === 'archivar' ? 'Archivar' : 'Eliminar borrador';
        abrirModal('com-confirmar', tipo === 'archivar' ? $('com-conf-motivo') : $('com-conf-ok'));
    }
    $('com-dw-archivar').addEventListener('click', function () { confirmar('archivar'); });
    $('com-dw-eliminar').addEventListener('click', function () { confirmar('eliminar'); });
    $('com-conf-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var c = actual, motivo = $('com-conf-motivo').value.trim();
        if (accion === 'archivar' && motivo.length < 5) {
            $('com-e-motivo').textContent = 'Escribe el motivo (mínimo 5 caracteres).'; $('com-e-motivo').hidden = false; $('com-conf-motivo').classList.add('is-invalid'); $('com-conf-motivo').focus(); return;
        }
        var btn = $('com-conf-ok'); btn.disabled = true;
        (accion === 'archivar' ? enviar('PATCH', '/general/comunicados/' + c.id + '/archivar', { motivo: motivo }) : enviar('DELETE', '/general/comunicados/' + c.id))
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (fallo) {
                btn.disabled = false;
                if (fallo.errores && fallo.errores.motivo) { $('com-e-motivo').textContent = fallo.errores.motivo[0]; $('com-e-motivo').hidden = false; $('com-conf-motivo').classList.add('is-invalid'); }
                else { cerrarModal(); toast(fallo.message); }
            });
    });

    /* ── Crear / editar ─────────────────────────── */
    var editando = null;
    $('com-f-sedes').innerHTML = D.sedes.map(function (s) { return '<label class="com-check"><input type="checkbox" value="' + s.id + '">' + esc(s.nombre) + '</label>'; }).join('');
    function radio(nombre) { var r = document.querySelector('#com-form input[name="' + nombre + '"]:checked'); return r ? r.value : ''; }
    function marcar(nombre, valor) { document.querySelectorAll('#com-form input[name="' + nombre + '"]').forEach(function (r) { r.checked = r.value === valor; }); }
    function sedesElegidas() { return radio('alcance') === 'todas' ? 'todas' : [].slice.call(document.querySelectorAll('#com-f-sedes input:checked')).map(function (i) { return +i.value; }); }
    function refrescarForm() {
        $('com-f-sedes').hidden = radio('alcance') !== 'algunas';
        $('com-programar').hidden = radio('cuando') !== 'programar';
        var s = sedesElegidas(), ids = s === 'todas' ? D.sedes.map(function (x) { return x.id; }) : s;
        var n = alcance(ids, radio('destino'));
        $('com-alcance').textContent = ids.length ? 'Lo recibirían hoy ' + num(n) + (n === 1 ? ' persona' : ' personas') + ' en ' + (s === 'todas' ? 'las ' + ids.length + ' sedes' : ids.length === 1 ? '1 sede' : ids.length + ' sedes') + '.' : 'Elige al menos una sede.';
        var c = radio('cuando');
        $('com-guardar').textContent = c === 'programar' ? 'Programar' : c === 'borrador' ? 'Guardar borrador' : editando && estado(editando) === 'publicado' ? 'Guardar cambios' : 'Publicar';
        $('com-contador').textContent = $('com-f-mensaje').value.length + ' / 2000';
    }
    $('com-form').addEventListener('change', function () { refrescarForm(); if (sedesElegidas() === 'todas' || sedesElegidas().length) err('sedes', ''); });
    $('com-f-mensaje').addEventListener('input', function () { refrescarForm(); err('mensaje', ''); });
    $('com-f-titulo').addEventListener('input', function () { err('titulo', ''); });
    $('com-f-fecha').addEventListener('input', function () { err('fecha', ''); });
    function err(campo, msg) {
        var e = $('com-e-' + campo); e.textContent = msg || ''; e.hidden = !msg;
        var el = { titulo: 'com-f-titulo', mensaje: 'com-f-mensaje', fecha: 'com-f-fecha' }[campo];
        if (el) $(el).classList.toggle('is-invalid', !!msg);
    }
    function aLocal(d) { var z = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return z.toISOString().slice(0, 16); }
    function abrirForm(c, duplicar) {
        editando = duplicar ? null : c;
        var e = c ? estado(c) : null, publicado = !!editando && e === 'publicado';
        $('com-modal-titulo').textContent = !c ? 'Nuevo comunicado' : duplicar ? 'Duplicar comunicado' : 'Editar comunicado';
        $('com-f-titulo').value = c ? c.titulo : ''; $('com-f-mensaje').value = c ? c.mensaje : '';
        marcar('alcance', c && c.sedes !== 'todas' ? 'algunas' : 'todas');
        document.querySelectorAll('#com-f-sedes input').forEach(function (i) { i.checked = !!c && c.sedes !== 'todas' && c.sedes.indexOf(+i.value) >= 0; });
        marcar('destino', c && c.destino !== 'grupo' ? c.destino : 'todos');
        marcar('cuando', !editando ? 'ahora' : e === 'programado' ? 'programar' : e === 'borrador' ? 'borrador' : 'ahora');
        $('com-f-fecha').value = editando && e === 'programado' ? aLocal(new Date(c.publicado_en)) : '';
        $('com-f-fecha').min = aLocal(new Date(Date.now() + 5 * 60000));
        /* Un publicado no cambia de destinatarios ni vuelve a borrador */
        document.querySelectorAll('#com-f-alcance input, #com-f-sedes input, #com-f-destino input').forEach(function (i) { i.disabled = publicado; });
        $('com-bloqueado').hidden = !publicado;
        $('com-cuando-wrap').hidden = publicado;
        ['sedes', 'titulo', 'mensaje', 'fecha'].forEach(function (x) { err(x, ''); });
        refrescarForm();
        abrirModal('com-modal', $('com-f-titulo'));
    }
    $('com-nuevo').addEventListener('click', function () { abrirForm(null); });
    $('com-dw-editar').addEventListener('click', function () { abrirForm(actual); });
    $('com-dw-duplicar').addEventListener('click', function () { abrirForm(actual, true); });
    $('com-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var titulo = $('com-f-titulo').value.trim(), mensaje = $('com-f-mensaje').value.trim(), cuando = radio('cuando'), sedes = sedesElegidas();
        var publicado = editando && estado(editando) === 'publicado';
        if (publicado) cuando = 'ahora';
        err('sedes', sedes !== 'todas' && !sedes.length ? 'Elige al menos una sede.' : '');
        err('titulo', titulo.length < 5 ? 'Escribe un título de al menos 5 caracteres.' : '');
        err('mensaje', cuando !== 'borrador' && mensaje.length < 10 ? 'Escribe el mensaje (mínimo 10 caracteres).' : '');
        var fecha = $('com-f-fecha').value ? new Date($('com-f-fecha').value) : null;
        err('fecha', cuando === 'programar' && (!fecha || fecha.getTime() <= Date.now()) ? 'Elige una fecha y hora que todavía no hayan pasado.' : '');
        var malo = document.querySelector('#com-form .com-field-error:not([hidden])');
        if (malo) { var campo = malo.parentNode.querySelector('input:not([type=radio]):not([type=checkbox]), textarea'); if (campo) campo.focus(); return; }

        var cuerpo = { sedes: sedes, destino: radio('destino'), titulo: titulo, mensaje: mensaje,
            accion: { ahora: 'publicar', programar: 'programar', borrador: 'borrador' }[cuando], publicar_en: cuando === 'programar' ? $('com-f-fecha').value : null };
        var btn = $('com-guardar'); btn.disabled = true;
        enviar(editando ? 'PUT' : 'POST', editando ? '/general/comunicados/' + editando.id : '/general/comunicados', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (fallo) {
                btn.disabled = false;
                var CAMPO = { sedes: 'sedes', 'sedes.0': 'sedes', titulo: 'titulo', mensaje: 'mensaje', publicar_en: 'fecha', destino: 'sedes' };
                var k = Object.keys(fallo.errores || {}).find(function (c) { return CAMPO[c]; });
                if (k) err(CAMPO[k], fallo.errores[k][0]); else toast(fallo.message);
            });
    });

    pintarKpis();
    filtrar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
}());
