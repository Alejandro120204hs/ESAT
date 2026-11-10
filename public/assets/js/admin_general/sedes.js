/* =========================================================
   ESAT — Panel Admin (ESAT general): Sedes
   Lista, búsqueda, paginación, detalle (drawer) y crear/editar (modal). Los datos llegan del servidor en
   window.GEN_DATOS (SedeController@index); guardar va por fetch a /general/sedes (SedeRequest valida lo mismo) y
   recarga la página con el aviso. Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 10;
    var ICON = {
        sede: '<svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>',
        editar: '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        ver: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        sedes: '<svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>',
        persona: '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        estudiantes: '<svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/></svg>'
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

    var toastEl = $('sed-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="sed-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="sed-pg-gap">…</span>'
                    : '<button type="button" class="sed-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="sed-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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

    /* ── Datos de cada sede ─────────────────────── */
    function secretariasDe(s) { return D.secretarias.filter(function (x) { return x.sede_id === s.id; }); }
    function activasDe(s) { return secretariasDe(s).filter(function (x) { return x.activo; }); }
    function lugar(s) { return s.ciudad + ', ' + s.departamento; }
    function nombreSec(x) { return x.nombres + ' ' + x.apellidos; }

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var sinSec = D.sedes.filter(function (s) { return !activasDe(s).length; }).length;
        var est = D.sedes.reduce(function (t, s) { return t + (s.estudiantes || 0); }, 0);
        var deptos = {}; D.sedes.forEach(function (s) { deptos[s.departamento] = 1; });
        var k = [
            ['blue', 'sedes', num(D.sedes.length), D.sedes.length === 1 ? 'Sede' : 'Sedes'],
            ['green', 'estudiantes', num(est), 'Estudiantes activos'],
            ['orange', 'persona', num(Object.keys(deptos).length), Object.keys(deptos).length === 1 ? 'Departamento' : 'Departamentos'],
            [sinSec ? 'red' : 'green', 'alerta', num(sinSec), sinSec === 1 ? 'Sede sin secretaría' : 'Sedes sin secretaría']
        ];
        $('sed-kpis').innerHTML = k.map(function (x) {
            return '<div class="sed-kpi sed-kpi-' + x[0] + '"><div class="sed-kpi-icon">' + ICON[x[1]] + '</div><div><div class="sed-kpi-val">' + x[2] + '</div><div class="sed-kpi-lbl">' + x[3] + '</div></div></div>';
        }).join('');
    }

    /* ── Tabla ──────────────────────────────────── */
    var pagina = 1, filtradas = [];
    function filtrar() {
        var q = normal($('sed-buscar').value.trim());
        filtradas = D.sedes.filter(function (s) { return !q || normal(s.nombre + ' ' + s.ciudad + ' ' + s.departamento + ' ' + s.direccion).indexOf(q) !== -1; })
            .sort(function (a, b) { return a.nombre.localeCompare(b.nombre, 'es'); });
        pagina = 1;
        pintarTabla();
    }
    function pintarTabla() {
        var desde = (pagina - 1) * POR_PAGINA;
        $('sed-count').textContent = filtradas.length + (filtradas.length === 1 ? ' sede' : ' sedes');
        $('sed-empty').hidden = filtradas.length > 0;
        $('sed-body').innerHTML = filtradas.slice(desde, desde + POR_PAGINA).map(function (s) {
            var act = activasDe(s);
            var sec = !act.length ? '<span class="app-status-tag app-status-danger">Sin secretaría</span>'
                : '<div class="sed-strong">' + esc(nombreSec(act[0])) + (act.length > 1 ? ' <span class="sed-muted" style="display:inline">+' + (act.length - 1) + '</span>' : '') + '</div>' +
                  '<div class="sed-muted">' + esc(act[0].email) + '</div>';
            return '<tr class="sed-row" tabindex="0" data-id="' + s.id + '">' +
                '<td><div class="sed-cell"><div class="sed-avatar">' + ICON.sede + '</div><div><div class="sed-strong">' + esc(s.nombre) + '</div><div class="sed-muted">' + esc(s.direccion) + '</div></div></div></td>' +
                '<td>' + esc(s.ciudad) + '<div class="sed-muted">' + esc(s.departamento) + '</div></td>' +
                '<td>' + sec + '</td>' +
                '<td class="sed-num"><span class="sed-strong">' + num(s.estudiantes) + '</span></td>' +
                '<td class="sed-num">' + num((s.programas || []).length) + '</td>' +
                '<td class="sed-actions"><button type="button" class="sed-icon-btn" data-editar="' + s.id + '" aria-label="Editar ' + esc(s.nombre) + '">' + ICON.editar + '</button></td></tr>';
        }).join('');
        paginar($('sed-paginacion'), filtradas.length, pagina, function (p) { pagina = p; pintarTabla(); });
    }
    $('sed-buscar').addEventListener('input', filtrar);
    $('sed-body').addEventListener('click', function (e) {
        var ed = e.target.closest('[data-editar]');
        if (ed) { e.stopPropagation(); abrirForm(+ed.dataset.editar); return; }
        var tr = e.target.closest('tr[data-id]');
        if (tr) abrirDrawer(+tr.dataset.id);
    });
    $('sed-body').addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirDrawer(+e.target.dataset.id); }
    });

    /* ── Drawer ─────────────────────────────────── */
    var drawer = $('sed-drawer'), actual = null, volverDrawer = null;
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === id; }); }
    function tab(nombre) {
        drawer.querySelectorAll('.sed-tab').forEach(function (b) { var on = b.dataset.tab === nombre; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        drawer.querySelectorAll('.sed-panel').forEach(function (p) { p.hidden = p.dataset.panel !== nombre; });
    }
    drawer.querySelectorAll('.sed-tab').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });
    function abrirDrawer(id) {
        actual = sedePorId(id);
        if (!actual) return;
        volverDrawer = document.activeElement;
        var s = actual;
        $('sed-dw-nombre').textContent = 'Sede ' + s.nombre;
        $('sed-dw-lugar').textContent = lugar(s);
        var info = [['Nombre', s.nombre], ['En ESAT desde', s.creada ? fechaLarga(s.creada) : '—'], ['Departamento', s.departamento], ['Ciudad o municipio', s.ciudad], ['Dirección', s.direccion, true],
            ['Estudiantes activos', num(s.estudiantes)], ['Grupos en clases', num(s.grupos)], ['Cupos ocupados', num(s.ocupados) + ' de ' + num(s.cupos)]];
        $('sed-dw-datos').innerHTML = info.map(function (x) {
            return '<div' + (x[2] ? ' class="sed-info-full"' : '') + '><span class="sed-info-lbl">' + esc(x[0]) + '</span><span class="sed-info-val">' + esc(x[1]) + '</span></div>';
        }).join('');
        var secs = secretariasDe(s);
        $('sed-dw-secretarias').innerHTML = secs.length ? secs.map(function (x) {
            var estado = !x.activo ? '<span class="app-status-tag app-status-muted">Inactiva</span>'
                : !x.ultimo_acceso ? '<span class="app-status-tag app-status-warn">Nunca ha ingresado</span>'
                : '<span class="app-status-tag app-status-active">Activa · entró ' + esc(hace(x.ultimo_acceso)) + '</span>';
            return '<li><div><div class="sed-strong">' + esc(nombreSec(x)) + '</div><div class="sed-muted">' + esc(x.email) + '</div></div>' + estado + '</li>';
        }).join('') + '<li style="border:none;padding:4px 0;justify-content:flex-end"><a class="sed-link" href="/general/secretarias?sede=' + s.id + '">Gestionar secretarías</a></li>'
            : '<li class="sed-list-empty">La sede no tiene cuentas de secretaría. <a class="sed-link" href="/general/secretarias?nueva=' + s.id + '">Crear una</a></li>';
        var progs = (s.programas || []).map(function (pid) { return D.programas.find(function (p) { return p.id === pid; }); }).filter(Boolean);
        $('sed-dw-programas').innerHTML = progs.length ? progs.map(function (p) {
            var n = (s.por_programa || {})[p.id] || 0;
            return '<li><span>' + esc(p.nombre) + '</span><span class="sed-muted">' + n + (n === 1 ? ' estudiante' : ' estudiantes') + '</span></li>';
        }).join('') : '<li class="sed-list-empty">La sede todavía no ofrece programas del catálogo. <a class="sed-link" href="/general/programas">Asígnalos en Programas</a></li>';
        tab('datos');
        drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('.sed-close').focus();
    }
    function cerrarDrawer() {
        drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true');
        if (volverDrawer && document.contains(volverDrawer)) volverDrawer.focus();
    }
    drawer.querySelectorAll('[data-cerrar-drawer]').forEach(function (b) { b.addEventListener('click', cerrarDrawer); });
    $('sed-dw-editar').addEventListener('click', function () { abrirForm(actual.id); });
    $('sed-dw-entrar').addEventListener('click', function () { abrirEntrar(actual); });

    /* ── Modales ────────────────────────────────── */
    var modalAbierto = null, volverModal = null;
    function abrirModal(id, foco) {
        volverModal = document.activeElement;
        modalAbierto = $(id); modalAbierto.hidden = false;
        (foco || modalAbierto.querySelector('input, button')).focus();
    }
    function cerrarModal() {
        if (!modalAbierto) return;
        modalAbierto.hidden = true; modalAbierto = null;
        if (volverModal && document.contains(volverModal)) volverModal.focus();
    }
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (document.querySelector('.app-combobox.is-open')) return;   // el Escape cierra primero la lista
        if (modalAbierto) cerrarModal(); else if (drawer.classList.contains('is-open')) cerrarDrawer();
    });


    /* ── Entrar a la sede: trabaja con las pantallas de la secretaría (EntrarSedeController) ── */
    function entrarASede(id, destino) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = '/general/sedes/' + encodeURIComponent(id) + '/entrar';
        [['_token', document.querySelector('meta[name="csrf-token"]').content], ['destino', destino || 'dashboard']].forEach(function (c) {
            var i = document.createElement('input'); i.type = 'hidden'; i.name = c[0]; i.value = c[1]; f.appendChild(i);
        });
        document.body.appendChild(f); f.submit();
    }

    function abrirEntrar(s) {
        $('sed-entrar-titulo').textContent = 'Entrar a ' + s.nombre;
        $('sed-entrar-sub').textContent = lugar(s);
        abrirModal('sed-entrar', $('sed-entrar-ok'));
    }
    $('sed-entrar-ok').addEventListener('click', function () { this.disabled = true; entrarASede(actual.id, 'dashboard'); });

    /* ── Formulario crear / editar ──────────────── */
    var cbDepto = $('sed-cb-depto'), cbCiudad = $('sed-cb-ciudad');
    initCombobox(cbDepto); initCombobox(cbCiudad);
    var colombia = [], colombiaLista = fetch('/assets/data/co-departamentos-ciudades.json')
        .then(function (r) { return r.json(); })
        .then(function (d) { colombia = d; cbDepto.setOptions(d.map(function (x) { return x.departamento; })); })
        .catch(function () { toast('No se pudo cargar la lista de departamentos. Recarga la página.'); });
    function ciudadesDe(depto) { var d = colombia.find(function (x) { return x.departamento === depto; }); return d ? d.ciudades : []; }
    cbDepto.querySelector('[data-combobox-value]').addEventListener('change', function () {
        cbCiudad.reset('Selecciona una ciudad');
        cbCiudad.setOptions(ciudadesDe(cbDepto.value()));
    });

    var editando = null;
    function error(campo, msg) {
        var e = $('sed-e-' + campo); e.textContent = msg || ''; e.hidden = !msg;
        if (campo === 'depto') cbDepto.classList.toggle('is-invalid', !!msg);
        else if (campo === 'ciudad') cbCiudad.classList.toggle('is-invalid', !!msg);
        else $('sed-f-' + campo).classList.toggle('is-invalid', !!msg);
    }
    function abrirForm(id) {
        editando = id ? sedePorId(id) : null;
        ['nombre', 'depto', 'ciudad', 'direccion'].forEach(function (c) { error(c, ''); });
        $('sed-modal-titulo').textContent = editando ? 'Editar sede ' + editando.nombre : 'Nueva sede';
        $('sed-guardar').textContent = editando ? 'Guardar cambios' : 'Crear sede';
        $('sed-nota-nueva').hidden = !!editando;
        $('sed-f-nombre').value = editando ? editando.nombre : '';
        $('sed-f-direccion').value = editando ? editando.direccion : '';
        cbDepto.setValue('', 'Selecciona un departamento', { silent: true });
        cbCiudad.reset('Elige el departamento primero');
        if (editando) {
            colombiaLista.then(function () {
                cbDepto.setValue(editando.departamento, editando.departamento, { silent: true });
                cbCiudad.setOptions(ciudadesDe(editando.departamento));
                cbCiudad.setValue(editando.ciudad, editando.ciudad, { silent: true });
            });
        }
        abrirModal('sed-modal', $('sed-f-nombre'));
    }
    $('sed-nueva').addEventListener('click', function () { abrirForm(null); });
    ['nombre', 'direccion'].forEach(function (c) { $('sed-f-' + c).addEventListener('input', function () { error(c, ''); }); });
    cbCiudad.querySelector('[data-combobox-value]').addEventListener('change', function () { error('ciudad', ''); });
    cbDepto.querySelector('[data-combobox-value]').addEventListener('change', function () { error('depto', ''); });
    $('sed-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var nombre = $('sed-f-nombre').value.trim().replace(/\s+/g, ' '), direccion = $('sed-f-direccion').value.trim().replace(/\s+/g, ' ');
        var depto = cbDepto.value(), ciudad = cbCiudad.value(), ok = true;
        var repetida = D.sedes.some(function (s) { return normal(s.nombre) === normal(nombre) && (!editando || s.id !== editando.id); });
        error('nombre', !nombre ? 'Escribe el nombre de la sede.' : nombre.length < 3 ? 'El nombre es muy corto.' : repetida ? 'Ya existe una sede con ese nombre.' : '');
        error('depto', depto ? '' : 'Elige el departamento.');
        error('ciudad', ciudad ? '' : 'Elige la ciudad o municipio.');
        error('direccion', !direccion ? 'Escribe la dirección.' : direccion.length < 5 ? 'La dirección está incompleta.' : '');
        ok = !document.querySelector('#sed-form .sed-field-error:not([hidden])');
        if (!ok) { var f = document.querySelector('#sed-form .is-invalid'); if (f) (f.querySelector('button') || f).focus(); return; }

        var btn = $('sed-guardar'); btn.disabled = true;
        var cuerpo = { nombre: nombre, departamento: depto, ciudad: ciudad, direccion: direccion };
        enviar(editando ? 'PUT' : 'POST', editando ? '/general/sedes/' + editando.id : '/general/sedes', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (err) {
                btn.disabled = false;
                var CAMPO = { nombre: 'nombre', departamento: 'depto', ciudad: 'ciudad', direccion: 'direccion' }, marcado = false;
                Object.keys(err.errores || {}).forEach(function (k) { if (CAMPO[k]) { error(CAMPO[k], err.errores[k][0]); marcado = true; } });
                if (!marcado) toast(err.message);
            });
    });

    pintarKpis();
    filtrar();
    var aviso = avisoPendiente();
    if (aviso) toast(aviso);
    if (/[?&]nueva=1/.test(location.search)) abrirForm(null);
}());
