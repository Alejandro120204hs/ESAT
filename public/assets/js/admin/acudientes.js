/* ESAT — Admin: Acudientes */
(function () {
    'use strict';

    var data        = window.ACU_DATA || [];
    var estudiantes = window.ACU_ESTUDIANTES || [];
    var grupos      = window.ACU_GRUPOS || [];

    var PAGE_SIZE = 12;
    var CUENTAS = {
        activa:      { label: 'Activa',             cls: 'app-status-active' },
        sin_ingreso: { label: 'Nunca ha ingresado', cls: 'app-status-warn' },
        inactiva:    { label: 'Inactiva',           cls: 'app-status-muted' }
    };
    var ESTADOS_EST = {
        activo:   { label: 'Activo',   cls: 'app-status-active' },
        aplazado: { label: 'Aplazado', cls: 'app-status-warn' },
        retirado: { label: 'Retirado', cls: 'app-status-muted' },
        graduado: { label: 'Graduado', cls: 'app-status-muted' }
    };
    var PAGOS = {
        al_dia:    { label: 'Al día',          cls: 'app-status-active' },
        pendiente: { label: 'Cuota pendiente', cls: 'app-status-warn' },
        vencido:   { label: 'Pago vencido',    cls: 'app-status-danger' }
    };
    var TIPOS_DOC = { CC: 'Cédula de ciudadanía', CE: 'Cédula de extranjería', PPT: 'Permiso por Protección Temporal' };
    var PARENTESCOS = ['Madre', 'Padre', 'Abuelo(a)', 'Tío(a)', 'Hermano(a)', 'Otro'];
    var MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

    /* Íconos (mismo trazo que el resto del panel) */
    var ICONOS = {
        pago:       '<svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>',
        asistencia: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="10" y1="14" x2="14" y2="18"/><line x1="14" y1="14" x2="10" y2="18"/></svg>',
        correo:     '<svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22 6 12 13 2 6"/></svg>',
        vinculo:    '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        whatsapp:   '<svg viewBox="0 0 24 24"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>',
        telefono:   '<svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
        quitar:     '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        ojo:        '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        mas:        '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'
    };
    var ALERTA_INFO = {
        pago:       { titulo: 'Estudiante con pago vencido', nivel: 'alta' },
        asistencia: { titulo: 'Estudiante con asistencia baja', nivel: 'media' },
        vinculo:    { titulo: 'Sin estudiantes vigentes', nivel: 'media' },
        correo:     { titulo: 'Sin correo electrónico', nivel: 'media' }
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
    function iniciales(p) { return ((p.nombres || '').charAt(0) + (p.apellidos || '').charAt(0)).toUpperCase(); }
    function nombreCompleto(p) { return p.nombres + ' ' + p.apellidos; }
    function primerNombre(p) { return p.nombres.split(' ')[0]; }
    function hoyISO() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function edad(fecha) {
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
    function corto(programa) { return programa.replace('Técnico Laboral en ', ''); }
    function grupoDe(id) { return grupos.find(function (g) { return g.id === id; }); }
    function estDe(id) { return estudiantes.find(function (x) { return x.id === id; }); }
    function acuDe(id) { return data.find(function (x) { return x.id === id; }); }
    function waLink(tel, texto) { return 'https://wa.me/57' + tel + (texto ? '?text=' + encodeURIComponent(texto) : ''); }
    function vigente(e) { return e.estado === 'activo' || e.estado === 'aplazado'; }
    function aCargo(a) {
        return estudiantes.filter(function (x) { return x.acudiente_id === a.id; })
            .sort(function (x, y) { return (vigente(y) ? 1 : 0) - (vigente(x) ? 1 : 0) || x.nombres.localeCompare(y.nombres, 'es'); });
    }
    function tag(info) { return '<span class="app-status-tag ' + info.cls + '">' + info.label + '</span>'; }
    function cuentaTag(a) { return tag(CUENTAS[a.cuenta]); }

    function alertasDe(a) {
        var out = [];
        var vig = aCargo(a).filter(vigente);
        vig.forEach(function (e) {
            if (e.pago === 'vencido') out.push({ tipo: 'pago', texto: primerNombre(e) + ' tiene un pago vencido · saldo ' + fmtPesos(e.saldo) });
        });
        vig.forEach(function (e) {
            if (e.asistencia !== null && e.asistencia < 80) out.push({ tipo: 'asistencia', texto: 'Asistencia de ' + primerNombre(e) + ' en ' + e.asistencia + ' %' });
        });
        if (!vig.length) out.push({ tipo: 'vinculo', texto: 'No tiene estudiantes vigentes a cargo' });
        if (!a.email) out.push({ tipo: 'correo', texto: 'Sin correo electrónico registrado' });
        return out;
    }

    /* ── Combobox (mismo patrón de Docentes y Estudiantes) ── */
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
        document.addEventListener('click', function (e) { if (root.isConnected && !root.contains(e.target)) cerrar(); });
        // Escape cierra primero la lista abierta y no llega al asistente ni a la ficha
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root.isConnected && !panel.hidden) { cerrar(); e.stopImmediatePropagation(); }
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

    /* Opciones de estudiante para vincular: menores sin acudiente primero */
    function opcionesEstudiantes(excluir) {
        return estudiantes.filter(function (e) { return vigente(e) && excluir.indexOf(e.id) < 0; })
            .sort(function (x, y) {
                var px = edad(x.fecha_nac) < 18 && !x.acudiente_id ? 0 : 1;
                var py = edad(y.fecha_nac) < 18 && !y.acudiente_id ? 0 : 1;
                return px - py || x.nombres.localeCompare(y.nombres, 'es');
            })
            .map(function (e) {
                var sinAcu = edad(e.fecha_nac) < 18 && !e.acudiente_id;
                return { value: String(e.id), label: nombreCompleto(e) + ' · ' + e.documento + (sinAcu ? ' · menor sin acudiente' : '') };
            });
    }
    function pintarChips(cont, name) {
        cont.innerHTML = PARENTESCOS.map(function (p) {
            return '<label class="acu-chip"><input type="radio" name="' + name + '" value="' + p + '"><span>' + p + '</span></label>';
        }).join('');
    }

    /* ── Estado de la vista ── */
    var filtro = { programa: '', cuenta: '', alerta: '', texto: '' };
    var pagina = 1;

    var listaWrap = document.getElementById('acu-lista-wrap');
    var listaBody = document.getElementById('acu-lista-body');
    var emptyEl   = document.getElementById('acu-empty');
    var countEl   = document.getElementById('acu-count');
    var pagEl     = document.getElementById('acu-pagination');
    var pagPages  = document.getElementById('acu-pg-pages');
    var pagPrev   = document.getElementById('acu-pg-prev');
    var pagNext   = document.getElementById('acu-pg-next');
    var searchEl  = document.getElementById('acu-search');

    function coincide(a) {
        var q = filtro.texto;
        var hijos = aCargo(a);
        var okTexto = !q ||
            nombreCompleto(a).toLowerCase().indexOf(q) >= 0 ||
            String(a.documento).indexOf(q.replace(/\D/g, '') || '§') >= 0 ||
            hijos.some(function (e) { return nombreCompleto(e).toLowerCase().indexOf(q) >= 0; });
        return okTexto &&
            (!filtro.programa || hijos.some(function (e) { return grupoDe(e.grupo_id).programa === filtro.programa; })) &&
            (!filtro.cuenta || a.cuenta === filtro.cuenta) &&
            (!filtro.alerta || alertasDe(a).some(function (x) { return x.tipo === filtro.alerta; }));
    }

    function alertasIconos(a) {
        return alertasDe(a).map(function (x) {
            return '<span class="acu-alerta-ico acu-alerta-' + ALERTA_INFO[x.tipo].nivel + '" title="' + esc(x.texto) + '" role="img" aria-label="' + esc(x.texto) + '">' + ICONOS[x.tipo] + '</span>';
        }).join('');
    }

    function cargoHTML(a) {
        var hijos = aCargo(a);
        if (!hijos.length) return '<span class="acu-sin-cargo">Sin estudiantes vinculados</span>';
        var max = 2;
        return '<div class="acu-cargo-lista">' + hijos.slice(0, max).map(function (e) {
            var g = grupoDe(e.grupo_id);
            return '<div class="acu-cargo acu-esc-' + escClass(g.escuela) + (vigente(e) ? '' : ' is-fuera') + '">' +
                '<span class="acu-cargo-foto" aria-hidden="true">' + esc(iniciales(e)) + '</span>' +
                '<span class="acu-cargo-txt"><span class="acu-cargo-nombre">' + esc(nombreCompleto(e)) + '</span>' +
                '<span class="acu-cargo-sub">' + esc(e.parentesco) + ' · ' + (vigente(e) ? esc(corto(g.programa)) : ESTADOS_EST[e.estado].label) + '</span></span></div>';
        }).join('') + (hijos.length > max ? '<span class="acu-cargo-sub">y ' + (hijos.length - max) + ' más</span>' : '') + '</div>';
    }

    function filaHTML(a) {
        var al = alertasDe(a);
        var hijos = aCargo(a);
        return '<tr class="acu-fila is-' + a.cuenta + '" data-id="' + a.id + '">' +
            '<td><div class="acu-fila-acu"><span class="acu-fila-foto" aria-hidden="true">' + esc(iniciales(a)) + '</span>' +
                '<span class="acu-fila-datos"><span class="acu-fila-nombre">' + esc(nombreCompleto(a)) + '</span>' +
                '<span class="acu-fila-doc">' + esc(a.tipo_doc) + ' ' + fmtDoc(a.documento) + ' · ' + esc(a.ciudad) + '</span>' +
                // En pantallas angostas las columnas se pliegan aquí: estudiantes, alertas y WhatsApp
                '<span class="acu-fila-movil"><span class="acu-fila-movil-est">' + (hijos.length ? 'A cargo: ' + esc(hijos.map(primerNombre).join(', ')) : 'Sin estudiantes vinculados') + '</span>' +
                '<span class="acu-fila-movil-acc"><span class="acu-alertas">' + alertasIconos(a) + '</span>' +
                '<a class="acu-wa" href="' + waLink(a.telefono) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(primerNombre(a)) + '">' + ICONOS.whatsapp + '</a></span></span>' +
                '</span></div></td>' +
            '<td>' + cargoHTML(a) + '</td>' +
            '<td>' + cuentaTag(a) + '</td>' +
            '<td><span class="acu-alertas">' + (al.length ? alertasIconos(a) : '<span class="acu-sin-alertas">—</span>') + '</span></td>' +
            '<td><a class="acu-fila-wa" href="' + waLink(a.telefono) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(primerNombre(a)) + '">' + ICONOS.whatsapp + fmtTel(a.telefono) + '</a></td>' +
            '<td class="acu-fila-acc"><button type="button" class="acu-icon-btn-fila" title="Ver ficha" aria-label="Ver ficha de ' + esc(nombreCompleto(a)) + '">' + ICONOS.ojo + '</button></td>' +
        '</tr>';
    }

    /* ── Render principal ── */
    function render() {
        // Orden alfabético por apellido; los registrados en esta sesión van primero
        var lista = data.filter(coincide).sort(function (x, y) {
            return (y.reciente ? 1 : 0) - (x.reciente ? 1 : 0) || x.apellidos.localeCompare(y.apellidos, 'es');
        });

        // KPIs (sobre todos los acudientes, como en Docentes)
        setTxt('acu-kpi-total', data.length);
        setTxt('acu-kpi-portal', data.filter(function (a) { return a.cuenta === 'activa'; }).length);
        setTxt('acu-kpi-menores', estudiantes.filter(function (e) { return vigente(e) && edad(e.fecha_nac) < 18 && !e.acudiente_id; }).length);
        setTxt('acu-kpi-estudiantes', estudiantes.filter(function (e) { return vigente(e) && e.acudiente_id; }).length);
        opcionesAlerta();

        var paginas = Math.max(1, Math.ceil(lista.length / PAGE_SIZE));
        if (pagina > paginas) pagina = paginas;
        var desde = (pagina - 1) * PAGE_SIZE;

        countEl.textContent = lista.length + (lista.length === 1 ? ' acudiente' : ' acudientes');
        emptyEl.hidden = lista.length > 0;
        listaWrap.hidden = !lista.length;
        listaBody.innerHTML = lista.slice(desde, desde + PAGE_SIZE).map(filaHTML).join('');

        pagEl.hidden = paginas <= 1;
        pagPrev.disabled = pagina === 1;
        pagNext.disabled = pagina === paginas;
        pagPages.innerHTML = '';
        for (var p = 1; p <= paginas; p++) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'acu-pg-num' + (p === pagina ? ' is-active' : '');
            b.textContent = p;
            b.setAttribute('aria-label', 'Página ' + p);
            if (p === pagina) b.setAttribute('aria-current', 'page');
            b.dataset.p = p;
            pagPages.appendChild(b);
        }
    }
    function setTxt(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

    function irAPagina(p) {
        pagina = p;
        render();
        document.querySelector('.acu-toolbar').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    pagPages.addEventListener('click', function (e) { var b = e.target.closest('.acu-pg-num'); if (b) irAPagina(parseInt(b.dataset.p)); });
    pagPrev.addEventListener('click', function () { if (pagina > 1) irAPagina(pagina - 1); });
    pagNext.addEventListener('click', function () { irAPagina(pagina + 1); });

    /* ── Filtros ── */
    searchEl.addEventListener('input', function () { filtro.texto = searchEl.value.toLowerCase().trim(); pagina = 1; render(); });

    var cbPrograma = document.getElementById('acu-cb-programa');
    var cbCuenta   = document.getElementById('acu-cb-cuenta');
    var cbAlerta   = document.getElementById('acu-cb-alerta');
    [cbPrograma, cbCuenta, cbAlerta].forEach(initCombobox);

    var programas = [];
    grupos.forEach(function (g) { if (programas.indexOf(g.programa) < 0) programas.push(g.programa); });
    cbPrograma.setOptions([{ value: '', label: 'Todos los programas' }].concat(programas.map(function (p) { return { value: p, label: corto(p) }; })));
    cbPrograma.setValue('', 'Todos los programas', { silent: true });
    cbCuenta.setOptions([{ value: '', label: 'Todas las cuentas' }].concat(Object.keys(CUENTAS).map(function (k) { return { value: k, label: CUENTAS[k].label }; })));
    cbCuenta.setValue('', 'Todas las cuentas', { silent: true });

    // Opciones de alerta con su conteo; se recalculan al cambiar los datos
    function opcionesAlerta() {
        var conteo = { pago: 0, asistencia: 0, vinculo: 0, correo: 0 };
        data.forEach(function (a) {
            var tipos = [];
            alertasDe(a).forEach(function (x) { if (tipos.indexOf(x.tipo) < 0) tipos.push(x.tipo); });
            tipos.forEach(function (t) { conteo[t]++; });
        });
        cbAlerta.setOptions([{ value: '', label: 'Todas las alertas' }].concat(Object.keys(ALERTA_INFO).map(function (k) {
            return { value: k, label: ALERTA_INFO[k].titulo + ' (' + conteo[k] + ')' };
        })));
        var sel = filtro.alerta;
        cbAlerta.setValue(sel, sel ? ALERTA_INFO[sel].titulo + ' (' + conteo[sel] + ')' : 'Todas las alertas', { silent: true });
    }

    document.getElementById('acu-val-programa').addEventListener('change', function (e) { filtro.programa = e.target.value; pagina = 1; render(); });
    document.getElementById('acu-val-cuenta').addEventListener('change', function (e) { filtro.cuenta = e.target.value; pagina = 1; render(); });
    document.getElementById('acu-val-alerta').addEventListener('change', function (e) { filtro.alerta = e.target.value; pagina = 1; render(); });

    function limpiarFiltros() {
        filtro = { programa: '', cuenta: '', alerta: '', texto: '' };
        searchEl.value = '';
        cbPrograma.setValue('', 'Todos los programas', { silent: true });
        cbCuenta.setValue('', 'Todas las cuentas', { silent: true });
        pagina = 1;
        render();
    }
    document.getElementById('acu-limpiar').addEventListener('click', limpiarFiltros);

    // Toda la fila abre la ficha (el enlace de WhatsApp no)
    listaBody.addEventListener('click', function (e) {
        if (e.target.closest('.acu-wa, .acu-fila-wa')) return;
        var fila = e.target.closest('.acu-fila');
        if (fila) abrirFicha(parseInt(fila.dataset.id), fila.querySelector('.acu-icon-btn-fila'));
    });

    /* ── Aviso ── */
    var toastEl = document.getElementById('acu-toast');
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

    /* ══ Ficha del acudiente ══ */
    var drawer      = document.getElementById('acu-drawer');
    var fichaEl     = document.getElementById('acu-ficha');
    var accionEl    = document.getElementById('acu-accion');
    var fichaFoot   = document.getElementById('acu-ficha-foot');
    var accionFoot  = document.getElementById('acu-accion-foot');
    var fiTabs      = document.getElementById('acu-fi-tabs');
    var fiPanel     = document.getElementById('acu-fi-panel');
    var accionError = document.getElementById('acu-accion-error');
    var actual = null, tabActual = 'datos', accionActual = null, accionEst = null, origenFoco = null;

    function abrirFicha(id, origen) {
        actual = acuDe(id);
        if (!actual) return;
        origenFoco = origen || null;
        tabActual = 'datos';
        mostrarFicha();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        setTimeout(function () { document.getElementById('acu-drawer-close').focus(); }, 60);
    }
    function cerrarFicha() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (origenFoco && document.body.contains(origenFoco)) origenFoco.focus();
    }
    document.getElementById('acu-drawer-overlay').addEventListener('click', cerrarFicha);
    document.getElementById('acu-drawer-close').addEventListener('click', cerrarFicha);

    function mostrarFicha() {
        accionActual = null;
        fichaEl.hidden = false; fichaFoot.hidden = false; fiTabs.hidden = false;
        accionEl.hidden = true; accionFoot.hidden = true;
        pintarCabecera(actual);
        document.querySelectorAll('.acu-fi-tab').forEach(function (t) {
            t.classList.toggle('is-active', t.dataset.tab === tabActual);
            t.setAttribute('aria-selected', t.dataset.tab === tabActual ? 'true' : 'false');
        });
        fiPanel.innerHTML = panelFicha(tabActual);
        // Mismo botón de activar/desactivar que la ficha de Docentes
        var btnCuenta = document.getElementById('acu-btn-cuenta');
        var inactiva = actual.cuenta === 'inactiva';
        btnCuenta.textContent = inactiva ? 'Activar cuenta' : 'Desactivar cuenta';
        btnCuenta.className = 'acu-btn ' + (inactiva ? 'acu-btn-secondary' : 'acu-btn-danger');
    }
    function pintarCabecera(a) {
        var av = document.getElementById('acu-fi-avatar');
        av.textContent = iniciales(a);
        av.className = 'acu-drawer-avatar' + (a.cuenta === 'inactiva' ? ' is-inactiva' : '');
        document.getElementById('acu-fi-nombre').textContent = nombreCompleto(a);
        document.getElementById('acu-fi-doc').textContent = a.tipo_doc + ' ' + fmtDoc(a.documento) + (a.ocupacion ? ' · ' + a.ocupacion : '') + ' · ' + a.ciudad;
        var n = aCargo(a).filter(vigente).length;
        document.getElementById('acu-fi-badges').innerHTML = cuentaTag(a) +
            '<span class="app-status-tag app-status-muted">' + (n === 1 ? '1 estudiante vigente' : n + ' estudiantes vigentes') + '</span>';
    }

    fiTabs.addEventListener('click', function (e) {
        var t = e.target.closest('.acu-fi-tab');
        if (!t) return;
        tabActual = t.dataset.tab;
        mostrarFicha();
    });

    function dato(lbl, valor, completo) {
        return '<div class="acu-info-item' + (completo ? ' acu-info-full' : '') + '"><span class="acu-info-lbl">' + lbl + '</span><span class="acu-info-val">' + valor + '</span></div>';
    }
    function seccion(titulo, cuerpo, extra) {
        return '<section class="acu-sec"><div class="acu-sec-head"><h4 class="acu-sec-title">' + titulo + '</h4>' + (extra || '') + '</div>' + cuerpo + '</section>';
    }
    function contactoHTML(a) {
        return '<a class="acu-contacto" href="tel:+57' + a.telefono + '">' + ICONOS.telefono + fmtTel(a.telefono) + '</a>' +
               '<a class="acu-contacto acu-contacto-wa" href="' + waLink(a.telefono) + '" target="_blank" rel="noopener">' + ICONOS.whatsapp + 'WhatsApp</a>' +
               (a.email ? '<a class="acu-contacto" href="mailto:' + esc(a.email) + '">' + ICONOS.correo + esc(a.email) + '</a>' : '');
    }

    function estCardHTML(e) {
        var g = grupoDe(e.grupo_id), ed = edad(e.fecha_nac);
        var vig = vigente(e);
        return '<article class="acu-est-card acu-esc-' + escClass(g.escuela) + (vig ? '' : ' is-fuera') + '">' +
            '<div class="acu-est-top">' +
                '<span class="acu-est-foto" aria-hidden="true">' + esc(iniciales(e)) + '</span>' +
                '<div class="acu-est-info"><strong>' + esc(nombreCompleto(e)) + '</strong>' +
                '<span>' + esc(e.parentesco) + ' · ' + esc(e.tipo_doc) + ' ' + fmtDoc(e.documento) + ' · ' + ed + ' años</span></div>' +
                '<div class="acu-est-tags">' + tag(ESTADOS_EST[e.estado]) + '</div>' +
            '</div>' +
            '<div class="acu-est-datos">' +
                dato('Programa', esc(corto(g.programa)), true) +
                dato('Grupo', esc(g.grupo) + ' · ' + esc(g.jornada)) +
                dato('Pago', vig ? tag(PAGOS[e.pago]) : '—') +
                dato('Asistencia', !vig ? '—' : (e.asistencia === null ? 'Sin clases aún' : e.asistencia + ' %')) +
            '</div>' +
            '<div class="acu-est-pie"><button type="button" class="acu-link-btn" data-accion="desvincular" data-est="' + e.id + '">' + ICONOS.quitar + 'Desvincular</button></div>' +
        '</article>';
    }

    function panelFicha(tab) {
        var a = actual, html = '';
        var al = alertasDe(a);

        if (tab === 'datos') {
            if (al.length) {
                html += '<ul class="acu-fi-alertas">' + al.map(function (x) {
                    return '<li class="acu-alerta-' + ALERTA_INFO[x.tipo].nivel + '">' + ICONOS[x.tipo] + esc(x.texto) + '</li>';
                }).join('') + '</ul>';
            }
            html += '<div class="acu-info-grid">' +
                dato('Celular', fmtTel(a.telefono)) +
                dato('Correo electrónico', a.email ? esc(a.email) : '—') +
                dato('Tipo de documento', TIPOS_DOC[a.tipo_doc] || esc(a.tipo_doc)) +
                dato('Número de documento', fmtDoc(a.documento)) +
                dato('Ocupación', a.ocupacion ? esc(a.ocupacion) : '—') +
                dato('Registrado desde', fmtFecha(a.fecha_registro)) +
                dato('Dirección', esc(a.direccion) + ', ' + esc(a.ciudad) + ' (' + esc(a.departamento) + ')', true) +
            '</div>' +
            seccion('Contacto rápido', '<div class="acu-contactos">' + contactoHTML(a) + '</div>');
        }

        if (tab === 'estudiantes') {
            var hijos = aCargo(a);
            if (hijos.length) {
                html += '<div class="acu-est-lista">' + hijos.map(estCardHTML).join('') + '</div>';
            } else {
                html += '<div class="acu-callout"><strong>No tiene estudiantes vinculados</strong>' +
                    '<p>Vincula al estudiante del que es responsable para que pueda seguir sus notas, su asistencia y sus pagos desde el portal.</p></div>';
            }
            html += '<div class="acu-sec-acc"><button type="button" class="acu-btn ' + (hijos.length ? 'acu-btn-secondary' : 'acu-btn-primary') + ' acu-btn-sm" data-accion="vincular">' + ICONOS.mas + 'Vincular estudiante</button></div>';
        }

        if (tab === 'cuenta') {
            if (a.cuenta === 'inactiva') {
                html += '<div class="acu-callout acu-callout-alerta"><strong>La cuenta está desactivada</strong>' +
                    '<p>No puede ingresar al portal de acudientes. Sigue vinculado a sus estudiantes y puedes contactarlo normalmente.</p></div>';
            } else if (a.cuenta === 'sin_ingreso') {
                var msg = 'Hola ' + primerNombre(a) + ', le escribimos de ESAT. Ya puede ingresar al portal de acudientes: su usuario y su contraseña inicial son su número de cédula, sin puntos.';
                html += '<div class="acu-callout acu-callout-aviso"><strong>Todavía no ha ingresado al portal</strong>' +
                    '<p>Recuérdale que su usuario y su contraseña inicial son su número de cédula.</p></div>' +
                    '<div class="acu-sec-acc"><a class="acu-contacto acu-contacto-wa" href="' + waLink(a.telefono, msg) + '" target="_blank" rel="noopener">' + ICONOS.whatsapp + 'Enviar recordatorio por WhatsApp</a></div>';
            }
            html += '<div class="acu-info-grid">' +
                dato('Usuario', '<span class="acu-num-inline">' + esc(a.documento) + '</span>') +
                dato('Estado de la cuenta', cuentaTag(a)) +
                dato('Último ingreso', a.ultimo_ingreso ? fmtFecha(a.ultimo_ingreso) : 'Nunca') +
                dato('Cuenta creada', fmtFecha(a.fecha_registro)) +
            '</div>' +
            seccion('Qué puede ver en el portal',
                '<p class="acu-sec-nota">Las notas, la asistencia, el horario y el estado de pagos de sus estudiantes vigentes. No puede modificar ninguna información.</p>');
        }
        return html;
    }

    /* ── Acciones dentro de la ficha ── */
    var accionTtl    = document.getElementById('acu-accion-ttl');
    var accionSub    = document.getElementById('acu-accion-sub');
    var accionCuerpo = document.getElementById('acu-accion-cuerpo');
    var btnConfirmar = document.getElementById('acu-accion-confirmar');
    var cbVincular   = null;

    function mostrarAccion(tipo, estId) {
        if (tipo === 'editar') { abrirModal(actual); return; }
        accionActual = tipo;
        accionEst = estId ? estDe(estId) : null;
        fichaEl.hidden = true; fichaFoot.hidden = true; fiTabs.hidden = true;
        accionEl.hidden = false; accionFoot.hidden = false;
        accionError.hidden = true;
        var a = actual;
        btnConfirmar.textContent = 'Confirmar';
        btnConfirmar.className = 'acu-btn acu-btn-primary';

        if (tipo === 'vincular') {
            accionTtl.textContent = 'Vincular estudiante';
            accionSub.textContent = 'Los menores de edad sin acudiente aparecen de primeros en la lista.';
            accionCuerpo.innerHTML =
                '<div class="acu-field"><span class="acu-lbl">Estudiante</span>' +
                '<div class="app-combobox" id="acc-cb-est">' +
                    '<button type="button" class="app-combobox-trigger" data-combobox-trigger><span data-combobox-label>Busca por nombre o documento</span>' +
                    '<svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></button>' +
                    '<div class="app-combobox-panel" data-combobox-panel hidden><div class="app-combobox-search-wrap">' +
                    '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                    '<input type="text" class="app-combobox-search" data-combobox-search placeholder="Nombre o documento" autocomplete="off"></div>' +
                    '<ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>' +
                    '<input type="hidden" id="acc-est" data-combobox-value></div></div>' +
                '<div id="acc-reemplazo"></div>' +
                '<div class="acu-field"><span class="acu-lbl">Parentesco con el estudiante</span><div class="acu-chips" id="acc-par" role="radiogroup" aria-label="Parentesco"></div></div>';
            cbVincular = document.getElementById('acc-cb-est');
            initCombobox(cbVincular);
            cbVincular.setOptions(opcionesEstudiantes(aCargo(a).map(function (e) { return e.id; })));
            pintarChips(document.getElementById('acc-par'), 'acc-par');
            document.getElementById('acc-est').addEventListener('change', function (ev) {
                document.getElementById('acc-reemplazo').innerHTML = avisoReemplazo(estDe(parseInt(ev.target.value)), a.id);
            });
            btnConfirmar.textContent = 'Vincular';
        }
        if (tipo === 'desvincular') {
            var e = accionEst, menor = edad(e.fecha_nac) < 18 && vigente(e);
            accionTtl.textContent = 'Desvincular estudiante';
            accionSub.textContent = '';
            accionCuerpo.innerHTML = '<div class="acu-callout' + (menor ? ' acu-callout-alerta' : '') + '">' +
                '<strong>' + esc(nombreCompleto(a)) + ' dejará de ser acudiente de ' + esc(nombreCompleto(e)) + '</strong>' +
                '<p>' + (menor
                    ? esc(primerNombre(e)) + ' tiene ' + edad(e.fecha_nac) + ' años y quedará sin acudiente. Aparecerá en las alertas de Estudiantes hasta que registres a otra persona responsable.'
                    : 'Ya no verá las notas, la asistencia ni los pagos de ' + esc(primerNombre(e)) + ' en el portal.') + '</p></div>';
            btnConfirmar.textContent = 'Desvincular';
            btnConfirmar.className = 'acu-btn acu-btn-danger';
        }
        if (tipo === 'clave') {
            accionTtl.textContent = 'Restablecer contraseña';
            accionSub.textContent = '';
            accionCuerpo.innerHTML = '<div class="acu-callout"><strong>La contraseña de ' + esc(primerNombre(a)) + ' volverá a ser su número de documento</strong>' +
                '<p>Podrá ingresar con el usuario y la contraseña <strong class="acu-num-inline">' + esc(a.documento) + '</strong> (sin puntos) y el sistema le pedirá crear una nueva. Su contraseña actual dejará de funcionar.</p></div>';
            btnConfirmar.textContent = 'Restablecer contraseña';
        }
        if (tipo === 'cuenta') {
            var activar = a.cuenta === 'inactiva';
            accionTtl.textContent = activar ? 'Activar cuenta' : 'Desactivar cuenta';
            accionSub.textContent = '';
            accionCuerpo.innerHTML = activar
                ? '<div class="acu-callout"><strong>' + esc(primerNombre(a)) + ' podrá ingresar de nuevo al portal</strong><p>Usará el mismo usuario y la misma contraseña que tenía antes de la desactivación.</p></div>'
                : '<div class="acu-callout acu-callout-alerta"><strong>' + esc(primerNombre(a)) + ' no podrá ingresar al portal de acudientes</strong><p>Sigue vinculado a sus estudiantes y conserva sus datos. Puedes activar la cuenta de nuevo cuando quieras.</p></div>';
            btnConfirmar.textContent = activar ? 'Activar cuenta' : 'Desactivar cuenta';
            btnConfirmar.className = 'acu-btn ' + (activar ? 'acu-btn-primary' : 'acu-btn-danger');
        }
        accionEl.scrollTop = 0;
        setTimeout(function () { document.getElementById('acu-accion-volver').focus(); }, 30);
    }

    /* Aviso cuando el estudiante ya tiene otro acudiente: al guardar se reemplaza */
    function avisoReemplazo(e, acuId) {
        if (!e || !e.acudiente_id || e.acudiente_id === acuId) return '';
        var otro = acuDe(e.acudiente_id);
        return '<div class="acu-callout acu-callout-aviso"><strong>' + esc(primerNombre(e)) + ' ya tiene acudiente: ' + esc(nombreCompleto(otro)) + ' (' + esc(e.parentesco) + ')</strong>' +
            '<p>Cada estudiante tiene un solo acudiente. Al guardar, ' + esc(primerNombre(otro)) + ' dejará de serlo.</p></div>';
    }

    function errorAccion(msg, campoId) {
        accionError.textContent = msg;
        accionError.hidden = false;
        if (campoId) {
            var c = document.getElementById(campoId);
            if (c) { c.classList.add('is-invalid'); (c.querySelector('[data-combobox-trigger]') || c).focus(); }
        }
    }

    fichaFoot.addEventListener('click', function (e) { var b = e.target.closest('[data-accion]'); if (b && !b.disabled) mostrarAccion(b.dataset.accion); });
    fiPanel.addEventListener('click', function (e) {
        var b = e.target.closest('[data-accion]');
        if (b) mostrarAccion(b.dataset.accion, b.dataset.est ? parseInt(b.dataset.est) : null);
    });
    document.getElementById('acu-accion-volver').addEventListener('click', mostrarFicha);
    document.getElementById('acu-accion-cancelar').addEventListener('click', mostrarFicha);
    accionEl.addEventListener('change', function (e) {
        var cb = e.target.closest('.app-combobox');
        if (cb) cb.classList.remove('is-invalid');
        accionError.hidden = true;
    });

    btnConfirmar.addEventListener('click', function () {
        var a = actual, msg = '';
        accionError.hidden = true;

        if (accionActual === 'vincular') {
            var estId = parseInt(cbVincular.value());
            if (!estId) return errorAccion('Selecciona el estudiante.', 'acc-cb-est');
            var par = (accionCuerpo.querySelector('input[name="acc-par"]:checked') || {}).value;
            if (!par) return errorAccion('Selecciona el parentesco con el estudiante.');
            var e = estDe(estId);
            e.acudiente_id = a.id;
            e.parentesco = par;
            tabActual = 'estudiantes';
            msg = nombreCompleto(a) + ' quedó como acudiente de ' + nombreCompleto(e) + '.';
        }
        if (accionActual === 'desvincular') {
            accionEst.acudiente_id = null;
            accionEst.parentesco = null;
            tabActual = 'estudiantes';
            msg = nombreCompleto(a) + ' ya no es acudiente de ' + nombreCompleto(accionEst) + '.';
        }
        if (accionActual === 'clave') {
            msg = 'La contraseña de ' + nombreCompleto(a) + ' volvió a ser su número de documento.';
        }
        if (accionActual === 'cuenta') {
            if (a.cuenta === 'inactiva') {
                a.cuenta = a.ultimo_ingreso ? 'activa' : 'sin_ingreso';
                msg = 'La cuenta de ' + nombreCompleto(a) + ' está activa de nuevo.';
            } else {
                a.cuenta = 'inactiva';
                msg = 'La cuenta de ' + nombreCompleto(a) + ' quedó desactivada.';
            }
        }

        render();
        mostrarFicha();
        toast(msg);
    });

    /* ══ Asistente: Registrar / editar acudiente ══ */
    var modal    = document.getElementById('acu-modal');
    var paso     = 1;
    var TOTAL    = 3;
    var editando = null;   // acudiente en edición o null si es nuevo
    var vinculos = [];     // [{ est_id, parentesco }]
    var regError = document.getElementById('reg-error');
    var btnSig   = document.getElementById('acu-btn-siguiente');
    var btnAtras = document.getElementById('acu-btn-atras');

    var cbTipo   = document.getElementById('reg-cb-tipo');
    var cbDepto  = document.getElementById('reg-cb-depto');
    var cbCiudad = document.getElementById('reg-cb-ciudad');
    var cbEst    = document.getElementById('reg-cb-estudiante');
    [cbTipo, cbDepto, cbCiudad, cbEst].forEach(initCombobox);
    cbTipo.setOptions(Object.keys(TIPOS_DOC).map(function (k) { return { value: k, label: TIPOS_DOC[k] }; }));
    pintarChips(document.getElementById('reg-parentesco'), 'reg-par');

    // Departamentos y municipios reales de Colombia
    var colombia = null;
    var colombiaListo = fetch('/assets/data/co-departamentos-ciudades.json')
        .then(function (r) { return r.json(); })
        .then(function (d) { colombia = d; cbDepto.setOptions(d.map(function (x) { return x.departamento; })); });
    function poblarCiudades(depto) {
        var dep = colombia && colombia.find(function (x) { return x.departamento === depto; });
        cbCiudad.reset(dep ? 'Selecciona un municipio' : 'Elige el departamento primero');
        cbCiudad.setOptions(dep ? dep.ciudades : []);
    }
    document.getElementById('reg-depto').addEventListener('change', function (e) { poblarCiudades(e.target.value); });

    function val(id) { return document.getElementById(id).value.trim(); }
    function setVal(id, v) { document.getElementById(id).value = v || ''; }

    function abrirModal(acu) {
        editando = acu || null;
        paso = 1;
        modal.querySelectorAll('input.acu-input').forEach(function (i) { i.value = ''; i.classList.remove('is-invalid'); i.disabled = false; });
        modal.querySelectorAll('input[type=radio]').forEach(function (r) { r.checked = false; });
        cbTipo.reset(); cbDepto.reset(); cbCiudad.reset('Elige el departamento primero'); cbCiudad.setOptions([]); cbEst.reset();
        document.getElementById('reg-par-wrap').hidden = true;
        document.getElementById('reg-reemplazo').innerHTML = '';
        vinculos = [];

        document.getElementById('acu-modal-ttl').textContent = editando ? 'Editar acudiente' : 'Registrar acudiente';
        document.getElementById('reg-nota-clave').hidden = !!editando;
        if (editando) {
            setVal('reg-nombres', editando.nombres);
            setVal('reg-apellidos', editando.apellidos);
            cbTipo.setValue(editando.tipo_doc, TIPOS_DOC[editando.tipo_doc], { silent: true });
            setVal('reg-documento', editando.documento);
            // El documento es el usuario del portal: no se cambia desde aquí
            document.getElementById('reg-documento').disabled = true;
            setVal('reg-ocupacion', editando.ocupacion);
            setVal('reg-telefono', editando.telefono);
            setVal('reg-email', editando.email);
            setVal('reg-direccion', editando.direccion);
            colombiaListo.then(function () {
                cbDepto.setValue(editando.departamento, editando.departamento, { silent: true });
                poblarCiudades(editando.departamento);
                cbCiudad.setValue(editando.ciudad, editando.ciudad, { silent: true });
            });
            vinculos = aCargo(editando).map(function (e) { return { est_id: e.id, parentesco: e.parentesco }; });
        }
        pintarVinculos();
        pintarPaso();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { document.getElementById('reg-nombres').focus(); }, 80);
    }
    function cerrarModal() {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (editando && drawer.classList.contains('is-open')) fichaFoot.querySelector('[data-accion="editar"]').focus();
        else document.getElementById('acu-btn-nuevo').focus();
    }
    document.getElementById('acu-btn-nuevo').addEventListener('click', function () { abrirModal(null); });
    document.getElementById('acu-modal-close').addEventListener('click', cerrarModal);
    document.getElementById('acu-modal-overlay').addEventListener('click', cerrarModal);

    function pintarPaso() {
        modal.querySelectorAll('.acu-mstep').forEach(function (s) { s.classList.toggle('is-active', +s.dataset.step === paso); });
        modal.querySelectorAll('.acu-wstep').forEach(function (s) {
            var n = +s.dataset.step;
            s.classList.toggle('is-active', n === paso);
            s.classList.toggle('is-done', n < paso);
            if (n === paso) s.setAttribute('aria-current', 'step'); else s.removeAttribute('aria-current');
        });
        document.getElementById('acu-modal-paso').textContent = 'Paso ' + paso + ' de ' + TOTAL;
        btnAtras.style.visibility = paso === 1 ? 'hidden' : 'visible';
        btnSig.textContent = paso === TOTAL ? (editando ? 'Guardar cambios' : 'Registrar acudiente') : 'Siguiente';
        regError.hidden = true;
        if (paso === 3) pintarResumen();
        modal.querySelector('.acu-modal-body').scrollTop = 0;
    }

    /* Paso 3: estudiantes vinculados */
    var regEstVal = document.getElementById('reg-estudiante');
    regEstVal.addEventListener('change', function () {
        var e = estDe(parseInt(regEstVal.value));
        document.getElementById('reg-par-wrap').hidden = !e;
        document.getElementById('reg-reemplazo').innerHTML = avisoReemplazo(e, editando ? editando.id : null);
    });

    function pintarVinculos() {
        var ul = document.getElementById('reg-vinculos');
        document.getElementById('reg-vinc-count').textContent = vinculos.length;
        cbEst.setOptions(opcionesEstudiantes(vinculos.map(function (v) { return v.est_id; })));
        if (!vinculos.length) {
            ul.innerHTML = '<li class="acu-vinculos-vacio">Todavía no has agregado estudiantes.</li>';
            return;
        }
        ul.innerHTML = vinculos.map(function (v, i) {
            var e = estDe(v.est_id), g = grupoDe(e.grupo_id);
            var otro = e.acudiente_id && (!editando || e.acudiente_id !== editando.id) ? acuDe(e.acudiente_id) : null;
            return '<li class="acu-vinculo acu-esc-' + escClass(g.escuela) + '">' +
                '<span class="acu-est-foto" aria-hidden="true">' + esc(iniciales(e)) + '</span>' +
                '<span class="acu-est-info"><strong>' + esc(nombreCompleto(e)) + '</strong>' +
                '<span>' + esc(v.parentesco) + ' · ' + esc(corto(g.programa)) + ' · ' + esc(g.grupo) + '</span>' +
                (otro ? '<span class="acu-vinculo-aviso">Reemplaza a ' + esc(nombreCompleto(otro)) + ' como acudiente</span>' : '') + '</span>' +
                '<button type="button" class="acu-icon-btn" data-quitar="' + i + '" aria-label="Quitar a ' + esc(nombreCompleto(e)) + '" title="Quitar">' + ICONOS.quitar + '</button>' +
            '</li>';
        }).join('');
    }
    document.getElementById('reg-vinculos').addEventListener('click', function (e) {
        var b = e.target.closest('[data-quitar]');
        if (!b) return;
        vinculos.splice(parseInt(b.dataset.quitar), 1);
        pintarVinculos();
        pintarResumen();
    });
    document.getElementById('reg-agregar').addEventListener('click', function () {
        regError.hidden = true;
        var estId = parseInt(cbEst.value());
        if (!estId) return errorReg('Selecciona el estudiante que quieres agregar.', 'reg-cb-estudiante');
        var par = (modal.querySelector('input[name="reg-par"]:checked') || {}).value;
        if (!par) return errorReg('Selecciona el parentesco con el estudiante.');
        vinculos.push({ est_id: estId, parentesco: par });
        cbEst.reset();
        modal.querySelectorAll('input[name="reg-par"]').forEach(function (r) { r.checked = false; });
        document.getElementById('reg-par-wrap').hidden = true;
        document.getElementById('reg-reemplazo').innerHTML = '';
        pintarVinculos();
        pintarResumen();
    });

    function pintarResumen() {
        var res = document.getElementById('reg-resumen');
        if (!vinculos.length) { res.hidden = true; return; }
        res.innerHTML = '<h4>' + (editando ? 'Resumen de los cambios' : 'Resumen del registro') + '</h4><div class="acu-info-grid">' +
            dato('Acudiente', esc(val('reg-nombres') + ' ' + val('reg-apellidos'))) +
            dato('Documento', esc(cbTipo.value()) + ' ' + fmtDoc(val('reg-documento').replace(/\D/g, ''))) +
            dato('Celular', fmtTel(val('reg-telefono').replace(/\D/g, ''))) +
            dato('Ciudad', esc(cbCiudad.value() || '—')) +
            dato('Estudiantes a cargo', vinculos.map(function (v) { return esc(nombreCompleto(estDe(v.est_id))) + ' (' + esc(v.parentesco) + ')'; }).join(', '), true) +
            (editando ? '' : dato('Usuario y contraseña inicial', '<span class="acu-num-inline">' + esc(val('reg-documento').replace(/\D/g, '')) + '</span>', true)) +
        '</div>';
        res.hidden = false;
    }

    function errorReg(msg, campo) {
        regError.textContent = msg;
        regError.hidden = false;
        if (campo) {
            var el = document.getElementById(campo);
            if (el) {
                el.classList.add('is-invalid');
                (el.classList.contains('app-combobox') ? el.querySelector('[data-combobox-trigger]') : el).focus();
            }
        }
        return false;
    }

    function validarPaso() {
        if (paso === 1) {
            if (val('reg-nombres').length < 2) return errorReg('Escribe los nombres del acudiente.', 'reg-nombres');
            if (val('reg-apellidos').length < 2) return errorReg('Escribe los apellidos del acudiente.', 'reg-apellidos');
            if (!cbTipo.value()) return errorReg('Selecciona el tipo de documento.', 'reg-cb-tipo');
            if (!editando) {
                var doc = val('reg-documento').replace(/\D/g, '');
                if (doc.length < 6 || doc.length > 11) return errorReg('El número de documento debe tener entre 6 y 11 dígitos.', 'reg-documento');
                var dup = data.find(function (x) { return x.documento === doc; });
                if (dup) return errorReg('Ya existe un acudiente con ese documento: ' + nombreCompleto(dup) + '. Ábrelo desde la tabla para vincularle más estudiantes.', 'reg-documento');
                var est = estudiantes.find(function (x) { return x.documento === doc; });
                if (est) return errorReg('Ese documento pertenece a un estudiante (' + nombreCompleto(est) + '). Un estudiante no puede ser su propio acudiente.', 'reg-documento');
            }
        }
        if (paso === 2) {
            var tel = val('reg-telefono').replace(/\D/g, '');
            var email = val('reg-email');
            if (!/^3\d{9}$/.test(tel)) return errorReg('El celular debe tener 10 dígitos y empezar por 3.', 'reg-telefono');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return errorReg('El correo no es válido. Revisa que tenga @ y un dominio.', 'reg-email');
            if (val('reg-direccion').length < 5) return errorReg('Escribe la dirección de residencia.', 'reg-direccion');
            if (!cbDepto.value()) return errorReg('Selecciona el departamento.', 'reg-cb-depto');
            if (!cbCiudad.value()) return errorReg('Selecciona la ciudad o municipio.', 'reg-cb-ciudad');
        }
        if (paso === 3) {
            if (!editando && !vinculos.length) return errorReg('Agrega al menos un estudiante a cargo.', 'reg-cb-estudiante');
            if (cbEst.value()) return errorReg('Seleccionaste un estudiante pero no lo agregaste. Pulsa «Agregar estudiante» o quita la selección.', 'reg-cb-estudiante');
        }
        return true;
    }

    btnSig.addEventListener('click', function () {
        regError.hidden = true;
        if (!validarPaso()) return;
        if (paso < TOTAL) { paso++; pintarPaso(); return; }
        guardar();
    });
    btnAtras.addEventListener('click', function () { if (paso > 1) { paso--; pintarPaso(); } });
    modal.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); regError.hidden = true; });
    modal.addEventListener('change', function (e) {
        var cb = e.target.closest('.app-combobox');
        if (cb) cb.classList.remove('is-invalid');
        regError.hidden = true;
    });

    function guardar() {
        var campos = {
            nombres: val('reg-nombres'), apellidos: val('reg-apellidos'), tipo_doc: cbTipo.value(),
            ocupacion: val('reg-ocupacion'), telefono: val('reg-telefono').replace(/\D/g, ''), email: val('reg-email'),
            direccion: val('reg-direccion'), departamento: cbDepto.value(), ciudad: cbCiudad.value()
        };
        var acu;
        if (editando) {
            acu = editando;
            Object.keys(campos).forEach(function (k) { acu[k] = campos[k]; });
            // Los que ya no están en la lista quedan sin acudiente
            estudiantes.forEach(function (e) {
                if (e.acudiente_id === acu.id && !vinculos.some(function (v) { return v.est_id === e.id; })) { e.acudiente_id = null; e.parentesco = null; }
            });
        } else {
            acu = campos;
            acu.id = data.reduce(function (m, x) { return Math.max(m, x.id); }, 0) + 1;
            acu.documento = val('reg-documento').replace(/\D/g, '');
            acu.cuenta = 'sin_ingreso';
            acu.ultimo_ingreso = null;
            acu.fecha_registro = hoyISO();
            acu.reciente = true;
            data.unshift(acu);
        }
        vinculos.forEach(function (v) { var e = estDe(v.est_id); e.acudiente_id = acu.id; e.parentesco = v.parentesco; });

        var eraEdicion = !!editando;
        cerrarModal();
        if (eraEdicion) {
            render();
            if (drawer.classList.contains('is-open')) mostrarFicha();
            toast('Se guardaron los cambios de ' + nombreCompleto(acu) + '.');
        } else {
            limpiarFiltros();
            toast('Se registró a ' + nombreCompleto(acu) + ' como acudiente de ' + vinculos.map(function (v) { return primerNombre(estDe(v.est_id)); }).join(' y ') + '. Usuario y contraseña inicial: ' + acu.documento + '.');
        }
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
