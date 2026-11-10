/* =========================================================
   ESAT — Panel Admin (ESAT general): Inicio
   Lee GEN_DATOS (InicioController, datos reales) y pinta KPI, comparación de sedes, ingresos de 6 meses, alertas y
   actividad según la sede elegida ("" = todas). Todo lo que viene de los datos se escapa con esc().
   ========================================================= */
document.addEventListener('DOMContentLoaded', function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var SEDE_KEY = 'esat.general.inicio.sede';
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MES_CORTO = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    var DIA = 86400000;
    var hoy = new Date(D.hoy);

    var ICON = {
        users: '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        money: '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        alert: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        book: '<svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        up: '<svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>',
        down: '<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>',
        entrar: '<svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
        persona: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/><line x1="4" y1="4" x2="20" y2="20"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>',
        cartera: '<svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><line x1="16" y1="15" x2="18" y2="15"/></svg>',
        cupos: '<svg viewBox="0 0 24 24"><path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-6h6v6"/></svg>',
        mensaje: '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'
    };

    var sel = '';
    try { sel = localStorage.getItem(SEDE_KEY) || ''; } catch (e) {}
    if (sel && !sedePorId(sel)) sel = '';

    /* ── Utilidades ─────────────────────────────── */
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function num(n) { return Math.round(n).toLocaleString('es-CO'); }
    function plata(n) { return '$ ' + num(n); }
    function plataCorta(n) {
        if (n >= 1000000) return '$ ' + (Math.round(n / 100000) / 10).toLocaleString('es-CO') + ' M';
        if (n >= 1000) return '$ ' + Math.round(n / 1000) + ' mil';
        return '$ ' + num(n);
    }
    function sedePorId(id) { for (var i = 0; i < D.sedes.length; i++) if (String(D.sedes[i].id) === String(id)) return D.sedes[i]; return null; }
    function colorDe(s) { return s ? 'gen-sede-c' + ((D.sedes.indexOf(s) % 8) + 1) : 'gen-sede-c0'; }
    function visibles() { return sel ? [sedePorId(sel)] : D.sedes; }
    function suma(lista, fn) { return lista.reduce(function (t, s) { return t + fn(s); }, 0); }
    function iniciales(nombre) {
        var p = String(nombre || '').trim().split(/\s+/);
        return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase() || '·';
    }
    function hace(iso) {
        var ms = hoy - new Date(iso), min = Math.round(ms / 60000);
        if (min < 60) return 'Hace ' + Math.max(min, 1) + ' min';
        var h = Math.round(min / 60);
        if (h < 24) return 'Hace ' + h + (h === 1 ? ' hora' : ' horas');
        var d = Math.floor(ms / DIA);
        if (d === 1) return 'Ayer';
        if (d < 7) return 'Hace ' + d + ' días';
        if (d < 30) { var s = Math.floor(d / 7); return 'Hace ' + s + (s === 1 ? ' semana' : ' semanas'); }
        var m = Math.floor(d / 30); return 'Hace ' + m + (m === 1 ? ' mes' : ' meses');
    }
    function fechaLarga(iso) {
        var f = new Date(iso);
        return f.getDate() + ' de ' + MESES[f.getMonth()] + ' de ' + f.getFullYear() + ', ' +
            f.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' });
    }
    function diasDesde(iso) { return Math.floor((hoy - new Date(iso)) / DIA); }
    /* Los 6 meses de la gráfica: del más viejo al mes en curso */
    function meses() {
        var r = [];
        for (var i = 5; i >= 0; i--) {
            var f = new Date(hoy.getFullYear(), hoy.getMonth() - i, 1);
            r.push({ corto: MES_CORTO[f.getMonth()], largo: MESES[f.getMonth()] + ' de ' + f.getFullYear(), actual: i === 0 });
        }
        return r;
    }
    var MES_ACTUAL = MESES[hoy.getMonth()], MES_ANT = MESES[new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1).getMonth()];

    /* Variación: dir sigue el signo; tono dice si es bueno (menos mora es bueno) */
    function delta(actual, anterior, menosEsBueno, fmt) {
        var dif = actual - anterior;
        if (anterior === 0 && dif === 0) return { txt: 'igual', dir: '', tono: 'igual' };
        var txt = fmt ? fmt(Math.abs(dif)) : (anterior ? Math.round(Math.abs(dif) / anterior * 100) + ' %' : num(Math.abs(dif)));
        if (dif === 0) return { txt: 'igual', dir: '', tono: 'igual' };
        var bueno = menosEsBueno ? dif < 0 : dif > 0;
        return { txt: (dif > 0 ? '+' : '−') + txt, dir: dif > 0 ? 'up' : 'down', tono: bueno ? 'bien' : 'mal' };
    }

    /* ── Filtro ─────────────────────────────────── */
    var filtro = document.getElementById('gen-filtro');
    D.sedes.forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'gen-chip'; b.setAttribute('role', 'radio'); b.dataset.sede = s.id;
        b.innerHTML = '<span class="gen-chip-dot ' + colorDe(s) + '" aria-hidden="true"></span>' + esc(s.nombre);
        filtro.appendChild(b);
    });
    filtro.addEventListener('click', function (e) {
        var b = e.target.closest('.gen-chip');
        if (b) elegir(b.dataset.sede);
    });
    filtro.addEventListener('keydown', function (e) {
        if (['ArrowRight', 'ArrowLeft'].indexOf(e.key) < 0) return;
        var chips = [].slice.call(filtro.querySelectorAll('.gen-chip'));
        var i = chips.indexOf(document.activeElement);
        if (i < 0) return;
        e.preventDefault();
        var n = chips[(i + (e.key === 'ArrowRight' ? 1 : chips.length - 1)) % chips.length];
        n.focus(); elegir(n.dataset.sede);
    });

    function elegir(id) {
        sel = id ? String(id) : '';
        try { localStorage.setItem(SEDE_KEY, sel); } catch (e) {}
        pintar();
    }

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var ss = visibles();
        var est = suma(ss, function (s) { return s.estudiantes; }), estAnt = suma(ss, function (s) { return s.estudiantesAnt; });
        var ing = suma(ss, function (s) { return s.ingresos[5]; }), ingAnt = suma(ss, function (s) { return s.ingresoMesAntAlDia; });
        var cerro = suma(ss, function (s) { return s.ingresos[4]; });
        var mora = suma(ss, function (s) { return s.mora.valor; }), moraAnt = suma(ss, function (s) { return s.moraAnt.valor; });
        var moraEst = suma(ss, function (s) { return s.mora.estudiantes; });
        var gr = suma(ss, function (s) { return s.grupos; }), grAnt = suma(ss, function (s) { return s.gruposAnt; });
        var porIniciar = suma(ss, function (s) { return s.gruposPorIniciar; });
        var donde = sel ? 'en ' + ss[0].nombre : 'en ' + ss.length + ' sedes';

        var kpis = [
            { color: 'blue', icon: 'users', label: 'Estudiantes activos', valor: num(est), d: delta(est, estAnt, false, num),
              titulo: 'Frente al cierre de ' + MES_ANT, foot: donde + ' · ' + num(estAnt) + ' al cierre de ' + MES_ANT },
            { color: 'green', icon: 'money', label: 'Ingresos del mes', valor: plata(ing), d: delta(ing, ingAnt, false),
              titulo: 'Frente a lo recaudado en ' + MES_ANT + ' hasta el mismo día',
              foot: 'Del 1 al ' + hoy.getDate() + ' de ' + MES_ACTUAL + ' · ' + MES_ANT + ' cerró en ' + plata(cerro) },
            { color: 'red', icon: 'alert', label: 'Cartera vencida', valor: plata(mora), d: delta(mora, moraAnt, true),
              titulo: 'Frente al cierre de ' + MES_ANT, foot: moraEst + (moraEst === 1 ? ' estudiante en mora' : ' estudiantes en mora') + ' ' + donde },
            { color: 'orange', icon: 'book', label: 'Grupos en clases', valor: num(gr), d: delta(gr, grAnt, false, num),
              titulo: 'Frente al cierre de ' + MES_ANT, foot: porIniciar + (porIniciar === 1 ? ' grupo por iniciar' : ' grupos por iniciar') + ' ' + donde }
        ];

        document.getElementById('gen-kpis').innerHTML = kpis.map(function (k, i) {
            var flecha = k.d.dir === 'up' ? ICON.up : k.d.dir === 'down' ? ICON.down : '';
            return '<div class="gen-kpi gen-kpi-' + k.color + ' app-anim app-anim-d' + (i + 1) + '">' +
                '<div class="gen-kpi-head"><div class="gen-kpi-icon gen-kpi-icon-' + k.color + '">' + ICON[k.icon] + '</div>' +
                '<span class="gen-delta gen-delta-' + k.d.tono + '" title="' + esc(k.titulo) + '">' + flecha + esc(k.d.txt) + '</span></div>' +
                '<div class="gen-kpi-label">' + esc(k.label) + '</div>' +
                '<div class="gen-kpi-value">' + esc(k.valor) + '</div>' +
                '<div class="gen-kpi-foot">' + esc(k.foot) + '</div></div>';
        }).join('');
    }

    /* ── Comparación de sedes ───────────────────── */
    /* Cuentas de secretaría activas de la sede, la que entró más reciente primero */
    function secretariasDe(s) {
        return D.secretarias.filter(function (x) { return x.sede_id === s.id && x.activo; })
            .sort(function (a, b) { return new Date(b.ultimo_acceso || 0) - new Date(a.ultimo_acceso || 0); });
    }
    function nombreSec(x) { return x.nombres + ' ' + x.apellidos.split(' ')[0]; }
    function lugar(s) { return s.ciudad === s.nombre ? s.departamento : s.ciudad + ', ' + s.departamento; }
    function secretariaCelda(s) {
        var ss = secretariasDe(s);
        if (!ss.length) return '<span class="gen-badge gen-badge-mal">' + ICON.persona + 'Sin secretaría</span>';
        var x = ss[0], otras = ss.length > 1 ? ' <span class="gen-sub" style="display:inline">+' + (ss.length - 1) + '</span>' : '';
        var sub = !x.ultimo_acceso ? '<div class="gen-sub gen-sub-warn">Nunca ha ingresado</div>'
            : diasDesde(x.ultimo_acceso) >= 30 ? '<div class="gen-sub gen-sub-warn">No entra hace ' + diasDesde(x.ultimo_acceso) + ' días</div>'
            : '<div class="gen-sub">Último ingreso: ' + esc(hace(x.ultimo_acceso).toLowerCase()) + '</div>';
        return '<div class="gen-strong">' + esc(nombreSec(x)) + otras + '</div>' + sub;
    }
    function ocupacion(ocup, cupos) {
        var pct = cupos ? Math.round(ocup / cupos * 100) : 0;
        return '<div class="gen-ocup" title="' + num(ocup) + ' de ' + num(cupos) + ' cupos"><div class="gen-ocup-bar">' +
            '<span class="gen-ocup-fill' + (pct < 50 ? ' is-baja' : '') + '" style="width:' + pct + '%"></span></div>' +
            '<span class="gen-ocup-pct">' + pct + ' %</span></div>';
    }
    function pintarComparacion() {
        document.getElementById('gen-comp-body').innerHTML = D.sedes.map(function (s) {
            var pctMora = s.estudiantes ? Math.round(s.mora.estudiantes / s.estudiantes * 100) : 0;
            return '<tr tabindex="0" data-sede="' + esc(s.id) + '" class="' + (String(s.id) === sel ? 'is-sel' : '') + '" aria-selected="' + (String(s.id) === sel) + '">' +
                '<td><div class="gen-sede-cell"><span class="gen-dot ' + colorDe(s) + '" aria-hidden="true"></span><div>' +
                    '<div class="gen-strong">' + esc(s.nombre) + '</div><div class="gen-sub">' + esc(lugar(s)) + '</div></div></div></td>' +
                '<td>' + secretariaCelda(s) + '</td>' +
                '<td class="gen-num"><div class="gen-strong">' + num(s.estudiantes) + '</div><div class="gen-sub">' + s.grupos + (s.grupos === 1 ? ' grupo' : ' grupos') + ' en clases</div></td>' +
                '<td class="gen-num"><div class="gen-strong">' + plata(s.ingresos[5]) + '</div><div class="gen-sub">' + esc(MES_ANT) + ': ' + plata(s.ingresos[4]) + '</div></td>' +
                '<td class="gen-num"><div class="gen-strong">' + plata(s.mora.valor) + '</div><div class="gen-sub' + (pctMora >= 20 ? ' gen-sub-mal' : '') + '">' +
                    s.mora.estudiantes + ' en mora · ' + pctMora + ' %</div></td>' +
                '<td>' + ocupacion(s.ocupados, s.cupos) + '</td>' +
                '<td><button type="button" class="gen-btn gen-btn-ghost gen-btn-sm" data-entrar="' + esc(s.id) + '">' + ICON.entrar + 'Entrar</button></td></tr>';
        }).join('');

        var t = D.sedes;
        var est = suma(t, function (s) { return s.estudiantes; });
        var moraEst = suma(t, function (s) { return s.mora.estudiantes; });
        document.getElementById('gen-comp-foot').innerHTML = '<tr>' +
            '<td>Total ESAT</td><td>' + t.filter(function (s) { return secretariasDe(s).length; }).length + ' de ' + t.length + ' sedes con secretaría</td>' +
            '<td class="gen-num">' + num(est) + '</td>' +
            '<td class="gen-num">' + plata(suma(t, function (s) { return s.ingresos[5]; })) + '</td>' +
            '<td class="gen-num">' + plata(suma(t, function (s) { return s.mora.valor; })) + '<div class="gen-sub">' + moraEst + ' en mora · ' + (est ? Math.round(moraEst / est * 100) : 0) + ' %</div></td>' +
            '<td>' + ocupacion(suma(t, function (s) { return s.ocupados; }), suma(t, function (s) { return s.cupos; })) + '</td><td></td></tr>';
    }
    var compBody = document.getElementById('gen-comp-body');
    compBody.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-entrar]');
        if (btn) { e.stopPropagation(); abrirEntrar(btn.dataset.entrar); return; }
        var tr = e.target.closest('tr[data-sede]');
        if (tr) elegir(tr.dataset.sede === sel ? '' : tr.dataset.sede);
    });
    compBody.addEventListener('keydown', function (e) {
        if (e.target.matches('tr[data-sede]') && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            var id = e.target.dataset.sede;
            elegir(id === sel ? '' : id);
            var fila = compBody.querySelector('tr[data-sede="' + id + '"]');
            if (fila) fila.focus();
        }
    });

    /* ── Ingresos de 6 meses ────────────────────── */
    var M = meses();
    var tip = document.getElementById('gen-tip');
    var chart = document.getElementById('gen-chart');

    function escalaMax(v) {
        if (v <= 0) return 1000000;
        var p = Math.pow(10, Math.floor(Math.log10(v))), n = v / p;
        var paso = n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10;
        return paso * p;
    }
    function pintarIngresos() {
        var ss = visibles();
        var totales = M.map(function (m, i) { return suma(ss, function (s) { return s.ingresos[i]; }); });
        var max = escalaMax(Math.max.apply(null, totales));

        document.getElementById('gen-ing-sub').textContent = sel
            ? 'Lo recaudado por mes en ' + ss[0].nombre + ', sin recibos anulados'
            : 'Lo recaudado por mes en cada sede, sin recibos anulados';

        var leyenda = document.getElementById('gen-legend');
        leyenda.hidden = ss.length < 2;
        leyenda.innerHTML = ss.length < 2 ? '' : ss.map(function (s) {
            return '<span class="gen-legend-item"><span class="gen-legend-sw ' + colorDe(s) + '" aria-hidden="true"></span>' + esc(s.nombre) + '</span>';
        }).join('');

        var lineas = '';
        for (var g = 0; g <= 4; g++) {
            var v = max * g / 4;
            lineas += '<div class="gen-grid-line" style="bottom:' + (g * 25) + '%"><span>' + esc(g === 0 ? '$ 0' : plataCorta(v)) + '</span></div>';
        }
        document.getElementById('gen-chart-grid').innerHTML = lineas;

        document.getElementById('gen-chart-cols').innerHTML = M.map(function (m, i) {
            var alto = totales[i] / max * 100;
            var segs = ss.map(function (s) {
                var h = totales[i] ? s.ingresos[i] / totales[i] * 100 : 0;
                return '<span class="gen-seg ' + colorDe(s) + '" style="height:' + h + '%"></span>';
            }).join('');
            return '<button type="button" class="gen-col' + (m.actual ? ' is-actual' : '') + '" data-mes="' + i + '" aria-label="' +
                esc(m.largo + ': ' + plata(totales[i]) + (m.actual ? ', mes en curso' : '')) + '">' +
                '<span class="gen-col-area"><span class="gen-stack" style="height:' + alto + '%; animation-delay:' + (i * 0.05) + 's">' + segs + '</span></span>' +
                '<span class="gen-col-lbl">' + esc(m.corto) + (m.actual ? ' · en curso' : '') + '</span></button>';
        }).join('');

        var cerrados = totales.slice(0, 5), mejor = cerrados.indexOf(Math.max.apply(null, cerrados));
        var semestre = totales.reduce(function (a, b) { return a + b; }, 0);
        document.getElementById('gen-chart-foot').innerHTML =
            '<div><strong>' + esc(plata(semestre)) + '</strong>en los 6 meses</div>' +
            '<div><strong>' + esc(plata(Math.round(cerrados.reduce(function (a, b) { return a + b; }, 0) / cerrados.length))) + '</strong>promedio por mes cerrado</div>' +
            '<div><strong>' + esc(M[mejor].corto) + '</strong>el mejor mes (' + esc(plata(cerrados[mejor])) + ')</div>';
    }
    function mostrarTip(col) {
        var i = +col.dataset.mes, ss = visibles(), m = M[i];
        var total = suma(ss, function (s) { return s.ingresos[i]; });
        var filas = ss.length < 2 ? '' : ss.slice().reverse().map(function (s) {
            return '<div class="gen-tip-fila"><span class="gen-legend-sw ' + colorDe(s) + '"></span><span>' + esc(s.nombre) + '</span><strong>' + esc(plata(s.ingresos[i])) + '</strong></div>';
        }).join('');
        tip.innerHTML = '<div class="gen-tip-mes">' + esc(m.largo.charAt(0).toUpperCase() + m.largo.slice(1)) + (m.actual ? ' · hasta hoy' : '') + '</div>' + filas +
            '<div class="gen-tip-fila' + (filas ? ' gen-tip-total' : '') + '"><span></span><span>' + (ss.length < 2 ? esc(ss[0].nombre) : 'Total') + '</span><strong>' + esc(plata(total)) + '</strong></div>';
        tip.hidden = false;
        [].forEach.call(chart.querySelectorAll('.gen-col'), function (c) { c.classList.toggle('is-hover', c === col); });
        var cr = chart.getBoundingClientRect(), r = col.getBoundingClientRect();
        // Al lado de la columna (derecha si cabe, si no izquierda) para no tapar la barra
        var x = r.right - cr.left + 8;
        if (x + tip.offsetWidth > cr.width) x = r.left - cr.left - tip.offsetWidth - 8;
        x = Math.max(0, x);
        tip.style.left = x + 'px';
        tip.style.top = '4px';
    }
    function ocultarTip() {
        tip.hidden = true;
        [].forEach.call(chart.querySelectorAll('.gen-col'), function (c) { c.classList.remove('is-hover'); });
    }
    chart.addEventListener('mouseover', function (e) { var c = e.target.closest('.gen-col'); if (c) mostrarTip(c); });
    chart.addEventListener('mouseleave', ocultarTip);
    chart.addEventListener('focusin', function (e) { var c = e.target.closest('.gen-col'); if (c) mostrarTip(c); });
    chart.addEventListener('focusout', ocultarTip);
    chart.addEventListener('click', function (e) { var c = e.target.closest('.gen-col'); if (c) mostrarTip(c); });

    /* ── Alertas ────────────────────────────────── */
    var NIVEL = { critica: 'Urgente', seria: 'Importante', aviso: 'Revisar', info: 'Para saber' };
    var ORDEN = { critica: 0, seria: 1, aviso: 2, info: 3 };
    function alertas() {
        var r = [];
        visibles().forEach(function (s) {
            var ss = secretariasDe(s);
            if (!ss.length) {
                r.push({ nivel: 'critica', icon: 'persona', sede: s, titulo: s.nombre + ' no tiene secretaría',
                    meta: 'Las matrículas y los pagos de la sede los tienes que registrar tú entrando a la sede. Crea o activa una cuenta de secretaría.' });
            }
            ss.forEach(function (x) {
                if (x.ultimo_acceso && diasDesde(x.ultimo_acceso) >= 30) {
                    r.push({ nivel: 'aviso', icon: 'reloj', sede: s, titulo: nombreSec(x) + ' no entra hace ' + diasDesde(x.ultimo_acceso) + ' días',
                        meta: 'Secretaría de ' + s.nombre + '. Si ya no trabaja en la sede, desactiva su cuenta.' });
                }
            });
            var pctMora = s.estudiantes ? Math.round(s.mora.estudiantes / s.estudiantes * 100) : 0;
            if (pctMora >= 20) {
                r.push({ nivel: 'seria', icon: 'cartera', sede: s, titulo: s.nombre + ': ' + pctMora + ' % de los estudiantes en mora',
                    meta: s.mora.estudiantes + ' estudiantes deben ' + plata(s.mora.valor) + ' vencidos.' });
            }
            var pct = s.cupos ? Math.round(s.ocupados / s.cupos * 100) : 0;
            if (pct < 50) {
                r.push({ nivel: 'aviso', icon: 'cupos', sede: s, titulo: s.nombre + ' tiene ocupado el ' + pct + ' % de sus cupos',
                    meta: num(s.ocupados) + ' de ' + num(s.cupos) + ' cupos en los grupos vigentes.' });
            } else if (pct >= 95) {
                r.push({ nivel: 'info', icon: 'cupos', sede: s, titulo: s.nombre + ' está casi llena (' + pct + ' %)',
                    meta: 'Quedan ' + (s.cupos - s.ocupados) + ' cupos en los grupos vigentes.' });
            }
        });
        D.comunicados.filter(function (c) { return c.estado === 'programado' && new Date(c.publicado_en) > hoy; }).forEach(function (c) {
            var todas = c.sedes === 'todas';
            if (sel && !todas && c.sedes.map(String).indexOf(sel) < 0) return;
            var s = !todas && c.sedes.length === 1 ? sedePorId(c.sedes[0]) : null;
            var para = todas ? 'Para todas las sedes' : 'Para ' + c.sedes.map(function (id) { var x = sedePorId(id); return x ? x.nombre : ''; }).join(', ');
            var dias = Math.max(0, Math.ceil((new Date(c.publicado_en) - hoy) / DIA));
            r.push({ nivel: 'info', icon: 'mensaje', sede: s, titulo: 'Comunicado programado: «' + c.titulo + '»',
                meta: para + ' · sale ' + (dias === 0 ? 'hoy' : dias === 1 ? 'mañana' : 'en ' + dias + ' días') + '.' });
        });
        return r.sort(function (a, b) { return ORDEN[a.nivel] - ORDEN[b.nivel]; });
    }
    var ALERTAS_VISIBLES = 3, todasAlertas = false;
    function pintarAlertas() {
        var a = alertas(), urgentes = a.filter(function (x) { return x.nivel !== 'info'; }).length;
        var cnt = document.getElementById('gen-al-count');
        cnt.hidden = !urgentes; cnt.textContent = urgentes;
        cnt.title = urgentes + (urgentes === 1 ? ' alerta por atender' : ' alertas por atender');
        var mas = document.getElementById('gen-al-mas'), resto = a.length - ALERTAS_VISIBLES;
        mas.hidden = resto <= 0;
        mas.textContent = todasAlertas ? 'Ver menos' : 'Ver ' + resto + (resto === 1 ? ' alerta más' : ' alertas más');
        if (!todasAlertas) a = a.slice(0, ALERTAS_VISIBLES);
        document.getElementById('gen-alerts').innerHTML = a.length ? a.map(function (x) {
            var accion = x.sede && !sel ? '<button type="button" class="gen-alert-accion" data-ver="' + esc(x.sede.id) + '">Ver ' + esc(x.sede.nombre) + '</button>' : '';
            return '<li class="gen-alert gen-alert-' + x.nivel + '"><div class="gen-alert-ico" aria-hidden="true">' + ICON[x.icon] + '</div>' +
                '<div class="gen-alert-body"><div class="gen-alert-nivel">' + NIVEL[x.nivel] + '</div>' +
                '<div class="gen-alert-titulo">' + esc(x.titulo) + '</div><div class="gen-alert-meta">' + esc(x.meta) + '</div>' + accion + '</div></li>';
        }).join('') : '<li class="gen-vacio">Todo en orden: no hay alertas.</li>';
    }
    document.getElementById('gen-al-mas').addEventListener('click', function () { todasAlertas = !todasAlertas; pintarAlertas(); });
    document.getElementById('gen-alerts').addEventListener('click', function (e) {
        var b = e.target.closest('[data-ver]');
        if (!b) return;
        elegir(b.dataset.ver);
        document.getElementById('gen-comp-titulo').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /* ── Actividad reciente ─────────────────────── */
    var POR_PAGINA = 8, mostrar = POR_PAGINA;
    function pintarActividad() {
        var lista = D.auditoria.filter(function (a) { return !sel || String(a.sede_id) === sel; })
            .sort(function (a, b) { return new Date(b.fecha) - new Date(a.fecha); });
        document.getElementById('gen-act-sub').textContent = sel
            ? 'Lo último que se hizo en la sede ' + sedePorId(sel).nombre
            : 'Lo último que se hizo en las sedes';
        var ul = document.getElementById('gen-act-list');
        ul.innerHTML = lista.length ? lista.slice(0, mostrar).map(function (a) {
            var s = sedePorId(a.sede_id), i = D.auditoria.indexOf(a);
            return '<li><button type="button" class="gen-act-item" data-act="' + i + '">' +
                '<span class="gen-act-av ' + colorDe(s) + '" aria-hidden="true">' + esc(iniciales(a.quien)) + '</span>' +
                '<span class="gen-act-text"><span class="gen-act-linea"><strong>' + esc(a.quien) + '</strong> · ' + esc(a.texto) + '</span>' +
                '<span class="gen-act-meta">' + esc(s ? s.nombre : 'Todas las sedes') + (a.rol === 'admin' ? ' · Admin' : '') + ' · ' + esc(hace(a.fecha)) + '</span></span></button></li>';
        }).join('') : '<li class="gen-vacio">Todavía no hay actividad registrada.</li>';
        var mas = document.getElementById('gen-act-mas');
        mas.hidden = lista.length <= mostrar;
    }
    document.getElementById('gen-act-mas').addEventListener('click', function () { mostrar += POR_PAGINA; pintarActividad(); });

    /* ── Modales ────────────────────────────────── */
    var abierto = null, volverA = null;
    function abrir(id) {
        volverA = document.activeElement;
        abierto = document.getElementById(id);
        abierto.hidden = false;
        var f = abierto.querySelector('[data-cerrar]');
        if (f) f.focus();
    }
    function cerrar() {
        if (!abierto) return;
        abierto.hidden = true; abierto = null;
        if (volverA && document.contains(volverA)) volverA.focus();
    }
    [].forEach.call(document.querySelectorAll('.gen-modal-overlay'), function (o) {
        o.addEventListener('click', function (e) { if (e.target === o || e.target.closest('[data-cerrar]')) cerrar(); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });

    document.getElementById('gen-act-list').addEventListener('click', function (e) {
        var b = e.target.closest('[data-act]');
        if (!b) return;
        var a = D.auditoria[+b.dataset.act], s = sedePorId(a.sede_id);
        var dot = document.getElementById('gen-act-modal-dot');
        dot.className = 'gen-modal-dot ' + colorDe(s);
        dot.textContent = iniciales(a.quien);
        document.getElementById('gen-act-modal-titulo').textContent = a.quien;
        document.getElementById('gen-act-modal-sede').textContent = ({ admin: 'Admin', secretaria: 'Secretaría', docente: 'Docente' }[a.rol] || 'Secretaría') + ' · ' + (s ? 'Sede ' + s.nombre : 'Todas las sedes');
        document.getElementById('gen-act-modal-text').textContent = a.texto;
        document.getElementById('gen-act-modal-time').textContent = fechaLarga(a.fecha) + ' · ' + hace(a.fecha);
        abrir('gen-act-modal');
    });


    /* ── Entrar a la sede: trabaja con las pantallas de la secretaría (EntrarSedeController) ── */
    function entrarASede(id, destino) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = '/general/sedes/' + encodeURIComponent(id) + '/entrar';
        [['_token', document.querySelector('meta[name="csrf-token"]').content], ['destino', destino || 'dashboard']].forEach(function (c) {
            var i = document.createElement('input'); i.type = 'hidden'; i.name = c[0]; i.value = c[1]; f.appendChild(i);
        });
        document.body.appendChild(f); f.submit();
    }

    var entrarSede = null;
    function abrirEntrar(id) {
        entrarSede = sedePorId(id);
        document.getElementById('gen-entrar-titulo').textContent = 'Entrar a ' + entrarSede.nombre;
        document.getElementById('gen-entrar-sub').textContent = entrarSede.ciudad + ' · ' +
            (secretariasDe(entrarSede).length ? 'Secretaría: ' + nombreSec(secretariasDe(entrarSede)[0]) : 'Sin secretaría');
        abrir('gen-entrar-modal');
    }
    document.getElementById('gen-entrar-ok').addEventListener('click', function () {
        this.disabled = true;
        entrarASede(entrarSede.id, 'dashboard');
    });

    /* ── Pintar ─────────────────────────────────── */
    function pintar() {
        [].forEach.call(filtro.querySelectorAll('.gen-chip'), function (c) {
            var on = (c.dataset.sede || '') === sel;
            c.classList.toggle('is-active', on);
            c.setAttribute('aria-checked', on);
            c.tabIndex = on ? 0 : -1;
        });
        document.getElementById('gen-welcome-desc').textContent = sel
            ? 'Así va la sede ' + sedePorId(sel).nombre + ' hoy: estudiantes, plata y lo que necesita atención.'
            : 'Así va ESAT hoy en sus ' + D.sedes.length + ' sedes: estudiantes, plata y lo que necesita atención.';
        mostrar = POR_PAGINA; todasAlertas = false;
        ocultarTip();
        pintarKpis();
        pintarComparacion();
        pintarIngresos();
        pintarAlertas();
        pintarActividad();
    }
    pintar();
});
