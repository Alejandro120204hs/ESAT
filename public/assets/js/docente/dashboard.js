/* ESAT — Docente: Inicio y mi horario
   Solo frontend: lee window.DOC_DATOS (datos-ejemplo.js) y no guarda nada. Cada pendiente lleva a la
   pantalla que lo resuelve (Asistencia, Notas o Mis grupos) con el grupo y la fecha o el módulo en la URL.
   Solo cuentan los grupos sin finalizar: los finalizados son historial. */
(function () {
    'use strict';

    var D   = window.DOC_DATOS;
    var app = document.getElementById('ini-app');
    if (!D || !app) return;

    var URL_ASIS   = app.dataset.urlAsistencia;
    var URL_NOTAS  = app.dataset.urlNotas;
    var URL_GRUPOS = app.dataset.urlGrupos;

    var DIAS    = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var DIAS_C  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    var MESES   = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var SEMANA  = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    var JORNADAS = ['Mañana', 'Tarde', 'Noche', 'Fines de semana'];
    var JORNADA_CLS = { 'Mañana': 'manana', 'Tarde': 'tarde', 'Noche': 'noche', 'Fines de semana': 'fds' };

    var NB       = ' '; // espacio que no parte la línea
    var MAX_PEND = 4;    // pendientes visibles por bloque antes de "Ver N más"
    var HORA_PX  = 46;   // alto de una hora en la grilla semanal
    var CORTE_PX = 30;   // alto de la franja que recorta las horas sin clases

    var ICON = {
        ir:     '<svg class="ini-pend-ir" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>',
        check:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        lapiz:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
        grupo:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
    };

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function $(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fecha(iso) { var p = String(iso).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function masDias(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
    function diaIso(d) { var w = d.getDay(); return w === 0 ? 7 : w; }
    function minutos(hhmm) { var p = String(hhmm).split(':'); return +p[0] * 60 + +p[1]; }
    function hhmm(min) { return pad(Math.floor(min / 60)) + ':' + pad(min % 60); }
    function ahoraMin() { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
    function capital(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
    function fechaLarga(iso) { var f = fecha(iso); return DIAS[f.getDay()] + ' ' + f.getDate() + ' de ' + MESES[f.getMonth()]; }
    // Con espacios que no parten la línea: "mié 21 oct" y "07:00 – 10:00" no se cortan a la mitad
    function fechaCorta(iso) { var f = fecha(iso); return DIAS_C[f.getDay()] + NB + f.getDate() + NB + MESES_C[f.getMonth()]; }
    function rango(ini, fin) { return ini + NB + '–' + NB + fin; }
    function cuandoLargo(iso) {
        var n = diasEntre(D.hoy, iso);
        return n === 1 ? 'mañana, ' + fechaLarga(iso) : fechaLarga(iso);
    }
    function diasEntre(a, b) { return Math.round((fecha(b) - fecha(a)) / 86400000); }
    function hace(iso) {
        var n = diasEntre(iso, D.hoy);
        return n <= 0 ? 'hoy' : (n === 1 ? 'ayer' : 'hace' + NB + n + NB + 'días');
    }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }
    function duracion(min) {
        var h = Math.floor(min / 60), m = min % 60;
        return h && m ? h + ' h ' + m + ' min' : (h ? h + ' h' : m + ' min');
    }
    function nota(v) { return v == null ? '—' : Number(v).toFixed(1).replace('.', ','); }
    function iniciales(n, a) { return ((n || '').charAt(0) + (a || '').charAt(0)).toUpperCase(); }
    function programaCorto(p) { return String(p || '').replace(/^Técnico laboral (por competencias )?en /i, ''); }
    function jClase(j) { return 'ini-j-' + (JORNADA_CLS[j] || 'otra'); }
    function grupoTxt(g) { return g.programa + ' · ' + String(g.nombre).replace(/ /g, NB); }
    function enlace(base, params) {
        var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        return base + (base.indexOf('?') === -1 ? '?' : '&') + q;
    }

    /* ── Datos derivados ──────────────────────── */
    function vigentes() {
        return D.grupos.filter(function (g) { return !g.soloConsulta && g.estado !== 'finalizado'; });
    }
    function moduloEn(g, iso) {
        return (g.modulos || []).filter(function (m) { return m.ini <= iso && iso <= m.fin; })[0] || g.moduloActual || null;
    }
    function clasesHoy() {
        var out = [];
        vigentes().forEach(function (g) {
            (g.clases || []).forEach(function (c) { if (c.fecha === D.hoy) out.push({ g: g, c: c }); });
        });
        return out.sort(function (a, b) { return minutos(a.c.ini) - minutos(b.c.ini); });
    }
    // Estado de una clase de hoy según DOC_DATOS y la hora actual (la página puede quedar abierta)
    function estadoClase(c) {
        if (c.estado === 'registrada') return 'registrada';
        if (c.estado === 'sin_registrar') return 'por_registrar';
        var m = ahoraMin();
        if (m >= minutos(c.fin)) return 'por_registrar';
        if (m >= minutos(c.ini)) return 'en_curso';
        return 'mas_tarde';
    }
    var CHIP = {
        en_curso:      ['ini-chip-curso', 'En curso'],
        mas_tarde:     ['app-status-muted', 'Más tarde'],
        por_registrar: ['app-status-warn', 'Por registrar'],
        registrada:    ['app-status-active', 'Registrada']
    };
    function proximaClase() {
        var m = ahoraMin(), mejor = null;
        vigentes().forEach(function (g) {
            (g.clases || []).forEach(function (c) {
                if (c.fecha < D.hoy || (c.fecha === D.hoy && minutos(c.ini) <= m)) return;
                if (!mejor || c.fecha < mejor.c.fecha || (c.fecha === mejor.c.fecha && minutos(c.ini) < minutos(mejor.c.ini))) {
                    mejor = { g: g, c: c };
                }
            });
        });
        return mejor;
    }
    function pendientes() {
        var asis = [], notas = [], hab = [];
        vigentes().forEach(function (g) {
            (g.sinRegistrar || []).forEach(function (f) {
                var c = (g.clases || []).filter(function (x) { return x.fecha === f; })[0] || null;
                asis.push({ g: g, fecha: f, c: c });
            });
            (g.modulosSinNota || []).forEach(function (x) { notas.push({ g: g, m: x.modulo, faltan: x.faltan }); });
            (g.estudiantes || []).forEach(function (e) {
                if (!e.matricula || e.matricula.estado !== 'activo') return;
                (g.modulos || []).forEach(function (m) {
                    var n = e.notas && e.notas[m.id];
                    if (n && n.estado === 'por_habilitar') hab.push({ g: g, m: m, e: e, n: n.n });
                });
            });
        });
        // Lo más viejo primero: es lo que más urge
        asis.sort(function (a, b) { return a.fecha < b.fecha ? -1 : (a.fecha > b.fecha ? 1 : 0); });
        notas.sort(function (a, b) { return a.m.fin < b.m.fin ? -1 : (a.m.fin > b.m.fin ? 1 : 0); });
        hab.sort(function (a, b) { return a.m.fin < b.m.fin ? -1 : (a.m.fin > b.m.fin ? 1 : a.e.apellidos.localeCompare(b.e.apellidos, 'es')); });
        return { asis: asis, notas: notas, hab: hab, total: asis.length + notas.length + hab.length };
    }
    function paraAcompanar() {
        var out = [];
        vigentes().forEach(function (g) {
            (g.estudiantes || []).forEach(function (e) {
                if (e.matricula && e.matricula.estado === 'activo' && e.asistencia && e.asistencia.situacion === 'fallas') out.push({ g: g, e: e });
            });
        });
        return out.sort(function (a, b) { return a.e.asistencia.pct - b.e.asistencia.pct; });
    }

    /* ── Encabezado y KPIs ────────────────────── */
    function pintarEncabezado() {
        $('ini-fecha').textContent = capital(fechaLarga(D.hoy));

        var n = clasesHoy().length, p = pendientes().total, txt;
        var cosas = plural(p, 'cosa pendiente', 'cosas pendientes');
        if (n && p)       txt = 'Hoy tienes <b>' + esc(plural(n, 'clase', 'clases')) + '</b> y <b>' + esc(cosas) + '</b>.';
        else if (n)       txt = 'Hoy tienes <b>' + esc(plural(n, 'clase', 'clases')) + '</b> y estás al día con tus registros.';
        else if (p)       txt = 'Hoy no tienes clases. Te quedan <b>' + esc(cosas) + '</b>.';
        else              txt = 'Hoy no tienes clases y estás al día.';
        $('ini-resumen').innerHTML = txt;
    }

    function kpi(id, valor, etiqueta) {
        var el = $(id);
        el.querySelector('[data-kpi-val]').textContent = valor;
        el.querySelector('[data-kpi-lbl]').textContent = etiqueta;
        el.classList.toggle('is-cero', !valor);
    }
    function pintarKpis() {
        var gs = vigentes(), p = pendientes();
        var asis = gs.reduce(function (s, g) { return s + (g.sinRegistrar || []).length; }, 0);
        var notas = gs.reduce(function (s, g) {
            return s + (g.modulosSinNota || []).reduce(function (t, x) { return t + x.faltan; }, 0);
        }, 0);
        var hab = gs.reduce(function (s, g) { return s + (g.porHabilitar || 0); }, 0);

        kpi('ini-kpi-hoy', clasesHoy().length, 'Clases hoy');
        kpi('ini-kpi-asis', asis, 'Asistencias sin registrar');
        kpi('ini-kpi-notas', notas, 'Notas por poner' + (p.notas.length ? ' · ' + plural(p.notas.length, 'módulo', 'módulos') : ''));
        kpi('ini-kpi-hab', hab, 'Por habilitar');
    }

    /* ── Clases de hoy ────────────────────────── */
    var firmaHoy = '';

    function cuentaTxt(c, est) {
        var m = ahoraMin();
        if (est === 'en_curso')  return 'Termina en ' + duracion(Math.max(1, minutos(c.fin) - m));
        if (est === 'mas_tarde') return 'Empieza en ' + duracion(Math.max(1, minutos(c.ini) - m));
        return '';
    }

    function claseHtml(x) {
        var g = x.g, c = x.c, est = estadoClase(c), chip = CHIP[est];
        var mod = moduloEn(g, D.hoy);
        var n = D.planilla(g, D.hoy).length;
        var href = enlace(URL_ASIS, { grupo: g.id, fecha: c.fecha });
        var btn = est === 'registrada'
            ? '<a class="ini-btn ini-btn-ghost" href="' + esc(href) + '" aria-label="' + esc('Ver o corregir la asistencia de ' + grupoTxt(g)) + '">' + ICON.lapiz + 'Ver o corregir</a>'
            : '<a class="ini-btn ini-btn-primary" href="' + esc(href) + '" aria-label="' + esc('Tomar asistencia de ' + grupoTxt(g)) + '">' + ICON.check + 'Tomar asistencia</a>';

        return '<li class="ini-clase ' + jClase(g.jornada) + ' is-' + est + '">' +
            '<div class="ini-clase-hora">' +
                '<span class="ini-clase-ini">' + esc(c.ini) + '</span>' +
                '<span class="ini-clase-fin">hasta ' + esc(c.fin) + '</span>' +
            '</div>' +
            '<div class="ini-clase-info">' +
                '<div class="ini-clase-tags">' +
                    '<span class="app-status-tag ' + chip[0] + '">' + chip[1] + '</span>' +
                    '<span class="ini-jornada">' + esc(g.jornada) + '</span>' +
                '</div>' +
                '<h4 class="ini-clase-prog">' + esc(grupoTxt(g)) + '</h4>' +
                '<p class="ini-clase-meta">' +
                    (mod ? 'Módulo en curso: <b>' + esc(mod.nombre) + '</b> · ' : '') +
                    esc(plural(n, 'estudiante', 'estudiantes')) + ' en la lista' +
                '</p>' +
                '<p class="ini-clase-cuenta" data-ini="' + esc(c.ini) + '" data-fin="' + esc(c.fin) + '" data-est="' + est + '">' + esc(cuentaTxt(c, est)) + '</p>' +
            '</div>' +
            btn +
        '</li>';
    }

    function proximaHtml(x, conBoton) {
        var cuando = capital(cuandoLargo(x.c.fecha));
        return '<div class="ini-proxima-txt">' +
                '<div class="ini-proxima-lbl">Tu próxima clase</div>' +
                '<div class="ini-proxima-cuando">' + esc(cuando) + ' · ' + esc(rango(x.c.ini, x.c.fin)) + '</div>' +
                '<div class="ini-proxima-que">' + esc(grupoTxt(x.g)) + ' · ' + esc(x.g.jornada) + '</div>' +
            '</div>' +
            (conBoton ? '<a class="ini-btn ini-btn-ghost" href="' + esc(enlace(URL_GRUPOS, { grupo: x.g.id })) + '">' + ICON.grupo + 'Ver el grupo</a>' : '');
    }

    function pintarHoy() {
        var lista = clasesHoy();
        var ul = $('ini-clases'), vacio = $('ini-hoy-empty'), prox = $('ini-proxima'), sub = $('ini-hoy-sub');
        firmaHoy = lista.map(function (x) { return estadoClase(x.c); }).join(',');

        // Conservar el foco si la lista se vuelve a pintar mientras el docente la recorre con el teclado
        var activo = document.activeElement, hrefFoco = activo && ul.contains(activo) ? activo.getAttribute('href') : null;

        if (!lista.length) {
            ul.innerHTML = '';
            vacio.hidden = false;
            prox.hidden = true;
            sub.textContent = '';
            var px = proximaClase();
            var esManana = px && diasEntre(D.hoy, px.c.fecha) === 1;
            $('ini-hoy-empty-txt').innerHTML = px
                ? 'Tu próxima clase es ' + (esManana ? '' : 'el ') + '<b>' + esc(cuandoLargo(px.c.fecha)) + '</b>, de ' + esc(px.c.ini) + NB + 'a' + NB + esc(px.c.fin) + ': ' + esc(grupoTxt(px.g)) + '.'
                : 'No tienes más clases programadas en tus grupos.';
            var btnVacio = vacio.querySelector('.ini-btn');
            if (btnVacio) btnVacio.remove();
            if (px) {
                vacio.insertAdjacentHTML('beforeend', '<a class="ini-btn ini-btn-ghost" href="' + esc(enlace(URL_GRUPOS, { grupo: px.g.id })) + '">' + ICON.grupo + 'Ver el grupo</a>');
            }
            return;
        }

        vacio.hidden = true;
        var ini = Math.min.apply(null, lista.map(function (x) { return minutos(x.c.ini); }));
        var fin = Math.max.apply(null, lista.map(function (x) { return minutos(x.c.fin); }));
        var total = lista.reduce(function (s, x) { return s + minutos(x.c.fin) - minutos(x.c.ini); }, 0);
        sub.textContent = 'De ' + hhmm(ini) + ' a ' + hhmm(fin) + ' · ' + duracion(total) + ' de clase';

        ul.innerHTML = lista.map(claseHtml).join('');

        // Cuando ya no queda ninguna clase por empezar ni en curso, mostrar la siguiente
        var quedan = lista.some(function (x) { return minutos(x.c.fin) > ahoraMin(); });
        var px2 = quedan ? null : proximaClase();
        prox.hidden = !px2;
        prox.innerHTML = px2 ? proximaHtml(px2, true) : '';

        if (hrefFoco) {
            var igual = ul.querySelector('a[href="' + hrefFoco.replace(/"/g, '\\"') + '"]');
            if (igual) igual.focus();
        }
    }

    /* ── Pendientes ───────────────────────────── */
    function itemHtml(href, ficha, nombre, sub) {
        return '<li><a class="ini-pend-item" href="' + esc(href) + '">' + ficha +
            '<span class="ini-pend-main"><span class="ini-pend-nombre">' + esc(nombre) + '</span><span class="ini-pend-sub">' + esc(sub) + '</span></span>' +
            ICON.ir + '</a></li>';
    }
    function bloqueHtml(tipo, titulo, items) {
        if (!items.length) return '';
        var html = '<div class="ini-pend-bloque" data-tipo="' + tipo + '">' +
            '<h4 class="ini-pend-titulo" id="ini-pend-t-' + tipo + '">' + esc(titulo) + ' <span class="ini-pend-titulo-num">' + items.length + '</span></h4>' +
            '<ul class="ini-pend-lista" aria-labelledby="ini-pend-t-' + tipo + '">';
        items.forEach(function (it, i) {
            html += i < MAX_PEND ? it : it.replace('<li>', '<li data-extra hidden>');
        });
        html += '</ul>';
        if (items.length > MAX_PEND) {
            html += '<button type="button" class="ini-mas" aria-expanded="false" data-mas="' + (items.length - MAX_PEND) + '">Ver ' + (items.length - MAX_PEND) + ' más</button>';
        }
        return html + '</div>';
    }

    function pintarPendientes() {
        var p = pendientes();

        var asis = p.asis.map(function (x) {
            var f = fecha(x.fecha);
            var horas = x.c ? rango(x.c.ini, x.c.fin) + ' · ' : '';
            return itemHtml(
                enlace(URL_ASIS, { grupo: x.g.id, fecha: x.fecha }),
                '<span class="ini-ficha ini-ficha-asis" aria-hidden="true"><small>' + DIAS_C[f.getDay()] + '</small><b>' + f.getDate() + '</b></span>',
                grupoTxt(x.g),
                capital(fechaCorta(x.fecha)) + ' · ' + horas + hace(x.fecha)
            );
        });
        var notas = p.notas.map(function (x) {
            return itemHtml(
                enlace(URL_NOTAS, { grupo: x.g.id, modulo: x.m.id }),
                '<span class="ini-ficha ini-ficha-notas"><small>faltan</small><b>' + x.faltan + '</b></span>',
                x.m.nombre,
                programaCorto(x.g.programa) + ' · ' + x.g.nombre + ' · terminó el ' + fechaCorta(x.m.fin)
            );
        });
        var hab = p.hab.map(function (x) {
            return itemHtml(
                enlace(URL_NOTAS, { grupo: x.g.id, modulo: x.m.id }),
                '<span class="ini-ficha ini-ficha-hab"><small>nota</small><b>' + esc(nota(x.n)) + '</b></span>',
                x.e.nombres + ' ' + x.e.apellidos,
                x.m.nombre + ' · ' + programaCorto(x.g.programa) + ' · ' + x.g.nombre
            );
        });

        $('ini-pend-body').innerHTML =
            bloqueHtml('asis', 'Asistencia sin registrar', asis) +
            bloqueHtml('notas', 'Notas por poner', notas) +
            bloqueHtml('hab', 'Habilitaciones por calificar', hab);

        $('ini-pend-empty').hidden = p.total > 0;
        $('ini-pend-num').textContent = p.total ? String(p.total) : '';
    }

    // "Ver N más" / "Ver menos": muestra el resto de un bloque sin volver a pintarlo (el foco se queda en el botón)
    $('ini-pend-body').addEventListener('click', function (e) {
        var btn = e.target.closest('.ini-mas');
        if (!btn) return;
        var abierto = btn.getAttribute('aria-expanded') === 'true';
        btn.closest('.ini-pend-bloque').querySelectorAll('[data-extra]').forEach(function (li) { li.hidden = abierto; });
        btn.setAttribute('aria-expanded', abierto ? 'false' : 'true');
        btn.textContent = abierto ? 'Ver ' + btn.dataset.mas + ' más' : 'Ver menos';
    });

    /* ── Estudiantes para acompañar ───────────── */
    function pintarAcompanar() {
        var lista = paraAcompanar();
        $('ini-acomp-num').textContent = lista.length ? String(lista.length) : '';
        $('ini-acomp-empty').hidden = lista.length > 0;
        $('ini-acomp-lista').innerHTML = lista.map(function (x) {
            var e = x.e, a = e.asistencia, pct = a.pct == null ? 0 : a.pct;
            var nombre = e.nombres + ' ' + e.apellidos;
            return '<li><a class="ini-acomp-item ' + jClase(x.g.jornada) + '" href="' + esc(enlace(URL_GRUPOS, { grupo: x.g.id })) + '">' +
                '<span class="ini-avatar" aria-hidden="true">' + esc(iniciales(e.nombres, e.apellidos)) + '</span>' +
                '<span class="ini-acomp-info">' +
                    '<span class="ini-acomp-nombre">' + esc(nombre) + '</span>' +
                    '<span class="ini-acomp-sub">' + esc(programaCorto(x.g.programa) + ' · ' + x.g.nombre + ' · faltó a ' + plural(a.A, 'clase', 'clases')) + '</span>' +
                '</span>' +
                '<span class="ini-meter-cell">' +
                    '<span class="ini-meter" aria-hidden="true"><span style="width:' + Math.max(0, Math.min(100, pct)) + '%"></span></span>' +
                    '<span class="ini-pct">' + esc(a.pct == null ? '—' : a.pct + ' %') + '</span>' +
                '</span>' +
            '</a></li>';
        }).join('');
    }

    /* ── Mi horario ───────────────────────────── */
    function sesiones() {
        var out = [];
        vigentes().forEach(function (g) {
            (g.horario || []).forEach(function (h) {
                var d = h.diaIso >= 1 && h.diaIso <= 6 ? h.diaIso - 1 : SEMANA.indexOf(h.dia);
                if (d < 0) return;
                out.push({ g: g, h: h, d: d, ini: minutos(h.ini), fin: minutos(h.fin) });
            });
        });
        return out.sort(function (a, b) { return a.d - b.d || a.ini - b.ini; });
    }

    // Tramos de horas con clase; las horas vacías entre tramos se recortan en una franja delgada
    function tramos(ses) {
        var iv = ses.map(function (s) { return [Math.floor(s.ini / 60), Math.ceil(s.fin / 60)]; })
            .sort(function (a, b) { return a[0] - b[0]; });
        var unidos = [];
        iv.forEach(function (x) {
            var u = unidos[unidos.length - 1];
            if (u && x[0] <= u[1] + 1) u[1] = Math.max(u[1], x[1]);
            else unidos.push([x[0], x[1]]);
        });
        var y = 0;
        return unidos.map(function (t) {
            var r = { ini: t[0], fin: t[1], y: y };
            y += (t[1] - t[0]) * HORA_PX + CORTE_PX;
            return r;
        });
    }
    function yDe(min, trs) {
        for (var i = 0; i < trs.length; i++) {
            var t = trs[i];
            if (min >= t.ini * 60 && min <= t.fin * 60) return t.y + (min - t.ini * 60) / 60 * HORA_PX;
        }
        return null;
    }
    // Carriles por si dos sesiones del mismo día se cruzan (no debería pasar: HorarioService lo impide)
    function carriles(lista) {
        var fines = [];
        lista.forEach(function (s) {
            var k = 0;
            while (k < fines.length && fines[k] > s.ini) k++;
            fines[k] = s.fin;
            s.carril = k;
        });
        lista.forEach(function (s) { s.carriles = Math.max(1, fines.length); });
    }

    function lunesDeLaSemana() {
        var h = fecha(D.hoy), iso = diaIso(h);
        return iso === 7 ? masDias(h, 1) : masDias(h, 1 - iso);   // el domingo muestra la semana que empieza
    }

    var ahoraEl = null, tramosActuales = [];

    function pintarHorario() {
        var ses = sesiones();
        var grid = $('ini-sem'), lista = $('ini-sem-lista');
        var vacio = !ses.length;
        $('ini-sem-empty').hidden = !vacio;
        grid.hidden = vacio;
        lista.hidden = vacio;
        ahoraEl = null;

        var horas = ses.reduce(function (s, x) { return s + x.fin - x.ini; }, 0);
        var grupos = vigentes().filter(function (g) { return (g.horario || []).length; }).length;
        $('ini-horario-sub').textContent = vacio ? 'Sin clases esta semana' :
            plural(ses.length, 'clase', 'clases') + ' a la semana · ' + duracion(horas) + ' en ' + plural(grupos, 'grupo', 'grupos');

        var presentes = JORNADAS.filter(function (j) { return ses.some(function (s) { return s.g.jornada === j; }); });
        ses.forEach(function (s) { if (presentes.indexOf(s.g.jornada) === -1) presentes.push(s.g.jornada); });
        $('ini-leyenda').innerHTML = presentes.map(function (j) { return '<li class="ini-jornada ' + jClase(j) + '">' + esc(j) + '</li>'; }).join('');

        if (vacio) { grid.innerHTML = ''; lista.innerHTML = ''; return; }

        var lunes = lunesDeLaSemana();
        var hoyIdx = diaIso(fecha(D.hoy)) - 1;   // 6 = domingo: ninguna columna
        var porDia = SEMANA.map(function () { return []; });
        ses.forEach(function (s) { porDia[s.d].push(s); });

        var trs = tramos(ses);
        tramosActuales = trs;
        var ult = trs[trs.length - 1];
        var alto = ult.y + (ult.fin - ult.ini) * HORA_PX;

        /* Grilla (pantallas anchas) */
        var html = '<div class="ini-sem-esquina" aria-hidden="true"></div>';
        SEMANA.forEach(function (d, i) {
            var f = masDias(lunes, i);
            html += '<div class="ini-sem-head' + (i === hoyIdx ? ' is-hoy' : '') + (porDia[i].length ? '' : ' is-libre') + '">' +
                '<span>' + DIAS_C[f.getDay()] + '</span><b>' + f.getDate() + '</b>' +
                (i === hoyIdx ? '<span class="ini-hoy-pill">Hoy</span>' : '') + '</div>';
        });

        html += '<div class="ini-sem-horas" style="height:' + alto + 'px" aria-hidden="true">';
        trs.forEach(function (t, ti) {
            for (var h = t.ini; h <= t.fin; h++) {
                var cls = (ti === 0 && h === t.ini) ? ' is-primera' : ((ti === trs.length - 1 && h === t.fin) ? ' is-ultima' : '');
                html += '<span class="ini-sem-hora' + cls + '" style="top:' + (t.y + (h - t.ini) * HORA_PX) + 'px">' + pad(h) + ':00</span>';
            }
        });
        html += '</div>';

        SEMANA.forEach(function (d, i) {
            html += '<div class="ini-sem-col' + (i === hoyIdx ? ' is-hoy' : '') + '" style="height:' + alto + 'px">';
            trs.forEach(function (t, ti) {
                for (var h = t.ini + 1; h < t.fin; h++) html += '<span class="ini-sem-linea" style="top:' + (t.y + (h - t.ini) * HORA_PX) + 'px"></span>';
                if (ti < trs.length - 1) {
                    html += '<span class="ini-sem-corte" style="top:' + (t.y + (t.fin - t.ini) * HORA_PX) + 'px;height:' + CORTE_PX + 'px"></span>';
                }
            });
            carriles(porDia[i]);
            porDia[i].forEach(function (s) {
                var top = yDe(s.ini, trs), bajo = yDe(s.fin, trs);
                var altoB = bajo - top, ancho = 100 / s.carriles;
                var etiqueta = SEMANA[s.d] + ', de ' + s.h.ini + ' a ' + s.h.fin + ': ' + grupoTxt(s.g) + ' (' + s.g.jornada + ')';
                html += '<a class="ini-sem-bloque ' + jClase(s.g.jornada) + (altoB < 76 ? ' is-corto' : '') + '"' +
                    ' href="' + esc(enlace(URL_GRUPOS, { grupo: s.g.id })) + '"' +
                    ' title="' + esc(etiqueta) + '" aria-label="' + esc(etiqueta) + '"' +
                    ' style="top:' + (top + 3) + 'px;height:' + (altoB - 6) + 'px;left:calc(' + (s.carril * ancho) + '% + 5px);width:calc(' + ancho + '% - 10px)">' +
                        '<span class="ini-sem-bloque-hora">' + esc(s.h.ini + ' – ' + s.h.fin) + '</span>' +
                        '<span class="ini-sem-bloque-prog">' + esc(programaCorto(s.g.programa)) + '</span>' +
                        '<span class="ini-sem-bloque-grupo">' + esc(s.g.nombre) + '</span>' +
                    '</a>';
            });
            if (i === hoyIdx) html += '<span class="ini-sem-ahora" data-ahora hidden></span>';
            html += '</div>';
        });
        grid.innerHTML = html;
        ahoraEl = grid.querySelector('[data-ahora]');
        moverAhora();

        /* Lista por día (pantallas angostas) */
        lista.innerHTML = SEMANA.map(function (d, i) {
            if (!porDia[i].length) return '';
            var f = masDias(lunes, i);
            return '<div class="ini-dia' + (i === hoyIdx ? ' is-hoy' : '') + '">' +
                '<h4 class="ini-dia-nombre">' + d + ' <span>' + f.getDate() + ' ' + MESES_C[f.getMonth()] + '</span>' +
                    (i === hoyIdx ? '<span class="ini-hoy-pill">Hoy</span>' : '') + '</h4>' +
                '<ul class="ini-dia-lista">' + porDia[i].map(function (s) {
                    return '<li><a class="ini-dia-clase ' + jClase(s.g.jornada) + '" href="' + esc(enlace(URL_GRUPOS, { grupo: s.g.id })) + '">' +
                        '<span class="ini-dia-hora">' + esc(rango(s.h.ini, s.h.fin)) + '</span>' +
                        '<span class="ini-dia-info"><b>' + esc(s.g.programa) + '</b><small>' + esc(s.g.nombre + ' · ' + s.g.jornada) + '</small></span>' +
                    '</a></li>';
                }).join('') + '</ul></div>';
        }).join('');
    }

    // Línea de "ahora" en la columna de hoy, solo si la hora cae dentro de un tramo con clases
    function moverAhora() {
        if (!ahoraEl) return;
        var y = yDe(ahoraMin(), tramosActuales);
        ahoraEl.hidden = y === null;
        if (y !== null) ahoraEl.style.top = y + 'px';
    }

    /* ── Reloj: estados y cuentas regresivas de las clases de hoy ── */
    function tick() {
        var firma = clasesHoy().map(function (x) { return estadoClase(x.c); }).join(',');
        if (firma !== firmaHoy) {
            pintarHoy();
        } else {
            $('ini-clases').querySelectorAll('.ini-clase-cuenta').forEach(function (p) {
                p.textContent = cuentaTxt({ ini: p.dataset.ini, fin: p.dataset.fin }, p.dataset.est);
            });
        }
        moverAhora();
    }

    function pintarTodo() {
        pintarEncabezado();
        pintarKpis();
        pintarHoy();
        pintarPendientes();
        pintarAcompanar();
        pintarHorario();
    }

    pintarTodo();
    setInterval(tick, 30000);

    // Al volver con "Atrás" desde Asistencia o Notas el navegador puede mostrar la página guardada en memoria:
    // recargar para leer lo que se guardó allá.
    window.addEventListener('pageshow', function (e) { if (e.persisted) window.location.reload(); });
})();
