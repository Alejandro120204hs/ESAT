/* ESAT — Admin: Contabilidad (Finanzas). Datos de ContabilidadService (CON_DATOS); registrar, editar y anular
   un egreso van a EgresoController (EgresoRequest / AnularEgresoRequest). Ingresos = lo recaudado en Pagos (solo
   consulta). Egresos = lo que registra la admin (nómina docentes, arriendo y servicios, materiales e insumos) más
   las devoluciones de Matrículas, que aparecen solas y no se editan. Saldo del mes = ingresos − egresos vigentes
   (lo anulado no cuenta). */
(function () {
    'use strict';

    var CFG = window.CON_CFG;
    var D = window.CON_DATOS;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var HOY = CFG.hoy;
    var MES_HOY = HOY.slice(0, 7);
    var VISTA_KEY = 'esat.admin.contabilidad.vista';
    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var MESES_L = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var CAT = { nomina: 'Nómina docentes', arriendo: 'Arriendo y servicios', materiales: 'Materiales e insumos', devolucion: 'Devoluciones' };
    var CATS_PROPIAS = ['nomina', 'arriendo', 'materiales'];
    var VINCULACION = { tiempo_completo: 'tiempo completo', medio_tiempo: 'medio tiempo', hora_catedra: 'hora cátedra' };
    var PLACEHOLDER = {
        arriendo: ['Ej. Inmobiliaria Los Andes S.A.S.', 'Ej. Energía eléctrica · octubre 2026'],
        materiales: ['Ej. Papelería El Estudiante', 'Ej. Guantes y tapabocas para el laboratorio de Salud']
    };

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function miles(v) { return String(Math.round(Math.abs(v || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function pesos(v) { return (v < 0 ? '− ' : '') + '$ ' + miles(v); }
    function corta(f) {
        if (!f) return '—';
        var p = f.split('-');
        return +p[2] + ' ' + MESES[+p[1] - 1] + (p[0] !== HOY.slice(0, 4) ? ' ' + p[0] : '');
    }
    function larga(f) { var p = f.split('-'); return +p[2] + ' de ' + MESES_L[+p[1] - 1] + ' de ' + p[0]; }
    function dos(n) { return (n < 10 ? '0' : '') + n; }
    function mesMenos(ym, n) { var y = +ym.slice(0, 4), m = +ym.slice(5, 7) - n; while (m <= 0) { m += 12; y--; } return y + '-' + dos(m); }
    function nombreMes(ym) { return MESES_L[+ym.slice(5, 7) - 1]; }
    function mesAnio(ym) { return nombreMes(ym) + ' ' + ym.slice(0, 4); }
    function mayus(t) { return t.charAt(0).toUpperCase() + t.slice(1); }
    function metodo(m) { return '<span class="con-metodo"><i class="m-' + esc(String(m).toLowerCase()) + '"></i>' + esc(m) + '</span>'; }
    function categoria(c) { return '<span class="con-cat"><i class="c-' + c + '"></i>' + esc(CAT[c]) + '</span>'; }
    function tag(clase, txt) { return '<span class="app-status-tag ' + clase + '">' + esc(txt) + '</span>'; }
    function pct(v, base) { return base ? Math.round(v * 100 / base) : 0; }
    var ICON = {
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        print: '<svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
        x: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        editar: '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        ojo: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        ir: '<svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
        nomina: '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        arriendo: '<svg viewBox="0 0 24 24"><path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>',
        materiales: '<svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        devolucion: '<svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>'
    };

    var toastEl = document.getElementById('con-toast');
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

    /* ── Servidor ── */
    function enviar(metodo, url, cuerpo) {
        return fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined
        }).catch(function () {
            throw new Error('No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.');
        }).then(function (r) {
            // Sesión cerrada o vencida: al recargar, el servidor lleva al login con el motivo
            if (r.status === 401 || r.status === 419) {
                location.reload();
                return new Promise(function () {});
            }
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) {
                    var msg = r.status === 422 ? (j.message || 'Revisa los datos.')
                        : r.status === 403 ? 'No tienes permiso para hacer esto (el egreso es de otra sede o tu usuario cambió de sede). Recarga la página.'
                        : r.status === 404 ? 'Ese egreso ya no existe. Recarga la página.'
                        : 'No se pudo guardar por un error del servidor. Intenta de nuevo.';
                    var err = new Error(msg);
                    err.errores = r.status === 422 ? (j.errors || null) : null;
                    throw err;
                }
                return j;
            });
        });
    }
    /* Deja el egreso que respondió el servidor en la lista (nuevo o reemplazando al anterior) */
    function guardarLocal(e) {
        var i = D.egresos.findIndex(function (k) { return k.id === e.id; });
        if (i === -1) D.egresos.push(e); else D.egresos[i] = e;
        return e;
    }
    var API = {
        guardarEgreso: function (x) {
            var cuerpo = {
                categoria: x.categoria, docente_id: x.docente_id, beneficiario: x.beneficiario, concepto: x.concepto, valor: x.valor,
                fecha: x.fecha, metodo_pago: x.metodo, referencia: x.referencia, factura: x.factura, observacion: x.observacion
            };
            return enviar(x.id ? 'PUT' : 'POST', CFG.egresosUrl + (x.id ? '/' + x.id : ''), cuerpo)
                .then(function (j) { guardarLocal(j.egreso); return j; });
        },
        anularEgreso: function (id, motivo) {
            return enviar('PATCH', CFG.egresosUrl + '/' + id + '/anular', { motivo: motivo })
                .then(function (j) { guardarLocal(j.egreso); return j; });
        }
    };

    /* ── Datos: egresos propios + devoluciones (como egresos de solo consulta) ── */
    var docPorId = {};
    D.docentes.forEach(function (d) { docPorId[d.id] = d; });
    function movimientos() {
        return D.egresos.map(function (e) { e.tipo = 'egreso'; return e; }).concat(D.devoluciones.map(function (d) {
            return {
                tipo: 'devolucion', id: 'd' + d.id, devId: d.id, comprobante: d.comprobante, fecha: d.fecha, categoria: 'devolucion',
                concepto: d.motivo, beneficiario: d.estudiante, documento: d.documento, programa: d.programa, valor: d.valor,
                metodo: d.metodo, referencia: d.referencia, factura: '', observacion: '', registrado: d.registrado, anulado: null
            };
        }));
    }
    function vigentes(lista) { return lista.filter(function (e) { return !e.anulado; }); }
    function suma(lista) { return lista.reduce(function (s, e) { return s + e.valor; }, 0); }
    function delMes(lista, ym) { return lista.filter(function (e) { return e.fecha.slice(0, 7) === ym; }); }
    function ingresoDe(ym) {
        var i = D.ingresos.find(function (x) { return x.mes === ym; });
        if (!i) return { mes: ym, derecho: 0, programa: 0, sistema: 0, pagos: 0, metodos: {}, total: 0 };
        i.total = i.derecho + i.programa + i.sistema;
        return i;
    }
    function balance(ym) {
        var eg = vigentes(delMes(movimientos(), ym)), ing = ingresoDe(ym);
        var porCat = {};
        Object.keys(CAT).forEach(function (c) { porCat[c] = { n: 0, valor: 0 }; });
        eg.forEach(function (e) { porCat[e.categoria].n++; porCat[e.categoria].valor += e.valor; });
        var egresos = suma(eg);
        return { mes: ym, ingresos: ing.total, egresos: egresos, saldo: ing.total - egresos, porCat: porCat, n: eg.length, ing: ing };
    }
    /* Meses con información: los últimos 12 (hasta el mes en curso) */
    var MESES_LISTA = [];
    for (var k = 0; k < 12; k++) MESES_LISTA.push(mesMenos(MES_HOY, k));

    /* ── Combobox (mismo patrón que Estudiantes: buscador opcional, abre hacia arriba si abajo no cabe, alScroll) ── */
    function initCombobox(root) {
        var trigger     = root.querySelector('[data-combobox-trigger]');
        var label       = root.querySelector('[data-combobox-label]');
        var panel       = root.querySelector('[data-combobox-panel]');
        var searchInput = root.querySelector('[data-combobox-search]');
        var list        = root.querySelector('[data-combobox-list]');
        var hidden      = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;
        var esAbsoluto  = root.dataset.position === 'absolute';

        function posicionar() {
            if (esAbsoluto) return;
            var rect   = trigger.getBoundingClientRect();
            var margen = 12;
            panel.style.left  = rect.left + 'px';
            panel.style.width = Math.max(rect.width, 220) + 'px';
            var abajo  = window.innerHeight - rect.bottom - 6 - margen;
            var arriba = rect.top - 6 - margen;
            list.style.maxHeight = '';
            var extra = panel.offsetHeight - list.offsetHeight;
            var tope  = 260;
            var necesario = Math.min(list.scrollHeight, tope) + extra;
            var haciaArriba = abajo < necesario && arriba > abajo;
            var espacio = haciaArriba ? arriba : abajo;
            panel.style.top    = haciaArriba ? '' : (rect.bottom + 6) + 'px';
            panel.style.bottom = haciaArriba ? (window.innerHeight - rect.top + 6) + 'px' : '';
            list.style.maxHeight = Math.max(Math.min(tope, espacio - extra), 40) + 'px';
        }
        /* Se recoloca cuando la página se desplaza, pero no con el scroll de su propia lista */
        function alScroll(e) { if (!panel.contains(e.target)) posicionar(); }
        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (searchInput) { searchInput.value = ''; filtrar(''); }
            posicionar();
            if (searchInput) searchInput.focus();
            if (!esAbsoluto) {
                window.addEventListener('scroll', alScroll, true);
                window.addEventListener('resize', posicionar);
            }
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
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });

        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (o) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = o.value;
                li.dataset.label = o.label;
                li.innerHTML = '<span>' + esc(o.label) + '</span><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>';
                li.addEventListener('click', function () { root.setValue(o.value, o.label); cerrar(); });
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
            label.textContent = etiqueta || placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === String(valor));
            });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        root.value = function () { return hidden.value; };
        root.etiqueta = function (valor) { var o = (root.opciones || []).find(function (x) { return x.value === valor; }); return o ? o.label : ''; };
        var setOpts = root.setOptions;
        root.setOptions = function (op) { root.opciones = op; setOpts(op); };
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

    /* ═══════════ KPIs del mes en curso ═══════════ */
    function pintarKpis() {
        var b = balance(MES_HOY), antes = balance(mesMenos(MES_HOY, 1));
        var mes = nombreMes(MES_HOY);
        document.getElementById('con-k-ingresos').textContent = pesos(b.ingresos);
        document.getElementById('con-k-ingresos-lbl').textContent = 'Ingresos de ' + mes + ' · ' + b.ing.pagos + (b.ing.pagos === 1 ? ' pago' : ' pagos');
        document.getElementById('con-k-egresos').textContent = pesos(b.egresos);
        document.getElementById('con-k-egresos-lbl').textContent = 'Egresos de ' + mes + ' · ' + b.n + (b.n === 1 ? ' comprobante' : ' comprobantes');
        var sEl = document.getElementById('con-k-saldo');
        sEl.textContent = pesos(b.saldo);
        sEl.classList.toggle('is-rojo', b.saldo < 0);
        document.getElementById('con-k-saldo-lbl').textContent = 'Saldo de ' + mes + ' · ' + nombreMes(antes.mes) + ' cerró en ' + pesos(antes.saldo);
        var nom = vigentes(delMes(D.egresos, MES_HOY)).filter(function (e) { return e.categoria === 'nomina'; });
        var personas = {};
        nom.forEach(function (e) { personas[e.docente_id] = true; });
        var nPer = Object.keys(personas).length;
        document.getElementById('con-k-nomina').textContent = pesos(suma(nom));
        document.getElementById('con-k-nomina-lbl').textContent = nPer
            ? 'Nómina docentes de ' + mes + ' · ' + nPer + ' de ' + D.docentes.filter(function (d) { return d.activo; }).length + ' docentes pagados'
            : 'Nómina docentes de ' + mes + ' · todavía sin registrar';
        document.getElementById('con-n-egresos').textContent = vigentes(delMes(movimientos(), MES_HOY)).length;
    }

    /* ═══════════ Resumen del mes ═══════════ */
    var mesSel = MES_HOY;
    var resumenEl = document.getElementById('con-resumen');
    function opcionesMes() {
        return MESES_LISTA.map(function (ym) { return { value: ym, label: ym === MES_HOY ? 'Este mes · ' + mesAnio(ym) : mayus(mesAnio(ym)) }; });
    }
    var cbMesResumen = filtroCombo('con-s-cb-mes', opcionesMes(), function () { elegirMes(valor('con-s-mes')); });
    var cbMesIngresos = filtroCombo('con-i-cb-mes', opcionesMes(), function () { elegirMes(valor('con-i-mes')); });
    /* Resumen e Ingresos muestran el mismo mes */
    function elegirMes(ym) {
        if (!ym) return;
        mesSel = ym;
        [cbMesResumen, cbMesIngresos].forEach(function (cb) { if (cb.value() !== ym) cb.setValue(ym, cb.etiqueta(ym), { silent: true }); });
        pintarResumen();
        pintarIngresos();
    }
    function dato(lbl, val, clase) { return '<div class="con-dato"><span class="con-dato-lbl">' + lbl + '</span><span class="con-dato-val' + (clase ? ' ' + clase : '') + '">' + val + '</span></div>'; }
    function datoTxt(lbl, txt) { return '<div class="con-dato"><span class="con-dato-lbl">' + lbl + '</span><span class="con-dato-txt">' + esc(txt || '—') + '</span></div>'; }

    function pintarResumen() {
        var b = balance(mesSel), a = balance(mesMenos(mesSel, 1));
        var dif = b.saldo - a.saldo;
        var enCurso = mesSel === MES_HOY;
        var html = '<div class="con-resumen"><div class="con-panel"><div class="con-panel-titulo">' + esc(mayus(mesAnio(mesSel))) + (enCurso ? ' · en curso, hasta hoy ' + corta(HOY) : '') + '</div><div class="con-datos">' +
            dato('Ingresos', pesos(b.ingresos), 'is-verde') +
            dato('Egresos', pesos(b.egresos) + ' <small>· ' + b.n + '</small>') +
            dato('Saldo del mes', pesos(b.saldo), b.saldo < 0 ? 'is-rojo' : '') +
            dato('Frente a ' + nombreMes(a.mes), (dif >= 0 ? '+ ' : '') + pesos(dif), dif < 0 ? 'is-rojo' : 'is-verde') + '</div></div>';
        if (enCurso && !b.porCat.nomina.n) {
            html += '<div class="con-nota">' + ICON.info + '<p>La nómina de ' + nombreMes(mesSel) + ' todavía no está registrada: el saldo del mes va a bajar cuando se les pague a los docentes.</p></div>';
        }
        html += '<div class="con-grid">' + panelGrafica() + panelCategorias(b) + '</div></div>';
        resumenEl.innerHTML = html;
    }
    function panelGrafica() {
        var meses = [];
        for (var i = 5; i >= 0; i--) meses.push(balance(mesMenos(mesSel, i)));
        var max = Math.max.apply(null, meses.map(function (m) { return Math.max(m.ingresos, m.egresos); }).concat([1]));
        var alto = function (v) { return Math.max(2, Math.round(v * 100 / max)); };
        return '<div class="con-panel"><div class="con-panel-titulo">Ingresos contra egresos · últimos 6 meses</div><div class="con-chart">' +
            '<div class="con-chart-leyenda"><span><i class="is-ingreso"></i>Ingresos</span><span><i class="is-egreso"></i>Egresos</span></div>' +
            '<div class="con-chart-barras">' + meses.map(function (m) {
                var sinDatos = MESES_LISTA.indexOf(m.mes) === -1;
                return '<button type="button" class="con-chart-col' + (m.mes === mesSel ? ' is-actual' : '') + '" data-mes="' + m.mes + '"' + (sinDatos ? ' disabled' : '') +
                    ' aria-label="' + esc(mayus(mesAnio(m.mes))) + ': ingresos ' + pesos(m.ingresos) + ', egresos ' + pesos(m.egresos) + ', saldo ' + pesos(m.saldo) + '">' +
                    '<span class="con-chart-par">' +
                        '<span class="con-chart-bar" style="height:' + alto(m.ingresos) + '%" data-val="Ingresos ' + pesos(m.ingresos) + '"></span>' +
                        '<span class="con-chart-bar is-egreso" style="height:' + alto(m.egresos) + '%" data-val="Egresos ' + pesos(m.egresos) + '"></span>' +
                    '</span>' +
                    '<span class="con-chart-mes">' + MESES[+m.mes.slice(5, 7) - 1] + '</span>' +
                    '<span class="con-chart-saldo' + (m.saldo < 0 ? ' is-rojo' : '') + '">' + (sinDatos ? '—' : (m.saldo >= 0 ? '+' : '−') + ' ' + millones(m.saldo)) + '</span>' +
                '</button>';
            }).join('') + '</div></div></div>';
    }
    function millones(v) {
        var a = Math.abs(v);
        return a >= 1000000 ? (Math.round(a / 100000) / 10).toString().replace('.', ',') + ' M' : Math.round(a / 1000) + ' mil';
    }
    function panelCategorias(b) {
        var filas = Object.keys(CAT).map(function (c) { return { c: c, n: b.porCat[c].n, valor: b.porCat[c].valor }; });
        return '<div class="con-panel"><div class="con-panel-titulo">Egresos por categoría</div>' +
            '<div class="con-table-wrap" style="border:none;border-radius:0"><table class="con-table"><thead><tr><th>Categoría</th><th class="num">Valor</th><th>Del total</th></tr></thead><tbody>' +
            filas.map(function (f) {
                var p = pct(f.valor, b.egresos);
                return '<tr class="con-row" data-cat="' + f.c + '" title="Ver estos egresos"><td>' + categoria(f.c) + '<div class="con-person-sub">' + f.n + (f.n === 1 ? ' comprobante' : ' comprobantes') + '</div></td>' +
                    '<td class="num"><span class="con-monto">' + pesos(f.valor) + '</span></td>' +
                    '<td><div class="con-meter-cell"><span class="con-meter is-egreso"><span style="width:' + p + '%"></span></span><span class="con-meter-pct">' + p + ' %</span></div></td></tr>';
            }).join('') + '</tbody><tfoot><tr><td>Total egresos</td><td class="num">' + pesos(b.egresos) + '</td><td></td></tr></tfoot></table></div></div>';
    }
    resumenEl.addEventListener('click', function (e) {
        var col = e.target.closest('[data-mes]');
        if (col && !col.disabled) return elegirMes(col.dataset.mes);
        var fila = e.target.closest('[data-cat]');
        if (fila) {
            cbCategoria.setValue(fila.dataset.cat, CAT[fila.dataset.cat], { silent: true });
            cbPeriodo.setValue(mesSel, cbPeriodo.etiqueta(mesSel), { silent: true });
            tEgresos.aplicar();
            mostrarVista('egresos');
        }
    });

    /* ═══════════ Egresos ═══════════ */
    var tbody = document.getElementById('con-e-body');
    var countEl = document.getElementById('con-e-count');
    var emptyEl = document.getElementById('con-e-empty');
    var pagEl = document.getElementById('con-e-pagination');
    var footEl = document.getElementById('con-e-foot');
    var tEgresos = (function () {
        var PAGE = 10, pagina = 1, items = [];
        function datos() {
            var q = (document.getElementById('con-e-search').value || '').toLowerCase().trim();
            var c = valor('con-e-categoria'), p = valor('con-e-periodo') || MES_HOY, me = valor('con-e-metodo');
            return movimientos().filter(function (e) {
                var enPeriodo = p === 'todo' || (p === 'anio' ? e.fecha.slice(0, 4) === HOY.slice(0, 4) : e.fecha.slice(0, 7) === p);
                return enPeriodo && (!c || e.categoria === c) && (!me || e.metodo === me) &&
                    (!q || [e.comprobante, e.concepto, e.beneficiario, e.factura, e.referencia].join(' ').toLowerCase().indexOf(q) !== -1);
            }).sort(function (a, b) { return b.fecha.localeCompare(a.fecha) || b.comprobante.localeCompare(a.comprobante); });
        }
        function aplicar(mantener) {
            items = datos();
            if (!mantener) pagina = 1;
            pagina = Math.min(pagina, Math.max(1, Math.ceil(items.length / PAGE)));
            pintar();
        }
        function fila(e) {
            var sub = e.tipo === 'devolucion' ? 'Devolución a ' + e.beneficiario + ' · ' + e.programa : e.beneficiario;
            return '<tr class="con-row" data-id="' + e.id + '">' +
                '<td><div class="con-comprobante">' + esc(e.comprobante) + '</div><div class="con-person-sub">' + corta(e.fecha) + '</div></td>' +
                '<td><div class="con-concepto"><div class="con-person-name" title="' + esc(e.concepto) + '">' + esc(e.concepto) + '</div><div class="con-person-sub">' + esc(sub) + '</div></div></td>' +
                '<td>' + categoria(e.categoria) + (e.tipo === 'devolucion' ? '<div class="con-person-sub">Desde Matrículas</div>' : '') + '</td>' +
                '<td>' + metodo(e.metodo) + (e.referencia ? '<div class="con-person-sub">' + esc(e.referencia) + '</div>' : '') + '</td>' +
                '<td class="num"><span class="con-monto' + (e.anulado ? ' is-tachado' : '') + '">' + pesos(e.valor) + '</span>' +
                    (e.anulado ? '<div>' + tag('app-status-danger', 'Anulado') + '</div>' : '') + '</td>' +
                '<td class="con-actions-cell"><button type="button" class="con-icon-btn" title="Ver egreso" aria-label="Ver egreso ' + esc(e.comprobante) + '">' + ICON.ojo + '</button></td></tr>';
        }
        function pintar() {
            var total = items.length, paginas = Math.max(1, Math.ceil(total / PAGE));
            var ini = (pagina - 1) * PAGE;
            tbody.innerHTML = items.slice(ini, ini + PAGE).map(fila).join('');
            countEl.textContent = total + (total === 1 ? ' egreso' : ' egresos');
            emptyEl.hidden = total > 0;
            var vig = vigentes(items), anul = total - vig.length;
            footEl.innerHTML = total ? '<tr><td colspan="4">Total de lo filtrado' + (anul ? ' · sin contar ' + anul + (anul === 1 ? ' anulado' : ' anulados') : '') + '</td><td class="num">' + pesos(suma(vig)) + '</td><td></td></tr>' : '';
            pagEl.hidden = paginas <= 1;
            if (paginas <= 1) { pagEl.innerHTML = ''; return; }
            var html = '<button type="button" class="con-pg-btn" data-pg="prev"' + (pagina === 1 ? ' disabled' : '') + '><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Anterior</button>';
            rango(pagina, paginas).forEach(function (p) {
                html += p === '…' ? '<span class="con-pg-ellipsis">…</span>'
                    : '<button type="button" class="con-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            });
            html += '<button type="button" class="con-pg-btn" data-pg="next"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg></button>';
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
            var paginas = Math.ceil(items.length / PAGE), v = b.dataset.pg;
            pagina = v === 'prev' ? Math.max(1, pagina - 1) : (v === 'next' ? Math.min(paginas, pagina + 1) : +v);
            pintar();
            tbody.closest('.con-table-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        tbody.addEventListener('click', function (e) {
            var row = e.target.closest('.con-row');
            if (row) abrirEgreso(row.dataset.id);
        });
        return { aplicar: aplicar, items: function () { return items; } };
    }());
    var cbCategoria = filtroCombo('con-e-cb-categoria', [{ value: '', label: 'Todas las categorías' }].concat(Object.keys(CAT).map(function (c) {
        return { value: c, label: CAT[c] };
    })), function () { tEgresos.aplicar(); });
    var cbPeriodo = filtroCombo('con-e-cb-periodo', MESES_LISTA.map(function (ym) {
        return { value: ym, label: ym === MES_HOY ? 'Este mes' : mayus(mesAnio(ym)) };
    }).concat([{ value: 'anio', label: 'Este año' }, { value: 'todo', label: 'Todas las fechas' }]), function () { tEgresos.aplicar(); });
    filtroCombo('con-e-cb-metodo', [{ value: '', label: 'Todos los medios' }].concat(D.metodos.map(function (x) { return { value: x, label: x }; })), function () { tEgresos.aplicar(); });
    document.getElementById('con-e-search').addEventListener('input', function () { tEgresos.aplicar(); });

    /* ═══════════ Ingresos (solo consulta) ═══════════ */
    var ingresosEl = document.getElementById('con-ingresos');
    function pintarIngresos() {
        var i = ingresoDe(mesSel);
        var html = '<div class="con-resumen"><div class="con-panel"><div class="con-panel-titulo">' + esc(mayus(mesAnio(mesSel))) + (mesSel === MES_HOY ? ' · hasta hoy ' + corta(HOY) : '') + '</div><div class="con-datos">' +
            dato('Total recibido', pesos(i.total), 'is-verde') + dato('Derecho de matrícula', pesos(i.derecho)) +
            dato('Cuotas del programa', pesos(i.programa)) + dato('Cuota del sistema', pesos(i.sistema)) + '</div></div>';
        if (!i.total) {
            ingresosEl.innerHTML = html + '<p class="con-panel-vacio">No se recibió ningún pago en este mes.</p></div>';
            return;
        }
        var conceptos = [['Cuotas del programa', i.programa, 'Lo que pagan por el programa en cada cuota (ya con descuentos y becas).'],
            ['Derecho de matrícula', i.derecho, 'Se paga una vez, antes de empezar clases.'],
            ['Cuota del sistema', i.sistema, 'Valor fijo de la plataforma que va en cada cuota.']];
        var metodos = Object.keys(i.metodos).map(function (m) { return { m: m, v: i.metodos[m] }; }).sort(function (a, b) { return b.v - a.v; });
        html += '<div class="con-grid is-mitad">' +
            '<div class="con-panel"><div class="con-panel-titulo">Por concepto</div><div class="con-table-wrap" style="border:none;border-radius:0"><table class="con-table"><thead><tr><th>Concepto</th><th class="num">Valor</th><th>Del total</th></tr></thead><tbody>' +
            conceptos.map(function (c) {
                var p = pct(c[1], i.total);
                return '<tr><td><div class="con-person-name">' + c[0] + '</div><div class="con-person-sub">' + c[2] + '</div></td><td class="num"><span class="con-monto">' + pesos(c[1]) + '</span></td>' +
                    '<td><div class="con-meter-cell"><span class="con-meter is-ingreso"><span style="width:' + p + '%"></span></span><span class="con-meter-pct">' + p + ' %</span></div></td></tr>';
            }).join('') + '</tbody><tfoot><tr><td>Total recibido</td><td class="num">' + pesos(i.total) + '</td><td></td></tr></tfoot></table></div></div>' +
            '<div class="con-panel"><div class="con-panel-titulo">Por medio de pago</div><div class="con-table-wrap" style="border:none;border-radius:0"><table class="con-table"><thead><tr><th>Medio</th><th class="num">Valor</th><th>Del total</th></tr></thead><tbody>' +
            metodos.map(function (x) {
                var p = pct(x.v, i.total);
                return '<tr><td>' + metodo(x.m) + '</td><td class="num"><span class="con-monto">' + pesos(x.v) + '</span></td>' +
                    '<td><div class="con-meter-cell"><span class="con-meter is-ingreso"><span style="width:' + p + '%"></span></span><span class="con-meter-pct">' + p + ' %</span></div></td></tr>';
            }).join('') + '</tbody></table></div></div></div></div>';
        ingresosEl.innerHTML = html;
    }

    function refrescar() {
        pintarKpis();
        pintarResumen();
        tEgresos.aplicar(true);
        pintarIngresos();
    }

    /* ── Pestañas de vista (se recuerda la última) ── */
    var vistaBtns = document.querySelectorAll('.con-vista-btn');
    function mostrarVista(v) {
        vistaBtns.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.con-vista').forEach(function (s) { s.hidden = s.dataset.panel !== v; });
        try { localStorage.setItem(VISTA_KEY, v); } catch (e) {}
    }
    vistaBtns.forEach(function (b) { b.addEventListener('click', function () { mostrarVista(b.dataset.vista); }); });

    /* ═══════════ Cajón: egreso / devolución ═══════════ */
    var drawer = document.getElementById('con-drawer');
    var dwBody = document.getElementById('con-dw-body');
    var dwAvatar = document.getElementById('con-dw-avatar');
    var ultimoFoco = null, abierto = null;

    function abrirCajon() {
        if (!drawer.classList.contains('is-open')) ultimoFoco = document.activeElement;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        dwBody.scrollTop = 0;
        setTimeout(function () { drawer.querySelector('.con-close-btn[data-cerrar]').focus({ preventScroll: true }); }, 30);
    }
    function cerrarCajon() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        abierto = null;
        if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus({ preventScroll: true });
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarCajon); });
    function aviso(clase, icono, texto) { return '<div class="con-aviso' + (clase ? ' ' + clase : '') + '"><div class="con-aviso-txt">' + icono + '<p>' + texto + '</p></div></div>'; }
    function buscar(id) { return movimientos().find(function (e) { return String(e.id) === String(id); }); }

    function abrirEgreso(id) {
        var e = buscar(id);
        if (!e) return;
        abierto = e.id;
        dwAvatar.innerHTML = ICON[e.categoria];
        dwAvatar.className = 'con-drawer-avatar c-' + e.categoria;
        document.getElementById('con-dw-titulo').textContent = (e.tipo === 'devolucion' ? 'Devolución ' : 'Egreso ') + e.comprobante;
        document.getElementById('con-dw-sub').textContent = CAT[e.categoria] + ' · ' + larga(e.fecha);
        document.getElementById('con-dw-badges').innerHTML = e.anulado ? tag('app-status-danger', 'Anulado')
            : (e.tipo === 'devolucion' ? tag('app-status-muted', 'Registrada en Matrículas') : tag('app-status-active', 'Vigente'));

        var html = '<div class="con-datos">' + dato('Valor', '<span class="' + (e.anulado ? 'con-monto is-tachado' : '') + '">' + pesos(e.valor) + '</span>') +
            dato('Fecha', corta(e.fecha)) + dato('Medio', esc(e.metodo)) + dato('Registró', esc(e.registrado)) + '</div>';
        if (e.anulado) {
            html += aviso('is-rojo', ICON.x, 'Anulado el ' + corta(e.anulado.fecha) + ' por ' + esc(e.anulado.por) + ': ' + esc(e.anulado.motivo) + ' No cuenta en el saldo del mes y su número no se vuelve a usar.');
        }
        if (e.tipo === 'devolucion') {
            html += '<div class="con-datos" style="grid-template-columns: repeat(2, minmax(0, 1fr))">' +
                datoTxt('Estudiante', e.beneficiario + ' · ' + e.documento) + datoTxt('Programa', e.programa) + '</div>' +
                '<div class="con-datos" style="grid-template-columns: minmax(0, 1fr) minmax(0, 2fr)">' + datoTxt('Referencia', e.referencia) + datoTxt('Motivo', e.concepto) + '</div>' +
                '<p class="con-ayuda">Las devoluciones se registran e imprimen desde Matrículas, en la ficha de la matrícula, porque el valor lo calcula el sistema con lo que el estudiante pagó por adelantado. Aquí se cuentan como egreso del mes.</p>' +
                '<div class="con-acciones-fila"><a class="con-btn con-btn-ghost con-btn-sm" href="' + esc(CFG.matriculasUrl) + '">' + ICON.ir + 'Ir a Matrículas</a></div>';
        } else {
            var doc = e.docente_id ? docPorId[e.docente_id] : null;
            html += '<div class="con-datos" style="grid-template-columns: repeat(2, minmax(0, 1fr))">' +
                datoTxt('Pagado a', e.beneficiario + (doc ? ' · CC ' + doc.documento : '')) +
                datoTxt(doc ? 'Vinculación' : 'Factura o soporte', doc ? 'Docente de ' + doc.escuela + ', ' + VINCULACION[doc.vinculacion] : e.factura) + '</div>' +
                '<div class="con-datos" style="grid-template-columns: repeat(2, minmax(0, 1fr))">' +
                datoTxt('Referencia', e.referencia) + (doc ? datoTxt('Factura o soporte', e.factura) : datoTxt('Categoría', CAT[e.categoria])) + '</div>' +
                '<div><div class="con-seccion-titulo">Concepto</div><p class="con-texto">' + esc(e.concepto) + '</p></div>' +
                (e.observacion ? '<div><div class="con-seccion-titulo">Observación</div><p class="con-texto">' + esc(e.observacion) + '</p></div>' : '') +
                '<div class="con-acciones-fila">' +
                    '<button type="button" class="con-btn con-btn-ghost con-btn-sm" id="con-dw-imprimir">' + ICON.print + 'Imprimir comprobante</button>' +
                    (e.anulado ? '' : '<button type="button" class="con-btn con-btn-ghost con-btn-sm" id="con-dw-editar">' + ICON.editar + 'Editar</button>' +
                        '<button type="button" class="con-btn con-btn-ghost con-btn-sm" id="con-dw-anular">' + ICON.x + 'Anular egreso</button>') +
                '</div>';
        }
        dwBody.innerHTML = html;
        abrirCajon();
        var bi = document.getElementById('con-dw-imprimir');
        if (bi) bi.addEventListener('click', function () { imprimirComprobante(e); });
        var be = document.getElementById('con-dw-editar');
        if (be) be.addEventListener('click', function () { abrirModalEgreso(e); });
        var ba = document.getElementById('con-dw-anular');
        if (ba) ba.addEventListener('click', function () { abrirModalAnular(e); });
    }

    /* ═══════════ Modal: registrar / editar / anular ═══════════ */
    var modal = document.getElementById('con-modal');
    var mdBody = document.getElementById('con-md-body');
    var mdError = document.getElementById('con-md-error');
    var mdOk = document.getElementById('con-md-ok');
    var mdAtras = document.getElementById('con-md-atras');
    var md = null, mdFoco = null;

    /* El formulario se arma una sola vez (el combobox de docentes registra sus listeners una vez) */
    var form = document.createElement('div');
    form.className = 'con-md-seccion';
    form.innerHTML =
        '<div class="con-field"><span class="con-lbl">Categoría *</span><div class="con-chips" id="con-f-cats" role="radiogroup" aria-label="Categoría">' +
            CATS_PROPIAS.map(function (c) { return '<label class="con-chip"><input type="radio" name="con-f-cat" value="' + c + '"><span>' + CAT[c] + '</span></label>'; }).join('') + '</div></div>' +
        '<div class="con-field" id="con-f-doc-wrap"><span class="con-lbl" id="con-f-doc-lbl">Docente *</span>' +
            '<div class="app-combobox" id="con-f-cb-docente">' +
                '<button type="button" class="app-combobox-trigger" data-combobox-trigger aria-labelledby="con-f-doc-lbl"><span data-combobox-label>Elige el docente</span><svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></button>' +
                '<div class="app-combobox-panel" data-combobox-panel hidden><div class="app-combobox-search-wrap"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                    '<input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar docente" autocomplete="off"></div><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>' +
                '<input type="hidden" id="con-f-docente" data-combobox-value></div></div>' +
        '<div class="con-field" id="con-f-ben-wrap"><label class="con-lbl" for="con-f-ben">Pagado a *</label><input class="con-input" id="con-f-ben" type="text" maxlength="120" autocomplete="off"></div>' +
        '<div class="con-field"><label class="con-lbl" for="con-f-concepto">Concepto *</label><input class="con-input" id="con-f-concepto" type="text" maxlength="160" autocomplete="off"></div>' +
        '<div class="con-form-row">' +
            '<div class="con-field"><label class="con-lbl" for="con-f-valor">Valor *</label><div class="con-valor-wrap"><span aria-hidden="true">$</span><input class="con-input" id="con-f-valor" type="text" inputmode="numeric" autocomplete="off"></div></div>' +
            '<div class="con-field"><label class="con-lbl" for="con-f-fecha">Fecha *</label><input class="con-input" id="con-f-fecha" type="date"></div>' +
        '</div>' +
        '<div class="con-field"><span class="con-lbl">Medio de pago *</span><div class="con-chips" id="con-f-metodos" role="radiogroup" aria-label="Medio de pago">' +
            D.metodos.map(function (x) { return '<label class="con-chip"><input type="radio" name="con-f-met" value="' + esc(x) + '"><span>' + esc(x) + '</span></label>'; }).join('') + '</div></div>' +
        '<div class="con-form-row">' +
            '<div class="con-field"><label class="con-lbl" for="con-f-ref">Referencia <em id="con-f-ref-opc">opcional en efectivo</em></label><input class="con-input" id="con-f-ref" type="text" maxlength="60" autocomplete="off" placeholder="N.º de transacción"></div>' +
            '<div class="con-field"><label class="con-lbl" for="con-f-factura">Factura o soporte <em>opcional</em></label><input class="con-input" id="con-f-factura" type="text" maxlength="60" autocomplete="off" placeholder="Ej. FV-1234 o cuenta de cobro"></div>' +
        '</div>' +
        '<div class="con-field"><label class="con-lbl" for="con-f-obs">Observación <em>opcional</em></label><textarea class="con-input con-textarea" id="con-f-obs" maxlength="255" placeholder="Algo que sirva para entender el gasto después"></textarea></div>';
    var f = function (id) { return form.querySelector('#' + id); };
    mdBody.appendChild(form);
    var cbDocente = f('con-f-cb-docente');
    initCombobox(cbDocente);
    /* Solo los docentes activos; al editar, también el del egreso aunque ya no esté activo */
    function opcionesDocente(actual) {
        cbDocente.setOptions(D.docentes.filter(function (d) { return d.activo || d.id === actual; }).map(function (d) {
            return { value: String(d.id), label: d.nombre + ' · ' + VINCULACION[d.vinculacion] + (d.activo ? '' : ' (inactivo)') };
        }));
    }
    opcionesDocente(null);
    var anularBox = document.createElement('div');
    anularBox.className = 'con-md-seccion';
    mdBody.appendChild(anularBox);

    function catElegida() { return (form.querySelector('input[name="con-f-cat"]:checked') || {}).value || ''; }
    function metElegido() { return (form.querySelector('input[name="con-f-met"]:checked') || {}).value || ''; }
    /* Concepto que se sugiere para la nómina (solo si la admin no escribió otro) */
    var conceptoSugerido = '';
    function sugerirConcepto() {
        var d = docPorId[+cbDocente.value()], cEl = f('con-f-concepto');
        if (catElegida() !== 'nomina' || !d) return;
        var mes = mesAnio((f('con-f-fecha').value || HOY).slice(0, 7));
        var nuevo = d.vinculacion === 'hora_catedra' ? 'Honorarios de ' + mes + ' · horas cátedra' : 'Nómina de ' + mes + ' · ' + VINCULACION[d.vinculacion];
        if (!cEl.value.trim() || cEl.value === conceptoSugerido) { cEl.value = nuevo; conceptoSugerido = nuevo; }
    }
    function porCategoria() {
        var c = catElegida(), esNom = c === 'nomina';
        f('con-f-doc-wrap').hidden = !esNom;
        f('con-f-ben-wrap').hidden = esNom;
        var ph = PLACEHOLDER[c] || ['', ''];
        f('con-f-ben').placeholder = ph[0];
        f('con-f-concepto').placeholder = esNom ? 'Ej. Nómina de octubre 2026 · tiempo completo' : (ph[1] || 'Elige primero la categoría');
        f('con-f-cats').classList.remove('is-invalid');
        sugerirConcepto();
    }
    function porMetodo() {
        f('con-f-ref-opc').textContent = metElegido() === 'Efectivo' || !metElegido() ? 'opcional en efectivo' : 'obligatoria';
        f('con-f-ref').classList.remove('is-invalid');
        f('con-f-metodos').classList.remove('is-invalid');
    }
    form.querySelectorAll('input[name="con-f-cat"]').forEach(function (el) { el.addEventListener('change', porCategoria); });
    form.querySelectorAll('input[name="con-f-met"]').forEach(function (el) { el.addEventListener('change', porMetodo); });
    f('con-f-docente').addEventListener('change', function () { cbDocente.classList.remove('is-invalid'); sugerirConcepto(); });
    f('con-f-fecha').addEventListener('change', sugerirConcepto);
    var vEl = f('con-f-valor');
    vEl.addEventListener('input', function () {
        var n = parseInt(vEl.value.replace(/\D/g, ''), 10) || 0;
        vEl.value = n ? miles(n) : '';
    });
    form.addEventListener('input', function (e) { if (e.target.classList) e.target.classList.remove('is-invalid'); });

    function abrirModal() {
        mdFoco = document.activeElement;
        mdOk.disabled = false; mdAtras.disabled = false; delete mdOk.dataset.texto;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        mdError.hidden = true;
    }
    function cerrarModal() {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        md = null;
        if (mdFoco && mdFoco.focus) mdFoco.focus({ preventScroll: true });
    }
    modal.querySelectorAll('[data-modal-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarModal); });
    mdAtras.addEventListener('click', cerrarModal);
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
        mdOk.disabled = si;
        mdAtras.disabled = si;
        if (si) { mdOk.dataset.texto = mdOk.textContent; mdOk.textContent = texto; }
        else if (mdOk.dataset.texto) mdOk.textContent = mdOk.dataset.texto;
    }

    /* Registrar (sin egreso) o editar (con egreso) */
    function abrirModalEgreso(e) {
        md = { tipo: 'egreso', id: e ? e.id : null };
        document.getElementById('con-md-titulo').textContent = e ? 'Editar egreso' : 'Registrar egreso';
        document.getElementById('con-md-paso').textContent = e ? e.comprobante + ' · el número no cambia' : 'El comprobante se numera solo (CE-' + HOY.slice(0, 4) + '-…)';
        form.hidden = false; anularBox.hidden = true;
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('input[name="con-f-cat"]').forEach(function (el) { el.checked = !!e && el.value === e.categoria; });
        form.querySelectorAll('input[name="con-f-met"]').forEach(function (el) { el.checked = e ? el.value === e.metodo : el.value === 'Transferencia'; });
        opcionesDocente(e ? e.docente_id : null);
        cbDocente.setValue(e && e.docente_id ? String(e.docente_id) : '', e && e.docente_id ? cbDocente.etiqueta(String(e.docente_id)) : '', { silent: true });
        f('con-f-ben').value = e && !e.docente_id ? e.beneficiario : '';
        f('con-f-concepto').value = e ? e.concepto : '';
        conceptoSugerido = '';
        vEl.value = e ? miles(e.valor) : '';
        var fEl = f('con-f-fecha');
        fEl.max = HOY;
        fEl.min = hace(365);
        fEl.value = e ? e.fecha : HOY;
        f('con-f-ref').value = e ? e.referencia : '';
        f('con-f-factura').value = e ? e.factura : '';
        f('con-f-obs').value = e ? e.observacion : '';
        porCategoria(); porMetodo();
        mdAtras.textContent = 'Cancelar';
        mdOk.className = 'con-btn con-btn-primary';
        mdOk.textContent = e ? 'Guardar cambios' : 'Registrar egreso';
        abrirModal();
        mdBody.scrollTop = 0;
        setTimeout(function () { (e ? vEl : form.querySelector('input[name="con-f-cat"]')).focus(); }, 30);
    }
    function hace(dias) { var d = new Date(HOY + 'T12:00:00'); d.setDate(d.getDate() - dias); return d.getFullYear() + '-' + dos(d.getMonth() + 1) + '-' + dos(d.getDate()); }

    function guardarEgreso() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        var c = catElegida(), met = metElegido(), v = parseInt(vEl.value.replace(/\D/g, ''), 10) || 0;
        var fecha = f('con-f-fecha').value, concepto = f('con-f-concepto').value.trim(), ben = f('con-f-ben').value.trim(), ref = f('con-f-ref').value.trim();
        if (!c) return error('Elige la categoría del egreso.', f('con-f-cats'));
        if (c === 'nomina' && !cbDocente.value()) return error('Elige el docente al que se le pagó.', cbDocente);
        if (c !== 'nomina' && ben.length < 3) return error('Escribe a quién se le pagó.', f('con-f-ben'));
        if (concepto.length < 5) return error('Escribe el concepto con un poco más de detalle.', f('con-f-concepto'));
        if (!v) return error('Escribe el valor que se pagó.', vEl);
        if (v > 100000000) return error('El valor no puede pasar de $ 100.000.000.', vEl);
        if (!fecha) return error('Escribe la fecha del egreso.', f('con-f-fecha'));
        if (fecha > HOY) return error('La fecha del egreso no puede ser futura.', f('con-f-fecha'));
        if (fecha < hace(365)) return error('La fecha no puede ser de hace más de un año.', f('con-f-fecha'));
        if (!met) return error('Elige el medio de pago.', f('con-f-metodos'));
        if (met !== 'Efectivo' && !ref) return error('Escribe la referencia: con ' + met + ' es obligatoria para poder verificar el pago.', f('con-f-ref'));

        ocupado(true, md.id ? 'Guardando…' : 'Registrando…');
        API.guardarEgreso({
            id: md.id, categoria: c, docente_id: c === 'nomina' ? +cbDocente.value() : null, beneficiario: ben, concepto: concepto,
            valor: v, fecha: fecha, metodo: met, referencia: ref, factura: f('con-f-factura').value, observacion: f('con-f-obs').value
        }).then(function (j) {
            ocupado(false);
            cerrarModal();
            refrescar();
            toast(j.mensaje);
            abrirEgreso(j.egreso.id);
        }).catch(function (err) {
            ocupado(false);
            var k = err.errores && Object.keys(err.errores)[0];
            var campos = { categoria: f('con-f-cats'), docente_id: cbDocente, beneficiario: f('con-f-ben'), concepto: f('con-f-concepto'), valor: vEl, fecha: f('con-f-fecha'), metodo_pago: f('con-f-metodos'), referencia: f('con-f-ref'), factura: f('con-f-factura'), observacion: f('con-f-obs') };
            error(k ? err.errores[k][0] : err.message, k ? campos[k] : null);
        });
    }

    function abrirModalAnular(e) {
        md = { tipo: 'anular', id: e.id };
        document.getElementById('con-md-titulo').textContent = 'Anular egreso';
        document.getElementById('con-md-paso').textContent = 'Comprobante ' + e.comprobante;
        form.hidden = true; anularBox.hidden = false;
        anularBox.innerHTML = aviso('is-rojo', ICON.alerta, '<strong>Se anulará el comprobante ' + esc(e.comprobante) + ' por ' + pesos(e.valor) + '.</strong> ' +
                esc(CAT[e.categoria]) + ' · ' + esc(e.concepto) + ', pagado a ' + esc(e.beneficiario) + ' el ' + corta(e.fecha) + '. ' +
                'Dejará de contar en el saldo del mes y quedará marcado como anulado (su número no se vuelve a usar). Úsalo solo si se registró por error.') +
            '<div class="con-field"><label class="con-lbl" for="con-f-motivo">Motivo *</label><textarea class="con-input con-textarea" id="con-f-motivo" maxlength="255" placeholder="Ej. Se registró dos veces la misma factura"></textarea></div>';
        var mEl = document.getElementById('con-f-motivo');
        mEl.addEventListener('input', function () { mEl.classList.remove('is-invalid'); });
        mdAtras.textContent = 'Cancelar';
        mdOk.className = 'con-btn con-btn-danger';
        mdOk.textContent = 'Anular egreso';
        abrirModal();
        setTimeout(function () { mEl.focus(); }, 30);
    }
    function guardarAnulacion() {
        var mEl = document.getElementById('con-f-motivo'), motivo = mEl.value.trim();
        if (motivo.length < 5) return error('Escribe el motivo con un poco más de detalle.', mEl);
        ocupado(true, 'Anulando…');
        var id = md.id;
        API.anularEgreso(id, motivo).then(function (j) {
            ocupado(false);
            cerrarModal();
            refrescar();
            toast(j.mensaje);
            abrirEgreso(id);
        }).catch(function (err) {
            ocupado(false);
            var k = err.errores && Object.keys(err.errores)[0];
            error(k ? err.errores[k][0] : err.message, mEl);
        });
    }
    mdOk.addEventListener('click', function () {
        if (!md) return;
        mdError.hidden = true;
        if (md.tipo === 'anular') guardarAnulacion(); else guardarEgreso();
    });
    mdBody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && !e.target.matches('[data-combobox-search]')) { e.preventDefault(); mdOk.click(); }
    });
    document.getElementById('con-btn-nuevo').addEventListener('click', function () { abrirModalEgreso(null); });

    /* ═══════════ Hoja imprimible: comprobante de egreso / resumen del mes ═══════════ */
    var hojaEl = document.getElementById('con-hoja-modal');
    document.body.appendChild(hojaEl); // hijo directo de <body>: al imprimir se oculta todo lo demás
    var hojaCuerpo = document.getElementById('con-hoja-cuerpo');
    var hojaFoco = null;
    function abrirHoja(tipo, numero, html) {
        document.getElementById('con-hoja-tipo').textContent = tipo;
        document.getElementById('con-hoja-numero').textContent = numero;
        hojaCuerpo.innerHTML = html;
        hojaFoco = document.activeElement;
        hojaEl.setAttribute('aria-hidden', 'false');
        hojaEl.scrollTop = 0;
        document.getElementById('con-hoja-imprimir').focus({ preventScroll: true });
    }
    function cerrarHoja() {
        if (hojaEl.getAttribute('aria-hidden') === 'true') return;
        hojaEl.setAttribute('aria-hidden', 'true');
        hojaCuerpo.innerHTML = '';
        if (hojaFoco && hojaFoco.focus) hojaFoco.focus({ preventScroll: true });
    }
    hojaEl.querySelectorAll('[data-hoja-cerrar]').forEach(function (b) { b.addEventListener('click', cerrarHoja); });
    document.getElementById('con-hoja-imprimir').addEventListener('click', function () { window.print(); });
    function filaHoja(lbl, val) { return '<tr><td class="con-hoja-lbl">' + lbl + '</td><td>' + val + '</td></tr>'; }

    function imprimirComprobante(e) {
        var doc = e.docente_id ? docPorId[e.docente_id] : null;
        abrirHoja('Comprobante de egreso', e.comprobante,
            (e.anulado ? '<div class="con-hoja-anulado" aria-hidden="true">ANULADO</div>' : '') +
            '<h1 class="con-hoja-titulo">Comprobante de egreso</h1>' +
            '<p>' + esc((CFG.ciudad ? CFG.ciudad + ', ' : '') + larga(e.fecha)) + '</p>' +
            '<table class="con-hoja-tabla"><tbody>' +
                filaHoja('Pagado a', esc(e.beneficiario) + (doc ? ' · CC ' + esc(doc.documento) : '')) +
                filaHoja('Concepto', esc(e.concepto)) +
                filaHoja('Categoría', esc(CAT[e.categoria])) +
                filaHoja('Medio de pago', esc(e.metodo) + (e.referencia ? ' · Ref. ' + esc(e.referencia) : '')) +
                (e.factura ? filaHoja('Factura o soporte', esc(e.factura)) : '') +
                (e.observacion ? filaHoja('Observación', esc(e.observacion)) : '') +
            '</tbody><tfoot><tr><td>Valor pagado</td><td class="num">' + pesos(e.valor) + '</td></tr></tfoot></table>' +
            (e.anulado ? '<p class="con-hoja-nota"><strong>Comprobante anulado</strong> el ' + esc(larga(e.anulado.fecha)) + ' por ' + esc(e.anulado.por) + ': ' + esc(e.anulado.motivo) + '</p>' : '') +
            '<div class="con-hoja-firmas"><div class="con-hoja-firma">Elaboró<span>' + esc(e.registrado) + '</span></div>' +
                '<div class="con-hoja-firma">Recibí conforme<span>' + esc(e.beneficiario) + '</span></div></div>');
    }
    document.getElementById('con-resumen-imprimir').addEventListener('click', function () {
        var b = balance(mesSel), i = b.ing;
        var tabla = function (cols, filas, pie) {
            return '<table class="con-hoja-tabla"><thead><tr>' + cols.map(function (x, k) { return '<th' + (k ? ' class="num"' : '') + '>' + x + '</th>'; }).join('') + '</tr></thead><tbody>' +
                filas.join('') + '</tbody>' + (pie ? '<tfoot><tr>' + pie.map(function (x, k) { return '<td' + (k ? ' class="num"' : '') + '>' + x + '</td>'; }).join('') + '</tr></tfoot>' : '') + '</table>';
        };
        abrirHoja('Resumen contable', (CFG.ciudad ? CFG.ciudad + ', ' : '') + larga(HOY),
            '<h1 class="con-hoja-titulo">Resumen contable de ' + esc(mesAnio(mesSel)) + '</h1>' +
            '<p>' + (mesSel === MES_HOY ? 'Mes en curso, con lo registrado hasta hoy. ' : '') + 'Ingresos recibidos de los estudiantes y egresos de la sede, sin contar lo anulado.</p>' +
            '<h2 class="con-hoja-sub">Ingresos</h2>' + tabla(['Concepto', 'Valor'], [
                '<tr><td>Cuotas del programa</td><td class="num">' + pesos(i.programa) + '</td></tr>',
                '<tr><td>Derecho de matrícula</td><td class="num">' + pesos(i.derecho) + '</td></tr>',
                '<tr><td>Cuota del sistema</td><td class="num">' + pesos(i.sistema) + '</td></tr>'
            ], ['Total ingresos', pesos(b.ingresos)]) +
            '<h2 class="con-hoja-sub">Egresos</h2>' + tabla(['Categoría', 'Comprobantes', 'Valor'], Object.keys(CAT).map(function (c) {
                return '<tr><td>' + CAT[c] + '</td><td class="num">' + b.porCat[c].n + '</td><td class="num">' + pesos(b.porCat[c].valor) + '</td></tr>';
            }), ['Total egresos', b.n, pesos(b.egresos)]) +
            '<table class="con-hoja-tabla"><tbody><tr><td>Total ingresos</td><td class="num">' + pesos(b.ingresos) + '</td></tr>' +
                '<tr><td>Total egresos</td><td class="num">− ' + pesos(b.egresos) + '</td></tr></tbody>' +
                '<tfoot><tr><td>Saldo del mes</td><td class="num">' + pesos(b.saldo) + '</td></tr></tfoot></table>' +
            '<div class="con-hoja-firmas"><div class="con-hoja-firma">Elaboró<span>' + esc(CFG.admin || 'Administrador') + '</span></div></div>');
    });

    /* Escape: se revisa antes que los combobox (fase de captura) para que, si hay una lista abierta,
       solo se cierre la lista y no el modal o el cajón */
    window.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || document.querySelector('.app-combobox.is-open')) return;
        if (hojaEl.getAttribute('aria-hidden') === 'false') cerrarHoja();
        else if (modal.classList.contains('is-open')) cerrarModal();
        else cerrarCajon();
    }, true);

    /* ═══════════ Exportar lo que se ve con los filtros (CSV con ; y BOM para las tildes) ═══════════ */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    document.getElementById('con-e-exportar').addEventListener('click', function () {
        var filas = [['Comprobante', 'Fecha', 'Categoría', 'Concepto', 'Pagado a', 'Medio de pago', 'Referencia', 'Factura o soporte', 'Valor', 'Registró', 'Estado', 'Motivo de anulación']]
            .concat(tEgresos.items().map(function (e) {
                return [e.comprobante, e.fecha, CAT[e.categoria], e.concepto, e.beneficiario, e.metodo, e.referencia || '', e.factura || '', e.valor, e.registrado,
                    e.anulado ? 'Anulado' : (e.tipo === 'devolucion' ? 'Devolución (Matrículas)' : 'Vigente'), e.anulado ? e.anulado.motivo : ''];
            }));
        var csv = '﻿' + filas.map(function (r) { return r.map(celdaCsv).join(';'); }).join('\r\n');
        var nombreArchivo = 'contabilidad-egresos-' + HOY + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a');
        a.href = url; a.download = nombreArchivo;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        var n = filas.length - 1;
        toast('Se descargó ' + nombreArchivo + ' con ' + n + (n === 1 ? ' fila.' : ' filas.'));
    });

    /* Inicializar */
    refrescar();
    try {
        var guardada = localStorage.getItem(VISTA_KEY);
        if (guardada && document.querySelector('.con-vista[data-panel="' + guardada + '"]')) mostrarVista(guardada);
    } catch (e) {}
}());
