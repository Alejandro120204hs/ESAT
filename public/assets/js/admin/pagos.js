/* ESAT — Admin: Pagos (Finanzas). Datos de CarteraService; registrar y anular un pago van a las rutas de
   Matrículas (PagoRequest / AnularPagoRequest + PagoService). Las cuentas de aquí siguen las mismas reglas:
   el pago se aplica a lo más viejo primero (el derecho en espera al final), lo que no alcanza queda abonado,
   un recibo por pago y no se recibe más de lo que se debe. */
(function () {
    'use strict';

    var D = window.PAG_DATA;
    var HOY = D.hoy;
    var MES_HOY = HOY.slice(0, 7);
    var VISTA_KEY = 'esat.admin.pagos.vista';
    var AVISO_KEY = 'esat.admin.pagos.aviso', ABRIR_KEY = 'esat.admin.pagos.abrir';
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var MESES_L = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var SITUACION = {
        vencido:  ['app-status-danger', 'Vencido'],
        mes:      ['app-status-warn', 'Debe este mes'],
        sin_plan: ['app-status-warn', 'Sin plan de pago'],
        al_dia:   ['app-status-active', 'Al día']
    };
    var ESTADO_MAT = { aplazado: 'Aplazada', retirado: 'Retirada', graduado: 'Graduada' };
    var COBRO_TAG = { pagado: ['app-status-active', 'Pagado'], pendiente: ['app-status-muted', 'Pendiente'], vencido: ['app-status-danger', 'Vencido'] };

    /* ── Datos: cobros como objetos y recaudos con su matrícula ── */
    var mats = D.matriculas.map(function (m) {
        m.cobros = m.cobros.map(function (c) {
            return { id: c[0], tipo: c[1], concepto: c[2], vence: c[3], valor: c[4], abonado: c[5], estado: c[6] };
        }).filter(function (c) { return c.estado !== 'anulado'; });
        return m;
    });
    var matPorId = {};
    mats.forEach(function (m) { matPorId[m.id] = m; });
    var recaudos = D.recaudos.map(function (r) {
        r.cubre = r.cubre.map(function (x) { return { id: x[0], concepto: x[1], monto: x[2] }; });
        return r;
    });
    var devoluciones = D.devoluciones;

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function miles(v) { return String(Math.round(v || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function pesos(v) { return '$ ' + miles(v); }
    function corta(f) {
        if (!f) return '—';
        var p = f.split('-');
        return +p[2] + ' ' + MESES[+p[1] - 1] + (p[0] !== HOY.slice(0, 4) ? ' ' + p[0] : '');
    }
    function larga(f) { var p = f.split('-'); return +p[2] + ' de ' + MESES_L[+p[1] - 1] + ' de ' + p[0]; }
    function diasEntre(a, b) { return Math.round((Date.parse(b) - Date.parse(a)) / 86400000); }
    function finDeMes(ym) { var p = ym.split('-'); var d = new Date(+p[0], +p[1], 0).getDate(); return ym + '-' + (d < 10 ? '0' : '') + d; }
    function mesAnterior(ym) { var p = ym.split('-'), y = +p[0], m = +p[1] - 1; if (m === 0) { m = 12; y--; } return y + '-' + (m < 10 ? '0' : '') + m; }
    function nombreMes(ym) { return MESES_L[+ym.slice(5, 7) - 1]; }
    function primerNombre(n) { return String(n || '').split(' ')[0]; }
    function iniciales(m) { return ((m.nombres || '').charAt(0) + (m.apellidos || '').charAt(0)).toUpperCase(); }
    function nombre(m) { return m.nombres + ' ' + m.apellidos; }
    function corto(p) { return String(p || '').replace('Técnico Laboral en ', ''); }
    function tag(par) { return '<span class="app-status-tag ' + par[0] + '">' + esc(par[1]) + '</span>'; }
    function metodo(m) { return '<span class="pag-metodo"><i class="m-' + esc(String(m).toLowerCase()) + '"></i>' + esc(m) + '</span>'; }
    function waLink(tel, texto) { return 'https://wa.me/57' + String(tel || '').replace(/\D/g, '') + '?text=' + encodeURIComponent(texto); }
    var FIN_MES = finDeMes(MES_HOY);
    var ICON = {
        wa: '<svg viewBox="0 0 24 24"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        ok: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        plus: '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        print: '<svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
        x: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        user: '<svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        ojo: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        recibo: '<svg viewBox="0 0 24 24"><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2l-3 2-3-2-3 2-3-2-3 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="14" y2="13"/></svg>',
        devol: '<svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>'
    };

    var toastEl = document.getElementById('pag-toast');
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

    /* ── Servidor (mismo patrón que Matrículas) ── */
    function enviar(metodo, url, cuerpo) {
        return fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) {
                    var err = new Error(r.status === 419 ? 'La sesión se venció. Recarga la página e intenta de nuevo.' : (j.message || 'No se pudo guardar. Intenta de nuevo.'));
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
    /* Guarda el aviso (y qué abrir) y recarga: tablas, KPI y caja salen del servidor */
    function recargarConAviso(msg, abrir) {
        try {
            sessionStorage.setItem(AVISO_KEY, msg);
            if (abrir) sessionStorage.setItem(ABRIR_KEY, JSON.stringify(abrir));
        } catch (e) {}
        location.reload();
    }

    /* ── Reglas de cobro (las mismas de PagoService::resumen) ── */
    function porPagar(c) { return c.estado === 'pendiente' || c.estado === 'vencido'; }
    function restante(c) { return porPagar(c) ? Math.max(0, c.valor - c.abonado) : 0; }
    function estadoCobro(c) { return c.estado === 'pendiente' && c.vence && c.vence < HOY ? 'vencido' : c.estado; }
    /* Orden en que se paga: por vencimiento y al final el derecho en espera (sin fecha) */
    function enOrden(m) {
        return m.cobros.filter(function (c) { return restante(c) > 0; }).sort(function (a, b) {
            return (a.vence ? 0 : 1) - (b.vence ? 0 : 1) || (a.vence || '').localeCompare(b.vence || '') || a.id - b.id;
        });
    }
    function resumen(m) {
        var debe = m.cobros.filter(function (c) { return restante(c) > 0; });
        var venc = debe.filter(function (c) { return estadoCobro(c) === 'vencido'; });
        var mes = debe.filter(function (c) { return c.vence && c.vence <= FIN_MES; });
        var suma = function (l) { return l.reduce(function (s, c) { return s + restante(c); }, 0); };
        var propios = recaudos.filter(function (r) { return r.matricula_id === m.id && !r.anulado; });
        var masViejo = venc.reduce(function (min, c) { return !min || c.vence < min ? c.vence : min; }, null);
        var derecho = venc.find(function (c) { return c.tipo === 'matricula'; });
        var vigente = m.estado === 'activo' || m.estado === 'aplazado';
        var r = {
            vencido: suma(venc), mes: suma(mes), total: suma(debe),
            pagado: m.cobros.reduce(function (s, c) { return s + c.abonado; }, 0) + (m.saldo_favor || 0),
            ultimo: propios.reduce(function (u, x) { return !u || x.fecha > u.fecha ? x : u; }, null),
            dias_mora: masViejo ? diasEntre(masViejo, HOY) : 0,
            derecho_vencido: !!derecho && vigente,
            vigente: vigente
        };
        r.situacion = r.vencido > 0 ? 'vencido' : (r.mes > 0 ? 'mes' : (vigente && !m.plan ? 'sin_plan' : 'al_dia'));
        return r;
    }
    /* En "Por cobrar": las matrículas vigentes y las cerradas que todavía deben algo */
    function enCartera(m) { var r = resumen(m); return r.vigente || r.total > 0; }

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
            root.opciones = opciones;
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
    function valor(id) { return document.getElementById(id).value; }
    function busca(id) { return (document.getElementById(id).value || '').toLowerCase().trim(); }

    /* ── Lista paginada (la arma JS para que cambie al registrar o anular) ── */
    function Lista(cfg) {
        var tbody = document.getElementById(cfg.body);
        var countEl = document.getElementById(cfg.count);
        var emptyEl = document.getElementById(cfg.empty);
        var pagEl = document.getElementById(cfg.pag);
        var PAGE = 10, pagina = 1, items = [];

        function aplicar(mantener) {
            items = cfg.datos();
            if (!mantener) pagina = 1;
            pagina = Math.min(pagina, Math.max(1, Math.ceil(items.length / PAGE)));
            pintar();
        }
        function pintar() {
            var total = items.length, paginas = Math.max(1, Math.ceil(total / PAGE));
            var ini = (pagina - 1) * PAGE;
            tbody.innerHTML = items.slice(ini, ini + PAGE).map(cfg.fila).join('');
            countEl.textContent = total + ' ' + (total === 1 ? cfg.nombre[0] : cfg.nombre[1]);
            emptyEl.hidden = total > 0;
            pagEl.hidden = paginas <= 1;
            if (paginas <= 1) { pagEl.innerHTML = ''; return; }
            var html = '<button type="button" class="pag-pg-btn" data-pg="prev"' + (pagina === 1 ? ' disabled' : '') + '><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Anterior</button>';
            rango(pagina, paginas).forEach(function (p) {
                html += p === '…' ? '<span class="pag-pg-ellipsis">…</span>'
                    : '<button type="button" class="pag-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            });
            html += '<button type="button" class="pag-pg-btn" data-pg="next"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg></button>';
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
            var paginas = Math.ceil(items.length / PAGE);
            var v = b.dataset.pg;
            pagina = v === 'prev' ? Math.max(1, pagina - 1) : (v === 'next' ? Math.min(paginas, pagina + 1) : +v);
            pintar();
            tbody.closest('.pag-table-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        tbody.addEventListener('click', function (e) {
            var row = e.target.closest('.pag-row');
            if (row) cfg.abrir(+row.dataset.id);
        });
        return { aplicar: aplicar, items: function () { return items; } };
    }

    /* ═══════════ Por cobrar ═══════════ */
    var ORDEN = {
        vencido: function (a, b) { return (b.r.vencido - a.r.vencido) || (b.r.dias_mora - a.r.dias_mora) || (b.r.mes - a.r.mes); },
        mes:     function (a, b) { return (b.r.mes - a.r.mes) || (b.r.total - a.r.total); },
        nombre:  function (a, b) { return (a.m.apellidos + ' ' + a.m.nombres).localeCompare(b.m.apellidos + ' ' + b.m.nombres, 'es'); }
    };
    var tCobrar = Lista({
        body: 'pag-c-body', count: 'pag-c-count', empty: 'pag-c-empty', pag: 'pag-c-pagination', nombre: ['estudiante', 'estudiantes'],
        abrir: function (id) { abrirEstudiante(id); },
        datos: function () {
            var q = busca('pag-c-search'), p = valor('pag-c-programa'), s = valor('pag-c-situacion');
            return mats.filter(enCartera).map(function (m) { return { m: m, r: resumen(m) }; }).filter(function (x) {
                var m = x.m;
                return (!q || (nombre(m) + ' ' + m.documento + ' ' + m.codigo).toLowerCase().indexOf(q) !== -1) &&
                    (!p || String(m.programa_id) === p) && (!s || x.r.situacion === s);
            }).sort(ORDEN[valor('pag-c-orden') || 'vencido']);
        },
        fila: function (x) {
            var m = x.m, r = x.r;
            var cerrada = ESTADO_MAT[m.estado] ? ' <span class="app-status-tag app-status-muted">' + ESTADO_MAT[m.estado] + '</span>' : '';
            return '<tr class="pag-row" data-id="' + m.id + '">' +
                '<td><div class="pag-person-cell"><div class="pag-avatar pag-esc-' + esc(m.sigla) + '">' + esc(iniciales(m)) + '</div><div>' +
                    '<div class="pag-person-name">' + esc(nombre(m)) + '</div><div class="pag-person-sub">' + esc(m.tipo_doc + ' ' + m.documento) + '</div></div></div></td>' +
                '<td><div class="pag-prog">' + esc(corto(m.programa)) + '</div><div class="pag-person-sub">' + esc((m.grupo || 'Sin grupo') + (m.jornada ? ' · ' + m.jornada : '')) + '</div></td>' +
                '<td class="num">' + monto(r.vencido, 'is-rojo') + (r.vencido ? '<div class="pag-person-sub">hace ' + r.dias_mora + ' días</div>' : '') + '</td>' +
                '<td class="num">' + monto(r.mes) + '</td>' +
                '<td class="num">' + monto(r.total) + '</td>' +
                '<td><div class="pag-tags">' + tag(SITUACION[r.situacion]) + cerrada + '</div></td>' +
                '<td class="pag-actions-cell"><button type="button" class="pag-icon-btn" title="Ver pagos" aria-label="Ver pagos de ' + esc(nombre(m)) + '">' + ICON.ojo + '</button></td></tr>';
        }
    });
    function monto(v, claseSiHay) { return '<span class="pag-monto ' + (v > 0 ? (claseSiHay || '') : 'is-nada') + '">' + (v > 0 ? pesos(v) : '—') + '</span>'; }

    var programas = [];
    mats.forEach(function (m) { if (!programas.some(function (p) { return p.id === m.programa_id; })) programas.push({ id: m.programa_id, nombre: m.programa, sigla: m.sigla }); });
    programas.sort(function (a, b) { return a.nombre.localeCompare(b.nombre, 'es'); });
    var cbPrograma = filtroCombo('pag-c-cb-programa', [{ value: '', label: 'Todos los programas' }].concat(programas.map(function (p) {
        return { value: String(p.id), label: corto(p.nombre) };
    })), function () { tCobrar.aplicar(); });
    filtroCombo('pag-c-cb-situacion', [{ value: '', label: 'Todas las situaciones' }].concat(['vencido', 'mes', 'sin_plan', 'al_dia'].map(function (s) {
        return { value: s, label: SITUACION[s][1] };
    })), function () { tCobrar.aplicar(); });
    filtroCombo('pag-c-cb-orden', [
        { value: 'vencido', label: 'Más vencido primero' },
        { value: 'mes', label: 'Más por pagar este mes' },
        { value: 'nombre', label: 'Por apellido (A–Z)' }
    ], function () { tCobrar.aplicar(); });
    document.getElementById('pag-c-search').addEventListener('input', function () { tCobrar.aplicar(); });

    /* ═══════════ Pagos recibidos ═══════════ */
    function semestre(ym) { var y = ym.slice(0, 4); return +ym.slice(5, 7) <= 6 ? [y + '-01-01', y + '-06-30'] : [y + '-07-01', y + '-12-31']; }
    var PERIODOS = {
        mes:      function () { return [MES_HOY + '-01', FIN_MES]; },
        anterior: function () { var a = mesAnterior(MES_HOY); return [a + '-01', finDeMes(a)]; },
        semestre: function () { return semestre(MES_HOY); },
        anio:     function () { return [HOY.slice(0, 4) + '-01-01', HOY.slice(0, 4) + '-12-31']; },
        todo:     function () { return ['0000-01-01', '9999-12-31']; }
    };
    function cubreTxt(r) { return r.cubre.map(function (x) { return x.concepto; }).join(', '); }
    var tRecibidos = Lista({
        body: 'pag-r-body', count: 'pag-r-count', empty: 'pag-r-empty', pag: 'pag-r-pagination', nombre: ['pago', 'pagos'],
        abrir: function (id) { abrirRecibo(id); },
        datos: function () {
            var q = busca('pag-r-search'), rg = PERIODOS[valor('pag-r-periodo') || 'mes'](), me = valor('pag-r-metodo'), es = valor('pag-r-estado');
            return recaudos.filter(function (r) {
                var m = matPorId[r.matricula_id];
                return r.fecha >= rg[0] && r.fecha <= rg[1] && (!me || r.metodo === me) &&
                    (!es || (es === 'anulados' ? !!r.anulado : !r.anulado)) &&
                    (!q || (r.recibo + ' ' + nombre(m) + ' ' + m.documento + ' ' + (r.referencia || '')).toLowerCase().indexOf(q) !== -1);
            });
        },
        fila: function (r) {
            var m = matPorId[r.matricula_id];
            return '<tr class="pag-row" data-id="' + r.id + '">' +
                '<td><div class="pag-recibo">' + esc(r.recibo) + '</div><div class="pag-person-sub">' + corta(r.fecha) + '</div></td>' +
                '<td><div class="pag-person-cell"><div class="pag-avatar pag-esc-' + esc(m.sigla) + '">' + esc(iniciales(m)) + '</div><div>' +
                    '<div class="pag-person-name">' + esc(nombre(m)) + '</div><div class="pag-person-sub">' + esc(corto(m.programa)) + '</div></div></div></td>' +
                '<td><div class="pag-cubre" title="' + esc(cubreTxt(r)) + '">' + esc(cubreTxt(r)) + '</div></td>' +
                '<td>' + metodo(r.metodo) + (r.referencia ? '<div class="pag-person-sub">' + esc(r.referencia) + '</div>' : '') + '</td>' +
                '<td class="num"><span class="pag-monto' + (r.anulado ? ' is-tachado' : '') + '">' + pesos(r.valor) + '</span>' +
                    (r.anulado ? '<div><span class="app-status-tag app-status-danger">Anulado</span></div>' : '') + '</td>' +
                '<td class="pag-actions-cell"><button type="button" class="pag-icon-btn" title="Ver recibo" aria-label="Ver recibo ' + esc(r.recibo) + '">' + ICON.ojo + '</button></td></tr>';
        }
    });
    filtroCombo('pag-r-cb-periodo', [
        { value: 'mes', label: 'Este mes' }, { value: 'anterior', label: 'Mes anterior' }, { value: 'semestre', label: 'Este semestre' },
        { value: 'anio', label: 'Este año' }, { value: 'todo', label: 'Todas las fechas' }
    ], function () { tRecibidos.aplicar(); });
    filtroCombo('pag-r-cb-metodo', [{ value: '', label: 'Todos los medios' }].concat(D.metodos.map(function (x) { return { value: x, label: x }; })), function () { tRecibidos.aplicar(); });
    filtroCombo('pag-r-cb-estado', [{ value: '', label: 'Todos los pagos' }, { value: 'vigentes', label: 'Solo vigentes' }, { value: 'anulados', label: 'Solo anulados' }], function () { tRecibidos.aplicar(); });
    document.getElementById('pag-r-search').addEventListener('input', function () { tRecibidos.aplicar(); });

    /* ═══════════ Resumen de caja (por rango de fechas: no se recibe todos los días) ═══════════ */
    var caja = { rango: 'mes', desde: MES_HOY + '-01', hasta: HOY };
    var cajaEl = document.getElementById('pag-caja');
    var fechasEl = document.getElementById('pag-caja-fechas');
    var desdeEl = document.getElementById('pag-caja-desde'), hastaEl = document.getElementById('pag-caja-hasta');
    desdeEl.max = HOY; hastaEl.max = HOY;

    function rangoCaja() {
        if (caja.rango === 'otro') return [caja.desde, caja.hasta];
        var r = PERIODOS[caja.rango]();
        return [r[0], r[1] > HOY ? HOY : r[1]];
    }
    function textoRango(r) { return r[0] === r[1] ? larga(r[0]) : 'Del ' + larga(r[0]) + ' al ' + larga(r[1]); }
    function calcularCaja() {
        var r = rangoCaja();
        var pagos = recaudos.filter(function (x) { return !x.anulado && x.fecha >= r[0] && x.fecha <= r[1]; });
        var anulados = recaudos.filter(function (x) { return x.anulado && x.fecha >= r[0] && x.fecha <= r[1]; });
        var devs = devoluciones.filter(function (x) { return x.fecha >= r[0] && x.fecha <= r[1]; });
        var total = pagos.reduce(function (s, x) { return s + x.valor; }, 0);
        var salidas = devs.reduce(function (s, x) { return s + x.valor; }, 0);
        var agrupar = function (lista, clave) {
            var out = {};
            lista.forEach(function (x) { var k = clave(x); out[k] = out[k] || { clave: k, n: 0, valor: 0, efectivo: 0 }; out[k].n++; out[k].valor += x.valor; if (x.metodo === 'Efectivo') out[k].efectivo += x.valor; });
            return Object.keys(out).map(function (k) { return out[k]; });
        };
        return {
            rango: r, pagos: pagos, anulados: anulados, devs: devs, total: total, salidas: salidas,
            metodos: agrupar(pagos, function (x) { return x.metodo; }).sort(function (a, b) { return b.valor - a.valor; }),
            personas: agrupar(pagos, function (x) { return x.registrado; }).sort(function (a, b) { return b.valor - a.valor; }),
            dias: agrupar(pagos, function (x) { return x.fecha; }).sort(function (a, b) { return b.clave.localeCompare(a.clave); })
        };
    }
    function pintarCaja() {
        document.querySelectorAll('.pag-rango').forEach(function (b) {
            var on = b.dataset.rango === caja.rango;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        fechasEl.hidden = caja.rango !== 'otro';
        var c = calcularCaja();
        var html = '<div class="pag-caja"><div class="pag-panel"><div class="pag-panel-titulo">' + esc(textoRango(c.rango)) + '</div><div class="pag-datos">' +
            dato('Total recibido', pesos(c.total)) +
            dato('Pagos', c.pagos.length + (c.anulados.length ? ' <small>· ' + c.anulados.length + ' anulado' + (c.anulados.length === 1 ? '' : 's') + '</small>' : '')) +
            dato('Devoluciones', c.salidas ? '− ' + pesos(c.salidas) : pesos(0)) +
            dato('Queda en caja', pesos(c.total - c.salidas)) + '</div></div>';
        if (!c.pagos.length) {
            html += '<p class="pag-lista-vacia">No se recibió ningún pago en estas fechas.</p></div>';
            cajaEl.innerHTML = html;
            return;
        }
        html += '<div class="pag-caja-grid">' +
            panelTabla('Por medio de pago', ['Medio', 'Pagos', 'Valor', 'Del total'], c.metodos.map(function (x) {
                var pct = Math.round(x.valor * 100 / c.total);
                return '<tr><td>' + metodo(x.clave) + '</td><td class="num">' + x.n + '</td><td class="num"><span class="pag-monto">' + pesos(x.valor) + '</span></td>' +
                    '<td><div class="pag-meter-cell"><span class="pag-meter"><span style="width:' + pct + '%"></span></span><span class="pag-meter-pct">' + pct + ' %</span></div></td></tr>';
            })) +
            panelTabla('Quién registró', ['Persona', 'Pagos', 'Valor', 'En efectivo'], c.personas.map(function (x) {
                return '<tr><td class="pag-person-name">' + esc(x.clave) + '</td><td class="num">' + x.n + '</td><td class="num"><span class="pag-monto">' + pesos(x.valor) + '</span></td><td class="num">' + pesos(x.efectivo) + '</td></tr>';
            })) + '</div>' +
            panelTabla('Días en que entraron pagos', ['Fecha', 'Pagos', 'En efectivo', 'Otros medios', 'Total'], c.dias.map(function (x) {
                return '<tr><td>' + esc(larga(x.clave)) + '</td><td class="num">' + x.n + '</td><td class="num">' + pesos(x.efectivo) + '</td><td class="num">' + pesos(x.valor - x.efectivo) + '</td>' +
                    '<td class="num"><span class="pag-monto">' + pesos(x.valor) + '</span></td></tr>';
            })) + '</div>';
        cajaEl.innerHTML = html;
    }
    function dato(lbl, val) { return '<div class="pag-dato"><span class="pag-dato-lbl">' + lbl + '</span><span class="pag-dato-val">' + val + '</span></div>'; }
    function panelTabla(titulo, cols, filas) {
        return '<div class="pag-panel"><div class="pag-panel-titulo">' + titulo + '</div><div class="pag-table-wrap" style="border:none;border-radius:0"><table class="pag-table"><thead><tr>' +
            cols.map(function (c, i) { return '<th' + (i > 0 && c !== 'Del total' ? ' class="num"' : '') + '>' + c + '</th>'; }).join('') + '</tr></thead><tbody>' + filas.join('') + '</tbody></table></div></div>';
    }
    document.querySelectorAll('.pag-rango').forEach(function (b) {
        b.setAttribute('role', 'radio');
        b.addEventListener('click', function () {
            caja.rango = b.dataset.rango;
            if (caja.rango === 'otro') { desdeEl.value = caja.desde; hastaEl.value = caja.hasta; }
            pintarCaja();
        });
    });
    [desdeEl, hastaEl].forEach(function (el) {
        el.addEventListener('change', function () {
            if (!desdeEl.value || !hastaEl.value) return;
            if (desdeEl.value > hastaEl.value) { toast('La fecha "Desde" no puede ser después de "Hasta".'); desdeEl.value = caja.desde; hastaEl.value = caja.hasta; return; }
            caja.desde = desdeEl.value; caja.hasta = hastaEl.value;
            pintarCaja();
        });
    });

    /* ═══════════ Por programa ═══════════ */
    function pintarProgramas() {
        var filas = programas.map(function (p) {
            var suyas = mats.filter(function (m) { return m.programa_id === p.id && (m.estado === 'activo' || m.estado === 'aplazado'); });
            var t = { p: p, n: suyas.length, pagado: 0, cobrar: 0, vencido: 0 };
            suyas.forEach(function (m) { var r = resumen(m); t.pagado += r.pagado; t.cobrar += r.total; t.vencido += r.vencido; });
            return t;
        }).filter(function (t) { return t.n > 0; }).sort(function (a, b) { return b.vencido - a.vencido || b.cobrar - a.cobrar; });
        var pct = function (t) { var base = t.pagado + t.cobrar; return base ? Math.round(t.pagado * 100 / base) : 100; };
        document.getElementById('pag-p-body').innerHTML = filas.map(function (t) {
            return '<tr class="pag-row" data-programa="' + t.p.id + '">' +
                '<td><div class="pag-person-cell"><div class="pag-avatar pag-esc-' + esc(t.p.sigla) + '">' + esc(corto(t.p.nombre).charAt(0)) + '</div><div class="pag-person-name">' + esc(corto(t.p.nombre)) + '</div></div></td>' +
                '<td class="num">' + t.n + '</td><td class="num"><span class="pag-monto">' + pesos(t.pagado) + '</span></td>' +
                '<td class="num">' + monto(t.cobrar) + '</td><td class="num">' + monto(t.vencido, 'is-rojo') + '</td>' +
                '<td><div class="pag-meter-cell"><span class="pag-meter"><span style="width:' + pct(t) + '%"></span></span><span class="pag-meter-pct">' + pct(t) + ' %</span></div></td>' +
                '<td class="pag-actions-cell"><button type="button" class="pag-icon-btn" title="Ver quién debe" aria-label="Ver quién debe en ' + esc(corto(t.p.nombre)) + '">' + ICON.ojo + '</button></td></tr>';
        }).join('');
        var tot = filas.reduce(function (s, t) { s.n += t.n; s.pagado += t.pagado; s.cobrar += t.cobrar; s.vencido += t.vencido; return s; }, { n: 0, pagado: 0, cobrar: 0, vencido: 0 });
        document.getElementById('pag-p-foot').innerHTML = '<tr><td>Total de la sede</td><td class="num">' + tot.n + '</td><td class="num">' + pesos(tot.pagado) + '</td><td class="num">' + pesos(tot.cobrar) + '</td>' +
            '<td class="num">' + pesos(tot.vencido) + '</td><td><span class="pag-meter-pct">' + pct(tot) + ' %</span></td><td></td></tr>';
    }
    document.getElementById('pag-p-body').addEventListener('click', function (e) {
        var row = e.target.closest('[data-programa]');
        if (!row) return;
        var p = programas.find(function (x) { return String(x.id) === row.dataset.programa; });
        cbPrograma.setValue(String(p.id), corto(p.nombre));
        mostrarVista('cobrar');
    });

    /* ═══════════ Devoluciones ═══════════ */
    function pintarDevoluciones() {
        document.getElementById('pag-d-body').innerHTML = devoluciones.map(function (d) {
            var m = matPorId[d.matricula_id];
            return '<tr class="pag-row" data-dev="' + d.id + '">' +
                '<td><div class="pag-recibo">' + esc(d.comprobante) + '</div><div class="pag-person-sub">' + corta(d.fecha) + '</div></td>' +
                '<td><div class="pag-person-cell"><div class="pag-avatar pag-esc-' + esc(m.sigla) + '">' + esc(iniciales(m)) + '</div><div>' +
                    '<div class="pag-person-name">' + esc(nombre(m)) + '</div><div class="pag-person-sub">' + esc(corto(m.programa)) + (ESTADO_MAT[m.estado] ? ' · ' + ESTADO_MAT[m.estado] : '') + '</div></div></div></td>' +
                '<td><div class="pag-cubre" title="' + esc(d.motivo) + '">' + esc(d.motivo) + '</div></td>' +
                '<td>' + metodo(d.metodo) + '</td><td class="num"><span class="pag-monto">' + pesos(d.valor) + '</span></td>' +
                '<td class="pag-actions-cell"><button type="button" class="pag-icon-btn" title="Ver devolución" aria-label="Ver devolución ' + esc(d.comprobante) + '">' + ICON.ojo + '</button></td></tr>';
        }).join('');
        document.getElementById('pag-d-empty').hidden = devoluciones.length > 0;
    }
    document.getElementById('pag-d-body').addEventListener('click', function (e) {
        var row = e.target.closest('[data-dev]');
        if (row) abrirDevolucion(+row.dataset.dev);
    });

    /* ═══════════ KPIs y contadores ═══════════ */
    function pintarKpis() {
        var cartera = mats.filter(enCartera).map(resumen);
        var delMes = recaudos.filter(function (r) { return !r.anulado && r.fecha.slice(0, 7) === MES_HOY; });
        var antes = recaudos.filter(function (r) { return !r.anulado && r.fecha.slice(0, 7) === mesAnterior(MES_HOY); });
        var suma = function (l) { return l.reduce(function (s, r) { return s + r.valor; }, 0); };
        var hoy = suma(delMes), prev = suma(antes);
        var cambio = prev ? Math.round((hoy - prev) * 100 / prev) : null;
        document.getElementById('pag-k-recibido').textContent = pesos(hoy);
        document.getElementById('pag-k-recibido-lbl').textContent = 'Recibido en ' + nombreMes(MES_HOY) + ' · ' + delMes.length + (delMes.length === 1 ? ' pago' : ' pagos') +
            (cambio === null ? '' : ' · ' + (cambio >= 0 ? '+' : '−') + Math.abs(cambio) + ' % que ' + nombreMes(mesAnterior(MES_HOY)));
        var conMes = cartera.filter(function (r) { return r.mes > 0; });
        document.getElementById('pag-k-mes').textContent = pesos(conMes.reduce(function (s, r) { return s + r.mes; }, 0));
        document.getElementById('pag-k-mes-lbl').textContent = 'Por cobrar este mes (incluye lo vencido) · ' + conMes.length + (conMes.length === 1 ? ' estudiante' : ' estudiantes');
        var morosos = cartera.filter(function (r) { return r.vencido > 0; });
        document.getElementById('pag-k-vencido').textContent = pesos(morosos.reduce(function (s, r) { return s + r.vencido; }, 0));
        document.getElementById('pag-k-vencido-lbl').textContent = 'Vencido · ' + morosos.length + (morosos.length === 1 ? ' estudiante en mora' : ' estudiantes en mora');
        document.getElementById('pag-k-sinplan').textContent = cartera.filter(function (r) { return r.situacion === 'sin_plan'; }).length;

        var nC = document.getElementById('pag-n-cobrar');
        nC.textContent = conMes.length;
        nC.classList.toggle('is-aviso', morosos.length > 0);
        document.getElementById('pag-n-recibidos').textContent = delMes.length;
        document.getElementById('pag-n-devoluciones').textContent = devoluciones.length;
    }

    function refrescar() {
        pintarKpis();
        tCobrar.aplicar(true);
        tRecibidos.aplicar(true);
        pintarCaja();
        pintarProgramas();
        pintarDevoluciones();
    }

    /* ── Pestañas de vista (se recuerda la última) ── */
    var vistaBtns = document.querySelectorAll('.pag-vista-btn');
    function mostrarVista(v) {
        vistaBtns.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.pag-vista').forEach(function (s) { s.hidden = s.dataset.panel !== v; });
        try { localStorage.setItem(VISTA_KEY, v); } catch (e) {}
    }
    vistaBtns.forEach(function (b) { b.addEventListener('click', function () { mostrarVista(b.dataset.vista); }); });

    /* ═══════════ Cajón ═══════════ */
    var drawer = document.getElementById('pag-drawer');
    var dwBody = document.getElementById('pag-dw-body');
    var dwAvatar = document.getElementById('pag-dw-avatar');
    var btnVolver = document.getElementById('pag-dw-volver');
    var pila = [], actual = null, ultimoFoco = null;

    function abrirCajon(vista, desdeCajon) {
        if (!drawer.classList.contains('is-open')) ultimoFoco = document.activeElement;
        if (desdeCajon && actual) pila.push(actual);
        else if (!desdeCajon) pila = [];
        actual = vista;
        btnVolver.hidden = pila.length === 0;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        dwBody.scrollTop = 0;
        setTimeout(function () { drawer.querySelector('[data-cerrar].pag-close-btn').focus({ preventScroll: true }); }, 30);
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
    var ABRIR = { estudiante: function (id, o) { abrirEstudiante(id, o); }, recibo: function (id, o) { abrirRecibo(id, o); }, devolucion: function (id, o) { abrirDevolucion(id, o); } };
    btnVolver.addEventListener('click', function () {
        var prev = pila.pop();
        if (!prev) return;
        actual = null;
        ABRIR[prev.tipo](prev.id, { volviendo: true });
        btnVolver.hidden = pila.length === 0;
    });
    function mostrar(vista, opts) {
        if (opts.volviendo) actual = vista;
        else abrirCajon(vista, !!opts.desdeCajon);
    }
    function cabecera(avatarTxt, sigla, titulo, sub, badges) {
        dwAvatar.innerHTML = avatarTxt;
        dwAvatar.className = 'pag-drawer-avatar pag-avatar pag-esc-' + sigla;
        document.getElementById('pag-dw-titulo').textContent = titulo;
        document.getElementById('pag-dw-sub').textContent = sub;
        document.getElementById('pag-dw-badges').innerHTML = badges;
    }
    function aviso(clase, icono, texto, accion) {
        return '<div class="pag-aviso' + (clase ? ' ' + clase : '') + '"><div class="pag-aviso-txt">' + icono + '<p>' + texto + '</p></div>' + (accion || '') + '</div>';
    }

    /* Recordatorio de pago por WhatsApp: a un menor se le escribe al acudiente */
    function recordatorio(m, r) {
        var a = m.menor && m.acudiente && m.acudiente.telefono ? m.acudiente : null;
        var tel = a ? a.telefono : m.telefono;
        if (!tel) return null;
        var quien = a ? primerNombre(a.nombre) : primerNombre(m.nombres);
        var deuda = r.vencido > 0 ? 'un saldo vencido de ' + pesos(r.vencido) : 'un pago de ' + pesos(r.mes) + ' de este mes';
        var txt = 'Hola ' + quien + ', le escribimos de ESAT. ' + (a ? primerNombre(m.nombres) + ' tiene ' : 'Tienes ') + deuda +
            ' en ' + corto(m.programa) + '. Puede acercarse a la sede para ponerse al día. ¡Gracias!';
        return { url: waLink(tel, txt), a: a };
    }

    function abrirEstudiante(id, opts) {
        var m = matPorId[id];
        if (!m) return;
        opts = opts || {};
        var r = resumen(m);
        cabecera(esc(iniciales(m)), m.sigla, nombre(m), m.tipo_doc + ' ' + m.documento + ' · ' + corto(m.programa) + (m.grupo ? ' · ' + m.grupo : ''),
            tag(SITUACION[r.situacion]) + (ESTADO_MAT[m.estado] ? '<span class="app-status-tag app-status-muted">Matrícula ' + ESTADO_MAT[m.estado].toLowerCase() + '</span>' : '') +
            (m.plan ? '<span class="app-status-tag app-status-muted">Plan ' + (m.plan === 'mensual' ? 'mensual' : 'por periodo') + '</span>' : ''));

        var html = '<div class="pag-datos">' +
            dato('Vencido', '<span class="' + (r.vencido ? 'pag-monto is-rojo' : '') + '">' + pesos(r.vencido) + '</span>') +
            dato('A pagar este mes', pesos(r.mes)) + dato('Total por pagar', pesos(r.total)) + dato('Ya pagó', pesos(r.pagado)) + '</div>';

        var wa = (r.vencido > 0 || r.mes > 0) ? recordatorio(m, r) : null;
        var btnWa = wa ? '<a class="pag-btn pag-btn-wa pag-btn-sm" href="' + wa.url + '" target="_blank" rel="noopener">' + ICON.wa + (wa.a ? 'Escribirle al acudiente' : 'Recordarle') + '</a>' : '';
        if (r.vencido > 0) {
            html += aviso('is-rojo', ICON.alerta, 'Tiene <strong>' + pesos(r.vencido) + ' vencidos</strong> desde hace ' + r.dias_mora + ' días.' +
                (r.derecho_vencido ? ' No ha pagado el derecho de matrícula y ya empezaron sus clases: <strong>no puede ingresar a la plataforma</strong> hasta pagarlo.' : '') +
                (wa && wa.a ? ' Es menor de edad: el recordatorio le llega a ' + esc(wa.a.nombre) + ' (' + esc(wa.a.parentesco.toLowerCase()) + ').' : ''), btnWa);
        } else if (r.mes > 0) {
            html += aviso('', ICON.info, 'Este mes debe pagar <strong>' + pesos(r.mes) + '</strong> (vence el 10).', btnWa);
        }
        if (r.situacion === 'sin_plan') {
            html += aviso('', ICON.info, 'Todavía <strong>no ha elegido su plan de pago</strong> (mensual o por periodo), así que no tiene cuotas y no se le está cobrando. El plan lo elige el estudiante desde su panel.');
        }
        if (m.estado === 'retirado' || m.estado === 'graduado') {
            html += aviso('', ICON.info, 'La matrícula está ' + ESTADO_MAT[m.estado].toLowerCase() + (m.estado_fecha ? ' desde el ' + corta(m.estado_fecha) : '') + '.' + (r.total ? ' Le quedó un saldo por pagar.' : ''));
        }

        html += '<div class="pag-acciones-fila">' +
            (r.total > 0 ? '<button type="button" class="pag-btn pag-btn-primary pag-btn-sm" id="pag-dw-pagar">' + ICON.plus + 'Registrar pago</button>' : '') + '</div>';

        var cobros = m.cobros.slice().sort(function (a, b) { return (a.vence ? 0 : 1) - (b.vence ? 0 : 1) || (a.vence || '').localeCompare(b.vence || '') || a.id - b.id; });
        html += '<div><div class="pag-seccion-titulo">Cobros' + (m.plan ? '' : ' · solo el derecho de matrícula (no tiene plan)') + '</div>' +
            (cobros.length ? '<ul class="pag-lista">' + cobros.map(function (c) {
                var est = estadoCobro(c), queda = restante(c);
                var sub = (c.vence ? 'Vence el ' + corta(c.vence) : 'En espera: vence al iniciar su grupo') + ' · ' + pesos(c.valor) +
                    (c.abonado > 0 && est !== 'pagado' ? ' · abonó ' + pesos(c.abonado) : '');
                return '<li><div class="pag-min0"><div class="pag-lista-nombre">' + esc(c.concepto) + '</div><div class="pag-lista-sub">' + sub + '</div></div>' +
                    '<div class="pag-lista-der">' + (queda > 0 ? '<span class="pag-monto' + (est === 'vencido' ? ' is-rojo' : '') + '">' + pesos(queda) + '</span>' : '') + tag(COBRO_TAG[est]) + '</div></li>';
            }).join('') + '</ul>' : '<p class="pag-lista-vacia">No tiene cobros.</p>') + '</div>';

        var suyos = recaudos.filter(function (x) { return x.matricula_id === m.id; }).sort(function (a, b) { return b.fecha.localeCompare(a.fecha) || b.id - a.id; });
        html += '<div><div class="pag-seccion-titulo">Pagos recibidos</div>' +
            (suyos.length ? '<ul class="pag-lista">' + suyos.map(function (x) {
                return '<li class="is-clic" data-recibo="' + x.id + '" tabindex="0" role="button"><div class="pag-min0"><div class="pag-lista-nombre">' + esc(x.recibo) + ' · ' + corta(x.fecha) + '</div>' +
                    '<div class="pag-lista-sub">' + esc(x.metodo) + ' · ' + esc(cubreTxt(x)) + '</div></div>' +
                    '<div class="pag-lista-der"><span class="pag-monto' + (x.anulado ? ' is-tachado' : '') + '">' + pesos(x.valor) + '</span>' + (x.anulado ? '<span class="app-status-tag app-status-danger">Anulado</span>' : '') + '</div></li>';
            }).join('') + '</ul>' : '<p class="pag-lista-vacia">Todavía no ha hecho ningún pago.</p>') + '</div>';

        dwBody.innerHTML = html;
        mostrar({ tipo: 'estudiante', id: id }, opts);
        var bp = document.getElementById('pag-dw-pagar');
        if (bp) bp.addEventListener('click', function () { abrirModalPago(m.id); });
        dwBody.querySelectorAll('[data-recibo]').forEach(function (li) {
            var ir = function () { abrirRecibo(+li.dataset.recibo, { desdeCajon: true }); };
            li.addEventListener('click', ir);
            li.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); ir(); } });
        });
    }

    function abrirRecibo(id, opts) {
        var x = recaudos.find(function (r) { return r.id === id; });
        if (!x) return;
        opts = opts || {};
        var m = matPorId[x.matricula_id];
        cabecera(ICON.recibo, m.sigla, 'Recibo ' + x.recibo, nombre(m) + ' · ' + m.tipo_doc + ' ' + m.documento,
            x.anulado ? '<span class="app-status-tag app-status-danger">Anulado</span>' : '<span class="app-status-tag app-status-active">Vigente</span>');
        var html = '<div class="pag-datos">' + dato('Valor', '<span class="' + (x.anulado ? 'pag-monto is-tachado' : '') + '">' + pesos(x.valor) + '</span>') +
            dato('Fecha', corta(x.fecha)) + dato('Medio', esc(x.metodo)) + dato('Registró', esc(x.registrado)) + '</div>';
        if (x.anulado) {
            html += aviso('is-rojo', ICON.x, 'Anulado el ' + corta(x.anulado.fecha) + ' por ' + esc(x.anulado.por) + ': ' + esc(x.anulado.motivo) + '. Lo que cubría volvió a quedar pendiente.');
        }
        if (x.referencia || x.observacion) {
            html += '<div class="pag-datos" style="grid-template-columns: repeat(2, minmax(0, 1fr))">' +
                dato('Referencia', '<span class="pag-dato-txt">' + esc(x.referencia || '—') + '</span>') +
                dato('Observación', '<span class="pag-dato-txt">' + esc(x.observacion || '—') + '</span>') + '</div>';
        }
        html += '<div><div class="pag-seccion-titulo">Lo que pagó (se aplica a lo más viejo primero)</div><ul class="pag-lista">' + x.cubre.map(function (c) {
            var cobro = m.cobros.find(function (k) { return k.id === c.id; });
            var completo = cobro && c.monto >= cobro.valor;
            return '<li><div class="pag-min0"><div class="pag-lista-nombre">' + esc(c.concepto) + '</div><div class="pag-lista-sub">' + (completo ? 'Completo' : 'Abono' + (cobro ? ' de ' + pesos(cobro.valor) : '')) + '</div></div>' +
                '<div class="pag-lista-der"><span class="pag-monto">' + pesos(c.monto) + '</span></div></li>';
        }).join('') + '</ul></div>';

        html += '<div class="pag-acciones-fila">' +
            '<a class="pag-btn pag-btn-ghost pag-btn-sm" href="' + D.reciboUrl + '/' + x.id + '" target="_blank" rel="noopener">' + ICON.print + 'Imprimir recibo</a>' +
            '<button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" id="pag-dw-ver-est">' + ICON.user + 'Ver estudiante</button>' +
            (x.anulable ? '<button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" id="pag-dw-anular">' + ICON.x + 'Anular pago</button>' : '') + '</div>' +
            (!x.anulado && !x.anulable ? '<p class="pag-ayuda">' + (m.estado === 'graduado'
                ? 'La matrícula está graduada y a paz y salvo: sus pagos ya no se anulan.'
                : 'Parte de este pago ya se devolvió o se trasladó a otra matrícula: no se puede anular.') + '</p>' : '');

        dwBody.innerHTML = html;
        mostrar({ tipo: 'recibo', id: id }, opts);
        document.getElementById('pag-dw-ver-est').addEventListener('click', function () { abrirEstudiante(m.id, { desdeCajon: true }); });
        var ba = document.getElementById('pag-dw-anular');
        if (ba) ba.addEventListener('click', function () { abrirModalAnular(x.id); });
    }

    function abrirDevolucion(id, opts) {
        var d = devoluciones.find(function (x) { return x.id === id; });
        if (!d) return;
        opts = opts || {};
        var m = matPorId[d.matricula_id];
        cabecera(ICON.devol, m.sigla, 'Devolución ' + d.comprobante, nombre(m) + ' · ' + m.tipo_doc + ' ' + m.documento,
            ESTADO_MAT[m.estado] ? '<span class="app-status-tag app-status-muted">Matrícula ' + ESTADO_MAT[m.estado].toLowerCase() + '</span>' : '');
        dwBody.innerHTML = '<div class="pag-datos">' + dato('Valor', pesos(d.valor)) + dato('Fecha', corta(d.fecha)) + dato('Medio', esc(d.metodo)) + dato('Registró', esc(d.registrado)) + '</div>' +
            '<div class="pag-datos" style="grid-template-columns: minmax(0, 1fr) minmax(0, 2fr)">' + dato('Referencia', '<span class="pag-dato-txt">' + esc(d.referencia || '—') + '</span>') +
                dato('Motivo', '<span class="pag-dato-txt">' + esc(d.motivo) + '</span>') + '</div>' +
            '<p class="pag-ayuda">Las devoluciones se registran e imprimen desde Matrículas, en la ficha de la matrícula, porque el valor lo calcula el sistema con lo que pagó por adelantado.</p>' +
            '<div class="pag-acciones-fila"><button type="button" class="pag-btn pag-btn-ghost pag-btn-sm" id="pag-dw-ver-est">' + ICON.user + 'Ver estudiante</button></div>';
        mostrar({ tipo: 'devolucion', id: id }, opts);
        document.getElementById('pag-dw-ver-est').addEventListener('click', function () { abrirEstudiante(m.id, { desdeCajon: true }); });
    }

    /* ═══════════ Modal: registrar pago / anular ═══════════ */
    var modal = document.getElementById('pag-modal');
    var mdBody = document.getElementById('pag-md-body');
    var mdError = document.getElementById('pag-md-error');
    var mdOk = document.getElementById('pag-md-ok');
    var mdAtras = document.getElementById('pag-md-atras');
    var mdSteps = document.getElementById('pag-md-steps');
    var md = null, mdFoco = null;

    function abrirModal() {
        mdFoco = document.activeElement;
        mdAtras.textContent = 'Atrás';
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
    function error(msg, campo) {
        mdError.textContent = msg;
        mdError.hidden = false;
        if (campo) { campo.classList.add('is-invalid'); campo.focus(); }
    }
    function pasos(n) {
        mdSteps.hidden = false;
        mdSteps.querySelectorAll('.pag-wstep').forEach(function (s) {
            var k = +s.dataset.step;
            s.classList.toggle('is-active', k === n);
            s.classList.toggle('is-done', k < n);
        });
        document.getElementById('pag-md-paso').textContent = 'Paso ' + n + ' de 2';
    }

    /* Paso 1: elegir a quién se le recibe el pago (solo quien debe algo) */
    function abrirModalPago(matId) {
        md = { tipo: 'pago', mat: matId || null };
        document.getElementById('pag-md-titulo').textContent = 'Registrar pago';
        abrirModal();
        if (matId) pasoPago(); else pasoEstudiante();
    }
    function pasoEstudiante() {
        md.paso = 1;
        pasos(1);
        mdError.hidden = true;
        mdBody.innerHTML = '<div class="pag-search-box"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
            '<input type="search" id="pag-md-buscar" placeholder="Buscar por nombre, documento o código..." autocomplete="off" aria-label="Buscar estudiante"></div>' +
            '<ul class="pag-elegir" id="pag-md-lista" role="listbox" aria-label="Estudiantes con saldo por pagar"></ul>';
        var lista = document.getElementById('pag-md-lista'), input = document.getElementById('pag-md-buscar');
        function pintar() {
            var q = input.value.toLowerCase().trim();
            var deudores = mats.map(function (m) { return { m: m, r: resumen(m) }; })
                .filter(function (x) { return x.r.total > 0 && (!q || (nombre(x.m) + ' ' + x.m.documento + ' ' + x.m.codigo).toLowerCase().indexOf(q) !== -1); })
                .sort(function (a, b) { return (a.m.apellidos + a.m.nombres).localeCompare(b.m.apellidos + b.m.nombres, 'es'); });
            lista.innerHTML = deudores.length ? deudores.map(function (x) {
                var m = x.m;
                return '<li><button type="button" data-mat="' + m.id + '" role="option" aria-selected="' + (md.mat === m.id) + '" class="' + (md.mat === m.id ? 'is-selected' : '') + '">' +
                    '<span class="pag-avatar pag-esc-' + esc(m.sigla) + '">' + esc(iniciales(m)) + '</span>' +
                    '<span class="pag-min0"><span class="pag-person-name" style="display:block">' + esc(nombre(m)) + '</span><span class="pag-person-sub" style="display:block">' + esc(m.tipo_doc + ' ' + m.documento + ' · ' + corto(m.programa)) + '</span></span>' +
                    '<span class="pag-lista-der"><span class="pag-monto' + (x.r.vencido ? ' is-rojo' : '') + '">' + pesos(x.r.mes || x.r.total) + '</span><span class="pag-person-sub">' + (x.r.vencido ? 'con vencido' : (x.r.mes ? 'este mes' : 'por pagar')) + '</span></span></button></li>';
            }).join('') : '<li><p class="pag-elegir-vacio">' + (q ? 'Nadie con saldo por pagar coincide con la búsqueda.' : 'Nadie tiene saldo por pagar.') + '</p></li>';
        }
        input.addEventListener('input', pintar);
        lista.addEventListener('click', function (e) {
            var b = e.target.closest('[data-mat]');
            if (!b) return;
            md.mat = +b.dataset.mat;
            pasoPago();
        });
        pintar();
        mdAtras.hidden = true;
        mdOk.hidden = false;
        mdOk.className = 'pag-btn pag-btn-primary';
        mdOk.textContent = 'Siguiente';
        setTimeout(function () { input.focus(); }, 30);
    }
    function pasoPago() {
        md.paso = 2;
        pasos(2);
        mdError.hidden = true;
        var m = matPorId[md.mat], r = resumen(m);
        var primero = enOrden(m)[0];
        var sugerido = r.mes > 0 ? r.mes : (primero ? restante(primero) : 0);
        mdBody.innerHTML =
            '<div class="pag-est-card"><span class="pag-avatar pag-esc-' + esc(m.sigla) + '">' + esc(iniciales(m)) + '</span><div class="pag-min0">' +
                '<strong>' + esc(nombre(m)) + '</strong><span>' + esc(m.tipo_doc + ' ' + m.documento + ' · ' + corto(m.programa)) + ' · debe ' + pesos(r.total) + '</span></div></div>' +
            '<div class="pag-field"><label class="pag-lbl" for="pag-f-valor">Valor recibido *</label>' +
                '<div class="pag-valor-wrap"><span aria-hidden="true">$</span><input class="pag-input" id="pag-f-valor" type="text" inputmode="numeric" autocomplete="off" value="' + miles(sugerido) + '"></div>' +
                '<div class="pag-chips pag-chips-valor">' +
                    (r.mes > 0 ? '<button type="button" class="pag-chip-btn" data-valor="' + r.mes + '">Este mes · ' + pesos(r.mes) + '</button>' : '') +
                    '<button type="button" class="pag-chip-btn" data-valor="' + r.total + '">Todo lo que debe · ' + pesos(r.total) + '</button>' +
                '</div></div>' +
            '<div class="pag-reparto" id="pag-f-reparto" aria-live="polite"></div>' +
            '<div class="pag-form-row">' +
                '<div class="pag-field"><label class="pag-lbl" for="pag-f-fecha">Fecha del pago *</label><input class="pag-input" id="pag-f-fecha" type="date" value="' + HOY + '" max="' + HOY + '"></div>' +
                '<div class="pag-field"><label class="pag-lbl" for="pag-f-ref">Referencia <em id="pag-f-ref-opc">opcional en efectivo</em></label><input class="pag-input" id="pag-f-ref" type="text" maxlength="60" autocomplete="off" placeholder="N.º de transacción"></div>' +
            '</div>' +
            '<div class="pag-field"><span class="pag-lbl">Medio de pago *</span><div class="pag-chips" id="pag-f-metodos" role="radiogroup" aria-label="Medio de pago">' +
                D.metodos.map(function (x) { return '<label class="pag-chip"><input type="radio" name="pag-f-met" value="' + esc(x) + '"' + (x === 'Efectivo' ? ' checked' : '') + '><span>' + esc(x) + '</span></label>'; }).join('') + '</div></div>' +
            '<div class="pag-nota">' + ICON.info + '<p>Antes de registrar una transferencia, Nequi o Daviplata, revisa en la cuenta que el dinero sí llegó y copia el número de la transacción en <strong>Referencia</strong>.</p></div>' +
            '<div class="pag-field"><label class="pag-lbl" for="pag-f-obs">Observación <em>opcional</em></label><textarea class="pag-input pag-textarea" id="pag-f-obs" maxlength="255" placeholder="Ej. Pagó en la sede con billetes de $ 50.000"></textarea></div>';

        var vEl = document.getElementById('pag-f-valor');
        vEl.addEventListener('input', function () {
            var n = parseInt(vEl.value.replace(/\D/g, '')) || 0;
            vEl.value = n ? miles(n) : '';
            vEl.classList.remove('is-invalid');
            reparto();
        });
        mdBody.querySelectorAll('[data-valor]').forEach(function (b) {
            b.addEventListener('click', function () { vEl.value = miles(+b.dataset.valor); vEl.classList.remove('is-invalid'); reparto(); });
        });
        mdBody.querySelectorAll('input[name="pag-f-met"]').forEach(function (el) {
            el.addEventListener('change', function () {
                document.getElementById('pag-f-ref-opc').textContent = el.value === 'Efectivo' ? 'opcional en efectivo' : 'obligatoria';
                document.getElementById('pag-f-ref').classList.remove('is-invalid');
            });
        });
        ['pag-f-fecha', 'pag-f-ref'].forEach(function (id) { document.getElementById(id).addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); }); });
        reparto();
        mdAtras.hidden = false;
        mdOk.hidden = false;
        mdOk.className = 'pag-btn pag-btn-primary';
        mdOk.textContent = 'Registrar pago';
        setTimeout(function () { vEl.focus(); vEl.select(); }, 30);
    }
    function valorEscrito() { return parseInt((document.getElementById('pag-f-valor').value || '').replace(/\D/g, '')) || 0; }
    /* Cómo se reparte el valor escrito: qué cobros completa y cuál queda abonado */
    function reparto() {
        var m = matPorId[md.mat], r = resumen(m), cont = document.getElementById('pag-f-reparto'), queda = valorEscrito(), filas = [];
        enOrden(m).forEach(function (c) {
            if (queda <= 0) return;
            var falta = restante(c), aplica = Math.min(queda, falta);
            filas.push('<li><span>' + esc(c.concepto) + (estadoCobro(c) === 'vencido' ? ' ' + tag(COBRO_TAG.vencido) : '') + '</span><span>' +
                (aplica >= falta ? '<strong>Completa</strong> ' + pesos(aplica) : 'Abono ' + pesos(aplica) + ' · <em>faltan ' + pesos(falta - aplica) + '</em>') + '</span></li>');
            queda -= aplica;
        });
        cont.innerHTML = valorEscrito() > r.total
            ? '<p class="pag-reparto-error">Supera lo que debe en total (' + pesos(r.total) + '). No se puede recibir más de lo que se debe.</p>'
            : filas.length ? '<span class="pag-reparto-ttl">Así se aplica · un solo recibo</span><ul>' + filas.join('') + '</ul>' : '';
    }
    function guardarPago() {
        var m = matPorId[md.mat], r = resumen(m);
        var vEl = document.getElementById('pag-f-valor'), fEl = document.getElementById('pag-f-fecha'), refEl = document.getElementById('pag-f-ref');
        var v = valorEscrito(), fecha = fEl.value, met = (mdBody.querySelector('input[name="pag-f-met"]:checked') || {}).value, ref = refEl.value.trim();
        mdBody.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        if (!v) return error('Escribe cuánto pagó.', vEl);
        if (v > r.total) return error('Supera lo que debe en total (' + pesos(r.total) + ').', vEl);
        if (!fecha) return error('Escribe la fecha del pago.', fEl);
        if (fecha > HOY) return error('La fecha del pago no puede ser futura.', fEl);
        if (!met) return error('Elige el medio de pago.');
        if (met !== 'Efectivo' && !ref) return error('Escribe la referencia: con ' + met + ' es obligatoria para poder verificar el pago.', refEl);

        // El servidor lo aplica en orden (lo más viejo primero), deja abonado lo que no alcanza y entrega el recibo
        ocupado(true, 'Registrando…');
        enviar('POST', D.matriculasUrl + '/' + m.id + '/pagos', {
            valor: v, fecha_pago: fecha, metodo_pago: met, referencia: ref || null,
            observacion: document.getElementById('pag-f-obs').value.trim() || null
        }).then(function (j) {
            recargarConAviso(j.mensaje, { tipo: 'recibo', recibo: j.recibo });
        }).catch(function (err) {
            ocupado(false);
            var pe = primerError(err);
            error(pe.mensaje, document.getElementById({ valor: 'pag-f-valor', fecha_pago: 'pag-f-fecha', referencia: 'pag-f-ref', observacion: 'pag-f-obs' }[pe.campo]));
        });
    }
    function ocupado(si, texto) {
        mdOk.disabled = si;
        mdAtras.disabled = si;
        if (si) { mdOk.dataset.texto = mdOk.textContent; mdOk.textContent = texto; }
        else if (mdOk.dataset.texto) mdOk.textContent = mdOk.dataset.texto;
    }

    function abrirModalAnular(recId) {
        var x = recaudos.find(function (r) { return r.id === recId; });
        md = { tipo: 'anular', rec: recId };
        document.getElementById('pag-md-titulo').textContent = 'Anular pago';
        document.getElementById('pag-md-paso').textContent = 'Recibo ' + x.recibo;
        mdSteps.hidden = true;
        abrirModal();
        mdBody.innerHTML = '<div class="pag-aviso is-rojo"><div class="pag-aviso-txt">' + ICON.alerta + '<p><strong>Se anulará el recibo ' + esc(x.recibo) + ' por ' + pesos(x.valor) + '.</strong> ' +
            'Pagado el ' + corta(x.fecha) + ' por ' + esc(x.metodo) + '. Cubría: ' + x.cubre.map(function (c) { return esc(c.concepto.toLowerCase()) + ' (' + pesos(c.monto) + ')'; }).join(', ') +
            '. Todo eso volverá a quedar pendiente y el recibo quedará marcado como anulado (su número no se vuelve a usar). Úsalo solo si el pago se registró por error.</p></div></div>' +
            '<div class="pag-field"><label class="pag-lbl" for="pag-f-motivo">Motivo *</label><textarea class="pag-input pag-textarea" id="pag-f-motivo" maxlength="255" placeholder="Ej. Se registró en el estudiante equivocado"></textarea></div>';
        document.getElementById('pag-f-motivo').addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); });
        mdAtras.hidden = false;
        mdAtras.textContent = 'Cancelar';
        mdOk.hidden = false;
        mdOk.className = 'pag-btn pag-btn-danger';
        mdOk.textContent = 'Anular pago';
        mdOk.disabled = false; mdAtras.disabled = false;
        setTimeout(function () { document.getElementById('pag-f-motivo').focus(); }, 30);
    }
    function guardarAnulacion() {
        var x = recaudos.find(function (r) { return r.id === md.rec; });
        var mEl = document.getElementById('pag-f-motivo'), motivo = mEl.value.trim();
        if (motivo.length < 5) return error('Escribe el motivo con un poco más de detalle.', mEl);
        ocupado(true, 'Anulando…');
        enviar('PATCH', D.matriculasUrl + '/' + x.matricula_id + '/recaudos/' + x.id + '/anular', { motivo: motivo })
            .then(function (j) { recargarConAviso(j.mensaje, { tipo: 'recibo', recibo: x.recibo }); })
            .catch(function (err) {
                ocupado(false);
                error(primerError(err).mensaje, mEl);
            });
    }

    mdOk.addEventListener('click', function () {
        if (!md) return;
        mdError.hidden = true;
        if (md.tipo === 'anular') return guardarAnulacion();
        if (md.paso === 1) {
            if (!md.mat) return error('Elige al estudiante que pagó.');
            return pasoPago();
        }
        guardarPago();
    });
    mdAtras.addEventListener('click', function () {
        if (!md || md.tipo === 'anular') return cerrarModal();
        pasoEstudiante();
    });
    mdBody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'search') { e.preventDefault(); mdOk.click(); }
    });
    document.getElementById('pag-btn-nuevo').addEventListener('click', function () { abrirModalPago(null); });

    /* ═══════════ Hoja imprimible del resumen de caja ═══════════ */
    var hojaEl = document.getElementById('pag-hoja-modal');
    document.body.appendChild(hojaEl); // hijo directo de <body>: al imprimir se oculta todo lo demás
    var hojaFoco = null;
    document.getElementById('pag-caja-imprimir').addEventListener('click', function () {
        var c = calcularCaja();
        document.getElementById('pag-hoja-fecha').textContent = (D.ciudad ? D.ciudad + ', ' : '') + larga(HOY);
        var tabla = function (cols, filas, pie) {
            return '<table class="pag-hoja-tabla"><thead><tr>' + cols.map(function (x, i) { return '<th' + (i ? ' class="num"' : '') + '>' + x + '</th>'; }).join('') + '</tr></thead><tbody>' +
                filas.join('') + '</tbody>' + (pie ? '<tfoot><tr>' + pie.map(function (x, i) { return '<td' + (i ? ' class="num"' : '') + '>' + x + '</td>'; }).join('') + '</tr></tfoot>' : '') + '</table>';
        };
        document.getElementById('pag-hoja-cuerpo').innerHTML =
            '<h1 class="pag-hoja-titulo">Resumen de caja</h1>' +
            '<p>' + esc(textoRango(c.rango)) + '. Pagos recibidos de los estudiantes, sin contar los recibos anulados.</p>' +
            (c.pagos.length ? '<h2 class="pag-hoja-sub">Por medio de pago</h2>' + tabla(['Medio', 'Pagos', 'Valor'], c.metodos.map(function (x) {
                return '<tr><td>' + esc(x.clave) + '</td><td class="num">' + x.n + '</td><td class="num">' + pesos(x.valor) + '</td></tr>';
            }), ['Total recibido', c.pagos.length, pesos(c.total)]) +
            '<h2 class="pag-hoja-sub">Días en que entraron pagos</h2>' + tabla(['Fecha', 'Pagos', 'En efectivo', 'Otros medios', 'Total'], c.dias.map(function (x) {
                return '<tr><td>' + esc(larga(x.clave)) + '</td><td class="num">' + x.n + '</td><td class="num">' + pesos(x.efectivo) + '</td><td class="num">' + pesos(x.valor - x.efectivo) + '</td><td class="num">' + pesos(x.valor) + '</td></tr>';
            })) : '<p><strong>No se recibió ningún pago en estas fechas.</strong></p>') +
            '<table class="pag-hoja-tabla"><tbody>' +
                '<tr><td>Total recibido</td><td class="num">' + pesos(c.total) + '</td></tr>' +
                '<tr><td>Devoluciones (' + c.devs.length + ')</td><td class="num">− ' + pesos(c.salidas) + '</td></tr>' +
                (c.anulados.length ? '<tr><td>Recibos anulados (no suman)</td><td class="num">' + c.anulados.length + '</td></tr>' : '') +
            '</tbody><tfoot><tr><td>Queda en caja</td><td class="num">' + pesos(c.total - c.salidas) + '</td></tr></tfoot></table>' +
            '<div class="pag-hoja-firma">Firma de quien entrega<span>' + esc(D.admin || 'Administrador') + '</span></div>';
        hojaFoco = document.activeElement;
        hojaEl.setAttribute('aria-hidden', 'false');
        hojaEl.scrollTop = 0;
        document.getElementById('pag-hoja-imprimir').focus({ preventScroll: true });
    });
    function cerrarHoja() {
        hojaEl.setAttribute('aria-hidden', 'true');
        if (hojaFoco && hojaFoco.focus) hojaFoco.focus({ preventScroll: true });
    }
    hojaEl.querySelectorAll('[data-hoja-cerrar]').forEach(function (b) { b.addEventListener('click', cerrarHoja); });
    document.getElementById('pag-hoja-imprimir').addEventListener('click', function () { window.print(); });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (hojaEl.getAttribute('aria-hidden') === 'false') cerrarHoja();
        else if (modal.classList.contains('is-open')) cerrarModal();
        else cerrarCajon();
    });

    /* ═══════════ Exportar lo que se ve con los filtros (CSV con ; y BOM para las tildes) ═══════════ */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    var EXPORT = {
        cobrar: function () {
            return [['Código', 'Nombres', 'Apellidos', 'Tipo doc.', 'Documento', 'Programa', 'Grupo', 'Plan', 'Situación', 'Vencido', 'Días de mora', 'A pagar este mes', 'Total por pagar', 'Ya pagó', 'Último pago', 'Celular', 'Acudiente', 'Celular acudiente']]
                .concat(tCobrar.items().map(function (x) {
                    var m = x.m, r = x.r;
                    return [m.codigo, m.nombres, m.apellidos, m.tipo_doc, m.documento, m.programa, m.grupo, m.plan ? (m.plan === 'mensual' ? 'Mensual' : 'Por periodo') : 'Sin elegir',
                        SITUACION[r.situacion][1], r.vencido, r.vencido ? r.dias_mora : '', r.mes, r.total, r.pagado, r.ultimo ? r.ultimo.fecha : '', m.telefono,
                        m.acudiente ? m.acudiente.nombre : '', m.acudiente ? m.acudiente.telefono : ''];
                }));
        },
        recibidos: function () {
            return [['Recibo', 'Fecha', 'Estudiante', 'Documento', 'Programa', 'Qué pagó', 'Medio de pago', 'Referencia', 'Valor', 'Registró', 'Estado']]
                .concat(tRecibidos.items().map(function (r) {
                    var m = matPorId[r.matricula_id];
                    return [r.recibo, r.fecha, nombre(m), m.documento, m.programa, cubreTxt(r), r.metodo, r.referencia || '', r.valor, r.registrado, r.anulado ? 'Anulado' : 'Vigente'];
                }));
        }
    };
    document.querySelectorAll('[data-exportar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var vista = btn.dataset.exportar;
            var filas = EXPORT[vista]();
            var csv = '﻿' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
            var nombreArchivo = 'pagos-' + (vista === 'cobrar' ? 'por-cobrar' : 'recibidos') + '-' + HOY + '.csv';
            var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            var a = document.createElement('a');
            a.href = url; a.download = nombreArchivo;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            var n = filas.length - 1;
            toast('Se descargó ' + nombreArchivo + ' con ' + n + (n === 1 ? ' fila.' : ' filas.'));
        });
    });

    /* Inicializar */
    refrescar();
    try {
        var guardada = localStorage.getItem(VISTA_KEY);
        if (guardada && document.querySelector('.pag-vista[data-panel="' + guardada + '"]')) mostrarVista(guardada);
    } catch (e) {}
    /* Después de guardar: el aviso y el recibo que se debe volver a abrir */
    var avisoGuardado = null, abrirGuardado = null;
    try {
        avisoGuardado = sessionStorage.getItem(AVISO_KEY);
        abrirGuardado = JSON.parse(sessionStorage.getItem(ABRIR_KEY) || 'null');
        sessionStorage.removeItem(AVISO_KEY);
        sessionStorage.removeItem(ABRIR_KEY);
    } catch (e) {}
    if (avisoGuardado) toast(avisoGuardado);
    if (abrirGuardado && abrirGuardado.tipo === 'recibo') {
        var reciboGuardado = recaudos.find(function (r) { return r.recibo === abrirGuardado.recibo; });
        if (reciboGuardado) abrirRecibo(reciboGuardado.id);
    }

    // Acceso rápido desde el Inicio: /admin/pagos#nuevo abre el formulario de registrar pago
    if (location.hash === '#nuevo') {
        abrirModalPago(null);
        history.replaceState(null, '', location.pathname);
    }
}());
