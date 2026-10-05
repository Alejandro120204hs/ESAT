/* ESAT — Admin: Estudiantes */
(function () {
    'use strict';

    var data       = window.EST_DATA || [];
    var grupos     = window.EST_GRUPOS || [];
    var acudientes = window.EST_ACUDIENTES || [];

    var PAGE_SIZE = 12;
    var ESTADOS = {
        activo:   { label: 'Activo',   desc: 'Asiste a clases y ocupa un cupo en su grupo.' },
        aplazado: { label: 'Aplazado', desc: 'Suspende sus estudios por un tiempo y libera el cupo. Puede retomar después.' },
        retirado: { label: 'Retirado', desc: 'Deja el programa de forma definitiva y libera el cupo.' },
        graduado: { label: 'Graduado', desc: 'Terminó el programa. Solo aplica cuando su grupo ya finalizó.' }
    };
    var ESCUELA_NOMBRE = {
        'Salud': 'Escuela de Salud', 'Cocina y Turismo': 'Escuela de Cocina y Turismo',
        'Administrativa': 'Escuela Administrativa', 'Educación e Idiomas': 'Escuela de Educación e Idiomas',
        'Deporte y Cultura': 'Escuela Deporte y Cultura', 'Ciencias': 'Escuela Ciencias', 'Belleza': 'Escuela de Belleza'
    };
    var PARENTESCOS = ['Madre', 'Padre', 'Abuelo(a)', 'Tío(a)', 'Hermano(a)', 'Otro'];
    var MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

    /* Íconos (mismo trazo que el resto del panel) */
    var ICONOS = {
        acudiente:  '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        pago:       '<svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>',
        asistencia: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="10" y1="14" x2="14" y2="18"/><line x1="14" y1="14" x2="10" y2="18"/></svg>',
        documentos: '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="12" x2="12" y2="15"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
        whatsapp:   '<svg viewBox="0 0 24 24"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>',
        telefono:   '<svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
        correo:     '<svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22 6 12 13 2 6"/></svg>',
        check:      '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>'
    };
    var ALERTA_INFO = {
        acudiente:  { titulo: 'Menores sin acudiente', nivel: 'alta' },
        pago:       { titulo: 'Pagos vencidos', nivel: 'alta' },
        asistencia: { titulo: 'Asistencia baja', nivel: 'media' },
        documentos: { titulo: 'Documentos pendientes', nivel: 'media' }
    };

    /* ── Utilidades ── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }
    function escClass(e) {
        return { 'Salud':'sal','Cocina y Turismo':'tur','Administrativa':'adm','Educación e Idiomas':'edu',
                 'Deporte y Cultura':'dep','Ciencias':'cie','Belleza':'bel' }[e] || 'adm';
    }
    function iniciales(e) {
        return ((e.nombres || '').charAt(0) + (e.apellidos || '').charAt(0)).toUpperCase();
    }
    function nombreCompleto(p) { return p.nombres + ' ' + p.apellidos; }
    function primerNombre(e) { return e.nombres.split(' ')[0]; }
    function hoyISO() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function edad(fecha) {
        if (!fecha) return null;
        var n = new Date(fecha + 'T00:00:00'), h = new Date();
        var a = h.getFullYear() - n.getFullYear();
        var m = h.getMonth() - n.getMonth();
        if (m < 0 || (m === 0 && h.getDate() < n.getDate())) a--;
        return a;
    }
    function fmtDoc(d) { return String(d).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function fmtTel(t) { t = String(t); return t.length === 10 ? t.slice(0,3) + ' ' + t.slice(3,6) + ' ' + t.slice(6) : t; }
    function fmtFecha(f) {
        if (!f) return '—';
        var p = f.split('-');
        return parseInt(p[2]) + ' ' + MESES[parseInt(p[1]) - 1] + ' ' + p[0];
    }
    function fmtPesos(v) { return '$ ' + fmtDoc(v); }
    function grupoDe(id) { return grupos.find(function (g) { return g.id === id; }); }
    function corto(programa) { return programa.replace('Técnico Laboral en ', ''); }
    function acudienteDe(e) { return e.acudiente_id ? acudientes.find(function (a) { return a.id === e.acudiente_id; }) : null; }
    function waLink(tel) { return 'https://wa.me/57' + tel; }
    function cuposLibres(g) { return g.cupo - g.inscritos; }
    function ocupaCupo(estado) { return estado === 'activo'; }

    function alertasDe(e) {
        if (e.estado === 'retirado' || e.estado === 'graduado') return [];
        var out = [];
        if (edad(e.fecha_nac) < 18 && !e.acudiente_id) out.push({ tipo: 'acudiente', texto: 'Menor de edad sin acudiente registrado' });
        if (e.pago === 'vencido') out.push({ tipo: 'pago', texto: 'Pago vencido · saldo ' + fmtPesos(e.saldo) });
        if (e.asistencia !== null && e.asistencia < 80) out.push({ tipo: 'asistencia', texto: 'Asistencia del ' + e.asistencia + ' %' });
        if (e.documentos_pendientes.length) {
            var n = e.documentos_pendientes.length;
            out.push({ tipo: 'documentos', texto: n + (n === 1 ? ' documento pendiente' : ' documentos pendientes') });
        }
        return out;
    }

    /* ── Combobox (mismo patrón de Docentes: abre hacia donde quepa) ── */
    function initCombobox(root) {
        var trigger     = root.querySelector('[data-combobox-trigger]');
        var label       = root.querySelector('[data-combobox-label]');
        var panel       = root.querySelector('[data-combobox-panel]');
        var searchInput = root.querySelector('[data-combobox-search]');
        var list        = root.querySelector('[data-combobox-list]');
        var hidden      = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;

        function posicionar() {
            var rect   = trigger.getBoundingClientRect();
            var margen = 12;
            panel.style.left  = rect.left + 'px';
            panel.style.width = Math.max(rect.width, 220) + 'px';
            var abajo  = window.innerHeight - rect.bottom - 6 - margen;
            var arriba = rect.top - 6 - margen;
            list.style.maxHeight = '';
            var extra = panel.offsetHeight - list.offsetHeight;
            var tope  = parseInt(root.dataset.listMaxHeight) || 300;
            var necesario = Math.min(list.scrollHeight, tope) + extra;
            var haciaArriba = abajo < necesario && arriba > abajo;
            var espacio = haciaArriba ? arriba : abajo;
            panel.style.top    = haciaArriba ? '' : (rect.bottom + 6) + 'px';
            panel.style.bottom = haciaArriba ? (window.innerHeight - rect.top + 6) + 'px' : '';
            list.style.maxHeight = Math.max(Math.min(tope, espacio - extra), 40) + 'px';
        }
        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (searchInput) { searchInput.value = ''; filtrar(''); }
            posicionar();
            if (searchInput) searchInput.focus();
            window.addEventListener('scroll', posicionar, true);
            window.addEventListener('resize', posicionar);
        }
        function cerrar() {
            if (panel.hidden) return;
            panel.hidden = true;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            window.removeEventListener('scroll', posicionar, true);
            window.removeEventListener('resize', posicionar);
        }
        function filtrar(q) {
            var qq = q.trim().toLowerCase(), vis = 0;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                var ok = qq === '' || li.dataset.label.toLowerCase().indexOf(qq) !== -1;
                li.hidden = !ok;
                if (ok) vis++;
            });
            var empty = list.querySelector('.app-combobox-empty');
            if (empty) empty.hidden = vis > 0;
        }

        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(); });
        if (searchInput) searchInput.addEventListener('input', function () { filtrar(searchInput.value); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });
        // Escape cierra primero la lista abierta y no llega al asistente ni a la ficha
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) { cerrar(); e.stopImmediatePropagation(); }
        });

        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (o) {
                var val = typeof o === 'object' ? o.value : o;
                var lbl = typeof o === 'object' ? o.label : o;
                var li  = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = val;
                li.dataset.label = lbl;
                var span = document.createElement('span');
                span.textContent = lbl;
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                li.appendChild(span);
                li.appendChild(svg);
                li.addEventListener('click', function () { root.setValue(val, lbl); cerrar(); });
                list.appendChild(li);
            });
            var empty = document.createElement('li');
            empty.className = 'app-combobox-empty';
            empty.hidden = opciones.length > 0;
            empty.textContent = 'Sin resultados';
            list.appendChild(empty);
        };
        root.setValue = function (valor, etiqueta, opts) {
            hidden.value = valor || '';
            label.textContent = etiqueta || valor || placeholder;
            root.classList.toggle('has-value', !!valor);
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === valor);
            });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        root.reset = function (nuevoPlaceholder) {
            if (nuevoPlaceholder) placeholder = nuevoPlaceholder;
            hidden.value = '';
            label.textContent = placeholder;
            root.classList.remove('has-value', 'is-invalid');
            list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.remove('is-selected'); });
        };
        root.value = function () { return hidden.value; };
    }

    /* ── Estado de la vista ── */
    var filtro = { estado: '', alerta: '', programa: '', grupo: '', texto: '' };
    // Vista por defecto: tabla (como Docentes). La elección del usuario se recuerda en este navegador.
    var vista = 'lista';
    try { if (localStorage.getItem('esat.admin.estudiantes.vista') === 'carnes') vista = 'carnes'; } catch (err) {}
    var pagina = 1;
    var nuevoId = null;

    var gridEl      = document.getElementById('est-grid');
    var listaWrap   = document.getElementById('est-lista-wrap');
    var listaBody   = document.getElementById('est-lista-body');
    var emptyEl     = document.getElementById('est-empty');
    var countEl     = document.getElementById('est-count');
    var pagEl       = document.getElementById('est-pagination');
    var pagPages    = document.getElementById('est-pg-pages');
    var pagPrev     = document.getElementById('est-pg-prev');
    var pagNext     = document.getElementById('est-pg-next');
    var searchEl    = document.getElementById('est-search');
    var cbPrograma  = document.getElementById('est-cb-programa');
    var cbGrupo     = document.getElementById('est-cb-grupo');

    function coincideBase(e) {
        var g = grupoDe(e.grupo_id);
        var q = filtro.texto;
        var okTexto = !q ||
            nombreCompleto(e).toLowerCase().indexOf(q) >= 0 ||
            String(e.documento).indexOf(q.replace(/\D/g, '') || '§') >= 0 ||
            e.codigo.toLowerCase().indexOf(q) >= 0;
        return okTexto &&
            (!filtro.programa || g.programa === filtro.programa) &&
            (!filtro.grupo || String(e.grupo_id) === filtro.grupo);
    }
    function coincide(e) {
        return coincideBase(e) &&
            (!filtro.estado || e.estado === filtro.estado) &&
            (!filtro.alerta || alertasDe(e).some(function (a) { return a.tipo === filtro.alerta; }));
    }

    /* ── Carné ── */
    function selloHTML(estado) {
        return '<span class="est-sello est-sello-' + estado + '">' + ESTADOS[estado].label + '</span>';
    }
    function alertasIconos(e) {
        return alertasDe(e).map(function (a) {
            return '<span class="est-alerta-ico est-alerta-' + ALERTA_INFO[a.tipo].nivel + '" title="' + esc(a.texto) + '" role="img" aria-label="' + esc(a.texto) + '">' + ICONOS[a.tipo] + '</span>';
        }).join('');
    }
    function carneHTML(e, grande) {
        var g = grupoDe(e.grupo_id);
        var ed = edad(e.fecha_nac);
        var tag = grande ? 'div' : 'article';
        var interactivo = grande ? '' : ' role="button" tabindex="0" aria-label="Abrir ficha de ' + esc(nombreCompleto(e)) + '"';
        return '<' + tag + ' class="est-carne est-esc-' + escClass(g.escuela) + ' is-' + e.estado + (grande ? ' est-carne--grande' : '') + (e.id === nuevoId ? ' is-nuevo' : '') + '" data-id="' + e.id + '"' + interactivo + '>' +
            '<div class="est-carne-banda">' +
                '<span class="est-carne-escuela">' + esc(ESCUELA_NOMBRE[g.escuela] || g.escuela) + '</span>' +
                '<span class="est-carne-codigo">' + esc(e.codigo) + '</span>' +
            '</div>' +
            '<div class="est-carne-cuerpo">' +
                '<div class="est-carne-foto" aria-hidden="true">' + esc(iniciales(e)) + '</div>' +
                '<div class="est-carne-info">' +
                    (grande ? '<h2 class="est-carne-nombre" id="est-fi-nombre">' : '<h3 class="est-carne-nombre">') + esc(nombreCompleto(e)) + (grande ? '</h2>' : '</h3>') +
                    '<p class="est-carne-doc">' + esc(e.tipo_doc) + ' ' + fmtDoc(e.documento) + '<span class="est-carne-sep" aria-hidden="true"></span>' + ed + ' años' +
                        (ed < 18 ? '<span class="est-menor">Menor</span>' : '') + '</p>' +
                    '<p class="est-carne-prog">' + esc(corto(g.programa)) + '</p>' +
                    '<p class="est-carne-grupo">' + esc(g.grupo) + ' · ' + esc(g.jornada) + '</p>' +
                '</div>' +
            '</div>' +
            '<div class="est-carne-pie">' +
                selloHTML(e.estado) +
                '<span class="est-carne-alertas">' + alertasIconos(e) + '</span>' +
                (grande ? '' : '<a class="est-wa" href="' + waLink(e.telefono) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(primerNombre(e)) + '" title="WhatsApp ' + fmtTel(e.telefono) + '">' + ICONOS.whatsapp + '</a>') +
            '</div>' +
        '</' + tag + '>';
    }
    /* Estado con la misma pastilla que Docentes (app-status-tag del layout) */
    function estadoTagHTML(estado) {
        var cls = { activo: 'app-status-active', aplazado: 'app-status-warn', retirado: 'app-status-muted', graduado: 'est-status-graduado' }[estado];
        return '<span class="app-status-tag ' + cls + '">' + ESTADOS[estado].label + '</span>';
    }

    function filaHTML(e) {
        var g = grupoDe(e.grupo_id);
        var al = alertasDe(e);
        var ed = edad(e.fecha_nac);
        return '<tr class="est-fila is-' + e.estado + '" data-id="' + e.id + '">' +
            '<td><div class="est-fila-est"><span class="est-fila-foto est-esc-' + escClass(g.escuela) + '" aria-hidden="true">' + esc(iniciales(e)) + '</span>' +
                '<span class="est-fila-datos"><span class="est-fila-nombre">' + esc(nombreCompleto(e)) + '</span>' +
                '<span class="est-fila-doc">' + esc(e.tipo_doc) + ' ' + fmtDoc(e.documento) + ' · ' + ed + ' años' +
                (ed < 18 ? '<span class="est-menor">Menor</span>' : '') + '</span>' +
                // En pantallas angostas las columnas se pliegan aquí: escuela, programa y grupo, alertas y WhatsApp
                '<span class="est-fila-movil"><span class="est-esc-tag est-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>' +
                '<span class="est-fila-movil-prog">' + esc(corto(g.programa)) + ' · ' + esc(g.grupo) + '</span>' +
                '<span class="est-fila-movil-acc"><span class="est-carne-alertas">' + alertasIconos(e) + '</span>' +
                '<a class="est-wa est-wa-sm" href="' + waLink(e.telefono) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(primerNombre(e)) + '">' + ICONOS.whatsapp + '</a></span></span>' +
                '</span></div></td>' +
            '<td><span class="est-esc-tag est-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span></td>' +
            '<td><span class="est-fila-prog">' + esc(corto(g.programa)) + '</span><span class="est-fila-sub">' + esc(g.grupo) + ' · ' + esc(g.codigo) + ' · ' + esc(g.jornada) + '</span></td>' +
            '<td>' + estadoTagHTML(e.estado) + '</td>' +
            '<td><span class="est-carne-alertas">' + (al.length ? alertasIconos(e) : '<span class="est-sin-alertas">—</span>') + '</span></td>' +
            '<td><a class="est-fila-wa" href="' + waLink(e.telefono) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(primerNombre(e)) + '">' + ICONOS.whatsapp + fmtTel(e.telefono) + '</a></td>' +
            '<td class="est-fila-acc"><button type="button" class="est-icon-btn-fila" title="Ver ficha" aria-label="Ver ficha de ' + esc(nombreCompleto(e)) + '">' +
                '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></td>' +
        '</tr>';
    }

    /* ── Render principal ── */
    function render() {
        var base = data.filter(coincideBase);
        // Orden alfabético por apellido; los inscritos en esta sesión van primero
        var lista = data.filter(coincide).sort(function (a, b) {
            return (b.reciente ? 1 : 0) - (a.reciente ? 1 : 0) || a.apellidos.localeCompare(b.apellidos, 'es');
        });

        // KPIs (sobre todos los estudiantes, como en Docentes)
        var conAlerta = data.filter(function (e) { return alertasDe(e).length > 0; }).length;
        var progs = [];
        data.forEach(function (e) { var pr = grupoDe(e.grupo_id).programa; if (progs.indexOf(pr) < 0) progs.push(pr); });
        setTxt('est-kpi-total', data.length);
        setTxt('est-kpi-activos', data.filter(function (e) { return e.estado === 'activo'; }).length);
        setTxt('est-kpi-alertas', conAlerta);
        setTxt('est-kpi-programas', progs.length);
        opcionesAlerta();

        var paginas = Math.max(1, Math.ceil(lista.length / PAGE_SIZE));
        if (pagina > paginas) pagina = paginas;
        var desde = (pagina - 1) * PAGE_SIZE;
        var visibles = lista.slice(desde, desde + PAGE_SIZE);

        countEl.textContent = lista.length + (lista.length === 1 ? ' estudiante' : ' estudiantes');
        emptyEl.hidden = lista.length > 0;
        gridEl.hidden = vista !== 'carnes' || !lista.length;
        listaWrap.hidden = vista !== 'lista' || !lista.length;

        if (vista === 'carnes') gridEl.innerHTML = visibles.map(function (e) { return carneHTML(e, false); }).join('');
        else listaBody.innerHTML = visibles.map(filaHTML).join('');

        // Paginación
        pagEl.hidden = paginas <= 1;
        pagPrev.disabled = pagina === 1;
        pagNext.disabled = pagina === paginas;
        pagPages.innerHTML = '';
        for (var p = 1; p <= paginas; p++) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'est-pg-num' + (p === pagina ? ' is-active' : '');
            b.textContent = p;
            b.setAttribute('aria-label', 'Página ' + p);
            if (p === pagina) b.setAttribute('aria-current', 'page');
            b.dataset.p = p;
            pagPages.appendChild(b);
        }
        nuevoId = null;
    }

    function irAPagina(p) {
        pagina = p;
        render();
        document.querySelector('.est-toolbar').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    pagPages.addEventListener('click', function (e) { var b = e.target.closest('.est-pg-num'); if (b) irAPagina(parseInt(b.dataset.p)); });
    pagPrev.addEventListener('click', function () { if (pagina > 1) irAPagina(pagina - 1); });
    pagNext.addEventListener('click', function () { irAPagina(pagina + 1); });

    function setTxt(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

    /* ── Controles ── */
    searchEl.addEventListener('input', function () { filtro.texto = searchEl.value.toLowerCase().trim(); pagina = 1; render(); });

    initCombobox(cbPrograma);
    initCombobox(cbGrupo);
    var programas = [];
    grupos.forEach(function (g) { if (programas.indexOf(g.programa) < 0) programas.push(g.programa); });
    cbPrograma.setOptions([{ value: '', label: 'Todos los programas' }].concat(programas.map(function (p) { return { value: p, label: corto(p) }; })));
    cbPrograma.setValue('', 'Todos los programas', { silent: true });

    var cbEstado = document.getElementById('est-cb-estado');
    var cbAlerta = document.getElementById('est-cb-alerta');
    initCombobox(cbEstado);
    initCombobox(cbAlerta);
    cbEstado.setOptions([{ value: '', label: 'Todos los estados' }].concat(Object.keys(ESTADOS).map(function (k) { return { value: k, label: ESTADOS[k].label }; })));
    cbEstado.setValue('', 'Todos los estados', { silent: true });
    document.getElementById('est-val-estado').addEventListener('change', function (e) { filtro.estado = e.target.value; pagina = 1; render(); });

    // Opciones de alerta con su conteo; se recalculan al cambiar los datos
    function opcionesAlerta() {
        var conteo = { acudiente: 0, pago: 0, asistencia: 0, documentos: 0 };
        data.forEach(function (e) { alertasDe(e).forEach(function (a) { conteo[a.tipo]++; }); });
        cbAlerta.setOptions([{ value: '', label: 'Todas las alertas' }].concat(Object.keys(ALERTA_INFO).map(function (k) {
            return { value: k, label: ALERTA_INFO[k].titulo + ' (' + conteo[k] + ')' };
        })));
        var sel = filtro.alerta;
        cbAlerta.setValue(sel, sel ? ALERTA_INFO[sel].titulo + ' (' + conteo[sel] + ')' : 'Todas las alertas', { silent: true });
    }
    document.getElementById('est-val-alerta').addEventListener('change', function (e) { filtro.alerta = e.target.value; pagina = 1; render(); });

    function opcionesGrupoFiltro() {
        var gs = grupos.filter(function (g) { return !filtro.programa || g.programa === filtro.programa; });
        cbGrupo.setOptions([{ value: '', label: 'Todos los grupos' }].concat(gs.map(function (g) {
            return { value: String(g.id), label: g.codigo + ' · ' + g.grupo };
        })));
        cbGrupo.setValue('', 'Todos los grupos', { silent: true });
        filtro.grupo = '';
    }
    opcionesGrupoFiltro();
    document.getElementById('est-val-programa').addEventListener('change', function (e) {
        filtro.programa = e.target.value;
        opcionesGrupoFiltro();
        pagina = 1; render();
    });
    document.getElementById('est-val-grupo').addEventListener('change', function (e) {
        filtro.grupo = e.target.value;
        pagina = 1; render();
    });

    document.querySelectorAll('.est-vista-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            vista = b.dataset.vista;
            try { localStorage.setItem('esat.admin.estudiantes.vista', vista); } catch (err) {}
            marcarVista();
            render();
        });
    });
    function marcarVista() {
        document.querySelectorAll('.est-vista-btn').forEach(function (x) {
            x.classList.toggle('is-active', x.dataset.vista === vista);
            x.setAttribute('aria-pressed', x.dataset.vista === vista ? 'true' : 'false');
        });
    }
    marcarVista();

    document.getElementById('est-limpiar').addEventListener('click', function () {
        filtro = { estado: '', alerta: '', programa: '', grupo: '', texto: '' };
        searchEl.value = '';
        cbPrograma.setValue('', 'Todos los programas', { silent: true });
        cbEstado.setValue('', 'Todos los estados', { silent: true });
        opcionesGrupoFiltro();
        pagina = 1; render();
    });

    // Abrir ficha desde carné o fila (el enlace de WhatsApp no abre la ficha)
    function abrirDesde(e) {
        if (e.target.closest('.est-wa, .est-fila-wa')) return;
        var item = e.target.closest('.est-carne[data-id], .est-fila');
        if (item) abrirFicha(parseInt(item.dataset.id), item);
    }
    function teclaAbrir(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (e.target.closest('.est-wa, .est-fila-wa')) return;
        var item = e.target.closest('.est-carne[data-id], .est-fila');
        if (!item || item !== e.target) return;
        e.preventDefault();
        abrirFicha(parseInt(item.dataset.id), item);
    }
    gridEl.addEventListener('click', abrirDesde);
    listaBody.addEventListener('click', abrirDesde);
    gridEl.addEventListener('keydown', teclaAbrir);
    listaBody.addEventListener('keydown', teclaAbrir);

    /* ── Aviso ── */
    var toastEl = document.getElementById('est-toast');
    var toastTimer;
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 4200);
    }

    /* ══ Ficha del estudiante ══ */
    var drawer      = document.getElementById('est-drawer');
    var fichaEl     = document.getElementById('est-ficha');
    var accionEl    = document.getElementById('est-accion');
    var fichaFoot   = document.getElementById('est-ficha-foot');
    var accionFoot  = document.getElementById('est-accion-foot');
    var fiTabs      = document.getElementById('est-fi-tabs');
    var fiPanel     = document.getElementById('est-fi-panel');
    var accionError = document.getElementById('est-accion-error');
    var actual = null, tabActual = 'datos', accionActual = null, origenFoco = null;

    function abrirFicha(id, origen) {
        actual = data.find(function (e) { return e.id === id; });
        if (!actual) return;
        origenFoco = origen || null;
        tabActual = 'datos';
        mostrarFicha();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        setTimeout(function () { document.getElementById('est-drawer-close').focus(); }, 60);
    }
    function cerrarFicha() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (origenFoco && document.body.contains(origenFoco)) origenFoco.focus();
    }
    document.getElementById('est-drawer-overlay').addEventListener('click', cerrarFicha);
    document.getElementById('est-drawer-close').addEventListener('click', cerrarFicha);

    function mostrarFicha() {
        accionActual = null;
        fichaEl.hidden = false; fichaFoot.hidden = false; fiTabs.hidden = false;
        accionEl.hidden = true; accionFoot.hidden = true;
        pintarCabecera(actual);
        document.querySelectorAll('.est-fi-tab').forEach(function (t) {
            t.classList.toggle('is-active', t.dataset.tab === tabActual);
            t.setAttribute('aria-selected', t.dataset.tab === tabActual ? 'true' : 'false');
        });
        fiPanel.innerHTML = panelFicha(tabActual);
        // "Cambiar de grupo" no aplica a retirados ni graduados
        fichaFoot.querySelector('[data-accion="grupo"]').disabled = actual.estado === 'retirado' || actual.estado === 'graduado';
    }
    function pintarCabecera(e) {
        var g = grupoDe(e.grupo_id), ed = edad(e.fecha_nac);
        var av = document.getElementById('est-fi-avatar');
        av.textContent = iniciales(e);
        av.className = 'est-drawer-avatar est-esc-' + escClass(g.escuela) + (e.estado === 'retirado' ? ' is-retirado' : '');
        document.getElementById('est-fi-nombre').textContent = nombreCompleto(e);
        document.getElementById('est-fi-doc').textContent = e.tipo_doc + ' ' + fmtDoc(e.documento) + ' · ' + ed + ' años · ' + e.codigo;
        document.getElementById('est-fi-badges').innerHTML = estadoTagHTML(e.estado) +
            '<span class="est-esc-tag est-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>' +
            (ed < 18 ? '<span class="est-menor">Menor de edad</span>' : '');
    }

    fiTabs.addEventListener('click', function (e) {
        var t = e.target.closest('.est-fi-tab');
        if (!t) return;
        tabActual = t.dataset.tab;
        mostrarFicha();
    });

    function dato(lbl, valor, completo) {
        return '<div class="est-info-item' + (completo ? ' est-info-full' : '') + '"><span class="est-info-lbl">' + lbl + '</span><span class="est-info-val">' + valor + '</span></div>';
    }
    function seccion(titulo, cuerpo, extra) {
        return '<section class="est-sec"><div class="est-sec-head"><h4 class="est-sec-title">' + titulo + '</h4>' + (extra || '') + '</div>' + cuerpo + '</section>';
    }
    function contactoHTML(tel, email) {
        return '<a class="est-contacto" href="tel:+57' + tel + '">' + ICONOS.telefono + fmtTel(tel) + '</a>' +
               '<a class="est-contacto est-contacto-wa" href="' + waLink(tel) + '" target="_blank" rel="noopener">' + ICONOS.whatsapp + 'WhatsApp</a>' +
               (email ? '<a class="est-contacto" href="mailto:' + esc(email) + '">' + ICONOS.correo + esc(email) + '</a>' : '');
    }

    function panelFicha(tab) {
        var e = actual, g = grupoDe(e.grupo_id), ed = edad(e.fecha_nac);
        var al = alertasDe(e);
        var html = '';

        if (tab === 'datos') {
            if (al.length) {
                html += '<ul class="est-fi-alertas">' + al.map(function (a) {
                    return '<li class="est-alerta-' + ALERTA_INFO[a.tipo].nivel + '">' + ICONOS[a.tipo] + esc(a.texto) + '</li>';
                }).join('') + '</ul>';
            }
            if (e.estado !== 'activo' && e.estado_fecha) {
                html += '<div class="est-callout est-callout-' + e.estado + '"><strong>' + ESTADOS[e.estado].label + ' desde el ' + fmtFecha(e.estado_fecha) + '</strong>' +
                    (e.estado_motivo ? '<p>' + esc(e.estado_motivo) + '</p>' : '') + '</div>';
            }
            html += '<div class="est-info-grid">' +
                dato('Correo electrónico', e.email ? esc(e.email) : '—') +
                dato('Celular', fmtTel(e.telefono)) +
                dato('Tipo de documento', { TI: 'Tarjeta de identidad', CC: 'Cédula de ciudadanía', CE: 'Cédula de extranjería', PPT: 'Permiso por Protección Temporal' }[e.tipo_doc] || esc(e.tipo_doc)) +
                dato('Número de documento', fmtDoc(e.documento)) +
                dato('Fecha de nacimiento', fmtFecha(e.fecha_nac) + ' · ' + ed + ' años') +
                dato('Género', esc(e.genero)) +
                dato('Dirección', esc(e.direccion) + ', ' + esc(e.ciudad) + ' (' + esc(e.departamento) + ')', true) +
                dato('Código estudiantil', esc(e.codigo)) +
                dato('Ingreso a ESAT', fmtFecha(e.fecha_ingreso)) +
            '</div>' +
            seccion('Contacto rápido', '<div class="est-contactos">' + contactoHTML(e.telefono, e.email) + '</div>');
        }

        if (tab === 'acudiente') {
            var a = acudienteDe(e);
            if (a) {
                var otros = data.filter(function (x) { return x.acudiente_id === a.id && x.id !== e.id; });
                html += '<div class="est-info-grid">' +
                    dato('Nombre', esc(nombreCompleto(a)), true) +
                    dato('Parentesco', esc(e.parentesco)) +
                    dato('Cédula', fmtDoc(a.documento)) +
                    dato('Celular', fmtTel(a.telefono)) +
                    dato('Correo electrónico', a.email ? esc(a.email) : '—') +
                '</div>' +
                seccion('Contacto rápido', '<div class="est-contactos">' + contactoHTML(a.telefono, a.email) + '</div>') +
                (otros.length ? seccion('También es acudiente de', '<div class="est-chips-info">' + otros.map(function (o) { return '<span class="est-info-chip">' + esc(nombreCompleto(o)) + '</span>'; }).join('') + '</div>') : '') +
                '<div class="est-sec-acc"><button type="button" class="est-btn est-btn-secondary est-btn-sm" data-accion="acudiente">Cambiar acudiente</button></div>';
            } else {
                html += '<div class="est-callout ' + (ed < 18 ? 'est-callout-alerta' : '') + '">' +
                    '<strong>' + (ed < 18 ? 'Este estudiante es menor de edad y no tiene acudiente' : 'Sin acudiente registrado') + '</strong>' +
                    '<p>' + (ed < 18 ? 'Registra a la persona responsable para que pueda seguir sus notas, su asistencia y sus pagos.' : 'Por ser mayor de edad no es obligatorio. Puedes registrar uno si el estudiante lo solicita.') + '</p></div>' +
                    '<div class="est-sec-acc"><button type="button" class="est-btn est-btn-primary est-btn-sm" data-accion="acudiente">Vincular acudiente</button></div>';
            }
        }

        if (tab === 'grupo') {
            var libres = cuposLibres(g);
            var pct = Math.round(g.inscritos / g.cupo * 100);
            html += '<div class="est-info-grid">' +
                dato('Grupo', esc(g.grupo)) +
                dato('Código', esc(g.codigo)) +
                dato('Programa', esc(g.programa), true) +
                dato('Escuela', '<span class="est-esc-tag est-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>') +
                dato('Jornada', esc(g.jornada)) +
                dato('Docente', esc(g.docente), true) +
            '</div>' +
            seccion('Horario', g.horario.map(function (h) { return '<span class="est-horario-linea">' + esc(h) + '</span>'; }).join('')) +
            seccion('Cupo del grupo',
                '<div class="est-cupo-barra" role="img" aria-label="' + g.inscritos + ' de ' + g.cupo + ' cupos ocupados"><span style="width:' + pct + '%"></span></div>' +
                '<p class="est-sec-nota">' + (g.estado === 'finalizado' ? 'Grupo finalizado' : (libres > 0 ? libres + (libres === 1 ? ' cupo libre' : ' cupos libres') : 'Sin cupos libres')) + '</p>',
                '<span class="est-sec-dato">' + g.inscritos + ' de ' + g.cupo + '</span>');
        }

        if (tab === 'seguimiento') {
            var pagoCls = { al_dia: 'app-status-active', pendiente: 'app-status-warn', vencido: 'app-status-danger' }[e.pago];
            var pagoLbl = { al_dia: 'Al día', pendiente: 'Cuota pendiente', vencido: 'Pago vencido' }[e.pago];
            var segs = '';
            for (var i = 1; i <= e.cuotas_total; i++) segs += '<span class="' + (i <= e.cuotas_pagadas ? 'is-paga' : '') + '"></span>';
            html += seccion('Pagos',
                '<div class="est-cuotas" role="img" aria-label="' + e.cuotas_pagadas + ' de ' + e.cuotas_total + ' cuotas pagadas">' + segs + '</div>' +
                '<div class="est-info-grid">' +
                    dato('Cuotas pagadas', e.cuotas_pagadas + ' de ' + e.cuotas_total) +
                    dato('Saldo pendiente', e.saldo ? fmtPesos(e.saldo) : '$ 0') +
                    dato('Último pago', fmtFecha(e.ultimo_pago)) +
                '</div>',
                '<span class="app-status-tag ' + pagoCls + '">' + pagoLbl + '</span>') +
            seccion('Asistencia',
                e.asistencia === null ? '<p class="est-sec-nota">Su grupo todavía no ha iniciado clases.</p>'
                    : '<p class="est-sec-nota">' + (e.asistencia < 80 ? 'Por debajo del 80 % esperado. Conviene contactar al estudiante' + (acudienteDe(e) ? ' y a su acudiente' : '') + '.' : 'Asistencia en el periodo actual.') + '</p>',
                e.asistencia === null ? '<span class="app-status-tag app-status-muted">Sin clases aún</span>'
                    : '<span class="app-status-tag ' + (e.asistencia < 80 ? 'app-status-warn' : 'app-status-active') + '">' + e.asistencia + ' %</span>') +
            seccion('Documentos',
                e.documentos_pendientes.length
                    ? '<ul class="est-docs">' + e.documentos_pendientes.map(function (d) { return '<li>' + ICONOS.documentos + esc(d) + '</li>'; }).join('') + '</ul>'
                    : '<p class="est-sec-nota est-docs-ok">' + ICONOS.check + 'Documentación completa.</p>');
        }
        return html;
    }

    /* ── Acciones dentro de la ficha ── */
    var accionTtl    = document.getElementById('est-accion-ttl');
    var accionSub    = document.getElementById('est-accion-sub');
    var accionCuerpo = document.getElementById('est-accion-cuerpo');
    var btnConfirmar = document.getElementById('est-accion-confirmar');
    var acuSel = null;   // acudiente encontrado o nuevo en la acción "acudiente"

    function mostrarAccion(tipo) {
        accionActual = tipo;
        fichaEl.hidden = true; fichaFoot.hidden = true; fiTabs.hidden = true;
        accionEl.hidden = false; accionFoot.hidden = false;
        accionError.hidden = true;
        var e = actual, g = grupoDe(e.grupo_id);
        btnConfirmar.textContent = 'Confirmar';
        btnConfirmar.className = 'est-btn est-btn-primary';

        if (tipo === 'grupo') {
            accionTtl.textContent = 'Cambiar de grupo';
            accionSub.textContent = 'Grupos de ' + corto(g.programa) + '. Solo se muestran grupos con cupo o en planificación.';
            var opciones = grupos.filter(function (x) { return x.programa === g.programa && x.estado !== 'finalizado'; });
            accionCuerpo.innerHTML = '<div class="est-grupos-opc" role="radiogroup" aria-label="Nuevo grupo">' + opciones.map(function (x) {
                return grupoOpcionHTML(x, x.id === g.id, 'acc-grupo');
            }).join('') + '</div>';
        }
        if (tipo === 'estado') {
            accionTtl.textContent = 'Cambiar estado';
            accionSub.textContent = 'Estado actual: ' + ESTADOS[e.estado].label + '.';
            accionCuerpo.innerHTML = '<div class="est-estado-opc" role="radiogroup" aria-label="Nuevo estado">' + Object.keys(ESTADOS).map(function (k) {
                return '<label class="est-radio-card' + (k === e.estado ? ' is-actual' : '') + '"><input type="radio" name="acc-estado" value="' + k + '"' + (k === e.estado ? ' checked' : '') + '>' +
                    '<span class="est-radio-card-main"><span class="est-radio-card-ttl">' + estadoTagHTML(k) + (k === e.estado ? '<em>Actual</em>' : '') + '</span>' +
                    '<span class="est-radio-card-desc">' + ESTADOS[k].desc + '</span></span></label>';
            }).join('') + '</div>' +
            '<div class="est-field"><label class="est-lbl" for="acc-fecha">Fecha del cambio</label><input class="est-input" type="date" id="acc-fecha" value="' + hoyISO() + '" max="' + hoyISO() + '"></div>' +
            '<div class="est-field" id="acc-motivo-wrap"><label class="est-lbl" for="acc-motivo">Motivo</label><textarea class="est-input est-textarea" id="acc-motivo" rows="3" placeholder="Ej. Incapacidad médica, viaje, cambio de ciudad"></textarea></div>';
            var actualizarMotivo = function () {
                var v = (accionCuerpo.querySelector('input[name="acc-estado"]:checked') || {}).value;
                document.getElementById('acc-motivo-wrap').hidden = !(v === 'aplazado' || v === 'retirado');
            };
            accionCuerpo.querySelectorAll('input[name="acc-estado"]').forEach(function (r) { r.addEventListener('change', actualizarMotivo); });
            actualizarMotivo();
        }
        if (tipo === 'clave') {
            accionTtl.textContent = 'Restablecer contraseña';
            accionSub.textContent = '';
            accionCuerpo.innerHTML = '<div class="est-callout"><strong>La contraseña de ' + esc(primerNombre(e)) + ' volverá a ser su número de documento</strong>' +
                '<p>Podrá ingresar con el usuario y la contraseña <strong class="est-num-inline">' + fmtDoc(e.documento) + '</strong> (sin puntos) y el sistema le pedirá crear una nueva. Su contraseña actual dejará de funcionar.</p></div>';
            btnConfirmar.textContent = 'Restablecer contraseña';
        }
        if (tipo === 'acudiente') {
            acuSel = null;
            accionTtl.textContent = acudienteDe(e) ? 'Cambiar acudiente' : 'Vincular acudiente';
            accionSub.textContent = 'Busca primero por cédula: si ya está registrado (por ejemplo, por un hermano) se vincula sin crearlo de nuevo.';
            accionCuerpo.innerHTML =
                '<div class="est-field"><label class="est-lbl" for="acc-acu-doc">Cédula del acudiente</label>' +
                '<div class="est-buscar-row"><input class="est-input est-num" id="acc-acu-doc" inputmode="numeric" autocomplete="off" placeholder="Solo números">' +
                '<button type="button" class="est-btn est-btn-navy" id="acc-acu-buscar">Buscar</button></div></div>' +
                '<div id="acc-acu-res"></div>' +
                '<div id="acc-acu-nuevo" hidden><div class="est-form-row">' +
                    '<div class="est-field"><label class="est-lbl" for="acc-acu-nom">Nombres</label><input class="est-input" id="acc-acu-nom" autocomplete="off"></div>' +
                    '<div class="est-field"><label class="est-lbl" for="acc-acu-ape">Apellidos</label><input class="est-input" id="acc-acu-ape" autocomplete="off"></div></div>' +
                    '<div class="est-form-row"><div class="est-field"><label class="est-lbl" for="acc-acu-tel">Celular</label><input class="est-input est-num" id="acc-acu-tel" inputmode="numeric" autocomplete="off" placeholder="3XX XXX XXXX"></div>' +
                    '<div class="est-field"><label class="est-lbl" for="acc-acu-email">Correo <em>opcional</em></label><input class="est-input" id="acc-acu-email" type="email" autocomplete="off"></div></div></div>' +
                '<div class="est-field" id="acc-acu-par-wrap" hidden><span class="est-lbl">Parentesco con el estudiante</span><div class="est-chips" id="acc-acu-par" role="radiogroup" aria-label="Parentesco"></div></div>';
            pintarChips(document.getElementById('acc-acu-par'), 'acc-par');
            var buscar = function () { buscarAcudiente('acc-acu-doc', 'acc-acu-res', 'acc-acu-nuevo', 'acc-acu-par-wrap', function (a) { acuSel = a; }); };
            document.getElementById('acc-acu-buscar').addEventListener('click', buscar);
            document.getElementById('acc-acu-doc').addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); buscar(); } });
        }
        accionEl.scrollTop = 0;
        setTimeout(function () { document.getElementById('est-accion-volver').focus(); }, 30);
    }

    function grupoOpcionHTML(g, actualSel, name) {
        var libres = cuposLibres(g);
        var lleno = libres <= 0 && !actualSel;
        var pct = Math.min(100, Math.round(g.inscritos / g.cupo * 100));
        return '<label class="est-grupo-opc' + (lleno ? ' is-lleno' : '') + (actualSel ? ' is-actual' : '') + '">' +
            '<input type="radio" name="' + name + '" value="' + g.id + '"' + (lleno ? ' disabled' : '') + (actualSel ? ' checked' : '') + '>' +
            '<span class="est-grupo-opc-main">' +
                '<span class="est-grupo-opc-top"><strong>' + esc(g.grupo) + '</strong><span>' + esc(g.codigo) + '</span>' +
                    (actualSel ? '<em>Grupo actual</em>' : (g.estado === 'planificacion' ? '<em>En planificación</em>' : '')) + '</span>' +
                '<span class="est-grupo-opc-meta">' + esc(g.jornada) + ' · ' + esc(g.horario.join(' / ')) + '</span>' +
                '<span class="est-grupo-opc-meta">' + esc(g.docente) + '</span>' +
                '<span class="est-cupo-barra" aria-hidden="true"><span style="width:' + pct + '%"></span></span>' +
                '<span class="est-grupo-opc-cupo">' + (lleno ? 'Sin cupos libres' : libres + (libres === 1 ? ' cupo libre' : ' cupos libres') + ' de ' + g.cupo) + '</span>' +
            '</span></label>';
    }

    function pintarChips(cont, name) {
        cont.innerHTML = PARENTESCOS.map(function (p) {
            return '<label class="est-chip"><input type="radio" name="' + name + '" value="' + p + '"><span>' + p + '</span></label>';
        }).join('');
    }

    /* Busca un acudiente por cédula y muestra el resultado o el formulario de registro */
    function buscarAcudiente(inputId, resId, nuevoId, parId, alElegir) {
        var doc = document.getElementById(inputId).value.replace(/\D/g, '');
        var res = document.getElementById(resId);
        var nuevo = document.getElementById(nuevoId);
        var par = document.getElementById(parId);
        if (doc.length < 6) {
            res.innerHTML = '<p class="est-campo-error">Escribe la cédula completa (mínimo 6 dígitos).</p>';
            nuevo.hidden = true; par.hidden = true; alElegir(null);
            return;
        }
        var a = acudientes.find(function (x) { return x.documento === doc; });
        if (a) {
            var hijos = data.filter(function (x) { return x.acudiente_id === a.id; });
            res.innerHTML = '<div class="est-acu-card est-acu-encontrado">' +
                '<span class="est-acu-badge">' + ICONOS.check + 'Ya está registrado</span>' +
                '<div class="est-acu-top"><span class="est-acu-foto" aria-hidden="true">' + esc(iniciales(a)) + '</span>' +
                '<div><strong>' + esc(nombreCompleto(a)) + '</strong><span>CC ' + fmtDoc(a.documento) + ' · ' + fmtTel(a.telefono) + '</span></div></div>' +
                (hijos.length ? '<p class="est-acu-otros">Acudiente de ' + hijos.map(function (h) { return '<strong>' + esc(nombreCompleto(h)) + '</strong>'; }).join(', ') + '.</p>' : '') +
            '</div>';
            nuevo.hidden = true;
            alElegir({ existente: a });
        } else {
            res.innerHTML = '<p class="est-acu-nuevo-msg">No hay un acudiente con la cédula <strong>' + fmtDoc(doc) + '</strong>. Completa sus datos para registrarlo.</p>';
            nuevo.hidden = false;
            alElegir({ nuevo: doc });
        }
        par.hidden = false;
    }

    var CAMPOS_ACU_ACC = { nom: 'acc-acu-nom', ape: 'acc-acu-ape', tel: 'acc-acu-tel', email: 'acc-acu-email' };
    var CAMPOS_ACU_INS = { nom: 'ins-acu-nombres', ape: 'ins-acu-apellidos', tel: 'ins-acu-telefono', email: 'ins-acu-email' };

    function leerNuevoAcudiente(ids, doc) {
        var nom = document.getElementById(ids.nom).value.trim();
        var ape = document.getElementById(ids.ape).value.trim();
        var tel = document.getElementById(ids.tel).value.replace(/\D/g, '');
        var email = document.getElementById(ids.email).value.trim();
        if (nom.length < 2) return { error: 'Escribe los nombres del acudiente.', campo: ids.nom };
        if (ape.length < 2) return { error: 'Escribe los apellidos del acudiente.', campo: ids.ape };
        if (!/^3\d{9}$/.test(tel)) return { error: 'El celular del acudiente debe tener 10 dígitos y empezar por 3.', campo: ids.tel };
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return { error: 'El correo del acudiente no es válido.', campo: ids.email };
        return { acudiente: { id: acudientes.reduce(function (m, a) { return Math.max(m, a.id); }, 0) + 1, nombres: nom, apellidos: ape, documento: doc, telefono: tel, email: email } };
    }

    function errorAccion(msg, campoId) {
        accionError.textContent = msg;
        accionError.hidden = false;
        if (campoId) { var c = document.getElementById(campoId); if (c) { c.classList.add('is-invalid'); c.focus(); } }
    }

    fichaFoot.addEventListener('click', function (e) { var b = e.target.closest('[data-accion]'); if (b && !b.disabled) mostrarAccion(b.dataset.accion); });
    fiPanel.addEventListener('click', function (e) { var b = e.target.closest('[data-accion]'); if (b) mostrarAccion(b.dataset.accion); });
    document.getElementById('est-accion-volver').addEventListener('click', mostrarFicha);
    document.getElementById('est-accion-cancelar').addEventListener('click', mostrarFicha);
    accionEl.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); accionError.hidden = true; });
    accionEl.addEventListener('change', function () { accionError.hidden = true; });

    btnConfirmar.addEventListener('click', function () {
        var e = actual, g = grupoDe(e.grupo_id), msg = '';
        accionError.hidden = true;

        if (accionActual === 'grupo') {
            var sel = accionCuerpo.querySelector('input[name="acc-grupo"]:checked');
            if (!sel || parseInt(sel.value) === g.id) return errorAccion('Selecciona un grupo diferente al actual.');
            var nuevo = grupoDe(parseInt(sel.value));
            if (ocupaCupo(e.estado)) { g.inscritos--; nuevo.inscritos++; }
            e.grupo_id = nuevo.id;
            msg = nombreCompleto(e) + ' pasó al ' + nuevo.grupo + ' (' + nuevo.codigo + ').';
        }

        if (accionActual === 'estado') {
            var est = (accionCuerpo.querySelector('input[name="acc-estado"]:checked') || {}).value;
            var fecha = document.getElementById('acc-fecha').value;
            var motivo = document.getElementById('acc-motivo').value.trim();
            if (est === e.estado) return errorAccion('Selecciona un estado diferente al actual.');
            if (!fecha) return errorAccion('Indica la fecha del cambio.', 'acc-fecha');
            if (fecha > hoyISO()) return errorAccion('La fecha del cambio no puede ser futura.', 'acc-fecha');
            if ((est === 'aplazado' || est === 'retirado') && motivo.length < 5) return errorAccion('Escribe el motivo del ' + (est === 'aplazado' ? 'aplazamiento' : 'retiro') + '.', 'acc-motivo');
            if (est === 'graduado' && g.estado !== 'finalizado') return errorAccion('Solo se puede graduar cuando su grupo haya finalizado. ' + g.codigo + ' sigue en curso.');
            if (est === 'activo' && !ocupaCupo(e.estado) && cuposLibres(g) <= 0) return errorAccion('El ' + g.grupo + ' (' + g.codigo + ') no tiene cupos libres. Cámbialo de grupo antes de reactivarlo.');
            if (ocupaCupo(e.estado) && !ocupaCupo(est)) g.inscritos--;
            if (!ocupaCupo(e.estado) && ocupaCupo(est)) g.inscritos++;
            e.estado = est;
            e.estado_fecha = est === 'activo' ? null : fecha;
            e.estado_motivo = (est === 'aplazado' || est === 'retirado') ? motivo : null;
            msg = nombreCompleto(e) + ' ahora está ' + ESTADOS[est].label.toLowerCase() + '.';
        }

        if (accionActual === 'clave') {
            msg = 'La contraseña de ' + nombreCompleto(e) + ' volvió a ser su número de documento.';
        }

        if (accionActual === 'acudiente') {
            if (!acuSel) return errorAccion('Busca al acudiente por su cédula.', 'acc-acu-doc');
            var docAcu = acuSel.existente ? acuSel.existente.documento : acuSel.nuevo;
            if (docAcu === String(e.documento)) return errorAccion('El acudiente no puede ser el mismo estudiante.', 'acc-acu-doc');
            var acu = acuSel.existente;
            if (!acu) {
                var r = leerNuevoAcudiente(CAMPOS_ACU_ACC, acuSel.nuevo);
                if (r.error) return errorAccion(r.error, r.campo);
                acu = r.acudiente;
            }
            var par = (accionCuerpo.querySelector('input[name="acc-par"]:checked') || {}).value;
            if (!par) return errorAccion('Selecciona el parentesco con el estudiante.');
            if (!acuSel.existente) acudientes.push(acu);
            e.acudiente_id = acu.id;
            e.parentesco = par;
            tabActual = 'acudiente';
            msg = nombreCompleto(acu) + ' quedó como acudiente de ' + primerNombre(e) + '.';
        }

        render();
        mostrarFicha();
        toast(msg);
    });

    /* ══ Asistente: Inscribir estudiante ══ */
    var modal     = document.getElementById('est-modal');
    var paso      = 1;
    var TOTAL     = 5;
    var insError  = document.getElementById('ins-error');
    var btnSig    = document.getElementById('est-btn-siguiente');
    var btnAtras  = document.getElementById('est-btn-atras');
    var insAcu    = null;   // { existente } | { nuevo: doc }

    var cbGenero   = document.getElementById('ins-cb-genero');
    var cbTipo     = document.getElementById('ins-cb-tipo');
    var cbDepto    = document.getElementById('ins-cb-depto');
    var cbCiudad   = document.getElementById('ins-cb-ciudad');
    var cbProgIns  = document.getElementById('ins-cb-programa');
    [cbGenero, cbTipo, cbDepto, cbCiudad, cbProgIns].forEach(initCombobox);

    cbGenero.setOptions(['Femenino', 'Masculino', 'No binario', 'Prefiere no decirlo']);
    cbTipo.setOptions([
        { value: 'TI', label: 'Tarjeta de identidad' },
        { value: 'CC', label: 'Cédula de ciudadanía' },
        { value: 'CE', label: 'Cédula de extranjería' },
        { value: 'PPT', label: 'Permiso por Protección Temporal' }
    ]);
    cbProgIns.setOptions(programas.map(function (p) { return { value: p, label: corto(p) }; }));
    pintarChips(document.getElementById('ins-parentesco'), 'ins-par');

    // Departamentos y municipios reales de Colombia
    var colombia = null;
    fetch('/assets/data/co-departamentos-ciudades.json')
        .then(function (r) { return r.json(); })
        .then(function (d) { colombia = d; cbDepto.setOptions(d.map(function (x) { return x.departamento; })); });
    document.getElementById('ins-depto').addEventListener('change', function (e) {
        var dep = colombia && colombia.find(function (x) { return x.departamento === e.target.value; });
        cbCiudad.reset(dep ? 'Selecciona un municipio' : 'Elige el departamento primero');
        cbCiudad.setOptions(dep ? dep.ciudades : []);
    });

    function val(id) { return document.getElementById(id).value.trim(); }
    function edadIns() { return edad(val('ins-nacimiento')); }

    // Edad en vivo desde la fecha de nacimiento (nunca se guarda como número)
    var edadEl = document.getElementById('ins-edad');
    document.getElementById('ins-nacimiento').max = hoyISO();
    document.getElementById('ins-nacimiento').addEventListener('input', function () {
        var ed = edadIns();
        if (ed === null || isNaN(ed) || ed < 0) { edadEl.textContent = ''; edadEl.className = 'est-edad'; return; }
        edadEl.textContent = ed + ' años' + (ed < 18 ? ' · menor de edad: necesitará acudiente' : '');
        edadEl.className = 'est-edad' + (ed < 18 ? ' is-menor' : '');
    });

    function abrirModal() {
        paso = 1;
        modal.querySelectorAll('input.est-input').forEach(function (i) { i.value = ''; i.classList.remove('is-invalid'); });
        modal.querySelectorAll('input[type=radio]').forEach(function (r) { r.checked = false; });
        edadEl.textContent = ''; edadEl.className = 'est-edad';
        cbGenero.reset(); cbTipo.reset(); cbDepto.reset(); cbCiudad.reset('Elige el departamento primero'); cbCiudad.setOptions([]); cbProgIns.reset();
        document.getElementById('ins-acu-agregar').checked = false;
        document.getElementById('ins-acu-resultado').innerHTML = '';
        document.getElementById('ins-acu-nuevo').hidden = true;
        document.getElementById('ins-acu-parentesco-wrap').hidden = true;
        document.getElementById('ins-grupos').innerHTML = '<p class="est-campo-ayuda">Primero elige el programa.</p>';
        document.getElementById('ins-resumen').hidden = true;
        insAcu = null;
        pintarPaso();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { document.getElementById('ins-nombres').focus(); }, 80);
    }
    function cerrarModal() {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.getElementById('est-btn-inscribir').focus();
    }
    document.getElementById('est-btn-inscribir').addEventListener('click', abrirModal);
    document.getElementById('est-modal-close').addEventListener('click', cerrarModal);
    document.getElementById('est-modal-overlay').addEventListener('click', cerrarModal);

    function pintarPaso() {
        modal.querySelectorAll('.est-mstep').forEach(function (s) { s.classList.toggle('is-active', +s.dataset.step === paso); });
        modal.querySelectorAll('.est-wstep').forEach(function (s) {
            var n = +s.dataset.step;
            s.classList.toggle('is-active', n === paso);
            s.classList.toggle('is-done', n < paso);
            if (n === paso) s.setAttribute('aria-current', 'step'); else s.removeAttribute('aria-current');
        });
        document.getElementById('est-modal-paso').textContent = 'Paso ' + paso + ' de ' + TOTAL;
        btnAtras.style.visibility = paso === 1 ? 'hidden' : 'visible';
        btnSig.textContent = paso === TOTAL ? 'Inscribir estudiante' : 'Siguiente';
        insError.hidden = true;
        if (paso === 4) prepararAcudiente();
        modal.querySelector('.est-modal-body').scrollTop = 0;
    }

    function prepararAcudiente() {
        var ed = edadIns();
        var menor = ed < 18;
        var aviso = document.getElementById('ins-acu-aviso');
        aviso.className = 'est-acu-aviso' + (menor ? ' is-menor' : '');
        aviso.innerHTML = menor
            ? '<strong>' + esc(val('ins-nombres').split(' ')[0] || 'El estudiante') + ' tiene ' + ed + ' años: el acudiente es obligatorio.</strong><span>Es la persona responsable que seguirá sus notas, su asistencia y sus pagos.</span>'
            : '<strong>Es mayor de edad: el acudiente es opcional.</strong><span>Regístralo solo si el estudiante lo solicita.</span>';
        document.getElementById('ins-acu-opcional').hidden = menor;
        document.getElementById('ins-acu-bloque').hidden = !menor && !document.getElementById('ins-acu-agregar').checked;
    }
    document.getElementById('ins-acu-agregar').addEventListener('change', prepararAcudiente);
    function buscarIns() {
        buscarAcudiente('ins-acu-buscar', 'ins-acu-resultado', 'ins-acu-nuevo', 'ins-acu-parentesco-wrap', function (a) { insAcu = a; });
    }
    document.getElementById('ins-acu-btn-buscar').addEventListener('click', buscarIns);
    document.getElementById('ins-acu-buscar').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); buscarIns(); } });

    // Paso 5: grupos del programa elegido
    document.getElementById('ins-programa').addEventListener('change', function (e) {
        var gs = grupos.filter(function (g) { return g.programa === e.target.value && g.estado !== 'finalizado'; });
        document.getElementById('ins-grupos').innerHTML = gs.map(function (g) { return grupoOpcionHTML(g, false, 'ins-grupo'); }).join('');
        document.getElementById('ins-resumen').hidden = true;
    });
    document.getElementById('ins-grupos').addEventListener('change', pintarResumen);

    function pintarResumen() {
        var sel = modal.querySelector('input[name="ins-grupo"]:checked');
        var res = document.getElementById('ins-resumen');
        if (!sel) { res.hidden = true; return; }
        var g = grupoDe(parseInt(sel.value));
        var acuTxt = insAcuNombre();
        res.innerHTML = '<h4>Resumen de la inscripción</h4><div class="est-info-grid">' +
            dato('Estudiante', esc(val('ins-nombres') + ' ' + val('ins-apellidos'))) +
            dato('Documento', esc(cbTipo.value()) + ' ' + fmtDoc(val('ins-documento').replace(/\D/g, ''))) +
            dato('Edad', edadIns() + ' años') +
            dato('Acudiente', acuTxt ? esc(acuTxt) : 'Sin acudiente') +
            dato('Programa y grupo', esc(corto(g.programa)) + ' · ' + esc(g.grupo) + ' (' + esc(g.codigo) + ')', true) +
            dato('Usuario y contraseña inicial', '<span class="est-num-inline">' + esc(val('ins-documento').replace(/\D/g, '')) + '</span>', true) +
        '</div>';
        res.hidden = false;
    }
    function insAcuNombre() {
        if (!insAcu) return '';
        if (insAcu.existente) return nombreCompleto(insAcu.existente);
        return (val('ins-acu-nombres') + ' ' + val('ins-acu-apellidos')).trim();
    }

    function errorIns(msg, campo) {
        insError.textContent = msg;
        insError.hidden = false;
        if (campo) {
            var el = document.getElementById(campo);
            if (el) {
                el.classList.add('is-invalid');
                var foco = el.classList.contains('app-combobox') ? el.querySelector('[data-combobox-trigger]') : el;
                foco.focus();
            }
        }
        return false;
    }

    function validarPaso() {
        if (paso === 1) {
            if (val('ins-nombres').length < 2) return errorIns('Escribe los nombres del estudiante.', 'ins-nombres');
            if (val('ins-apellidos').length < 2) return errorIns('Escribe los apellidos del estudiante.', 'ins-apellidos');
            if (!val('ins-nacimiento')) return errorIns('Indica la fecha de nacimiento.', 'ins-nacimiento');
            var ed = edadIns();
            if (val('ins-nacimiento') > hoyISO() || ed < 0) return errorIns('La fecha de nacimiento no puede ser futura.', 'ins-nacimiento');
            if (ed > 99) return errorIns('Revisa el año de nacimiento.', 'ins-nacimiento');
            if (!cbGenero.value()) return errorIns('Selecciona el género.', 'ins-cb-genero');
        }
        if (paso === 2) {
            var tipo = cbTipo.value(), ed2 = edadIns();
            var doc = val('ins-documento').replace(/\D/g, '');
            if (!tipo) return errorIns('Selecciona el tipo de documento.', 'ins-cb-tipo');
            if (tipo === 'TI' && ed2 >= 18) return errorIns('Con ' + ed2 + ' años el documento es la cédula de ciudadanía, no la tarjeta de identidad.', 'ins-cb-tipo');
            if (tipo === 'CC' && ed2 < 18) return errorIns('Un menor de edad se identifica con tarjeta de identidad.', 'ins-cb-tipo');
            if (doc.length < 6 || doc.length > 11) return errorIns('El número de documento debe tener entre 6 y 11 dígitos.', 'ins-documento');
            var dup = data.find(function (x) { return String(x.documento) === doc; });
            if (dup) return errorIns('Ya existe un estudiante con ese documento: ' + nombreCompleto(dup) + ' (' + dup.codigo + ').', 'ins-documento');
        }
        if (paso === 3) {
            var tel = val('ins-telefono').replace(/\D/g, '');
            var email = val('ins-email');
            if (!/^3\d{9}$/.test(tel)) return errorIns('El celular debe tener 10 dígitos y empezar por 3.', 'ins-telefono');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return errorIns('El correo no es válido. Revisa que tenga @ y un dominio.', 'ins-email');
            if (val('ins-direccion').length < 5) return errorIns('Escribe la dirección de residencia.', 'ins-direccion');
            if (!cbDepto.value()) return errorIns('Selecciona el departamento.', 'ins-cb-depto');
            if (!cbCiudad.value()) return errorIns('Selecciona la ciudad o municipio.', 'ins-cb-ciudad');
        }
        if (paso === 4) {
            var requiere = edadIns() < 18 || document.getElementById('ins-acu-agregar').checked;
            if (requiere) {
                if (!insAcu) return errorIns('Busca al acudiente por su cédula.', 'ins-acu-buscar');
                var docA = insAcu.existente ? insAcu.existente.documento : insAcu.nuevo;
                if (docA === val('ins-documento').replace(/\D/g, '')) return errorIns('El acudiente no puede ser el mismo estudiante.', 'ins-acu-buscar');
                if (insAcu.nuevo) {
                    var r = leerNuevoAcudiente(CAMPOS_ACU_INS, insAcu.nuevo);
                    if (r.error) return errorIns(r.error, r.campo);
                }
                if (!modal.querySelector('input[name="ins-par"]:checked')) return errorIns('Selecciona el parentesco con el estudiante.');
            }
        }
        if (paso === 5) {
            if (!cbProgIns.value()) return errorIns('Selecciona el programa.', 'ins-cb-programa');
            if (!modal.querySelector('input[name="ins-grupo"]:checked')) return errorIns('Selecciona un grupo con cupo.');
        }
        return true;
    }

    btnSig.addEventListener('click', function () {
        insError.hidden = true;
        if (!validarPaso()) return;
        if (paso < TOTAL) { paso++; pintarPaso(); return; }
        inscribir();
    });
    btnAtras.addEventListener('click', function () { if (paso > 1) { paso--; pintarPaso(); } });
    modal.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); insError.hidden = true; });
    modal.addEventListener('change', function (e) {
        var cb = e.target.closest('.app-combobox');
        if (cb) cb.classList.remove('is-invalid');
        insError.hidden = true;
    });

    function inscribir() {
        var g = grupoDe(parseInt(modal.querySelector('input[name="ins-grupo"]:checked').value));
        var requiere = edadIns() < 18 || document.getElementById('ins-acu-agregar').checked;
        var acuId = null, parentesco = null;
        if (requiere && insAcu) {
            var acu = insAcu.existente;
            if (!acu) { acu = leerNuevoAcudiente(CAMPOS_ACU_INS, insAcu.nuevo).acudiente; acudientes.push(acu); }
            acuId = acu.id;
            parentesco = modal.querySelector('input[name="ins-par"]:checked').value;
        }
        var id = data.reduce(function (m, x) { return Math.max(m, x.id); }, 0) + 1;
        var nuevo = {
            id: id, codigo: 'EST-2026-' + String(100 + id * 7).padStart(4, '0'),
            nombres: val('ins-nombres'), apellidos: val('ins-apellidos'),
            tipo_doc: cbTipo.value(), documento: val('ins-documento').replace(/\D/g, ''),
            fecha_nac: val('ins-nacimiento'), genero: cbGenero.value(),
            telefono: val('ins-telefono').replace(/\D/g, ''), email: val('ins-email'),
            direccion: val('ins-direccion'), departamento: cbDepto.value(), ciudad: cbCiudad.value(),
            grupo_id: g.id, estado: 'activo', estado_fecha: null, estado_motivo: null,
            fecha_ingreso: hoyISO(), acudiente_id: acuId, parentesco: parentesco,
            pago: 'al_dia', cuotas_pagadas: 0, cuotas_total: 10, saldo: 0, ultimo_pago: null,
            asistencia: null, documentos_pendientes: ['Copia del documento de identidad', 'Certificado de estudios'],
            reciente: true
        };
        data.unshift(nuevo);
        g.inscritos++;
        cerrarModal();

        // Mostrar el carné nuevo de primero, sin filtros que lo oculten
        document.getElementById('est-limpiar').click();
        nuevoId = id;
        render();
        toast(nombreCompleto(nuevo) + ' quedó ' + (nuevo.genero === 'Femenino' ? 'inscrita' : 'inscrito') + ' en el ' + g.grupo + ' (' + g.codigo + '). Usuario y contraseña inicial: ' + nuevo.documento + '.');
    }

    /* Escape: primero cierra listas abiertas, luego el asistente o la ficha */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (modal.classList.contains('is-open')) { cerrarModal(); return; }
        if (drawer.classList.contains('is-open')) {
            if (accionActual) mostrarFicha(); else cerrarFicha();
        }
    });

    render();
}());
