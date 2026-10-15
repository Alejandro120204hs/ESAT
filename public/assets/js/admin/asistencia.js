/* ESAT — Admin: Asistencia (solo consulta) */
(function () {
    'use strict';

    var DATA = window.ASI_DATA || { grupos: [], estudiantes: [], docentes: [] };
    var VISTA_KEY = 'esat.admin.asistencia.vista';

    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var DOW_C = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    var ESTADO_LBL = { P: 'Asistió', T: 'Llegó tarde', A: 'Faltó', E: 'Falta con excusa', '-': 'Sin registro de asistencia', '.': 'No estaba en el grupo' };

    var grupoPorId = {}, estPorId = {}, docPorId = {};
    DATA.grupos.forEach(function (g) { grupoPorId[g.id] = g; });
    DATA.estudiantes.forEach(function (e) { estPorId[e.id] = e; });
    DATA.docentes.forEach(function (d) { docPorId[d.id] = d; });

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fecha(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function corta(iso) { var f = fecha(iso); return f.getDate() + ' ' + MES_C[f.getMonth()]; }
    function listaFechas(isos) {
        var t = isos.map(corta);
        return t.length > 1 ? t.slice(0, -1).join(', ') + ' y ' + t[t.length - 1] : (t[0] || '');
    }
    function iniciales(n, a) { return ((n || '').charAt(0) + (a || '').charAt(0)).toUpperCase(); }
    function primerNombre(n) { return (n || '').split(' ')[0]; }
    function fmtTel(t) { return t ? t.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3') : ''; }
    function waLink(tel, texto) { return 'https://wa.me/57' + tel + (texto ? '?text=' + encodeURIComponent(texto) : ''); }
    function tono(p) { return p == null ? 'none' : (p >= 90 ? 'ok' : (p >= 80 ? 'warn' : 'bad')); }
    function pctTxt(p) { return p == null ? '—' : p + ' %'; }
    function situacionTag(s) {
        return {
            bien:      '<span class="app-status-tag app-status-active">Asiste bien</span>',
            vigilar:   '<span class="app-status-tag app-status-warn">Por vigilar</span>',
            fallas:    '<span class="app-status-tag app-status-danger">Bajo el 80 %</span>',
            sin_datos: '<span class="app-status-tag app-status-muted">Sin registros</span>'
        }[s] || '';
    }
    var SALIDA = { aplazado: 'Aplazado', retirado: 'Retirado', graduado: 'Graduado' };
    function salidaTxt(e) { return (SALIDA[e.estado] || 'Fuera del grupo') + (e.estado_desde ? ' desde el ' + e.estado_desde : ''); }
    function celda(l, titulo) {
        var cls = l === '-' ? 'sin' : (l === '.' ? 'na' : l);
        var txt = (l === '-' || l === '.') ? '' : l;
        return '<span class="asi-celda asi-c-' + cls + '" title="' + esc(titulo) + '">' + (l === '.' ? '·' : txt) + '</span>';
    }
    var ICON = {
        wa: '<svg viewBox="0 0 24 24"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        tabla: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>'
    };

    var toastEl = document.getElementById('asi-toast');
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        clearTimeout(toast.t);
        toast.t = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 4500);
    }

    /* ── Combobox de filtros (mismo patrón que Docentes) ── */
    function initCombobox(root) {
        var trigger = root.querySelector('[data-combobox-trigger]');
        var label   = root.querySelector('[data-combobox-label]');
        var panel   = root.querySelector('[data-combobox-panel]');
        var list    = root.querySelector('[data-combobox-list]');
        var hidden  = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;
        var esAbsoluto = root.dataset.position === 'absolute';

        function posicionar() {
            var rect = trigger.getBoundingClientRect();
            panel.style.left = rect.left + 'px';
            panel.style.width = rect.width + 'px';
            panel.style.top = (rect.bottom + 6) + 'px';
        }
        /* No se recoloca con el scroll de su propia lista */
        function alScroll(e) { if (!panel.contains(e.target)) posicionar(); }
        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (!esAbsoluto) {
                posicionar();
                window.addEventListener('scroll', alScroll, true);
                window.addEventListener('resize', posicionar);
            }
        }
        function cerrar() {
            panel.hidden = true;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (!esAbsoluto) {
                window.removeEventListener('scroll', alScroll, true);
                window.removeEventListener('resize', posicionar);
            }
        }
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
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === String(valor));
            });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        root.value = function () { return hidden.value; };
    }
    function filtroCombo(id, opciones, alCambiar) {
        var root = document.getElementById(id);
        initCombobox(root);
        root.setOptions(opciones);
        root.setValue(opciones[0].value, opciones[0].label, { silent: true });
        root.querySelector('[data-combobox-value]').addEventListener('change', alCambiar);
        return root;
    }

    /* ── Tabla con filtro y paginación (una por vista) ── */
    function Tabla(cfg) {
        var tbody = document.querySelector('#' + cfg.tabla + ' tbody');
        var countEl = document.getElementById(cfg.count);
        var emptyEl = document.getElementById(cfg.empty);
        var pagEl = document.getElementById(cfg.pag);
        var PAGE = 10, pagina = 1, filtradas = [];

        function filas() { return Array.prototype.slice.call(tbody.querySelectorAll('.asi-row')); }
        function aplicar() {
            var todas = filas();
            if (cfg.ordenar) todas.sort(cfg.ordenar).forEach(function (r) { tbody.appendChild(r); });
            filtradas = todas.filter(cfg.filtro);
            pagina = 1;
            pintar();
        }
        function pintar() {
            var total = filtradas.length, paginas = Math.max(1, Math.ceil(total / PAGE));
            var ini = (pagina - 1) * PAGE;
            filas().forEach(function (r) { r.classList.add('is-hidden'); });
            filtradas.slice(ini, ini + PAGE).forEach(function (r) { r.classList.remove('is-hidden'); });
            countEl.textContent = total + ' ' + (total === 1 ? cfg.nombre[0] : cfg.nombre[1]);
            emptyEl.hidden = total > 0;

            pagEl.hidden = paginas <= 1;
            if (paginas <= 1) return;
            var html = '<button type="button" class="asi-pg-btn" data-pg="prev"' + (pagina === 1 ? ' disabled' : '') + '><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Anterior</button>';
            rango(pagina, paginas).forEach(function (p) {
                html += p === '…' ? '<span class="asi-pg-ellipsis">…</span>'
                    : '<button type="button" class="asi-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            });
            html += '<button type="button" class="asi-pg-btn" data-pg="next"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg></button>';
            pagEl.innerHTML = html;
        }
        function rango(cur, total) {
            if (total <= 7) { var r = []; for (var i = 1; i <= total; i++) r.push(i); return r; }
            var out = [1];
            if (cur > 3) out.push('…');
            for (var j = Math.max(2, cur - 1); j <= Math.min(total - 1, cur + 1); j++) out.push(j);
            if (cur < total - 2) out.push('…');
            out.push(total);
            return out;
        }
        pagEl.addEventListener('click', function (e) {
            var b = e.target.closest('[data-pg]');
            if (!b || b.disabled) return;
            var paginas = Math.ceil(filtradas.length / PAGE);
            var v = b.dataset.pg;
            pagina = v === 'prev' ? Math.max(1, pagina - 1) : (v === 'next' ? Math.min(paginas, pagina + 1) : +v);
            pintar();
            tbody.closest('.asi-table-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        tbody.addEventListener('click', function (e) {
            var row = e.target.closest('.asi-row');
            if (row) cfg.abrir(+row.dataset.id);
        });
        return { aplicar: aplicar, filtradas: function () { return filtradas; } };
    }

    function valor(id) { return document.getElementById(id).value; }
    function busca(id) { return (document.getElementById(id).value || '').toLowerCase().trim(); }

    /* Por grupo */
    var tGrupos = Tabla({
        tabla: 'asi-g-table', count: 'asi-g-count', empty: 'asi-g-empty', pag: 'asi-g-pagination', nombre: ['grupo', 'grupos'],
        abrir: function (id) { abrirGrupo(id); },
        filtro: function (r) {
            var q = busca('asi-g-search'), j = valor('asi-g-jornada'), s = valor('asi-g-situacion');
            var pct = r.dataset.pct === '' ? null : +r.dataset.pct;
            return (!q || r.dataset.buscar.indexOf(q) !== -1) &&
                (!j || r.dataset.jornada === j) &&
                (!s || (s === 'sin' && +r.dataset.sin > 0) || (s === 'bajo' && pct !== null && pct < 80));
        }
    });
    var jornadas = JSON.parse(document.getElementById('asi-g-cb-jornada').dataset.options || '[]');
    filtroCombo('asi-g-cb-jornada', [{ value: '', label: 'Todas las jornadas' }].concat(jornadas.map(function (j) { return { value: j, label: j }; })), tGrupos.aplicar);
    filtroCombo('asi-g-cb-situacion', [
        { value: '', label: 'Todas las situaciones' },
        { value: 'sin', label: 'Con clases sin registrar' },
        { value: 'bajo', label: 'Asistencia bajo el 80 %' }
    ], tGrupos.aplicar);
    document.getElementById('asi-g-search').addEventListener('input', tGrupos.aplicar);

    /* Por estudiante */
    var ORDEN = {
        faltas: function (a, b) { return (+b.dataset.faltas - +a.dataset.faltas) || (+a.dataset.pct - +b.dataset.pct); },
        pct:    function (a, b) { return (+a.dataset.pct - +b.dataset.pct) || (+b.dataset.faltas - +a.dataset.faltas); },
        nombre: function (a, b) { return a.dataset.nombre.localeCompare(b.dataset.nombre, 'es'); }
    };
    var tEst = Tabla({
        tabla: 'asi-e-table', count: 'asi-e-count', empty: 'asi-e-empty', pag: 'asi-e-pagination', nombre: ['estudiante', 'estudiantes'],
        abrir: function (id) { abrirEstudiante(id); },
        ordenar: function (a, b) { return ORDEN[valor('asi-e-orden') || 'faltas'](a, b); },
        filtro: function (r) {
            var q = busca('asi-e-search'), g = valor('asi-e-grupo'), s = valor('asi-e-situacion');
            return (!q || r.dataset.buscar.indexOf(q) !== -1) && (!g || r.dataset.grupo === g) && (!s || r.dataset.situacion === s);
        }
    });
    filtroCombo('asi-e-cb-grupo', [{ value: '', label: 'Todos los grupos' }].concat(DATA.grupos.map(function (g) {
        return { value: String(g.id), label: g.programa + ' · ' + g.grupo };
    })), tEst.aplicar);
    filtroCombo('asi-e-cb-situacion', [
        { value: '', label: 'Todas las situaciones' },
        { value: 'bien', label: 'Asiste bien (90 % o más)' },
        { value: 'vigilar', label: 'Por vigilar (80 a 89 %)' },
        { value: 'fallas', label: 'Bajo el 80 %' }
    ], tEst.aplicar);
    filtroCombo('asi-e-cb-orden', [
        { value: 'faltas', label: 'Más fallas primero' },
        { value: 'pct', label: 'Menor asistencia primero' },
        { value: 'nombre', label: 'Por apellido (A–Z)' }
    ], tEst.aplicar);
    document.getElementById('asi-e-search').addEventListener('input', tEst.aplicar);

    /* Por docente */
    var tDoc = Tabla({
        tabla: 'asi-d-table', count: 'asi-d-count', empty: 'asi-d-empty', pag: 'asi-d-pagination', nombre: ['docente', 'docentes'],
        abrir: function (id) { abrirDocente(id); },
        ordenar: function (a, b) { return +b.dataset.sin - +a.dataset.sin; },
        filtro: function (r) {
            var q = busca('asi-d-search'), s = valor('asi-d-situacion');
            return (!q || r.dataset.buscar.indexOf(q) !== -1) &&
                (!s || (s === 'sin' && +r.dataset.sin > 0) || (s === 'aldia' && +r.dataset.sin === 0));
        }
    });
    filtroCombo('asi-d-cb-situacion', [
        { value: '', label: 'Todos los docentes' },
        { value: 'sin', label: 'Con clases sin registrar' },
        { value: 'aldia', label: 'Al día' }
    ], tDoc.aplicar);
    document.getElementById('asi-d-search').addEventListener('input', tDoc.aplicar);

    /* ── Pestañas de vista (se recuerda la última) ── */
    var vistaBtns = document.querySelectorAll('.asi-vista-btn');
    function mostrarVista(v) {
        vistaBtns.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.asi-vista').forEach(function (s) { s.hidden = s.dataset.panel !== v; });
        try { localStorage.setItem(VISTA_KEY, v); } catch (e) {}
    }
    vistaBtns.forEach(function (b) { b.addEventListener('click', function () { mostrarVista(b.dataset.vista); }); });
    try {
        var guardada = localStorage.getItem(VISTA_KEY);
        if (guardada && document.querySelector('.asi-vista[data-panel="' + guardada + '"]')) mostrarVista(guardada);
    } catch (e) {}

    /* ── Cajón ────────────────────────────────── */
    var drawer = document.getElementById('asi-drawer');
    var dwBody = document.getElementById('asi-dw-body');
    var dwAvatar = document.getElementById('asi-dw-avatar');
    var btnVolver = document.getElementById('asi-dw-volver');
    var pila = [], actual = null, ultimoFoco = null;

    function abrirCajon(vista, desdeCajon) {
        if (!drawer.classList.contains('is-open')) ultimoFoco = document.activeElement;
        if (desdeCajon && actual) pila.push(actual);
        else if (!desdeCajon) pila = [];
        actual = vista;
        btnVolver.hidden = pila.length === 0;
        drawer.classList.toggle('is-ancho', vista.tipo === 'grupo');
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        dwBody.scrollTop = 0;
        setTimeout(function () { drawer.querySelector('[data-cerrar].asi-close-btn').focus({ preventScroll: true }); }, 30);
    }
    function cerrarCajon() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        pila = []; actual = null;
        if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus({ preventScroll: true });
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarCajon); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarCajon(); });
    btnVolver.addEventListener('click', function () {
        var prev = pila.pop();
        if (!prev) return;
        actual = null;
        ({ grupo: abrirGrupo, estudiante: abrirEstudiante, docente: abrirDocente })[prev.tipo](prev.id, { volviendo: true, mes: prev.mes });
        btnVolver.hidden = pila.length === 0;
    });
    function cabecera(avatarTxt, sigla, titulo, sub, badges) {
        dwAvatar.textContent = avatarTxt;
        dwAvatar.className = 'asi-drawer-avatar asi-avatar asi-esc-' + sigla;
        document.getElementById('asi-dw-titulo').textContent = titulo;
        document.getElementById('asi-dw-sub').textContent = sub;
        document.getElementById('asi-dw-badges').innerHTML = badges;
    }
    function dato(lbl, val) { return '<div class="asi-dato"><span class="asi-dato-lbl">' + lbl + '</span><span class="asi-dato-val">' + val + '</span></div>'; }
    function datoTxt(lbl, txt) { return '<div class="asi-dato"><span class="asi-dato-lbl">' + lbl + '</span><span class="asi-dato-txt">' + esc(txt) + '</span></div>'; }
    function sinTag(n, sufijo) { return n ? '<span class="asi-sin-tag">' + n + ' ' + (sufijo || (n === 1 ? 'clase sin registrar' : 'clases sin registrar')) + '</span>' : ''; }

    /* Mensaje de WhatsApp para recordarle al docente las clases sin registrar */
    function textoRecordatorio(d) {
        var partes = d.grupos.map(function (gid) { return grupoPorId[gid]; })
            .filter(function (g) { return g.sin_tomar.length; })
            .map(function (g) { return g.programa + ' ' + g.grupo + ' (' + listaFechas(g.sin_tomar) + ')'; });
        return 'Hola ' + primerNombre(d.nombres) + ', te escribimos de ESAT. Tienes pendiente registrar la asistencia de: ' + partes.join('; ') + '. ¡Gracias!';
    }

    /* ── Planilla del grupo ───────────────────── */
    function abrirGrupo(id, opts) {
        var g = grupoPorId[id];
        if (!g) return;
        opts = opts || {};
        var d = docPorId[g.docente_id];
        var meses = [];
        g.fechas.forEach(function (iso) { var k = iso.slice(0, 7); if (meses.indexOf(k) === -1) meses.push(k); });
        var mes = opts.mes || meses[meses.length - 1] || 'todo';

        cabecera(g.grupo.slice(-1), g.sigla, g.programa + ' · ' + g.grupo, g.jornada + ' · Docente: ' + g.docente,
            '<span class="app-status-tag ' + ({ ok: 'app-status-active', warn: 'app-status-warn', bad: 'app-status-danger', none: 'app-status-muted' })[tono(g.pct)] + '">' + pctTxt(g.pct) + ' de asistencia</span>' + sinTag(g.sin_tomar.length));

        var html = '<div class="asi-datos">' +
            dato('Asistencia', pctTxt(g.pct)) +
            dato('Registradas', g.tomadas + ' <small>de ' + g.clases + '</small>') +
            dato('Estudiantes', g.activos + (g.bajo80 ? ' <small>· ' + g.bajo80 + ' bajo el 80 %</small>' : '')) +
            datoTxt('Horario', g.horario.replace('  ', '\n')) +
            '</div>';

        if (g.sin_tomar.length && d) {
            html += '<div class="asi-aviso"><div class="asi-aviso-txt">' + ICON.reloj + '<p><strong>' + esc(d.nombres) + '</strong> no ha registrado ' +
                (g.sin_tomar.length === 1 ? 'la clase del ' : 'las clases del ') + esc(listaFechas(g.sin_tomar)) + '. Esas fechas no cuentan en el porcentaje.</p></div>' +
                '<a class="asi-btn asi-btn-wa asi-btn-sm" href="' + waLink(d.telefono, textoRecordatorio(d)) + '" target="_blank" rel="noopener">' + ICON.wa + 'Recordarle</a></div>';
        }

        html += '<div><div class="asi-seccion-fila"><div class="asi-seccion-titulo">Planilla de asistencia</div>' +
            '<div class="asi-meses" role="tablist" aria-label="Mes">' +
            meses.map(function (k) {
                var m = +k.slice(5, 7) - 1;
                return '<button type="button" class="asi-mes' + (k === mes ? ' is-active' : '') + '" data-mes="' + k + '" role="tab" aria-selected="' + (k === mes) + '">' + MES_C[m] + '</button>';
            }).join('') +
            '<button type="button" class="asi-mes' + (mes === 'todo' ? ' is-active' : '') + '" data-mes="todo" role="tab" aria-selected="' + (mes === 'todo') + '">Todo</button>' +
            '</div></div>' +
            '<div class="asi-planilla-wrap" id="asi-planilla"></div>' +
            '<div class="asi-leyenda" style="margin-top:12px">' +
            '<span>' + celda('P', '') + 'Asistió</span><span>' + celda('T', '') + 'Tarde (cuenta como asistió)</span>' +
            '<span>' + celda('A', '') + 'Faltó</span><span>' + celda('E', '') + 'Excusa (no cuenta como falla)</span>' +
            '<span>' + celda('-', '') + 'Sin registrar</span></div></div>';

        dwBody.innerHTML = html;
        if (!opts.volviendo) abrirCajon({ tipo: 'grupo', id: id, mes: mes }, !!opts.desdeCajon);
        else { actual = { tipo: 'grupo', id: id, mes: mes }; drawer.classList.add('is-ancho'); }

        dwBody.querySelectorAll('.asi-mes').forEach(function (b) {
            b.addEventListener('click', function () {
                dwBody.querySelectorAll('.asi-mes').forEach(function (x) {
                    x.classList.toggle('is-active', x === b);
                    x.setAttribute('aria-selected', x === b ? 'true' : 'false');
                });
                actual.mes = b.dataset.mes;
                pintarPlanilla(g, b.dataset.mes);
            });
        });
        pintarPlanilla(g, mes);
        // El mes elegido queda a la vista aunque la fila de meses no quepa
        var activo = dwBody.querySelector('.asi-mes.is-active');
        if (activo) activo.parentNode.scrollLeft = activo.offsetLeft - activo.parentNode.clientWidth + activo.offsetWidth + 40;
    }

    function pintarPlanilla(g, mes) {
        var cols = [];
        g.fechas.forEach(function (iso, i) { if (mes === 'todo' || iso.slice(0, 7) === mes) cols.push(i); });
        var personas = DATA.estudiantes.filter(function (e) { return e.grupo_id === g.id; })
            .sort(function (a, b) { return (a.estado !== 'activo') - (b.estado !== 'activo') || a.apellidos.localeCompare(b.apellidos, 'es'); });

        var html = '<table class="asi-planilla"><thead><tr><th class="asi-pl-nombre" scope="col">Estudiante</th>';
        cols.forEach(function (i) {
            var iso = g.fechas[i], f = fecha(iso), sin = g.sin_tomar.indexOf(iso) !== -1;
            html += '<th class="asi-pl-fecha' + (sin ? ' is-sin' : '') + '" scope="col" title="' + (sin ? 'Sin registrar · ' : '') + esc(corta(iso)) + '"><b>' + f.getDate() + '</b><i>' + DOW_C[f.getDay()] + '</i></th>';
        });
        html += '<th class="asi-pl-pct" scope="col">Asist.</th></tr></thead><tbody>';

        personas.forEach(function (e) {
            var P = 0, T = 0, A = 0;
            var celdas = cols.map(function (i) {
                var l = e.cod.charAt(i);
                if (l === 'P') P++; else if (l === 'T') T++; else if (l === 'A') A++;
                var titulo = corta(g.fechas[i]) + ' · ' + ESTADO_LBL[l] + (l === 'E' && e.motivos[i] ? ': ' + e.motivos[i] : '');
                return '<td class="asi-pl-celda">' + celda(l, titulo) + '</td>';
            }).join('');
            var base = P + T + A, p = base ? Math.round((P + T) * 100 / base) : null;
            var aplazado = e.estado !== 'activo';
            html += '<tr' + (aplazado ? ' class="is-aplazado"' : '') + '><th class="asi-pl-nombre" scope="row">' +
                '<button type="button" class="asi-pl-persona" data-est="' + e.id + '" title="Ver historial de ' + esc(e.nombres + ' ' + e.apellidos) + '">' + esc(e.apellidos + ', ' + primerNombre(e.nombres)) + '</button>' +
                (aplazado ? '<span class="asi-pl-nota">' + esc(salidaTxt(e)) + '</span>' : '') +
                '</th>' + celdas + '<td class="asi-pl-pct asi-tono-' + (aplazado ? 'none' : tono(p)) + '">' + pctTxt(p) + '</td></tr>';
        });
        html += '</tbody></table>';
        if (!cols.length) html = '<p class="asi-nota-vacia">Este grupo todavía no ha tenido clases.</p>';

        var wrap = document.getElementById('asi-planilla');
        wrap.innerHTML = html;
        wrap.scrollLeft = wrap.scrollWidth; // lo más reciente a la vista
        wrap.querySelectorAll('[data-est]').forEach(function (b) {
            b.addEventListener('click', function () { abrirEstudiante(+b.dataset.est, { desdeCajon: true }); });
        });
    }

    /* ── Historial del estudiante ─────────────── */
    function abrirEstudiante(id, opts) {
        var e = estPorId[id];
        if (!e) return;
        opts = opts || {};
        var g = grupoPorId[e.grupo_id];
        var aplazado = e.estado !== 'activo';
        var registradas = e.P + e.T + e.A + e.E;

        cabecera(iniciales(e.nombres, e.apellidos), g.sigla, e.nombres + ' ' + e.apellidos,
            e.tipo_doc + ' ' + e.documento + ' · ' + g.programa + ' · ' + g.grupo,
            (aplazado ? '<span class="app-status-tag app-status-muted">' + esc(salidaTxt(e)) + '</span>' : situacionTag(e.situacion)) +
            (e.racha >= 2 ? '<span class="asi-racha-tag">' + e.racha + ' faltas seguidas</span>' : ''));

        var html = '<div class="asi-datos">' +
            dato('Asistencia', '<span class="asi-tono-' + tono(e.pct) + '">' + pctTxt(e.pct) + '</span>') +
            dato('Clases', registradas) +
            dato('Faltas', e.A) +
            dato('Excusas', e.E) + '</div>';

        if (e.racha >= 2 && !aplazado) {
            html += '<div class="asi-aviso is-rojo"><div class="asi-aviso-txt">' + ICON.alerta + '<p>Lleva <strong>' + e.racha + ' clases seguidas sin venir</strong>; la última fue el ' + esc(e.ultima_falla_txt) + '.</p></div>' +
                '<a class="asi-btn asi-btn-wa asi-btn-sm" href="' + waLink(e.acudiente ? e.acudiente.telefono : e.telefono) + '" target="_blank" rel="noopener">' + ICON.wa + (e.acudiente ? 'Escribir al acudiente' : 'Escribirle') + '</a></div>';
        }

        if (registradas) {
            var parte = function (n) { return (n * 100 / registradas).toFixed(2) + '%'; };
            html += '<div><div class="asi-seccion-titulo">Cómo ha asistido</div>' +
                '<div class="asi-reparto" role="img" aria-label="Asistió ' + e.P + ', tarde ' + e.T + ', faltó ' + e.A + ', excusas ' + e.E + '">' +
                (e.P ? '<span class="r-P" style="width:' + parte(e.P) + '"></span>' : '') +
                (e.T ? '<span class="r-T" style="width:' + parte(e.T) + '"></span>' : '') +
                (e.A ? '<span class="r-A" style="width:' + parte(e.A) + '"></span>' : '') +
                (e.E ? '<span class="r-E" style="width:' + parte(e.E) + '"></span>' : '') + '</div>' +
                '<div class="asi-reparto-ley">' +
                '<span><i class="r-P" style="background:#4C9A45"></i><b>' + e.P + '</b>asistió</span>' +
                '<span><i style="background:#E0A526"></i><b>' + e.T + '</b>tarde</span>' +
                '<span><i style="background:#D93636"></i><b>' + e.A + '</b>' + (e.A === 1 ? 'falta' : 'faltas') + '</span>' +
                '<span><i style="background:#5B8DEF"></i><b>' + e.E + '</b>' + (e.E === 1 ? 'excusa' : 'excusas') + '</span>' +
                '</div></div>';
        }

        html += '<div><div class="asi-seccion-titulo">Clase por clase</div><div class="asi-cals">' + calendarios(e, g) + '</div>' +
            '<div class="asi-leyenda" style="margin-top:12px"><span>' + celda('P', '') + 'Asistió</span><span>' + celda('T', '') + 'Tarde</span>' +
            '<span>' + celda('A', '') + 'Faltó</span><span>' + celda('E', '') + 'Excusa</span><span>' + celda('-', '') + 'Sin registrar</span></div></div>';

        var excusas = Object.keys(e.motivos).map(Number).sort(function (a, b) { return b - a; });
        html += '<div><div class="asi-seccion-titulo">Excusas</div>' + (excusas.length
            ? '<ul class="asi-lista">' + excusas.map(function (i) {
                return '<li><div><div class="asi-lista-fecha">' + esc(corta(g.fechas[i])) + '</div><div class="asi-lista-sub">' + esc(e.motivos[i]) + '</div></div>' + celda('E', 'Excusa') + '</li>';
            }).join('') + '</ul>'
            : '<p class="asi-nota-vacia">No ha presentado excusas.</p>') + '</div>';

        html += '<div><div class="asi-seccion-titulo">Contacto</div><div class="asi-contactos">' +
            contacto(e.nombres + ' ' + e.apellidos, 'Estudiante · ' + fmtTel(e.telefono), e.telefono) +
            (e.acudiente ? contacto(e.acudiente.nombre, 'Acudiente (' + e.acudiente.parentesco.toLowerCase() + ') · ' + fmtTel(e.acudiente.telefono), e.acudiente.telefono) : '') +
            '</div></div>';

        html += '<div><button type="button" class="asi-btn asi-btn-ghost asi-btn-sm" id="asi-ver-grupo">' + ICON.tabla + 'Ver planilla del grupo</button></div>';

        dwBody.innerHTML = html;
        if (!opts.volviendo) abrirCajon({ tipo: 'estudiante', id: id }, !!opts.desdeCajon);
        else { actual = { tipo: 'estudiante', id: id }; drawer.classList.remove('is-ancho'); }
        document.getElementById('asi-ver-grupo').addEventListener('click', function () { abrirGrupo(g.id, { desdeCajon: true }); });
    }
    function contacto(nombre, sub, tel) {
        return '<div class="asi-contacto"><div><div class="asi-contacto-nombre">' + esc(nombre) + '</div><div class="asi-contacto-sub">' + esc(sub) + '</div></div>' +
            '<a class="asi-btn asi-btn-wa asi-btn-sm" href="' + waLink(tel) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(nombre) + '">' + ICON.wa + 'WhatsApp</a></div>';
    }

    /* Un calendario por mes, del más reciente al más viejo; los días de clase llevan el color de lo que pasó */
    function calendarios(e, g) {
        var porDia = {};
        g.fechas.forEach(function (iso, i) { porDia[iso] = i; });
        var meses = [];
        g.fechas.forEach(function (iso) { var k = iso.slice(0, 7); if (meses.indexOf(k) === -1) meses.push(k); });
        if (!meses.length) return '<p class="asi-nota-vacia">El grupo todavía no ha tenido clases.</p>';
        return meses.reverse().map(function (k) {
            var y = +k.slice(0, 4), m = +k.slice(5, 7) - 1;
            var primero = new Date(y, m, 1), dias = new Date(y, m + 1, 0).getDate();
            var desfase = (primero.getDay() + 6) % 7; // lunes primero
            var faltas = 0;
            var celdas = '';
            for (var b = 0; b < desfase; b++) celdas += '<span></span>';
            for (var dd = 1; dd <= dias; dd++) {
                var iso = y + '-' + (m < 9 ? '0' : '') + (m + 1) + '-' + (dd < 10 ? '0' : '') + dd;
                var i = porDia[iso], cls = '', titulo = '';
                if (i !== undefined) {
                    var l = e.cod.charAt(i);
                    if (l === 'A') faltas++;
                    cls = l === '-' ? ' asi-c-sin' : (l === '.' ? '' : ' asi-c-' + l);
                    titulo = ' title="' + esc(corta(iso) + ' · ' + ESTADO_LBL[l] + (l === 'E' && e.motivos[i] ? ': ' + e.motivos[i] : '')) + '"';
                }
                if (iso === DATA.hoy) cls += ' is-hoy';
                celdas += '<span class="asi-cal-dia' + cls + '"' + titulo + '>' + dd + '</span>';
            }
            return '<div class="asi-cal"><div class="asi-cal-mes">' + MESES[m] + ' ' + y +
                '<small>' + (faltas ? faltas + (faltas === 1 ? ' falta' : ' faltas') : 'sin faltas') + '</small></div>' +
                '<div class="asi-cal-grid">' + ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map(function (x) { return '<span class="asi-cal-dow">' + x + '</span>'; }).join('') + celdas + '</div></div>';
        }).join('');
    }

    /* ── Docente ──────────────────────────────── */
    function abrirDocente(id, opts) {
        var d = docPorId[id];
        if (!d) return;
        opts = opts || {};
        cabecera(iniciales(d.nombres, d.apellidos), d.sigla, d.nombres + ' ' + d.apellidos, 'CC ' + d.documento + ' · ' + d.escuela,
            d.sin_tomar ? sinTag(d.sin_tomar) : '<span class="app-status-tag app-status-active">Al día con la asistencia</span>');

        var html = '<div class="asi-datos">' +
            dato('Grupos', d.grupos.length) +
            dato('Registradas', d.tomadas + ' <small>de ' + d.clases + '</small>') +
            dato('Sin registrar', d.sin_tomar) +
            datoTxt('Último registro', d.ultima_txt || 'Todavía no registra') + '</div>';

        if (d.sin_tomar) {
            html += '<div class="asi-aviso"><div class="asi-aviso-txt">' + ICON.reloj + '<p>Tiene <strong>' + d.sin_tomar + (d.sin_tomar === 1 ? ' clase' : ' clases') + ' sin registrar</strong>. Mientras no las registre, esas fechas no cuentan en la asistencia de sus estudiantes.</p></div>' +
                '<a class="asi-btn asi-btn-wa asi-btn-sm" href="' + waLink(d.telefono, textoRecordatorio(d)) + '" target="_blank" rel="noopener">' + ICON.wa + 'Recordarle por WhatsApp</a></div>';
        }

        html += '<div><div class="asi-seccion-titulo">Grupos a cargo</div><ul class="asi-lista">' + d.grupos.map(function (gid) {
            var g = grupoPorId[gid];
            return '<li><div style="min-width:0">' +
                '<button type="button" class="asi-lista-btn" data-grupo="' + g.id + '">' + esc(g.programa + ' · ' + g.grupo) + '</button>' +
                '<div class="asi-lista-sub">' + esc(g.horario) + ' · ' + esc(g.jornada) + '</div>' +
                (g.sin_tomar.length ? '<div class="asi-fechas-sin">' + g.sin_tomar.map(function (iso) { return '<span class="asi-fecha-chip">' + esc(corta(iso)) + '</span>'; }).join('') + '</div>' : '') +
                '</div><div style="text-align:right;white-space:nowrap"><span class="asi-num">' + g.tomadas + '</span><span class="asi-de"> de ' + g.clases + '</span>' +
                '<div class="asi-lista-sub asi-tono-' + tono(g.pct) + '" style="font-weight:700">' + pctTxt(g.pct) + ' asistencia</div></div></li>';
        }).join('') + '</ul></div>';

        html += '<div><div class="asi-seccion-titulo">Contacto</div><div class="asi-contactos">' +
            contacto(d.nombres + ' ' + d.apellidos, 'Docente · ' + fmtTel(d.telefono), d.telefono) + '</div></div>';

        dwBody.innerHTML = html;
        if (!opts.volviendo) abrirCajon({ tipo: 'docente', id: id }, !!opts.desdeCajon);
        else { actual = { tipo: 'docente', id: id }; drawer.classList.remove('is-ancho'); }
        dwBody.querySelectorAll('[data-grupo]').forEach(function (b) {
            b.addEventListener('click', function () { abrirGrupo(+b.dataset.grupo, { desdeCajon: true }); });
        });
    }

    /* ── Exportar lo que se ve con los filtros (CSV con ; y BOM para las tildes) ── */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    var SITUACION = { bien: 'Asiste bien', vigilar: 'Por vigilar', fallas: 'Bajo el 80 %', sin_datos: 'Sin registros' };
    var EXPORT = {
        grupos: function () {
            return [['Programa', 'Grupo', 'Escuela', 'Jornada', 'Horario', 'Docente', 'Clases dictadas', 'Clases registradas', 'Fechas sin registrar', 'Asistencia %', 'Estudiantes', 'Bajo el 80 %']]
                .concat(tGrupos.filtradas().map(function (r) {
                    var g = grupoPorId[r.dataset.id];
                    return [g.programa, g.grupo, g.escuela, g.jornada, g.horario.replace('  ', ' '), g.docente, g.clases, g.tomadas, g.sin_tomar.map(corta).join(', '), g.pct, g.activos, g.bajo80];
                }));
        },
        estudiantes: function () {
            return [['Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Programa', 'Grupo', 'Asistencia %', 'Situación', 'Asistió', 'Tardes', 'Faltas', 'Excusas', 'Faltas seguidas', 'Última falta', 'Celular']]
                .concat(tEst.filtradas().map(function (r) {
                    var e = estPorId[r.dataset.id], g = grupoPorId[e.grupo_id];
                    return [e.nombres, e.apellidos, e.tipo_doc, e.documento, g.programa, g.grupo, e.pct, SITUACION[e.situacion], e.P, e.T, e.A, e.E, e.racha, e.ultima_falla_txt || '', e.telefono];
                }));
        },
        docentes: function () {
            return [['Nombres', 'Apellidos', 'Cédula', 'Escuela', 'Grupos', 'Clases dictadas', 'Clases registradas', 'Sin registrar', 'Último registro', 'Celular']]
                .concat(tDoc.filtradas().map(function (r) {
                    var d = docPorId[r.dataset.id];
                    return [d.nombres, d.apellidos, d.documento, d.escuela, d.grupos.map(function (gid) { return grupoPorId[gid].programa + ' ' + grupoPorId[gid].grupo; }).join(', '),
                        d.clases, d.tomadas, d.sin_tomar, d.ultima_txt || '', d.telefono];
                }));
        }
    };
    document.querySelectorAll('[data-exportar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var vista = btn.dataset.exportar;
            var filas = EXPORT[vista]();
            var csv = '﻿' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
            var hoy = new Date(), dd = function (n) { return (n < 10 ? '0' : '') + n; };
            var nombre = 'asistencia-' + vista + '-' + hoy.getFullYear() + '-' + dd(hoy.getMonth() + 1) + '-' + dd(hoy.getDate()) + '.csv';
            var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            var a = document.createElement('a');
            a.href = url; a.download = nombre;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            var n = filas.length - 1;
            toast('Se descargó ' + nombre + ' con ' + n + (n === 1 ? ' fila.' : ' filas.'));
        });
    });

    /* Inicializar */
    tGrupos.aplicar();
    tEst.aplicar();
    tDoc.aplicar();
}());
