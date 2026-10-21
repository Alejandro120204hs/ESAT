/* ESAT — Admin: Estudiantes */
(function () {
    'use strict';

    var data       = window.EST_DATA || [];
    var grupos     = window.EST_GRUPOS || [];
    var acudientes = window.EST_ACUDIENTES || [];
    var opciones   = window.EST_OPCIONES || {};
    var csrf       = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var FLASH      = 'esat.admin.estudiantes.aviso';
    var ABRIR      = 'esat.admin.estudiantes.abrir';   // ficha a reabrir tras recargar: {id, tab} o {nuevo: id}

    /* ── Backend ── */
    function enviar(metodo, url, cuerpo) {
        return fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) {
                    var err = new Error(j.message || 'No se pudo guardar. Intenta de nuevo.');
                    err.errores = j.errors || null;
                    throw err;
                }
                return j;
            });
        });
    }
    /* Primer mensaje de error del servidor (o el general) */
    function primerError(err) {
        var k = err.errores && Object.keys(err.errores)[0];
        return { campo: k || null, mensaje: k ? err.errores[k][0] : err.message };
    }
    /* Guarda el aviso (y qué ficha reabrir) y recarga: tabla, KPI y cupos salen del servidor */
    function recargarConAviso(msg, abrir) {
        try {
            sessionStorage.setItem(FLASH, msg);
            if (abrir) sessionStorage.setItem(ABRIR, JSON.stringify(abrir));
        } catch (e) {}
        location.reload();
    }

    var PAGE_SIZE = 12;
    var ESTADOS = {
        activo:   { label: 'Activo',   desc: 'Asiste a clases y ocupa un cupo en su grupo.' },
        aplazado: { label: 'Aplazado', desc: 'Suspende sus estudios por un tiempo y libera el cupo. Puede retomar después.' },
        retirado: { label: 'Retirado', desc: 'Deja el programa de forma definitiva y libera el cupo.' },
        graduado: { label: 'Graduado', desc: 'Terminó el programa. Solo aplica cuando su grupo ya finalizó.' },
        sin_matricula: { label: 'Sin matrícula', desc: 'Registrado, pero todavía no está matriculado en un programa.' }
    };
    /* Un estudiante sin matrícula no tiene grupo: se usa este "grupo vacío" para pintarlo */
    var SIN_GRUPO = { id: null, codigo: '', grupo: 'Sin grupo', programa: 'Sin matrícula', escuela: 'Sin matrícula',
                      docente: '', jornada: '', horario: [], cupo: 0, inscritos: 0, estado: '' };
    var ESCUELA_NOMBRE = {
        'Salud': 'Escuela de Salud', 'Cocina y Turismo': 'Escuela de Cocina y Turismo',
        'Administrativa': 'Escuela Administrativa', 'Educación e Idiomas': 'Escuela de Educación e Idiomas',
        'Deporte y Cultura': 'Escuela Deporte y Cultura', 'Ciencias': 'Escuela Ciencias', 'Belleza': 'Escuela de Belleza'
    };
    var PARENTESCOS = opciones.parentescos || ['Madre', 'Padre', 'Abuelo(a)', 'Tío(a)', 'Hermano(a)', 'Otro'];
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
                 'Deporte y Cultura':'dep','Ciencias':'cie','Belleza':'bel', 'Sin matrícula':'sin' }[e] || 'adm';
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
    // El teléfono puede venir de /profile: solo se formatea si son 10 dígitos, lo demás se escapa
    function fmtTel(t) { t = String(t == null ? '' : t); return /^\d{10}$/.test(t) ? t.slice(0,3) + ' ' + t.slice(3,6) + ' ' + t.slice(6) : esc(t); }
    function digitos(t) { return String(t == null ? '' : t).replace(/\D/g, ''); }
    function fmtFecha(f) {
        if (!f) return '—';
        var p = f.split('-');
        return parseInt(p[2]) + ' ' + MESES[parseInt(p[1]) - 1] + ' ' + p[0];
    }
    function fmtPesos(v) { return '$ ' + fmtDoc(v); }
    function grupoDe(id) { return (id && grupos.find(function (g) { return g.id === id; })) || SIN_GRUPO; }
    function corto(programa) { return programa.replace('Técnico Laboral en ', ''); }
    function acudienteDe(e) { return e.acudiente_id ? acudientes.find(function (a) { return a.id === e.acudiente_id; }) : null; }
    function waLink(tel) { return 'https://wa.me/57' + digitos(tel); }
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
        /* Se recoloca cuando la página se desplaza, pero no con el scroll de su propia
           lista: recolocar reinicia el alto de la lista y la devolvía al inicio. */
        function alScroll(e) {
            if (panel.contains(e.target)) return;
            posicionar();
        }
        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (searchInput) { searchInput.value = ''; filtrar(''); }
            posicionar();
            if (searchInput) searchInput.focus();
            window.addEventListener('scroll', alScroll, true);
            window.addEventListener('resize', posicionar);
        }
        function cerrar() {
            if (panel.hidden) return;
            panel.hidden = true;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            window.removeEventListener('scroll', alScroll, true);
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
    var listaActual = [];   // lo que se ve con los filtros: es lo que se exporta

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
            (e.codigo || '').toLowerCase().indexOf(q) >= 0;
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
                '<span class="est-carne-codigo">' + esc(e.codigo || '') + '</span>' +
            '</div>' +
            '<div class="est-carne-cuerpo">' +
                '<div class="est-carne-foto" aria-hidden="true">' + esc(iniciales(e)) + '</div>' +
                '<div class="est-carne-info">' +
                    (grande ? '<h2 class="est-carne-nombre" id="est-fi-nombre">' : '<h3 class="est-carne-nombre">') + esc(nombreCompleto(e)) + (grande ? '</h2>' : '</h3>') +
                    '<p class="est-carne-doc">' + esc(e.tipo_doc) + ' ' + fmtDoc(e.documento) + '<span class="est-carne-sep" aria-hidden="true"></span>' + ed + ' años' +
                        (ed < 18 ? '<span class="est-menor">Menor</span>' : '') + '</p>' +
                    '<p class="est-carne-prog">' + esc(corto(g.programa)) + '</p>' +
                    '<p class="est-carne-grupo">' + (g.id ? esc(g.grupo) + ' · ' + esc(g.jornada) : 'Se matricula en Matrículas') + '</p>' +
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
        var cls = { activo: 'app-status-active', aplazado: 'app-status-warn', retirado: 'app-status-muted', graduado: 'est-status-graduado', sin_matricula: 'app-status-muted' }[estado];
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
                '<span class="est-fila-movil">' + (g.id ? '<span class="est-esc-tag est-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>' : '') +
                '<span class="est-fila-movil-prog">' + esc(corto(g.programa)) + (g.id ? ' · ' + esc(g.grupo) : '') + '</span>' +
                '<span class="est-fila-movil-acc"><span class="est-carne-alertas">' + alertasIconos(e) + '</span>' +
                '<a class="est-wa est-wa-sm" href="' + waLink(e.telefono) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(primerNombre(e)) + '">' + ICONOS.whatsapp + '</a></span></span>' +
                '</span></div></td>' +
            '<td>' + (g.id ? '<span class="est-esc-tag est-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>' : '<span class="est-sin-alertas">—</span>') + '</td>' +
            '<td><span class="est-fila-prog">' + esc(corto(g.programa)) + '</span><span class="est-fila-sub">' +
                (g.id ? esc(g.grupo) + ' · ' + esc(g.codigo) + ' · ' + esc(g.jornada) : 'Matricúlalo en Matrículas') + '</span></td>' +
            '<td>' + estadoTagHTML(e.estado) + '</td>' +
            '<td><span class="est-carne-alertas">' + (al.length ? alertasIconos(e) : '<span class="est-sin-alertas">—</span>') + '</span></td>' +
            '<td><a class="est-fila-wa" href="' + waLink(e.telefono) + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(primerNombre(e)) + '">' + ICONOS.whatsapp + fmtTel(e.telefono) + '</a></td>' +
            '<td class="est-fila-acc"><button type="button" class="est-icon-btn-fila" title="Ver ficha" aria-label="Ver ficha de ' + esc(nombreCompleto(e)) + '">' +
                '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></td>' +
        '</tr>';
    }


    /* ── Exportar la lista filtrada a un archivo que abre Excel (CSV con ; y BOM para las tildes) ── */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    function exportar() {
        var PAGO = { al_dia: 'Al día', pendiente: 'Cuota del mes', vencido: 'Pago vencido' };
        var filas = [['Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Edad', 'Género', 'Celular', 'Correo', 'Departamento', 'Ciudad', 'Dirección',
            'Estado', 'Programa', 'Grupo', 'Jornada', 'Acudiente', 'Parentesco', 'Celular del acudiente', 'Asistencia %', 'Pago', 'A pagar este mes', 'Por pagar en total', 'Documentos pendientes']]
            .concat(listaActual.map(function (e) {
                var g = grupoDe(e.grupo_id), a = acudienteDe(e);
                return [e.nombres, e.apellidos, e.tipo_doc, e.documento, edad(e.fecha_nac), e.genero, e.telefono, e.email, e.departamento, e.ciudad, e.direccion,
                    (ESTADOS[e.estado] || {}).label || e.estado, g.id ? g.programa : '', g.id ? g.grupo + ' · ' + g.codigo : '', g.jornada,
                    a ? nombreCompleto(a) : '', a ? e.parentesco : '', a ? a.telefono : '', e.asistencia,
                    e.estado === 'sin_matricula' ? '' : (PAGO[e.pago] || e.pago), e.saldo || 0, e.por_pagar || 0, (e.documentos_pendientes || []).join(', ')];
            }));
        var csv = '\ufeff' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
        var hoy = new Date(), dd = function (n) { return (n < 10 ? '0' : '') + n; };
        var nombre = 'estudiantes-' + hoy.getFullYear() + '-' + dd(hoy.getMonth() + 1) + '-' + dd(hoy.getDate()) + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a');
        a.href = url; a.download = nombre;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        var n = listaActual.length;
        toast('Se descargó ' + nombre + ' con ' + n + (n === 1 ? ' fila.' : ' filas.'));
    }
    document.getElementById('est-exportar').addEventListener('click', exportar);

    /* ── Render principal ── */
    function render() {
        var base = data.filter(coincideBase);
        // Orden alfabético por apellido; los inscritos en esta sesión van primero
        var lista = data.filter(coincide).sort(function (a, b) {
            return (b.reciente ? 1 : 0) - (a.reciente ? 1 : 0) || a.apellidos.localeCompare(b.apellidos, 'es');
        });
        listaActual = lista;

        // KPIs (sobre todos los estudiantes, como en Docentes)
        var conAlerta = data.filter(function (e) { return alertasDe(e).length > 0; }).length;
        var progs = [];
        data.forEach(function (e) { var g = grupoDe(e.grupo_id); if (g.id && progs.indexOf(g.programa) < 0) progs.push(g.programa); });
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
    }
    function pintarCabecera(e) {
        var g = grupoDe(e.grupo_id), ed = edad(e.fecha_nac);
        var av = document.getElementById('est-fi-avatar');
        av.textContent = iniciales(e);
        av.className = 'est-drawer-avatar est-esc-' + escClass(g.escuela) + (e.estado === 'retirado' ? ' is-retirado' : '');
        document.getElementById('est-fi-nombre').textContent = nombreCompleto(e);
        document.getElementById('est-fi-doc').textContent = e.tipo_doc + ' ' + fmtDoc(e.documento) + ' · ' + ed + ' años' + (e.codigo ? ' · ' + e.codigo : '');
        document.getElementById('est-fi-badges').innerHTML = estadoTagHTML(e.estado) +
            (g.id ? '<span class="est-esc-tag est-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>' : '') +
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
    /* Todas las matrículas de la persona (la más reciente primero): programas terminados, retiros y la actual */
    function trayectoriaHTML(e) {
        var t = e.trayectoria || [];
        if (t.length < 2) return '';
        var n = { graduado: 0, curso: 0, retirado: 0 };
        t.forEach(function (x) { if (x.estado === 'graduado') n.graduado++; else if (x.estado === 'retirado') n.retirado++; else n.curso++; });
        var resumen = [];
        if (n.graduado) resumen.push(n.graduado + (n.graduado === 1 ? ' terminado' : ' terminados'));
        if (n.curso) resumen.push(n.curso + ' en curso');
        if (n.retirado) resumen.push(n.retirado + (n.retirado === 1 ? ' retiro' : ' retiros'));
        var cuando = { activo: 'Activa desde el ', aplazado: 'Aplazada desde el ', retirado: 'Retirada el ', graduado: 'Graduada el ' };
        return seccion('Trayectoria en ESAT', '<ol class="est-tray">' + t.map(function (x) {
            var actual = x.codigo === e.codigo;
            return '<li class="est-tray-item is-' + x.estado + '"><span class="est-tray-punto" aria-hidden="true"></span><div class="est-tray-main">' +
                '<div class="est-tray-top"><strong>' + esc(corto(x.programa)) + '</strong>' + estadoTagHTML(x.estado) +
                    (actual ? '<span class="est-tray-actual">La que ves arriba</span>' : '') + '</div>' +
                '<span class="est-tray-meta">' + esc(x.codigo) + (x.grupo ? ' · ' + esc(x.grupo) + ' · ' + esc(x.jornada) : '') + '</span>' +
                '<span class="est-tray-meta">' + cuando[x.estado] + fmtFecha(x.estado_fecha || x.inicio) + ' · matriculado el ' + fmtFecha(x.inicio) + '</span>' +
                (x.estado === 'retirado' && x.estado_motivo ? '<span class="est-tray-meta">Motivo: ' + esc(x.estado_motivo) + '</span>' : '') +
                (x.estado === 'graduado' || x.estado === 'retirado'
                    ? '<span class="est-tray-deuda' + (x.debe > 0 ? ' is-deuda' : '') + '">' + (x.debe > 0 ? 'Quedó debiendo ' + fmtPesos(x.debe) : 'A paz y salvo') + '</span>' : '') +
            '</div></li>';
        }).join('') + '</ol>', '<span class="est-sec-dato">' + resumen.join(' · ') + '</span>');
    }

    function seccion(titulo, cuerpo, extra) {
        return '<section class="est-sec"><div class="est-sec-head"><h4 class="est-sec-title">' + titulo + '</h4>' + (extra || '') + '</div>' + cuerpo + '</section>';
    }
    function contactoHTML(tel, email) {
        return '<a class="est-contacto" href="tel:+57' + digitos(tel) + '">' + ICONOS.telefono + fmtTel(tel) + '</a>' +
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
            if (e.estado !== 'activo' && e.estado !== 'sin_matricula' && e.estado_fecha) {
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
                dato('Lugar de nacimiento', e.ciudad_nac ? esc(e.ciudad_nac) + ' (' + esc(e.depto_nac) + ')' : '—') +
                dato('Residencia', esc(e.direccion) + ', ' + esc(e.ciudad) + ' (' + esc(e.departamento) + ')') +
                dato('Matrícula', e.codigo ? esc(e.codigo) : 'Sin matrícula') +
                dato('Registrado en ESAT', fmtFecha(e.fecha_ingreso)) +
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

        if (tab === 'grupo' && !g.id) {
            html += '<div class="est-callout"><strong>Todavía no está matriculado</strong>' +
                '<p>Matricúlalo en un programa y un grupo desde <strong>Matrículas</strong>. Allí también se cambian el grupo, el estado y los pagos.</p></div>';
        }
        if (tab === 'grupo' && g.id) {
            var libres = cuposLibres(g);
            var pct = Math.round(g.inscritos / g.cupo * 100);
            html += '<p class="est-sec-nota">Solo consulta: el grupo, el estado y los pagos se cambian en Matrículas.</p>' +
            '<div class="est-info-grid">' +
                dato('Matrícula', esc(e.codigo) + ' ' + estadoTagHTML(e.estado)) +
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
                '<span class="est-sec-dato">' + g.inscritos + ' de ' + g.cupo + '</span>') +
            trayectoriaHTML(e);
        }

        if (tab === 'seguimiento') {
            var pagoCls = { al_dia: 'app-status-active', pendiente: 'app-status-warn', vencido: 'app-status-danger' }[e.pago];
            var pagoLbl = { al_dia: 'Al día', pendiente: 'Cuota pendiente', vencido: 'Pago vencido' }[e.pago];
            var pagosHTML;
            if (e.estado === 'sin_matricula') {
                pagosHTML = seccion('Pagos', '<p class="est-sec-nota">Sin matrícula: sus pagos aparecen cuando se matricule.</p>',
                    '<span class="app-status-tag app-status-muted">Sin matrícula</span>');
            } else if (!e.plan_pago) {
                // El plan (mensual o por periodo) lo elige el estudiante desde su panel
                pagosHTML = seccion('Pagos',
                    '<p class="est-sec-nota">Todavía no ha elegido su plan de pago (mensual o ' + (e.tipo_periodo === 'semestre' ? 'semestral' : 'trimestral') + '). Lo elige ' + esc(primerNombre(e)) + ' desde su panel; mientras tanto no tiene cuotas.</p>',
                    '<span class="app-status-tag app-status-muted">Plan por elegir</span>');
            } else {
                var segs = '';
                for (var i = 1; i <= e.cuotas_total; i++) segs += '<span class="' + (i <= e.cuotas_pagadas ? 'is-paga' : '') + '"></span>';
                pagosHTML = seccion('Pagos',
                    '<div class="est-cuotas" role="img" aria-label="' + e.cuotas_pagadas + ' de ' + e.cuotas_total + ' cuotas pagadas">' + segs + '</div>' +
                    '<div class="est-info-grid">' +
                        dato('Plan de pago', e.plan_pago === 'mensual' ? 'Mensual' : (e.tipo_periodo === 'semestre' ? 'Semestral' : 'Trimestral')) +
                        dato('Cuotas pagadas', e.cuotas_pagadas + ' de ' + e.cuotas_total) +
                        dato('A pagar este mes', e.saldo ? fmtPesos(e.saldo) : '$ 0') +
                        dato('Último pago', fmtFecha(e.ultimo_pago)) +
                    '</div>',
                    '<span class="app-status-tag ' + pagoCls + '">' + pagoLbl + '</span>');
            }
            var pendientes = e.documentos.filter(function (d) { return !d.entregado; }).length;
            html += pagosHTML +
            seccion('Asistencia',
                e.asistencia === null ? '<p class="est-sec-nota">Todavía no hay asistencia registrada en su grupo.</p>'
                    : '<p class="est-sec-nota">' + (e.asistencia < 80 ? 'Por debajo del 80 % esperado. Conviene contactar al estudiante' + (acudienteDe(e) ? ' y a su acudiente' : '') + '.' : 'Asistencia en el periodo actual.') + '</p>',
                e.asistencia === null ? '<span class="app-status-tag app-status-muted">Sin clases aún</span>'
                    : '<span class="app-status-tag ' + (e.asistencia < 80 ? 'app-status-warn' : 'app-status-active') + '">' + e.asistencia + ' %</span>') +
            // Lista de chequeo: se marca cada documento cuando el estudiante lo entrega
            seccion('Documentos',
                '<ul class="est-docs-check">' + e.documentos.map(function (d) {
                    return '<li><label class="est-doc-check' + (d.entregado ? ' is-entregado' : '') + '">' +
                        '<input type="checkbox" data-doc="' + d.id + '"' + (d.entregado ? ' checked' : '') + '>' +
                        '<span>' + esc(d.nombre) + '</span>' +
                        '<em>' + (d.entregado ? 'Entregado' : 'Pendiente') + '</em></label></li>';
                }).join('') + '</ul>',
                pendientes ? '<span class="app-status-tag app-status-warn">' + pendientes + (pendientes === 1 ? ' pendiente' : ' pendientes') + '</span>'
                           : '<span class="app-status-tag app-status-active">Completa</span>');
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
        var e = actual;
        btnConfirmar.textContent = 'Confirmar';
        btnConfirmar.className = 'est-btn est-btn-primary';

        if (tipo === 'clave') {
            accionTtl.textContent = 'Restablecer contraseña';
            accionSub.textContent = '';
            accionCuerpo.innerHTML = '<div class="est-callout"><strong>La contraseña de ' + esc(primerNombre(e)) + ' volverá a ser su número de documento</strong>' +
                '<p>Podrá ingresar con su correo <strong>' + esc(e.email) + '</strong> y la contraseña <strong class="est-num-inline">' + esc(e.documento) + '</strong> (sin puntos), y cambiarla desde su perfil. Su contraseña actual dejará de funcionar.</p></div>';
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
                    '<div class="est-field"><label class="est-lbl" for="acc-acu-email">Correo</label><input class="est-input" id="acc-acu-email" type="email" autocomplete="off"></div></div></div>' +
                '<div class="est-field" id="acc-acu-par-wrap" hidden><span class="est-lbl">Parentesco con el estudiante</span><div class="est-chips" id="acc-acu-par" role="radiogroup" aria-label="Parentesco"></div></div>';
            pintarChips(document.getElementById('acc-acu-par'), 'acc-par');
            var buscar = function () { buscarAcudiente('acc-acu-doc', 'acc-acu-res', 'acc-acu-nuevo', 'acc-acu-par-wrap', function (a) { acuSel = a; }); };
            document.getElementById('acc-acu-buscar').addEventListener('click', buscar);
            document.getElementById('acc-acu-doc').addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); buscar(); } });
        }
        accionEl.scrollTop = 0;
        setTimeout(function () { document.getElementById('est-accion-volver').focus(); }, 30);
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
        if (!email) return { error: 'Escribe el correo del acudiente: con él ingresa al sistema.', campo: ids.email };
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return { error: 'El correo del acudiente no es válido.', campo: ids.email };
        return { acudiente: { documento: doc, nombres: nom, apellidos: ape, telefono: tel, email: email } };
    }

    function errorAccion(msg, campoId) {
        accionError.textContent = msg;
        accionError.hidden = false;
        if (campoId) { var c = document.getElementById(campoId); if (c) { c.classList.add('is-invalid'); c.focus(); } }
    }

    fiPanel.addEventListener('change', function (ev) {
        var cb = ev.target.closest('input[data-doc]');
        if (!cb) return;
        var e = actual, docId = parseInt(cb.dataset.doc);
        cb.disabled = true;
        enviar('PATCH', '/admin/estudiantes/' + e.id + '/documentos/' + docId)
            .then(function (r) {
                var d = e.documentos.find(function (x) { return x.id === docId; });
                d.entregado = r.entregado;
                e.documentos_pendientes = e.documentos.filter(function (x) { return !x.entregado; }).map(function (x) { return x.nombre; });
                mostrarFicha();
                render();
            })
            .catch(function (err) { cb.disabled = false; cb.checked = !cb.checked; toast(primerError(err).mensaje); });
    });

    fichaFoot.addEventListener('click', function (e) {
        var b = e.target.closest('[data-accion]');
        if (!b || b.disabled) return;
        if (b.dataset.accion === 'editar') abrirModal(actual);
        else mostrarAccion(b.dataset.accion);
    });
    fiPanel.addEventListener('click', function (e) { var b = e.target.closest('[data-accion]'); if (b) mostrarAccion(b.dataset.accion); });
    document.getElementById('est-accion-volver').addEventListener('click', mostrarFicha);
    document.getElementById('est-accion-cancelar').addEventListener('click', mostrarFicha);
    accionEl.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); accionError.hidden = true; });
    accionEl.addEventListener('change', function () { accionError.hidden = true; });

    btnConfirmar.addEventListener('click', function () {
        var e = actual;
        var url = '/admin/estudiantes/' + e.id, metodo = 'PATCH', cuerpo = null, tab = tabActual;
        accionError.hidden = true;

        if (accionActual === 'clave') {
            url += '/clave';
        }

        if (accionActual === 'acudiente') {
            if (!acuSel) return errorAccion('Busca al acudiente por su cédula.', 'acc-acu-doc');
            var docAcu = acuSel.existente ? acuSel.existente.documento : acuSel.nuevo;
            if (docAcu === String(e.documento)) return errorAccion('El acudiente no puede ser el mismo estudiante.', 'acc-acu-doc');
            var acu = acuSel.existente ? { id: acuSel.existente.id } : null;
            if (!acu) {
                var r = leerNuevoAcudiente(CAMPOS_ACU_ACC, acuSel.nuevo);
                if (r.error) return errorAccion(r.error, r.campo);
                acu = r.acudiente;
            }
            var par = (accionCuerpo.querySelector('input[name="acc-par"]:checked') || {}).value;
            if (!par) return errorAccion('Selecciona el parentesco con el estudiante.');
            metodo = 'PUT';
            url += '/acudiente';
            cuerpo = { acudiente: acu, parentesco: par };
            tab = 'acudiente';
        }

        btnConfirmar.disabled = true;
        enviar(metodo, url, cuerpo)
            .then(function (resp) { recargarConAviso(resp.mensaje, { id: e.id, tab: tab }); })
            .catch(function (err) {
                btnConfirmar.disabled = false;
                errorAccion(primerError(err).mensaje);
            });
    });

    /* ══ Asistente: Registrar / Editar estudiante ══
       Solo la persona: datos personales, contacto y (al registrar) acudiente.
       Programa y grupo se asignan en Matrículas. */
    var modal     = document.getElementById('est-modal');
    var paso      = 1;
    var TOTAL     = 3;
    var editando  = null;   // estudiante que se edita (null = registrar)
    var insError  = document.getElementById('ins-error');
    var btnSig    = document.getElementById('est-btn-siguiente');
    var btnAtras  = document.getElementById('est-btn-atras');
    var insAcu    = null;   // { existente } | { nuevo: doc }

    var cbGenero   = document.getElementById('ins-cb-genero');
    var cbTipo     = document.getElementById('ins-cb-tipo');
    var cbDepto    = document.getElementById('ins-cb-depto');
    var cbCiudad   = document.getElementById('ins-cb-ciudad');
    var cbDeptoNac  = document.getElementById('ins-cb-depto-nac');
    var cbCiudadNac = document.getElementById('ins-cb-ciudad-nac');
    [cbGenero, cbTipo, cbDepto, cbCiudad, cbDeptoNac, cbCiudadNac].forEach(initCombobox);

    // Mismos valores que valida el servidor (EstudianteRequest::GENEROS y TIPOS_DOCUMENTO)
    function aOpciones(obj) { return Object.keys(obj || {}).map(function (k) { return { value: k, label: obj[k] }; }); }
    cbGenero.setOptions(aOpciones(opciones.generos));
    cbTipo.setOptions(aOpciones(opciones.tipos));
    pintarChips(document.getElementById('ins-parentesco'), 'ins-par');

    // Departamentos y municipios reales de Colombia (nacimiento y residencia)
    var colombia = null;
    fetch('/assets/data/co-departamentos-ciudades.json')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            colombia = d;
            var deptos = d.map(function (x) { return x.departamento; });
            cbDepto.setOptions(deptos);
            cbDeptoNac.setOptions(deptos);
        });
    function poblarCiudades(cbCiudadDe) {
        return function (e) {
            var dep = colombia && colombia.find(function (x) { return x.departamento === e.target.value; });
            cbCiudadDe.reset(dep ? 'Selecciona un municipio' : 'Elige el departamento primero');
            cbCiudadDe.setOptions(dep ? dep.ciudades : []);
        };
    }
    document.getElementById('ins-depto').addEventListener('change', poblarCiudades(cbCiudad));
    document.getElementById('ins-depto-nac').addEventListener('change', poblarCiudades(cbCiudadNac));

    function val(id) { return document.getElementById(id).value.trim(); }
    function edadIns() { return edad(val('ins-nacimiento')); }

    // Edad en vivo desde la fecha de nacimiento (nunca se guarda como número)
    var edadEl = document.getElementById('ins-edad');
    document.getElementById('ins-nacimiento').max = hoyISO();
    document.getElementById('ins-nacimiento').addEventListener('input', function () {
        var ed = edadIns();
        if (ed === null || isNaN(ed) || ed < 0) { edadEl.textContent = ''; edadEl.className = 'est-edad'; return; }
        edadEl.textContent = ed + ' años' + (ed < 18 ? ' · menor de edad' : '');
        edadEl.className = 'est-edad' + (ed < 18 ? ' is-menor' : '');
    });

    /* e = estudiante a editar (precarga sus datos) o nada para registrar uno nuevo */
    function abrirModal(e) {
        editando = e && e.id ? e : null;
        TOTAL = editando ? 2 : 3;
        paso = 1;
        modal.querySelectorAll('input.est-input').forEach(function (i) { i.value = ''; i.classList.remove('is-invalid'); });
        modal.querySelectorAll('input[type=radio]').forEach(function (r) { r.checked = false; });
        edadEl.textContent = ''; edadEl.className = 'est-edad';
        cbGenero.reset(); cbTipo.reset(); cbDepto.reset(); cbCiudad.reset('Elige el departamento primero'); cbCiudad.setOptions([]);
        cbDeptoNac.reset(); cbCiudadNac.reset('Elige el departamento primero'); cbCiudadNac.setOptions([]);
        document.getElementById('ins-acu-agregar').checked = false;
        document.getElementById('ins-acu-resultado').innerHTML = '';
        document.getElementById('ins-acu-nuevo').hidden = true;
        document.getElementById('ins-acu-parentesco-wrap').hidden = true;
        insAcu = null;

        // Al editar no hay paso de acudiente: se cambia desde la ficha
        modal.querySelectorAll('.est-wstep[data-step="3"], .est-wstep-line.is-paso-acu').forEach(function (el) { el.hidden = !!editando; });
        document.getElementById('est-modal-ttl').textContent = editando ? 'Editar estudiante' : 'Registrar estudiante';

        if (editando) {
            document.getElementById('ins-nombres').value = editando.nombres;
            document.getElementById('ins-apellidos').value = editando.apellidos;
            document.getElementById('ins-nacimiento').value = editando.fecha_nac || '';
            document.getElementById('ins-nacimiento').dispatchEvent(new Event('input'));
            if (editando.depto_nac) {
                cbDeptoNac.setValue(editando.depto_nac, editando.depto_nac);   // carga sus municipios
                if (editando.ciudad_nac) cbCiudadNac.setValue(editando.ciudad_nac, editando.ciudad_nac, { silent: true });
            }
            cbGenero.setValue(editando.genero_valor, (opciones.generos || {})[editando.genero_valor], { silent: true });
            cbTipo.setValue(editando.tipo_doc, (opciones.tipos || {})[editando.tipo_doc], { silent: true });
            document.getElementById('ins-documento').value = editando.documento;
            document.getElementById('ins-telefono').value = editando.telefono;
            document.getElementById('ins-email').value = editando.email || '';
            document.getElementById('ins-direccion').value = editando.direccion || '';
            if (editando.departamento) {
                cbDepto.setValue(editando.departamento, editando.departamento);   // carga sus municipios
                if (editando.ciudad) cbCiudad.setValue(editando.ciudad, editando.ciudad, { silent: true });
            }
        }

        pintarPaso();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { document.getElementById('ins-nombres').focus(); }, 80);
    }
    function cerrarModal() {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!editando) document.getElementById('est-btn-registrar').focus();
    }
    document.getElementById('est-btn-registrar').addEventListener('click', function () { abrirModal(null); });
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
        btnSig.textContent = paso === TOTAL ? (editando ? 'Guardar cambios' : 'Registrar estudiante') : 'Siguiente';
        insError.hidden = true;
        if (paso === 3) prepararAcudiente();
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
            var tipo = cbTipo.value();
            var doc = val('ins-documento').replace(/\D/g, '');
            if (!tipo) return errorIns('Selecciona el tipo de documento.', 'ins-cb-tipo');
            if (doc.length < 6 || doc.length > 11) return errorIns('El número de documento debe tener entre 6 y 11 dígitos.', 'ins-documento');
            var dup = data.find(function (x) { return String(x.documento) === doc && (!editando || x.id !== editando.id); });
            if (dup) return errorIns('Ya existe un estudiante con ese documento: ' + nombreCompleto(dup) + '.', 'ins-documento');
            if (!val('ins-nacimiento')) return errorIns('Indica la fecha de nacimiento.', 'ins-nacimiento');
            var ed = edadIns();
            if (val('ins-nacimiento') > hoyISO() || ed < 0) return errorIns('La fecha de nacimiento no puede ser futura.', 'ins-nacimiento');
            if (ed > 99) return errorIns('Revisa el año de nacimiento.', 'ins-nacimiento');
            if (!cbGenero.value()) return errorIns('Selecciona el género.', 'ins-cb-genero');
            if (!cbDeptoNac.value()) return errorIns('Selecciona el departamento de nacimiento.', 'ins-cb-depto-nac');
            if (!cbCiudadNac.value()) return errorIns('Selecciona la ciudad de nacimiento.', 'ins-cb-ciudad-nac');
            if (tipo === 'TI' && ed >= 18) return errorIns('Con ' + ed + ' años el documento es la cédula de ciudadanía, no la tarjeta de identidad.', 'ins-cb-tipo');
            if (tipo === 'CC' && ed < 18) return errorIns('Un menor de edad se identifica con tarjeta de identidad.', 'ins-cb-tipo');
            // Corregir la fecha no puede dejar a un menor sin acudiente
            if (editando && ed < 18 && !editando.acudiente_id) return errorIns('Con esa fecha tendría ' + ed + ' años y no tiene acudiente. Vincúlale uno desde su ficha (pestaña Acudiente) antes de cambiar la fecha.', 'ins-nacimiento');
        }
        if (paso === 2) {
            var tel = val('ins-telefono').replace(/\D/g, '');
            var email = val('ins-email');
            if (!email) return errorIns('Escribe el correo: es el usuario con el que ingresará al sistema.', 'ins-email');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return errorIns('El correo no es válido. Revisa que tenga @ y un dominio.', 'ins-email');
            if (!/^3\d{9}$/.test(tel)) return errorIns('El celular debe tener 10 dígitos y empezar por 3.', 'ins-telefono');
            if (val('ins-direccion').length < 5) return errorIns('Escribe la dirección de residencia.', 'ins-direccion');
            if (!cbDepto.value()) return errorIns('Selecciona el departamento de residencia.', 'ins-cb-depto');
            if (!cbCiudad.value()) return errorIns('Selecciona la ciudad de residencia.', 'ins-cb-ciudad');
        }
        if (paso === 3) {
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
        return true;
    }

    btnSig.addEventListener('click', function () {
        insError.hidden = true;
        if (!validarPaso()) return;
        if (paso < TOTAL) { paso++; pintarPaso(); return; }
        guardarEstudiante();
    });
    btnAtras.addEventListener('click', function () { if (paso > 1) { paso--; pintarPaso(); } });
    modal.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); insError.hidden = true; });
    modal.addEventListener('change', function (e) {
        var cb = e.target.closest('.app-combobox');
        if (cb) cb.classList.remove('is-invalid');
        insError.hidden = true;
    });

    /* Paso del asistente donde está cada campo (para volver a él si el servidor lo rechaza) */
    var PASO_DE = {
        nombres: 1, apellidos: 1, fecha_nacimiento: 1, genero: 1, tipo_documento: 1, numero_documento: 1,
        departamento_nacimiento: 1, lugar_nacimiento: 1,
        telefono: 2, email: 2, direccion: 2, departamento_residencia: 2, ciudad_residencia: 2,
        acudiente: 3, parentesco: 3
    };

    function guardarEstudiante() {
        var cuerpo = {
            nombres: val('ins-nombres'), apellidos: val('ins-apellidos'),
            fecha_nacimiento: val('ins-nacimiento'), genero: cbGenero.value(),
            departamento_nacimiento: cbDeptoNac.value(), lugar_nacimiento: cbCiudadNac.value(),
            tipo_documento: cbTipo.value(), numero_documento: val('ins-documento'),
            telefono: val('ins-telefono'), email: val('ins-email'),
            direccion: val('ins-direccion'), departamento_residencia: cbDepto.value(), ciudad_residencia: cbCiudad.value()
        };
        if (!editando) {
            var conAcu = edadIns() < 18 || document.getElementById('ins-acu-agregar').checked;
            cuerpo.con_acudiente = conAcu;
            cuerpo.acudiente = null;
            cuerpo.parentesco = null;
            if (conAcu && insAcu) {
                cuerpo.acudiente = insAcu.existente ? { id: insAcu.existente.id } : leerNuevoAcudiente(CAMPOS_ACU_INS, insAcu.nuevo).acudiente;
                cuerpo.parentesco = modal.querySelector('input[name="ins-par"]:checked').value;
            }
        }

        var textoBtn = btnSig.textContent;
        btnSig.disabled = true;
        btnSig.textContent = 'Guardando…';
        var peticion = editando ? enviar('PUT', '/admin/estudiantes/' + editando.id, cuerpo) : enviar('POST', '/admin/estudiantes', cuerpo);
        peticion
            .then(function (r) { recargarConAviso(r.mensaje, editando ? { id: editando.id, tab: 'datos' } : { nuevo: r.id }); })
            .catch(function (err) {
                btnSig.disabled = false;
                btnSig.textContent = textoBtn;
                var pe = primerError(err);
                var raiz = pe.campo ? pe.campo.split('.')[0] : null;
                if (raiz && PASO_DE[raiz] && PASO_DE[raiz] !== paso) { paso = PASO_DE[raiz]; pintarPaso(); }
                errorIns(pe.mensaje);
            });
    }

    /* Escape: primero cierra listas abiertas, luego el asistente o la ficha */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (modal.classList.contains('is-open')) { cerrarModal(); return; }
        if (drawer.classList.contains('is-open')) {
            if (accionActual) mostrarFicha(); else cerrarFicha();
        }
    });

    var reabrir = null;
    try {
        var aviso = sessionStorage.getItem(FLASH);
        reabrir = JSON.parse(sessionStorage.getItem(ABRIR) || 'null');
        sessionStorage.removeItem(FLASH);
        sessionStorage.removeItem(ABRIR);
        if (aviso) toast(aviso);
    } catch (e) {}
    if (reabrir && reabrir.nuevo) nuevoId = reabrir.nuevo;

    render();

    if (reabrir && reabrir.id) {
        abrirFicha(reabrir.id);
        tabActual = reabrir.tab || 'datos';
        if (actual) mostrarFicha();
    }
}());
