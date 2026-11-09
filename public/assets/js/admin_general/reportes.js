/* =========================================================
   ESAT — Panel Admin (ESAT general): Reportes
   Documentos formales de todo ESAT o de una sede, como estaban al cierre del mes elegido (el mes en curso, hasta
   hoy): matriculados, novedades, recaudo, ingresos y egresos, y cartera por edades (a hoy). Imprimir y Excel.
   Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 10;

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

    var toastEl = $('rep-toast'), toastT;
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
        nav.innerHTML = '<button type="button" class="rep-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="rep-pg-gap">…</span>'
                    : '<button type="button" class="rep-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="rep-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
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
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    function descargarCsv(base, filas) {
        var csv = '﻿' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a'); a.href = url; a.download = base + '.csv';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        toast('Se descargó ' + base + '.csv.');
    }
    var MES_C = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
    function fechaMini(f) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f); return d.getDate() + ' ' + MES_C[d.getMonth()] + ' ' + d.getFullYear(); }

    /* ── Mes y sede ─────────────────────────────── */
    var HOY = new Date(D.hoy), DIA = 86400000;
    var mes = 5, sel = '', doc = 'matriculados';
    try { doc = localStorage.getItem('esat.general.reportes.doc') || doc; } catch (e) {}
    function inicioMes(i) { return new Date(HOY.getFullYear(), HOY.getMonth() - (5 - i), 1); }
    function corte(i) { return i === 5 ? HOY : new Date(HOY.getFullYear(), HOY.getMonth() - (5 - i) + 1, 0, 23, 59, 59); }
    function enMes(f, i) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f), a = inicioMes(i); return d.getFullYear() === a.getFullYear() && d.getMonth() === a.getMonth(); }
    function nombreMes(i) { var a = inicioMes(i), n = MESES[a.getMonth()] + ' de ' + a.getFullYear(); return n.charAt(0).toUpperCase() + n.slice(1); }
    function sedePorId(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function sedes() { return sel ? [sedePorId(sel)] : D.sedes; }
    function enSede(x) { return !sel || String(x.sede_id) === sel; }
    function corto(p) { return String(p || '').replace('Técnico Laboral en ', ''); }
    function sum(l, k) { return l.reduce(function (t, x) { return t + (k ? x[k] : x); }, 0); }
    var cbMes = $('rep-cb-mes'), cbSede = $('rep-cb-sede');
    initCombobox(cbMes); initCombobox(cbSede);
    cbMes.setOptions([5, 4, 3, 2, 1, 0].map(function (i) { return { value: String(i), label: nombreMes(i) + (i === 5 ? ' (en curso)' : '') }; }));
    cbMes.setValue('5', nombreMes(5) + ' (en curso)', { silent: true });
    cbSede.setOptions([{ value: '', label: 'Todas las sedes' }].concat(D.sedes.map(function (s) { return { value: String(s.id), label: 'Sede ' + s.nombre }; })));
    cbSede.setValue('', 'Todas las sedes', { silent: true });
    cbMes.querySelector('[data-combobox-value]').addEventListener('change', function () { mes = +cbMes.value(); pintar(); });
    cbSede.querySelector('[data-combobox-value]').addEventListener('change', function () { sel = cbSede.value(); pintar(); });

    /* Estado de una matrícula al corte (solo se conoce el último cambio de estado, como en la secretaría) */
    function estadoAl(e, f) {
        if (!e.fecha_matricula || new Date(e.fecha_matricula + 'T00:00:00') > f) return null;
        if (e.estado_fecha && new Date(e.estado_fecha + 'T00:00:00') <= f) return e.estado;
        return e.estado === 'sin_matricula' ? null : 'activo';
    }

    /* ── Documentos: cada uno arma {titulo, sub, resumen, tablas, notas} ── */
    var DOCS = {
        matriculados: {
            titulo: 'Estudiantes matriculados', desc: 'Activos y aplazados al cierre del mes, por sede y programa.',
            armar: function () {
                var f = corte(mes), filas = [], tot = [0, 0];
                sedes().forEach(function (s) {
                    (s.programas || []).forEach(function (pid) {
                        var p = D.programas.find(function (x) { return x.id === pid; });
                        var est = D.estudiantes.filter(function (e) { return e.sede_id === s.id && e.programa_id === pid; });
                        var a = est.filter(function (e) { return estadoAl(e, f) === 'activo'; }).length, b = est.filter(function (e) { return estadoAl(e, f) === 'aplazado'; }).length;
                        if (a || b) { filas.push([s.nombre, corto(p.nombre), a, b, a + b]); tot[0] += a; tot[1] += b; }
                    });
                });
                return { resumen: [[num(tot[0]), 'Activos'], [num(tot[1]), 'Aplazados'], [num(tot[0] + tot[1]), 'Matrículas vigentes']],
                    tablas: [{ cols: ['Sede', 'Programa', 'Activos', 'Aplazados', 'Total'], num: [2, 3, 4], filas: filas, total: ['Total', '', tot[0], tot[1], tot[0] + tot[1]] }],
                    notas: 'Una matrícula vigente es activa o aplazada. Retirados, graduados y anulados no cuentan.' };
            }
        },
        novedades: {
            titulo: 'Novedades del mes', desc: 'Matrículas nuevas, aplazamientos, retiros y graduados.',
            armar: function () {
                var filas = [];
                D.estudiantes.filter(enSede).forEach(function (e) {
                    var s = sedePorId(e.sede_id), n = e.nombres + ' ' + e.apellidos;
                    if (e.fecha_matricula && enMes(e.fecha_matricula, mes)) filas.push([fechaMini(e.fecha_matricula), n, e.numero_documento, s.nombre, corto(e.programa), 'Matrícula nueva', '']);
                    if (e.estado_fecha && enMes(e.estado_fecha, mes)) filas.push([fechaMini(e.estado_fecha), n, e.numero_documento, s.nombre, corto(e.programa), { aplazado: 'Aplazamiento', retirado: 'Retiro', graduado: 'Grado' }[e.estado], e.estado_motivo || '']);
                });
                var ORD = ['Matrícula nueva', 'Aplazamiento', 'Retiro', 'Grado'];
                filas.sort(function (a, b) { return (ORD.indexOf(a[5]) - ORD.indexOf(b[5])) || a[1].localeCompare(b[1]); });
                var c = function (t) { return filas.filter(function (x) { return x[5] === t; }).length; };
                return { resumen: [[c('Matrícula nueva'), 'Matrículas nuevas'], [c('Aplazamiento'), 'Aplazamientos'], [c('Retiro'), 'Retiros'], [c('Grado'), 'Graduados']],
                    tablas: [{ cols: ['Fecha', 'Estudiante', 'Documento', 'Sede', 'Programa', 'Novedad', 'Motivo'], nowrap: [0, 2, 3, 5], filas: filas }] };
            }
        },
        recaudo: {
            titulo: 'Recaudo del mes', desc: 'Lo recibido por sede y por medio de pago, sin recibos anulados.',
            armar: function () {
                var MED = ['Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta'];
                var r = D.recaudos.filter(function (x) { return enSede(x) && !x.anulado && enMes(x.fecha, mes); });
                var an = D.recaudos.filter(function (x) { return enSede(x) && x.anulado && enMes(x.fecha, mes); });
                var filas = sedes().map(function (s) {
                    var rs = r.filter(function (x) { return x.sede_id === s.id; });
                    return [s.nombre, rs.length].concat(MED.map(function (m) { return sum(rs.filter(function (x) { return x.medio === m; }), 'valor'); })).concat([sum(rs, 'valor')]);
                });
                var total = ['Total', r.length].concat(MED.map(function (m) { return sum(r.filter(function (x) { return x.medio === m; }), 'valor'); })).concat([sum(r, 'valor')]);
                return { resumen: [[plata(sum(r, 'valor')), 'Recaudado'], [num(r.length), 'Recibos'], [num(an.length) + ' · ' + plata(sum(an, 'valor')), 'Anulados (no suman)']],
                    tablas: [{ cols: ['Sede', 'Recibos'].concat(MED).concat(['Total']), num: [1, 2, 3, 4, 5, 6, 7], dinero: [2, 3, 4, 5, 6, 7], filas: filas, total: sel ? null : total }],
                    notas: 'Los consecutivos de recibo (RC) son de todo ESAT. Un recibo anulado no se vuelve a usar.' };
            }
        },
        resultado: {
            titulo: 'Ingresos y egresos', desc: 'Resultado del mes de cada sede: lo recaudado menos lo gastado.',
            armar: function () {
                var CAT = ['Nómina', 'Arriendo y servicios', 'Materiales e insumos'];
                var filas = sedes().map(function (s) {
                    var ing = sum(D.recaudos.filter(function (x) { return x.sede_id === s.id && !x.anulado && enMes(x.fecha, mes); }), 'valor');
                    var eg = CAT.map(function (c) { return sum(D.egresos.filter(function (g) { return g.sede_id === s.id && g.categoria === c && enMes(g.fecha, mes); }), 'valor'); });
                    var dev = sum(D.devoluciones.filter(function (d) { return d.sede_id === s.id && enMes(d.fecha, mes); }), 'valor');
                    var tot = sum(eg) + dev;
                    return [s.nombre, ing].concat(eg).concat([dev, tot, ing - tot]);
                });
                var t = filas.reduce(function (a, f) { return a.map(function (v, i) { return i === 0 ? v : v + f[i]; }); }, ['Total ESAT', 0, 0, 0, 0, 0, 0, 0]);
                return { resumen: [[plata(t[1]), 'Ingresos'], [plata(t[6]), 'Egresos'], [(t[7] < 0 ? '−' : '') + plata(Math.abs(t[7])), t[7] < 0 ? 'Pérdida' : 'Utilidad']],
                    tablas: [{ cols: ['Sede', 'Ingresos', 'Nómina', 'Arriendo y servicios', 'Materiales', 'Devoluciones', 'Total egresos', 'Resultado'], num: [1, 2, 3, 4, 5, 6, 7], dinero: [1, 2, 3, 4, 5, 6, 7], filas: filas, total: sel ? null : t }],
                    notas: 'Ingresos = recibos sin anular. La nómina se registra al terminar el mes, así que el mes en curso todavía no la tiene.' };
            }
        },
        cartera: {
            titulo: 'Cartera por edades', desc: 'Lo vencido y sin pagar, por sede y antigüedad (a hoy).',
            armar: function () {
                var E = [[1, 30], [31, 60], [61, 90], [91, Infinity]];
                function edades(l) {
                    var r = [0, 0, 0, 0];
                    l.forEach(function (e) { e.cuotas_vencidas.forEach(function (c) { var d = Math.max(1, Math.floor((HOY - new Date(c.vence + 'T12:00:00')) / DIA)); E.forEach(function (x, i) { if (d >= x[0] && d <= x[1]) r[i] += c.valor; }); }); });
                    return r;
                }
                var filas = sedes().map(function (s) {
                    var m = D.estudiantes.filter(function (e) { return e.sede_id === s.id && e.estado === 'activo' && e.pago === 'vencido'; });
                    var r = edades(m);
                    return [s.nombre, m.length].concat(r).concat([sum(r)]);
                });
                var t = filas.reduce(function (a, f) { return a.map(function (v, i) { return i === 0 ? v : v + f[i]; }); }, ['Total ESAT', 0, 0, 0, 0, 0, 0]);
                return { resumen: [[plata(t[6]), 'Cartera vencida'], [num(t[1]), 'Estudiantes en mora'], [plata(t[5]), 'Más de 90 días']],
                    tablas: [{ cols: ['Sede', 'Estudiantes', '1 a 30 días', '31 a 60 días', '61 a 90 días', 'Más de 90 días', 'Total'], num: [1, 2, 3, 4, 5, 6], dinero: [2, 3, 4, 5, 6], filas: filas, total: sel ? null : t }],
                    notas: 'La cartera se muestra a hoy: no depende del mes elegido. Las cuotas vencen el día 10 de cada mes.', aHoy: true };
            }
        }
    };
    var ICONO_DOC = '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>';
    if (!DOCS[doc]) doc = 'matriculados';
    $('rep-lista').innerHTML = Object.keys(DOCS).map(function (k) {
        return '<li><button type="button" class="rep-doc" data-doc="' + k + '"><span class="rep-doc-ico">' + ICONO_DOC + '</span><span><span class="rep-doc-titulo">' + esc(DOCS[k].titulo) + '</span><span class="rep-doc-desc">' + esc(DOCS[k].desc) + '</span></span></button></li>';
    }).join('');
    $('rep-lista').addEventListener('click', function (e) {
        var b = e.target.closest('[data-doc]'); if (!b) return;
        doc = b.dataset.doc; try { localStorage.setItem('esat.general.reportes.doc', doc); } catch (err) {}
        pintar();
    });

    var actual = null;
    function celda(t, v, i) {
        var dinero = t.dinero && t.dinero.indexOf(i) >= 0, n = t.num && t.num.indexOf(i) >= 0;
        var txt = dinero ? (v < 0 ? '−' : '') + plata(Math.abs(v)) : typeof v === 'number' ? num(v) : v;
        return '<td' + (n ? ' class="rep-num"' : t.nowrap && t.nowrap.indexOf(i) >= 0 ? ' class="rep-nowrap"' : '') + '>' + esc(txt) + '</td>';
    }
    function pintar() {
        document.querySelectorAll('.rep-doc').forEach(function (b) { b.classList.toggle('is-active', b.dataset.doc === doc); b.setAttribute('aria-pressed', b.dataset.doc === doc); });
        var d = DOCS[doc], r = d.armar(), I = D.configuracion.institucion, f = corte(mes);
        actual = { d: d, r: r };
        var alcance = sel ? 'Sede ' + sedePorId(sel).nombre : 'Todas las sedes';
        var periodo = r.aHoy ? 'A hoy, ' + fechaLarga(HOY.toISOString()) : nombreMes(mes) + (mes === 5 ? ' · hasta hoy' : '');
        $('rep-hoja').innerHTML =
            '<header class="rep-hoja-cab"><div><div class="rep-inst">' + esc(I.nombre) + '</div><div class="rep-inst-sub">NIT ' + esc(I.nit) + ' · Licencia de funcionamiento ' + esc(I.licencia) + ' de ' + esc(fechaLarga(I.fecha_licencia)) + '<br>' + esc(I.entidad) + '</div></div>' +
            '<div class="rep-corte">' + esc(alcance) + '<br>' + esc(r.aHoy ? 'Corte: hoy' : 'Corte: ' + fechaLarga(f.toISOString())) + '<br>Generado por ' + esc(D.yo) + '</div></header>' +
            '<h2>' + esc(d.titulo) + '</h2><p class="rep-hoja-sub">' + esc(periodo) + '</p>' +
            '<div class="rep-resumen">' + r.resumen.map(function (x) { return '<div><strong>' + esc(x[0]) + '</strong><span>' + esc(x[1]) + '</span></div>'; }).join('') + '</div>' +
            r.tablas.map(function (t) {
                return '<h3>Detalle</h3>' + (t.filas.length
                    ? '<div class="rep-tabla-wrap"><table class="rep-tabla"><thead><tr>' + t.cols.map(function (c, i) { return '<th' + (t.num && t.num.indexOf(i) >= 0 ? ' class="rep-num"' : '') + '>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>' +
                      t.filas.map(function (fl) { return '<tr>' + fl.map(function (v, i) { return celda(t, v, i); }).join('') + '</tr>'; }).join('') +
                      (t.total ? '<tr class="rep-total">' + t.total.map(function (v, i) { return celda(t, v, i); }).join('') + '</tr>' : '') + '</tbody></table></div>'
                    : '<p class="rep-vacia">No hubo movimientos en el periodo.</p>');
            }).join('') +
            (r.notas ? '<p class="rep-notas">' + esc(r.notas) + '</p>' : '') +
            '<div class="rep-firmas"><div class="rep-firma">Elaboró<span>' + esc(D.yo) + ' · Admin de ESAT</span></div><div class="rep-firma">Revisó<span>&nbsp;</span></div></div>';
    }
    $('rep-imprimir').addEventListener('click', function () { window.print(); });
    $('rep-excel').addEventListener('click', function () {
        var t = actual.r.tablas[0], base = actual.d.titulo.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-') +
            '-' + (sel ? normal(sedePorId(sel).nombre).replace(/\s+/g, '-') + '-' : '') + (actual.r.aHoy ? 'hoy' : inicioMes(mes).toISOString().slice(0, 7));
        descargarCsv(base, [t.cols].concat(t.filas).concat(t.total ? [t.total] : []));
    });

    pintar();
}());
