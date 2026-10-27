/* ESAT — Admin: Comunicados (Comunicación). Datos de ComunicadoService (COM_DATOS); crear, editar, archivar
   y eliminar van a ComunicadoController (ComunicadoRequest / ArchivarComunicadoRequest). El admin publica a toda la sede o a un rol; los de los docentes (a sus grupos) aquí
   solo se ven y se archivan. Un programado cuya hora ya llegó cuenta como publicado. Leído = la persona lo abrió
   en su panel; a quien no lo ha leído se le puede escribir por WhatsApp (al acudiente si es menor). */
(function () {
    'use strict';

    var CFG = window.COM_CFG;
    var D = window.COM_DATOS;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var AHORA = CFG.ahora;
    var HOY = AHORA.slice(0, 10);
    var VISTA_KEY = 'esat.admin.comunicados.vista';
    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var MESES_L = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var CAT = { general: 'General', academico: 'Académico', pagos: 'Pagos', evento: 'Evento', urgente: 'Urgente' };
    var DESTINO = { sede: 'Toda la sede', estudiantes: 'Estudiantes', docentes: 'Docentes', acudientes: 'Acudientes', grupo: 'Un grupo' };
    var ROL = { e: 'Estudiante', d: 'Docente', a: 'Acudiente' };
    var MAX_MSG = 2000, MAX_ADJ = 5 * 1024 * 1024;

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function digitos(t) { return String(t || '').replace(/\D/g, ''); }
    function dos(n) { return (n < 10 ? '0' : '') + n; }
    function corta(f) {
        if (!f) return '—';
        var p = f.slice(0, 10).split('-');
        return +p[2] + ' ' + MESES[+p[1] - 1] + (p[0] !== HOY.slice(0, 4) ? ' ' + p[0] : '');
    }
    function hora(f) {
        var h = +f.slice(11, 13), m = f.slice(14, 16);
        return (h % 12 || 12) + ':' + m + (h < 12 ? ' a. m.' : ' p. m.');
    }
    function cuando(f) { return f ? corta(f) + ', ' + hora(f) : '—'; }
    function larga(f) { var p = f.slice(0, 10).split('-'); return +p[2] + ' de ' + MESES_L[+p[1] - 1] + ' de ' + p[0]; }
    function iniciales(n) { var p = String(n || '').split(' '); return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase(); }
    function primerNombre(n) { return String(n || '').split(' ')[0]; }
    function peso(b) { return b >= 1048576 ? (Math.round(b / 104857.6) / 10).toString().replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function catTag(c) { return '<span class="com-cat c-' + c + '">' + esc(CAT[c]) + '</span>'; }
    function tag(clase, txt) { return '<span class="app-status-tag ' + clase + '">' + esc(txt) + '</span>'; }
    var ICON = {
        pin: '<svg viewBox="0 0 24 24"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1z"/></svg>',
        clip: '<svg viewBox="0 0 24 24"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
        ojo: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        megafono: '<svg viewBox="0 0 24 24"><path d="M3 11l18-8v18l-18-8z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
        editar: '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        copiar: '<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        archivar: '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
        borrar: '<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        wa: '<svg viewBox="0 0 24 24"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>',
        pdf: '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        imagen: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'
    };

    var toastEl = document.getElementById('com-toast');
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        clearTimeout(toast.t);
        toast.t = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 5000);
    }

    /* ── Servidor (cuerpo FormData: puede llevar el adjunto) ── */
    function enviarServidor(metodo, url, campos) {
        var fd = new FormData();
        Object.keys(campos || {}).forEach(function (k) {
            var v = campos[k];
            if (v === null || v === undefined) return;
            fd.append(k, v === true ? '1' : (v === false ? '0' : v));
        });
        if (metodo !== 'POST') fd.append('_method', metodo);
        return fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: fd })
            .catch(function () { throw new Error('No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.'); })
            .then(function (r) {
                // Sesión cerrada o vencida: al recargar, el servidor lleva al login con el motivo
                if (r.status === 401 || r.status === 419) { location.reload(); return new Promise(function () {}); }
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (!r.ok) {
                        var msg = r.status === 422 ? (j.message || 'Revisa los datos.')
                            : r.status === 413 ? 'El adjunto es demasiado pesado: el máximo es 5 MB.'
                            : r.status === 403 ? 'No tienes permiso para hacer esto (el comunicado es de otra sede o tu usuario cambió de sede). Recarga la página.'
                            : r.status === 404 ? 'Ese comunicado ya no existe. Recarga la página.'
                            : 'No se pudo guardar por un error del servidor. Intenta de nuevo.';
                        var err = new Error(msg);
                        err.errores = r.status === 422 ? (j.errors || null) : null;
                        throw err;
                    }
                    return j;
                });
            });
    }
    function guardarLocal(c) {
        var i = D.comunicados.findIndex(function (k) { return k.id === c.id; });
        if (i === -1) D.comunicados.push(c); else D.comunicados[i] = c;
        return c;
    }
    var API = {
        guardar: function (x) {
            return enviarServidor(x.id ? 'PUT' : 'POST', CFG.url + (x.id ? '/' + x.id : ''), {
                accion: x.accion, destino: x.destino, titulo: x.titulo, categoria: x.categoria, mensaje: x.mensaje,
                publicar_en: x.publicar_en, visible_hasta: x.visible_hasta, fijado: x.fijado, adjunto: x.archivo, quitar_adjunto: x.quitar
            }).then(function (j) { guardarLocal(j.comunicado); return j; });
        },
        archivar: function (id, motivo) {
            return enviarServidor('PATCH', CFG.url + '/' + id + '/archivar', { motivo: motivo })
                .then(function (j) { guardarLocal(j.comunicado); return j; });
        },
        eliminar: function (id) {
            return enviarServidor('DELETE', CFG.url + '/' + id, {}).then(function (j) {
                D.comunicados = D.comunicados.filter(function (k) { return k.id !== id; });
                return j;
            });
        }
    };

    /* ── Personas y reglas ───────────────────────────── */
    var P = { e: {}, d: {}, a: {} };
    D.personas.estudiantes.forEach(function (p) { P.e[p.id] = p; });
    D.personas.docentes.forEach(function (p) { P.d[p.id] = p; });
    D.personas.acudientes.forEach(function (p) { P.a[p.id] = p; });
    var grupoPorId = {};
    D.grupos.forEach(function (g) { grupoPorId[g.id] = g; });

    function claves(destino, grupoId) {
        var out = [];
        if (destino === 'sede' || destino === 'estudiantes') D.personas.estudiantes.forEach(function (p) { out.push('e' + p.id); });
        if (destino === 'sede' || destino === 'docentes') D.personas.docentes.forEach(function (p) { out.push('d' + p.id); });
        if (destino === 'sede' || destino === 'acudientes') D.personas.acudientes.forEach(function (p) { out.push('a' + p.id); });
        if (destino === 'grupo') D.personas.estudiantes.forEach(function (p) { if (p.grupo_id === grupoId) out.push('e' + p.id); });
        return out;
    }
    function persona(k) { return P[k.charAt(0)][+k.slice(1)]; }
    /* Un programado cuya hora ya llegó se ve como publicado */
    function estado(c) { return c.estado === 'programado' && c.publicado_en <= AHORA ? 'publicado' : c.estado; }
    function vencido(c) { return estado(c) === 'publicado' && c.visible_hasta && c.visible_hasta < HOY; }
    function paraTxt(c) {
        if (c.destino !== 'grupo') return DESTINO[c.destino];
        var g = grupoPorId[c.grupo_id];
        return g ? g.programa + ' · ' + g.nombre : 'Un grupo';
    }
    function lectura(c) {
        var todos = claves(c.destino, c.grupo_id), leidos = todos.filter(function (k) { return c.lecturas[k]; });
        return { total: todos.length, leidos: leidos.length, pct: todos.length ? Math.round(leidos.length * 100 / todos.length) : 0 };
    }
    function fechaDe(c) {
        var e = estado(c);
        return e === 'borrador' ? c.creado : (e === 'archivado' ? c.archivado.fecha : c.publicado_en);
    }

    /* ── Combobox de filtros (mismo patrón que Pagos) ── */
    function initCombobox(root) {
        var trigger = root.querySelector('[data-combobox-trigger]');
        var label   = root.querySelector('[data-combobox-label]');
        var panel   = root.querySelector('[data-combobox-panel]');
        var list    = root.querySelector('[data-combobox-list]');
        var hidden  = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;
        function abrir() { panel.hidden = false; root.classList.add('is-open'); trigger.setAttribute('aria-expanded', 'true'); }
        function cerrar() { if (panel.hidden) return; panel.hidden = true; root.classList.remove('is-open'); trigger.setAttribute('aria-expanded', 'false'); }
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });
        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (o) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = o.value;
                li.innerHTML = '<span>' + esc(o.label) + '</span><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>';
                li.addEventListener('click', function () { root.setValue(o.value, o.label); cerrar(); });
                list.appendChild(li);
            });
        };
        root.setValue = function (valor, etiqueta, opts) {
            hidden.value = valor || '';
            label.textContent = etiqueta || placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.toggle('is-selected', li.dataset.value === String(valor)); });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
    }
    function filtroCombo(id, opciones, alCambiar) {
        var root = document.getElementById(id);
        initCombobox(root);
        root.setOptions(opciones);
        root.setValue(opciones[0].value, opciones[0].label, { silent: true });
        root.querySelector('[data-combobox-value]').addEventListener('change', alCambiar);
        return root;
    }
    function valor(id) { return document.getElementById(id).value; }

    /* ═══════════ Lista por estado ═══════════ */
    var vista = 'publicado';
    var tbody = document.getElementById('com-body');
    var countEl = document.getElementById('com-count');
    var emptyEl = document.getElementById('com-empty');
    var pagEl = document.getElementById('com-pagination');
    var FECHA_COL = { publicado: 'Publicado', programado: 'Se publica', borrador: 'Creado', archivado: 'Archivado' };
    var VACIO = {
        publicado: 'No hay comunicados publicados que coincidan con la búsqueda.',
        programado: 'No hay comunicados programados. Al crear uno puedes elegir la fecha y hora en que se publica.',
        borrador: 'No hay borradores.',
        archivado: 'No hay comunicados archivados.'
    };
    var PAGE = 10, pagina = 1, items = [];

    function datos() {
        var q = (document.getElementById('com-search').value || '').toLowerCase().trim();
        var cat = valor('com-categoria'), des = valor('com-destino'), aut = valor('com-autor');
        var lista = D.comunicados.filter(function (c) {
            return estado(c) === vista && (!cat || c.categoria === cat) && (!des || c.destino === des) && (!aut || c.autor.tipo === aut) &&
                (!q || [c.titulo, c.mensaje, c.autor.nombre, paraTxt(c)].join(' ').toLowerCase().indexOf(q) !== -1);
        });
        return lista.sort(function (a, b) {
            if (vista === 'programado') return a.publicado_en.localeCompare(b.publicado_en);
            if (vista === 'publicado') {
                var fa = a.fijado && !vencido(a) ? 0 : 1, fb = b.fijado && !vencido(b) ? 0 : 1;
                if (fa !== fb) return fa - fb;
            }
            return fechaDe(b).localeCompare(fechaDe(a)) || b.id - a.id;
        });
    }
    function fila(c) {
        var e = estado(c), l = lectura(c);
        var marcas = (c.fijado && e === 'publicado' && !vencido(c) ? ICON.pin.replace('<svg ', '<svg class="is-fijado" role="img" aria-label="Fijado arriba" ') : '') +
            (c.adjunto ? ICON.clip.replace('<svg ', '<svg role="img" aria-label="Tiene adjunto" ') : '');
        var lect = e === 'publicado' || e === 'archivado'
            ? '<div class="com-meter-cell"><span class="com-meter"><span style="width:' + l.pct + '%"></span></span><span><span class="com-meter-pct">' + l.pct + ' %</span><div class="com-meter-de">' + l.leidos + ' de ' + l.total + '</div></span></div>'
            : '<span class="com-meter-de">' + l.total + (l.total === 1 ? ' persona' : ' personas') + '</span>';
        var fecha = e === 'borrador' ? corta(c.creado) : cuando(fechaDe(c));
        return '<tr class="com-row" data-id="' + c.id + '">' +
            '<td><div class="com-cell"><div class="com-cell-titulo">' + marcas + '<span>' + esc(c.titulo) + '</span></div><div class="com-cell-msg">' + esc(c.mensaje || 'Sin mensaje todavía') + '</div></div></td>' +
            '<td>' + catTag(c.categoria) + '</td>' +
            '<td><div class="com-para"><div class="com-person-name com-nowrap">' + esc(c.destino === 'grupo' ? 'Su grupo' : DESTINO[c.destino]) + '</div>' + (c.destino === 'grupo' ? '<div class="com-person-sub" title="' + esc(paraTxt(c)) + '">' + esc(paraTxt(c)) + '</div>' : '') + '</div></td>' +
            '<td><div class="com-person-name com-nowrap">' + esc(c.autor.tipo === 'admin' ? 'Administración' : c.autor.nombre) + '</div><div class="com-person-sub">' + (c.autor.tipo === 'admin' ? esc(c.autor.nombre) : 'Docente') + '</div></td>' +
            '<td class="com-nowrap">' + fecha + (vencido(c) ? '<div class="com-person-sub">Ya no se ve desde el ' + corta(c.visible_hasta) + '</div>' : '') + '</td>' +
            '<td>' + lect + '</td>' +
            '<td class="com-actions-cell"><button type="button" class="com-icon-btn" title="Ver comunicado" aria-label="Ver ' + esc(c.titulo) + '">' + ICON.ojo + '</button></td></tr>';
    }
    function aplicar(mantener) {
        items = datos();
        if (!mantener) pagina = 1;
        pagina = Math.min(pagina, Math.max(1, Math.ceil(items.length / PAGE)));
        pintar();
    }
    function pintar() {
        var total = items.length, paginas = Math.max(1, Math.ceil(total / PAGE)), ini = (pagina - 1) * PAGE;
        document.getElementById('com-th-fecha').textContent = FECHA_COL[vista];
        document.getElementById('com-th-lectura').textContent = vista === 'publicado' || vista === 'archivado' ? 'Leído' : 'Le llega a';
        tbody.innerHTML = items.slice(ini, ini + PAGE).map(fila).join('');
        countEl.textContent = total + (total === 1 ? ' comunicado' : ' comunicados');
        emptyEl.hidden = total > 0;
        document.getElementById('com-empty-txt').textContent = VACIO[vista];
        pagEl.hidden = paginas <= 1;
        if (paginas <= 1) { pagEl.innerHTML = ''; return; }
        var html = '<button type="button" class="com-pg-btn" data-pg="prev"' + (pagina === 1 ? ' disabled' : '') + '><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Anterior</button>';
        for (var p = 1; p <= paginas; p++) {
            html += '<button type="button" class="com-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
        }
        html += '<button type="button" class="com-pg-btn" data-pg="next"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg></button>';
        pagEl.innerHTML = html;
    }
    pagEl.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pg]');
        if (!b || b.disabled) return;
        var paginas = Math.ceil(items.length / PAGE), v = b.dataset.pg;
        pagina = v === 'prev' ? Math.max(1, pagina - 1) : (v === 'next' ? Math.min(paginas, pagina + 1) : +v);
        pintar();
        tbody.closest('.com-table-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    tbody.addEventListener('click', function (e) {
        var row = e.target.closest('.com-row');
        if (row) abrirFicha(+row.dataset.id);
    });
    filtroCombo('com-cb-categoria', [{ value: '', label: 'Todas las categorías' }].concat(Object.keys(CAT).map(function (k) { return { value: k, label: CAT[k] }; })), function () { aplicar(); });
    filtroCombo('com-cb-destino', [{ value: '', label: 'Todos los destinatarios' }, { value: 'sede', label: 'Toda la sede' }, { value: 'estudiantes', label: 'Estudiantes' },
        { value: 'docentes', label: 'Docentes' }, { value: 'acudientes', label: 'Acudientes' }, { value: 'grupo', label: 'Grupos (de los docentes)' }], function () { aplicar(); });
    filtroCombo('com-cb-autor', [{ value: '', label: 'Todos los autores' }, { value: 'admin', label: 'Administración' }, { value: 'docente', label: 'Docentes' }], function () { aplicar(); });
    document.getElementById('com-search').addEventListener('input', function () { aplicar(); });

    var vistaBtns = document.querySelectorAll('.com-vista-btn');
    function mostrarVista(v) {
        vista = v;
        vistaBtns.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        try { localStorage.setItem(VISTA_KEY, v); } catch (e) {}
        aplicar();
    }
    vistaBtns.forEach(function (b) { b.addEventListener('click', function () { mostrarVista(b.dataset.vista); }); });

    /* ═══════════ KPIs y contadores ═══════════ */
    function pintarKpis() {
        var mes = HOY.slice(0, 7), nMes = MESES_L[+mes.slice(5, 7) - 1];
        var pubs = D.comunicados.filter(function (c) { return estado(c) === 'publicado'; });
        var delMes = pubs.filter(function (c) { return c.publicado_en.slice(0, 7) === mes; });
        var fijados = pubs.filter(function (c) { return c.fijado && !vencido(c); }).length;
        document.getElementById('com-k-publicados').textContent = delMes.length;
        document.getElementById('com-k-publicados-lbl').textContent = 'Publicados en ' + nMes + (fijados ? ' · ' + fijados + (fijados === 1 ? ' fijado arriba' : ' fijados arriba') : '');
        var t = delMes.reduce(function (s, c) { var l = lectura(c); s.l += l.leidos; s.t += l.total; return s; }, { l: 0, t: 0 });
        document.getElementById('com-k-lectura').textContent = (t.t ? Math.round(t.l * 100 / t.t) : 0) + ' %';
        document.getElementById('com-k-lectura-lbl').textContent = 'Lectura de lo publicado en ' + nMes + ' · ' + t.l + ' de ' + t.t + ' entregas';
        var prog = D.comunicados.filter(function (c) { return estado(c) === 'programado'; }).sort(function (a, b) { return a.publicado_en.localeCompare(b.publicado_en); });
        document.getElementById('com-k-programados').textContent = prog.length;
        document.getElementById('com-k-programados-lbl').textContent = prog.length ? 'Programados · el próximo sale el ' + cuando(prog[0].publicado_en) : 'Programados · ninguno pendiente';
        var bor = D.comunicados.filter(function (c) { return c.estado === 'borrador'; }).length;
        document.getElementById('com-k-borradores').textContent = bor;
        document.getElementById('com-k-borradores-lbl').textContent = bor === 1 ? 'Borrador sin publicar' : 'Borradores sin publicar';
        ['publicado', 'programado', 'borrador', 'archivado'].forEach(function (e) {
            document.getElementById('com-n-' + e).textContent = D.comunicados.filter(function (c) { return estado(c) === e; }).length;
        });
    }
    function refrescar() { pintarKpis(); aplicar(true); }

    /* ═══════════ Ficha (cajón) ═══════════ */
    var drawer = document.getElementById('com-drawer');
    var dwBody = document.getElementById('com-dw-body');
    var ultimoFoco = null, fichaId = null, pestanaLectura = 'faltan';

    function abrirCajon() {
        if (!drawer.classList.contains('is-open')) ultimoFoco = document.activeElement;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { drawer.querySelector('.com-close-btn[data-cerrar]').focus({ preventScroll: true }); }, 30);
    }
    function cerrarCajon() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        fichaId = null;
        if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus({ preventScroll: true });
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarCajon); });
    function dato(lbl, val) { return '<div class="com-dato"><span class="com-dato-lbl">' + lbl + '</span><span class="com-dato-val">' + val + '</span></div>'; }
    function aviso(clase, icono, texto) { return '<div class="com-aviso' + (clase ? ' ' + clase : '') + '"><div class="com-aviso-txt">' + icono + '<p>' + texto + '</p></div></div>'; }
    function adjuntoHtml(a) {
        var dentro = (a.tipo === 'pdf' ? ICON.pdf : ICON.imagen) + '<div class="com-min0"><strong>' + esc(a.nombre) + '</strong><small>' + (a.tipo === 'pdf' ? 'PDF' : 'Imagen') + ' · ' + peso(a.peso) + (a.url ? ' · abrir' : '') + '</small></div>';
        return a.url ? '<a class="com-adjunto" href="' + esc(a.url) + '" target="_blank" rel="noopener">' + dentro + '</a>' : '<div class="com-adjunto">' + dentro + '</div>';
    }
    function preview(c) {
        return '<div class="com-preview"><div class="com-preview-top"><span class="com-preview-de">' + esc(c.autor.tipo === 'admin' ? 'ESAT · ' + (CFG.sede || 'Administración') : c.autor.nombre + ' · ' + paraTxt(c)) + '</span>' + catTag(c.categoria) + '</div>' +
            '<h4 class="com-preview-titulo">' + esc(c.titulo) + '</h4>' +
            '<p class="com-preview-msg">' + esc(c.mensaje || 'Todavía no tiene mensaje.') + '</p>' + (c.adjunto ? adjuntoHtml(c.adjunto) : '') + '</div>';
    }
    /* WhatsApp a quien no lo ha leído: a un estudiante menor se le escribe al acudiente */
    function whatsapp(k, c) {
        var p = persona(k), rol = k.charAt(0);
        var a = rol === 'e' && p.menor && p.acudiente ? p.acudiente : null;
        var tel = digitos(a ? a.celular : p.celular);
        if (tel.length !== 10) return null;
        var quien = primerNombre(a ? a.nombre : p.nombre);
        var txt = 'Hola ' + quien + ', le escribimos de ESAT. ' + (a ? 'Hay un comunicado para ' + primerNombre(p.nombre) : 'Tiene un comunicado') +
            ' en la plataforma: «' + c.titulo + '». Por favor ingrese a la plataforma para leerlo. ¡Gracias!';
        return { url: 'https://wa.me/57' + tel + '?text=' + encodeURIComponent(txt), acudiente: a };
    }
    function listaPersonas(c) {
        var todos = claves(c.destino, c.grupo_id);
        var faltan = todos.filter(function (k) { return !c.lecturas[k]; });
        var leyeron = todos.filter(function (k) { return c.lecturas[k]; }).sort(function (x, y) { return c.lecturas[y].localeCompare(c.lecturas[x]); });
        var lista = pestanaLectura === 'faltan' ? faltan : leyeron;
        var archivado = estado(c) === 'archivado';
        var html = '<div><div class="com-seccion-titulo">Quién lo ha leído</div>' +
            '<div class="com-subtabs" role="tablist" aria-label="Lectura">' +
                '<button type="button" class="com-subtab' + (pestanaLectura === 'faltan' ? ' is-active' : '') + '" data-lectura="faltan" role="tab" aria-selected="' + (pestanaLectura === 'faltan') + '">No lo han leído · ' + faltan.length + '</button>' +
                '<button type="button" class="com-subtab' + (pestanaLectura === 'leyeron' ? ' is-active' : '') + '" data-lectura="leyeron" role="tab" aria-selected="' + (pestanaLectura === 'leyeron') + '">Ya lo leyeron · ' + leyeron.length + '</button>' +
            '</div>';
        if (!lista.length) {
            return html + '<p class="com-lista-vacia">' + (pestanaLectura === 'faltan' ? 'Todos lo leyeron.' : 'Nadie lo ha leído todavía.') + '</p></div>';
        }
        return html + '<ul class="com-personas">' + lista.map(function (k) {
            var p = persona(k), r = k.charAt(0), der;
            if (pestanaLectura === 'leyeron') der = 'Lo leyó el ' + cuando(c.lecturas[k]);
            else {
                var w = archivado ? null : whatsapp(k, c);
                der = w ? '<a class="com-btn com-btn-wa com-btn-sm" href="' + esc(w.url) + '" target="_blank" rel="noopener" title="' + (w.acudiente ? 'Es menor: se le escribe a ' + esc(w.acudiente.nombre) : 'Escribirle por WhatsApp') + '">' +
                    ICON.wa + (w.acudiente ? 'Al acudiente' : 'WhatsApp') + '</a>' : (archivado ? '' : 'Sin celular');
            }
            return '<li><span class="com-persona-av r-' + r + '">' + esc(iniciales(p.nombre)) + '</span>' +
                '<div class="com-min0"><div class="com-persona-nombre">' + esc(p.nombre) + '</div><div class="com-persona-sub">' + esc(ROL[r] + ' · ' + p.detalle) + '</div></div>' +
                '<div class="com-persona-der">' + der + '</div></li>';
        }).join('') + '</ul></div>';
    }

    function abrirFicha(id, mantenerPestana) {
        var c = D.comunicados.find(function (x) { return x.id === id; });
        if (!c) return;
        if (!mantenerPestana || fichaId !== id) pestanaLectura = 'faltan';
        fichaId = id;
        var e = estado(c), l = lectura(c), esAdmin = c.autor.tipo === 'admin';
        var avatar = document.getElementById('com-dw-avatar');
        avatar.innerHTML = ICON.megafono;
        avatar.className = 'com-drawer-avatar c-' + c.categoria;
        document.getElementById('com-dw-titulo').textContent = c.titulo;
        document.getElementById('com-dw-sub').textContent = 'Para ' + (c.destino === 'grupo' ? paraTxt(c) : DESTINO[c.destino].toLowerCase()) + ' · ' + (esAdmin ? 'Administración (' + c.autor.nombre + ')' : 'Docente ' + c.autor.nombre);
        var ESTADO_TAG = { publicado: ['app-status-active', 'Publicado'], programado: ['app-status-warn', 'Programado'], borrador: ['app-status-muted', 'Borrador'], archivado: ['app-status-danger', 'Archivado'] };
        document.getElementById('com-dw-badges').innerHTML = tag(ESTADO_TAG[e][0], ESTADO_TAG[e][1]) + catTag(c.categoria) +
            (c.fijado && e !== 'archivado' ? tag('app-status-warn', 'Fijado arriba') : '') + (vencido(c) ? tag('app-status-muted', 'Ya no se ve') : '');

        var html = '<div class="com-datos">' +
            dato('Le llega a', l.total + ' <small>' + (l.total === 1 ? 'persona' : 'personas') + '</small>') +
            dato('Leído', e === 'publicado' || e === 'archivado' ? l.pct + ' % <small>· ' + l.leidos + '</small>' : '—') +
            dato(e === 'programado' ? 'Se publica' : (e === 'borrador' ? 'Creado' : 'Publicado'), e === 'borrador' ? corta(c.creado) : corta(c.publicado_en) + '<small class="com-dato-hora">' + hora(c.publicado_en) + '</small>') +
            dato('Visible hasta', c.visible_hasta ? corta(c.visible_hasta) : 'Siempre') + '</div>';
        if (e === 'archivado') html += aviso('is-rojo', ICON.archivar, 'Archivado el ' + cuando(c.archivado.fecha) + ' por ' + esc(c.archivado.por) + ': ' + esc(c.archivado.motivo) + ' Ya nadie lo ve en su panel.');
        if (e === 'programado') html += aviso('', ICON.info, 'Se publicará solo el <strong>' + larga(c.publicado_en) + ' a las ' + hora(c.publicado_en) + '</strong>. Hasta entonces nadie lo ve.');
        if (e === 'borrador') html += aviso('', ICON.info, 'Es un borrador: nadie lo ve hasta que lo publiques.');
        if (!esAdmin && e !== 'archivado') html += '<p class="com-ayuda">Lo publicó el docente para su grupo desde su panel. Desde aquí no se edita; si no es adecuado, puedes archivarlo para que nadie lo vea.</p>';

        html += '<div><div class="com-seccion-titulo">Así lo ve quien lo recibe</div>' + preview(c) + '</div>';
        if (e === 'publicado' || e === 'archivado') html += listaPersonas(c);

        html += '<div class="com-acciones-fila">' +
            (esAdmin && e !== 'archivado' ? '<button type="button" class="com-btn com-btn-ghost com-btn-sm" data-accion="editar">' + ICON.editar + 'Editar</button>' : '') +
            (esAdmin ? '<button type="button" class="com-btn com-btn-ghost com-btn-sm" data-accion="duplicar">' + ICON.copiar + 'Duplicar</button>' : '') +
            (e === 'publicado' || e === 'programado' ? '<button type="button" class="com-btn com-btn-ghost com-btn-sm" data-accion="archivar">' + ICON.archivar + 'Archivar</button>' : '') +
            (e === 'borrador' ? '<button type="button" class="com-btn com-btn-ghost com-btn-sm" data-accion="eliminar">' + ICON.borrar + 'Eliminar borrador</button>' : '') + '</div>';
        dwBody.innerHTML = html;
        abrirCajon();
    }
    dwBody.addEventListener('click', function (e) {
        var t = e.target.closest('[data-lectura]');
        if (t) {
            pestanaLectura = t.dataset.lectura;
            abrirFicha(fichaId, true);
            dwBody.querySelector('[data-lectura="' + pestanaLectura + '"]').focus();
            return;
        }
        var b = e.target.closest('[data-accion]');
        if (!b) return;
        var c = D.comunicados.find(function (x) { return x.id === fichaId; });
        if (b.dataset.accion === 'editar') abrirAsistente(c, false);
        else if (b.dataset.accion === 'duplicar') abrirAsistente(c, true);
        else if (b.dataset.accion === 'archivar') abrirArchivar(c);
        else if (b.dataset.accion === 'eliminar') abrirEliminar(c);
    });

    /* ═══════════ Modal: asistente / archivar / eliminar ═══════════ */
    var modal = document.getElementById('com-modal');
    var mdBody = document.getElementById('com-md-body');
    var mdError = document.getElementById('com-md-error');
    var mdOk = document.getElementById('com-md-ok');
    var mdAtras = document.getElementById('com-md-atras');
    var mdBorrador = document.getElementById('com-md-borrador');
    var mdSteps = document.getElementById('com-md-steps');
    var md = null, mdFoco = null;

    function abrirModal() {
        if (!modal.classList.contains('is-open')) mdFoco = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        mdError.hidden = true;
        ocupado(false);
    }
    function cerrarModal() {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        md = null;
        if (mdFoco && mdFoco.focus) mdFoco.focus({ preventScroll: true });
    }
    modal.querySelectorAll('[data-modal-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarModal); });
    function error(msg, campo) {
        mdError.textContent = msg;
        mdError.hidden = false;
        if (campo) {
            campo.classList.add('is-invalid');
            var foco = campo.matches('input, textarea') ? campo : campo.querySelector('input, button');
            if (foco) foco.focus();
        }
    }
    function ocupado(si, texto) {
        [mdOk, mdAtras, mdBorrador].forEach(function (b) { b.disabled = si; });
        if (si) { mdOk.dataset.texto = mdOk.textContent; mdOk.textContent = texto; }
        else if (mdOk.dataset.texto) { mdOk.textContent = mdOk.dataset.texto; delete mdOk.dataset.texto; }
    }
    function el(id) { return document.getElementById(id); }

    /* El comunicado (c) se edita; con copia = true se usa como base de uno nuevo */
    function abrirAsistente(c, copia) {
        var ed = c && !copia;
        md = {
            tipo: 'asistente', id: ed ? c.id : null, publicado: ed && estado(c) === 'publicado', paso: 1,
            d: {
                destino: c ? c.destino : '', titulo: c ? c.titulo : '', categoria: c ? c.categoria : 'general', mensaje: c ? c.mensaje : '',
                // El adjunto de otro comunicado no se copia al duplicar (vive en el servidor): se vuelve a subir
                adjunto: ed && c.adjunto ? c.adjunto : null, archivo: null, quitar: false, fijado: c ? c.fijado : false, visible_hasta: ed && c.visible_hasta ? c.visible_hasta : '',
                cuando: ed && estado(c) === 'programado' ? 'programar' : 'ahora',
                fecha: ed && estado(c) === 'programado' ? c.publicado_en.slice(0, 10) : '', hora: ed && estado(c) === 'programado' ? c.publicado_en.slice(11, 16) : '07:00'
            }
        };
        el('com-md-titulo').textContent = ed ? 'Editar comunicado' : (copia ? 'Duplicar comunicado' : 'Nuevo comunicado');
        mdSteps.hidden = false;
        abrirModal();
        paso(1);
    }
    function marcarPasos(n) {
        mdSteps.querySelectorAll('.com-wstep').forEach(function (s) {
            var k = +s.dataset.step;
            s.classList.toggle('is-active', k === n);
            s.classList.toggle('is-done', k < n);
        });
        el('com-md-paso').textContent = 'Paso ' + n + ' de 3';
    }
    function guardarPaso() {
        var d = md.d;
        if (md.paso === 1) d.destino = (mdBody.querySelector('input[name="com-f-destino"]:checked') || {}).value || '';
        if (md.paso === 2) {
            d.titulo = el('com-f-titulo').value;
            d.categoria = (mdBody.querySelector('input[name="com-f-cat"]:checked') || {}).value || '';
            d.mensaje = el('com-f-msg').value;
        }
        if (md.paso === 3) {
            if (!md.publicado) {
                d.cuando = (mdBody.querySelector('input[name="com-f-cuando"]:checked') || {}).value || 'ahora';
                d.fecha = el('com-f-fecha').value; d.hora = el('com-f-hora').value;
            }
            d.visible_hasta = el('com-f-hasta').value;
            d.fijado = el('com-f-fijar').checked;
        }
    }
    function chip(name, val, txt, checked) {
        return '<label class="com-chip"><input type="radio" name="' + name + '" value="' + val + '"' + (checked ? ' checked' : '') + '><span>' + txt + '</span></label>';
    }
    function paso(n) {
        md.paso = n;
        marcarPasos(n);
        mdError.hidden = true;
        var d = md.d, html = '';
        if (n === 1) {
            var cuenta = function (k) { return claves(k).length; };
            var OPC = [
                ['sede', 'Toda la sede', 'Estudiantes, docentes y acudientes.'],
                ['estudiantes', 'Estudiantes', 'Los que tienen matrícula vigente en la sede.'],
                ['docentes', 'Docentes', 'Los docentes activos de la sede.'],
                ['acudientes', 'Acudientes', 'Padres y acudientes de los estudiantes.']
            ];
            html = '<p class="com-ayuda">¿A quién le llega? Los docentes les escriben a sus grupos desde su propio panel.</p><div class="com-destinos" id="com-f-destinos" role="radiogroup" aria-label="Destinatarios">' +
                OPC.map(function (o) {
                    var n2 = cuenta(o[0]);
                    return '<label class="com-destino"><input type="radio" name="com-f-destino" value="' + o[0] + '"' + (d.destino === o[0] ? ' checked' : '') + (md.publicado ? ' disabled' : '') + '>' +
                        '<span class="com-destino-box"><strong>' + o[1] + '</strong><span>' + o[2] + '</span><em>' + n2 + (n2 === 1 ? ' persona' : ' personas') + '</em></span></label>';
                }).join('') + '</div>' +
                (md.publicado ? '<div class="com-nota">' + ICON.info + '<p>Ya está publicado: los destinatarios no se cambian. Si necesitas que le llegue a otras personas, duplícalo.</p></div>' : '');
        } else if (n === 2) {
            html = '<div class="com-field"><label class="com-lbl" for="com-f-titulo">Título *</label><input class="com-input" id="com-f-titulo" type="text" maxlength="120" autocomplete="off" placeholder="Ej. Sin clases el lunes festivo" value="' + esc(d.titulo) + '"></div>' +
                '<div class="com-field"><span class="com-lbl">Categoría *</span><div class="com-chips" id="com-f-cats" role="radiogroup" aria-label="Categoría">' +
                    Object.keys(CAT).map(function (k) { return chip('com-f-cat', k, CAT[k], d.categoria === k); }).join('') + '</div></div>' +
                '<div class="com-field"><label class="com-lbl" for="com-f-msg">Mensaje *</label><textarea class="com-input com-textarea" id="com-f-msg" rows="7" maxlength="' + MAX_MSG + '" placeholder="Escribe el comunicado como lo leerá la persona.">' + esc(d.mensaje) + '</textarea>' +
                    '<span class="com-contador" id="com-f-contador"></span></div>' +
                '<div class="com-field"><span class="com-lbl">Adjunto <em>opcional · PDF, JPG o PNG de máximo 5 MB</em></span>' +
                    '<div class="com-file" id="com-f-file"><input type="file" id="com-f-adjunto" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">' +
                        '<div class="com-file-txt" id="com-f-file-txt"></div>' +
                        '<label class="com-btn com-btn-ghost com-btn-sm" for="com-f-adjunto">' + ICON.clip + 'Elegir archivo</label>' +
                        '<button type="button" class="com-file-quitar" id="com-f-quitar">Quitar</button></div></div>';
        } else {
            var minFecha = HOY;
            html = (md.publicado ? '' :
                '<div class="com-field"><span class="com-lbl">¿Cuándo se publica? *</span><div class="com-chips" role="radiogroup" aria-label="Cuándo se publica">' +
                    chip('com-f-cuando', 'ahora', 'Publicar ahora', d.cuando === 'ahora') + chip('com-f-cuando', 'programar', 'Programar fecha y hora', d.cuando === 'programar') + '</div></div>' +
                '<div class="com-programar" id="com-f-prog"' + (d.cuando === 'programar' ? '' : ' hidden') + '>' +
                    '<div class="com-field"><label class="com-lbl" for="com-f-fecha">Fecha *</label><input class="com-input" id="com-f-fecha" type="date" min="' + minFecha + '" value="' + esc(d.fecha) + '"></div>' +
                    '<div class="com-field"><label class="com-lbl" for="com-f-hora">Hora *</label><input class="com-input" id="com-f-hora" type="time" value="' + esc(d.hora) + '"></div></div>') +
                '<div class="com-field"><label class="com-lbl" for="com-f-hasta">Visible hasta <em>opcional · después de esta fecha deja de verse en los paneles</em></label><input class="com-input" id="com-f-hasta" type="date" min="' + minFecha + '" value="' + esc(d.visible_hasta) + '"></div>' +
                '<label class="com-switch"><input type="checkbox" id="com-f-fijar"' + (d.fijado ? ' checked' : '') + '><span class="com-switch-pista" aria-hidden="true"></span>' +
                    '<span class="com-switch-txt"><strong>Fijar arriba</strong><span>Aparece primero en el panel de cada persona hasta que lo quites o deje de ser visible.</span></span></label>' +
                '<ul class="com-resumen-lista">' +
                    '<li><span>Para</span><span>' + esc(DESTINO[d.destino]) + ' · ' + claves(d.destino).length + ' personas</span></li>' +
                    '<li><span>Título</span><span>' + esc(d.titulo.trim()) + '</span></li>' +
                    '<li><span>Categoría</span><span>' + esc(CAT[d.categoria] || '—') + '</span></li>' +
                    '<li><span>Adjunto</span><span>' + (d.adjunto ? esc(d.adjunto.nombre) : 'Ninguno') + '</span></li></ul>';
        }
        mdBody.innerHTML = html;
        mdBody.scrollTop = 0;
        if (n === 2) prepararPaso2();
        if (n === 3 && !md.publicado) {
            mdBody.querySelectorAll('input[name="com-f-cuando"]').forEach(function (r) {
                r.addEventListener('change', function () { el('com-f-prog').hidden = r.value !== 'programar' || !r.checked; actualizarBotones(); });
            });
        }
        mdBody.querySelectorAll('input, textarea').forEach(function (i) {
            // Al corregir el campo se quita el error
            var limpiar = function () {
                var g = i.classList.contains('is-invalid') ? i : i.closest('.is-invalid');
                if (g) { g.classList.remove('is-invalid'); mdError.hidden = true; }
            };
            i.addEventListener('input', limpiar);
            i.addEventListener('change', limpiar);
        });
        actualizarBotones();
        setTimeout(function () {
            var f = n === 1 ? (mdBody.querySelector('input[name="com-f-destino"]:checked') || mdBody.querySelector('input[name="com-f-destino"]')) : (n === 2 ? el('com-f-titulo') : mdBody.querySelector('input'));
            if (f && !f.disabled) f.focus();
        }, 30);
    }
    function prepararPaso2() {
        var msg = el('com-f-msg'), cont = el('com-f-contador'), file = el('com-f-adjunto');
        function contar() {
            var n = msg.value.length;
            cont.textContent = n.toLocaleString('es-CO') + ' / ' + MAX_MSG.toLocaleString('es-CO');
            cont.classList.toggle('is-tope', n >= MAX_MSG);
        }
        function pintarAdj() {
            var a = md.d.adjunto;
            el('com-f-file-txt').innerHTML = a ? '<strong>' + esc(a.nombre) + '</strong>' + (a.tipo === 'pdf' ? 'PDF' : 'Imagen') + ' · ' + peso(a.peso) : 'Ningún archivo. Sirve para una circular, un afiche o los requisitos.';
            el('com-f-quitar').hidden = !a;
        }
        msg.addEventListener('input', contar);
        file.addEventListener('change', function () {
            var f = file.files && file.files[0];
            el('com-f-file').classList.remove('is-invalid');
            if (!f) return;
            var tipo = /\.pdf$/i.test(f.name) ? 'pdf' : (/\.(jpe?g|png)$/i.test(f.name) ? 'imagen' : null);
            if (!tipo || f.size > MAX_ADJ) {
                file.value = '';
                return error(!tipo ? 'El adjunto debe ser PDF, JPG o PNG.' : 'El adjunto pesa ' + peso(f.size) + ': el máximo es 5 MB.', el('com-f-file'));
            }
            mdError.hidden = true;
            md.d.adjunto = { nombre: f.name, tipo: tipo, peso: f.size };
            md.d.archivo = f;
            pintarAdj();
        });
        el('com-f-quitar').addEventListener('click', function () {
            if (md.d.adjunto && md.d.adjunto.url) md.d.quitar = true;
            md.d.adjunto = null; md.d.archivo = null; file.value = ''; pintarAdj();
        });
        contar(); pintarAdj();
    }
    function actualizarBotones() {
        var n = md.paso;
        mdAtras.textContent = n === 1 ? 'Cancelar' : 'Atrás';
        mdOk.className = 'com-btn com-btn-primary';
        // Un publicado no vuelve a borrador
        mdBorrador.hidden = md.publicado;
        if (n < 3) { mdOk.textContent = 'Siguiente'; return; }
        var prog = !md.publicado && (mdBody.querySelector('input[name="com-f-cuando"]:checked') || {}).value === 'programar';
        mdOk.textContent = md.publicado ? 'Guardar cambios' : (prog ? 'Programar' : 'Publicar ahora');
    }
    /* Revisa el paso n; devuelve el campo con error o null */
    function revisar(n) {
        var d = md.d;
        if (n === 1 && !d.destino) return [el('com-f-destinos'), 'Elige a quién le llega el comunicado.'];
        if (n === 2) {
            if (d.titulo.trim().length < 5) return [el('com-f-titulo'), 'Escribe un título de al menos 5 letras.'];
            if (!d.categoria) return [el('com-f-cats'), 'Elige la categoría.'];
            if (d.mensaje.trim().length < 10) return [el('com-f-msg'), 'Escribe el mensaje del comunicado.'];
        }
        if (n === 3) {
            var pub = HOY;
            if (!md.publicado && d.cuando === 'programar') {
                if (!d.fecha || !d.hora) return [el(d.fecha ? 'com-f-hora' : 'com-f-fecha'), 'Elige la fecha y la hora en que se publica.'];
                if (d.fecha + ' ' + d.hora <= AHORA) return [el('com-f-fecha'), 'La fecha y hora de publicación deben ser futuras.'];
                pub = d.fecha;
            }
            if (d.visible_hasta && d.visible_hasta < pub) return [el('com-f-hasta'), '"Visible hasta" no puede ser antes de que se publique.'];
        }
        return null;
    }
    function enviar(accion) {
        var d = md.d;
        ocupado(true, accion === 'borrador' ? 'Guardando…' : (accion === 'programar' ? 'Programando…' : 'Publicando…'));
        API.guardar({
            id: md.id, accion: accion, destino: d.destino, titulo: d.titulo, categoria: d.categoria, mensaje: d.mensaje, archivo: d.archivo, quitar: d.quitar && !d.archivo,
            publicar_en: accion === 'programar' ? d.fecha + ' ' + d.hora : null, visible_hasta: d.visible_hasta || null, fijado: d.fijado
        }).then(function (j) {
            cerrarModal();
            refrescar();
            mostrarVista(estado(j.comunicado));
            toast(j.mensaje);
            abrirFicha(j.comunicado.id);
        }).catch(function (err) {
            ocupado(false);
            var k = err.errores && Object.keys(err.errores)[0];
            var pasoDe = { destino: 1, titulo: 2, categoria: 2, mensaje: 2, adjunto: 2, publicar_en: 3, visible_hasta: 3 };
            if (k && pasoDe[k] && pasoDe[k] !== md.paso) paso(pasoDe[k]);
            var campos = { destino: 'com-f-destinos', titulo: 'com-f-titulo', categoria: 'com-f-cats', mensaje: 'com-f-msg', adjunto: 'com-f-file', publicar_en: 'com-f-fecha', visible_hasta: 'com-f-hasta' };
            error(k ? err.errores[k][0] : err.message, k ? el(campos[k]) : null);
        });
    }
    mdOk.addEventListener('click', function () {
        if (!md) return;
        mdError.hidden = true;
        if (md.tipo === 'archivar') return guardarArchivar();
        if (md.tipo === 'eliminar') return guardarEliminar();
        guardarPaso();
        var mal = revisar(md.paso);
        if (mal) return error(mal[1], mal[0]);
        if (md.paso < 3) return paso(md.paso + 1);
        enviar(md.publicado ? 'publicar' : (md.d.cuando === 'programar' ? 'programar' : 'publicar'));
    });
    mdAtras.addEventListener('click', function () {
        if (!md || md.tipo !== 'asistente' || md.paso === 1) return cerrarModal();
        guardarPaso();
        paso(md.paso - 1);
    });
    /* Un borrador solo pide a quién va y el título */
    mdBorrador.addEventListener('click', function () {
        if (!md || md.tipo !== 'asistente') return;
        guardarPaso();
        mdError.hidden = true;
        if (!md.d.destino) { if (md.paso !== 1) paso(1); return error('Elige a quién le llega el comunicado.', el('com-f-destinos')); }
        if (md.d.titulo.trim().length < 5) { if (md.paso !== 2) paso(2); return error('Para guardar el borrador escribe al menos el título.', el('com-f-titulo')); }
        enviar('borrador');
    });
    mdBody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'file') { e.preventDefault(); mdOk.click(); }
    });

    function modalSimple(tipo, c, titulo, html, boton) {
        md = { tipo: tipo, id: c.id };
        el('com-md-titulo').textContent = titulo;
        el('com-md-paso').textContent = c.titulo;
        mdSteps.hidden = true;
        abrirModal();
        mdBody.innerHTML = html;
        mdAtras.textContent = 'Cancelar';
        mdBorrador.hidden = true;
        mdOk.className = 'com-btn com-btn-danger';
        mdOk.textContent = boton;
    }
    function abrirArchivar(c) {
        var docente = c.autor.tipo === 'docente', l = lectura(c);
        modalSimple('archivar', c, 'Archivar comunicado',
            aviso('is-rojo', ICON.alerta, '<strong>Dejará de verse en el panel de ' + (estado(c) === 'programado' ? 'las ' + l.total + ' personas a las que les iba a llegar (ya no se publicará)' : 'las ' + l.total + ' personas a las que les llegó') + '.</strong> ' +
                (docente ? 'Lo publicó el docente ' + esc(c.autor.nombre) + ' para ' + esc(paraTxt(c)) + '; el motivo queda registrado.' : 'Queda en Archivados con quién lo leyó.')) +
            '<div class="com-field"><label class="com-lbl" for="com-f-motivo">Motivo *</label><textarea class="com-input com-textarea" id="com-f-motivo" maxlength="255" placeholder="' +
                (docente ? 'Ej. No es un tema de la institución' : 'Ej. El evento ya pasó') + '"></textarea></div>', 'Archivar');
        setTimeout(function () { el('com-f-motivo').focus(); }, 30);
        el('com-f-motivo').addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); });
    }
    function guardarArchivar() {
        var m = el('com-f-motivo'), motivo = m.value.trim(), id = md.id;
        if (motivo.length < 5) return error('Escribe el motivo con un poco más de detalle.', m);
        ocupado(true, 'Archivando…');
        API.archivar(id, motivo).then(function (j) {
            cerrarModal(); refrescar(); toast(j.mensaje); abrirFicha(id);
        }).catch(function (err) { ocupado(false); var k = err.errores && Object.keys(err.errores)[0]; error(k ? err.errores[k][0] : err.message, m); });
    }
    function abrirEliminar(c) {
        modalSimple('eliminar', c, 'Eliminar borrador',
            aviso('is-rojo', ICON.alerta, '<strong>Se eliminará el borrador «' + esc(c.titulo) + '».</strong> Nadie lo ha visto y no se puede recuperar.'), 'Eliminar');
    }
    function guardarEliminar() {
        var id = md.id;
        ocupado(true, 'Eliminando…');
        API.eliminar(id).then(function (j) {
            cerrarModal(); cerrarCajon(); refrescar(); toast(j.mensaje);
        }).catch(function (err) { ocupado(false); error(err.message); });
    }
    document.getElementById('com-btn-nuevo').addEventListener('click', function () { abrirAsistente(null, false); });

    /* Escape: se revisa antes que los combobox (fase de captura); con una lista abierta solo se cierra la lista */
    window.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || document.querySelector('.app-combobox.is-open')) return;
        if (modal.classList.contains('is-open')) cerrarModal(); else cerrarCajon();
    }, true);

    /* ═══════════ Exportar lo que se ve con los filtros (CSV con ; y BOM para las tildes) ═══════════ */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    document.getElementById('com-exportar').addEventListener('click', function () {
        var filas = [['Título', 'Categoría', 'Estado', 'Para', 'Autor', 'Publicado / se publica', 'Visible hasta', 'Fijado', 'Le llega a', 'Lo leyeron', '% leído', 'Adjunto', 'Mensaje']]
            .concat(items.map(function (c) {
                var l = lectura(c), e = estado(c);
                return [c.titulo, CAT[c.categoria], { publicado: 'Publicado', programado: 'Programado', borrador: 'Borrador', archivado: 'Archivado' }[e], paraTxt(c),
                    c.autor.tipo === 'admin' ? 'Administración (' + c.autor.nombre + ')' : c.autor.nombre + ' (docente)', c.publicado_en || '', c.visible_hasta || '',
                    c.fijado ? 'Sí' : 'No', l.total, e === 'publicado' || e === 'archivado' ? l.leidos : '', e === 'publicado' || e === 'archivado' ? l.pct : '',
                    c.adjunto ? c.adjunto.nombre : '', c.mensaje];
            }));
        var csv = '﻿' + filas.map(function (r) { return r.map(celdaCsv).join(';'); }).join('\r\n');
        var nombre = 'comunicados-' + vista + 's-' + HOY + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a');
        a.href = url; a.download = nombre;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        var n = filas.length - 1;
        toast('Se descargó ' + nombre + ' con ' + n + (n === 1 ? ' fila.' : ' filas.'));
    });

    /* Inicializar */
    pintarKpis();
    var guardada = null;
    try { guardada = localStorage.getItem(VISTA_KEY); } catch (e) {}
    mostrarVista(guardada && FECHA_COL[guardada] ? guardada : 'publicado');
}());
