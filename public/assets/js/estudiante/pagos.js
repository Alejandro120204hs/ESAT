/* ESAT — Estudiante: Mis pagos
   Lee window.EST_DATOS.pagos y elige el plan con window.EST_API.elegirPlan (datos.js: PagoService en el servidor).
   Solo consulta, salvo elegir el plan una vez. Los recibos se imprimen con
   EST_DOC (la hoja del recibo de Secretaría › Matrículas). */
(function () {
    'use strict';

    var D = window.EST_DATOS, API = window.EST_API;
    var app = document.getElementById('pag-app');
    if (!D || !API || !app) return;

    var MESES   = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var NB = ' ';   // espacio que no parte la línea
    var ICON = {
        recibo:   '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2l-3 2-3-2-3 2-3-2-3 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="14" y2="13"/></svg>',
        imprimir: '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
        info:     '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
        alerta:   '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
    };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function $(id) { return document.getElementById(id); }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function fechaCorta(iso) { var f = fecha(iso); return f.getDate() + NB + MESES_C[f.getMonth()] + NB + f.getFullYear(); }
    function fechaSinAnio(iso) { var f = fecha(iso); return f.getDate() + NB + 'de' + NB + MESES[f.getMonth()]; }
    function fechaAnio(iso) { var f = fecha(iso); return f.getDate() + ' de ' + MESES[f.getMonth()] + ' de ' + f.getFullYear(); }
    function plata(n) { return '$' + NB + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }

    var M = D.matricula;
    // Sin matrícula no hay cobros: solo el aviso
    if (!D.pagos) {
        document.getElementById('pag-vacio').hidden = false;
        ['pag-plan', 'pag-cuenta', 'pag-toolbar', 'pag-panel-cuotas', 'pag-panel-recibos'].forEach(function (id) { document.getElementById(id).hidden = true; });
        app.querySelector('.pag-pie').hidden = true;
        return;
    }
    var ESTADO = { pagado: ['app-status-active', 'Pagado'], pendiente: ['app-status-warn', 'Pendiente'], vencido: ['app-status-danger', 'Vencido'] };

    /* ── Elegir el plan ───────────────────────── */
    var elegido = null;
    function pintarPlan() {
        var P = D.pagos, cont = $('pag-plan');
        cont.hidden = !P.puedeElegir || !P.opciones;
        if (cont.hidden) return;
        var ops = P.opciones;
        $('pag-opciones').innerHTML = '<legend class="sr-solo">Plan de pago</legend>' + ['mensual', 'periodo'].map(function (k) {
            var o = ops[k];
            var cada = k === 'mensual' ? 'al mes' : 'por ' + o.cada;
            var sistema = k === 'mensual' ? 'cuota del sistema ' + plata(o.sistema) : 'cuota del sistema ' + plata(o.sistema) + ' (' + o.meses + ' meses)';
            return '<label class="pag-op"><input type="radio" name="pag-plan" value="' + k + '"' + (elegido === k ? ' checked' : '') + '>' +
                '<span class="pag-op-nombre">' + esc(o.nombre) + '</span>' +
                '<span class="pag-op-valor">' + esc(plata(o.cuota)) + ' <small>' + esc(cada) + '</small></span>' +
                '<span class="pag-op-det">' + esc(plural(o.cuotas, 'cuota', 'cuotas') + ', el día 10. Carrera ' + plata(o.valorPrograma) + ' + ' + sistema + '.') + '</span>' +
            '</label>';
        }).join('');
        pintarAvisoPlan();
    }
    function pintarAvisoPlan() {
        var P = D.pagos, btn = $('pag-elegir');
        btn.disabled = !elegido;
        btn.textContent = elegido ? 'Elegir el plan ' + P.opciones[elegido].nombre.toLowerCase() : 'Elegir este plan';
        var inicio = (D.matricula && D.matricula.grupo) ? D.matricula.grupo.inicio : D.hoy, txt;
        if (elegido && P.vencidasSiElige(elegido) > 0) {
            var n = P.vencidasSiElige(elegido);
            txt = '<div class="pag-aviso pag-aviso-riesgo">' + ICON.alerta + '<p>Tus clases empezaron el <b>' + esc(fechaAnio(inicio)) + '</b>: con este plan ' +
                esc(plural(n, 'cuota ya venció', 'cuotas ya vencieron')) + ' y quedarían por pagar de una vez.</p></div>';
        } else {
            txt = '<div class="pag-aviso pag-aviso-neutro">' + ICON.info + '<p>Las cuotas cuentan desde el mes en que empezaron tus clases (' +
                esc(fechaAnio(inicio)) + '). El derecho de matrícula es aparte: es un solo pago.</p></div>';
        }
        $('pag-plan-aviso').innerHTML = txt;
    }
    $('pag-opciones').addEventListener('change', function (e) {
        if (e.target.name !== 'pag-plan') return;
        elegido = e.target.value;
        pintarAvisoPlan();
    });
    $('pag-elegir').addEventListener('click', function () {
        if (!elegido) return;
        var o = D.pagos.opciones[elegido];
        confirmar('¿Elegir el plan ' + o.nombre.toLowerCase() + '?',
            'Pagarás ' + plural(o.cuotas, 'cuota', 'cuotas') + ' de ' + plata(o.cuota) + ', el día 10 de cada ' + (elegido === 'mensual' ? 'mes' : o.cada) + '. Después no se puede cambiar.',
            function () {
                var btn = $('pag-elegir');
                btn.disabled = true;
                API.elegirPlan(elegido).then(function (r) {
                    toast(r.mensaje, !r.ok);
                    if (!r.ok) { btn.disabled = false; return; }
                    render();
                    var cuenta = $('pag-cuenta');
                    cuenta.setAttribute('tabindex', '-1');
                    cuenta.focus();
                });
            });
    });

    /* ── Lo que debe en otras matrículas (ES5) ── */
    var ESTADO_OTRA = { activo: 'activa', aplazado: 'aplazada', retirado: 'retirada', graduado: 'graduada' };
    function pintarOtras() {
        var otras = D.pagos.otras || [], el = $('pag-otras');
        el.hidden = !otras.length;
        if (!otras.length) { el.innerHTML = ''; return; }
        var total = otras.reduce(function (s, o) { return s + o.porPagar; }, 0);
        el.innerHTML = ICON.alerta + '<p>Además debes <b>' + esc(plata(total)) + '</b> de ' + (otras.length === 1 ? 'tu otra matrícula' : 'tus otras matrículas') + ': ' +
            otras.map(function (o) {
                return esc(o.programa + ' (' + (ESTADO_OTRA[o.estado] || o.estado) + ')' + (o.vencido > 0 ? ', ' + plata(o.vencido) + ' ya vencido' : ''));
            }).join('; ') + '. Se paga en la administración de tu sede y sin eso no recibes el paz y salvo.</p>';
    }

    /* ── Estado de cuenta ─────────────────────── */
    function pintarCuenta() {
        var P = D.pagos, R = P.resumen, cont = $('pag-cuenta');
        // Sin plan, el estado de cuenta sale igual si debe algo (por ejemplo el derecho de matrícula)
        var debeAlgo = P.cobros.some(function (c) { return c.restante > 0; });
        cont.hidden = !P.plan && !debeAlgo;
        if (cont.hidden) { cont.innerHTML = ''; return; }
        var venc = P.cobros.filter(function (c) { return c.estado === 'vencido'; });
        var subPagar = R.vencido > 0
            ? (venc.length === 1 ? venc[0].concepto + ': venció el ' + fechaSinAnio(venc[0].vence) : plural(venc.length, 'cobro vencido', 'cobros vencidos'))
            : (R.aPagar > 0 ? 'Vence este mes' : ((P.otras || []).length ? 'Al día en esta matrícula' : 'Estás al día'));
        var total = R.pagado + R.porPagar, pct = total ? Math.round(R.pagado * 100 / total) : 0;
        var px = R.proxima, finMes = D.hoy.slice(0, 7) + '-31';
        if (px && R.aPagar > 0 && px.vence <= finMes) {
            // La de este mes ya está en "A pagar este mes": aquí va la que sigue (cada cifra una vez)
            px = P.cobros.filter(function (c) { return c.restante > 0 && c.vence && c.vence > finMes; })
                .sort(function (a, b) { return a.vence < b.vence ? -1 : 1; })[0] || null;
        }
        cont.innerHTML =
            '<div class="pag-cifra"><span class="pag-cifra-lbl">A pagar este mes</span>' +
                '<span class="pag-cifra-val' + (R.vencido > 0 ? ' is-riesgo' : (R.aPagar === 0 ? ' is-bien' : '')) + '">' + esc(plata(R.aPagar)) + '</span>' +
                '<span class="pag-cifra-sub">' + esc(subPagar) + '</span></div>' +
            '<div class="pag-cifra"><span class="pag-cifra-lbl">Próxima cuota</span>' +
                (px ? '<span class="pag-cifra-val">' + esc(plata(px.restante)) + '</span><span class="pag-cifra-sub">' + esc(px.concepto + ' · vence el ' + fechaSinAnio(px.vence)) + '</span>'
                    : '<span class="pag-cifra-val is-bien">—</span><span class="pag-cifra-sub">No te quedan cuotas por vencer</span>') + '</div>' +
            '<div class="pag-cifra"><span class="pag-cifra-lbl">Pagado</span>' +
                '<span class="pag-cifra-val">' + esc(plata(R.pagado)) + '</span>' +
                '<span class="pag-barra" role="progressbar" aria-label="Pagado del total" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"><span style="width:' + pct + '%"></span></span>' +
                '<span class="pag-cifra-sub">' + esc('Te faltan ' + plata(R.porPagar) + ' de ' + plata(total)) + '</span></div>' +
            (P.plan && P.opciones
                ? '<p class="pag-cuenta-pie">Plan <b>' + esc(P.opciones[P.plan].nombre.toLowerCase()) + '</b>: ' + esc(plural(P.totalCuotas || P.opciones[P.plan].cuotas, 'cuota', 'cuotas')) +
                    ' el día 10 (' + esc(desglose(primeraCuota())) + '). El diploma se entrega cuando estés a paz y salvo.</p>'
                : '<p class="pag-cuenta-pie">Todavía no tienes plan de pago: tus cuotas aparecen cuando lo elijas. El diploma se entrega cuando estés a paz y salvo.</p>');
    }

    /* ── Cobros ───────────────────────────────── */
    function desglose(c) { return c ? 'carrera ' + plata(c.valorPrograma) + ' + cuota del sistema ' + plata(c.sistema) : ''; }
    function primeraCuota() { return D.pagos.cobros.filter(function (c) { return c.tipo !== 'matricula'; })[0] || null; }
    function pintarCobros() {
        var P = D.pagos, R = P.resumen, base = desglose(primeraCuota());
        if (!P.cobros.length) { $('pag-cobros').innerHTML = '<tr><td class="pag-vacio" colspan="5">No tienes cobros todavía.</td></tr>'; return; }
        $('pag-cobros').innerHTML = P.cobros.map(function (c) {
            var tag = ESTADO[c.estado];
            // El desglose de las cuotas va una vez en el plan; aquí solo el de la que es distinta (la última absorbe los pesos que sobran)
            var sub = c.tipo === 'matricula' ? 'Pago único' : (desglose(c) !== base ? desglose(c).charAt(0).toUpperCase() + desglose(c).slice(1) : '');
            var cls = c.estado === 'vencido' ? ' is-vencido' : (R.proxima && R.proxima.id === c.id ? ' is-proxima' : '');
            var falta = c.restante > 0 && c.abonado > 0 ? '<span class="pag-concepto-sub">Faltan ' + esc(plata(c.restante)) + '</span>' : '';
            return '<tr class="pag-fila' + cls + '">' +
                '<td><span class="pag-concepto">' + esc(c.concepto) + '</span>' + (sub ? '<span class="pag-concepto-sub">' + esc(sub) + '</span>' : '') + '</td>' +
                '<td data-lbl="Vence">' + esc(c.vence ? fechaCorta(c.vence) : 'Al reintegrarte') + '</td>' +
                '<td class="pag-num" data-lbl="Valor">' + esc(plata(c.valor)) + '</td>' +
                '<td class="pag-num' + (c.abonado || !c.valor ? '' : ' pag-falta') + '" data-lbl="Pagado">' + (!c.valor ? 'No se cobra' : (c.abonado ? esc(plata(c.abonado)) : '—')) + '</td>' +
                '<td><span class="app-status-tag ' + tag[0] + '">' + tag[1] + '</span>' + falta + '</td>' +
            '</tr>';
        }).join('');
    }

    /* ── Recibos ──────────────────────────────── */
    function pintarRecibos() {
        var lista = D.pagos.recibos;
        $('pag-num-recibos').textContent = lista.length || '';
        if (!lista.length) { $('pag-recibos').innerHTML = '<li class="pag-recibo"><span class="pag-recibo-sub">Todavía no tienes recibos.</span></li>'; return; }
        $('pag-recibos').innerHTML = lista.map(function (r, i) {
            var conceptos = r.aplicado.map(function (a) { return a.concepto; }).join(', ');
            return '<li class="pag-recibo' + (r.anulado ? ' is-anulado' : '') + '">' +
                '<span class="pag-recibo-icono" aria-hidden="true">' + ICON.recibo + '</span>' +
                '<div class="pag-recibo-info">' +
                    '<div class="pag-recibo-top"><span class="pag-recibo-num">' + esc(r.numero) + '</span>' +
                        (r.anulado ? '<span class="app-status-tag app-status-muted">Anulado</span>' : '') + '</div>' +
                    '<span class="pag-recibo-sub">' + esc(fechaCorta(r.fecha) + ' · ' + r.medio + (r.referencia ? ' (ref. ' + r.referencia + ')' : '') + ' · ' + conceptos) + '</span>' +
                    (r.anulado ? '<span class="pag-recibo-sub">' + esc('La administración lo anuló' + (r.motivo ? ': ' + String(r.motivo).replace(/\.?\s*$/, '.') : '.') + ' No cuenta como pago.') + '</span>' : '') +
                '</div>' +
                '<span class="pag-recibo-total">' + esc(plata(r.total)) + '</span>' +
                '<button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" data-recibo="' + i + '" aria-label="' + esc('Imprimir el recibo ' + r.numero) + '">' + ICON.imprimir + 'Imprimir</button>' +
            '</li>';
        }).join('');
    }
    $('pag-recibos').addEventListener('click', function (e) {
        var b = e.target.closest('[data-recibo]');
        if (!b) return;
        imprimirRecibo(D.pagos.recibos[+b.dataset.recibo]);
    });
    function imprimirRecibo(r) {
        var e = window.EST_DOC.esc, E = D.estudiante, G = M.grupo;
        window.EST_DOC.imprimir({
            titulo: 'Recibo de pago',
            numero: r.numero,
            extra: fechaAnio(r.fecha),
            sello: r.anulado ? 'Anulado' : null,
            contenido:
                (r.anulado ? '<div class="rc-aviso"><strong>Este recibo fue anulado.</strong>' + (r.motivo ? '<span>Motivo: ' + e(r.motivo) + '</span>' : '') + '</div>' : '') +
                '<section class="rc-datos">' +
                    '<div><span>Estudiante</span><strong>' + e((E.nombres + ' ' + E.apellidos).trim()) + '</strong></div>' +
                    '<div><span>Documento</span><strong>' + e(E.documento || '—') + '</strong></div>' +
                    '<div><span>Programa</span><strong>' + e(M.programa) + '</strong></div>' +
                    '<div><span>Matrícula</span><strong>' + e(M.codigo + ' · ' + G.nombre) + '</strong></div>' +
                '</section>' +
                '<table class="rc-tabla"><thead><tr><th>Concepto</th><th class="rc-num">Valor pagado</th></tr></thead><tbody>' +
                    (r.aplicado.length ? '' : '<tr><td>Pago anulado</td><td class="rc-num">' + e(plata(r.total)) + '</td></tr>') +
                    r.aplicado.map(function (a) { return '<tr><td><strong>' + e(a.concepto) + '</strong></td><td class="rc-num">' + e(plata(a.monto)) + '</td></tr>'; }).join('') +
                '</tbody><tfoot><tr><td>Total recibido</td><td class="rc-num">' + e(plata(r.total)) + '</td></tr></tfoot></table>' +
                '<section class="rc-datos rc-datos-pago">' +
                    '<div><span>Medio de pago</span><strong>' + e(r.medio) + '</strong></div>' +
                    '<div><span>Referencia</span><strong>' + e(r.referencia || '—') + '</strong></div>' +
                    '<div><span>Recibió</span><strong>Administración de la sede</strong></div>' +
                '</section>' +
                '<p class="rc-nota">Copia del recibo impresa desde el panel del estudiante.</p>'
        });
    }

    /* ── Pestañas ─────────────────────────────── */
    var tabs = Array.prototype.slice.call(app.querySelectorAll('.pag-vista-btn'));
    function mostrar(v, enfocar) {
        tabs.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            if (on && enfocar) b.focus();
        });
        $('pag-panel-cuotas').hidden = v !== 'cuotas';
        $('pag-panel-recibos').hidden = v !== 'recibos';
    }
    tabs.forEach(function (b, i) {
        b.addEventListener('click', function () { mostrar(b.dataset.vista); });
        b.addEventListener('keydown', function (e) {
            var j = e.key === 'ArrowRight' ? (i + 1) % tabs.length : (e.key === 'ArrowLeft' ? (i - 1 + tabs.length) % tabs.length : null);
            if (j === null) return;
            e.preventDefault();
            mostrar(tabs[j].dataset.vista, true);
        });
    });

    /* ── Confirmación y avisos ────────────────── */
    var alConfirmar = null, focoAntes = null;
    function confirmar(titulo, texto, fn) {
        alConfirmar = fn;
        focoAntes = document.activeElement;
        $('pag-dlg-titulo').textContent = titulo;
        $('pag-dlg-txt').textContent = texto;
        $('pag-dialogo').hidden = false;
        $('pag-dlg-si').focus();
    }
    function cerrarDialogo(si) {
        $('pag-dialogo').hidden = true;
        var fn = alConfirmar;
        alConfirmar = null;
        if (focoAntes && focoAntes.focus) focoAntes.focus();
        if (si && fn) fn();
    }
    $('pag-dlg-si').addEventListener('click', function () { cerrarDialogo(true); });
    $('pag-dialogo').querySelectorAll('[data-no]').forEach(function (b) { b.addEventListener('click', function () { cerrarDialogo(false); }); });
    document.addEventListener('keydown', function (e) {
        if ($('pag-dialogo').hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); cerrarDialogo(false); }
        if (e.key === 'Tab') {
            var f = $('pag-dialogo').querySelectorAll('button');
            var primero = f[0], ultimo = f[f.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        }
    });
    var toastT = null;
    function toast(msg, error) {
        if (!msg) return;
        var t = $('pag-toast');
        t.textContent = msg;
        t.classList.toggle('is-error', !!error);
        t.setAttribute('role', error ? 'alert' : 'status');
        t.hidden = false;
        requestAnimationFrame(function () { t.classList.add('is-visible'); });
        clearTimeout(toastT);
        toastT = setTimeout(function () { t.classList.remove('is-visible'); setTimeout(function () { t.hidden = true; }, 220); }, 4500);
    }

    function render() {
        pintarPlan();
        pintarOtras();
        pintarCuenta();
        pintarCobros();
        pintarRecibos();
    }
    render();
})();
