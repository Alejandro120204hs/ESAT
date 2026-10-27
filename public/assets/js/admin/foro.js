/* ESAT — Admin: Foro (Comunicación). Datos de ForoService (FORO_DATOS); lo que hace la administración va a
   ForoController (TemaRequest, RespuestaRequest, EstadoTemaRequest, OcultarMensajeRequest).
   Un foro general por sede: estudiantes, docentes, acudientes y administración abren temas y responden. El admin
   modera después, con reportes: fija, cierra o reabre temas, oculta mensajes con motivo (ocultar el primero oculta
   el tema), los restaura, descarta reportes y responde como administración. */
(function () {
    'use strict';

    var CFG = window.FORO_CFG;
    var D = window.FORO_DATOS;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var AHORA = CFG.ahora;
    var HOY = AHORA.slice(0, 10);
    var VISTA_KEY = 'esat.admin.foro.vista';
    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var CAT = { academico: 'Dudas académicas', practicas: 'Prácticas y salidas', tramites: 'Trámites y pagos', vida: 'Vida en la sede' };
    var ROL = { e: 'Estudiante', d: 'Docente', a: 'Acudiente', x: 'Administración' };
    var MAX_TXT = 3000, MAX_ADJ = 5 * 1024 * 1024;

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function dos(n) { return (n < 10 ? '0' : '') + n; }
    function corta(f) { var p = f.slice(0, 10).split('-'); return +p[2] + ' ' + MESES[+p[1] - 1] + (p[0] !== HOY.slice(0, 4) ? ' ' + p[0] : ''); }
    function hora(f) { var h = +f.slice(11, 13); return (h % 12 || 12) + ':' + f.slice(14, 16) + (h < 12 ? ' a. m.' : ' p. m.'); }
    function cuando(f) {
        if (f.slice(0, 10) === HOY) return 'Hoy, ' + hora(f);
        var ayer = new Date(HOY + 'T12:00:00'); ayer.setDate(ayer.getDate() - 1);
        if (f.slice(0, 10) === ayer.getFullYear() + '-' + dos(ayer.getMonth() + 1) + '-' + dos(ayer.getDate())) return 'Ayer, ' + hora(f);
        return corta(f) + ', ' + hora(f);
    }
    function iniciales(n) { var p = String(n || '').split(' '); return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase(); }
    function peso(b) { return b >= 1048576 ? (Math.round(b / 104857.6) / 10).toString().replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function catTag(c) { return '<span class="for-cat c-' + c + '">' + esc(CAT[c]) + '</span>'; }
    function tag(clase, txt) { return '<span class="app-status-tag ' + clase + '">' + esc(txt) + '</span>'; }
    function persona(k) { var p = D.personas[k] || ['Persona', '']; return { clave: k, rol: k.charAt(0), nombre: p[0], detalle: p[1] }; }
    function rolHtml(k) { var p = persona(k); return '<span class="for-rol"><i class="r-' + p.rol + '"></i>' + ROL[p.rol] + '</span>'; }
    var ICON = {
        pin: '<svg viewBox="0 0 24 24"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1z"/></svg>',
        candado: '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        abrir: '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>',
        clip: '<svg viewBox="0 0 24 24"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
        ojo: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        ocultar: '<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>',
        restaurar: '<svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>',
        bandera: '<svg viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        ok: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        burbuja: '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        pdf: '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        imagen: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'
    };

    var toastEl = document.getElementById('for-toast');
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
                            : r.status === 403 ? 'No tienes permiso para hacer esto (es de otra sede o tu usuario cambió de sede). Recarga la página.'
                            : r.status === 404 ? 'Ya no existe. Recarga la página.'
                            : 'No se pudo guardar por un error del servidor. Intenta de nuevo.';
                        var err = new Error(msg);
                        err.errores = r.status === 422 ? (j.errors || null) : null;
                        throw err;
                    }
                    return j;
                });
            });
    }
    /* Deja el tema que respondió el servidor en la lista (nuevo o reemplazando al anterior) */
    function guardarLocal(j) {
        var i = D.temas.findIndex(function (k) { return k.id === j.tema.id; });
        if (i === -1) D.temas.push(j.tema); else D.temas[i] = j.tema;
        return j;
    }
    var API = {
        crearTema: function (x) {
            return enviarServidor('POST', CFG.url + '/temas', { categoria: x.categoria, titulo: x.titulo, texto: x.texto, fijado: x.fijado, adjunto: x.adjunto && x.adjunto.file }).then(guardarLocal);
        },
        responder: function (temaId, x) {
            return enviarServidor('POST', CFG.url + '/temas/' + temaId + '/mensajes', { texto: x.texto, adjunto: x.adjunto && x.adjunto.file }).then(guardarLocal);
        },
        fijar: function (temaId, si) { return enviarServidor('PATCH', CFG.url + '/temas/' + temaId, { fijado: si }).then(guardarLocal); },
        cerrar: function (temaId, si) { return enviarServidor('PATCH', CFG.url + '/temas/' + temaId, { cerrado: si }).then(guardarLocal); },
        ocultar: function (msgId, motivo) { return enviarServidor('PATCH', CFG.url + '/mensajes/' + msgId + '/ocultar', { motivo: motivo }).then(guardarLocal); },
        restaurar: function (msgId) { return enviarServidor('PATCH', CFG.url + '/mensajes/' + msgId + '/restaurar', {}).then(guardarLocal); },
        descartarReportes: function (msgId) { return enviarServidor('PATCH', CFG.url + '/mensajes/' + msgId + '/descartar', {}).then(guardarLocal); }
    };

    /* ── Reglas ───────────────────────────── */
    function temaOculto(t) { return !!t.mensajes[0].oculto; }
    function visibles(t) { return t.mensajes.filter(function (m) { return !m.oculto; }); }
    function respuestas(t) { return visibles(t).length - 1; }
    function ultimo(t) { var v = visibles(t); return v.length ? v[v.length - 1] : t.mensajes[t.mensajes.length - 1]; }
    function pendientes(m) { return m.reportes.filter(function (r) { return r.estado === 'pendiente'; }); }
    function reportesTema(t) { return t.mensajes.reduce(function (s, m) { return s + (m.oculto ? 0 : pendientes(m).length); }, 0); }
    function todosLosMensajes() {
        var out = [];
        D.temas.forEach(function (t) { t.mensajes.forEach(function (m, i) { out.push({ t: t, m: m, primero: i === 0 }); }); });
        return out;
    }

    /* ── Combobox de filtros (mismo patrón que Comunicados) ── */
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

    /* ═══════════ Temas ═══════════ */
    var tBody = document.getElementById('for-t-body');
    var pagEl = document.getElementById('for-t-pagination');
    var PAGE = 10, pagina = 1, items = [];
    function datosTemas() {
        var q = (document.getElementById('for-t-search').value || '').toLowerCase().trim();
        var c = valor('for-t-categoria'), e = valor('for-t-estado'), r = valor('for-t-rol');
        return D.temas.filter(function (t) {
            if (temaOculto(t)) return false;
            var st = t.cerrado ? 'cerrado' : 'abierto';
            return (!c || t.categoria === c) && (!r || t.mensajes[0].autor.charAt(0) === r) &&
                (!e || (e === 'reportes' ? reportesTema(t) > 0 : (e === 'sin' ? respuestas(t) === 0 && !t.cerrado : st === e))) &&
                (!q || (t.titulo + ' ' + visibles(t).map(function (m) { return m.texto + ' ' + persona(m.autor).nombre; }).join(' ')).toLowerCase().indexOf(q) !== -1);
        }).sort(function (a, b) {
            return (b.fijado ? 1 : 0) - (a.fijado ? 1 : 0) || ultimo(b).fecha.localeCompare(ultimo(a).fecha);
        });
    }
    function filaTema(t) {
        var p0 = t.mensajes[0], autor = persona(p0.autor), u = ultimo(t), n = respuestas(t), rep = reportesTema(t);
        var marcas = (t.fijado ? ICON.pin.replace('<svg ', '<svg class="is-fijado" role="img" aria-label="Fijado arriba" ') : '') +
            (t.cerrado ? ICON.candado.replace('<svg ', '<svg role="img" aria-label="Cerrado" ') : '') +
            (t.mensajes.some(function (m) { return m.adjunto && !m.oculto; }) ? ICON.clip.replace('<svg ', '<svg role="img" aria-label="Tiene adjuntos" ') : '');
        var tags = (rep ? tag('app-status-danger', rep + (rep === 1 ? ' reporte' : ' reportes')) : '') + (t.cerrado ? tag('app-status-muted', 'Cerrado') : '') +
            (!n && !t.cerrado ? tag('app-status-warn', 'Sin respuesta') : '');
        return '<tr class="for-row" data-tema="' + t.id + '">' +
            '<td><div class="for-cell"><div class="for-cell-titulo">' + marcas + '<span>' + esc(t.titulo) + '</span></div><div class="for-cell-msg">' + esc(p0.texto) + '</div>' +
                '<div class="for-cell-tags">' + tags + '</div></div></td>' +
            '<td>' + catTag(t.categoria) + '</td>' +
            '<td><div class="for-person-name for-nowrap">' + esc(autor.nombre) + '</div>' + rolHtml(p0.autor) + '</td>' +
            '<td class="num"><span class="for-num">' + n + '</span></td>' +
            '<td class="for-nowrap">' + cuando(u.fecha) + '<div class="for-person-sub">' + esc(n ? 'Respondió ' + persona(u.autor).nombre.split(' ')[0] : 'Lo abrió ' + autor.nombre.split(' ')[0]) + '</div></td>' +
            '<td class="for-actions-cell"><button type="button" class="for-icon-btn" title="Ver conversación" aria-label="Ver ' + esc(t.titulo) + '">' + ICON.ojo + '</button></td></tr>';
    }
    function aplicar(mantener) {
        items = datosTemas();
        if (!mantener) pagina = 1;
        pagina = Math.min(pagina, Math.max(1, Math.ceil(items.length / PAGE)));
        pintarTemas();
    }
    function pintarTemas() {
        var total = items.length, paginas = Math.max(1, Math.ceil(total / PAGE)), ini = (pagina - 1) * PAGE;
        tBody.innerHTML = items.slice(ini, ini + PAGE).map(filaTema).join('');
        document.getElementById('for-t-count').textContent = total + (total === 1 ? ' tema' : ' temas');
        document.getElementById('for-t-empty').hidden = total > 0;
        pagEl.hidden = paginas <= 1;
        if (paginas <= 1) { pagEl.innerHTML = ''; return; }
        var html = '<button type="button" class="for-pg-btn" data-pg="prev"' + (pagina === 1 ? ' disabled' : '') + '><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Anterior</button>';
        for (var p = 1; p <= paginas; p++) html += '<button type="button" class="for-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
        html += '<button type="button" class="for-pg-btn" data-pg="next"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg></button>';
        pagEl.innerHTML = html;
    }
    pagEl.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pg]');
        if (!b || b.disabled) return;
        var paginas = Math.ceil(items.length / PAGE), v = b.dataset.pg;
        pagina = v === 'prev' ? Math.max(1, pagina - 1) : (v === 'next' ? Math.min(paginas, pagina + 1) : +v);
        pintarTemas();
        tBody.closest('.for-table-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    filtroCombo('for-t-cb-categoria', [{ value: '', label: 'Todas las categorías' }].concat(Object.keys(CAT).map(function (k) { return { value: k, label: CAT[k] }; })), function () { aplicar(); });
    filtroCombo('for-t-cb-estado', [{ value: '', label: 'Todos los temas' }, { value: 'abierto', label: 'Abiertos' }, { value: 'cerrado', label: 'Cerrados' },
        { value: 'sin', label: 'Sin respuesta' }, { value: 'reportes', label: 'Con reportes' }], function () { aplicar(); });
    filtroCombo('for-t-cb-rol', [{ value: '', label: 'Cualquier autor' }, { value: 'e', label: 'Abiertos por estudiantes' }, { value: 'd', label: 'Abiertos por docentes' },
        { value: 'a', label: 'Abiertos por acudientes' }, { value: 'x', label: 'Abiertos por la administración' }], function () { aplicar(); });
    document.getElementById('for-t-search').addEventListener('input', function () { aplicar(); });

    /* ═══════════ Reportes y ocultos ═══════════ */
    function filaMensaje(x, der) {
        var p = persona(x.m.autor);
        return '<tr class="for-row" data-tema="' + x.t.id + '" data-msg="' + x.m.id + '">' +
            '<td><div class="for-cell"><div class="for-cell-titulo"><span>' + esc(x.m.texto) + '</span></div><div class="for-cell-msg">' + (x.primero ? 'Abre el tema' : 'En') + ' «' + esc(x.t.titulo) + '»</div></div></td>' +
            '<td><div class="for-person-name for-nowrap">' + esc(p.nombre) + '</div>' + rolHtml(x.m.autor) + '</td>' + der +
            '<td class="for-actions-cell"><button type="button" class="for-icon-btn" title="Ver en la conversación" aria-label="Ver mensaje de ' + esc(p.nombre) + '">' + ICON.ojo + '</button></td></tr>';
    }
    function pintarReportes() {
        var lista = todosLosMensajes().filter(function (x) { return !x.m.oculto && pendientes(x.m).length; })
            .sort(function (a, b) { return ultRep(b).localeCompare(ultRep(a)); });
        document.getElementById('for-r-body').innerHTML = lista.map(function (x) {
            var ps = pendientes(x.m);
            return filaMensaje(x, '<td><span class="app-status-tag app-status-danger">' + ps.length + (ps.length === 1 ? ' reporte' : ' reportes') + '</span><div class="for-person-sub">«' + esc(ps[ps.length - 1].motivo) + '»</div></td>' +
                '<td class="for-nowrap">' + cuando(ultRep(x)) + '<div class="for-person-sub">por ' + esc(persona(ps[ps.length - 1].por).nombre) + '</div></td>');
        }).join('');
        document.getElementById('for-r-empty').hidden = lista.length > 0;
    }
    function ultRep(x) { var ps = pendientes(x.m); return ps.length ? ps[ps.length - 1].fecha : ''; }
    function pintarOcultos() {
        var lista = todosLosMensajes().filter(function (x) { return x.m.oculto; }).sort(function (a, b) { return b.m.oculto.fecha.localeCompare(a.m.oculto.fecha); });
        document.getElementById('for-o-body').innerHTML = lista.map(function (x) {
            return filaMensaje(x, '<td><div class="for-cell-msg" style="white-space:normal;max-width:240px">' + esc(x.m.oculto.motivo) + '</div>' + (x.primero ? tag('app-status-muted', 'Tema completo') : '') + '</td>' +
                '<td class="for-nowrap">' + cuando(x.m.oculto.fecha) + '<div class="for-person-sub">por ' + esc(persona(x.m.oculto.por).nombre) + '</div></td>');
        }).join('');
        document.getElementById('for-o-empty').hidden = lista.length > 0;
    }
    ['for-t-body', 'for-r-body', 'for-o-body'].forEach(function (id) {
        document.getElementById(id).addEventListener('click', function (e) {
            var row = e.target.closest('.for-row');
            if (row) abrirTema(+row.dataset.tema, row.dataset.msg ? +row.dataset.msg : null);
        });
    });

    /* ═══════════ KPIs ═══════════ */
    function pintarKpis() {
        var activos = D.temas.filter(function (t) { return !temaOculto(t) && !t.cerrado; });
        var hace7 = new Date(HOY + 'T00:00:00'); hace7.setDate(hace7.getDate() - 6);
        var desde = hace7.getFullYear() + '-' + dos(hace7.getMonth() + 1) + '-' + dos(hace7.getDate());
        var semana = todosLosMensajes().filter(function (x) { return !x.m.oculto && x.m.fecha.slice(0, 10) >= desde; });
        var personasSemana = {};
        semana.forEach(function (x) { personasSemana[x.m.autor] = 1; });
        var conReporte = todosLosMensajes().filter(function (x) { return !x.m.oculto && pendientes(x.m).length; });
        var sinResp = activos.filter(function (t) { return respuestas(t) === 0; });
        document.getElementById('for-k-temas').textContent = activos.length;
        document.getElementById('for-k-temas-lbl').textContent = 'Temas abiertos · ' + D.temas.filter(function (t) { return !temaOculto(t) && t.cerrado; }).length + ' cerrados';
        document.getElementById('for-k-semana').textContent = semana.length;
        document.getElementById('for-k-semana-lbl').textContent = 'Mensajes en los últimos 7 días · ' + Object.keys(personasSemana).length + ' personas';
        document.getElementById('for-k-reportes').textContent = conReporte.length;
        document.getElementById('for-k-reportes-lbl').textContent = conReporte.length === 1 ? 'Mensaje reportado por revisar' : 'Mensajes reportados por revisar';
        document.getElementById('for-k-sinresp').textContent = sinResp.length;
        document.getElementById('for-k-sinresp-lbl').textContent = sinResp.length === 1 ? 'Tema sin ninguna respuesta' : 'Temas sin ninguna respuesta';
        document.getElementById('for-n-temas').textContent = D.temas.filter(function (t) { return !temaOculto(t); }).length;
        var nR = document.getElementById('for-n-reportes');
        nR.textContent = conReporte.length;
        nR.classList.toggle('is-aviso', conReporte.length > 0);
        document.getElementById('for-n-ocultos').textContent = todosLosMensajes().filter(function (x) { return x.m.oculto; }).length;
    }
    function refrescar() { pintarKpis(); aplicar(true); pintarReportes(); pintarOcultos(); }

    /* ── Pestañas (se recuerda la última) ── */
    var vistaBtns = document.querySelectorAll('.for-vista-btn');
    function mostrarVista(v) {
        vistaBtns.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.for-vista').forEach(function (s) { s.hidden = s.dataset.panel !== v; });
        try { localStorage.setItem(VISTA_KEY, v); } catch (e) {}
    }
    vistaBtns.forEach(function (b) { b.addEventListener('click', function () { mostrarVista(b.dataset.vista); }); });

    /* ═══════════ Cajón: la conversación ═══════════ */
    var drawer = document.getElementById('for-drawer');
    var dwBody = document.getElementById('for-dw-body');
    var resp = document.getElementById('for-responder');
    var ultimoFoco = null, temaId = null, borrador = { texto: '', archivo: null };

    function abrirCajon() {
        if (!drawer.classList.contains('is-open')) ultimoFoco = document.activeElement;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function cerrarCajon() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        temaId = null;
        if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus({ preventScroll: true });
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarCajon); });
    function dato(lbl, val) { return '<div class="for-dato"><span class="for-dato-lbl">' + lbl + '</span><span class="for-dato-val">' + val + '</span></div>'; }
    function adjuntoHtml(a) {
        var dentro = (a.tipo === 'pdf' ? ICON.pdf : ICON.imagen) + '<div class="for-min0"><strong>' + esc(a.nombre) + '</strong><small>' + (a.tipo === 'pdf' ? 'PDF' : 'Imagen') + ' · ' + peso(a.peso) + (a.url ? ' · abrir' : '') + '</small></div>';
        return a.url ? '<a class="for-adjunto" href="' + esc(a.url) + '" target="_blank" rel="noopener">' + dentro + '</a>' : '<div class="for-adjunto">' + dentro + '</div>';
    }
    function mensajeHtml(t, m, i, foco) {
        var p = persona(m.autor), ps = pendientes(m), oculto = m.oculto;
        var cls = 'for-msg' + (i === 0 ? ' is-primero' : '') + (oculto ? ' is-oculto' : '') + (!oculto && ps.length ? ' is-reportado' : '') + (foco === m.id ? ' is-foco' : '');
        var html = '<li class="' + cls + '" id="for-msg-' + m.id + '"><span class="for-av r-' + p.rol + '" aria-hidden="true">' + esc(iniciales(p.nombre)) + '</span><div class="for-min0">' +
            '<div class="for-msg-cab"><div class="for-msg-quien"><span class="for-msg-nombre">' + esc(p.nombre) + '</span> ' + rolHtml(m.autor) +
                (p.detalle && p.rol !== 'x' ? '<div class="for-msg-det">' + esc(p.detalle) + '</div>' : '') + '</div>' +
                '<span class="for-msg-fecha">' + cuando(m.fecha) + '</span></div>' +
            '<p class="for-msg-texto">' + esc(m.texto) + '</p>' + (m.adjunto ? adjuntoHtml(m.adjunto) : '');
        if (oculto) {
            html += '<div class="for-nota-msg is-oculto"><strong>' + (i === 0 ? 'Tema oculto' : 'Mensaje oculto') + '</strong> el ' + cuando(oculto.fecha) + ' por ' + esc(persona(oculto.por).nombre) + ': ' + esc(oculto.motivo) +
                ' Nadie más lo ve.<div class="for-nota-msg-acciones"><button type="button" class="for-btn for-btn-ghost for-btn-sm" data-restaurar="' + m.id + '">' + ICON.restaurar + 'Restaurar</button></div></div>';
        } else if (ps.length) {
            html += '<div class="for-nota-msg is-reporte"><strong>' + ps.length + (ps.length === 1 ? ' reporte' : ' reportes') + ' por revisar</strong><ul>' +
                ps.map(function (r) { return '<li>' + esc(persona(r.por).nombre) + ' (' + ROL[r.por.charAt(0)].toLowerCase() + ', ' + cuando(r.fecha) + '): «' + esc(r.motivo) + '»</li>'; }).join('') + '</ul>' +
                '<div class="for-nota-msg-acciones"><button type="button" class="for-btn for-btn-danger for-btn-sm" data-ocultar="' + m.id + '">' + ICON.ocultar + 'Ocultar</button>' +
                '<button type="button" class="for-btn for-btn-ghost for-btn-sm" data-descartar="' + m.id + '">' + ICON.ok + 'Descartar reporte</button></div></div>';
        } else if (m.autor !== CFG.yo || i > 0) {
            html += '<div class="for-msg-pie"><button type="button" class="for-link-btn is-peligro" data-ocultar="' + m.id + '">' + ICON.ocultar + (i === 0 ? 'Ocultar tema' : 'Ocultar') + '</button></div>';
        }
        return html + '</div></li>';
    }
    function abrirTema(id, foco) {
        var t = D.temas.find(function (x) { return x.id === id; });
        if (!t) return;
        var nuevo = temaId !== id;
        temaId = id;
        if (nuevo) borrador = { texto: '', archivo: null };
        var p0 = t.mensajes[0], autor = persona(p0.autor), n = respuestas(t);
        var participantes = {};
        visibles(t).forEach(function (m) { participantes[m.autor] = 1; });
        var avatar = document.getElementById('for-dw-avatar');
        avatar.innerHTML = ICON.burbuja;
        avatar.className = 'for-drawer-avatar c-' + t.categoria;
        document.getElementById('for-dw-titulo').textContent = t.titulo;
        document.getElementById('for-dw-sub').textContent = 'Lo abrió ' + autor.nombre + ' (' + ROL[autor.rol].toLowerCase() + ') · ' + cuando(p0.fecha);
        document.getElementById('for-dw-badges').innerHTML = (temaOculto(t) ? tag('app-status-danger', 'Tema oculto') : (t.cerrado ? tag('app-status-muted', 'Cerrado') : tag('app-status-active', 'Abierto'))) +
            catTag(t.categoria) + (t.fijado ? tag('app-status-warn', 'Fijado arriba') : '');

        var html = '<div class="for-datos">' + dato('Respuestas', n) + dato('Participan', Object.keys(participantes).length + ' <small>personas</small>') +
            dato('Última actividad', corta(ultimo(t).fecha) + '<small>' + hora(ultimo(t).fecha) + '</small>') + dato('Reportes', reportesTema(t) || '—') + '</div>';
        if (!temaOculto(t)) {
            html += '<div class="for-acciones-fila">' +
                '<button type="button" class="for-btn for-btn-ghost for-btn-sm" data-fijar="' + (t.fijado ? '0' : '1') + '">' + ICON.pin + (t.fijado ? 'Quitar de arriba' : 'Fijar arriba') + '</button>' +
                '<button type="button" class="for-btn for-btn-ghost for-btn-sm" data-cerrar-tema="' + (t.cerrado ? '0' : '1') + '">' + (t.cerrado ? ICON.abrir + 'Reabrir tema' : ICON.candado + 'Cerrar tema') + '</button></div>';
        }
        if (t.cerrado && !temaOculto(t)) {
            html += '<div class="for-nota">' + ICON.candado + '<p>Cerrado el ' + cuando(t.cerrado.fecha) + ' por ' + esc(persona(t.cerrado.por).nombre) + ': se puede leer, pero nadie puede responder.</p></div>';
        }
        html += '<ul class="for-hilo">' + t.mensajes.map(function (m, i) { return mensajeHtml(t, m, i, foco); }).join('') + '</ul>';
        dwBody.innerHTML = html;
        pintarResponder(t);
        abrirCajon();
        if (foco) {
            var el = document.getElementById('for-msg-' + foco);
            if (el) setTimeout(function () { el.scrollIntoView({ block: 'center' }); }, 40);
        } else if (nuevo) dwBody.scrollTop = 0;
        if (nuevo) setTimeout(function () { drawer.querySelector('.for-close-btn[data-cerrar]').focus({ preventScroll: true }); }, 30);
    }

    /* Responder como administración (abajo del cajón) */
    function pintarResponder(t) {
        resp.hidden = false;
        if (temaOculto(t)) { resp.hidden = true; return; }
        if (t.cerrado) {
            resp.innerHTML = '<div class="for-responder-cerrado"><span>El tema está cerrado: nadie puede responder.</span><button type="button" class="for-btn for-btn-ghost for-btn-sm" data-cerrar-tema="0">' + ICON.abrir + 'Reabrir tema</button></div>';
            return;
        }
        resp.innerHTML = '<label class="for-lbl" for="for-r-texto">Responder como administración</label>' +
            '<textarea class="for-input for-textarea" id="for-r-texto" maxlength="' + MAX_TXT + '" placeholder="Escribe tu respuesta…">' + esc(borrador.texto) + '</textarea>' +
            '<div class="for-form-error" id="for-r-error" role="alert" hidden style="margin:0"></div>' +
            '<div class="for-responder-pie"><div class="for-adj-elegido" id="for-r-adj"></div>' +
                '<div class="for-acciones-fila"><input type="file" id="for-r-file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">' +
                '<label class="for-btn for-btn-ghost for-btn-sm" for="for-r-file">' + ICON.clip + 'Adjuntar</label>' +
                '<button type="button" class="for-btn for-btn-primary for-btn-sm" id="for-r-enviar">Responder</button></div></div>';
        var txt = document.getElementById('for-r-texto'), file = document.getElementById('for-r-file');
        function pintarAdj() {
            var a = borrador.archivo;
            document.getElementById('for-r-adj').innerHTML = a ? ICON.clip + '<strong>' + esc(a.nombre) + '</strong><button type="button" class="for-file-quitar" id="for-r-quitar">Quitar</button>' : '';
            var q = document.getElementById('for-r-quitar');
            if (q) q.addEventListener('click', function () { borrador.archivo = null; file.value = ''; pintarAdj(); });
        }
        txt.addEventListener('input', function () { borrador.texto = txt.value; txt.classList.remove('is-invalid'); document.getElementById('for-r-error').hidden = true; });
        file.addEventListener('change', function () {
            var f = file.files && file.files[0], err = document.getElementById('for-r-error');
            if (!f) return;
            var a = leerArchivo(f);
            if (a.error) { file.value = ''; err.textContent = a.error; err.hidden = false; return; }
            err.hidden = true;
            borrador.archivo = a;
            pintarAdj();
        });
        document.getElementById('for-r-enviar').addEventListener('click', function () {
            var err = document.getElementById('for-r-error'), b = this;
            if (txt.value.trim().length < 2) { txt.classList.add('is-invalid'); err.textContent = 'Escribe la respuesta.'; err.hidden = false; txt.focus(); return; }
            b.disabled = true; b.textContent = 'Enviando…';
            API.responder(temaId, { texto: txt.value, adjunto: borrador.archivo }).then(function (j) {
                borrador = { texto: '', archivo: null };
                refrescar();
                abrirTema(j.tema.id);
                dwBody.scrollTop = dwBody.scrollHeight;
                toast(j.mensaje);
            }).catch(function (e) {
                b.disabled = false; b.textContent = 'Responder';
                var k = e.errores && Object.keys(e.errores)[0];
                err.textContent = k ? e.errores[k][0] : e.message; err.hidden = false;
            });
        });
        pintarAdj();
    }
    function leerArchivo(f) {
        var tipo = /\.pdf$/i.test(f.name) ? 'pdf' : (/\.(jpe?g|png)$/i.test(f.name) ? 'imagen' : null);
        if (!tipo) return { error: 'El adjunto debe ser PDF, JPG o PNG.' };
        if (f.size > MAX_ADJ) return { error: 'El adjunto pesa ' + peso(f.size) + ': el máximo es 5 MB.' };
        return { nombre: f.name, tipo: tipo, peso: f.size, file: f };
    }

    /* Acciones dentro del cajón */
    function accion(promesa, foco) {
        return promesa.then(function (j) { refrescar(); abrirTema(j.tema.id, foco); toast(j.mensaje); })
            .catch(function (e) { var k = e.errores && Object.keys(e.errores)[0]; toast(k ? e.errores[k][0] : e.message); });
    }
    drawer.addEventListener('click', function (e) {
        var b;
        if ((b = e.target.closest('[data-fijar]'))) return accion(API.fijar(temaId, b.dataset.fijar === '1'));
        if ((b = e.target.closest('[data-cerrar-tema]'))) return accion(API.cerrar(temaId, b.dataset.cerrarTema === '1'));
        if ((b = e.target.closest('[data-restaurar]'))) return accion(API.restaurar(+b.dataset.restaurar), +b.dataset.restaurar);
        if ((b = e.target.closest('[data-descartar]'))) return accion(API.descartarReportes(+b.dataset.descartar), +b.dataset.descartar);
        if ((b = e.target.closest('[data-ocultar]'))) return abrirOcultar(+b.dataset.ocultar);
    });

    /* ═══════════ Modal: nuevo tema / ocultar ═══════════ */
    var modal = document.getElementById('for-modal');
    var mdBody = document.getElementById('for-md-body');
    var mdError = document.getElementById('for-md-error');
    var mdOk = document.getElementById('for-md-ok');
    var md = null, mdFoco = null;
    function el(id) { return document.getElementById(id); }
    function abrirModal() {
        mdFoco = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        mdError.hidden = true;
        mdOk.disabled = false;
    }
    function cerrarModal() {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        md = null;
        if (mdFoco && mdFoco.focus) mdFoco.focus({ preventScroll: true });
    }
    modal.querySelectorAll('[data-modal-cerrar]').forEach(function (x) { x.addEventListener('click', cerrarModal); });
    function error(msg, campo) {
        mdError.textContent = msg;
        mdError.hidden = false;
        if (campo) {
            campo.classList.add('is-invalid');
            var f = campo.matches('input, textarea') ? campo : campo.querySelector('input');
            if (f) f.focus();
        }
    }
    function limpiarAlEscribir() {
        mdBody.querySelectorAll('input, textarea').forEach(function (i) {
            var limpiar = function () { var g = i.classList.contains('is-invalid') ? i : i.closest('.is-invalid'); if (g) { g.classList.remove('is-invalid'); mdError.hidden = true; } };
            i.addEventListener('input', limpiar);
            i.addEventListener('change', limpiar);
        });
    }

    function abrirNuevo() {
        md = { tipo: 'tema', archivo: null };
        el('for-md-titulo').textContent = 'Nuevo tema';
        el('for-md-sub').textContent = 'Se publica como administración, a la vista de toda la sede';
        mdBody.innerHTML =
            '<div class="for-field"><span class="for-lbl">Categoría *</span><div class="for-chips" id="for-f-cats" role="radiogroup" aria-label="Categoría">' +
                Object.keys(CAT).map(function (k) { return '<label class="for-chip"><input type="radio" name="for-f-cat" value="' + k + '"><span>' + CAT[k] + '</span></label>'; }).join('') + '</div></div>' +
            '<div class="for-field"><label class="for-lbl" for="for-f-titulo">Título *</label><input class="for-input" id="for-f-titulo" type="text" maxlength="120" autocomplete="off" placeholder="Ej. Horario de la biblioteca en vacaciones"></div>' +
            '<div class="for-field"><label class="for-lbl" for="for-f-texto">Mensaje *</label><textarea class="for-input for-textarea" id="for-f-texto" rows="6" maxlength="' + MAX_TXT + '" placeholder="Escribe el mensaje con el que abres el tema."></textarea><span class="for-contador" id="for-f-contador"></span></div>' +
            '<div class="for-field"><span class="for-lbl">Adjunto <em>opcional · PDF, JPG o PNG de máximo 5 MB</em></span>' +
                '<div class="for-file" id="for-f-file"><input type="file" id="for-f-adjunto" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">' +
                '<div class="for-file-txt" id="for-f-file-txt"></div><label class="for-btn for-btn-ghost for-btn-sm" for="for-f-adjunto">' + ICON.clip + 'Elegir archivo</label>' +
                '<button type="button" class="for-file-quitar" id="for-f-quitar" hidden>Quitar</button></div></div>' +
            '<label class="for-switch"><input type="checkbox" id="for-f-fijar"><span class="for-switch-pista" aria-hidden="true"></span>' +
                '<span class="for-switch-txt"><strong>Fijar arriba</strong><span>Aparece primero en el foro de todos hasta que lo quites.</span></span></label>';
        var txt = el('for-f-texto'), cont = el('for-f-contador'), file = el('for-f-adjunto');
        function contar() { cont.textContent = txt.value.length.toLocaleString('es-CO') + ' / ' + MAX_TXT.toLocaleString('es-CO'); }
        function pintarAdj() {
            el('for-f-file-txt').innerHTML = md.archivo ? '<strong>' + esc(md.archivo.nombre) + '</strong>' + (md.archivo.tipo === 'pdf' ? 'PDF' : 'Imagen') + ' · ' + peso(md.archivo.peso) : 'Ningún archivo.';
            el('for-f-quitar').hidden = !md.archivo;
        }
        txt.addEventListener('input', contar);
        file.addEventListener('change', function () {
            var f = file.files && file.files[0];
            if (!f) return;
            var a = leerArchivo(f);
            if (a.error) { file.value = ''; return error(a.error, el('for-f-file')); }
            el('for-f-file').classList.remove('is-invalid'); mdError.hidden = true;
            md.archivo = a; pintarAdj();
        });
        el('for-f-quitar').addEventListener('click', function () { md.archivo = null; file.value = ''; pintarAdj(); });
        contar(); pintarAdj(); limpiarAlEscribir();
        mdOk.className = 'for-btn for-btn-primary';
        mdOk.textContent = 'Publicar tema';
        abrirModal();
        setTimeout(function () { mdBody.querySelector('input[name="for-f-cat"]').focus(); }, 30);
    }
    function guardarTema() {
        var cat = (mdBody.querySelector('input[name="for-f-cat"]:checked') || {}).value, tit = el('for-f-titulo').value.trim(), txt = el('for-f-texto').value.trim();
        if (!cat) return error('Elige la categoría del tema.', el('for-f-cats'));
        if (tit.length < 5) return error('Escribe un título de al menos 5 letras.', el('for-f-titulo'));
        if (txt.length < 2) return error('Escribe el mensaje.', el('for-f-texto'));
        mdOk.disabled = true; mdOk.textContent = 'Publicando…';
        API.crearTema({ categoria: cat, titulo: tit, texto: txt, adjunto: md.archivo, fijado: el('for-f-fijar').checked }).then(function (j) {
            cerrarModal(); refrescar(); mostrarVista('temas'); toast(j.mensaje); abrirTema(j.tema.id);
        }).catch(function (e) {
            mdOk.disabled = false; mdOk.textContent = 'Publicar tema';
            var k = e.errores && Object.keys(e.errores)[0];
            error(k ? e.errores[k][0] : e.message, k ? el({ categoria: 'for-f-cats', titulo: 'for-f-titulo', texto: 'for-f-texto', adjunto: 'for-f-file' }[k]) : null);
        });
    }

    function abrirOcultar(msgId) {
        var x = todosLosMensajes().find(function (k) { return k.m.id === msgId; });
        if (!x) return;
        md = { tipo: 'ocultar', id: msgId, primero: x.primero };
        el('for-md-titulo').textContent = x.primero ? 'Ocultar tema' : 'Ocultar mensaje';
        el('for-md-sub').textContent = 'De ' + persona(x.m.autor).nombre + ' · ' + cuando(x.m.fecha);
        mdBody.innerHTML = '<div class="for-aviso is-rojo"><div class="for-aviso-txt">' + ICON.ocultar + '<p><strong>' + (x.primero ? 'Se ocultará el tema «' + esc(x.t.titulo) + '» con todas sus respuestas.' : 'Se ocultará este mensaje.') +
                '</strong> Nadie más lo verá en el foro' + (pendientes(x.m).length ? ' y sus reportes quedan atendidos' : '') + '. Si fue un error, se puede restaurar desde Ocultos.</p></div></div>' +
            '<div class="for-field"><label class="for-lbl" for="for-f-motivo">Motivo *</label><textarea class="for-input for-textarea" id="for-f-motivo" maxlength="255" placeholder="Ej. Lenguaje irrespetuoso con los compañeros"></textarea></div>';
        limpiarAlEscribir();
        mdOk.className = 'for-btn for-btn-danger';
        mdOk.textContent = x.primero ? 'Ocultar tema' : 'Ocultar mensaje';
        abrirModal();
        setTimeout(function () { el('for-f-motivo').focus(); }, 30);
    }
    function guardarOcultar() {
        var mEl = el('for-f-motivo'), motivo = mEl.value.trim(), id = md.id;
        if (motivo.length < 5) return error('Escribe el motivo con un poco más de detalle.', mEl);
        mdOk.disabled = true;
        API.ocultar(id, motivo).then(function (j) { cerrarModal(); refrescar(); abrirTema(j.tema.id, id); toast(j.mensaje); })
            .catch(function (e) { mdOk.disabled = false; var k = e.errores && Object.keys(e.errores)[0]; error(k ? e.errores[k][0] : e.message, mEl); });
    }
    mdOk.addEventListener('click', function () {
        if (!md) return;
        mdError.hidden = true;
        if (md.tipo === 'tema') guardarTema(); else guardarOcultar();
    });
    mdBody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'file') { e.preventDefault(); mdOk.click(); }
    });
    document.getElementById('for-btn-nuevo').addEventListener('click', abrirNuevo);

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
    document.getElementById('for-exportar').addEventListener('click', function () {
        var filas = [['Tema', 'Categoría', 'Abierto por', 'Rol', 'Fecha', 'Respuestas', 'Última actividad', 'Estado', 'Fijado', 'Reportes por revisar']]
            .concat(items.map(function (t) {
                var p = persona(t.mensajes[0].autor);
                return [t.titulo, CAT[t.categoria], p.nombre, ROL[p.rol], t.mensajes[0].fecha, respuestas(t), ultimo(t).fecha, t.cerrado ? 'Cerrado' : 'Abierto', t.fijado ? 'Sí' : 'No', reportesTema(t)];
            }));
        var csv = '﻿' + filas.map(function (r) { return r.map(celdaCsv).join(';'); }).join('\r\n');
        var nombre = 'foro-temas-' + HOY + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a');
        a.href = url; a.download = nombre;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        var n = filas.length - 1;
        toast('Se descargó ' + nombre + ' con ' + n + (n === 1 ? ' fila.' : ' filas.'));
    });

    /* Inicializar */
    refrescar();
    try {
        var guardada = localStorage.getItem(VISTA_KEY);
        if (guardada && document.querySelector('.for-vista[data-panel="' + guardada + '"]')) mostrarVista(guardada);
    } catch (e) {}
}());
