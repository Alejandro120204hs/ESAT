/* ESAT — Admin: Matrículas */
(function () {
    'use strict';

    var data       = window.MAT_DATA || [];
    var sinVigente = window.MAT_PENDIENTES || [];   // sin matrícula vigente: los ofrece el asistente
    // "Por matricular" = registrados que nunca se han matriculado (un graduado o retirado no está pendiente)
    var pendientes = sinVigente.filter(function (e) { return !e.anterior; });
    var grupos     = window.MAT_GRUPOS || [];
    var programas  = window.MAT_PROGRAMAS || [];
    var opciones   = window.MAT_OPCIONES || {};
    var csrf       = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var FLASH      = 'esat.admin.matriculas.aviso';
    var ABRIR      = 'esat.admin.matriculas.abrir';   // ficha a reabrir tras recargar: {id, tab, nuevo, recibo}
    var HOY        = opciones.hoy || new Date().toISOString().slice(0, 10);

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
    /* Guarda el aviso (y qué ficha reabrir) y recarga: tabla, KPI, cupos y cuotas salen del servidor */
    function recargarConAviso(msg, abrir) {
        try {
            sessionStorage.setItem(FLASH, msg);
            if (abrir) sessionStorage.setItem(ABRIR, JSON.stringify(abrir));
        } catch (e) {}
        location.reload();
    }

    var PAGE_SIZE = 12;
    var ESTADOS = {
        activo:   { label: 'Activa',   cls: 'app-status-active', accion: 'Reintegrar', desc: 'Vuelve a clases en un grupo con cupo y se generan las cuotas que le faltan.' },
        aplazado: { label: 'Aplazada', cls: 'app-status-warn',   accion: 'Aplazar',    desc: 'Suspende sus estudios por un tiempo: libera el cupo y se anulan las cuotas que vencen después. Si no ha pagado el derecho de matrícula, queda en espera hasta que se reintegre.' },
        retirado: { label: 'Retirada', cls: 'app-status-muted',  accion: 'Retirar',    desc: 'Deja el programa de forma definitiva: libera el cupo y se anulan las cuotas futuras (y el derecho de matrícula si aún no vencía). Lo atrasado se sigue debiendo.' },
        graduado: { label: 'Graduada', cls: 'mat-status-graduado', accion: 'Graduar',  desc: 'Terminó el programa. Solo cuando su grupo finalizó, pagó todas las cuotas y está a paz y salvo.' },
        anulado:  { label: 'Anulada',  cls: 'app-status-danger', accion: 'Anular',     desc: 'Se hizo por error (estudiante o grupo equivocado). Solo si no tiene pagos: libera el cupo, no se cobra nada y queda en Finalizadas para consulta.' }
    };
    var CAMBIO_PROGRAMA = { accion: 'Cambiar de programa', desc: 'Va a estudiar otro programa: esta matrícula queda retirada y se crea una nueva en el grupo que elijas. No se vuelve a cobrar el derecho de matrícula si ya lo pagó, y lo pagado por adelantado pasa como abono.' };
    var PAGOS = {
        al_dia:    { label: 'Al día',          cls: 'app-status-active' },
        pendiente: { label: 'Cuota del mes',   cls: 'app-status-warn' },
        vencido:   { label: 'Pago vencido',    cls: 'app-status-danger' },
        sin_plan:  { label: 'Sin plan elegido', cls: 'app-status-muted' }
    };
    var COBROS = {
        pendiente: { label: 'Pendiente', cls: 'app-status-warn' },
        vencido:   { label: 'Vencido',   cls: 'app-status-danger' },
        pagado:    { label: 'Pagado',    cls: 'app-status-active' },
        anulado:   { label: 'Anulado',   cls: 'app-status-muted' }
    };
    var MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    var MESES_LARGOS = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    var METODOS = opciones.metodos || ['Efectivo'];
    var TRANSICIONES = opciones.transiciones || {};
    var CON_MOTIVO = opciones.con_motivo || [];

    var ICONOS = {
        ojo:      '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        pago:     '<svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>',
        doc:      '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="12" y1="19" x2="12.01" y2="19"/></svg>',
        asist:    '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        aviso:    '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        quitar:   '<svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>',
        mas:      '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        recibo:   '<svg viewBox="0 0 24 24"><path d="M4 2v20l3-2 3 2 2-2 2 2 3-2 3 2V2l-3 2-3-2-2 2-2-2-3 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="14" y2="13"/></svg>'
    };

    /* ── Utilidades ── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }
    function escClass(e) {
        return { 'Salud':'sal','Cocina y Turismo':'tur','Administrativa':'adm','Educación e Idiomas':'edu',
                 'Deporte y Cultura':'dep','Ciencias':'cie','Belleza':'bel' }[e] || 'sin';
    }
    function iniciales(p) { return ((p.nombres || '').charAt(0) + (p.apellidos || '').charAt(0)).toUpperCase(); }
    function nombreCompleto(p) { return p.nombres + ' ' + p.apellidos; }
    function primerNombre(p) { return p.nombres.split(' ')[0]; }
    function edad(fecha) {
        if (!fecha) return null;
        var n = new Date(fecha + 'T00:00:00'), h = new Date(HOY + 'T00:00:00');
        var a = h.getFullYear() - n.getFullYear();
        var m = h.getMonth() - n.getMonth();
        if (m < 0 || (m === 0 && h.getDate() < n.getDate())) a--;
        return a;
    }
    function esMenor(p) { var ed = edad(p.fecha_nac); return ed !== null && ed < 18; }
    function fmtDoc(d) { return String(d == null ? '' : d).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function fmtTel(t) { t = String(t || ''); return t.length === 10 ? t.slice(0,3) + ' ' + t.slice(3,6) + ' ' + t.slice(6) : t; }
    function fmtFecha(f) {
        if (!f) return '—';
        var p = f.slice(0, 10).split('-');
        return parseInt(p[2]) + ' ' + MESES[parseInt(p[1]) - 1] + ' ' + p[0];
    }
    // Espacio que no se parte: el signo nunca queda solo al final de un renglón
    function fmtPesos(v) { return '$ ' + fmtDoc(Math.round(v || 0)); }
    function fmtPct(v) { return String(Math.round(v * 100) / 100).replace('.', ',') + ' %'; }
    function corto(programa) { return String(programa || '').replace('Técnico Laboral en ', ''); }
    function grupoDe(id) { return grupos.find(function (g) { return g.id === id; }); }
    function programaDe(id) { return programas.find(function (p) { return p.id === id; }); }
    function matDe(id) { return data.find(function (m) { return m.id === id; }); }
    function pendDe(id) { return sinVigente.find(function (e) { return e.id === id; }); }
    function tag(info) { return '<span class="app-status-tag ' + info.cls + '">' + info.label + '</span>'; }
    function cerrada(m) { return m.estado === 'retirado' || m.estado === 'graduado' || m.estado === 'anulado'; }
    function porPagar(c) { return c.estado === 'pendiente' || c.estado === 'vencido'; }
    /* Derecho de matrícula en espera: se aplazó antes de empezar y vence cuando se reintegre */
    function venceTxt(c) { return c.vence ? fmtFecha(c.vence) : 'Al reintegrarse'; }
    function fechaMinEstado(m) { return m.estado_fecha && m.estado_fecha > m.fecha ? m.estado_fecha : m.fecha; }
    /* vencido manda; sin plan elegido se distingue de "al día" */
    function pagoClave(m) { return m.pago === 'vencido' ? 'vencido' : (!m.plan_pago ? 'sin_plan' : m.pago); }
    function planNombre(plan, tipoPeriodo) { return plan === 'mensual' ? 'Mensual' : (tipoPeriodo === 'semestre' ? 'Semestral' : 'Trimestral'); }
    function cuposLibres(g) { return Math.max(0, g.cupo - g.inscritos); }
    function abierto(g) { return g.estado !== 'finalizado'; }

    /* ── Combobox (mismo patrón de Docentes, Estudiantes y Acudientes) ── */
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
        document.addEventListener('click', function (e) { if (root.isConnected && !root.contains(e.target)) cerrar(); });
        // Escape cierra primero la lista abierta y no llega al asistente ni a la ficha
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root.isConnected && !panel.hidden) { cerrar(); e.stopImmediatePropagation(); }
        });

        root.setOptions = function (lista) {
            list.innerHTML = '';
            lista.forEach(function (o) {
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
            empty.hidden = lista.length > 0;
            empty.textContent = 'Sin resultados';
            list.appendChild(empty);
        };
        root.setValue = function (valor, etiqueta, opts) {
            hidden.value = valor || '';
            label.textContent = etiqueta || valor || placeholder;
            root.classList.toggle('has-value', !!valor);
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === String(valor));
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

    function pintarChips(cont, name, lista, marcado) {
        cont.innerHTML = lista.map(function (v) {
            return '<label class="mat-chip"><input type="radio" name="' + name + '" value="' + esc(v) + '"' + (v === marcado ? ' checked' : '') + '><span>' + esc(v) + '</span></label>';
        }).join('');
    }

    /* ── Estado de la vista ── */
    var vista = 'vigentes';   // vigentes | pendientes | finalizadas
    /* Matrículas: las vigentes (activas y aplazadas); Finalizadas: graduadas y retiradas (solo consulta) */
    function enVista(m) { return vista === 'finalizadas' ? cerrada(m) : !cerrada(m); }
    var filtro = { programa: '', grupo: '', estado: '', pago: '', texto: '' };
    var textoPend = '';
    var pagina = 1;
    var nuevoId = null;
    var listaActual = [];   // lo que se ve con los filtros: es lo que se exporta

    var listaWrap = document.getElementById('mat-lista-wrap');
    var listaBody = document.getElementById('mat-lista-body');
    var pendWrap  = document.getElementById('mat-pend-wrap');
    var pendBody  = document.getElementById('mat-pend-body');
    var emptyEl   = document.getElementById('mat-empty');
    var pagEl     = document.getElementById('mat-pagination');
    var pagPages  = document.getElementById('mat-pg-pages');
    var pagPrev   = document.getElementById('mat-pg-prev');
    var pagNext   = document.getElementById('mat-pg-next');

    function coincide(m) {
        var q = filtro.texto;
        var okTexto = !q ||
            nombreCompleto(m).toLowerCase().indexOf(q) >= 0 ||
            m.codigo.toLowerCase().indexOf(q) >= 0 ||
            String(m.documento).indexOf(q.replace(/\D/g, '') || '§') >= 0;
        return okTexto &&
            (!filtro.programa || String(m.programa_id) === filtro.programa) &&
            (!filtro.grupo || String(m.grupo_id) === filtro.grupo) &&
            (!filtro.estado || m.estado === filtro.estado) &&
            (!filtro.pago || pagoClave(m) === filtro.pago);
    }
    function coincidePend(e) {
        var q = textoPend;
        return !q || nombreCompleto(e).toLowerCase().indexOf(q) >= 0 || String(e.documento).indexOf(q.replace(/\D/g, '') || '§') >= 0;
    }

    var ORDEN_ESTADO = { activo: 0, aplazado: 1, graduado: 2, retirado: 3, anulado: 4 };

    function cuotasHTML(m) {
        if (!m.plan_pago) {
            return '<span class="mat-sin-plan">Sin plan elegido</span>' + (m.pago === 'vencido' ? ' ' + tag(PAGOS.vencido) : '');
        }
        var pct = m.cuotas_total ? Math.round(m.cuotas_pagadas / m.cuotas_total * 100) : 0;
        return '<div class="mat-avance"><div class="mat-avance-top"><span class="mat-avance-txt">' + m.cuotas_pagadas + ' de ' + m.cuotas_total + ' · ' + planNombre(m.plan_pago, m.tipo_periodo).toLowerCase() + '</span></div>' +
            '<span class="mat-barra" aria-hidden="true"><span style="width:' + pct + '%"></span></span>' +
            tag(PAGOS[pagoClave(m)]) + '</div>';
    }

    function filaHTML(m) {
        var g = grupoDe(m.grupo_id) || {};
        var prog = programaDe(m.programa_id);
        var escuela = g.escuela || (prog && prog.escuela);
        var deuda = (vista === 'finalizadas' ? m.por_pagar : m.saldo) > 0;
        return '<tr class="mat-fila mat-esc-' + escClass(escuela) + (cerrada(m) ? ' is-cerrada' : '') + (m.id === nuevoId ? ' is-nuevo' : '') + '" data-id="' + m.id + '">' +
            '<td><div class="mat-fila-est"><span class="mat-fila-foto" aria-hidden="true">' + esc(iniciales(m)) + '</span>' +
                '<span class="mat-fila-datos"><span class="mat-fila-nombre">' + esc(nombreCompleto(m)) + '</span>' +
                '<span class="mat-fila-doc">' + esc(m.tipo_doc) + ' ' + fmtDoc(m.documento) + ' · ' + esc(m.codigo) + (esMenor(m) ? ' <span class="mat-menor">Menor</span>' : '') + '</span>' +
                // En pantallas angostas las columnas se pliegan aquí
                '<span class="mat-fila-movil"><span class="mat-fila-movil-prog">' + esc(corto(g.programa)) + ' · ' + esc(g.grupo || '') + '</span>' + tag(PAGOS[pagoClave(m)]) + '</span>' +
                '</span></div></td>' +
            '<td><span class="mat-fila-prog">' + esc(corto(g.programa)) + '</span>' +
                '<span class="mat-fila-sub">' + esc(g.grupo) + ' · ' + esc(g.codigo) + ' · ' + esc(g.jornada) + '</span></td>' +
            '<td>' + tag(ESTADOS[m.estado]) + (m.estado !== 'activo' && m.estado_fecha ? '<span class="mat-fila-sub">' + (m.estado === 'aplazado' ? 'desde el ' : 'el ') + fmtFecha(m.estado_fecha) + '</span>' : '') + '</td>' +
            '<td>' + cuotasHTML(m) + '</td>' +
            '<td class="mat-fila-valor' + (deuda ? (m.pago === 'vencido' ? ' is-deuda' : '') : ' is-cero') + '">' + fmtPesos(vista === 'finalizadas' ? m.por_pagar : m.saldo) + '</td>' +
            '<td class="mat-fila-acc"><button type="button" class="mat-icon-btn-fila" title="Ver matrícula" aria-label="Ver matrícula de ' + esc(nombreCompleto(m)) + '">' + ICONOS.ojo + '</button></td>' +
        '</tr>';
    }

    function pendHTML(e) {
        var menor = esMenor(e), sinAcu = menor && !e.acudiente, sinDocs = e.documentos_pendientes.length > 0;
        var situacion = e.anterior
            ? 'Antes: ' + esc(ESTADOS[e.anterior.estado].label.toLowerCase()) + ' en ' + esc(corto(e.anterior.programa)) + '<span class="mat-fila-sub">' + esc(e.anterior.codigo) + '</span>'
            : 'Nunca se ha matriculado<span class="mat-fila-sub">Registrado el ' + fmtFecha(e.registrado) + '</span>';
        var docs = e.documentos_pendientes.length
            ? '<span class="mat-fila-aviso">' + ICONOS.doc + e.documentos_pendientes.length + (e.documentos_pendientes.length === 1 ? ' pendiente' : ' pendientes') + '</span>'
            : '<span class="mat-fila-ok">Completos</span>';
        var acu = e.acudiente
            ? esc(e.acudiente.nombre) + '<span class="mat-fila-sub">' + esc(e.acudiente.parentesco) + '</span>'
            : (sinAcu ? '<span class="mat-fila-aviso">' + ICONOS.aviso + 'Menor sin acudiente</span>' : '<span class="mat-fila-sub">No tiene (mayor de edad)</span>');
        return '<tr class="mat-fila mat-fila-pend mat-esc-sin" data-pend="' + e.id + '">' +
            '<td><div class="mat-fila-est"><span class="mat-fila-foto" aria-hidden="true">' + esc(iniciales(e)) + '</span>' +
                '<span class="mat-fila-datos"><span class="mat-fila-nombre">' + esc(nombreCompleto(e)) + '</span>' +
                '<span class="mat-fila-doc">' + esc(e.tipo_doc) + ' ' + fmtDoc(e.documento) + (edad(e.fecha_nac) !== null ? ' · ' + edad(e.fecha_nac) + ' años' : '') + (menor ? ' <span class="mat-menor">Menor</span>' : '') + '</span></span></div></td>' +
            '<td>' + situacion + '</td>' +
            '<td>' + acu + '</td>' +
            '<td>' + docs + '</td>' +
            '<td class="mat-fila-acc"><button type="button" class="mat-btn mat-btn-primary" data-matricular="' + e.id + '"' +
                (sinAcu ? ' disabled title="Vincúlale un acudiente en Estudiantes antes de matricularlo"'
                    : sinDocs ? ' disabled title="Recibe sus documentos en Estudiantes antes de matricularlo"' : '') + '>Matricular</button></td>' +
        '</tr>';
    }

    /* ── Render principal ── */
    function render() {
        // KPIs (sobre todas las matrículas de la sede)
        var mes = HOY.slice(0, 7), recaudo = 0, cartera = 0, conDeuda = 0;
        data.forEach(function (m) {
            m.recaudos.forEach(function (r) { if (!r.anulado && r.fecha.slice(0, 7) === mes) recaudo += r.valor; });
            if (m.saldo_vencido > 0) { cartera += m.saldo_vencido; conDeuda++; }
        });
        setTxt('mat-kpi-activas', data.filter(function (m) { return m.estado === 'activo'; }).length);
        setTxt('mat-kpi-pendientes', pendientes.length);
        setTxt('mat-kpi-recaudo', fmtPesos(recaudo));
        setTxt('mat-kpi-recaudo-lbl', 'Recaudado en ' + MESES_LARGOS[parseInt(mes.slice(5)) - 1]);
        setTxt('mat-kpi-cartera', fmtPesos(cartera));
        setTxt('mat-kpi-cartera-lbl', 'Cartera vencida (' + conDeuda + (conDeuda === 1 ? ' estudiante)' : ' estudiantes)'));
        setTxt('mat-num-vigentes', data.filter(function (m) { return !cerrada(m); }).length);
        setTxt('mat-num-pendientes', pendientes.length);
        setTxt('mat-num-finalizadas', data.filter(cerrada).length);
        setTxt('mat-th-saldo', vista === 'finalizadas' ? 'Quedó debiendo' : 'A pagar este mes');

        var esMat = vista !== 'pendientes', fin = vista === 'finalizadas';
        document.getElementById('mat-toolbar').hidden = !esMat;
        document.getElementById('mat-toolbar-pend').hidden = esMat;

        var lista = esMat
            ? data.filter(enVista).filter(coincide).sort(function (x, y) {
                return (y.id === nuevoId ? 1 : 0) - (x.id === nuevoId ? 1 : 0) ||
                    ORDEN_ESTADO[x.estado] - ORDEN_ESTADO[y.estado] || x.apellidos.localeCompare(y.apellidos, 'es');
            })
            : pendientes.filter(coincidePend);

        listaActual = lista;
        var paginas = Math.max(1, Math.ceil(lista.length / PAGE_SIZE));
        if (pagina > paginas) pagina = paginas;
        var desde = (pagina - 1) * PAGE_SIZE, trozo = lista.slice(desde, desde + PAGE_SIZE);

        if (esMat) {
            setTxt('mat-count', lista.length + (lista.length === 1 ? ' matrícula' : ' matrículas'));
            listaBody.innerHTML = trozo.map(filaHTML).join('');
        } else {
            setTxt('mat-count-pend', lista.length + (lista.length === 1 ? ' estudiante' : ' estudiantes'));
            pendBody.innerHTML = trozo.map(pendHTML).join('');
        }
        listaWrap.hidden = !esMat || !lista.length;
        pendWrap.hidden = esMat || !lista.length;

        emptyEl.hidden = lista.length > 0;
        var sinFiltros = esMat ? !filtro.texto && !filtro.programa && !filtro.grupo && !filtro.estado && !filtro.pago : !textoPend;
        setTxt('mat-empty-ttl', esMat
            ? (sinFiltros ? (fin ? 'Todavía no hay matrículas finalizadas' : 'Todavía no hay matrículas vigentes') : 'No hay matrículas con estos filtros')
            : (sinFiltros ? 'Todos los estudiantes están matriculados' : 'Nadie coincide con la búsqueda'));
        setTxt('mat-empty-sub', esMat
            ? (sinFiltros ? (fin ? 'Aquí quedan las matrículas graduadas y retiradas para consultarlas.' : 'Matricula a un estudiante registrado con «Nueva matrícula».') : 'Prueba con otro nombre, documento o código, o quita el filtro de programa, grupo, estado o pago.')
            : (sinFiltros ? 'Cuando registres un estudiante nuevo en Estudiantes, aparecerá aquí para matricularlo.' : 'Prueba con otro nombre o documento.'));
        document.getElementById('mat-limpiar').hidden = sinFiltros;

        pagEl.hidden = paginas <= 1;
        pagPrev.disabled = pagina === 1;
        pagNext.disabled = pagina === paginas;
        pagPages.innerHTML = '';
        for (var p = 1; p <= paginas; p++) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'mat-pg-num' + (p === pagina ? ' is-active' : '');
            b.textContent = p;
            b.setAttribute('aria-label', 'Página ' + p);
            if (p === pagina) b.setAttribute('aria-current', 'page');
            b.dataset.p = p;
            pagPages.appendChild(b);
        }
    }
    function setTxt(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

    /* ── Exportar la lista filtrada a un archivo que abre Excel (CSV con ; y BOM para las tildes) ── */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    function exportar() {
        var filas;
        if (vista === 'pendientes') {
            filas = [['Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Edad', 'Acudiente', 'Parentesco', 'Documentos pendientes', 'Registrado']]
                .concat(listaActual.map(function (e) {
                    return [e.nombres, e.apellidos, e.tipo_doc, e.documento, edad(e.fecha_nac), e.acudiente ? e.acudiente.nombre : '',
                        e.acudiente ? e.acudiente.parentesco : '', e.documentos_pendientes.join(', '), e.registrado];
                }));
        } else {
            filas = [['Código', 'Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Programa', 'Grupo', 'Jornada', 'Estado', 'Fecha del estado',
                'Fecha de matrícula', 'Plan de pago', 'Cuotas pagadas', 'Cuotas del plan', 'Descuento %', 'Pagado', 'A pagar este mes', 'Vencido', 'Por pagar en total', 'Celular', 'Correo']]
                .concat(listaActual.map(function (m) {
                    var g = grupoDe(m.grupo_id) || {};
                    return [m.codigo, m.nombres, m.apellidos, m.tipo_doc, m.documento, g.programa || (programaDe(m.programa_id) || {}).nombre, g.grupo + ' · ' + g.codigo, g.jornada,
                        ESTADOS[m.estado].label, m.estado_fecha, m.fecha, m.plan_pago ? planNombre(m.plan_pago, m.tipo_periodo) : 'Sin elegir',
                        m.plan_pago ? m.cuotas_pagadas : '', m.plan_pago ? m.cuotas_total : '', m.descuento_pct || 0, m.pagado, m.saldo, m.saldo_vencido, m.por_pagar, m.telefono, m.email];
                }));
        }
        var csv = '\ufeff' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
        var nombre = 'matriculas-' + { vigentes: 'vigentes', pendientes: 'por-matricular', finalizadas: 'finalizadas' }[vista] + '-' + HOY + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a');
        a.href = url; a.download = nombre;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        toast('Se descargó ' + nombre + ' con ' + listaActual.length + (listaActual.length === 1 ? ' fila.' : ' filas.'));
    }
    document.querySelectorAll('[data-exportar]').forEach(function (b) { b.addEventListener('click', exportar); });

    function irAPagina(p) {
        pagina = p;
        render();
        document.querySelector('.mat-vistas').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    pagPages.addEventListener('click', function (e) { var b = e.target.closest('.mat-pg-num'); if (b) irAPagina(parseInt(b.dataset.p)); });
    pagPrev.addEventListener('click', function () { if (pagina > 1) irAPagina(pagina - 1); });
    pagNext.addEventListener('click', function () { irAPagina(pagina + 1); });

    /* Pestañas de la vista */
    document.querySelector('.mat-vistas').addEventListener('click', function (e) {
        var b = e.target.closest('.mat-vista-btn');
        if (!b || b.dataset.vista === vista) return;
        vista = b.dataset.vista;
        document.querySelectorAll('.mat-vista-btn').forEach(function (x) {
            x.classList.toggle('is-active', x === b);
            x.setAttribute('aria-selected', x === b ? 'true' : 'false');
        });
        if (vista !== 'pendientes') { filtro.estado = ''; opcionesEstado(); }
        pagina = 1;
        render();
    });

    /* ── Filtros ── */
    document.getElementById('mat-search').addEventListener('input', function (e) { filtro.texto = e.target.value.toLowerCase().trim(); pagina = 1; render(); });
    document.getElementById('mat-search-pend').addEventListener('input', function (e) { textoPend = e.target.value.toLowerCase().trim(); pagina = 1; render(); });

    var cbPrograma = document.getElementById('mat-cb-programa');
    var cbGrupo    = document.getElementById('mat-cb-grupo');
    var cbEstado   = document.getElementById('mat-cb-estado');
    var cbPago     = document.getElementById('mat-cb-pago');
    [cbPrograma, cbGrupo, cbEstado, cbPago].forEach(initCombobox);

    // Solo programas y grupos que tienen matrículas
    var progIds = [], grupoIds = [];
    data.forEach(function (m) {
        if (progIds.indexOf(m.programa_id) < 0) progIds.push(m.programa_id);
        if (m.grupo_id && grupoIds.indexOf(m.grupo_id) < 0) grupoIds.push(m.grupo_id);
    });
    function nombrePrograma(id) { var g = grupos.find(function (x) { return x.programa_id === id; }); var p = programaDe(id); return corto((p && p.nombre) || (g && g.programa) || ''); }
    cbPrograma.setOptions([{ value: '', label: 'Todos los programas' }].concat(progIds.map(function (id) { return { value: String(id), label: nombrePrograma(id) }; })
        .sort(function (a, b) { return a.label.localeCompare(b.label, 'es'); })));
    cbPrograma.setValue('', 'Todos los programas', { silent: true });
    function opcionesGrupo() {
        var lista = grupoIds.map(grupoDe).filter(function (g) { return g && (!filtro.programa || String(g.programa_id) === filtro.programa); })
            .sort(function (a, b) { return a.codigo.localeCompare(b.codigo); });
        cbGrupo.setOptions([{ value: '', label: 'Todos los grupos' }].concat(lista.map(function (g) { return { value: String(g.id), label: g.grupo + ' · ' + g.codigo + ' · ' + g.jornada }; })));
        cbGrupo.setValue('', 'Todos los grupos', { silent: true });
    }
    opcionesGrupo();
    function opcionesEstado() {
        var estados = vista === 'finalizadas' ? ['graduado', 'retirado', 'anulado'] : ['activo', 'aplazado'];
        cbEstado.setOptions([{ value: '', label: 'Todos los estados' }].concat(estados.map(function (k) { return { value: k, label: ESTADOS[k].label }; })));
        cbEstado.setValue('', 'Todos los estados', { silent: true });
    }
    opcionesEstado();
    cbPago.setOptions([{ value: '', label: 'Todos los pagos' }].concat(Object.keys(PAGOS).map(function (k) { return { value: k, label: PAGOS[k].label }; })));
    cbPago.setValue('', 'Todos los pagos', { silent: true });

    document.getElementById('mat-val-programa').addEventListener('change', function (e) { filtro.programa = e.target.value; filtro.grupo = ''; opcionesGrupo(); pagina = 1; render(); });
    document.getElementById('mat-val-grupo').addEventListener('change', function (e) { filtro.grupo = e.target.value; pagina = 1; render(); });
    document.getElementById('mat-val-estado').addEventListener('change', function (e) { filtro.estado = e.target.value; pagina = 1; render(); });
    document.getElementById('mat-val-pago').addEventListener('change', function (e) { filtro.pago = e.target.value; pagina = 1; render(); });

    document.getElementById('mat-limpiar').addEventListener('click', function () {
        if (vista === 'pendientes') {
            textoPend = '';
            document.getElementById('mat-search-pend').value = '';
        } else {
            filtro = { programa: '', grupo: '', estado: '', pago: '', texto: '' };
            document.getElementById('mat-search').value = '';
            cbPrograma.setValue('', 'Todos los programas', { silent: true });
            opcionesGrupo();
            cbEstado.setValue('', 'Todos los estados', { silent: true });
            cbPago.setValue('', 'Todos los pagos', { silent: true });
        }
        pagina = 1;
        render();
    });

    // Toda la fila abre la ficha; en "Por matricular" el botón abre el asistente
    listaBody.addEventListener('click', function (e) {
        var fila = e.target.closest('.mat-fila');
        if (fila) abrirFicha(parseInt(fila.dataset.id), fila.querySelector('.mat-icon-btn-fila'));
    });
    pendBody.addEventListener('click', function (e) {
        var b = e.target.closest('[data-matricular]');
        if (b && !b.disabled) abrirModal(parseInt(b.dataset.matricular));
    });

    /* ── Aviso ── */
    var toastEl = document.getElementById('mat-toast');
    var toastTimer;
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 5200);
    }

    /* ══ Ficha de la matrícula ══ */
    var drawer      = document.getElementById('mat-drawer');
    var fichaEl     = document.getElementById('mat-ficha');
    var accionEl    = document.getElementById('mat-accion');
    var fichaFoot   = document.getElementById('mat-ficha-foot');
    var accionFoot  = document.getElementById('mat-accion-foot');
    var fiTabs      = document.getElementById('mat-fi-tabs');
    var fiPanel     = document.getElementById('mat-fi-panel');
    var accionError = document.getElementById('mat-accion-error');
    var actual = null, tabActual = 'general', accionActual = null, accionPago = null, origenFoco = null;
    var reciboNuevo = null;        // recibo recién registrado: la pestaña Pagos ofrece imprimirlo
    var comprobanteNuevo = null;   // igual con el comprobante de una devolución

    function abrirFicha(id, origen) {
        actual = matDe(id);
        if (!actual) return;
        origenFoco = origen || null;
        tabActual = 'general';
        mostrarFicha();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        setTimeout(function () { document.getElementById('mat-drawer-close').focus(); }, 60);
    }
    function cerrarFicha() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (origenFoco && document.body.contains(origenFoco)) origenFoco.focus();
    }
    document.getElementById('mat-drawer-overlay').addEventListener('click', cerrarFicha);
    document.getElementById('mat-drawer-close').addEventListener('click', cerrarFicha);

    function mostrarFicha() {
        accionActual = null;
        fichaEl.hidden = false; fichaFoot.hidden = false; fiTabs.hidden = false;
        accionEl.hidden = true; accionFoot.hidden = true;
        var m = actual, g = grupoDe(m.grupo_id) || {};

        var av = document.getElementById('mat-fi-avatar');
        av.textContent = iniciales(m);
        av.className = 'mat-drawer-avatar mat-esc-' + escClass(g.escuela) + (cerrada(m) ? ' is-cerrada' : '');
        setTxt('mat-fi-nombre', nombreCompleto(m));
        setTxt('mat-fi-doc', m.tipo_doc + ' ' + fmtDoc(m.documento) + ' · ' + m.codigo + (edad(m.fecha_nac) !== null ? ' · ' + edad(m.fecha_nac) + ' años' : ''));
        document.getElementById('mat-fi-badges').innerHTML = tag(ESTADOS[m.estado]) + tag(PAGOS[pagoClave(m)]) +
            (esMenor(m) ? '<span class="mat-menor">Menor de edad</span>' : '');

        document.querySelectorAll('.mat-fi-tab').forEach(function (t) {
            t.classList.toggle('is-active', t.dataset.tab === tabActual);
            t.setAttribute('aria-selected', t.dataset.tab === tabActual ? 'true' : 'false');
        });
        fiPanel.innerHTML = panelFicha(tabActual);

        // Solo las acciones que aplican a su estado
        document.getElementById('mat-btn-pagar').hidden = !(m.por_pagar > 0) || m.estado === 'anulado';
        document.getElementById('mat-btn-grupo').hidden = m.estado !== 'activo';
        document.getElementById('mat-btn-estado').hidden = !(TRANSICIONES[m.estado] || []).length;
        fichaFoot.hidden = !fichaFoot.querySelector('.mat-btn:not([hidden])');
    }
    fiTabs.addEventListener('click', function (e) {
        var t = e.target.closest('.mat-fi-tab');
        if (!t) return;
        tabActual = t.dataset.tab;
        mostrarFicha();
    });

    function dato(lbl, valor, completo) {
        return '<div class="mat-info-item' + (completo ? ' mat-info-full' : '') + '"><span class="mat-info-lbl">' + lbl + '</span><span class="mat-info-val">' + valor + '</span></div>';
    }
    function seccion(titulo, cuerpo, extra) {
        return '<section class="mat-sec"><div class="mat-sec-head"><h4 class="mat-sec-title">' + titulo + '</h4>' + (extra || '') + '</div>' + cuerpo + '</section>';
    }

    function alertasDe(m) {
        var out = [];
        if (m.saldo_vencido > 0) out.push({ nivel: 'alta', icono: 'pago', texto: 'Tiene ' + fmtPesos(m.saldo_vencido) + ' vencidos' });
        if (!cerrada(m) && m.documentos_pendientes.length) out.push({ nivel: 'media', icono: 'doc', texto: 'Documentos pendientes: ' + m.documentos_pendientes.join(', ') });
        if (m.estado === 'activo' && m.asistencia !== null && m.asistencia < 80) out.push({ nivel: 'media', icono: 'asist', texto: 'Asistencia en ' + m.asistencia + ' %, por debajo del 80 % esperado' });
        return out;
    }

    function panelFicha(tab) {
        var m = actual, g = grupoDe(m.grupo_id) || {}, prog = programaDe(m.programa_id), html = '';

        if (tab === 'general') {
            var al = alertasDe(m);
            if (al.length) {
                html += '<ul class="mat-fi-alertas">' + al.map(function (x) {
                    return '<li class="mat-alerta-' + x.nivel + '">' + ICONOS[x.icono] + esc(x.texto) + '</li>';
                }).join('') + '</ul>';
            }
            if (m.estado !== 'activo') {
                var ttl = { aplazado: 'Aplazada desde el ', retirado: 'Retirada el ', graduado: 'Graduada el ', anulado: 'Anulada el ' }[m.estado] + fmtFecha(m.estado_fecha);
                // Graduada o retirada: un solo aviso con el motivo, que está cerrada y cómo volver a matricularlo
                var libre = cerrada(m) && pendDe(m.estudiante_id);   // sin otra matrícula vigente
                html += '<div class="mat-callout mat-callout-' + m.estado + '"><strong>' + ttl + '</strong>' +
                    (m.estado_motivo ? '<p>' + esc(m.estado_motivo) + '</p>' : '') +
                    (m.estado === 'aplazado' ? '<p>No ocupa cupo. Para que vuelva a clases usa «Cambiar estado» › Reintegrar.' +
                        (m.cobros.some(function (c) { return c.tipo === 'matricula' && porPagar(c) && !c.vence; }) ? ' El derecho de matrícula queda en espera: vence cuando empiecen las clases del grupo al que vuelva.' : '') + '</p>' : '') +
                    (m.estado === 'anulado' ? '<p>Se anuló porque se hizo por error: no ocupa cupo, no se le cobra nada y no cuenta en su trayectoria. Solo se consulta. ' +
                        (libre ? 'Si de verdad se va a matricular, crea una matrícula nueva.' : esc(primerNombre(m)) + ' ya tiene otra matrícula vigente.') + '</p>'
                    : cerrada(m) ? '<p>Esta matrícula está cerrada y solo se consulta. ' + (libre
                        ? (m.estado === 'graduado' ? 'Si ' + esc(primerNombre(m)) + ' va a estudiar otro programa, crea una matrícula nueva.' : 'Si ' + esc(primerNombre(m)) + ' vuelve a estudiar, crea una matrícula nueva; esta queda como historial.')
                        : esc(primerNombre(m)) + ' ya tiene otra matrícula vigente.') + '</p>' : '') +
                    (libre ? '<div class="mat-callout-acc"><button type="button" class="mat-btn mat-btn-secondary mat-btn-sm" data-rematricular="' + m.estudiante_id + '">' + ICONOS.mas +
                        ({ graduado: 'Matricular en otro programa', anulado: 'Matricular de nuevo' }[m.estado] || 'Volver a matricular') + '</button></div>' : '') + '</div>';
            }
            html += '<div class="mat-info-grid">' +
                dato('Programa', esc(g.programa || (prog && prog.nombre)), true) +
                dato('Escuela', '<span class="mat-esc-tag mat-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>') +
                dato('Grupo', esc(g.grupo) + ' · <span class="mat-num-inline">' + esc(g.codigo) + '</span>') +
                dato('Jornada', esc(g.jornada)) +
                dato('Docente', esc(g.docente)) +
                dato('Horario', (g.horario || []).map(function (h) { return '<span class="mat-horario-linea">' + esc(h) + '</span>'; }).join(''), true) +
                dato('Inicio de clases', fmtFecha(g.inicio)) +
                dato('Fin del programa', fmtFecha(g.fin)) +
                dato('Fecha de matrícula', fmtFecha(m.fecha)) +
                dato('Asistencia', m.asistencia === null ? 'Sin clases registradas' : m.asistencia + ' %') +
            '</div>' +
            (m.origen ? '<p class="mat-sec-nota">Viene de la matrícula <button type="button" class="mat-link-btn mat-link-neutro" data-ir="' + m.origen.id + '">' + esc(m.origen.codigo) + '</button> por cambio de programa.</p>' : '') +
            (m.siguiente ? '<p class="mat-sec-nota">Cambió de programa: ahora está en la matrícula <button type="button" class="mat-link-btn mat-link-neutro" data-ir="' + m.siguiente.id + '">' + esc(m.siguiente.codigo) + '</button>.</p>' : '') +
            documentosHTML(m) +
            otrasHTML(m) +
            seccion('Contacto', '<div class="mat-info-grid">' +
                dato('Celular', m.telefono ? fmtTel(m.telefono) : '—') +
                dato('Correo', m.email ? esc(m.email) : '—') +
                dato('Acudiente', m.acudiente ? esc(m.acudiente.nombre) + ' · ' + esc(m.acudiente.parentesco) + ' · ' + fmtTel(m.acudiente.telefono) : (esMenor(m) ? 'Sin acudiente' : 'No tiene (mayor de edad)'), true) +
            '</div>') +
            '<p class="mat-sec-nota">Los datos personales, el acudiente y los documentos se actualizan en Estudiantes.</p>';
        }

        if (tab === 'pagos') {
            if (!m.plan_pago) {
                html += '<div class="mat-callout mat-callout-aviso"><strong>' + esc(primerNombre(m)) + ' todavía no elige su plan de pago</strong>' +
                    '<p>Lo elige desde su panel: mensual o ' + planNombre('periodo', m.tipo_periodo).toLowerCase() + '. Mientras tanto solo tiene el derecho de matrícula (pago único); cuando elija, aquí aparecerán sus cuotas con su fecha de vencimiento.</p></div>';
            }
            html += '<div class="mat-finanzas">' +
                '<div class="mat-fin is-ok"><span>Pagado</span><strong>' + fmtPesos(m.pagado) + '</strong></div>' +
                // Lo atrasado se acumula con lo del mes: este mes paga las dos cosas
                '<div class="mat-fin' + (m.saldo_vencido > 0 ? ' is-deuda' : '') + '"><span>A pagar este mes</span><strong>' + fmtPesos(m.saldo) + '</strong>' +
                    (m.saldo_vencido > 0 ? '<small>' + fmtPesos(m.saldo_vencido) + ' vencido' + (m.saldo > m.saldo_vencido ? ' + ' + fmtPesos(m.saldo - m.saldo_vencido) + ' del mes' : '') + '</small>' : '') + '</div>' +
                '<div class="mat-fin"><span>Por pagar en total</span><strong>' + fmtPesos(m.por_pagar) + '</strong></div>' +
            '</div>';
            if (reciboNuevo) {
                var rn = m.recaudos.find(function (r) { return r.recibo === reciboNuevo; });
                if (rn) html += '<div class="mat-callout mat-callout-ok"><strong>Pago registrado · recibo ' + esc(rn.recibo) + '</strong>' +
                    '<p>' + fmtPesos(rn.valor) + ' por ' + esc(rn.metodo) + '. Imprímelo o guárdalo en PDF para entregárselo a ' + esc(primerNombre(m)) + '.</p>' +
                    '<div class="mat-callout-acc"><a class="mat-btn mat-btn-secondary mat-btn-sm" href="' + esc(rn.url) + '" target="_blank" rel="noopener">' + ICONOS.recibo + 'Ver e imprimir recibo</a></div></div>';
            }
            if (comprobanteNuevo) {
                var dn = m.devoluciones.find(function (d) { return d.comprobante === comprobanteNuevo; });
                if (dn) html += '<div class="mat-callout mat-callout-ok"><strong>Devolución registrada · comprobante ' + esc(dn.comprobante) + '</strong>' +
                    '<p>' + fmtPesos(dn.valor) + ' por ' + esc(dn.metodo) + '. Imprímelo para que ' + esc(primerNombre(m)) + ' lo firme al recibir el dinero.</p>' +
                    '<div class="mat-callout-acc"><a class="mat-btn mat-btn-secondary mat-btn-sm" href="' + esc(dn.url) + '" target="_blank" rel="noopener">' + ICONOS.recibo + 'Ver e imprimir comprobante</a></div></div>';
            }
            if (m.saldo_favor > 0) {
                html += '<div class="mat-callout mat-callout-ok"><strong>Tiene ' + fmtPesos(m.saldo_favor) + ' a favor</strong>' +
                    '<p>Es dinero trasladado de un programa anterior o de cuotas que se anularon. Se aplica solo a sus cuotas en cuanto existan (cuando elija su plan de pago).</p></div>';
            }
            if (m.adelantado.total > 0) {
                html += '<div class="mat-callout mat-callout-aviso"><strong>Pagó ' + fmtPesos(m.adelantado.total) + ' por adelantado</strong>' +
                    '<p>Son cuotas de meses que ya no va a estudiar' + (m.estado === 'aplazado' ? ' mientras está aplazada (si se reintegra, se le abonan a sus cuotas)' : '') + '. ' +
                    (m.adelantado.cubre > 0 ? fmtPesos(m.adelantado.cubre) + ' cubren lo que debe vencido y se le devolverían ' + fmtPesos(m.adelantado.resto) + '.' : 'Se le pueden devolver.') + '</p>' +
                    '<div class="mat-callout-acc"><button type="button" class="mat-btn mat-btn-secondary mat-btn-sm" data-devolver>' + ICONOS.pago + 'Registrar devolución</button></div></div>';
            }
            var vigente = !cerrada(m);
            html += '<div class="mat-info-grid">' +
                (m.plan_pago
                    ? dato('Plan de pago', planNombre(m.plan_pago, m.tipo_periodo)) +
                      dato('Cuotas pagadas', m.cuotas_pagadas + ' de ' + m.cuotas_total) +
                      dato('Vencimiento', 'Día ' + (opciones.dia_vencimiento || 10) + ' de cada ' + (m.plan_pago === 'mensual' ? 'mes' : (m.tipo_periodo === 'semestre' ? 'semestre' : 'trimestre')))
                    : '') +
                dato('Último pago', fmtFecha(m.ultimo_pago)) +
                dato('Descuento', m.descuento_pct > 0 ? fmtPct(m.descuento_pct) + ' sobre el programa · ' + esc(m.descuento_motivo || '') : 'Sin descuento', true) +
            '</div>';
            html += seccion('Cobros', m.cobros.length ? '<div class="mat-cobros-wrap"><table class="mat-cobros"><thead><tr>' +
                    '<th>Concepto</th><th>Vence</th><th>Detalle</th><th class="mat-num-col">Valor</th></tr></thead><tbody>' +
                    m.cobros.map(cobroHTML).join('') + '</tbody></table></div>'
                : '<p class="mat-campo-ayuda">Sin cobros todavía.</p>',
                vigente ? '<button type="button" class="mat-link-btn mat-link-neutro" data-descuento>' + (m.descuento_pct > 0 ? 'Cambiar descuento' : 'Aplicar descuento') + '</button>' : '');
            html += seccion('Pagos recibidos', m.recaudos.length ? '<ul class="mat-recaudos">' + m.recaudos.map(recaudoHTML).join('') + '</ul>'
                : '<p class="mat-campo-ayuda">Todavía no ha hecho pagos.</p>');
            if (m.devoluciones.length) {
                html += seccion('Devoluciones', '<ul class="mat-recaudos">' + m.devoluciones.map(function (d) {
                    return '<li class="mat-recaudo"><div class="mat-recaudo-top"><a class="mat-recaudo-recibo" href="' + esc(d.url) + '" target="_blank" rel="noopener" title="Ver e imprimir el comprobante">' + ICONOS.recibo + esc(d.comprobante) + '</a>' +
                        '<strong class="mat-recaudo-valor">− ' + fmtPesos(d.valor) + '</strong></div>' +
                        '<span class="mat-recaudo-meta">' + fmtFecha(d.fecha) + ' · ' + esc(d.metodo) + (d.referencia ? ' · Ref. ' + esc(d.referencia) : '') + (d.registro ? ' · Registró ' + esc(d.registro) : '') + '</span>' +
                        '<span class="mat-recaudo-meta">' + esc(d.motivo) + '</span></li>';
                }).join('') + '</ul>');
            }
        }

        if (tab === 'historial') {
            html += m.historial.length
                ? '<ul class="mat-historial">' + m.historial.map(function (h) {
                    return '<li class="mat-hist-item is-' + esc(h.accion) + '"><span class="mat-hist-punto" aria-hidden="true"></span><div>' +
                        '<div class="mat-hist-txt">' + esc(h.texto) + '</div>' +
                        '<div class="mat-hist-meta">' + fmtFecha(h.fecha) + ' · ' + esc(h.fecha.slice(11)) + ' · ' + esc(h.quien) + '</div></div></li>';
                }).join('') + '</ul>'
                : '<p class="mat-campo-ayuda">Todavía no hay movimientos registrados en esta matrícula.</p>';
        }
        return html;
    }

    /* Constancia (matrícula vigente) y paz y salvo (si no debe nada en ninguna de sus matrículas) */
    function documentosHTML(m) {
        if (m.estado === 'anulado') return '';
        var vigente = !cerrada(m);
        var abrir = function (url, txt) { return '<a class="mat-btn mat-btn-secondary mat-btn-sm" href="' + esc(url) + '" target="_blank" rel="noopener">' + ICONOS.recibo + txt + '</a>'; };
        return seccion('Documentos', '<div class="mat-docs">' +
            (vigente ? abrir(m.documentos_url.constancia, 'Constancia de matrícula') : '') +
            (m.deuda_estudiante > 0
                ? '<span class="mat-doc-off">Paz y salvo: no disponible, debe ' + fmtPesos(m.deuda_estudiante) + ' en total</span>'
                : m.sin_plan_vigente
                    ? '<span class="mat-doc-off">Paz y salvo: no disponible, todavía no elige su plan de pago</span>'
                    : abrir(m.documentos_url.paz_y_salvo, 'Paz y salvo')) +
        '</div>');
    }

    /* Las demás matrículas de la misma persona (programas anteriores o la nueva), para saltar a su ficha */
    function otrasHTML(m) {
        var otras = data.filter(function (x) { return x.estudiante_id === m.estudiante_id && x.id !== m.id; })
            .sort(function (a, b) { return b.fecha.localeCompare(a.fecha) || b.id - a.id; });
        if (!otras.length) return '';
        var cuando = { activo: 'Activa desde el ', aplazado: 'Aplazada desde el ', retirado: 'Retirada el ', graduado: 'Graduada el ', anulado: 'Anulada el ' };
        var flecha = '<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>';
        return seccion('Otras matrículas de ' + esc(primerNombre(m)), '<ol class="mat-tray">' + otras.map(function (x) {
            var g = grupoDe(x.grupo_id) || {};
            return '<li class="mat-tray-item is-link is-' + x.estado + '"><button type="button" class="mat-tray-btn" data-ir="' + x.id + '">' +
                '<span class="mat-tray-punto" aria-hidden="true"></span><span class="mat-tray-main">' +
                    '<span class="mat-tray-top"><strong>' + esc(corto(g.programa)) + '</strong>' + tag(ESTADOS[x.estado]) + '</span>' +
                    '<span class="mat-tray-meta">' + esc(x.codigo) + ' · ' + esc(g.grupo) + ' · ' + esc(g.jornada) + '</span>' +
                    '<span class="mat-tray-meta">' + cuando[x.estado] + fmtFecha(x.estado_fecha || x.fecha) + '</span>' +
                    (cerrada(x) ? '<span class="mat-tray-deuda' + (x.por_pagar > 0 ? ' is-deuda' : '') + '">' + (x.por_pagar > 0 ? 'Quedó debiendo ' + fmtPesos(x.por_pagar) : 'A paz y salvo') + '</span>' : '') +
                '</span>' + flecha + '</button></li>';
        }).join('') + '</ol>', '<span class="mat-sec-dato">' + otras.length + (otras.length === 1 ? ' matrícula' : ' matrículas') + '</span>');
    }

    function cobroHTML(c) {
        var detalle = c.estado === 'pagado'
            ? '<span class="mat-cobro-pago">Pagado el ' + fmtFecha(c.fecha_pago) + '</span>'
            : tag(COBROS[c.estado]) + (porPagar(c) && c.abonado > 0
                ? '<span class="mat-cobro-abono">Abonado ' + fmtPesos(c.abonado) + ' · faltan ' + fmtPesos(c.restante) + '</span>' : '') +
              (c.estado === 'anulado' && c.abonado > 0 ? '<span class="mat-cobro-abono">Adelantado ' + fmtPesos(c.abonado) + '</span>' : '') +
              (c.estado === 'anulado' && c.reembolsado > 0 ? '<span class="mat-cobro-abono">Salieron ' + fmtPesos(c.reembolsado) + ' (devueltos o trasladados)</span>' : '');
        return '<tr class="' + (c.estado === 'anulado' ? 'is-anulado' : '') + '">' +
            '<td class="mat-cobro-concepto"><strong>' + esc(c.concepto) + '</strong>' + (c.periodo ? '<span>' + esc(c.periodo) + '</span>' : '') + '</td>' +
            '<td class="mat-num-inline">' + venceTxt(c) + '</td>' +
            '<td>' + detalle + '</td>' +
            '<td class="mat-num-col mat-cobro-valor">' + fmtPesos(c.valor) +
                (c.descuento > 0 ? '<span class="mat-cobro-desc">Incluye − ' + fmtPesos(c.descuento) + ' de descuento</span>' : '') + '</td>' +
        '</tr>';
    }

    /* Un pago recibido con su recibo: qué cubrió, quién lo recibió y si se anuló */
    function recaudoHTML(r) {
        var cubre = r.cubre.map(function (x) { return esc(x.concepto) + ' ' + fmtPesos(x.monto); }).join(' · ');
        // Una matrícula graduada quedó a paz y salvo: sus pagos ya no se anulan
        var anulable = !r.anulado && actual && actual.estado !== 'graduado';
        return '<li class="mat-recaudo' + (r.anulado ? ' is-anulado' : '') + '">' +
            '<div class="mat-recaudo-top"><a class="mat-recaudo-recibo" href="' + esc(r.url) + '" target="_blank" rel="noopener" title="Ver e imprimir el recibo">' + ICONOS.recibo + esc(r.recibo) + '</a>' +
                '<strong class="mat-recaudo-valor">' + fmtPesos(r.valor) + '</strong></div>' +
            '<span class="mat-recaudo-meta">' + fmtFecha(r.fecha) + ' · ' + esc(r.metodo) + (r.referencia ? ' · Ref. ' + esc(r.referencia) : '') + (r.registro ? ' · Recibió ' + esc(r.registro) : '') + '</span>' +
            '<span class="mat-recaudo-meta">' + cubre + '</span>' +
            (r.anulado ? '<span class="mat-recaudo-anulado">' + tag({ label: 'Anulado', cls: 'app-status-muted' }) + ' el ' + fmtFecha(r.anulado_en) + (r.anulado_por ? ' por ' + esc(r.anulado_por) : '') + ': ' + esc(r.motivo_anulacion) + '</span>' : '') +
            (anulable ? '<button type="button" class="mat-link-btn mat-recaudo-anular" data-anular="' + r.id + '" aria-label="Anular el pago del recibo ' + esc(r.recibo) + '">Anular pago</button>' : '') +
        '</li>';
    }

    /* ── Acciones dentro de la ficha ── */
    var accionTtl    = document.getElementById('mat-accion-ttl');
    var accionSub    = document.getElementById('mat-accion-sub');
    var accionCuerpo = document.getElementById('mat-accion-cuerpo');
    var btnConfirmar = document.getElementById('mat-accion-confirmar');

    function grupoOpcHTML(g, name, marcado) {
        var libres = cuposLibres(g), lleno = libres <= 0;
        var pct = g.cupo ? Math.min(100, Math.round(g.inscritos / g.cupo * 100)) : 0;
        var empezo = g.inicio <= HOY;
        return '<label class="mat-opc' + (lleno ? ' is-lleno' : '') + '">' +
            '<input type="radio" name="' + name + '" value="' + g.id + '"' + (lleno ? ' disabled' : '') + (marcado ? ' checked' : '') + '>' +
            '<span class="mat-opc-main">' +
                '<span class="mat-opc-top"><strong>' + esc(g.grupo) + ' · ' + esc(g.jornada) + '</strong><span>' + esc(g.codigo) + '</span></span>' +
                (g.horario || []).map(function (h) { return '<span class="mat-opc-meta">' + esc(h) + '</span>'; }).join('') +
                '<span class="mat-opc-meta">' + esc(g.docente) + ' · ' + (empezo ? 'Empezó el ' : 'Empieza el ') + fmtFecha(g.inicio) + '</span>' +
                '<span class="mat-barra" aria-hidden="true"><span style="width:' + pct + '%"></span></span>' +
                '<span class="mat-opc-cupo">' + (lleno ? 'Sin cupos libres' : libres + (libres === 1 ? ' cupo libre' : ' cupos libres') + ' de ' + g.cupo) + '</span>' +
            '</span></label>';
    }

    function mostrarAccion(tipo, pagoId) {
        accionActual = tipo;
        accionPago = pagoId || null;
        fichaEl.hidden = true; fichaFoot.hidden = true; fiTabs.hidden = true;
        accionEl.hidden = false; accionFoot.hidden = false;
        accionError.hidden = true;
        btnConfirmar.hidden = false;
        btnConfirmar.disabled = false;
        btnConfirmar.className = 'mat-btn mat-btn-primary';
        var m = actual;

        if (tipo === 'pagar') {
            accionTtl.textContent = 'Registrar pago';
            accionSub.textContent = 'Escribe cuánto pagó ' + primerNombre(m) + '. Se aplica en orden: primero lo más viejo; si no alcanza para una cuota, queda abonada.';
            var sugerido = m.saldo > 0 ? m.saldo : (cobrosEnOrden(m)[0] || {}).restante || 0;
            accionCuerpo.innerHTML =
                '<div class="mat-field"><label class="mat-lbl" for="acc-valor">Valor recibido *</label>' +
                    '<div class="mat-valor-wrap"><span aria-hidden="true">$</span><input class="mat-input mat-input-valor" id="acc-valor" type="text" inputmode="numeric" autocomplete="off" value="' + fmtDoc(sugerido) + '"></div>' +
                    '<div class="mat-chips mat-chips-valor">' +
                        (m.saldo > 0 ? '<button type="button" class="mat-chip-btn" data-valor="' + m.saldo + '">Este mes · ' + fmtPesos(m.saldo) + '</button>' : '') +
                        '<button type="button" class="mat-chip-btn" data-valor="' + m.por_pagar + '">Todo lo que debe · ' + fmtPesos(m.por_pagar) + '</button>' +
                    '</div></div>' +
                '<div class="mat-reparto" id="acc-reparto" aria-live="polite"></div>' +
                '<div class="mat-form-row">' +
                    '<div class="mat-field"><label class="mat-lbl" for="acc-fecha">Fecha del pago *</label><input class="mat-input" id="acc-fecha" type="date" value="' + HOY + '" max="' + HOY + '"></div>' +
                    '<div class="mat-field"><label class="mat-lbl" for="acc-ref">Referencia <em id="acc-ref-opc">opcional en efectivo</em></label><input class="mat-input" id="acc-ref" type="text" maxlength="60" autocomplete="off" placeholder="N.º de transacción"></div>' +
                '</div>' +
                '<div class="mat-field"><span class="mat-lbl">Medio de pago *</span><div class="mat-chips" id="acc-metodos" role="radiogroup" aria-label="Medio de pago"></div></div>' +
                '<div class="mat-field"><label class="mat-lbl" for="acc-obs">Observación <em>opcional</em></label><textarea class="mat-input mat-textarea" id="acc-obs" maxlength="255" placeholder="Ej. Pagó en la sede con billetes de $ 50.000"></textarea></div>';
            pintarChips(document.getElementById('acc-metodos'), 'acc-met', METODOS, 'Efectivo');
            pintarReparto();
            btnConfirmar.textContent = 'Registrar pago';
        }

        if (tipo === 'anular') {
            var r = m.recaudos.find(function (x) { return x.id === pagoId; });
            accionTtl.textContent = 'Anular pago';
            accionSub.textContent = '';
            accionCuerpo.innerHTML = '<div class="mat-callout mat-callout-alerta"><strong>Se anulará el recibo ' + esc(r.recibo) + ' por ' + fmtPesos(r.valor) + '</strong>' +
                '<p>Pagado el ' + fmtFecha(r.fecha) + ' por ' + esc(r.metodo) + '. Cubría: ' + r.cubre.map(function (x) { return esc(x.concepto.toLowerCase()) + ' (' + fmtPesos(x.monto) + ')'; }).join(', ') +
                '. Todo eso volverá a figurar como pendiente y el recibo quedará marcado como anulado. Úsalo solo si el pago se registró por error.</p></div>' +
                '<div class="mat-field"><label class="mat-lbl" for="acc-motivo">Motivo *</label><textarea class="mat-input mat-textarea" id="acc-motivo" maxlength="255" placeholder="Ej. Se registró en la matrícula equivocada"></textarea></div>';
            btnConfirmar.textContent = 'Anular pago';
            btnConfirmar.className = 'mat-btn mat-btn-danger';
        }

        if (tipo === 'devolucion') {
            var ad = m.adelantado;
            accionTtl.textContent = 'Registrar devolución';
            accionSub.textContent = 'Lo pagado por adelantado de meses que ya no va a estudiar. El valor lo calcula el sistema: primero se cubre lo que debe vencido y se devuelve el resto.';
            accionCuerpo.innerHTML =
                '<div class="mat-reparto"><span class="mat-reparto-ttl">Cómo queda</span><ul>' +
                    '<li><span>Pagado por adelantado</span><span>' + fmtPesos(ad.total) + '</span></li>' +
                    (ad.cubre > 0 ? '<li><span>Cubre lo que debe vencido</span><span>− ' + fmtPesos(ad.cubre) + '</span></li>' : '') +
                    '<li><span><strong>Se le devuelve</strong></span><span><strong>' + fmtPesos(ad.resto) + '</strong></span></li></ul></div>' +
                (ad.resto > 0
                    ? '<div class="mat-form-row">' +
                        '<div class="mat-field"><label class="mat-lbl" for="acc-dev-fecha">Fecha de la devolución *</label><input class="mat-input" id="acc-dev-fecha" type="date" value="' + HOY + '" max="' + HOY + '" min="' + (m.estado_fecha || m.fecha) + '"></div>' +
                        '<div class="mat-field"><label class="mat-lbl" for="acc-ref">Referencia <em id="acc-ref-opc">opcional en efectivo</em></label><input class="mat-input" id="acc-ref" type="text" maxlength="60" autocomplete="off" placeholder="N.º de transacción"></div>' +
                      '</div>' +
                      '<div class="mat-field"><span class="mat-lbl">Medio de devolución *</span><div class="mat-chips" id="acc-metodos" role="radiogroup" aria-label="Medio de devolución"></div></div>'
                    : '<p class="mat-campo-ayuda">Todo lo adelantado se usa para cubrir lo que debe: no queda nada por devolver.</p>') +
                '<div class="mat-field"><label class="mat-lbl" for="acc-dev-motivo">Motivo *</label><textarea class="mat-input mat-textarea" id="acc-dev-motivo" maxlength="255" placeholder="Ej. Se retiró por cambio de ciudad y pidió la devolución"></textarea></div>';
            if (ad.resto > 0) pintarChips(document.getElementById('acc-metodos'), 'acc-met', METODOS, 'Efectivo');
            btnConfirmar.textContent = ad.resto > 0 ? 'Registrar devolución' : 'Aplicar a lo que debe';
        }

        if (tipo === 'descuento') {
            var muestra = m.cobros.find(function (x) { return x.tipo !== 'matricula' && porPagar(x) && !x.abonado; });
            accionTtl.textContent = m.descuento_pct > 0 ? 'Cambiar descuento' : 'Aplicar descuento';
            accionSub.textContent = 'Beca o convenio: un porcentaje sobre el valor del programa. No toca el derecho de matrícula ni la cuota del sistema, y solo cambia las cuotas que todavía no ha empezado a pagar.';
            accionCuerpo.innerHTML =
                '<div class="mat-form-row">' +
                    '<div class="mat-field"><label class="mat-lbl" for="acc-pct">Porcentaje *</label><div class="mat-valor-wrap mat-pct-wrap"><input class="mat-input mat-input-valor" id="acc-pct" type="number" min="0" max="100" step="0.5" value="' + (m.descuento_pct || '') + '" placeholder="Ej. 20"><span aria-hidden="true">%</span></div></div>' +
                '</div>' +
                '<div class="mat-reparto" id="acc-desc-prev" aria-live="polite" data-programa="' + (muestra ? muestra.valor_programa : '') + '" data-sistema="' + (muestra ? muestra.valor_sistema : '') + '" data-concepto="' + (muestra ? esc(muestra.concepto) : '') + '"></div>' +
                '<div class="mat-field"><label class="mat-lbl" for="acc-desc-motivo">Motivo o convenio <em id="acc-desc-opc">obligatorio</em></label><textarea class="mat-input mat-textarea" id="acc-desc-motivo" maxlength="255" placeholder="Ej. Beca por convenio con la alcaldía">' + esc(m.descuento_motivo || '') + '</textarea></div>' +
                (m.descuento_pct > 0 ? '<p class="mat-campo-ayuda">Para quitar el descuento escribe 0.</p>' : '');
            pintarDescuento();
            btnConfirmar.textContent = 'Guardar descuento';
        }

        if (tipo === 'grupo') {
            var actualG = grupoDe(m.grupo_id);
            var otros = grupos.filter(function (g) { return g.programa_id === m.programa_id && g.id !== m.grupo_id && abierto(g); });
            accionTtl.textContent = 'Cambiar de grupo';
            accionSub.textContent = 'Ahora está en el ' + actualG.grupo + ' (' + actualG.codigo + ', ' + actualG.jornada + '). Solo grupos del mismo programa que no han terminado.';
            accionCuerpo.innerHTML = otros.length
                ? '<div class="mat-opciones" role="radiogroup" aria-label="Grupo nuevo">' + otros.map(function (g) { return grupoOpcHTML(g, 'acc-grupo', false); }).join('') + '</div>'
                : '<div class="mat-callout"><strong>No hay otros grupos abiertos de este programa</strong><p>Crea uno nuevo en Grupos si necesitas cambiarlo de jornada.</p></div>';
            btnConfirmar.textContent = 'Cambiar de grupo';
            btnConfirmar.hidden = !otros.length;
        }

        if (tipo === 'estado') {
            // Cambiar de programa no es un estado: retira esta matrícula y crea otra en el programa nuevo
            var destinos = (TRANSICIONES[m.estado] || []).concat(cerrada(m) ? [] : ['programa']);
            accionTtl.textContent = 'Cambiar estado';
            accionSub.textContent = 'La matrícula está ' + ESTADOS[m.estado].label.toLowerCase() + '.';
            accionCuerpo.innerHTML =
                '<div class="mat-opciones" role="radiogroup" aria-label="Nuevo estado">' + destinos.map(function (k) {
                    var o = k === 'programa' ? CAMBIO_PROGRAMA : ESTADOS[k];
                    return '<label class="mat-opc"><input type="radio" name="acc-estado" value="' + k + '"><span class="mat-opc-main">' +
                        '<span class="mat-opc-top"><strong>' + o.accion + '</strong></span><span class="mat-opc-desc">' + o.desc + '</span></span></label>';
                }).join('') + '</div>' +
                '<div id="acc-estado-extra"></div>' +
                '<div class="mat-form-row" id="acc-fecha-row">' +
                    '<div class="mat-field"><label class="mat-lbl" for="acc-fecha-estado">Fecha *</label><input class="mat-input" id="acc-fecha-estado" type="date" value="' + HOY + '" max="' + HOY + '" min="' + fechaMinEstado(m) + '"></div>' +
                '</div>' +
                '<div class="mat-field" id="acc-motivo-wrap"><label class="mat-lbl" for="acc-motivo-estado">Motivo <em id="acc-motivo-opc">opcional</em></label>' +
                    '<textarea class="mat-input mat-textarea" id="acc-motivo-estado" maxlength="255" placeholder="Ej. Viaja por trabajo hasta enero"></textarea></div>';
            btnConfirmar.textContent = 'Guardar estado';
        }
        accionEl.scrollTop = 0;
        setTimeout(function () { document.getElementById('mat-accion-volver').focus(); }, 30);
    }

    /* Lo que debe, en el orden en que se paga (igual que el servidor): por vencimiento y al final el derecho en espera */
    function cobrosEnOrden(m) {
        return m.cobros.filter(function (c) { return porPagar(c) && c.restante > 0; }).sort(function (a, b) {
            return (a.vence ? 0 : 1) - (b.vence ? 0 : 1) || (a.vence || '').localeCompare(b.vence || '') || a.id - b.id;
        });
    }
    function valorEscrito() { return parseInt((document.getElementById('acc-valor').value || '').replace(/\D/g, '')) || 0; }

    /* Cómo se reparte el valor escrito: qué cuotas completa y cuál queda abonada */
    function pintarReparto() {
        var m = actual, cont = document.getElementById('acc-reparto'), queda = valorEscrito(), filas = [];
        cobrosEnOrden(m).forEach(function (c) {
            if (queda <= 0) return;
            var aplica = Math.min(queda, c.restante);
            filas.push('<li><span>' + esc(c.concepto) + (c.estado === 'vencido' ? ' ' + tag(COBROS.vencido) : '') + '</span><span>' +
                (aplica >= c.restante ? '<strong>Completa</strong> ' + fmtPesos(aplica) : 'Abono ' + fmtPesos(aplica) + ' · <em>faltan ' + fmtPesos(c.restante - aplica) + '</em>') + '</span></li>');
            queda -= aplica;
        });
        cont.innerHTML = valorEscrito() > m.por_pagar
            ? '<p class="mat-reparto-error">Supera lo que debe en total (' + fmtPesos(m.por_pagar) + ').</p>'
            : filas.length ? '<span class="mat-reparto-ttl">Así se aplica · un solo recibo</span><ul>' + filas.join('') + '</ul>' : '';
    }

    /* Vista previa del descuento sobre una cuota de ejemplo */
    function pintarDescuento() {
        var cont = document.getElementById('acc-desc-prev');
        var pct = parseFloat(document.getElementById('acc-pct').value) || 0;
        document.getElementById('acc-desc-opc').textContent = pct > 0 ? 'obligatorio' : 'opcional';
        var prog = parseInt(cont.dataset.programa), sis = parseInt(cont.dataset.sistema) || 0;
        if (!prog || pct < 0 || pct > 100) { cont.innerHTML = actual.plan_pago ? '' : '<p class="mat-campo-ayuda">Todavía no tiene plan de pago: el descuento se aplicará a sus cuotas cuando lo elija.</p>'; return; }
        var desc = Math.round(prog * pct / 100);
        cont.innerHTML = '<span class="mat-reparto-ttl">Ejemplo · ' + esc(cont.dataset.concepto) + '</span><ul>' +
            '<li><span>Programa ' + fmtPesos(prog) + ' − ' + fmtPct(pct) + '</span><span>' + fmtPesos(prog - desc) + '</span></li>' +
            '<li><span>Cuota del sistema</span><span>' + fmtPesos(sis) + '</span></li>' +
            '<li><span><strong>Nueva cuota</strong></span><span><strong>' + fmtPesos(prog - desc + sis) + '</strong></span></li></ul>';
    }

    /* Al elegir el estado: motivo obligatorio, aviso de graduación o grupo para reintegrar */
    function pintarExtraEstado(estado) {
        var m = actual, extra = document.getElementById('acc-estado-extra');
        var conMotivo = CON_MOTIVO.indexOf(estado) >= 0;
        document.getElementById('acc-motivo-opc').textContent = conMotivo ? '' : 'opcional';
        document.querySelector('#acc-motivo-wrap .mat-lbl').firstChild.textContent = conMotivo ? 'Motivo * ' : 'Motivo ';
        extra.innerHTML = '';
        document.getElementById('acc-fecha-row').hidden = estado === 'programa';
        btnConfirmar.textContent = estado === 'programa' ? 'Cambiar de programa' : 'Guardar estado';
        if (estado === 'programa') {
            document.getElementById('acc-motivo-opc').textContent = '';
            document.querySelector('#acc-motivo-wrap .mat-lbl').firstChild.textContent = 'Motivo * ';
            var otros = progConGrupos.filter(function (p) { return p.id !== m.programa_id; });
            extra.innerHTML = otros.length
                ? '<div class="mat-field"><span class="mat-lbl">Programa nuevo *</span><div class="mat-opciones mat-opciones-compactas" role="radiogroup" aria-label="Programa nuevo">' +
                    otros.map(function (p) {
                        return '<label class="mat-opc"><input type="radio" name="acc-prog" value="' + p.id + '"><span class="mat-opc-main"><span class="mat-opc-top"><strong>' + esc(corto(p.nombre)) + '</strong></span>' +
                            '<span class="mat-opc-meta">' + esc(p.escuela) + ' · ' + p.meses + ' meses</span></span></label>';
                    }).join('') + '</div></div>' +
                  '<div class="mat-field"><span class="mat-lbl">Grupo *</span><div class="mat-opciones" id="acc-prog-grupos" role="radiogroup" aria-label="Grupo del programa nuevo"><p class="mat-campo-ayuda">Elige primero el programa.</p></div></div>'
                : '<p class="mat-campo-ayuda">No hay otros programas con grupos abiertos en la sede.</p>';
            return;
        }
        if (estado === 'graduado') {
            var g = grupoDe(m.grupo_id), termino = g.estado === 'finalizado' || g.fin <= HOY;
            extra.innerHTML = (!termino ? '<div class="mat-callout mat-callout-aviso"><strong>Su grupo termina el ' + fmtFecha(g.fin) + '</strong><p>Solo se puede graduar cuando el grupo finalice.</p></div>' : '') +
                (m.por_pagar > 0 ? '<div class="mat-callout mat-callout-alerta"><strong>No está a paz y salvo</strong><p>Todavía debe ' + fmtPesos(m.por_pagar) + '. Registra sus pagos antes de graduarlo.</p></div>'
                    : !m.plan_pago ? '<div class="mat-callout mat-callout-alerta"><strong>No ha pagado el programa</strong><p>Nunca eligió su plan de pago, así que no tiene cuotas pagadas. No se puede graduar.</p></div>'
                    : m.cuotas_pagadas < m.cuotas_total ? '<div class="mat-callout mat-callout-alerta"><strong>No ha pagado todo el programa</strong><p>Lleva ' + m.cuotas_pagadas + ' de ' + m.cuotas_total + ' cuotas pagadas.</p></div>' : '');
        }
        if (estado === 'anulado') {
            var conPagos = m.recaudos.filter(function (r) { return !r.anulado; });
            extra.innerHTML = conPagos.length
                ? '<div class="mat-callout mat-callout-alerta"><strong>Tiene pagos registrados</strong><p>Recibos ' + conPagos.map(function (r) { return esc(r.recibo); }).join(', ') +
                    '. Si fueron un error, anúlalos primero en Pagos. Si el estudiante se va, usa Retirar.</p></div>'
                : '<div class="mat-callout mat-callout-aviso"><strong>Úsalo solo si la matrícula se hizo por error</strong><p>Libera el cupo, se anulan sus cobros y queda en Finalizadas como anulada.</p></div>';
        }
        if (estado === 'activo') {
            var opc = grupos.filter(function (x) { return x.programa_id === m.programa_id && abierto(x); });
            var preferido = opc.find(function (x) { return x.id === m.grupo_id && cuposLibres(x) > 0; });
            extra.innerHTML = '<div class="mat-field"><span class="mat-lbl">Grupo al que vuelve *</span><div class="mat-opciones" role="radiogroup" aria-label="Grupo">' +
                (opc.length ? opc.map(function (x) { return grupoOpcHTML(x, 'acc-grupo-re', preferido && x.id === preferido.id); }).join('')
                    : '<p class="mat-campo-ayuda">No hay grupos abiertos de este programa. Crea uno en Grupos.</p>') + '</div></div>';
        }
    }

    function errorAccion(msg, campoId) {
        accionError.textContent = msg;
        accionError.hidden = false;
        if (campoId) {
            var c = document.getElementById(campoId);
            if (c) { c.classList.add('is-invalid'); c.focus(); }
        }
        return false;
    }

    fichaFoot.addEventListener('click', function (e) { var b = e.target.closest('[data-accion]'); if (b && !b.disabled) mostrarAccion(b.dataset.accion); });
    fiPanel.addEventListener('click', function (e) {
        var b = e.target.closest('[data-anular]');
        if (b) mostrarAccion('anular', parseInt(b.dataset.anular));
        if (e.target.closest('[data-descuento]')) mostrarAccion('descuento');
        if (e.target.closest('[data-devolver]')) mostrarAccion('devolucion');
        var r = e.target.closest('[data-rematricular]');
        if (r) { cerrarFicha(); abrirModal(parseInt(r.dataset.rematricular)); }
        // Saltar a otra matrícula de la misma persona sin cerrar la ficha
        var ir = e.target.closest('[data-ir]');
        if (ir) { actual = matDe(parseInt(ir.dataset.ir)); tabActual = 'general'; mostrarFicha(); fichaEl.scrollTop = 0; }
    });
    document.getElementById('mat-accion-volver').addEventListener('click', mostrarFicha);
    document.getElementById('mat-accion-cancelar').addEventListener('click', mostrarFicha);
    accionEl.addEventListener('change', function (e) {
        accionError.hidden = true;
        if (e.target.id === 'acc-pct') pintarDescuento();
        if (e.target.name === 'acc-estado') pintarExtraEstado(e.target.value);
        if (e.target.name === 'acc-prog') {
            var lista = grupos.filter(function (g) { return g.programa_id === parseInt(e.target.value) && abierto(g); });
            var conCupo = lista.filter(function (g) { return cuposLibres(g) > 0; });
            document.getElementById('acc-prog-grupos').innerHTML = lista.length
                ? lista.map(function (g) { return grupoOpcHTML(g, 'acc-grupo-prog', conCupo.length === 1 && conCupo[0] === g); }).join('')
                : '<p class="mat-campo-ayuda">Este programa no tiene grupos abiertos.</p>';
        }
        if (e.target.name === 'acc-met') document.getElementById('acc-ref-opc').textContent = e.target.value === 'Efectivo' ? 'opcional en efectivo' : 'obligatoria';
    });
    accionEl.addEventListener('input', function (e) {
        e.target.classList.remove('is-invalid');
        accionError.hidden = true;
        // El valor se escribe con puntos de miles mientras se digita
        if (e.target.id === 'acc-valor') { var v = valorEscrito(); e.target.value = v ? fmtDoc(v) : ''; pintarReparto(); }
        if (e.target.id === 'acc-pct') pintarDescuento();
    });
    accionEl.addEventListener('click', function (e) {
        var b = e.target.closest('.mat-chip-btn[data-valor]');
        if (!b) return;
        document.getElementById('acc-valor').value = fmtDoc(b.dataset.valor);
        pintarReparto();
    });

    btnConfirmar.addEventListener('click', function () {
        var m = actual, peticion, tab = 'general', abrirTras = null;
        accionError.hidden = true;

        if (accionActual === 'pagar') {
            var valor = valorEscrito();
            if (!valor) return errorAccion('Escribe cuánto pagó.', 'acc-valor');
            if (valor > m.por_pagar) return errorAccion('El valor supera lo que debe en total (' + fmtPesos(m.por_pagar) + ').', 'acc-valor');
            var fecha = document.getElementById('acc-fecha').value;
            if (!fecha) return errorAccion('Indica la fecha del pago.', 'acc-fecha');
            if (fecha > HOY) return errorAccion('La fecha del pago no puede ser futura.', 'acc-fecha');
            var metodo = (accionCuerpo.querySelector('input[name="acc-met"]:checked') || {}).value;
            var ref = document.getElementById('acc-ref').value.trim();
            if (metodo !== 'Efectivo' && !ref) return errorAccion('Escribe la referencia o el número de la transacción.', 'acc-ref');
            peticion = enviar('POST', '/admin/matriculas/' + m.id + '/pagos', {
                valor: valor, fecha_pago: fecha, metodo_pago: metodo, referencia: ref, observacion: document.getElementById('acc-obs').value.trim()
            });
            tab = 'pagos';
        }
        if (accionActual === 'anular') {
            var motivo = document.getElementById('acc-motivo').value.trim();
            if (motivo.length < 5) return errorAccion('Escribe por qué se anula el pago.', 'acc-motivo');
            peticion = enviar('PATCH', '/admin/matriculas/' + m.id + '/recaudos/' + accionPago + '/anular', { motivo: motivo });
            tab = 'pagos';
        }
        if (accionActual === 'descuento') {
            var pctTxt = document.getElementById('acc-pct').value;
            var pct = parseFloat(pctTxt);
            if (pctTxt === '' || isNaN(pct)) return errorAccion('Escribe el porcentaje de descuento (0 para quitarlo).', 'acc-pct');
            if (pct < 0 || pct > 100) return errorAccion('El porcentaje debe estar entre 0 y 100.', 'acc-pct');
            var motivoD = document.getElementById('acc-desc-motivo').value.trim();
            if (pct > 0 && motivoD.length < 5) return errorAccion('Escribe el motivo o el convenio del descuento.', 'acc-desc-motivo');
            peticion = enviar('PATCH', '/admin/matriculas/' + m.id + '/descuento', { porcentaje: pct, motivo: motivoD });
            tab = 'pagos';
        }
        if (accionActual === 'grupo') {
            var g = (accionCuerpo.querySelector('input[name="acc-grupo"]:checked') || {}).value;
            if (!g) return errorAccion('Selecciona el grupo nuevo.');
            peticion = enviar('PATCH', '/admin/matriculas/' + m.id + '/grupo', { curso_id: parseInt(g) });
        }
        if (accionActual === 'devolucion') {
            var motivoDv = document.getElementById('acc-dev-motivo').value.trim();
            var cuerpoDv = { motivo: motivoDv, fecha: HOY, metodo_pago: 'Efectivo' };
            if (m.adelantado.resto > 0) {
                cuerpoDv.fecha = document.getElementById('acc-dev-fecha').value;
                if (!cuerpoDv.fecha) return errorAccion('Indica la fecha de la devolución.', 'acc-dev-fecha');
                if (cuerpoDv.fecha > HOY) return errorAccion('La fecha no puede ser futura.', 'acc-dev-fecha');
                cuerpoDv.metodo_pago = (accionCuerpo.querySelector('input[name="acc-met"]:checked') || {}).value;
                cuerpoDv.referencia = document.getElementById('acc-ref').value.trim();
                if (cuerpoDv.metodo_pago !== 'Efectivo' && !cuerpoDv.referencia) return errorAccion('Escribe la referencia o el número de la transacción.', 'acc-ref');
            }
            if (motivoDv.length < 5) return errorAccion('Escribe el motivo de la devolución.', 'acc-dev-motivo');
            peticion = enviar('POST', '/admin/matriculas/' + m.id + '/devoluciones', cuerpoDv);
            tab = 'pagos';
        }
        if (accionActual === 'estado' && (accionCuerpo.querySelector('input[name="acc-estado"]:checked') || {}).value === 'programa') {
            var grP = (accionCuerpo.querySelector('input[name="acc-grupo-prog"]:checked') || {}).value;
            if (!(accionCuerpo.querySelector('input[name="acc-prog"]:checked'))) return errorAccion('Selecciona el programa nuevo.');
            if (!grP) return errorAccion('Selecciona el grupo del programa nuevo.');
            var motivoP = document.getElementById('acc-motivo-estado').value.trim();
            if (motivoP.length < 5) return errorAccion('Escribe por qué cambia de programa.', 'acc-motivo-estado');
            peticion = enviar('PATCH', '/admin/matriculas/' + m.id + '/programa', { curso_id: parseInt(grP), motivo: motivoP });
            abrirTras = 'nueva';
        }
        else if (accionActual === 'estado') {
            var estado = (accionCuerpo.querySelector('input[name="acc-estado"]:checked') || {}).value;
            if (!estado) return errorAccion('Selecciona el nuevo estado.');
            var fechaE = document.getElementById('acc-fecha-estado').value;
            if (!fechaE) return errorAccion('Indica la fecha.', 'acc-fecha-estado');
            if (fechaE > HOY) return errorAccion('La fecha no puede ser futura.', 'acc-fecha-estado');
            if (fechaE < fechaMinEstado(m)) return errorAccion('La fecha no puede ser anterior al ' + fmtFecha(fechaMinEstado(m)) + ' (la matrícula o su último cambio de estado).', 'acc-fecha-estado');
            var motivoE = document.getElementById('acc-motivo-estado').value.trim();
            if (CON_MOTIVO.indexOf(estado) >= 0 && motivoE.length < 5) return errorAccion('Escribe el motivo.', 'acc-motivo-estado');
            var cuerpo = { estado: estado, fecha: fechaE, motivo: motivoE };
            if (estado === 'activo') {
                var gr = (accionCuerpo.querySelector('input[name="acc-grupo-re"]:checked') || {}).value;
                if (!gr) return errorAccion('Selecciona el grupo al que vuelve.');
                cuerpo.curso_id = parseInt(gr);
            }
            peticion = enviar('PATCH', '/admin/matriculas/' + m.id + '/estado', cuerpo);
        }
        if (!peticion) return;

        var texto = btnConfirmar.textContent;
        btnConfirmar.disabled = true;
        btnConfirmar.textContent = 'Guardando…';
        peticion
            .then(function (r) {
                // Cambio de programa: se abre la matrícula nueva
                recargarConAviso(r.mensaje, abrirTras === 'nueva'
                    ? { id: r.id, tab: 'general', nuevo: true }
                    : { id: m.id, tab: tab, recibo: r.recibo || null, comprobante: r.comprobante || null });
            })
            .catch(function (err) {
                btnConfirmar.disabled = false;
                btnConfirmar.textContent = texto;
                errorAccion(primerError(err).mensaje);
            });
    });

    /* ══ Asistente: Nueva matrícula ══ */
    var modal    = document.getElementById('mat-modal');
    var paso     = 1;
    var TOTAL    = 3;
    var nmError  = document.getElementById('nm-error');
    var btnSig   = document.getElementById('nm-siguiente');
    var btnAtras = document.getElementById('nm-atras');
    var cbEst    = document.getElementById('nm-cb-estudiante');
    var cbProg   = document.getElementById('nm-cb-programa');
    [cbEst, cbProg].forEach(initCombobox);
    pintarChips(document.getElementById('nm-metodos'), 'nm-met', METODOS, 'Efectivo');

    // Programas con al menos un grupo abierto en la sede
    var progConGrupos = programas.filter(function (p) { return grupos.some(function (g) { return g.programa_id === p.id && abierto(g); }); });
    cbProg.setOptions(progConGrupos.map(function (p) { return { value: String(p.id), label: corto(p.nombre) }; }));

    function opcionesEstudiante() {
        return sinVigente.slice().sort(function (a, b) { return a.nombres.localeCompare(b.nombres, 'es'); }).map(function (e) {
            var sinAcu = esMenor(e) && !e.acudiente;
            var nota = sinAcu ? ' · menor sin acudiente' : (e.documentos_pendientes.length ? ' · documentos pendientes' : '');
            return { value: String(e.id), label: nombreCompleto(e) + ' · ' + fmtDoc(e.documento) + nota };
        });
    }

    function abrirModal(estId) {
        paso = 1;
        nmError.hidden = true;
        cbEst.setOptions(opcionesEstudiante());
        cbEst.reset();
        cbProg.reset();
        document.getElementById('nm-est-card').hidden = true;
        document.getElementById('nm-grupos').innerHTML = '<p class="mat-campo-ayuda">Elige primero el programa.</p>';
        document.getElementById('nm-pagar').checked = false;
        document.getElementById('nm-pago').hidden = true;
        document.getElementById('nm-fecha').value = HOY;
        document.getElementById('nm-fecha').max = HOY;
        document.getElementById('nm-referencia').value = '';
        pintarChips(document.getElementById('nm-metodos'), 'nm-met', METODOS, 'Efectivo');
        if (estId) {
            var e = pendDe(estId);
            if (e) cbEst.setValue(String(e.id), nombreCompleto(e) + ' · ' + fmtDoc(e.documento));
        }
        pintarPaso();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { modal.querySelector(estId ? '#nm-siguiente' : '#nm-cb-estudiante [data-combobox-trigger]').focus(); }, 80);
    }
    function cerrarModal() {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.getElementById('mat-btn-nueva').focus();
    }
    document.getElementById('mat-btn-nueva').addEventListener('click', function () { abrirModal(null); });
    document.getElementById('mat-modal-close').addEventListener('click', cerrarModal);
    document.getElementById('mat-modal-overlay').addEventListener('click', cerrarModal);

    function pintarPaso() {
        modal.querySelectorAll('.mat-mstep').forEach(function (s) { s.classList.toggle('is-active', +s.dataset.step === paso); });
        modal.querySelectorAll('.mat-wstep').forEach(function (s) {
            var n = +s.dataset.step;
            s.classList.toggle('is-active', n === paso);
            s.classList.toggle('is-done', n < paso);
            if (n === paso) s.setAttribute('aria-current', 'step'); else s.removeAttribute('aria-current');
        });
        setTxt('mat-modal-paso', 'Paso ' + paso + ' de ' + TOTAL);
        btnAtras.style.visibility = paso === 1 ? 'hidden' : 'visible';
        btnSig.textContent = paso === TOTAL ? 'Matricular' : 'Siguiente';
        nmError.hidden = true;
        if (paso === 3) pintarResumen();
        modal.querySelector('.mat-modal-body').scrollTop = 0;
    }

    /* Paso 1: tarjeta del estudiante elegido */
    document.getElementById('nm-estudiante').addEventListener('change', function (ev) {
        var e = pendDe(parseInt(ev.target.value)), card = document.getElementById('nm-est-card');
        if (!e) { card.hidden = true; return; }
        var menor = esMenor(e), ed = edad(e.fecha_nac);
        var avisos = [];
        if (menor && !e.acudiente) avisos.push('<div class="mat-callout mat-callout-alerta"><strong>Es menor de edad y no tiene acudiente</strong><p>Vincúlale uno en Estudiantes antes de matricularlo.</p></div>');
        if (e.documentos_pendientes.length) avisos.push('<div class="mat-callout mat-callout-alerta"><strong>Documentos pendientes</strong><p>' + esc(e.documentos_pendientes.join(', ')) + '. Recíbelos en Estudiantes antes de matricularlo.</p></div>');
        card.innerHTML = '<div class="mat-est-top"><span class="mat-est-foto" aria-hidden="true">' + esc(iniciales(e)) + '</span><div>' +
                '<strong>' + esc(nombreCompleto(e)) + '</strong>' +
                '<span>' + esc(e.tipo_doc) + ' ' + fmtDoc(e.documento) + (ed !== null ? ' · ' + ed + ' años' : '') + '</span></div></div>' +
            '<div class="mat-info-grid">' +
                dato('Acudiente', e.acudiente ? esc(e.acudiente.nombre) + ' · ' + esc(e.acudiente.parentesco) : (menor ? 'Sin acudiente' : 'No tiene (mayor de edad)')) +
                dato('Matrícula anterior', e.anterior ? esc(ESTADOS[e.anterior.estado].label) + ' · ' + esc(corto(e.anterior.programa)) : 'Ninguna') +
            '</div>' + avisos.join('');
        card.hidden = false;
    });

    /* Paso 2: grupos del programa elegido */
    document.getElementById('nm-programa').addEventListener('change', function (ev) {
        var pid = parseInt(ev.target.value);
        var lista = grupos.filter(function (g) { return g.programa_id === pid && abierto(g); })
            .sort(function (a, b) { return a.codigo.localeCompare(b.codigo); });
        var conCupo = lista.filter(function (g) { return cuposLibres(g) > 0; });
        document.getElementById('nm-grupos').innerHTML = lista.length
            ? lista.map(function (g) { return grupoOpcHTML(g, 'nm-grupo', conCupo.length === 1 && conCupo[0] === g); }).join('')
            : '<p class="mat-campo-ayuda">Este programa no tiene grupos abiertos.</p>';
    });

    document.getElementById('nm-pagar').addEventListener('change', function (e) { document.getElementById('nm-pago').hidden = !e.target.checked; });
    document.getElementById('nm-metodos').addEventListener('change', function (e) {
        document.getElementById('nm-ref-opc').textContent = e.target.value === 'Efectivo' ? 'opcional en efectivo' : 'obligatoria';
    });

    function grupoElegido() {
        var v = (modal.querySelector('input[name="nm-grupo"]:checked') || {}).value;
        return v ? grupoDe(parseInt(v)) : null;
    }

    function pintarResumen() {
        var e = pendDe(parseInt(cbEst.value())), g = grupoElegido(), p = programaDe(g.programa_id);
        var vence = g.inicio > HOY ? g.inicio : HOY;
        document.getElementById('nm-resumen').innerHTML = '<h4>Resumen de la matrícula</h4><div class="mat-info-grid">' +
            dato('Estudiante', esc(nombreCompleto(e)) + ' · ' + fmtDoc(e.documento), true) +
            dato('Programa', esc(p.nombre), true) +
            dato('Grupo', esc(g.grupo) + ' · ' + esc(g.codigo)) +
            dato('Jornada', esc(g.jornada)) +
            dato('Inicio de clases', fmtFecha(g.inicio)) +
            dato('Duración', p.meses + ' meses · ' + p.periodos + ' ' + (p.tipo_periodo === 'semestre' ? 'semestres' : 'trimestres')) +
            dato('Derecho de matrícula', p.matricula
                ? fmtPesos(p.matricula) + ' · pago único, ' + (vence > HOY ? 'antes de iniciar clases (' + fmtFecha(vence) + ')' : 'hoy (las clases ya empezaron)')
                : 'No tiene', true) +
            dato('Valor del programa', fmtPesos(p.precio), true) +
            dato('Plan mensual', p.meses + ' cuotas de ' + fmtPesos(p.cuota_mensual)) +
            dato('Plan ' + planNombre('periodo', p.tipo_periodo).toLowerCase(), p.periodos + ' cuotas de ' + fmtPesos(p.cuota_periodo)) +
        '</div>';
        document.getElementById('nm-pagar-wrap').hidden = !p.matricula;
        setTxt('nm-pagar-txt', 'Ya pagó el derecho de matrícula (pago único de ' + fmtPesos(p.matricula) + ')');
        if (!p.matricula) { document.getElementById('nm-pagar').checked = false; document.getElementById('nm-pago').hidden = true; }
    }

    function errorNm(msg, campo) {
        nmError.textContent = msg;
        nmError.hidden = false;
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
            var e = pendDe(parseInt(cbEst.value()));
            if (!e) return errorNm('Selecciona el estudiante.', 'nm-cb-estudiante');
            if (esMenor(e) && !e.acudiente) return errorNm(nombreCompleto(e) + ' es menor de edad y no tiene acudiente. Vincúlale uno en Estudiantes antes de matricularlo.', 'nm-cb-estudiante');
            if (e.documentos_pendientes.length) return errorNm('A ' + nombreCompleto(e) + ' le faltan documentos: ' + e.documentos_pendientes.join(', ') + '. Recíbelos en Estudiantes antes de matricularlo.', 'nm-cb-estudiante');
        }
        if (paso === 2) {
            if (!cbProg.value()) return errorNm('Selecciona el programa.', 'nm-cb-programa');
            if (!grupoElegido()) return errorNm('Selecciona el grupo.');
        }
        if (paso === 3 && document.getElementById('nm-pagar').checked) {
            var f = document.getElementById('nm-fecha').value;
            if (!f) return errorNm('Indica la fecha del pago.', 'nm-fecha');
            if (f > HOY) return errorNm('La fecha del pago no puede ser futura.', 'nm-fecha');
            var met = (modal.querySelector('input[name="nm-met"]:checked') || {}).value;
            if (met !== 'Efectivo' && !document.getElementById('nm-referencia').value.trim()) return errorNm('Escribe la referencia o el número de la transacción.', 'nm-referencia');
        }
        return true;
    }

    var PASO_DE = { estudiante_id: 1, curso_id: 2 };

    btnSig.addEventListener('click', function () {
        nmError.hidden = true;
        if (!validarPaso()) return;
        if (paso < TOTAL) { paso++; pintarPaso(); return; }

        var pagar = document.getElementById('nm-pagar').checked;
        var cuerpo = { estudiante_id: parseInt(cbEst.value()), curso_id: grupoElegido().id, pagar_matricula: pagar };
        if (pagar) {
            cuerpo.fecha_pago = document.getElementById('nm-fecha').value;
            cuerpo.metodo_pago = (modal.querySelector('input[name="nm-met"]:checked') || {}).value;
            cuerpo.referencia = document.getElementById('nm-referencia').value.trim();
        }
        var textoBtn = btnSig.textContent;
        btnSig.disabled = true;
        btnSig.textContent = 'Matriculando…';
        enviar('POST', '/admin/matriculas', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje, { id: r.id, tab: 'general', nuevo: true }); })
            .catch(function (err) {
                btnSig.disabled = false;
                btnSig.textContent = textoBtn;
                var pe = primerError(err);
                if (pe.campo && PASO_DE[pe.campo] && PASO_DE[pe.campo] !== paso) { paso = PASO_DE[pe.campo]; pintarPaso(); }
                errorNm(pe.mensaje);
            });
    });
    btnAtras.addEventListener('click', function () { if (paso > 1) { paso--; pintarPaso(); } });
    modal.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); nmError.hidden = true; });
    modal.addEventListener('change', function (e) {
        var cb = e.target.closest('.app-combobox');
        if (cb) cb.classList.remove('is-invalid');
        nmError.hidden = true;
    });

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
    if (reabrir && reabrir.nuevo) nuevoId = reabrir.id;
    if (reabrir && reabrir.recibo) reciboNuevo = reabrir.recibo;
    if (reabrir && reabrir.comprobante) comprobanteNuevo = reabrir.comprobante;

    render();

    if (reabrir && reabrir.id) {
        abrirFicha(reabrir.id);
        tabActual = reabrir.tab || 'general';
        if (actual) mostrarFicha();
    }
}());
