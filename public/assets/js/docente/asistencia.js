/* ESAT — Docente: Tomar asistencia
   Lee solo de window.DOC_DATOS y guarda solo con window.DOC_API (datos-ejemplo.js) hasta que exista el backend.
   Reglas (las mismas de AsistenciaService): se registra y corrige cualquier clase ya dictada o la de hoy mientras
   el grupo esté vigente; un grupo finalizado es solo consulta; una clase futura no se registra; la excusa lleva
   motivo; % = (presente + tarde) / (presente + tarde + ausente) y el 80 % es solo referencia. */
(function () {
    'use strict';

    var D = window.DOC_DATOS, API = window.DOC_API;
    if (!D || !API) return;

    var CLAVE_GRUPO = 'esat.docente.asistencia.grupo';
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var DIAS  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var DIA_C = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    var OPCIONES = [
        { v: 'presente', l: 'P', txt: 'Presente' },
        { v: 'tarde',    l: 'T', txt: 'Tarde' },
        { v: 'ausente',  l: 'A', txt: 'Ausente' },
        { v: 'excusa',   l: 'E', txt: 'Excusa' }
    ];
    var MOTIVOS_RAPIDOS = ['Cita médica', 'Incapacidad médica', 'Calamidad doméstica'];
    // Solo quienes salieron del grupo antes de terminar (en un grupo finalizado todos los demás son graduados)
    var SALIDA = { aplazado: 'Aplazado', retirado: 'Retirado' };
    var reducirMov = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var ICON = {
        alerta: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        check: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        candado: '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        cal: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        personas: '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        izq: '<svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>',
        der: '<svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>'
    };

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function $(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function parse(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    var anioHoy = +D.hoy.slice(0, 4);
    function conAnio(d) { return d.getFullYear() !== anioHoy ? ' de ' + d.getFullYear() : ''; }
    /* "miércoles 21 de octubre" (con el año solo si no es el actual) */
    function fechaLarga(iso) { var d = parse(iso); return DIAS[d.getDay()] + ' ' + d.getDate() + ' de ' + MESES[d.getMonth()] + conAnio(d); }
    /* "mié 21 oct" */
    function fechaCorta(iso) { var d = parse(iso); return DIA_C[d.getDay()] + ' ' + d.getDate() + ' ' + MES_C[d.getMonth()] + (d.getFullYear() !== anioHoy ? ' ' + d.getFullYear() : ''); }
    function diaMes(iso) { var d = parse(iso); return d.getDate() + ' ' + MES_C[d.getMonth()] + (d.getFullYear() !== anioHoy ? ' ' + d.getFullYear() : ''); }
    function mayus(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
    function horas(c) { return c.ini + ' – ' + c.fin; }
    function iniciales(n, a) { return ((n || '').charAt(0) + (a || '').charAt(0)).toUpperCase(); }
    function nombreDe(e) { return e.nombres + ' ' + e.apellidos; }
    function tono(p) { return p == null ? 'none' : (p >= 90 ? 'ok' : (p >= D.MINIMO ? 'warn' : 'bad')); }
    function pctTxt(p) { return p == null ? '—' : p + ' %'; }
    function plural(n, uno, varios) { return n + ' ' + (n === 1 ? uno : varios); }
    function sigla(escuela) {
        var s = (escuela || '').toLowerCase();
        if (s.indexOf('salud') > -1) return 'sal';
        if (s.indexOf('turis') > -1) return 'tur';
        if (s.indexOf('admin') > -1) return 'adm';
        if (s.indexOf('educa') > -1) return 'edu';
        if (s.indexOf('deport') > -1) return 'dep';
        if (s.indexOf('cienc') > -1) return 'cie';
        if (s.indexOf('belleza') > -1 || s.indexOf('estét') > -1) return 'bel';
        return 'gen';
    }
    function listaY(arr) { return arr.length > 1 ? arr.slice(0, -1).join(', ') + ' y ' + arr[arr.length - 1] : (arr[0] || ''); }
    function horarioTxt(g) {
        var dias = g.horario.map(function (h) { return h.dia.slice(0, 3); });
        var mismas = g.horario.every(function (h) { return h.ini === g.horario[0].ini && h.fin === g.horario[0].fin; });
        if (mismas && g.horario.length) return listaY(dias) + ' · ' + horas(g.horario[0]);
        return g.horario.map(function (h) { return h.dia.slice(0, 3) + ' ' + horas(h); }).join(' · ');
    }

    var toastEl = $('tas-toast');
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        clearTimeout(toast.t);
        toast.t = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 4500);
    }
    var anuncioEl = $('tas-anuncio');
    function anunciar(msg) { anuncioEl.textContent = ''; setTimeout(function () { anuncioEl.textContent = msg; }, 40); }

    function leerGrupoGuardado() { try { return localStorage.getItem(CLAVE_GRUPO); } catch (e) { return null; } }
    function recordarGrupo(id) { try { localStorage.setItem(CLAVE_GRUPO, String(id)); } catch (e) { /* sin almacenamiento */ } }

    /* ── Datos ────────────────────────────────── */
    function vigentes() { return D.grupos.filter(function (g) { return !g.soloConsulta; }); }
    function finalizados() { return D.grupos.filter(function (g) { return g.soloConsulta; }); }
    function buscarGrupo(id) { return D.grupos.filter(function (g) { return g.id === +id; })[0] || null; }
    function claseDe(g, fecha) { return g.clases.filter(function (c) { return c.fecha === fecha; })[0] || null; }
    function moduloDe(g, fecha) {
        for (var i = 0; i < g.modulos.length; i++) {
            if (g.modulos[i].ini <= fecha && fecha <= g.modulos[i].fin) return { m: g.modulos[i], n: i + 1 };
        }
        return null;
    }
    function pendientes(g) { return g.soloConsulta ? [] : g.clases.filter(function (c) { return c.estado === 'sin_registrar'; }); }
    function lista() {
        if (!st.g || !st.fecha) return [];
        return D.planilla(st.g, st.fecha).slice().sort(function (a, b) {
            return a.apellidos.localeCompare(b.apellidos, 'es') || a.nombres.localeCompare(b.nombres, 'es');
        });
    }

    /* Clase por defecto: la de hoy; si no, la más vieja sin registrar; si no, la última registrada */
    function claseDefecto(g) {
        var hoy = claseDe(g, D.hoy);
        if (hoy) return hoy.fecha;
        var sin = pendientes(g);
        if (sin.length) return sin[0].fecha;
        var reg = g.clases.filter(function (c) { return c.estado === 'registrada'; });
        if (reg.length) return reg[reg.length - 1].fecha;
        return g.clases.length ? g.clases[0].fecha : null;
    }
    /* Grupo por defecto: el recordado; si no, uno con clase hoy, uno con pendientes, el primero vigente */
    function grupoDefecto() {
        var rec = buscarGrupo(leerGrupoGuardado());
        if (rec) return rec;
        var v = vigentes();
        return v.filter(function (g) { return claseDe(g, D.hoy); })[0] ||
            v.filter(function (g) { return pendientes(g).length; })[0] || v[0] || D.grupos[0] || null;
    }

    /* ── Estado de la pantalla ────────────────── */
    var st = { g: null, fecha: null, mes: null, soloSin: false, borrador: {}, original: {}, guardando: false };

    function clase() { return st.g && st.fecha ? claseDe(st.g, st.fecha) : null; }
    function modo() {
        var c = clase();
        if (!c) return 'nada';
        if (st.g.soloConsulta) return 'consulta';
        if (c.estado === 'futura') return 'futura';
        return 'editar';
    }
    function copiar(obj) { var o = {}; Object.keys(obj).forEach(function (k) { o[k] = { estado: obj[k].estado, motivo: obj[k].motivo }; }); return o; }
    function cargarBorrador() {
        st.original = {};
        var regs = (st.g && st.fecha && D.registros(st.g.id, st.fecha)) || {};
        lista().forEach(function (e) {
            var x = regs[e.id];
            if (x) st.original[e.id] = { estado: x.estado, motivo: x.motivo || '' };
        });
        st.borrador = copiar(st.original);
    }
    function hayCambios() {
        if (modo() !== 'editar') return false;
        return lista().some(function (e) {
            var a = st.borrador[e.id], b = st.original[e.id];
            if (!a !== !b) return true;
            if (!a) return false;
            if (a.estado !== b.estado) return true;
            return a.estado === 'excusa' && String(a.motivo).trim() !== String(b.motivo).trim();
        });
    }

    /* ── Elementos ────────────────────────────── */
    var el = {
        vigentes: $('tas-vigentes'), finWrap: $('tas-fin-wrap'), finalizados: $('tas-finalizados'), sinGrupos: $('tas-sin-grupos'),
        resumen: $('tas-resumen'), main: $('tas-main'),
        soloSin: $('tas-solo-sin'), soloNum: $('tas-solo-num'), pend: $('tas-pend'), cal: $('tas-cal'), listaSin: $('tas-lista-sin'), leyenda: $('tas-leyenda'),
        planilla: $('tas-planilla'), titulo: $('tas-pl-titulo'), sub: $('tas-pl-sub'), estado: $('tas-pl-estado'), nota: $('tas-pl-nota'),
        cuerpo: $('tas-pl-cuerpo'), conteo: $('tas-conteo'), tools: $('tas-tools'), todos: $('tas-todos'), todosHint: $('tas-todos-hint'),
        confirmar: $('tas-confirmar'), confirmarTxt: $('tas-confirmar-txt'), confSi: $('tas-conf-si'), confNo: $('tas-conf-no'),
        filas: $('tas-filas'), vacio: $('tas-pl-vacio'),
        barra: $('tas-barra'), error: $('tas-error'), cambios: $('tas-cambios'), faltan: $('tas-faltan'),
        descartar: $('tas-descartar'), guardar: $('tas-guardar'), guardarTxt: $('tas-guardar-txt'),
        dialogo: $('tas-dialogo'), dlgTxt: $('tas-dlg-txt')
    };

    /* El primero que se ve (el calendario oculto conserva sus botones mientras se muestra la lista) */
    function visible(sel) {
        var todos = document.querySelectorAll(sel);
        for (var i = 0; i < todos.length; i++) if (todos[i].offsetParent !== null) return todos[i];
        return null;
    }
    /* Vuelve a pintar sin perder el foco del control que lo disparó */
    function conservarFoco(fn) {
        var a = document.activeElement, sel = null;
        if (a && a !== document.body) {
            if (a.dataset && a.dataset.grupo) sel = '[data-grupo="' + a.dataset.grupo + '"]';
            else if (a.dataset && a.dataset.fecha) sel = '[data-fecha="' + a.dataset.fecha + '"]';
            else if (a.dataset && a.dataset.mes) sel = '[data-mes="' + a.dataset.mes + '"]';
        }
        fn();
        if (sel) {
            var b = visible(sel);
            if (b && !b.disabled) b.focus({ preventScroll: true });
            else if (sel.indexOf('data-mes') > -1) { var otro = document.querySelector('[data-mes]:not(:disabled)'); if (otro) otro.focus({ preventScroll: true }); }
        }
    }

    /* ═══════════ Grupos ═══════════ */
    function tarjeta(g) {
        var sel = st.g && st.g.id === g.id;
        var n = pendientes(g).length;
        var tags = '';
        if (g.soloConsulta) tags += '<span class="app-status-tag app-status-muted">Solo consulta</span>';
        else {
            if (claseDe(g, D.hoy)) tags += '<span class="tas-hoy-tag">Clase hoy</span>';
            tags += n ? '<span class="tas-sin-tag">' + esc(plural(n, 'sin registrar', 'sin registrar')) + '</span>'
                : '<span class="app-status-tag app-status-active">Al día</span>';
        }
        return '<button type="button" class="tas-gcard' + (sel ? ' is-selected' : '') + (g.soloConsulta ? ' is-fin' : '') + '" data-grupo="' + g.id + '" aria-pressed="' + sel + '">' +
            '<span class="tas-gcard-av tas-esc-' + sigla(g.escuela) + '" aria-hidden="true">' + esc(g.nombre.slice(-1)) + '</span>' +
            '<span class="tas-gcard-txt">' +
                '<span class="tas-gcard-prog">' + esc(g.programa) + '</span>' +
                '<span class="tas-gcard-sub">' + esc(g.nombre + ' · ' + g.jornada) + '</span>' +
                '<span class="tas-gcard-tags">' + tags + '</span>' +
            '</span></button>';
    }
    function renderGrupos() {
        var v = vigentes(), f = finalizados();
        el.vigentes.innerHTML = v.length ? v.map(tarjeta).join('') : '<p class="tas-tools-hint">No tienes grupos vigentes.</p>';
        el.finalizados.innerHTML = f.map(tarjeta).join('');
        el.finWrap.hidden = !f.length;
    }

    function dato(lbl, val) { return '<div class="tas-dato"><span class="tas-dato-lbl">' + esc(lbl) + '</span><span class="tas-dato-val">' + val + '</span></div>'; }
    function renderResumen() {
        var g = st.g;
        var registradas = g.clases.filter(function (c) { return c.estado === 'registrada'; }).length;
        var dictadas = registradas + g.clases.filter(function (c) { return c.estado === 'sin_registrar'; }).length;
        var sub = horarioTxt(g) + ' · ' + g.jornada + ' · ' + g.escuela;
        sub += g.soloConsulta ? ' · Finalizó el ' + diaMes(g.fin) : ' · Termina el ' + diaMes(g.fin);
        var html = '<div class="tas-resumen-txt"><h3 class="tas-resumen-ttl">' + esc(g.programa + ' · ' + g.nombre) + '</h3>' +
            '<div class="tas-resumen-sub">' + esc(sub) + '</div></div>' +
            '<div class="tas-datos">' +
                dato('Asistencia del grupo', '<span class="tas-tono-' + tono(g.pct) + '">' + esc(pctTxt(g.pct)) + '</span>') +
                dato(g.soloConsulta ? 'Graduados' : 'Estudiantes activos', esc(g.soloConsulta ? g.graduados : g.activos)) +
                dato('Bajo el ' + D.MINIMO + ' %', '<span class="' + (g.bajoMinimo ? 'tas-tono-bad' : '') + '">' + esc(g.bajoMinimo) + '</span>') +
                dato('Registradas', esc(registradas) + ' <small>de ' + esc(dictadas) + '</small>') +
            '</div>';
        el.resumen.innerHTML = html;
    }

    /* ═══════════ Clases ═══════════ */
    var ESTADO_CLASE = { registrada: 'registrada', sin_registrar: 'sin registrar', hoy: 'hoy, todavía no termina', futura: 'futura, todavía no se puede registrar' };

    function renderClases() {
        var g = st.g, sin = pendientes(g);
        el.soloNum.textContent = sin.length;
        el.soloNum.classList.toggle('is-aviso', sin.length > 0);
        el.soloSin.checked = st.soloSin;

        // Aviso de pendientes (solo con el calendario; la lista ya las muestra todas)
        if (sin.length && !st.soloSin) {
            var masVieja = sin[0];
            var ya = masVieja.fecha === st.fecha;
            el.pend.innerHTML = ICON.alerta + '<p>' + (sin.length === 1 ? 'Tienes 1 clase sin registrar: ' : 'Tienes ' + sin.length + ' clases sin registrar. La más antigua es el ') +
                '<b>' + esc(fechaCorta(masVieja.fecha)) + '</b>.' + (ya ? ' Es la que tienes abierta.' : '') + '</p>' +
                (ya ? '' : '<button type="button" class="tas-btn tas-btn-sm" data-fecha="' + masVieja.fecha + '" data-ir="1">Ir a esa clase</button>');
            el.pend.hidden = false;
        } else {
            el.pend.hidden = true;
            el.pend.innerHTML = '';
        }

        el.cal.hidden = st.soloSin;
        el.leyenda.hidden = st.soloSin;
        el.listaSin.hidden = !st.soloSin;
        if (st.soloSin) renderListaSin(g, sin);
        else renderCalendario(g);
    }

    function renderListaSin(g, sin) {
        if (!sin.length) {
            el.listaSin.innerHTML = '<div class="tas-vacio tas-vacio-chico">' + ICON.check +
                '<p>' + (g.soloConsulta ? 'El grupo ya terminó: no tiene clases por registrar.' : 'Estás al día: todas las clases dictadas de este grupo tienen asistencia.') + '</p></div>';
            return;
        }
        el.listaSin.innerHTML = '<ul class="tas-pend-lista">' + sin.map(function (c) {
            var mod = moduloDe(g, c.fecha);
            var sel = c.fecha === st.fecha;
            return '<li><button type="button" class="tas-pend-btn' + (sel ? ' is-sel' : '') + '" data-fecha="' + c.fecha + '" aria-pressed="' + sel + '">' +
                '<span><span class="tas-pend-fecha">' + esc(mayus(fechaCorta(c.fecha))) + '</span>' +
                '<span class="tas-pend-sub">' + esc(horas(c) + (mod ? ' · ' + mod.m.nombre : '')) + '</span></span>' +
                '<span class="tas-sin-tag">Sin registrar</span></button></li>';
        }).join('') + '</ul>';
    }

    function rangoMeses(g) {
        if (!g.clases.length) return null;
        return { min: g.clases[0].fecha.slice(0, 7), max: g.clases[g.clases.length - 1].fecha.slice(0, 7) };
    }
    function moverMes(mes, delta) {
        var y = +mes.slice(0, 4), m = +mes.slice(5, 7) - 1 + delta;
        var d = new Date(y, m, 1);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1);
    }

    function renderCalendario(g) {
        var r = rangoMeses(g);
        if (!r) {
            el.cal.innerHTML = '<div class="tas-vacio tas-vacio-chico">' + ICON.cal + '<p>Este grupo todavía no tiene clases en el horario.</p></div>';
            return;
        }
        if (!st.mes || st.mes < r.min) st.mes = r.min;
        if (st.mes > r.max) st.mes = r.max;
        var y = +st.mes.slice(0, 4), m = +st.mes.slice(5, 7) - 1;
        var porDia = {};
        g.clases.forEach(function (c) { if (c.fecha.slice(0, 7) === st.mes) porDia[c.fecha] = c; });
        var delMes = Object.keys(porDia).map(function (k) { return porDia[k]; });
        var sinMes = delMes.filter(function (c) { return c.estado === 'sin_registrar'; }).length;
        var resumen = plural(delMes.length, 'clase', 'clases') + (sinMes && !g.soloConsulta ? ' · ' + sinMes + ' sin registrar' : '');

        var primero = new Date(y, m, 1), dias = new Date(y, m + 1, 0).getDate();
        var desfase = (primero.getDay() + 6) % 7; // lunes primero
        var celdas = '';
        for (var b = 0; b < desfase; b++) celdas += '<span aria-hidden="true"></span>';
        for (var dd = 1; dd <= dias; dd++) {
            var iso = y + '-' + pad(m + 1) + '-' + pad(dd);
            var c = porDia[iso];
            var hoy = iso === D.hoy;
            if (!c) { celdas += '<span class="tas-dia" aria-hidden="true">' + dd + '</span>'; continue; }
            var cls = 'tas-dia' + ({ registrada: ' is-reg', sin_registrar: ' is-sin', hoy: ' is-pend', futura: ' is-fut' }[c.estado] || '') + (hoy ? ' is-hoy' : '');
            var sel = iso === st.fecha;
            if (sel) cls += ' is-sel';
            var etiqueta = fechaLarga(iso) + ', ' + horas(c) + ': ' + (hoy && c.estado !== 'hoy' ? 'hoy, ' : '') + ESTADO_CLASE[c.estado];
            if (c.estado === 'futura') {
                celdas += '<button type="button" class="' + cls + '" disabled aria-label="' + esc(etiqueta) + '" title="' + esc(mayus(fechaCorta(iso)) + ': todavía no llega') + '">' + dd + '</button>';
            } else {
                celdas += '<button type="button" class="' + cls + '" data-fecha="' + iso + '" aria-pressed="' + sel + '" aria-label="' + esc(etiqueta) + '"' + (hoy ? ' aria-current="date"' : '') + '>' + dd + '</button>';
            }
        }
        var titulo = MESES[m] + ' ' + y;
        el.cal.innerHTML =
            '<div class="tas-cal-nav">' +
                '<button type="button" class="tas-icon-btn" data-mes="-1" aria-label="Mes anterior"' + (st.mes <= r.min ? ' disabled' : '') + '>' + ICON.izq + '</button>' +
                '<div class="tas-cal-mes" aria-live="polite"><b>' + esc(mayus(titulo)) + '</b><small>' + esc(resumen) + '</small></div>' +
                '<button type="button" class="tas-icon-btn" data-mes="1" aria-label="Mes siguiente"' + (st.mes >= r.max ? ' disabled' : '') + '>' + ICON.der + '</button>' +
            '</div>' +
            '<div class="tas-cal-grid" role="group" aria-label="' + esc('Clases de ' + titulo) + '">' +
                ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map(function (x) { return '<span class="tas-cal-dow" aria-hidden="true">' + x + '</span>'; }).join('') +
                celdas +
            '</div>';
    }

    /* ═══════════ Planilla ═══════════ */
    function chipEstado(c) {
        var out = '';
        if (c.fecha === D.hoy) out += '<span class="tas-hoy-tag">Hoy</span>';
        if (c.estado === 'registrada') out += '<span class="app-status-tag app-status-active">Registrada</span>';
        else if (c.estado === 'sin_registrar') out += st.g.soloConsulta ? '<span class="app-status-tag app-status-muted">Sin registro</span>' : '<span class="tas-sin-tag">Sin registrar</span>';
        else if (c.estado === 'hoy') out += '<span class="app-status-tag app-status-warn">En curso hasta las ' + esc(c.fin) + '</span>';
        else if (c.estado === 'futura') out += '<span class="app-status-tag app-status-muted">Futura</span>';
        if (st.g.soloConsulta) out += '<span class="app-status-tag app-status-muted">Solo consulta</span>';
        return out;
    }
    function nota(tipo, icono, texto) {
        el.nota.className = 'tas-nota' + (tipo ? ' is-' + tipo : '');
        el.nota.innerHTML = icono + '<p>' + texto + '</p>';
        el.nota.hidden = false;
    }

    function renderPlanilla() {
        var g = st.g, c = clase(), md = modo();
        limpiarError();
        cerrarConfirmar();
        if (!c) {
            el.titulo.textContent = 'Sin clases';
            el.sub.textContent = '';
            el.estado.innerHTML = '';
            el.nota.hidden = true;
            el.cuerpo.hidden = true;
            el.barra.hidden = true;
            el.vacio.innerHTML = ICON.cal + '<p>Este grupo todavía no tiene clases en el horario.</p>';
            el.vacio.hidden = false;
            return;
        }
        var mod = moduloDe(g, c.fecha);
        el.titulo.textContent = mayus(fechaLarga(c.fecha));
        el.sub.innerHTML = esc(horas(c)) + (mod ? ' · Módulo ' + mod.n + ' de ' + g.modulos.length + ': <b>' + esc(mod.m.nombre) + '</b>' : '');
        el.estado.innerHTML = chipEstado(c);

        if (md === 'futura') {
            el.nota.hidden = true;
            el.cuerpo.hidden = true;
            el.barra.hidden = true;
            var def = claseDefecto(g);
            el.vacio.innerHTML = ICON.cal + '<p>Esta clase es el ' + esc(fechaLarga(c.fecha)) + ': todavía no se puede registrar.</p>' +
                (def && def !== c.fecha ? '<button type="button" class="tas-btn tas-btn-ghost tas-btn-sm" data-fecha="' + def + '" data-ir="1">Ir a ' + esc(def === D.hoy ? 'la clase de hoy' : 'la clase del ' + fechaCorta(def)) + '</button>' : '');
            el.vacio.hidden = false;
            return;
        }

        var est = lista();
        el.vacio.hidden = true;
        if (md === 'consulta') {
            nota('lock', ICON.candado, '<b>Grupo finalizado: solo consulta.</b> Su asistencia ya no se puede cambiar.');
        } else if (c.estado === 'registrada') {
            nota('ok', ICON.check, '<b>Registrada</b> — puedes corregirla mientras el grupo esté vigente.');
        } else if (c.estado === 'hoy') {
            nota('', ICON.reloj, 'La clase de hoy termina a las ' + esc(c.fin) + '. Puedes ir marcando desde ya y corregir antes de que termine.');
        } else {
            nota('pend', ICON.alerta, 'Esta clase ya se dictó y no tiene asistencia. Márcala para ponerte al día.');
        }

        if (!est.length) {
            el.cuerpo.hidden = true;
            el.barra.hidden = true;
            el.vacio.innerHTML = ICON.personas + '<p>Nadie estaba matriculado en este grupo ese día.</p>';
            el.vacio.hidden = false;
            return;
        }

        el.cuerpo.hidden = false;
        el.tools.hidden = md !== 'editar';
        el.barra.hidden = md !== 'editar';
        el.filas.innerHTML = est.map(function (e) { return filaHtml(e, md !== 'editar'); }).join('');
        actualizarEstado();
    }

    function filaHtml(e, soloVer) {
        var x = st.borrador[e.id];
        var a = e.asistencia || {};
        var salida = e.matricula && SALIDA[e.matricula.estado]
            ? '<span class="tas-salida">' + esc(SALIDA[e.matricula.estado] + (e.matricula.estadoFecha ? ' desde el ' + diaMes(e.matricula.estadoFecha) : '')) + '</span>' : '';
        var idN = 'tas-n-' + e.id;
        var ops = OPCIONES.map(function (o) {
            return '<label class="tas-op tas-op-' + o.l + '">' +
                '<input type="radio" name="tas-e-' + e.id + '" value="' + o.v + '"' + (x && x.estado === o.v ? ' checked' : '') + (soloVer ? ' disabled' : '') + '>' +
                '<span class="tas-op-cara"><span class="tas-celda" aria-hidden="true">' + o.l + '</span>' + o.txt + '</span></label>';
        }).join('');
        var motivo;
        if (soloVer) {
            motivo = '<div class="tas-motivo"' + (x && x.estado === 'excusa' ? '' : ' hidden') + '><span class="tas-motivo-txt"><b>Motivo de la excusa:</b> ' + esc(x && x.motivo ? x.motivo : 'sin motivo') + '</span></div>';
        } else {
            motivo = '<div class="tas-motivo"' + (x && x.estado === 'excusa' ? '' : ' hidden') + '>' +
                '<label class="tas-motivo-lbl" for="tas-m-' + e.id + '">Motivo de la excusa <em>obligatorio</em></label>' +
                '<input type="text" class="tas-input" id="tas-m-' + e.id + '" data-motivo-de="' + e.id + '" maxlength="120" autocomplete="off" placeholder="Ej.: cita médica con soporte" value="' + esc(x && x.motivo ? x.motivo : '') + '">' +
                '<div class="tas-chips" role="group" aria-label="' + esc('Motivos frecuentes para ' + e.nombres) + '">' +
                    MOTIVOS_RAPIDOS.map(function (m) { return '<button type="button" class="tas-chip" data-chip="' + esc(m) + '">' + esc(m) + '</button>'; }).join('') +
                '</div></div>';
        }
        return '<li class="tas-fila' + (x ? '' : ' is-sin-marca') + '" data-est="' + e.id + '">' +
            '<div class="tas-persona">' +
                '<span class="tas-av tas-esc-' + sigla(st.g.escuela) + '" aria-hidden="true">' + esc(iniciales(e.nombres, e.apellidos)) + '</span>' +
                '<div class="tas-persona-txt">' +
                    '<div class="tas-nombre" id="' + idN + '">' + esc(nombreDe(e)) + '</div>' +
                    '<div class="tas-doc"><span>' + esc(e.tipoDoc + ' ' + e.documento) + '</span>' +
                        '<span class="tas-pct tas-tono-' + tono(a.pct) + '" title="' + esc('Asistencia acumulada (referencia: ' + D.MINIMO + ' %)') + '">' +
                            '<span class="tas-sr">Asistencia acumulada: </span>' + esc(pctTxt(a.pct)) + '</span>' + salida + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="tas-opciones" role="radiogroup" aria-labelledby="' + idN + '">' + ops + '</div>' +
            motivo +
            '</li>';
    }

    /* Repinta una fila desde el borrador sin reconstruirla (no pierde el foco) */
    function pintarFila(li) {
        var id = +li.dataset.est, x = st.borrador[id];
        li.classList.toggle('is-sin-marca', !x);
        li.querySelectorAll('input[type="radio"]').forEach(function (r) { r.checked = !!x && r.value === x.estado; });
        var mot = li.querySelector('.tas-motivo');
        if (mot) mot.hidden = !(x && x.estado === 'excusa');
        var inp = li.querySelector('.tas-input');
        if (inp && x && inp.value !== (x.motivo || '')) inp.value = x.motivo || '';
        marcarChips(li);
    }
    function marcarChips(li) {
        var inp = li.querySelector('.tas-input');
        li.querySelectorAll('.tas-chip').forEach(function (ch) {
            var activo = !!inp && inp.value.trim() === ch.dataset.chip;
            ch.classList.toggle('is-activo', activo);
            ch.setAttribute('aria-pressed', String(activo));
        });
    }
    function filas() { return Array.prototype.slice.call(el.filas.querySelectorAll('.tas-fila')); }

    function conteo() {
        var n = { presente: 0, tarde: 0, ausente: 0, excusa: 0, sin: 0 };
        lista().forEach(function (e) { var x = st.borrador[e.id]; if (x) n[x.estado]++; else n.sin++; });
        return n;
    }

    function actualizarEstado() {
        var md = modo();
        if (md !== 'editar' && md !== 'consulta') return;
        var n = conteo(), total = lista().length;
        var pct = D.porcentaje(n.presente + n.tarde, n.ausente);
        var cnt = function (l, v, txt) { return '<span class="tas-cnt"><span class="tas-celda tas-c-' + l + '" aria-hidden="true">' + l + '</span><b>' + v + '</b>' + txt + '</span>'; };
        el.conteo.innerHTML =
            cnt('P', n.presente, n.presente === 1 ? 'Presente' : 'Presentes') +
            cnt('T', n.tarde, n.tarde === 1 ? 'Tarde' : 'Tardes') +
            cnt('A', n.ausente, n.ausente === 1 ? 'Ausente' : 'Ausentes') +
            cnt('E', n.excusa, n.excusa === 1 ? 'Excusa' : 'Excusas') +
            (md === 'editar' ? '<span class="tas-cnt tas-cnt-sin' + (n.sin ? '' : ' is-cero') + '"><b>' + n.sin + '</b>sin marcar</span>' : '') +
            '<span class="tas-cnt-pct">Asistencia de la clase <b class="tas-tono-' + tono(pct) + '">' + esc(pctTxt(pct)) + '</b></span>';

        if (md !== 'editar') return;
        var sucio = hayCambios(), c = clase();

        // Todos presentes
        var noPresentes = total - n.presente - n.sin;
        el.todos.disabled = st.guardando || (n.sin === 0 && noPresentes === 0);
        el.todosHint.textContent = n.sin === total ? 'Marca a todos y luego cambia solo a quien llegó tarde o faltó.'
            : n.sin ? 'Marca como presentes a los ' + n.sin + ' que faltan por marcar.'
            : noPresentes ? 'Todos tienen marca.' : 'Todos están presentes.';

        // Barra
        el.cambios.className = 'tas-cambios' + (sucio ? ' is-sucio' : (c.estado === 'registrada' ? ' is-ok' : ''));
        el.cambios.innerHTML = '<i aria-hidden="true"></i>' + (sucio ? 'Cambios sin guardar' : (c.estado === 'registrada' ? 'Asistencia guardada' : 'Sin registrar todavía'));
        el.faltan.className = 'tas-faltan' + (n.sin ? ' is-aviso' : '');
        el.faltan.textContent = n.sin ? (n.sin === 1 ? 'Falta 1 estudiante por marcar' : 'Faltan ' + n.sin + ' estudiantes por marcar') : 'Todos marcados · ' + plural(total, 'estudiante', 'estudiantes');
        el.descartar.hidden = !sucio;
        el.descartar.disabled = st.guardando;
        el.guardar.disabled = st.guardando || (!sucio && c.estado === 'registrada');
        el.guardarTxt.textContent = st.guardando ? 'Guardando…' : (c.estado === 'registrada' ? 'Guardar corrección' : 'Guardar asistencia');
        el.barra.setAttribute('aria-busy', String(st.guardando));
    }

    /* ── Errores ──────────────────────────────── */
    function limpiarError() {
        el.error.hidden = true;
        el.error.innerHTML = '';
        filas().forEach(function (li) { li.classList.remove('is-invalid'); });
        el.filas.querySelectorAll('.tas-input.is-invalid').forEach(function (i) { i.classList.remove('is-invalid'); i.removeAttribute('aria-invalid'); });
    }
    function mostrarError(msg, estId, que) {
        el.error.innerHTML = ICON.alerta + '<span>' + esc(msg) + '</span>';
        el.error.hidden = false;
        if (!estId) return;
        var li = el.filas.querySelector('.tas-fila[data-est="' + estId + '"]');
        if (!li) return;
        li.classList.add('is-invalid');
        var foco;
        if (que === 'motivo') {
            foco = li.querySelector('.tas-input');
            if (foco) { foco.classList.add('is-invalid'); foco.setAttribute('aria-invalid', 'true'); }
        }
        if (!foco) foco = li.querySelector('input[type="radio"]:checked') || li.querySelector('input[type="radio"]');
        if (foco) {
            foco.focus({ preventScroll: true });
            li.scrollIntoView({ behavior: reducirMov ? 'auto' : 'smooth', block: 'center' });
        }
    }
    /* El servidor nombra al estudiante en el mensaje: se busca para llevar el foco a su fila */
    function filaDeMensaje(msg) {
        var mejor = null, largo = 0;
        lista().forEach(function (e) {
            [nombreDe(e), e.nombres].forEach(function (t) {
                if (msg.indexOf(t) > -1 && t.length > largo) { mejor = e; largo = t.length; }
            });
        });
        if (!mejor) {
            // Solo el primer nombre: sirve si no hay otro estudiante con el mismo
            var palabras = msg.split(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+/);
            var porPrimero = lista().filter(function (e) { return palabras.indexOf(e.nombres.split(' ')[0]) > -1; });
            if (porPrimero.length === 1) mejor = porPrimero[0];
        }
        if (!mejor) return null;
        return { id: mejor.id, que: /motivo|excusa/i.test(msg) ? 'motivo' : 'radio' };
    }

    /* ── Guardar ──────────────────────────────── */
    function guardar() {
        if (st.guardando || modo() !== 'editar') return Promise.resolve(false);
        limpiarError();
        var est = lista();
        var sinMarca = est.filter(function (e) { return !st.borrador[e.id]; });
        if (sinMarca.length) {
            sinMarca.forEach(function (e) { var li = el.filas.querySelector('.tas-fila[data-est="' + e.id + '"]'); if (li) li.classList.add('is-invalid'); });
            mostrarError(sinMarca.length === 1 ? 'Falta marcar a ' + nombreDe(sinMarca[0]) + '.'
                : 'Faltan ' + sinMarca.length + ' estudiantes por marcar. Empieza por ' + nombreDe(sinMarca[0]) + '.', sinMarca[0].id, 'radio');
            return Promise.resolve(false);
        }
        var sinMotivo = est.filter(function (e) { var x = st.borrador[e.id]; return x.estado === 'excusa' && String(x.motivo || '').trim().length < 3; })[0];
        if (sinMotivo) {
            mostrarError('Escribe el motivo de la excusa de ' + sinMotivo.nombres + ' (mínimo 3 letras).', sinMotivo.id, 'motivo');
            return Promise.resolve(false);
        }

        var datos = {};
        est.forEach(function (e) {
            var x = st.borrador[e.id];
            datos[e.id] = { estado: x.estado, motivo: x.estado === 'excusa' ? String(x.motivo).trim() : null };
        });
        st.guardando = true;
        actualizarEstado();
        var gid = st.g.id, fecha = st.fecha;
        return API.guardarAsistencia(gid, fecha, datos).then(function (r) {
            st.guardando = false;
            // DOC_DATOS ya se recalculó: se vuelve a pintar desde ahí
            st.g = buscarGrupo(gid) || st.g;
            cargarBorrador();
            renderTodo();
            toast(r && r.mensaje ? r.mensaje : 'Guardaste la asistencia.');
            el.titulo.focus({ preventScroll: true });
            return true;
        }, function (err) {
            st.guardando = false;
            actualizarEstado();
            var msg = err && err.message ? err.message : 'No se pudo guardar la asistencia. Intenta de nuevo.';
            var f = filaDeMensaje(msg);
            mostrarError(msg, f && f.id, f && f.que);
            if (!f) el.guardar.focus();
            return false;
        });
    }

    /* ── Navegación (con aviso si hay cambios) ── */
    var dlg = { accion: null, origen: null };
    function siHayCambios(accion) {
        if (!hayCambios()) { accion(); return; }
        dlg.accion = accion;
        dlg.origen = document.activeElement;
        el.dlgTxt.textContent = 'Tienes cambios sin guardar en la asistencia del ' + fechaLarga(st.fecha) + '. Si sales de esta clase sin guardarlos, se pierden.';
        el.dialogo.hidden = false;
        el.dialogo.querySelector('[data-dlg="guardar"]').focus();
    }
    function cerrarDialogo(volverFoco) {
        el.dialogo.hidden = true;
        var o = dlg.origen;
        dlg.accion = null; dlg.origen = null;
        if (volverFoco && o && document.body.contains(o) && o.focus) o.focus({ preventScroll: true });
    }
    el.dialogo.addEventListener('click', function (e) {
        var b = e.target.closest('[data-dlg]');
        if (!b) return;
        var que = b.dataset.dlg, accion = dlg.accion;
        if (que === 'cancelar') { cerrarDialogo(true); return; }
        if (que === 'descartar') { cerrarDialogo(false); st.borrador = copiar(st.original); if (accion) accion(); return; }
        // Guardar y seguir; si falla, se queda en la clase con el error a la vista
        cerrarDialogo(false);
        guardar().then(function (ok) { if (ok && accion) accion(); });
    });
    el.dialogo.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { e.preventDefault(); cerrarDialogo(true); return; }
        if (e.key !== 'Tab') return;
        var btns = Array.prototype.slice.call(el.dialogo.querySelectorAll('button'));
        var i = btns.indexOf(document.activeElement);
        if (e.shiftKey && i <= 0) { e.preventDefault(); btns[btns.length - 1].focus(); }
        else if (!e.shiftKey && i === btns.length - 1) { e.preventDefault(); btns[0].focus(); }
    });

    function irAGrupo(id, fecha) {
        var g = buscarGrupo(id);
        if (!g) return;
        st.g = g;
        recordarGrupo(g.id);
        var f = fecha && claseDe(g, fecha) ? fecha : claseDefecto(g);
        abrirClase(f);
    }
    function abrirClase(fecha) {
        st.fecha = fecha;
        if (fecha) st.mes = fecha.slice(0, 7);
        cargarBorrador();
        renderTodo();
        actualizarUrl();
    }
    function actualizarUrl() {
        if (!window.history || !history.replaceState) return;
        var q = '?grupo=' + encodeURIComponent(st.g.id) + (st.fecha ? '&fecha=' + encodeURIComponent(st.fecha) : '');
        try { history.replaceState(null, '', location.pathname + q); } catch (e) { /* sin historial */ }
    }
    function verPlanillaEnMovil() {
        if (!window.matchMedia('(max-width: 1180px)').matches) return;
        el.planilla.scrollIntoView({ behavior: reducirMov ? 'auto' : 'smooth', block: 'start' });
    }

    function renderTodo() {
        if (!st.g) return;
        conservarFoco(function () {
            renderGrupos();
            renderResumen();
            el.resumen.hidden = false;
            el.main.hidden = false;
            renderClases();
            renderPlanilla();
        });
    }

    /* ── Eventos ──────────────────────────────── */
    function alElegirGrupo(e) {
        var b = e.target.closest('[data-grupo]');
        if (!b) return;
        var id = +b.dataset.grupo;
        if (st.g && st.g.id === id) return;
        siHayCambios(function () { irAGrupo(id); });
    }
    el.vigentes.addEventListener('click', alElegirGrupo);
    el.finalizados.addEventListener('click', alElegirGrupo);

    function alElegirClase(e) {
        var mes = e.target.closest('[data-mes]');
        if (mes && !mes.disabled) {
            st.mes = moverMes(st.mes, +mes.dataset.mes);
            conservarFoco(function () { renderCalendario(st.g); });
            return;
        }
        var b = e.target.closest('[data-fecha]');
        if (!b || b.disabled) return;
        var fecha = b.dataset.fecha;
        var irDirecto = !!b.dataset.ir;
        if (fecha === st.fecha) { verPlanillaEnMovil(); return; }
        siHayCambios(function () {
            abrirClase(fecha);
            if (irDirecto) {
                var dia = visible('.tas-dia[data-fecha="' + fecha + '"], .tas-pend-btn[data-fecha="' + fecha + '"]');
                if (dia) dia.focus({ preventScroll: true });
            }
            verPlanillaEnMovil();
        });
    }
    el.cal.addEventListener('click', alElegirClase);
    el.listaSin.addEventListener('click', alElegirClase);
    el.pend.addEventListener('click', alElegirClase);
    el.vacio.addEventListener('click', alElegirClase);

    el.soloSin.addEventListener('change', function () {
        st.soloSin = el.soloSin.checked;
        renderClases();
    });

    // Marcar presente / tarde / ausente / excusa (las flechas del teclado también disparan "change")
    el.filas.addEventListener('change', function (e) {
        var r = e.target;
        if (r.type !== 'radio' || modo() !== 'editar') return;
        var li = r.closest('.tas-fila'), id = +li.dataset.est;
        var antes = st.borrador[id];
        st.borrador[id] = { estado: r.value, motivo: antes ? antes.motivo : '' };
        if (r.value === 'excusa' && !st.borrador[id].motivo && st.original[id] && st.original[id].estado === 'excusa') st.borrador[id].motivo = st.original[id].motivo;
        li.classList.remove('is-invalid');
        pintarFila(li);
        cerrarConfirmar();
        actualizarEstado();
    });
    el.filas.addEventListener('input', function (e) {
        var inp = e.target.closest('[data-motivo-de]');
        if (!inp) return;
        var id = +inp.dataset.motivoDe;
        if (st.borrador[id]) st.borrador[id].motivo = inp.value;
        inp.classList.remove('is-invalid');
        inp.removeAttribute('aria-invalid');
        marcarChips(inp.closest('.tas-fila'));
        actualizarEstado();
    });
    el.filas.addEventListener('click', function (e) {
        var ch = e.target.closest('.tas-chip');
        if (!ch) return;
        var li = ch.closest('.tas-fila'), id = +li.dataset.est, inp = li.querySelector('.tas-input');
        if (!st.borrador[id]) return;
        st.borrador[id].motivo = ch.dataset.chip;
        inp.value = ch.dataset.chip;
        inp.classList.remove('is-invalid');
        inp.removeAttribute('aria-invalid');
        li.classList.remove('is-invalid');
        marcarChips(li);
        actualizarEstado();
        inp.focus();
    });

    // Todos presentes: solo a los que faltan por marcar; si ya todos tienen marca, pregunta antes de cambiarlos
    el.todos.addEventListener('click', function () {
        var est = lista();
        var sin = est.filter(function (e) { return !st.borrador[e.id]; });
        if (sin.length) {
            sin.forEach(function (e) { st.borrador[e.id] = { estado: 'presente', motivo: '' }; });
            filas().forEach(function (li) { li.classList.remove('is-invalid'); pintarFila(li); });
            limpiarError();
            actualizarEstado();
            anunciar(sin.length === 1 ? 'Marcaste 1 estudiante como presente.' : 'Marcaste ' + sin.length + ' estudiantes como presentes.');
            return;
        }
        var otros = est.filter(function (e) { return st.borrador[e.id].estado !== 'presente'; });
        if (!otros.length) return;
        el.confirmarTxt.textContent = 'Todos ya tienen marca. ¿Cambiar también a «Presente» ' +
            (otros.length === 1 ? 'a ' + nombreDe(otros[0]) + ', que está' : 'a los ' + otros.length + ' que están') + ' como tarde, ausente o excusa?';
        el.confirmar.hidden = false;
        el.confSi.focus();
    });
    function cerrarConfirmar(volver) {
        if (el.confirmar.hidden) return;
        el.confirmar.hidden = true;
        if (volver) el.todos.focus();
    }
    el.confNo.addEventListener('click', function () { cerrarConfirmar(true); });
    el.confSi.addEventListener('click', function () {
        lista().forEach(function (e) { st.borrador[e.id] = { estado: 'presente', motivo: '' }; });
        filas().forEach(pintarFila);
        limpiarError();
        actualizarEstado();
        cerrarConfirmar(false);
        if (el.todos.disabled) el.titulo.focus({ preventScroll: true });
        else el.todos.focus();
        anunciar('Todos quedaron como presentes. Recuerda guardar.');
    });
    el.confirmar.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); cerrarConfirmar(true); } });

    el.descartar.addEventListener('click', function () {
        st.borrador = copiar(st.original);
        filas().forEach(pintarFila);
        limpiarError();
        actualizarEstado();
        if (el.guardar.disabled) el.titulo.focus({ preventScroll: true });
        else el.guardar.focus();
        anunciar('Descartaste los cambios.');
    });
    el.guardar.addEventListener('click', function () { guardar(); });

    window.addEventListener('beforeunload', function (e) {
        if (hayCambios()) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ── Arranque: ?grupo=ID&fecha=AAAA-MM-DD ── */
    (function iniciar() {
        if (!D.grupos.length) {
            el.sinGrupos.hidden = false;
            document.querySelector('.tas-grupos').hidden = true;
            return;
        }
        var q = new URLSearchParams(location.search);
        var pg = q.get('grupo'), pf = q.get('fecha');
        if (pf && !/^\d{4}-\d{2}-\d{2}$/.test(pf)) pf = null;

        var g = pg ? buscarGrupo(pg) : null;
        if (pg && !g) toast('Ese grupo no está entre los tuyos. Te mostramos otro de tus grupos.');
        if (!g && pf) {
            // Solo llegó la fecha: el grupo recordado si tiene clase ese día; si no, el primero que la tenga
            var rec = buscarGrupo(leerGrupoGuardado());
            g = (rec && claseDe(rec, pf)) ? rec : (vigentes().concat(finalizados()).filter(function (x) { return claseDe(x, pf); })[0] || null);
        }
        if (!g) g = grupoDefecto();

        if (pf && !claseDe(g, pf)) {
            toast('El ' + fechaLarga(pf) + ' no hay clase de ' + g.programa + ' · ' + g.nombre + '.');
            pf = null;
        }
        st.g = g;
        recordarGrupo(g.id);
        abrirClase(pf || claseDefecto(g));
    })();
})();
