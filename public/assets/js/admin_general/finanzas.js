/* =========================================================
   ESAT — Panel Admin (ESAT general): Finanzas
   Consulta de la plata de todo ESAT o de una sede: Recaudo (por mes: KPI, por sede, por medio, recibos con
   detalle imprimible), Cartera (a hoy, por edades, con recordatorio por WhatsApp), Contabilidad (ingresos −
   egresos) y Devoluciones. Exporta a Excel. Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 12;
    var ICON = {
        plata: '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        recibo: '<svg viewBox="0 0 24 24"><path d="M4 2v20l3-2 3 2 2-2 2 2 3-2 3 2V2l-3 2-3-2-2 2-2-2-3 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="14" y2="13"/></svg>',
        anular: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        persona: '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        wa: '<svg viewBox="0 0 24 24"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.4A8.4 8.4 0 1 1 21 11.5z"/></svg>'
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

    var toastEl = $('fin-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="fin-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="fin-pg-gap">…</span>'
                    : '<button type="button" class="fin-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="fin-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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

    function plata(n) { return '$ ' + num(n); }
    var MES_C = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
    function fechaMini(f) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f); return d.getDate() + ' ' + MES_C[d.getMonth()] + ' ' + d.getFullYear(); }
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
    function digitos(t) { return String(t || '').replace(/\D/g, ''); }

    /* ── Estado de la pantalla ──────────────────── */
    var HOY = new Date(D.hoy), DIA = 86400000;
    var SEDE_KEY = 'esat.general.finanzas.sede', TAB_KEY = 'esat.general.finanzas.tab';
    var sel = '', mes = 5, ptab = 'recaudo';
    try { sel = localStorage.getItem(SEDE_KEY) || ''; ptab = localStorage.getItem(TAB_KEY) || 'recaudo'; } catch (e) {}
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    if (sel && !sedePorId(sel)) sel = '';
    function colorDe(s) { return s ? 'fin-c' + ((D.sedes.indexOf(s) % 8) + 1) : 'fin-c0'; }
    function enSede(x) { return !sel || String(x.sede_id) === sel; }
    function sedesVisibles() { return sel ? [sedePorId(sel)] : D.sedes; }
    function estudiante(id) { return D.estudiantes[id - 1]; }
    function nombreEst(e) { return e ? e.nombres + ' ' + e.apellidos : ''; }
    function inicioMes(i) { return new Date(HOY.getFullYear(), HOY.getMonth() - (5 - i), 1); }
    function enMes(f, i) { var d = new Date(f), a = inicioMes(i); return d.getFullYear() === a.getFullYear() && d.getMonth() === a.getMonth(); }
    function nombreMes(i) { var a = inicioMes(i); return MESES[a.getMonth()] + ' de ' + a.getFullYear(); }
    function sum(lista, k) { return lista.reduce(function (t, x) { return t + (k ? x[k] : x); }, 0); }
    function kpis(id, lista) {
        $(id).innerHTML = lista.map(function (x) {
            return '<div class="fin-kpi fin-kpi-' + x[0] + '"><div class="fin-kpi-icon">' + ICON[x[1]] + '</div><div><div class="fin-kpi-val">' + esc(x[2]) + '</div><div class="fin-kpi-lbl">' + esc(x[3]) + '</div></div></div>';
        }).join('');
    }
    function barras(id, filas, max) {
        $(id).innerHTML = filas.length ? filas.map(function (f) {
            var pct = max ? Math.round(f.valor / max * 100) : 0;
            return '<li><span class="fin-barras-nombre">' + (f.color ? '<span class="fin-dot ' + f.color + '"></span>' : '') + esc(f.nombre) + '</span>' +
                '<span class="fin-barras-bg"><span class="' + (f.color || '') + '" style="width:' + pct + '%"></span></span>' +
                '<span class="fin-barras-val">' + esc(plata(f.valor)) + (f.sub ? '<small>' + esc(f.sub) + '</small>' : '') + '</span></li>';
        }).join('') : '<li class="fin-muted">Sin movimientos en el mes elegido.</li>';
    }

    /* ── Filtros superiores ─────────────────────── */
    var chips = $('fin-chips');
    D.sedes.forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'fin-chip'; b.setAttribute('role', 'radio'); b.dataset.sede = s.id;
        b.innerHTML = '<span class="fin-dot ' + colorDe(s) + '" aria-hidden="true"></span>' + esc(s.nombre);
        chips.appendChild(b);
    });
    chips.addEventListener('click', function (e) {
        var b = e.target.closest('.fin-chip'); if (!b) return;
        sel = b.dataset.sede || ''; try { localStorage.setItem(SEDE_KEY, sel); } catch (err) {}
        pintar();
    });
    var cbMes = $('fin-cb-mes'); initCombobox(cbMes);
    cbMes.setOptions([5, 4, 3, 2, 1, 0].map(function (i) { var n = nombreMes(i); return { value: String(i), label: n.charAt(0).toUpperCase() + n.slice(1) + (i === 5 ? ' (en curso)' : '') }; }));
    function etiquetaMes() { var n = nombreMes(mes); return n.charAt(0).toUpperCase() + n.slice(1) + (mes === 5 ? ' (en curso)' : ''); }
    cbMes.setValue('5', etiquetaMes(), { silent: true });
    cbMes.querySelector('[data-combobox-value]').addEventListener('change', function () { mes = +cbMes.value(); pintar(); });

    document.querySelectorAll('.fin-ptab').forEach(function (b) {
        b.addEventListener('click', function () { ptab = b.dataset.ptab; try { localStorage.setItem(TAB_KEY, ptab); } catch (e) {} mostrarTab(); });
    });
    function mostrarTab() {
        document.querySelectorAll('.fin-ptab').forEach(function (b) { var on = b.dataset.ptab === ptab; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        document.querySelectorAll('.fin-ppanel').forEach(function (p) { p.hidden = p.dataset.ppanel !== ptab; });
        cbMes.hidden = ptab === 'cartera' || ptab === 'devoluciones';
    }

    /* ── Recaudo ────────────────────────────────── */
    var cbMedio = $('fin-cb-medio'); initCombobox(cbMedio);
    var MEDIOS = ['Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta'];
    cbMedio.setOptions([{ value: '', label: 'Todos los medios' }].concat(MEDIOS).concat([{ value: 'anulados', label: 'Solo anulados' }]));
    cbMedio.setValue('', 'Todos los medios', { silent: true });
    var recPagina = 1, recFiltrados = [];
    function pintarRecaudo() {
        var delMes = D.recaudos.filter(function (r) { return enSede(r) && enMes(r.fecha, mes); });
        var validos = delMes.filter(function (r) { return !r.anulado; }), anulados = delMes.filter(function (r) { return r.anulado; });
        var total = sum(validos, 'valor');
        kpis('fin-rec-kpis', [['green', 'plata', plata(total), 'Recaudado en ' + MESES[inicioMes(mes).getMonth()]],
            ['blue', 'recibo', num(validos.length), validos.length === 1 ? 'Recibo' : 'Recibos'],
            ['blue', 'plata', plata(validos.length ? total / validos.length : 0), 'Promedio por recibo'],
            [anulados.length ? 'red' : 'green', 'anular', num(anulados.length), anulados.length === 1 ? 'Recibo anulado' : 'Recibos anulados']]);
        $('fin-rec-sede-sub').textContent = 'Lo recaudado en ' + nombreMes(mes);
        var porSede = sedesVisibles().map(function (s) {
            var rs = validos.filter(function (r) { return r.sede_id === s.id; });
            return { nombre: s.nombre, valor: sum(rs, 'valor'), color: colorDe(s), sub: rs.length + (rs.length === 1 ? ' recibo' : ' recibos') };
        });
        barras('fin-rec-sedes', porSede, Math.max.apply(null, porSede.map(function (x) { return x.valor; }).concat([0])));
        var porMedio = MEDIOS.map(function (m) {
            var rs = validos.filter(function (r) { return r.medio === m; });
            return { nombre: m, valor: sum(rs, 'valor'), sub: total ? Math.round(sum(rs, 'valor') / total * 100) + ' %' : '' };
        }).filter(function (x) { return x.valor; }).sort(function (a, b) { return b.valor - a.valor; });
        barras('fin-rec-medios', porMedio, porMedio.length ? porMedio[0].valor : 0);

        var q = normal($('fin-rec-buscar').value.trim()), medio = cbMedio.value();
        recFiltrados = delMes.filter(function (r) {
            if (medio === 'anulados' && !r.anulado) return false;
            if (medio && medio !== 'anulados' && r.medio !== medio) return false;
            return !q || normal(r.numero + ' ' + nombreEst(estudiante(r.est_id)) + ' ' + r.referencia).indexOf(q) !== -1;
        }).sort(function (a, b) { return new Date(b.fecha) - new Date(a.fecha); });
        recPagina = 1; pintarRecibos();
    }
    function pintarRecibos() {
        var desde = (recPagina - 1) * POR_PAGINA;
        $('fin-rec-count').textContent = num(recFiltrados.length) + (recFiltrados.length === 1 ? ' recibo' : ' recibos');
        $('fin-rec-empty').hidden = recFiltrados.length > 0;
        $('fin-rec-body').innerHTML = recFiltrados.slice(desde, desde + POR_PAGINA).map(function (r) {
            var s = sedePorId(r.sede_id), e = estudiante(r.est_id);
            return '<tr class="fin-row' + (r.anulado ? ' fin-anulado' : '') + '" tabindex="0" data-recibo="' + r.id + '">' +
                '<td><div class="fin-strong">' + esc(r.numero) + '</div><div class="fin-muted">' + esc(fechaMini(r.fecha)) + '</div></td>' +
                '<td>' + esc(nombreEst(e)) + '</td><td>' + esc(s.nombre) + '</td>' +
                '<td>' + esc(r.concepto) + (r.anulado ? '<div><span class="app-status-tag app-status-danger">Anulado</span></div>' : '') + '</td>' +
                '<td>' + esc(r.medio) + (r.referencia ? '<div class="fin-muted">Ref. ' + esc(r.referencia) + '</div>' : '') + '</td>' +
                '<td>' + esc(r.quien.nombre) + (r.quien.rol === 'admin' ? '<div class="fin-muted">Admin</div>' : '') + '</td>' +
                '<td class="fin-num"><span class="fin-strong">' + esc(plata(r.valor)) + '</span></td></tr>';
        }).join('');
        paginar($('fin-rec-paginacion'), recFiltrados.length, recPagina, function (p) { recPagina = p; pintarRecibos(); });
    }
    $('fin-rec-buscar').addEventListener('input', pintarRecaudo);
    cbMedio.querySelector('[data-combobox-value]').addEventListener('change', pintarRecaudo);

    /* Recibo */
    var modal = $('fin-recibo-modal'), volver = null;
    function abrirRecibo(id) {
        var r = D.recaudos.find(function (x) { return x.id === id; }); if (!r) return;
        var s = sedePorId(r.sede_id), e = estudiante(r.est_id);
        volver = document.activeElement;
        $('fin-rc-titulo').textContent = 'Recibo de caja ' + r.numero;
        var filas = [['Fecha', fechaLarga(r.fecha) + ', ' + new Date(r.fecha).toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' })], ['Sede', s.nombre + ' · ' + s.direccion],
            ['Estudiante', nombreEst(e)], ['Documento', e.tipo_documento + ' ' + e.numero_documento], ['Programa', e.programa || '—'], ['Concepto', r.concepto],
            ['Medio de pago', r.medio + (r.referencia ? ' · Ref. ' + r.referencia : '')], ['Registró', r.quien.nombre + (r.quien.rol === 'admin' ? ' (Admin)' : '')]];
        $('fin-rc-cuerpo').innerHTML = '<p class="fin-sello"' + (r.anulado ? '' : ' hidden') + '>Recibo anulado: ' + esc(r.motivo || '') + '</p>' +
            filas.map(function (x) { return '<div><span class="fin-info-lbl">' + esc(x[0]) + '</span><span class="fin-info-val">' + esc(x[1]) + '</span></div>'; }).join('') +
            '<div class="fin-recibo-total"><span class="fin-info-lbl">Valor recibido</span><strong>' + esc(plata(r.valor)) + '</strong></div>';
        modal.hidden = false; $('fin-rc-imprimir').focus();
    }
    function cerrarRecibo() { if (modal.hidden) return; modal.hidden = true; if (volver && document.contains(volver)) volver.focus(); }
    modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrarRecibo); });
    $('fin-rc-imprimir').addEventListener('click', function () {
        document.body.classList.add('fin-imprimiendo'); window.print(); document.body.classList.remove('fin-imprimiendo');
    });
    $('fin-rec-body').addEventListener('click', function (e) { var tr = e.target.closest('[data-recibo]'); if (tr) abrirRecibo(+tr.dataset.recibo); });
    $('fin-rec-body').addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-recibo]')) { e.preventDefault(); abrirRecibo(+e.target.dataset.recibo); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !document.querySelector('.app-combobox.is-open')) cerrarRecibo(); });

    /* ── Cartera (a hoy) ────────────────────────── */
    var EDADES = [[1, 30], [31, 60], [61, 90], [91, Infinity]];
    function diasVencida(f) { return Math.max(1, Math.floor((HOY - new Date(f + 'T12:00:00')) / DIA)); }
    function edadesDe(lista) {
        var r = [0, 0, 0, 0];
        lista.forEach(function (e) { e.cuotas_vencidas.forEach(function (c) { var d = diasVencida(c.vence); EDADES.forEach(function (x, i) { if (d >= x[0] && d <= x[1]) r[i] += c.valor; }); }); });
        return r;
    }
    var carPagina = 1, carFiltrados = [];
    function enMora(lista) { return lista.filter(function (e) { return e.estado === 'activo' && e.pago === 'vencido'; }); }
    function pintarCartera() {
        var mora = enMora(D.estudiantes.filter(enSede));
        var activos = D.estudiantes.filter(function (e) { return enSede(e) && e.estado === 'activo'; }).length;
        var ed = edadesDe(mora), total = sum(ed);
        kpis('fin-car-kpis', [['red', 'alerta', plata(total), 'Cartera vencida'], ['orange', 'persona', num(mora.length), 'Estudiantes en mora'],
            ['blue', 'persona', (activos ? Math.round(mora.length / activos * 100) : 0) + ' %', 'De los estudiantes activos'],
            [ed[3] ? 'red' : 'green', 'reloj', plata(ed[3]), 'Vencido hace más de 90 días']]);
        var filas = sedesVisibles().map(function (s) {
            var e = edadesDe(enMora(D.estudiantes.filter(function (x) { return x.sede_id === s.id; })));
            return '<tr><td><span class="fin-dot ' + colorDe(s) + '" style="display:inline-block;margin-right:8px"></span>' + esc(s.nombre) + '</td>' +
                e.map(function (v) { return '<td class="fin-num">' + esc(plata(v)) + '</td>'; }).join('') + '<td class="fin-num"><span class="fin-strong">' + esc(plata(sum(e))) + '</span></td></tr>';
        });
        if (!sel) filas.push('<tr class="fin-total"><td>Total ESAT</td>' + ed.map(function (v) { return '<td class="fin-num">' + esc(plata(v)) + '</td>'; }).join('') + '<td class="fin-num">' + esc(plata(total)) + '</td></tr>');
        $('fin-car-edades').innerHTML = filas.join('');

        var q = normal($('fin-car-buscar').value.trim());
        carFiltrados = mora.filter(function (e) { return !q || normal(nombreEst(e) + ' ' + e.numero_documento).indexOf(q) !== -1; })
            .sort(function (a, b) { return b.saldo_vencido - a.saldo_vencido; });
        carPagina = 1; pintarMorosos();
    }
    function vieja(e) { return e.cuotas_vencidas.reduce(function (m, c) { return c.vence < m ? c.vence : m; }, '9999-12-31'); }
    function pintarMorosos() {
        var desde = (carPagina - 1) * POR_PAGINA;
        $('fin-car-count').textContent = num(carFiltrados.length) + (carFiltrados.length === 1 ? ' estudiante' : ' estudiantes');
        $('fin-car-empty').hidden = carFiltrados.length > 0;
        $('fin-car-body').innerHTML = carFiltrados.slice(desde, desde + POR_PAGINA).map(function (e) {
            var s = sedePorId(e.sede_id), v = vieja(e), d = diasVencida(v);
            var a = e.acudiente, tel = digitos(a ? a.telefono : e.telefono);
            var msg = 'Hola' + (a ? ' ' + a.nombre.split(' ')[0] : ' ' + e.nombres.split(' ')[0]) + ', te escribimos de ESAT sede ' + s.nombre + '. ' +
                (a ? e.nombres.split(' ')[0] + ' tiene' : 'Tienes') + ' ' + plata(e.saldo_vencido) + ' en cuotas vencidas. ¿Nos ayudas a ponerte al día?';
            return '<tr><td><div class="fin-strong">' + esc(nombreEst(e)) + '</div><div class="fin-muted">' + esc(e.tipo_documento + ' ' + e.numero_documento) + '</div></td>' +
                '<td>' + esc(s.nombre) + '</td><td>' + esc((e.programa || '').replace('Técnico Laboral en ', '')) + '<div class="fin-muted">' + esc(e.grupo || '') + '</div></td>' +
                '<td class="fin-num">' + e.cuotas_vencidas.length + '</td>' +
                '<td>' + esc(fechaMini(v)) + '<div class="fin-muted' + (d > 90 ? ' fin-neg' : '') + '">Hace ' + d + ' días</div></td>' +
                '<td class="fin-num"><span class="fin-strong">' + esc(plata(e.saldo_vencido)) + '</span></td>' +
                '<td>' + (tel.length === 10 ? '<a class="fin-wa" target="_blank" rel="noopener" href="https://wa.me/57' + tel + '?text=' + encodeURIComponent(msg) + '" title="Recordatorio por WhatsApp' + (a ? ' al acudiente' : '') + '">' + ICON.wa + (a ? 'Acudiente' : 'WhatsApp') + '</a>' : '') + '</td></tr>';
        }).join('');
        paginar($('fin-car-paginacion'), carFiltrados.length, carPagina, function (p) { carPagina = p; pintarMorosos(); });
    }
    $('fin-car-buscar').addEventListener('input', pintarCartera);

    /* ── Contabilidad ───────────────────────────── */
    function ingresosDe(sedeId, i) { return sum(D.recaudos.filter(function (r) { return r.sede_id === sedeId && !r.anulado && enMes(r.fecha, i); }), 'valor'); }
    function egresosDe(sedeId, i) {
        return sum(D.egresos.filter(function (g) { return g.sede_id === sedeId && enMes(g.fecha, i); }), 'valor') +
            sum(D.devoluciones.filter(function (d) { return d.sede_id === sedeId && enMes(d.fecha, i); }), 'valor');
    }
    function resultado(v) { return '<span class="' + (v < 0 ? 'fin-neg' : 'fin-pos') + '">' + (v < 0 ? '−' : '') + esc(plata(Math.abs(v))) + '</span>'; }
    function pintarContabilidad() {
        var ss = sedesVisibles();
        var ing = sum(ss.map(function (s) { return ingresosDe(s.id, mes); })), eg = sum(ss.map(function (s) { return egresosDe(s.id, mes); }));
        kpis('fin-con-kpis', [['green', 'plata', plata(ing), 'Ingresos'], ['blue', 'recibo', plata(eg), 'Egresos'],
            [ing - eg < 0 ? 'red' : 'green', 'plata', (ing - eg < 0 ? '−' : '') + plata(Math.abs(ing - eg)), ing - eg < 0 ? 'Pérdida del mes' : 'Utilidad del mes'],
            ['blue', 'reloj', ing ? Math.round(eg / ing * 100) + ' %' : '—', 'De los ingresos se fue en egresos']]);
        $('fin-con-sede-sub').textContent = nombreMes(mes).charAt(0).toUpperCase() + nombreMes(mes).slice(1) + (mes === 5 ? ', hasta hoy' : '');
        var filas = ss.map(function (s) {
            var a = ingresosDe(s.id, mes), b = egresosDe(s.id, mes);
            return '<tr><td><span class="fin-dot ' + colorDe(s) + '" style="display:inline-block;margin-right:8px"></span>' + esc(s.nombre) + '</td><td class="fin-num">' + esc(plata(a)) + '</td><td class="fin-num">' + esc(plata(b)) + '</td><td class="fin-num fin-strong">' + resultado(a - b) + '</td></tr>';
        });
        if (!sel) filas.push('<tr class="fin-total"><td>Total ESAT</td><td class="fin-num">' + esc(plata(ing)) + '</td><td class="fin-num">' + esc(plata(eg)) + '</td><td class="fin-num">' + resultado(ing - eg) + '</td></tr>');
        $('fin-con-sedes').innerHTML = filas.join('');

        var egMes = D.egresos.filter(function (g) { return enSede(g) && enMes(g.fecha, mes); });
        var devMes = D.devoluciones.filter(function (d) { return enSede(d) && enMes(d.fecha, mes); });
        var cats = ['Nómina', 'Arriendo y servicios', 'Materiales e insumos'].map(function (c) { return { nombre: c, valor: sum(egMes.filter(function (g) { return g.categoria === c; }), 'valor') }; });
        cats.push({ nombre: 'Devoluciones', valor: sum(devMes, 'valor') });
        cats = cats.filter(function (c) { return c.valor; }).map(function (c) { c.sub = eg ? Math.round(c.valor / eg * 100) + ' %' : ''; return c; });
        barras('fin-con-cats', cats, Math.max.apply(null, cats.map(function (c) { return c.valor; }).concat([0])));

        $('fin-con-meses-sub').textContent = sel ? 'Sede ' + sedePorId(sel).nombre : 'Todas las sedes';
        $('fin-con-meses').innerHTML = [5, 4, 3, 2, 1, 0].map(function (i) {
            var a = sum(ss.map(function (s) { return ingresosDe(s.id, i); })), b = sum(ss.map(function (s) { return egresosDe(s.id, i); }));
            var n = nombreMes(i);
            return '<tr' + (i === mes ? ' style="background:rgba(217,123,46,0.06)"' : '') + '><td>' + esc(n.charAt(0).toUpperCase() + n.slice(1)) + (i === 5 ? ' <span class="fin-muted" style="display:inline">(hasta hoy)</span>' : '') + '</td>' +
                '<td class="fin-num">' + esc(plata(a)) + '</td><td class="fin-num">' + esc(plata(b)) + '</td><td class="fin-num fin-strong">' + resultado(a - b) + '</td></tr>';
        }).join('');

        $('fin-con-eg-sub').textContent = egMes.length + (egMes.length === 1 ? ' comprobante en ' : ' comprobantes en ') + nombreMes(mes);
        $('fin-con-eg-empty').hidden = egMes.length > 0;
        $('fin-con-egresos').innerHTML = egMes.slice().sort(function (a, b) { return new Date(b.fecha) - new Date(a.fecha); }).map(function (g) {
            return '<tr><td><div class="fin-strong">' + esc(g.numero) + '</div><div class="fin-muted">' + esc(fechaMini(g.fecha)) + '</div></td><td>' + esc(sedePorId(g.sede_id).nombre) + '</td>' +
                '<td>' + esc(g.categoria) + '</td><td>' + esc(g.beneficiario) + '<div class="fin-muted">Registró ' + esc(g.quien) + '</div></td><td>' + esc(g.medio) + '</td><td class="fin-num fin-strong">' + esc(plata(g.valor)) + '</td></tr>';
        }).join('');
    }

    /* ── Devoluciones ───────────────────────────── */
    function pintarDevoluciones() {
        var lista = D.devoluciones.filter(enSede).sort(function (a, b) { return new Date(b.fecha) - new Date(a.fecha); });
        $('fin-dev-empty').hidden = lista.length > 0;
        $('fin-dev-body').innerHTML = lista.map(function (d) {
            return '<tr><td><div class="fin-strong">' + esc(d.numero) + '</div><div class="fin-muted">' + esc(fechaMini(d.fecha)) + '</div></td><td>' + esc(nombreEst(estudiante(d.est_id))) + '</td>' +
                '<td>' + esc(sedePorId(d.sede_id).nombre) + '</td><td>' + esc(d.motivo) + '</td><td>' + esc(d.medio) + '</td><td>' + esc(d.quien) + '</td><td class="fin-num fin-strong">' + esc(plata(d.valor)) + '</td></tr>';
        }).join('');
    }

    /* ── Exportar ───────────────────────────────── */
    document.querySelectorAll('[data-exportar]').forEach(function (b) {
        b.addEventListener('click', function () {
            var t = b.dataset.exportar;
            if (t === 'recaudo') descargarCsv('recibos-' + inicioMes(mes).toISOString().slice(0, 7), [['Recibo', 'Fecha', 'Estudiante', 'Documento', 'Sede', 'Concepto', 'Medio', 'Referencia', 'Registró', 'Valor', 'Estado', 'Motivo de anulación']]
                .concat(recFiltrados.map(function (r) { var e = estudiante(r.est_id); return [r.numero, fechaMini(r.fecha), nombreEst(e), e.numero_documento, sedePorId(r.sede_id).nombre, r.concepto, r.medio, r.referencia, r.quien.nombre, r.valor, r.anulado ? 'Anulado' : 'Vigente', r.motivo || '']; })));
            if (t === 'cartera') descargarCsv('cartera-vencida', [['Estudiante', 'Documento', 'Sede', 'Programa', 'Grupo', 'Cuotas vencidas', 'Vencida desde', 'Debe', 'Celular', 'Acudiente', 'Celular del acudiente']]
                .concat(carFiltrados.map(function (e) { return [nombreEst(e), e.numero_documento, sedePorId(e.sede_id).nombre, e.programa, e.grupo, e.cuotas_vencidas.length, vieja(e), e.saldo_vencido, e.telefono, e.acudiente ? e.acudiente.nombre : '', e.acudiente ? e.acudiente.telefono : '']; })));
            if (t === 'egresos') descargarCsv('egresos-' + inicioMes(mes).toISOString().slice(0, 7), [['Comprobante', 'Fecha', 'Sede', 'Categoría', 'Beneficiario', 'Medio', 'Registró', 'Valor']]
                .concat(D.egresos.filter(function (g) { return enSede(g) && enMes(g.fecha, mes); }).map(function (g) { return [g.numero, fechaMini(g.fecha), sedePorId(g.sede_id).nombre, g.categoria, g.beneficiario, g.medio, g.quien, g.valor]; })));
        });
    });

    /* ── Pintar ─────────────────────────────────── */
    function pintar() {
        chips.querySelectorAll('.fin-chip').forEach(function (c) { var on = (c.dataset.sede || '') === sel; c.classList.toggle('is-active', on); c.setAttribute('aria-checked', on); });
        pintarRecaudo(); pintarCartera(); pintarContabilidad(); pintarDevoluciones();
    }
    mostrarTab();
    pintar();
}());
