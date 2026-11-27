/* ESAT — Estudiante: Mi programa
   Lee window.EST_DATOS (datos.js: PanelEstudianteService::datos). Solo consulta: notas por módulo,
   asistencia por clase (calendario del mes) y el boletín imprimible (EST_DOC). ?vista=asistencia abre esa pestaña. */
(function () {
    'use strict';

    var D = window.EST_DATOS;
    var app = document.getElementById('mpr-app');
    if (!D || !app) return;

    var URL_AULA = app.dataset.urlAula;
    var DIAS    = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var DIAS_C  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    var MESES   = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var NB = ' ';   // espacio que no parte la línea
    var M = D.matricula, G = M && M.grupo, R = D.reglas;
    // Sin matrícula vigente: solo el aviso
    if (!M) {
        document.getElementById('mpr-vacio').hidden = false;
        document.getElementById('mpr-imprimir').hidden = true;
        app.querySelector('.mpr-toolbar').hidden = true;
        document.getElementById('mpr-panel-notas').hidden = true;
        document.getElementById('mpr-sub').textContent = 'Tus notas por módulo y tu asistencia.';
        return;
    }
    var ESTADO_MAT = { aplazado: 'aplazada', retirado: 'retirada', graduado: 'graduada' };

    /* ── Utilidades ───────────────────────────── */
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function $(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function capital(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
    function fechaLarga(iso) { var f = fecha(iso); return DIAS[f.getDay()] + ' ' + f.getDate() + ' de ' + MESES[f.getMonth()]; }
    function fechaCorta(iso) { var f = fecha(iso); return f.getDate() + NB + MESES_C[f.getMonth()]; }
    function fechaAnio(iso) { var f = fecha(iso); return f.getDate() + ' de ' + MESES[f.getMonth()] + ' de ' + f.getFullYear(); }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }
    function nota(v) { return v == null ? '—' : Number(v).toFixed(1).replace('.', ','); }
    function tonoNota(n) { return n == null ? '' : (n >= 4 ? 'ok' : (n >= R.APRUEBA ? 'warn' : 'bad')); }
    function tonoAsis(p) { return p == null ? '' : (p >= 90 ? 'ok' : (p >= R.MINIMO ? 'warn' : 'bad')); }
    function enlace(base, params) {
        var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        return base + (base.indexOf('?') === -1 ? '?' : '&') + q;
    }
    function moduloDe(id) { return D.modulos.filter(function (m) { return m.id === id; })[0] || null; }

    /* ── Encabezado y resumen ─────────────────── */
    function pintarEncabezado() {
        $('mpr-sub').textContent = M.programa + (M.nivel ? ' · ' + M.nivel : '') +
            (G ? ' · ' + G.nombre + ', jornada ' + G.jornada.toLowerCase() + '. Del ' + fechaAnio(G.inicio) + ' al ' + fechaAnio(G.fin) + '.' : '.') +
            (ESTADO_MAT[M.estado] ? ' Tu matrícula está ' + ESTADO_MAT[M.estado] + (M.estadoFecha ? ' desde el ' + fechaAnio(M.estadoFecha) : '') + '.' : '');
        // La asistencia tiene su cifra grande en su pestaña: aquí no se repite
        $('mpr-resumen').innerHTML =
            'Promedio <b class="mpr-tono-' + tonoNota(D.promedio) + '">' + esc(nota(D.promedio)) + '</b><i aria-hidden="true">·</i>' +
            '<b>' + D.aprobados + '</b> de ' + D.modulos.length + ' módulos aprobados';
    }

    /* ── Notas por módulo ─────────────────────── */
    var ESTADO = {
        aprobado:      ['app-status-active', 'Aprobado'],
        habilito:      ['app-status-active', 'Aprobado en habilitación'],
        por_habilitar: ['app-status-danger', 'Por habilitar'],
        reprobado:     ['app-status-danger', 'Reprobado'],
        sin_nota:      ['app-status-muted', 'Sin nota todavía'],
        en_curso:      ['mpr-chip-curso', 'En curso'],
        por_iniciar:   ['app-status-muted', 'Por iniciar'],
        no_cursado:    ['app-status-muted', 'No lo cursaste aquí']
    };
    function aulaDe(m) {
        var ev = D.evaluaciones.filter(function (e) { return e.moduloId === m.id; }).length;
        var ac = D.actividades.filter(function (a) { return a.moduloId === m.id; }).length;
        if (!ev && !ac) return '';
        var partes = [];
        if (ev) partes.push(plural(ev, 'evaluación', 'evaluaciones'));
        if (ac) partes.push(plural(ac, 'actividad', 'actividades'));
        return '<a class="mpr-mod-aula" href="' + esc(enlace(URL_AULA, { modulo: m.id })) + '">' +
            '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>' +
            'En el Aula: ' + esc(partes.join(' y ')) + '</a>';
    }
    function pintarMalla() {
        $('mpr-malla').innerHTML = D.modulos.map(function (m) {
            var e = m.estadoNota, tag = ESTADO[e] || ESTADO.sin_nota;
            var clase = e === 'aprobado' || e === 'habilito' ? 'is-bien' : (e === 'por_habilitar' || e === 'reprobado' ? 'is-mal' : (e === 'en_curso' ? 'is-actual' : (e === 'por_iniciar' ? 'is-futuro' : '')));
            var txt = '';
            if (e === 'en_curso') {
                var clases = D.clases.filter(function (c) { return c.moduloId === m.id; });
                var hechas = clases.filter(function (c) { return c.estado !== 'futura' && c.estado !== 'hoy'; }).length;
                var pct = clases.length ? Math.round(hechas * 100 / clases.length) : 0;
                txt = '<div class="mpr-mod-av"><span class="mpr-barra" aria-hidden="true"><span style="width:' + pct + '%"></span></span>' +
                    esc('Van ' + hechas + ' de ' + clases.length + ' clases') + '</div>';
            } else if (e === 'sin_nota') {
                txt = '<p class="mpr-mod-txt">Terminó el ' + esc(fechaCorta(m.fin)) + '. Tu docente todavía no pone la nota.</p>';
            } else if (e === 'por_habilitar') {
                // La habilitación es después de que el módulo termina: mientras sigue, la nota todavía puede cambiar
                txt = m.estado === 'terminado'
                    ? '<p class="mpr-mod-txt">Lo perdiste con <b>' + esc(nota(m.n)) + '</b>. Tienes una habilitación: tu docente te dirá la fecha.</p>'
                    : '<p class="mpr-mod-txt">Tu nota va en <b>' + esc(nota(m.n)) + '</b>. Si el módulo termina por debajo de 3,0, tendrás una habilitación.</p>';
            } else if (e === 'habilito') {
                txt = '<p class="mpr-mod-txt">Lo perdiste con ' + esc(nota(m.n)) + ' y lo aprobaste en la habilitación el ' + esc(fechaCorta(m.fechaHab)) + '.</p>';
            } else if (e === 'no_cursado') {
                // No coincide con las fechas en que estuvo en el grupo (llegó después, o estaba aplazado o retirado)
                txt = '<p class="mpr-mod-txt">' + (m.fin && m.fin < D.hoy
                    ? 'Pasó cuando no estabas en este grupo: antes de que llegaras o mientras tu matrícula no estaba activa.'
                    : 'No lo cursas mientras tu matrícula no esté activa.') + '</p>';
            } else if (e === 'reprobado') {
                txt = '<p class="mpr-mod-txt">No aprobaste la habilitación. El administrativo te dirá cómo repetir el módulo.</p>';
            }
            var der = m.nota != null
                ? '<span class="mpr-nota mpr-tono-' + tonoNota(m.nota) + '">' + esc(nota(m.nota)) + '</span>'
                : '';
            var fechas = !m.ini ? 'Sin fechas: tu grupo no tiene horario' : (e === 'por_iniciar' ? 'Empieza el ' + fechaCorta(m.ini) : fechaCorta(m.ini) + ' – ' + fechaCorta(m.fin));
            return '<li class="mpr-mod ' + clase + '">' +
                '<span class="mpr-mod-num" aria-hidden="true">' + m.numero + '</span>' +
                '<div class="mpr-mod-info">' +
                    '<h3 class="mpr-mod-nombre"><span class="sr-solo">Módulo ' + m.numero + ': </span>' + esc(m.nombre) + '</h3>' +
                    '<span class="mpr-mod-sub">' + esc(fechas + ' · ' + m.horas + ' h') + '</span>' +
                    txt + aulaDe(m) +
                '</div>' +
                '<div class="mpr-mod-der">' + der + '<span class="app-status-tag ' + tag[0] + '">' + tag[1] + '</span></div>' +
            '</li>';
        }).join('');
    }

    /* ── Asistencia ───────────────────────────── */
    var ESTADO_CLASE = { P: 'Presente', T: 'Llegó tarde', A: 'Ausente', E: 'Ausente con excusa', sin_registrar: 'Sin registro del docente', hoy: 'Clase de hoy', futura: 'Por venir' };
    function pintarAsisTop() {
        var A = D.asistencia, t = tonoAsis(A.pct);
        var asistio = A.P + A.T, cuentan = A.P + A.T + A.A;
        var extra = [];
        if (A.E) extra.push((A.E === 1 ? 'La excusa no cuenta' : 'Las ' + A.E + ' excusas no cuentan') + ' como falla');
        if (A.sinRegistrar) extra.push(plural(A.sinRegistrar, 'clase no tiene', 'clases no tienen') + ' registro del docente');
        $('mpr-asis-top').innerHTML =
            '<span class="mpr-asis-pct mpr-tono-' + t + '">' + (A.pct == null ? '—' : A.pct + NB + '%') + '</span>' +
            '<div class="mpr-asis-medio">' +
                '<div class="mpr-meter mpr-tono-' + t + '" aria-hidden="true"><span style="width:' + Math.max(0, Math.min(100, A.pct || 0)) + '%"></span></div>' +
                '<p class="mpr-asis-txt">Asististe a <b>' + asistio + ' de ' + cuentan + '</b> clases' + (A.T ? ' (' + plural(A.T, 'vez', 'veces') + ' llegaste tarde)' : '') + '.' +
                    (extra.length ? ' ' + esc(extra.join(' y ')) + '.' : '') + '</p>' +
                '<p class="mpr-asis-nota">La asistencia es informativa: no bloquea nada. La referencia es el 80' + NB + '%.</p>' +
            '</div>';
        // El color del medidor sigue al del porcentaje
        var meter = $('mpr-asis-top').querySelector('.mpr-meter');
        meter.style.color = t === 'ok' ? '#2D7A3A' : (t === 'warn' ? '#B45309' : (t === 'bad' ? '#B91C1C' : '#94A3B8'));
    }

    var porFecha = {};
    D.clases.forEach(function (c) { porFecha[c.fecha] = c; });
    // Desde la primera clase que cuenta (puede ser de su grupo anterior) o el inicio de su grupo, lo que sea antes
    var desdeCal = D.clases.length && (!G || D.clases[0].fecha < G.inicio) ? D.clases[0].fecha : (G ? G.inicio : D.hoy);
    var primerMes = fecha(desdeCal); primerMes = new Date(primerMes.getFullYear(), primerMes.getMonth(), 1);
    var ultimoMes = fecha(G ? G.fin : D.hoy); ultimoMes = new Date(ultimoMes.getFullYear(), ultimoMes.getMonth(), 1);
    var mes = fecha(D.hoy); mes = new Date(mes.getFullYear(), mes.getMonth(), 1);
    if (mes < primerMes) mes = primerMes;
    if (mes > ultimoMes) mes = ultimoMes;
    var seleccion = null;
    (function () {
        var hechas = D.clases.filter(function (c) { return c.estado !== 'futura' && c.estado !== 'hoy'; });
        seleccion = hechas.length ? hechas[hechas.length - 1].fecha : (D.clases[0] && D.clases[0].fecha);
    })();

    function pintarCalendario(enfocar) {
        var anio = mes.getFullYear(), m = mes.getMonth();
        $('mpr-cal-mes').textContent = capital(MESES[m]) + ' de ' + anio;
        var delMes = D.clases.filter(function (c) { var f = fecha(c.fecha); return f.getFullYear() === anio && f.getMonth() === m; });
        var hechas = delMes.filter(function (c) { return c.estado !== 'futura' && c.estado !== 'hoy'; }).length;
        $('mpr-cal-cuenta').textContent = delMes.length ? plural(delMes.length, 'clase', 'clases') + (hechas < delMes.length ? ' · ' + (delMes.length - hechas) + ' por venir' : '') : 'Sin clases este mes';
        $('mpr-cal-ant').disabled = mes <= primerMes;
        $('mpr-cal-sig').disabled = mes >= ultimoMes;

        var html = ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map(function (d) { return '<span class="mpr-cal-dow" aria-hidden="true">' + d + '</span>'; }).join('');
        var primero = new Date(anio, m, 1), desfase = (primero.getDay() + 6) % 7, dias = new Date(anio, m + 1, 0).getDate();
        for (var i = 0; i < desfase; i++) html += '<span class="mpr-dia" aria-hidden="true"></span>';
        for (var d = 1; d <= dias; d++) {
            var iso = ymd(new Date(anio, m, d)), c = porFecha[iso];
            var hoyCls = iso === D.hoy ? ' is-hoy' : '';
            if (!c) { html += '<span class="mpr-dia' + hoyCls + '">' + d + '</span>'; continue; }
            var est = c.estado === 'hoy' ? 'hoy-clase' : c.estado;
            var etiqueta = capital(fechaLarga(iso)) + ': ' + ESTADO_CLASE[c.estado];
            html += '<button type="button" class="mpr-dia is-' + est + hoyCls + (iso === seleccion ? ' is-sel' : '') + '" data-fecha="' + iso + '"' +
                ' aria-label="' + esc(etiqueta) + '" aria-pressed="' + (iso === seleccion ? 'true' : 'false') + '">' + d + '</button>';
        }
        $('mpr-cal-grid').innerHTML = html;
        pintarDetalle();
        if (enfocar) { var b = $('mpr-cal-grid').querySelector('.is-sel'); if (b) b.focus(); }
    }
    function pintarDetalle() {
        var c = seleccion && porFecha[seleccion];
        var f = c && fecha(c.fecha);
        if (!c || f.getFullYear() !== mes.getFullYear() || f.getMonth() !== mes.getMonth()) { $('mpr-detalle').innerHTML = ''; return; }
        var mod = moduloDe(c.moduloId);
        var tag = /^[PTAE]$/.test(c.estado) ? '<span class="app-status-tag mpr-st-' + c.estado + '">' + ESTADO_CLASE[c.estado] + '</span>'
            : '<span class="app-status-tag app-status-muted">' + ESTADO_CLASE[c.estado] + '</span>';
        $('mpr-detalle').innerHTML =
            '<div><p class="mpr-detalle-fecha">' + esc(capital(fechaLarga(c.fecha))) + ' · ' + esc(c.ini + NB + '–' + NB + c.fin) + '</p>' +
                '<p class="mpr-detalle-sub">' + esc(mod ? 'Módulo ' + mod.numero + ': ' + mod.nombre : '') + '</p>' +
                (c.motivo ? '<p class="mpr-detalle-motivo">Excusa: ' + esc(c.motivo) + '</p>' : '') +
                (c.estado === 'sin_registrar' ? '<p class="mpr-detalle-sub">Tu docente todavía no registra la asistencia de esta clase; no cuenta en tu porcentaje.</p>' : '') +
            '</div>' + tag;
    }
    $('mpr-cal-grid').addEventListener('click', function (e) {
        var b = e.target.closest('button.mpr-dia');
        if (!b) return;
        seleccion = b.dataset.fecha;
        pintarCalendario(true);
    });
    $('mpr-cal-ant').addEventListener('click', function () { mes = new Date(mes.getFullYear(), mes.getMonth() - 1, 1); pintarCalendario(); });
    $('mpr-cal-sig').addEventListener('click', function () { mes = new Date(mes.getFullYear(), mes.getMonth() + 1, 1); pintarCalendario(); });

    function pintarNovedades() {
        var lista = D.clases.filter(function (c) { return c.estado === 'A' || c.estado === 'T' || c.estado === 'E'; }).reverse();
        var mas = $('mpr-nov-mas');
        if (!lista.length) { $('mpr-nov-lista').innerHTML = '<li class="mpr-nov-vacia">No tienes faltas ni llegadas tarde.</li>'; mas.hidden = true; return; }
        mas.hidden = lista.length <= MAX_NOV;
        mas.dataset.mas = lista.length - MAX_NOV;
        mas.textContent = 'Ver ' + (lista.length - MAX_NOV) + ' más';
        mas.setAttribute('aria-expanded', 'false');
        $('mpr-nov-lista').innerHTML = lista.map(function (c, i) {
            var f = fecha(c.fecha), mod = moduloDe(c.moduloId);
            return '<li class="mpr-nov"' + (i >= MAX_NOV ? ' data-extra hidden' : '') + '>' +
                '<span class="mpr-nov-fecha" aria-hidden="true"><small>' + MESES_C[f.getMonth()] + '</small><b>' + f.getDate() + '</b></span>' +
                '<div class="mpr-nov-info">' +
                    '<div class="mpr-nov-top"><span class="mpr-nov-dia">' + esc(capital(fechaLarga(c.fecha))) + '</span>' +
                        '<span class="app-status-tag mpr-st-' + c.estado + '">' + ESTADO_CLASE[c.estado] + '</span></div>' +
                    '<span class="mpr-nov-sub">' + esc((c.motivo ? 'Excusa: ' + c.motivo + ' · ' : '') + (mod ? 'Módulo ' + mod.numero : '')) + '</span>' +
                '</div>' +
            '</li>';
        }).join('');
    }

    var MAX_NOV = 6;
    $('mpr-nov-mas').addEventListener('click', function () {
        var btn = this, abierto = btn.getAttribute('aria-expanded') === 'true';
        $('mpr-nov-lista').querySelectorAll('[data-extra]').forEach(function (li) { li.hidden = abierto; });
        btn.setAttribute('aria-expanded', abierto ? 'false' : 'true');
        btn.textContent = abierto ? 'Ver ' + btn.dataset.mas + ' más' : 'Ver menos';
    });

    /* ── Pestañas ─────────────────────────────── */
    var tabs = Array.prototype.slice.call(app.querySelectorAll('.mpr-vista-btn'));
    function mostrar(v, enfocar) {
        tabs.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            if (on && enfocar) b.focus();
        });
        $('mpr-panel-notas').hidden = v !== 'notas';
        $('mpr-panel-asistencia').hidden = v !== 'asistencia';
        try {
            var u = new URL(window.location.href);
            if (v === 'asistencia') u.searchParams.set('vista', v); else u.searchParams.delete('vista');
            window.history.replaceState(null, '', u.pathname + u.search);
        } catch (err) { /* sin History API */ }
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

    /* ── Boletín imprimible ───────────────────── */
    var ESTADO_MAT_DOC = { aplazado: 'Aplazada', retirado: 'Retirada', graduado: 'Graduada' };
    var ESTADO_DOC = { aprobado: 'Aprobado', habilito: 'Aprobado (habilitación)', por_habilitar: 'Por habilitar', reprobado: 'Reprobado', sin_nota: 'Sin nota', en_curso: 'En curso', por_iniciar: 'Por iniciar', no_cursado: 'No cursado' };
    $('mpr-imprimir').addEventListener('click', function () {
        var E = D.estudiante, A = D.asistencia, e = window.EST_DOC.esc;
        var filas = D.modulos.map(function (m) {
            return '<tr><td>' + m.numero + '</td><td>' + e(m.nombre) + '</td><td class="rc-num">' + m.horas + '</td>' +
                '<td class="rc-num">' + (m.n != null ? e(nota(m.n)) : '—') + '</td><td class="rc-num">' + (m.h != null ? e(nota(m.h)) : '—') + '</td>' +
                '<td>' + e(ESTADO_DOC[m.estadoNota] || '') + '</td></tr>';
        }).join('');
        window.EST_DOC.imprimir({
            titulo: 'Boletín de notas',
            numero: M.codigo,
            extra: 'Generado el ' + fechaAnio(D.hoy),
            contenido:
                '<div class="rc-datos">' +
                    '<div><span>Estudiante</span><strong>' + e((E.nombres + ' ' + E.apellidos).trim()) + '</strong></div>' +
                    '<div><span>Documento</span><strong>' + e(E.documento || '—') + '</strong></div>' +
                    '<div><span>Programa</span><strong>' + e(M.programa + ' (' + M.nivel + ')') + '</strong></div>' +
                    '<div><span>Grupo</span><strong>' + e(G ? G.nombre + ' · ' + G.codigo + ' · ' + G.jornada : '—') + '</strong></div>' +
                    '<div><span>Docente</span><strong>' + e(G && G.docente ? G.docente : '—') + '</strong></div>' +
                    '<div><span>Clases</span><strong>' + e(G ? 'Del ' + fechaAnio(G.inicio) + ' al ' + fechaAnio(G.fin) : '—') + '</strong></div>' +
                    (M.estado !== 'activo' ? '<div><span>Matrícula</span><strong>' + e((ESTADO_MAT_DOC[M.estado] || M.estado) + (M.estadoFecha ? ' desde el ' + fechaAnio(M.estadoFecha) : '')) + '</strong></div>' : '') +
                '</div>' +
                '<table class="rc-tabla"><thead><tr><th>#</th><th>Módulo</th><th class="rc-num">Horas</th><th class="rc-num">Nota</th><th class="rc-num">Habilitación</th><th>Estado</th></tr></thead>' +
                '<tbody>' + filas + '</tbody>' +
                '<tfoot><tr><td></td><td>Promedio de los módulos con nota</td><td></td><td class="rc-num">' + e(nota(D.promedio)) + '</td><td></td><td></td></tr></tfoot></table>' +
                '<div class="rc-datos"><div class="rc-full"><span>Asistencia</span><strong>' +
                    e((A.pct == null ? '—' : A.pct + ' %') + ' · asistió a ' + (A.P + A.T) + ' de ' + (A.P + A.T + A.A) + ' clases' + (A.E ? ' · ' + plural(A.E, 'falta con excusa', 'faltas con excusa') : '')) +
                '</strong></div></div>' +
                '<p class="rc-nota">Boletín informativo generado desde el panel del estudiante. Se aprueba con 3,0. No reemplaza el certificado de notas que expide la administración de la sede.</p>'
        });
    });

    pintarEncabezado();
    pintarMalla();
    pintarAsisTop();
    pintarCalendario();
    pintarNovedades();
    if (/[?&]vista=asistencia\b/.test(window.location.search)) mostrar('asistencia');
})();
