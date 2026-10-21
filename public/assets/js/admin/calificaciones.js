/* ESAT — Admin: Calificaciones (solo consulta: la nota la pone el docente) */
(function () {
    'use strict';

    var DATA = window.CAL_DATA || { grupos: [], estudiantes: [] };
    var VISTA_KEY = 'esat.admin.calificaciones.vista';

    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var ESTADO_LBL = {
        aprobado: 'Aprobado', habilito: 'Aprobado en habilitación', por_habilitar: 'Perdido · puede habilitar',
        reprobado: 'Perdió la habilitación', sin_nota: 'Sin nota del docente', en_curso: 'En curso', por_iniciar: 'Por cursar',
        no_cursado: 'No lo cursó en este grupo'
    };
    var ESTADO_TAG = {
        aprobado: 'app-status-active', habilito: 'app-status-active', por_habilitar: 'app-status-warn',
        reprobado: 'app-status-danger', sin_nota: 'app-status-warn', en_curso: 'app-status-muted', por_iniciar: 'app-status-muted',
        no_cursado: 'app-status-muted'
    };
    var SITUACION = {
        listo:     ['app-status-active', 'Listo para graduar'],
        graduado:  ['app-status-active', 'Graduado'],
        al_dia:    ['app-status-muted', 'Al día'],
        habilitar: ['app-status-warn', 'Por habilitar'],
        reprobado: ['app-status-danger', 'Perdió la habilitación'],
        sin_notas: ['app-status-muted', 'Sin notas aún']
    };

    var grupoPorId = {}, estPorId = {};
    DATA.grupos.forEach(function (g) { grupoPorId[g.id] = g; });
    DATA.estudiantes.forEach(function (e) { estPorId[e.id] = e; });

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fmt(v) { return v == null ? '—' : v.toFixed(1).replace('.', ','); }
    function tono(v) { return v == null ? 'none' : (v >= 4 ? 'ok' : (v >= 3 ? 'warn' : 'bad')); }
    function iniciales(n, a) { return ((n || '').charAt(0) + (a || '').charAt(0)).toUpperCase(); }
    function primerNombre(n) { return (n || '').split(' ')[0]; }
    function fmtTel(t) { return t ? t.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3') : ''; }
    function waLink(tel, texto) { return 'https://wa.me/57' + tel + (texto ? '?text=' + encodeURIComponent(texto) : ''); }
    function lista(t) { return t.length > 1 ? t.slice(0, -1).join(', ') + ' y ' + t[t.length - 1] : (t[0] || ''); }
    function final(x) { return x == null ? null : (x.h != null ? x.h : x.n); }
    function situacionTag(s) { return '<span class="app-status-tag ' + SITUACION[s][0] + '">' + SITUACION[s][1] + '</span>'; }
    function estadoTag(s) { return '<span class="app-status-tag ' + ESTADO_TAG[s] + '">' + ESTADO_LBL[s] + '</span>'; }
    var ICON = {
        wa: '<svg viewBox="0 0 24 24"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        birrete: '<svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>',
        tabla: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
        doc: '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>'
    };

    var toastEl = document.getElementById('cal-toast');
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

        function filas() { return Array.prototype.slice.call(tbody.querySelectorAll('.cal-row')); }
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
            var html = '<button type="button" class="cal-pg-btn" data-pg="prev"' + (pagina === 1 ? ' disabled' : '') + '><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Anterior</button>';
            rango(pagina, paginas).forEach(function (p) {
                html += p === '…' ? '<span class="cal-pg-ellipsis">…</span>'
                    : '<button type="button" class="cal-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            });
            html += '<button type="button" class="cal-pg-btn" data-pg="next"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg></button>';
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
            tbody.closest('.cal-table-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        tbody.addEventListener('click', function (e) {
            var row = e.target.closest('.cal-row');
            if (row) cfg.abrir(+row.dataset.id);
        });
        return { aplicar: aplicar, filtradas: function () { return filtradas; } };
    }

    function valor(id) { return document.getElementById(id).value; }
    function busca(id) { return (document.getElementById(id).value || '').toLowerCase().trim(); }

    /* Por grupo: primero los que tienen algo pendiente */
    var tGrupos = Tabla({
        tabla: 'cal-g-table', count: 'cal-g-count', empty: 'cal-g-empty', pag: 'cal-g-pagination', nombre: ['grupo', 'grupos'],
        abrir: function (id) { abrirGrupo(id); },
        ordenar: function (a, b) { return (+b.dataset.sin - +a.dataset.sin) || (+b.dataset.hab - +a.dataset.hab); },
        filtro: function (r) {
            var q = busca('cal-g-search'), j = valor('cal-g-jornada'), s = valor('cal-g-situacion');
            return (!q || r.dataset.buscar.indexOf(q) !== -1) &&
                (!j || r.dataset.jornada === j) &&
                (!s || (s === 'sin' && +r.dataset.sin > 0) || (s === 'hab' && +r.dataset.hab > 0) ||
                    (s === 'terminado' && r.dataset.terminado === '1'));
        }
    });
    var jornadas = JSON.parse(document.getElementById('cal-g-cb-jornada').dataset.options || '[]');
    filtroCombo('cal-g-cb-jornada', [{ value: '', label: 'Todas las jornadas' }].concat(jornadas.map(function (j) { return { value: j, label: j }; })), tGrupos.aplicar);
    filtroCombo('cal-g-cb-situacion', [
        { value: '', label: 'Todas las situaciones' },
        { value: 'sin', label: 'Con módulos sin nota' },
        { value: 'hab', label: 'Con estudiantes que perdieron' },
        { value: 'terminado', label: 'Grupos terminados' }
    ], tGrupos.aplicar);
    document.getElementById('cal-g-search').addEventListener('input', tGrupos.aplicar);

    /* Por estudiante */
    var ORDEN = {
        prom:     function (a, b) { return (+a.dataset.prom - +b.dataset.prom) || (+b.dataset.perdidos - +a.dataset.perdidos); },
        perdidos: function (a, b) { return (+b.dataset.perdidos - +a.dataset.perdidos) || (+a.dataset.prom - +b.dataset.prom); },
        nombre:   function (a, b) { return a.dataset.nombre.localeCompare(b.dataset.nombre, 'es'); }
    };
    var tEst = Tabla({
        tabla: 'cal-e-table', count: 'cal-e-count', empty: 'cal-e-empty', pag: 'cal-e-pagination', nombre: ['estudiante', 'estudiantes'],
        abrir: function (id) { abrirEstudiante(id); },
        ordenar: function (a, b) { return ORDEN[valor('cal-e-orden') || 'prom'](a, b); },
        filtro: function (r) {
            var q = busca('cal-e-search'), g = valor('cal-e-grupo'), s = valor('cal-e-situacion');
            return (!q || r.dataset.buscar.indexOf(q) !== -1) && (!g || r.dataset.grupo === g) && (!s || r.dataset.situacion === s);
        }
    });
    filtroCombo('cal-e-cb-grupo', [{ value: '', label: 'Todos los grupos' }].concat(DATA.grupos.map(function (g) {
        return { value: String(g.id), label: g.programa + ' · ' + g.grupo };
    })), tEst.aplicar);
    filtroCombo('cal-e-cb-situacion', [{ value: '', label: 'Todas las situaciones' }].concat(
        ['habilitar', 'reprobado', 'listo', 'graduado', 'al_dia', 'sin_notas'].map(function (s) { return { value: s, label: SITUACION[s][1] }; })
    ), tEst.aplicar);
    filtroCombo('cal-e-cb-orden', [
        { value: 'prom', label: 'Menor promedio primero' },
        { value: 'perdidos', label: 'Más módulos perdidos primero' },
        { value: 'nombre', label: 'Por apellido (A–Z)' }
    ], tEst.aplicar);
    document.getElementById('cal-e-search').addEventListener('input', tEst.aplicar);

    /* ── Pestañas de vista (se recuerda la última) ── */
    var vistaBtns = document.querySelectorAll('.cal-vista-btn');
    function mostrarVista(v) {
        vistaBtns.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.cal-vista').forEach(function (s) { s.hidden = s.dataset.panel !== v; });
        try { localStorage.setItem(VISTA_KEY, v); } catch (e) {}
    }
    vistaBtns.forEach(function (b) { b.addEventListener('click', function () { mostrarVista(b.dataset.vista); }); });
    try {
        var guardada = localStorage.getItem(VISTA_KEY);
        if (guardada && document.querySelector('.cal-vista[data-panel="' + guardada + '"]')) mostrarVista(guardada);
    } catch (e) {}

    /* ── Cajón ────────────────────────────────── */
    var drawer = document.getElementById('cal-drawer');
    var dwBody = document.getElementById('cal-dw-body');
    var dwAvatar = document.getElementById('cal-dw-avatar');
    var btnVolver = document.getElementById('cal-dw-volver');
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
        setTimeout(function () { drawer.querySelector('[data-cerrar].cal-close-btn').focus({ preventScroll: true }); }, 30);
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
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (certEl.getAttribute('aria-hidden') === 'false') cerrarCert();
        else cerrarCajon();
    });
    btnVolver.addEventListener('click', function () {
        var prev = pila.pop();
        if (!prev) return;
        actual = null;
        ({ grupo: abrirGrupo, estudiante: abrirEstudiante })[prev.tipo](prev.id, { volviendo: true });
        btnVolver.hidden = pila.length === 0;
    });
    function cabecera(avatarTxt, sigla, titulo, sub, badges) {
        dwAvatar.textContent = avatarTxt;
        dwAvatar.className = 'cal-drawer-avatar cal-avatar cal-esc-' + sigla;
        document.getElementById('cal-dw-titulo').textContent = titulo;
        document.getElementById('cal-dw-sub').textContent = sub;
        document.getElementById('cal-dw-badges').innerHTML = badges;
    }
    function dato(lbl, val) { return '<div class="cal-dato"><span class="cal-dato-lbl">' + lbl + '</span><span class="cal-dato-val">' + val + '</span></div>'; }
    function datoTxt(lbl, txt) { return '<div class="cal-dato"><span class="cal-dato-lbl">' + lbl + '</span><span class="cal-dato-txt">' + esc(txt) + '</span></div>'; }
    function aviso(clase, icono, texto, accion) {
        return '<div class="cal-aviso' + (clase ? ' ' + clase : '') + '"><div class="cal-aviso-txt">' + icono + '<p>' + texto + '</p></div>' + (accion || '') + '</div>';
    }
    function notaTag(v, sufijo) { return '<span class="app-status-tag app-status-' + ({ ok: 'active', warn: 'warn', bad: 'danger', none: 'muted' })[tono(v)] + '">' + (v == null ? 'Sin notas' : fmt(v) + ' ' + sufijo) + '</span>'; }
    function celda(x, estado, titulo) {
        var txt = (x && estado !== 'sin_nota') ? fmt(final(x)) + (x.h != null ? '<sup>H</sup>' : '') : (estado === 'sin_nota' ? '' : '·');
        return '<span class="cal-celda cal-c-' + estado + '" title="' + esc(titulo) + '">' + txt + '</span>';
    }
    function detalleNota(x) {
        if (!x) return '';
        return x.h != null ? 'Nota ' + fmt(x.n) + ' · habilitación ' + fmt(x.h) : 'Nota ' + fmt(x.n);
    }

    /* Mensaje de WhatsApp para recordarle al docente los módulos sin nota */
    function textoRecordatorio(g) {
        var mods = g.sin_nota.map(function (i) { return g.modulos[i].nombre; });
        return 'Hola ' + primerNombre(g.docente) + ', te escribimos de ESAT. Tienes pendiente registrar las notas de ' + lista(mods) +
            ' (' + g.programa + ' ' + g.grupo + '). ¡Gracias!';
    }

    /* ── Notas del grupo ──────────────────────── */
    function abrirGrupo(id, opts) {
        var g = grupoPorId[id];
        if (!g) return;
        opts = opts || {};
        var personas = DATA.estudiantes.filter(function (e) { return e.grupo_id === g.id; })
            .sort(function (a, b) { return a.apellidos.localeCompare(b.apellidos, 'es'); });
        var perdieron = g.por_habilitar + g.reprobados;

        cabecera(g.grupo.slice(-1), g.sigla, g.programa + ' · ' + g.grupo, g.jornada + ' · Docente: ' + g.docente,
            notaTag(g.prom, 'de promedio') +
            (g.sin_nota.length ? '<span class="cal-sin-tag">' + (g.sin_nota.length === 1 ? '1 módulo sin nota' : g.sin_nota.length + ' módulos sin nota') + '</span>' : '') +
            (g.terminado ? '<span class="app-status-tag app-status-muted">Terminó el ' + esc(g.fin_txt) + '</span>' : ''));

        var html = '<div class="cal-datos">' +
            dato('Promedio', '<span class="cal-tono-' + tono(g.prom) + '">' + fmt(g.prom) + '</span>') +
            dato('Módulos terminados', g.terminados + ' <small>de ' + g.modulos.length + '</small>') +
            dato('Estudiantes', g.activos + (perdieron ? ' <small>· ' + perdieron + ' con módulos perdidos</small>' : '')) +
            datoTxt('Horario', g.horario) +
            '</div>';

        if (g.sin_nota.length) {
            var mods = g.sin_nota.map(function (i) { return '<strong>' + esc(g.modulos[i].nombre) + '</strong> (terminó el ' + esc(g.modulos[i].fin_txt) + ')'; });
            html += aviso('', ICON.reloj, esc(g.docente) + ' no ha registrado las notas de ' + lista(mods) + '. Mientras no las registre, ese módulo no cuenta en el promedio ni para graduar.',
                g.telefono ? '<a class="cal-btn cal-btn-wa cal-btn-sm" href="' + waLink(g.telefono, textoRecordatorio(g)) + '" target="_blank" rel="noopener">' + ICON.wa + 'Recordarle</a>' : '');
        }
        if (g.terminado) {
            var aprobaron = g.listos + g.graduados;
            html += aviso(aprobaron === g.activos ? 'is-verde' : '', ICON.birrete,
                'El grupo terminó el ' + esc(g.fin_txt) + '. <strong>' + aprobaron + ' de ' + g.activos + '</strong> estudiantes tienen todos los módulos aprobados' +
                (g.graduados ? ' (' + g.graduados + ' ya ' + (g.graduados === 1 ? 'se graduó' : 'se graduaron') + ')' : '') + '. ' +
                'El grado se hace en Matrículas, donde también se revisan los pagos y el paz y salvo.');
        }

        html += '<div><div class="cal-seccion-titulo">Malla del grupo · fechas según el horario y las horas de cada módulo</div><ol class="cal-malla">' +
            g.modulos.map(function (m, i) {
                var est = m.sin_nota ? 'sin_nota' : m.estado;
                return '<li class="is-' + (m.sin_nota ? 'sin' : m.estado) + '"><span class="cal-malla-n">' + (i + 1) + '</span>' +
                    '<div class="cal-min0"><div class="cal-malla-nombre">' + esc(m.nombre) + '</div>' +
                    '<div class="cal-malla-sub">' + esc(m.ini_txt) + ' – ' + esc(m.fin_txt) + ' · ' + m.horas + ' h</div></div>' +
                    '<div class="cal-malla-der">' + (est === 'terminado'
                        ? '<span class="cal-nota cal-tono-' + tono(m.prom) + '">' + fmt(m.prom) + '</span><span class="cal-person-sub">promedio</span>'
                        : estadoTag(est)) + '</div></li>';
            }).join('') + '</ol></div>';

        html += '<div><div class="cal-seccion-titulo">Notas del grupo</div><div class="cal-matriz-wrap">' + matriz(g, personas) + '</div>' +
            '<div class="cal-leyenda">' +
            '<span>' + celda({ n: 4.2, h: null }, 'aprobado', '') + 'Aprobado</span>' +
            '<span>' + celda({ n: 2.6, h: 3.4 }, 'habilito', '') + 'Aprobó en habilitación</span>' +
            '<span>' + celda({ n: 2.5, h: null }, 'por_habilitar', '') + 'Perdido, puede habilitar</span>' +
            '<span>' + celda({ n: 2.1, h: 2.4 }, 'reprobado', '') + 'Perdió la habilitación</span>' +
            '<span>' + celda(null, 'sin_nota', '') + 'Sin nota del docente</span></div></div>';

        dwBody.innerHTML = html;
        if (!opts.volviendo) abrirCajon({ tipo: 'grupo', id: id }, !!opts.desdeCajon);
        else { actual = { tipo: 'grupo', id: id }; drawer.classList.add('is-ancho'); }

        dwBody.querySelectorAll('[data-est]').forEach(function (b) {
            b.addEventListener('click', function () { abrirEstudiante(+b.dataset.est, { desdeCajon: true }); });
        });
    }

    function matriz(g, personas) {
        if (!personas.length) return '<p class="cal-nota-vacia">El grupo no tiene estudiantes activos.</p>';
        var html = '<table class="cal-matriz"><thead><tr><th class="cal-mz-nombre" scope="col">Estudiante</th>';
        g.modulos.forEach(function (m, i) {
            var est = m.sin_nota ? 'sin' : m.estado;
            html += '<th class="cal-mz-mod is-' + est + '" scope="col" title="' + esc((i + 1) + '. ' + m.nombre + ' · ' + m.ini_txt + ' – ' + m.fin_txt) + '">' +
                '<b>M' + (i + 1) + '</b><i>' + ({ terminado: 'terminado', en_curso: 'en curso', por_iniciar: 'por cursar', sin: 'sin nota' })[est] + '</i></th>';
        });
        html += '<th class="cal-mz-prom" scope="col">Prom.</th></tr></thead><tbody>';
        personas.forEach(function (e) {
            html += '<tr><th class="cal-mz-nombre" scope="row"><button type="button" class="cal-mz-persona" data-est="' + e.id + '" title="Ver notas de ' + esc(e.nombres + ' ' + e.apellidos) + '">' +
                esc(e.apellidos + ', ' + primerNombre(e.nombres)) + '</button></th>' +
                g.modulos.map(function (m, i) {
                    var x = e.notas[i], s = e.estados[i];
                    return '<td class="cal-mz-celda">' + celda(x, s, m.nombre + ' · ' + ESTADO_LBL[s] + (x ? ' · ' + detalleNota(x) : '')) + '</td>';
                }).join('') +
                '<td class="cal-mz-prom cal-tono-' + tono(e.prom) + '">' + fmt(e.prom) + '</td></tr>';
        });
        html += '</tbody><tfoot><tr><th class="cal-mz-nombre" scope="row">Promedio del módulo</th>' +
            g.modulos.map(function (m) { return '<td class="cal-mz-celda"><span class="cal-nota cal-tono-' + tono(m.prom) + '">' + fmt(m.prom) + '</span></td>'; }).join('') +
            '<td class="cal-mz-prom cal-tono-' + tono(g.prom) + '">' + fmt(g.prom) + '</td></tr></tfoot></table>';
        return html;
    }

    /* ── Notas del estudiante ─────────────────── */
    function abrirEstudiante(id, opts) {
        var e = estPorId[id];
        if (!e) return;
        opts = opts || {};
        var g = grupoPorId[e.grupo_id];
        var perdidos = e.por_habilitar + e.reprobados;

        cabecera(iniciales(e.nombres, e.apellidos), g.sigla, e.nombres + ' ' + e.apellidos,
            e.tipo_doc + ' ' + e.documento + ' · ' + g.programa + ' · ' + g.grupo, situacionTag(e.situacion));

        var html = '<div class="cal-datos">' +
            dato('Promedio', '<span class="cal-tono-' + tono(e.prom) + '">' + fmt(e.prom) + '</span>') +
            dato('Aprobados', e.aprobados + ' <small>de ' + e.total + '</small>') +
            dato('Perdidos', perdidos) +
            dato('Asistencia', e.asistencia == null ? '— <small>sin registros</small>' : e.asistencia + ' % <small>referencia</small>') + '</div>';

        var nombresDe = function (estado) {
            return g.modulos.map(function (m, i) { return e.estados[i] === estado ? '<strong>' + esc(m.nombre) + '</strong> (' + fmt(e.notas[i].n) + ')' : null; }).filter(Boolean);
        };
        if (e.reprobados) {
            html += aviso('is-rojo', ICON.alerta, 'Perdió la habilitación de ' + lista(nombresDe('reprobado')) + '. Sin ' + (e.reprobados === 1 ? 'ese módulo aprobado' : 'esos módulos aprobados') + ' no puede graduarse.');
        }
        if (e.por_habilitar) {
            html += aviso('', ICON.alerta, 'Perdió ' + lista(nombresDe('por_habilitar')) + '. Tiene derecho a una habilitación por módulo; la nota la registra el docente.',
                e.telefono ? '<a class="cal-btn cal-btn-wa cal-btn-sm" href="' + waLink(e.telefono, 'Hola ' + primerNombre(e.nombres) + ', te escribimos de ESAT sobre la habilitación de tus módulos pendientes.') + '" target="_blank" rel="noopener">' + ICON.wa + 'Escribirle</a>' : '');
        }
        var noCursados = g.modulos.map(function (m, i) { return e.estados[i] === 'no_cursado' ? '<strong>' + esc(m.nombre) + '</strong>' : null; }).filter(Boolean);
        if (noCursados.length) {
            html += aviso('', ICON.reloj, 'Se matriculó cuando el grupo ya había visto ' + lista(noCursados) + '. Para graduarse también necesita ' + (noCursados.length === 1 ? 'ese módulo aprobado' : 'esos módulos aprobados') + '.');
        }
        if (e.situacion === 'graduado') {
            html += aviso('is-verde', ICON.birrete, 'Se graduó con <strong>todos los módulos aprobados</strong>.');
        } else if (e.situacion === 'listo') {
            html += aviso('is-verde', ICON.birrete, 'Tiene <strong>todos los módulos aprobados</strong>: por notas ya puede graduarse. El grado se hace en Matrículas, donde también se revisan los pagos y el paz y salvo.');
        } else if (g.terminado && !perdidos && !noCursados.length) {
            html += aviso('', ICON.reloj, 'El grupo ya terminó, pero le faltan notas por registrar. Hasta tenerlas todas aprobadas no puede graduarse.');
        }

        html += '<div><div class="cal-seccion-titulo">Notas por módulo</div><ol class="cal-modulos">' +
            g.modulos.map(function (m, i) {
                var x = e.notas[i], s = e.estados[i];
                var der = x
                    ? '<span class="cal-mod-grande cal-tono-' + tono(final(x)) + '">' + fmt(final(x)) + '</span>' +
                      (x.h != null ? '<span class="cal-mod-detalle">Nota <s>' + fmt(x.n) + '</s> · ' + (s === 'reprobado' ? 'en habilitación' : 'habilitó') + '</span>' : '') + estadoTag(s)
                    : estadoTag(s);
                return '<li><span class="cal-malla-n">' + (i + 1) + '</span>' +
                    '<div class="cal-min0"><div class="cal-malla-nombre">' + esc(m.nombre) + '</div>' +
                    '<div class="cal-malla-sub">' + esc(m.ini_txt) + ' – ' + esc(m.fin_txt) + ' · ' + m.horas + ' h · ' + esc(g.docente) + '</div></div>' +
                    '<div class="cal-mod-nota">' + der + '</div></li>';
            }).join('') + '</ol></div>';

        html += '<div class="cal-acciones-fila">' +
            '<button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" id="cal-ver-cert"' + (e.prom == null ? ' disabled title="Todavía no tiene notas"' : '') + '>' + ICON.doc + 'Certificado de notas</button>' +
            '<button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" id="cal-ver-grupo">' + ICON.tabla + 'Ver notas del grupo</button></div>';

        dwBody.innerHTML = html;
        if (!opts.volviendo) abrirCajon({ tipo: 'estudiante', id: id }, !!opts.desdeCajon);
        else { actual = { tipo: 'estudiante', id: id }; drawer.classList.remove('is-ancho'); }
        document.getElementById('cal-ver-grupo').addEventListener('click', function () { abrirGrupo(g.id, { desdeCajon: true }); });
        document.getElementById('cal-ver-cert').addEventListener('click', function () { abrirCert(e.id); });
    }

    /* ── Certificado de notas ─────────────────── */
    var certEl = document.getElementById('cal-cert');
    document.body.appendChild(certEl); // hijo directo de <body>: al imprimir se oculta todo lo demás
    var certFoco = null;
    var RESULTADO = {
        aprobado: 'Aprobado', habilito: 'Aprobado (H)', por_habilitar: 'No aprobado', reprobado: 'No aprobado',
        sin_nota: 'Pendiente', en_curso: 'En curso', por_iniciar: 'Por cursar', no_cursado: 'Pendiente'
    };
    function fechaLarga(iso) { var p = iso.split('-'); return +p[2] + ' de ' + MESES[+p[1] - 1] + ' de ' + p[0]; }

    function abrirCert(id) {
        var e = estPorId[id], g = grupoPorId[e.grupo_id];
        document.getElementById('cal-hoja-fecha').textContent = (DATA.ciudad ? DATA.ciudad + ', ' : '') + fechaLarga(DATA.hoy);
        var horas = g.modulos.reduce(function (s, m) { return s + m.horas; }, 0);
        document.getElementById('cal-hoja-cuerpo').innerHTML =
            '<h1 class="cal-hoja-titulo">Certificado de notas</h1>' +
            '<p>La Escuela Nacional de Educación ESAT hace constar que <strong>' + esc(e.nombres + ' ' + e.apellidos) + '</strong>, identificado(a) con ' +
            '<strong>' + esc(e.tipo_doc + ' ' + e.documento) + '</strong>, ' + (g.terminado ? 'cursó' : 'está cursando') + ' el programa <strong>' + esc(g.programa) + '</strong> ' +
            '(' + esc(g.grupo) + ', jornada ' + esc(g.jornada.toLowerCase()) + ', del ' + esc(g.inicio_txt) + ' al ' + esc(g.fin_txt) + ') y obtuvo las siguientes calificaciones:</p>' +
            '<table class="cal-hoja-tabla"><thead><tr><th>#</th><th>Módulo</th><th class="num">Intensidad</th><th class="num">Nota</th><th>Resultado</th></tr></thead><tbody>' +
            g.modulos.map(function (m, i) {
                var x = e.notas[i];
                return '<tr><td>' + (i + 1) + '</td><td>' + esc(m.nombre) + '</td><td class="num">' + m.horas + ' h</td>' +
                    '<td class="num">' + (x ? fmt(final(x)) : '—') + '</td><td>' + RESULTADO[e.estados[i]] + '</td></tr>';
            }).join('') +
            '</tbody><tfoot><tr><td></td><td>Promedio acumulado · ' + e.aprobados + ' de ' + e.total + ' módulos aprobados</td><td class="num">' + horas + ' h</td><td class="num">' + fmt(e.prom) + '</td><td></td></tr></tfoot></table>' +
            '<p class="cal-hoja-nota">Escala de calificación de 1,0 a 5,0; nota mínima aprobatoria 3,0. (H): aprobado en habilitación. ' +
            'El promedio solo incluye los módulos con nota registrada.</p>' +
            '<div class="cal-hoja-firma">Firma y sello<span>Escuela Nacional de Educación ESAT</span></div>';

        certFoco = document.activeElement;
        certEl.setAttribute('aria-hidden', 'false');
        certEl.scrollTop = 0;
        document.getElementById('cal-cert-imprimir').focus({ preventScroll: true });
    }
    function cerrarCert() {
        certEl.setAttribute('aria-hidden', 'true');
        if (certFoco && certFoco.focus) certFoco.focus({ preventScroll: true });
    }
    certEl.querySelectorAll('[data-cert-cerrar]').forEach(function (b) { b.addEventListener('click', cerrarCert); });
    document.getElementById('cal-cert-imprimir').addEventListener('click', function () { window.print(); });

    /* ── Exportar lo que se ve con los filtros (CSV con ; y BOM para las tildes) ── */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    function csvNota(v) { return v == null ? '' : fmt(v); }
    var EXPORT = {
        grupos: function () {
            return [['Programa', 'Grupo', 'Escuela', 'Jornada', 'Docente', 'Módulos terminados', 'Total de módulos', 'Módulo en curso', 'Módulos sin nota', 'Promedio', 'Por habilitar', 'Perdieron la habilitación', 'Listos para graduar']]
                .concat(tGrupos.filtradas().map(function (r) {
                    var g = grupoPorId[r.dataset.id];
                    return [g.programa, g.grupo, g.escuela, g.jornada, g.docente, g.terminados, g.modulos.length,
                        g.actual != null ? g.modulos[g.actual].nombre : (g.terminado ? 'Grupo terminado' : ''),
                        g.sin_nota.map(function (i) { return g.modulos[i].nombre; }).join(', '),
                        csvNota(g.prom), g.por_habilitar, g.reprobados, g.listos];
                }));
        },
        estudiantes: function () {
            return [['Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Programa', 'Grupo', 'Módulos aprobados', 'Total de módulos', 'Promedio', 'Situación', 'Módulos perdidos', 'Celular']]
                .concat(tEst.filtradas().map(function (r) {
                    var e = estPorId[r.dataset.id], g = grupoPorId[e.grupo_id];
                    var perdidos = g.modulos.map(function (m, i) {
                        var s = e.estados[i];
                        return s === 'por_habilitar' || s === 'reprobado' ? m.nombre + ' (' + fmt(final(e.notas[i])) + (s === 'reprobado' ? ', perdió la habilitación' : '') + ')' : null;
                    }).filter(Boolean).join(', ');
                    return [e.nombres, e.apellidos, e.tipo_doc, e.documento, g.programa, g.grupo, e.aprobados, e.total, csvNota(e.prom), SITUACION[e.situacion][1], perdidos, e.telefono];
                }));
        }
    };
    document.querySelectorAll('[data-exportar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var vista = btn.dataset.exportar;
            var filas = EXPORT[vista]();
            var csv = '﻿' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
            var hoy = new Date(), dd = function (n) { return (n < 10 ? '0' : '') + n; };
            var nombre = 'calificaciones-' + vista + '-' + hoy.getFullYear() + '-' + dd(hoy.getMonth() + 1) + '-' + dd(hoy.getDate()) + '.csv';
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
}());
