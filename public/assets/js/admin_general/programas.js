/* =========================================================
   ESAT — Panel Admin (ESAT general): Programas
   Catálogo de todo ESAT: programas (crear/editar en 3 pasos con malla, precio único para todas las sedes,
   activar/desactivar) y escuelas; en el detalle, a qué sedes se ofrece (la secretaría también lo elige en su sede).
   Editar no puede invalidar grupos vigentes (cupo, jornadas). Guardar usa GEN_API. Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS, API = window.GEN_API;
    if (!D) return;

    var POR_PAGINA = 10;
    var ICON = {
        libro: '<svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        escuela: '<svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/></svg>',
        persona: '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/></svg>',
        editar: '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        arriba: '<svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>',
        abajo: '<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>',
        quitar: '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
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

    var toastEl = $('prg-toast'), toastT;
    function toast(msg) {
        clearTimeout(toastT);
        toastEl.textContent = msg; toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        toastT = setTimeout(function () { toastEl.classList.remove('is-visible'); setTimeout(function () { toastEl.hidden = true; }, 220); }, 3800);
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
        nav.innerHTML = '<button type="button" class="prg-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="prg-pg-gap">…</span>'
                    : '<button type="button" class="prg-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="prg-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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
    function digitos(t) { return String(t || '').replace(/\D/g, ''); }

    /* ── Datos ──────────────────────────────────── */
    var ESTADO = { activo: ['Activo', 'active'], en_aprobacion: ['En aprobación', 'warn'], inactivo: ['Inactivo', 'muted'] };
    var JORNADAS = ['Mañana', 'Tarde', 'Noche', 'Fines de semana'];
    function escuelas() { return D.escuelas.filter(function (e) { return !e.eliminado; }); }
    function escuela(id) { return D.escuelas.find(function (e) { return e.id === +id; }); }
    function programa(id) { return D.programas.find(function (p) { return p.id === +id; }); }
    function sedesQueOfrecen(p) { return D.sedes.filter(function (s) { return (s.programas || []).indexOf(p.id) >= 0; }); }
    function vigentes(p, sedeId) { return D.grupos.filter(function (g) { return g.programa_id === p.id && g.estado !== 'finalizado' && (!sedeId || g.sede_id === sedeId); }); }
    function activosDe(p, sedeId) { return D.estudiantes.filter(function (e) { return e.programa_id === p.id && e.estado === 'activo' && (!sedeId || e.sede_id === sedeId); }).length; }
    function horas(p) { return (p.modulos || []).reduce(function (t, m) { return t + m.horas; }, 0); }
    function mensual(p) { return Math.round(p.precio_total / p.duracion_meses); }
    function tag(e) { return '<span class="app-status-tag app-status-' + ESTADO[e][1] + '">' + ESTADO[e][0] + '</span>'; }

    /* ── Pestañas ───────────────────────────────── */
    var ptab = 'programas';
    document.querySelectorAll('.prg-ptab').forEach(function (b) {
        b.addEventListener('click', function () {
            ptab = b.dataset.ptab;
            document.querySelectorAll('.prg-ptab').forEach(function (x) { var on = x === b; x.classList.toggle('is-active', on); x.setAttribute('aria-selected', on); });
            document.querySelectorAll('.prg-ppanel').forEach(function (p) { p.hidden = p.dataset.ppanel !== ptab; });
            $('prg-nuevo-txt').textContent = ptab === 'escuelas' ? 'Nueva escuela' : 'Nuevo programa';
        });
    });
    $('prg-nuevo').addEventListener('click', function () { if (ptab === 'escuelas') abrirEscuela(null); else abrirForm(null); });

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var act = D.programas.filter(function (p) { return p.estado === 'activo'; }).length, apr = D.programas.filter(function (p) { return p.estado === 'en_aprobacion'; }).length;
        var est = D.estudiantes.filter(function (e) { return e.estado === 'activo'; }).length;
        var k = [['green', 'libro', num(act), 'Programas activos'], ['orange', 'reloj', num(apr), 'En aprobación'], ['blue', 'escuela', num(escuelas().length), 'Escuelas'], ['blue', 'persona', num(est), 'Estudiantes activos']];
        $('prg-kpis').innerHTML = k.map(function (x) { return '<div class="prg-kpi prg-kpi-' + x[0] + '"><div class="prg-kpi-icon">' + ICON[x[1]] + '</div><div><div class="prg-kpi-val">' + esc(x[2]) + '</div><div class="prg-kpi-lbl">' + esc(x[3]) + '</div></div></div>'; }).join('');
    }

    /* ── Tabla de programas ─────────────────────── */
    var cbEsc = $('prg-cb-escuela'), cbSede = $('prg-cb-sede'), cbEst = $('prg-cb-estado');
    [cbEsc, cbSede, cbEst].forEach(initCombobox);
    function opcionesEscuela() {
        cbEsc.setOptions([{ value: '', label: 'Todas las escuelas' }].concat(escuelas().map(function (e) { return { value: String(e.id), label: e.nombre }; })));
        $('prg-f-escuela').innerHTML = '<option value="">Selecciona</option>' + escuelas().map(function (e) { return '<option value="' + e.id + '">' + esc(e.nombre) + '</option>'; }).join('');
    }
    opcionesEscuela();
    cbSede.setOptions([{ value: '', label: 'Ofrecido en cualquier sede' }, { value: 'ninguna', label: 'Sin ninguna sede' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: 'Ofrecido en ' + s.nombre }; })));
    cbEst.setOptions([{ value: '', label: 'Todos los estados' }, { value: 'activo', label: 'Activos' }, { value: 'en_aprobacion', label: 'En aprobación' }, { value: 'inactivo', label: 'Inactivos' }]);
    cbEsc.setValue('', 'Todas las escuelas', { silent: true }); cbSede.setValue('', 'Ofrecido en cualquier sede', { silent: true }); cbEst.setValue('', 'Todos los estados', { silent: true });
    function pintarTabla() {
        var q = normal($('prg-buscar').value.trim()), e = cbEsc.value(), s = cbSede.value(), st = cbEst.value();
        var l = D.programas.filter(function (p) {
            if (q && normal(p.nombre + ' ' + p.codigo).indexOf(q) === -1) return false;
            if (e && String(p.escuela_id) !== e) return false;
            if (st && p.estado !== st) return false;
            var of = sedesQueOfrecen(p);
            if (s === 'ninguna' && of.length) return false;
            if (s && s !== 'ninguna' && !of.some(function (x) { return String(x.id) === s; })) return false;
            return true;
        }).sort(function (a, b) { return (a.estado === 'inactivo') - (b.estado === 'inactivo') || a.nombre.localeCompare(b.nombre, 'es'); });
        $('prg-count').textContent = l.length + (l.length === 1 ? ' programa' : ' programas');
        $('prg-empty').hidden = l.length > 0;
        $('prg-body').innerHTML = l.map(function (p) {
            var of = sedesQueOfrecen(p), es = escuela(p.escuela_id);
            return '<tr class="prg-row" tabindex="0" data-id="' + p.id + '">' +
                '<td><div class="prg-strong">' + esc(p.nombre.replace('Técnico Laboral en ', '')) + '</div><div class="prg-muted">' + esc(p.codigo + ' · ' + p.modalidad) + '</div></td>' +
                '<td>' + esc(es ? es.nombre : '—') + '</td>' +
                '<td>' + p.duracion_meses + ' meses<div class="prg-muted">' + num(horas(p)) + ' horas · ' + p.modulos.length + ' módulos</div></td>' +
                '<td class="prg-num"><span class="prg-strong">' + esc(plata(p.precio_total)) + '</span><div class="prg-muted">Matrícula ' + esc(plata(p.matricula)) + '</div></td>' +
                '<td>' + (of.length ? '<div class="prg-tags">' + of.map(function (x) { return '<span class="prg-tag">' + esc(x.nombre) + '</span>'; }).join('') + '</div>' : '<span class="prg-muted">Ninguna todavía</span>') + '</td>' +
                '<td class="prg-num">' + num(activosDe(p)) + '</td><td>' + tag(p.estado) + '</td></tr>';
        }).join('');
    }
    $('prg-buscar').addEventListener('input', pintarTabla);
    [cbEsc, cbSede, cbEst].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', pintarTabla); });
    $('prg-body').addEventListener('click', function (e) { var tr = e.target.closest('tr[data-id]'); if (tr) abrirDrawer(+tr.dataset.id); });
    $('prg-body').addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrirDrawer(+e.target.dataset.id); } });

    /* ── Drawer ─────────────────────────────────── */
    var drawer = $('prg-drawer'), actual = null, volverDrawer = null;
    function tab(n) {
        drawer.querySelectorAll('.prg-tab').forEach(function (b) { var on = b.dataset.tab === n; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        drawer.querySelectorAll('.prg-panel').forEach(function (p) { p.hidden = p.dataset.panel !== n; });
    }
    drawer.querySelectorAll('.prg-tab').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });
    function info(filas) { return '<div class="prg-info">' + filas.map(function (x) { return '<div' + (x[2] ? ' class="prg-info-full"' : '') + '><span class="prg-info-lbl">' + esc(x[0]) + '</span><span class="prg-info-val">' + esc(x[1]) + '</span></div>'; }).join('') + '</div>'; }
    function abrirDrawer(id, pestana) {
        var p = programa(id); if (!p) return;
        if (!drawer.classList.contains('is-open')) volverDrawer = document.activeElement;
        actual = p;
        var es = escuela(p.escuela_id), cuota = D.configuracion.cuota_sistema;
        $('prg-dw-nombre').textContent = p.nombre;
        $('prg-dw-sub').innerHTML = esc(p.codigo + ' · ' + (es ? 'Escuela ' + es.nombre : '')) + ' ' + tag(p.estado);
        $('prg-dw-datos').innerHTML = info([['Nivel', p.nivel], ['Modalidad', p.modalidad], ['Resolución', p.resolucion, true], ['Jornadas', p.jornadas.join(', '), true],
            ['Cupo por grupo', p.cupo + ' estudiantes'], ['Duración', p.duracion_meses + ' meses · ' + p.duracion_periodos + ' ' + p.tipo_periodo + 's'], ['Horas', num(horas(p)) + ' horas en ' + p.modulos.length + ' módulos']]) +
            '<h3 class="prg-seccion">Precio (igual en todas las sedes)</h3>' + info([['Precio total', plata(p.precio_total)], ['Derecho de matrícula', plata(p.matricula)],
            ['Valor del programa por mes', plata(mensual(p))], ['Cuota del sistema por mes', plata(cuota)], ['El estudiante paga por mes', plata(mensual(p) + cuota), true]]);
        var max = Math.max.apply(null, p.modulos.map(function (m) { return m.horas; }));
        $('prg-dw-malla').innerHTML = '<ol class="prg-malla">' + p.modulos.map(function (m, i) {
            return '<li><span class="prg-malla-n">' + (i + 1) + '</span><span class="prg-malla-nombre">' + esc(m.nombre) + '</span><span class="prg-bar"><span style="width:' + Math.round(m.horas / max * 100) + '%"></span></span><b>' + num(m.horas) + ' h</b></li>';
        }).join('') + '</ol><p class="prg-malla-total">Total: ' + num(horas(p)) + ' horas</p>';
        pintarSedesDrawer();
        tab(pestana || 'datos');
        $('prg-dw-estado').textContent = p.estado === 'inactivo' ? 'Activar programa' : 'Desactivar programa';
        drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('.prg-close').focus();
    }
    function pintarSedesDrawer() {
        var p = actual;
        $('prg-dw-sedes').innerHTML = '<p class="prg-hint">Cada secretaría elige si ofrece el programa en su sede. Tú también puedes asignarlo o quitarlo; no se quita de una sede con grupos vigentes.</p>' +
            '<ul class="prg-list">' + D.sedes.map(function (s) {
                var on = (s.programas || []).indexOf(p.id) >= 0, gv = vigentes(p, s.id).length;
                return '<li><div><div class="prg-strong">' + esc(s.nombre) + '</div><div class="prg-muted">' + (on ? gv + (gv === 1 ? ' grupo vigente · ' : ' grupos vigentes · ') + activosDe(p, s.id) + ' estudiantes' : 'No lo ofrece') + '</div></div>' +
                    '<button type="button" class="prg-switch" role="switch" aria-checked="' + on + '" data-sede="' + s.id + '"' + (p.estado === 'inactivo' && !on ? ' disabled' : '') + '><span class="prg-switch-track"></span>' + (on ? 'Lo ofrece' : 'No lo ofrece') + '</button></li>';
            }).join('') + '</ul>';
    }
    $('prg-dw-sedes').addEventListener('click', function (e) {
        var b = e.target.closest('[data-sede]'); if (!b || b.disabled) return;
        var s = D.sedes.find(function (x) { return x.id === +b.dataset.sede; }), p = actual, on = (s.programas || []).indexOf(p.id) >= 0;
        if (on && vigentes(p, s.id).length) { toast('No se puede quitar de ' + s.nombre + ': tiene ' + vigentes(p, s.id).length + ' grupo(s) vigente(s). Se quita cuando terminen.'); return; }
        var lista = on ? s.programas.filter(function (x) { return x !== p.id; }) : (s.programas || []).concat([p.id]);
        b.disabled = true;
        API.guardar('sedes', { id: s.id, programas: lista }, (on ? 'Quitó' : 'Asignó') + ' el programa ' + p.nombre + (on ? ' de' : ' a') + ' la sede ' + s.nombre + '.', 'editar', s.id).then(function () {
            pintarSedesDrawer(); pintarTabla();
            toast(on ? 'La sede ' + s.nombre + ' ya no ofrece ' + p.nombre.replace('Técnico Laboral en ', '') + '.' : 'La sede ' + s.nombre + ' ya puede abrir grupos de ' + p.nombre.replace('Técnico Laboral en ', '') + '.');
        });
    });
    function cerrarDrawer() { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); if (volverDrawer && document.contains(volverDrawer)) volverDrawer.focus(); }
    drawer.querySelectorAll('[data-cerrar-drawer]').forEach(function (b) { b.addEventListener('click', cerrarDrawer); });

    /* ── Modales ────────────────────────────────── */
    var modalAbierto = null, volverModal = null, pila = [];
    function abrirModal(id, foco) {
        if (modalAbierto) pila.push(modalAbierto); else volverModal = document.activeElement;
        modalAbierto = $(id); modalAbierto.hidden = false; (foco || modalAbierto.querySelector('input, select, button')).focus();
    }
    function cerrarModal() {
        if (!modalAbierto) return;
        modalAbierto.hidden = true; modalAbierto = pila.pop() || null;
        if (!modalAbierto && volverModal && document.contains(volverModal)) volverModal.focus();
    }
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarModal); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || document.querySelector('.app-combobox.is-open')) return;
        if (modalAbierto) cerrarModal(); else if (drawer.classList.contains('is-open')) cerrarDrawer();
    });
    var alConfirmar = null;
    function confirmar(titulo, texto, boton, accion) {
        $('prg-conf-titulo').textContent = titulo; $('prg-conf-texto').textContent = texto; $('prg-conf-ok').textContent = boton;
        alConfirmar = accion; abrirModal('prg-confirmar', $('prg-conf-ok'));
    }
    $('prg-conf-ok').addEventListener('click', function () { var b = this; b.disabled = true; alConfirmar().then(function () { b.disabled = false; cerrarModal(); }); });

    /* Activar / desactivar */
    $('prg-dw-estado').addEventListener('click', function () {
        var p = actual;
        if (p.estado !== 'inactivo') {
            var gv = vigentes(p).length;
            if (gv) { toast('No se puede desactivar: tiene ' + gv + ' grupo(s) vigente(s) en las sedes. Se desactiva cuando terminen.'); return; }
            confirmar('¿Desactivar ' + p.nombre + '?', 'Ninguna sede podrá abrir grupos nuevos de este programa. Los estudiantes que ya lo terminaron conservan su historial.', 'Desactivar', function () {
                return API.guardar('programas', { id: p.id, estado: 'inactivo' }, 'Desactivó el programa ' + p.nombre + '.', 'desactivar', null).then(function () { refrescar(p.id); toast('Se desactivó el programa.'); });
            });
        } else {
            confirmar('¿Activar ' + p.nombre + '?', 'Las sedes que lo ofrecen podrán volver a abrir grupos.', 'Activar', function () {
                return API.guardar('programas', { id: p.id, estado: 'activo' }, 'Activó el programa ' + p.nombre + '.', 'activar', null).then(function () { refrescar(p.id); toast('Se activó el programa.'); });
            });
        }
    });
    function refrescar(id) { pintarKpis(); pintarTabla(); pintarEscuelas(); if (id && drawer.classList.contains('is-open')) abrirDrawer(id, drawer.querySelector('.prg-tab.is-active').dataset.tab); }

    /* ── Formulario de programa (3 pasos) ───────── */
    var paso = 1, editando = null, malla = [];
    var TITULOS = { 1: 'Datos del programa', 2: 'Duración y precio', 3: 'Malla de módulos' };
    function f(id) { return $('prg-f-' + id); }
    function err(campo, msg) {
        var e = document.querySelector('#prg-form [data-error="' + campo + '"]'); if (!e) return;
        e.textContent = msg || ''; e.hidden = !msg;
        var el = f(campo); if (el && el.classList) el.classList.toggle('is-invalid', !!msg);
    }
    function dinero(id) { var d = digitos(f(id).value); return d === '' ? null : +d; }
    function formatoDinero(input) { input.addEventListener('input', function () { var d = digitos(this.value); this.value = d ? num(+d) : ''; resumenPrecio(); }); }
    formatoDinero(f('precio')); formatoDinero(f('matricula'));
    function jornadasMarcadas() { return [].slice.call(document.querySelectorAll('#prg-f-jornadas input:checked')).map(function (i) { return i.value; }); }
    function periodos() { var m = +f('meses').value || 0, t = f('periodo').value === 'semestre' ? 6 : 3; return Math.max(1, Math.ceil(m / t)); }
    function resumenPrecio() {
        var precio = dinero('precio'), meses = +f('meses').value, cuota = D.configuracion.cuota_sistema;
        $('prg-periodos').textContent = meses ? periodos() + ' ' + (f('periodo').value === 'semestre' ? 'semestres' : 'trimestres') : '';
        $('prg-resumen-precio').innerHTML = precio && meses ? 'Valor del programa por mes: <strong>' + esc(plata(Math.round(precio / meses))) + '</strong> + cuota del sistema ' + esc(plata(cuota)) +
            ' = <strong>' + esc(plata(Math.round(precio / meses) + cuota)) + '</strong> al mes para el estudiante, en todas las sedes.' : 'Escribe la duración y el precio para ver la mensualidad.';
    }
    f('meses').addEventListener('input', resumenPrecio); f('periodo').addEventListener('change', resumenPrecio);
    function pintarMalla() {
        $('prg-malla-edit').innerHTML = malla.map(function (m, i) {
            return '<li data-i="' + i + '"><span class="prg-malla-n">' + (i + 1) + '</span>' +
                '<input type="text" class="prg-mod-nombre" value="' + esc(m.nombre) + '" maxlength="100" placeholder="Nombre del módulo" aria-label="Nombre del módulo ' + (i + 1) + '">' +
                '<input type="number" class="prg-mod-horas" value="' + (m.horas || '') + '" min="8" max="2000" step="8" placeholder="Horas" aria-label="Horas del módulo ' + (i + 1) + '">' +
                '<span class="prg-mod-acc"><button type="button" class="prg-icon-btn" data-mover="-1" aria-label="Subir"' + (i === 0 ? ' disabled' : '') + '>' + ICON.arriba + '</button>' +
                '<button type="button" class="prg-icon-btn" data-mover="1" aria-label="Bajar"' + (i === malla.length - 1 ? ' disabled' : '') + '>' + ICON.abajo + '</button>' +
                '<button type="button" class="prg-icon-btn" data-quitar aria-label="Quitar módulo"' + (malla.length <= 1 ? ' disabled' : '') + '>' + ICON.quitar + '</button></span></li>';
        }).join('');
        totalMalla();
    }
    function totalMalla() { var t = malla.reduce(function (a, m) { return a + (+m.horas || 0); }, 0); $('prg-malla-total').textContent = 'Total: ' + num(t) + ' horas en ' + malla.length + (malla.length === 1 ? ' módulo' : ' módulos'); }
    $('prg-malla-edit').addEventListener('input', function (e) {
        var li = e.target.closest('li'); if (!li) return; var m = malla[+li.dataset.i];
        if (e.target.classList.contains('prg-mod-nombre')) m.nombre = e.target.value; else m.horas = +e.target.value || 0;
        err('malla', ''); totalMalla();
    });
    $('prg-malla-edit').addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b || b.disabled) return; var i = +b.closest('li').dataset.i;
        if (b.hasAttribute('data-quitar')) malla.splice(i, 1);
        else { var j = i + +b.dataset.mover, t = malla[i]; malla[i] = malla[j]; malla[j] = t; }
        pintarMalla();
    });
    $('prg-mod-agregar').addEventListener('click', function () { malla.push({ nombre: '', horas: 0 }); pintarMalla(); var ins = $('prg-malla-edit').querySelectorAll('.prg-mod-nombre'); ins[ins.length - 1].focus(); });

    function validar(p) {
        var v = function (id) { return f(id).value.trim(); };
        if (p === 1) {
            var otro = function (k, val) { return D.programas.some(function (x) { return normal(x[k]) === normal(val) && (!editando || x.id !== editando.id); }); };
            err('nombre', v('nombre').length < 10 ? 'Escribe el nombre completo del programa.' : otro('nombre', v('nombre')) ? 'Ya hay un programa con ese nombre.' : '');
            err('codigo', !/^[A-Za-z0-9-]{4,20}$/.test(v('codigo')) ? 'Escribe el código (letras, números y guiones).' : otro('codigo', v('codigo')) ? 'Ese código ya lo tiene otro programa.' : '');
            err('escuela', v('escuela') ? '' : 'Elige la escuela.');
            err('resolucion', v('resolucion').length < 5 ? 'Escribe la resolución o «En trámite».' : '');
            var jor = jornadasMarcadas(), usadas = editando ? vigentes(editando).map(function (g) { return g.jornada; }) : [];
            var falta = usadas.filter(function (j) { return jor.indexOf(j) < 0; });
            err('jornadas', !jor.length ? 'Marca al menos una jornada.' : falta.length ? 'Hay grupos vigentes en jornada ' + falta[0] + ': no la puedes quitar.' : '');
            var cupo = +v('cupo'), lleno = editando ? Math.max.apply(null, vigentes(editando).map(function (g) { return g.estudiantes; }).concat([0])) : 0;
            err('cupo', !(cupo >= 5 && cupo <= 60) ? 'Escribe un cupo entre 5 y 60.' : cupo < lleno ? 'Un grupo vigente ya tiene ' + lleno + ' estudiantes: el cupo no puede ser menor.' : '');
        }
        if (p === 2) {
            var meses = +v('meses'), precio = dinero('precio'), mat = dinero('matricula');
            err('meses', !(Number.isInteger(meses) && meses >= 3 && meses <= 36) ? 'Escribe una duración entre 3 y 36 meses.' : '');
            err('precio', !precio || precio < 100000 ? 'Escribe el precio total (mínimo $ 100.000).' : precio % 100 ? 'Usa un valor redondo (múltiplo de $ 100).' : '');
            err('matricula', mat == null ? 'Escribe el derecho de matrícula (puede ser 0).' : mat % 100 ? 'Usa un valor redondo (múltiplo de $ 100).' : precio && mat > precio ? 'No puede ser mayor que el precio total.' : '');
        }
        if (p === 3) {
            var mal = malla.findIndex(function (m) { return m.nombre.trim().length < 3 || !(m.horas >= 8); });
            err('malla', !malla.length ? 'Agrega al menos un módulo.' : mal >= 0 ? 'Revisa el módulo ' + (mal + 1) + ': necesita nombre y al menos 8 horas.' : '');
        }
        var malo = document.querySelector('#prg-form .prg-paso[data-paso="' + p + '"] .prg-field-error:not([hidden])');
        if (malo) { var c = malo.parentNode.querySelector('input, select'); if (c) c.focus(); }
        return !malo;
    }
    function irPaso(p) {
        paso = p;
        document.querySelectorAll('#prg-form .prg-paso').forEach(function (el) { el.hidden = +el.dataset.paso !== p; });
        $('prg-modal-sub').textContent = 'Paso ' + p + ' de 3 · ' + TITULOS[p];
        $('prg-atras').hidden = p === 1;
        $('prg-siguiente').textContent = p < 3 ? 'Siguiente' : editando ? 'Guardar cambios' : 'Crear programa';
        if (p === 2) resumenPrecio();
        if (p === 3) pintarMalla();
    }
    function abrirForm(id) {
        editando = id ? programa(id) : null;
        var p = editando || { nombre: '', codigo: '', escuela_id: '', modalidad: 'Presencial', estado: 'activo', resolucion: '', jornadas: [], cupo: 25, duracion_meses: 10, tipo_periodo: 'trimestre', precio_total: '', matricula: '', modulos: [{ nombre: '', horas: 0 }] };
        f('nombre').value = p.nombre; f('codigo').value = p.codigo; f('escuela').value = p.escuela_id || ''; f('modalidad').value = p.modalidad;
        f('estado').value = p.estado === 'inactivo' ? 'activo' : p.estado; f('resolucion').value = p.resolucion; f('cupo').value = p.cupo;
        document.querySelectorAll('#prg-f-jornadas input').forEach(function (i) { i.checked = p.jornadas.indexOf(i.value) >= 0; });
        f('meses').value = p.duracion_meses; f('periodo').value = p.tipo_periodo;
        f('precio').value = p.precio_total ? num(p.precio_total) : ''; f('matricula').value = p.matricula !== '' ? num(p.matricula) : '';
        malla = p.modulos.map(function (m) { return { nombre: m.nombre, horas: m.horas }; });
        document.querySelectorAll('#prg-form .prg-field-error').forEach(function (e) { e.hidden = true; });
        document.querySelectorAll('#prg-form .is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        $('prg-modal-titulo').textContent = editando ? 'Editar ' + editando.nombre.replace('Técnico Laboral en ', '') : 'Nuevo programa';
        abrirModal('prg-modal', f('nombre'));
        irPaso(1);
    }
    $('prg-dw-editar').addEventListener('click', function () { abrirForm(actual.id); });
    $('prg-atras').addEventListener('click', function () { if (paso > 1) irPaso(paso - 1); });
    document.querySelectorAll('#prg-form input, #prg-form select').forEach(function (i) {
        i.addEventListener('input', function () { var k = (i.id || '').replace('prg-f-', ''); if (k) err(k, ''); if (i.closest('#prg-f-jornadas')) err('jornadas', ''); });
    });
    $('prg-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validar(paso)) return;
        if (paso < 3) { irPaso(paso + 1); return; }
        var meses = +f('meses').value;
        var reg = { nombre: f('nombre').value.trim(), codigo: f('codigo').value.trim().toUpperCase(), escuela_id: +f('escuela').value, nivel: 'Técnico Laboral', modalidad: f('modalidad').value,
            estado: f('estado').value, resolucion: f('resolucion').value.trim(), jornadas: JORNADAS.filter(function (j) { return jornadasMarcadas().indexOf(j) >= 0; }), cupo: +f('cupo').value,
            duracion_meses: meses, tipo_periodo: f('periodo').value, duracion_periodos: periodos(), precio_total: dinero('precio'), matricula: dinero('matricula'),
            modulos: malla.map(function (m) { return { nombre: m.nombre.trim(), horas: +m.horas }; }) };
        reg.horas = reg.modulos.reduce(function (t, m) { return t + m.horas; }, 0);
        var eraNuevo = !editando, cambios = [];
        if (editando) {
            reg.id = editando.id;
            if (editando.precio_total !== reg.precio_total) cambios.push('precio de ' + plata(editando.precio_total) + ' a ' + plata(reg.precio_total));
            if (editando.matricula !== reg.matricula) cambios.push('matrícula de ' + plata(editando.matricula) + ' a ' + plata(reg.matricula));
            if (editando.duracion_meses !== reg.duracion_meses) cambios.push('duración de ' + editando.duracion_meses + ' a ' + reg.duracion_meses + ' meses (se recalcula el fin de sus grupos)');
        }
        var texto = eraNuevo ? 'Creó el programa ' + reg.nombre + ' (' + reg.codigo + ') por ' + plata(reg.precio_total) + '.' : 'Editó el programa ' + reg.nombre + (cambios.length ? ': ' + cambios.join('; ') : '') + '.';
        var btn = $('prg-siguiente'); btn.disabled = true;
        API.guardar('programas', reg, texto, eraNuevo ? 'crear' : 'editar', null).then(function (r) {
            btn.disabled = false; cerrarModal(); refrescar(drawer.classList.contains('is-open') ? reg.id : null);
            toast(eraNuevo ? 'Se creó el programa. Ahora asígnalo a las sedes o deja que cada secretaría lo active.' : 'Se guardaron los cambios del programa' + (cambios.length ? ': ' + cambios.join('; ') : '') + '.');
            if (eraNuevo) abrirDrawer(r.id, 'sedes');
        });
    });

    /* ── Escuelas ───────────────────────────────── */
    function pintarEscuelas() {
        $('prg-esc-body').innerHTML = escuelas().map(function (e) {
            var np = D.programas.filter(function (p) { return p.escuela_id === e.id; }).length, nd = D.docentes.filter(function (d) { return d.escuela_id === e.id; }).length;
            return '<tr class="prg-row" tabindex="0" data-esc="' + e.id + '"><td class="prg-strong">' + esc(e.nombre) + '</td><td>' + esc(e.sigla) + '</td><td class="prg-num">' + np + '</td><td class="prg-num">' + nd + '</td>' +
                '<td class="prg-actions"><button type="button" class="prg-icon-btn" data-esc-editar="' + e.id + '" aria-label="Editar ' + esc(e.nombre) + '">' + ICON.editar + '</button></td></tr>';
        }).join('');
        opcionesEscuela();
    }
    var escEditando = null;
    function errE(c, m) { var e = document.querySelector('#prg-esc-form [data-error="' + c + '"]'); e.textContent = m || ''; e.hidden = !m; $('prg-' + c).classList.toggle('is-invalid', !!m); }
    function abrirEscuela(id) {
        escEditando = id ? escuela(id) : null;
        $('prg-e-nombre').value = escEditando ? escEditando.nombre : ''; $('prg-e-sigla').value = escEditando ? escEditando.sigla : '';
        errE('e-nombre', ''); errE('e-sigla', '');
        $('prg-esc-titulo').textContent = escEditando ? 'Editar escuela' : 'Nueva escuela';
        $('prg-esc-guardar').textContent = escEditando ? 'Guardar cambios' : 'Crear escuela';
        $('prg-esc-eliminar').hidden = !escEditando;
        abrirModal('prg-esc-modal', $('prg-e-nombre'));
    }
    $('prg-esc-body').addEventListener('click', function (e) { var tr = e.target.closest('[data-esc]'); if (tr) abrirEscuela(+tr.dataset.esc); });
    $('prg-esc-body').addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-esc]')) { e.preventDefault(); abrirEscuela(+e.target.dataset.esc); } });
    ['e-nombre', 'e-sigla'].forEach(function (c) { $('prg-' + c).addEventListener('input', function () { errE(c, ''); }); });
    $('prg-esc-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var nombre = $('prg-e-nombre').value.trim(), sigla = $('prg-e-sigla').value.trim().toUpperCase();
        var otra = function (k, v) { return escuelas().some(function (x) { return normal(x[k]) === normal(v) && (!escEditando || x.id !== escEditando.id); }); };
        errE('e-nombre', nombre.length < 3 ? 'Escribe el nombre.' : otra('nombre', nombre) ? 'Ya hay una escuela con ese nombre.' : '');
        errE('e-sigla', !/^[A-ZÑ]{3,4}$/.test(sigla) ? 'Escribe 3 o 4 letras.' : otra('sigla', sigla) ? 'Esa sigla ya la tiene otra escuela.' : '');
        if (!document.querySelector('#prg-esc-form .prg-field-error:not([hidden])')) {
            var reg = { nombre: nombre, sigla: sigla }; if (escEditando) reg.id = escEditando.id;
            API.guardar('escuelas', reg, (escEditando ? 'Editó la escuela ' : 'Creó la escuela ') + nombre + ' (' + sigla + ').', escEditando ? 'editar' : 'crear', null).then(function () {
                cerrarModal(); pintarEscuelas(); pintarKpis(); pintarTabla(); toast(escEditando ? 'Se guardó la escuela.' : 'Se creó la escuela ' + nombre + '.');
            });
        }
    });
    $('prg-esc-eliminar').addEventListener('click', function () {
        var e = escEditando, np = D.programas.filter(function (p) { return p.escuela_id === e.id; }).length, nd = D.docentes.filter(function (d) { return d.escuela_id === e.id; }).length;
        if (np || nd) { toast('No se puede eliminar: tiene ' + np + ' programa(s) y ' + nd + ' docente(s). Pásalos a otra escuela primero.'); return; }
        confirmar('¿Eliminar la escuela ' + e.nombre + '?', 'No tiene programas ni docentes. Se borra del catálogo.', 'Eliminar', function () {
            return API.guardar('escuelas', { id: e.id, eliminado: true }, 'Eliminó la escuela ' + e.nombre + '.', 'eliminar', null).then(function () {
                $('prg-confirmar').hidden = true; pila = []; modalAbierto = $('prg-esc-modal'); cerrarModal(); pintarEscuelas(); pintarKpis(); toast('Se eliminó la escuela.');
            });
        });
    });

    pintarKpis(); pintarTabla(); pintarEscuelas();
}());
