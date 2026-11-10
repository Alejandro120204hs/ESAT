/* =========================================================
   ESAT — Panel Admin (ESAT general): Auditoría
   Consulta de lo que hicieron las secretarías y el Admin: filtros (periodo, sede, persona, acción), detalle y
   exportar a Excel. El texto de cada evento se pinta escapado (nunca como HTML).
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 15;
    var ICON = {
        lista: '<svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        persona: '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/></svg>',
        anular: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>',
        sede: '<svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>'
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

    var toastEl = $('aud-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="aud-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="aud-pg-gap">…</span>'
                    : '<button type="button" class="aud-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="aud-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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

    /* ── Datos ──────────────────────────────────── */
    var DIA = 86400000;
    var ROL = { admin: 'Admin', secretaria: 'Secretaría', docente: 'Docente', estudiante: 'Estudiante', acudiente: 'Acudiente' };
    function rol(a) { return ROL[a.rol] || a.rol; }
    var TIPO = { crear: 'Creó', editar: 'Editó', anular: 'Anuló', activar: 'Activó', desactivar: 'Desactivó', eliminar: 'Eliminó' };
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function fechaHora(f) { var d = new Date(f); return fechaLarga(f) + ', ' + d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' }); }
    var MES_C = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
    function fechaMini(f) { var d = new Date(f); return d.getDate() + ' ' + MES_C[d.getMonth()] + ' ' + d.getFullYear(); }

    /* ── Filtros ────────────────────────────────── */
    var cbPeriodo = $('aud-cb-periodo'), cbSede = $('aud-cb-sede'), cbPersona = $('aud-cb-persona'), cbTipo = $('aud-cb-tipo');
    [cbPeriodo, cbSede, cbPersona, cbTipo].forEach(initCombobox);
    cbPeriodo.setOptions([{ value: '1', label: 'Hoy' }, { value: '7', label: 'Últimos 7 días' }, { value: '30', label: 'Últimos 30 días' }, { value: '90', label: 'Últimos 90 días' }, { value: '', label: 'Todo' }]);
    cbSede.setOptions([{ value: '', label: 'Todas las sedes' }, { value: 'general', label: 'Sin sede (todo ESAT)' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: s.nombre }; })));
    var personas = {}; D.auditoria.forEach(function (a) { personas[a.quien] = a.rol; });
    cbPersona.setOptions([{ value: '', label: 'Todas las personas' }].concat(Object.keys(personas).sort().map(function (p) { return { value: p, label: p + ' (' + (ROL[personas[p]] || personas[p]) + ')' }; })));
    cbTipo.setOptions([{ value: '', label: 'Todas las acciones' }].concat(Object.keys(TIPO).map(function (t) { return { value: t, label: TIPO[t] + ' (' + t + ')' }; })));
    cbPeriodo.setValue('30', 'Últimos 30 días', { silent: true }); cbSede.setValue('', 'Todas las sedes', { silent: true });
    cbPersona.setValue('', 'Todas las personas', { silent: true }); cbTipo.setValue('', 'Todas las acciones', { silent: true });

    var pagina = 1, filtrados = [];
    function enPeriodo(a) {
        var p = cbPeriodo.value(); if (!p) return true;
        if (p === '1') return new Date(a.fecha).toDateString() === new Date().toDateString();
        return Date.now() - new Date(a.fecha) <= +p * DIA;
    }
    function filtrar() {
        var q = normal($('aud-buscar').value.trim()), sede = cbSede.value(), persona = cbPersona.value(), tipo = cbTipo.value();
        var base = D.auditoria.filter(enPeriodo);
        filtrados = base.filter(function (a) {
            if (q && normal(a.texto + ' ' + a.quien).indexOf(q) === -1) return false;
            if (sede === 'general' && a.sede_id != null) return false;
            if (sede && sede !== 'general' && String(a.sede_id) !== sede) return false;
            if (persona && a.quien !== persona) return false;
            if (tipo && a.tipo !== tipo) return false;
            return true;
        });
        pintarKpis(filtrados);
        pagina = 1; pintarTabla();
    }
    function pintarKpis(l) {
        var gente = {}; l.forEach(function (a) { gente[a.quien] = 1; });
        var k = [['blue', 'lista', num(l.length), l.length === 1 ? 'Evento' : 'Eventos'], ['green', 'persona', num(Object.keys(gente).length), 'Personas con actividad'],
            ['red', 'anular', num(l.filter(function (a) { return a.tipo === 'anular' || a.tipo === 'eliminar' || a.tipo === 'desactivar'; }).length), 'Anulaciones y bajas'],
            ['orange', 'sede', num(l.filter(function (a) { return a.rol === 'admin'; }).length), 'Hechas por el Admin']];
        $('aud-kpis').innerHTML = k.map(function (x) {
            return '<div class="aud-kpi aud-kpi-' + x[0] + '"><div class="aud-kpi-icon">' + ICON[x[1]] + '</div><div><div class="aud-kpi-val">' + esc(x[2]) + '</div><div class="aud-kpi-lbl">' + esc(x[3]) + '</div></div></div>';
        }).join('');
    }
    function pintarTabla() {
        var desde = (pagina - 1) * POR_PAGINA;
        $('aud-count').textContent = num(filtrados.length) + (filtrados.length === 1 ? ' evento' : ' eventos');
        $('aud-empty').hidden = filtrados.length > 0;
        $('aud-body').innerHTML = filtrados.slice(desde, desde + POR_PAGINA).map(function (a) {
            var s = sedePorId(a.sede_id), d = new Date(a.fecha);
            return '<tr class="aud-row" tabindex="0" data-id="' + a.id + '">' +
                '<td class="aud-fecha"><div class="aud-strong">' + esc(fechaMini(a.fecha)) + '</div><div class="aud-muted">' + esc(d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' })) + '</div></td>' +
                '<td><div class="aud-strong">' + esc(a.quien) + '</div><div class="aud-muted' + (a.rol === 'admin' ? ' aud-rol-admin' : '') + '">' + esc(rol(a)) + '</div></td>' +
                '<td>' + esc(s ? s.nombre : 'Todo ESAT') + '</td>' +
                '<td><span class="aud-tipo aud-tipo-' + esc(a.tipo) + '">' + esc(TIPO[a.tipo] || a.tipo) + '</span><div class="aud-muted">' + esc(a.entidad) + '</div></td>' +
                '<td><div class="aud-texto" title="' + esc(a.texto) + '">' + esc(a.texto) + '</div></td></tr>';
        }).join('');
        paginar($('aud-paginacion'), filtrados.length, pagina, function (p) { pagina = p; pintarTabla(); $('aud-buscar').scrollIntoView({ block: 'start', behavior: 'smooth' }); });
    }
    $('aud-buscar').addEventListener('input', filtrar);
    [cbPeriodo, cbSede, cbPersona, cbTipo].forEach(function (cb) { cb.querySelector('[data-combobox-value]').addEventListener('change', filtrar); });

    /* ── Detalle ────────────────────────────────── */
    var modal = $('aud-modal'), volver = null, actual = null;
    function abrir(id) {
        actual = D.auditoria.find(function (a) { return a.id === id; }); if (!actual) return;
        var a = actual, s = sedePorId(a.sede_id);
        volver = document.activeElement;
        $('aud-md-titulo').textContent = (TIPO[a.tipo] || a.tipo) + ' · ' + a.entidad;
        $('aud-md-sub').textContent = fechaHora(a.fecha);
        $('aud-md-texto').textContent = a.texto;
        $('aud-md-info').innerHTML = [['Quién', a.quien], ['Rol', a.rol === 'admin' ? 'Admin (ESAT general)' : rol(a)], ['Sede', s ? s.nombre : 'Todo ESAT'], ['Sobre', a.entidad]]
            .map(function (x) { return '<div><span class="aud-info-lbl">' + esc(x[0]) + '</span><span class="aud-info-val">' + esc(x[1]) + '</span></div>'; }).join('');
        modal.hidden = false; modal.querySelector('.aud-close').focus();
    }
    function cerrar() { if (modal.hidden) return; modal.hidden = true; if (volver && document.contains(volver)) volver.focus(); }
    modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrar); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !document.querySelector('.app-combobox.is-open')) cerrar(); });
    $('aud-md-persona').addEventListener('click', function () {
        cbPersona.setValue(actual.quien, actual.quien + (actual.rol === 'admin' ? ' (Admin)' : ''));
        cerrar(); $('aud-buscar').focus();
    });
    $('aud-body').addEventListener('click', function (e) { var tr = e.target.closest('tr[data-id]'); if (tr) abrir(+tr.dataset.id); });
    $('aud-body').addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-id]')) { e.preventDefault(); abrir(+e.target.dataset.id); }
    });

    $('aud-exportar').addEventListener('click', function () {
        descargarCsv('auditoria-esat', [['Fecha', 'Hora', 'Quién', 'Rol', 'Sede', 'Acción', 'Sobre', 'Detalle']].concat(filtrados.map(function (a) {
            var d = new Date(a.fecha), s = sedePorId(a.sede_id);
            return [fechaMini(a.fecha), d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' }), a.quien, rol(a), s ? s.nombre : 'Todo ESAT', TIPO[a.tipo] || a.tipo, a.entidad, a.texto];
        })));
    });

    filtrar();
}());
