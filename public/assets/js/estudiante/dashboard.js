/* ESAT — Estudiante: Inicio y mi horario
   Lee window.EST_DATOS (datos.js: PanelEstudianteService::datos) y no guarda nada. Cada pendiente lleva a la pantalla
   que lo resuelve (Aula virtual, Mi programa, Mis pagos o Comunicados). El horario es el mismo componente del Inicio
   del Docente (= Secretaría › Horarios), con un solo grupo. Sin matrícula vigente o sin grupo, solo saludo y aviso. */
(function () {
    'use strict';

    var D   = window.EST_DATOS;
    var app = document.getElementById('ini-app');
    if (!D || !app) return;

    var URL = {
        programa: app.dataset.urlPrograma, aula: app.dataset.urlAula,
        pagos: app.dataset.urlPagos, comunicados: app.dataset.urlComunicados
    };

    var DIAS    = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var DIAS_C  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    var MESES   = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var SEMANA  = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    var NB       = ' ';   // espacio que no parte la línea
    var MAX_PEND = 4;
    // Horario semanal: las mismas medidas de Admin › Horarios y del Inicio del Docente
    var HORA_PX = 56, RANGO_MIN_H = 6, HORA_MIN = 6, HORA_MAX = 24;

    var ICON = {
        ir:    '<svg class="ini-pend-ir" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>',
        plata: '<svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
        notas: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>'
    };

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function $(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function masDias(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function diaIso(d) { var w = d.getDay(); return w === 0 ? 7 : w; }
    function minutos(hhmm) { var p = String(hhmm).split(':'); return +p[0] * 60 + +p[1]; }
    function ahoraMin() { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
    function capital(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
    function diasEntre(a, b) { return Math.round((fecha(b) - fecha(a)) / 86400000); }
    function fechaLarga(iso) { var f = fecha(iso); return DIAS[f.getDay()] + ' ' + f.getDate() + ' de ' + MESES[f.getMonth()]; }
    function fechaCorta(iso) { var f = fecha(iso); return DIAS_C[f.getDay()] + NB + f.getDate() + NB + MESES_C[f.getMonth()]; }
    function rango(ini, fin) { return ini + NB + '–' + NB + fin; }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }
    function duracion(min) { var h = Math.floor(min / 60), m = min % 60; return h && m ? h + ' h ' + m + ' min' : (h ? h + ' h' : m + ' min'); }
    function nota(v) { return v == null ? '—' : Number(v).toFixed(1).replace('.', ','); }
    function plata(n) { return '$' + NB + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function enlace(base, params) {
        var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        return base + (base.indexOf('?') === -1 ? '?' : '&') + q;
    }
    function cuando(iso) {
        var n = diasEntre(D.hoy, iso);
        if (n === 0) return 'hoy';
        if (n === 1) return 'mañana';
        return 'el ' + fechaCorta(iso);
    }
    function hace(iso) {
        var n = diasEntre(iso, D.hoy);
        return n <= 0 ? 'hoy' : (n === 1 ? 'ayer' : 'hace' + NB + n + NB + 'días');
    }
    function tonoNota(n) { return n == null ? 'none' : (n >= 4 ? 'ok' : (n >= D.reglas.APRUEBA ? 'warn' : 'bad')); }
    function tonoAsis(p) { return p == null ? 'none' : (p >= 90 ? 'ok' : (p >= D.reglas.MINIMO ? 'warn' : 'bad')); }

    var M = D.matricula, G = M && M.grupo;
    // Sin matrícula o sin grupo no hay clases, avance ni horario: solo el saludo y el aviso
    if (!M || !G) { sinMatricula(); return; }
    var grupoTxt = M.programa + ' · ' + G.nombre.replace(/ /g, NB);
    var ESTADO_MAT = { aplazado: 'aplazada', retirado: 'retirada', graduado: 'graduada' };
    function moduloDe(id) { return D.modulos.filter(function (m) { return m.id === id; })[0] || null; }
    function moduloEnFecha(iso) {
        var c = D.clases.filter(function (x) { return x.fecha === iso; })[0];
        return c ? moduloDe(c.moduloId) : null;
    }

    /* ── Clases ───────────────────────────────── */
    function claseHoy() { return D.clases.filter(function (c) { return c.fecha === D.hoy; })[0] || null; }
    function estadoClase(c) {
        var m = ahoraMin();
        if (m >= minutos(c.fin)) return 'terminada';
        if (m >= minutos(c.ini)) return 'en_curso';
        return 'mas_tarde';
    }
    var CHIP = {
        en_curso:  ['ini-chip-curso', 'En curso'],
        mas_tarde: ['app-status-muted', 'Más tarde'],
        terminada: ['app-status-muted', 'Terminó']
    };
    function proximaClase() {
        var m = ahoraMin();
        return D.clases.filter(function (c) {
            return c.fecha > D.hoy || (c.fecha === D.hoy && minutos(c.ini) > m);
        })[0] || null;
    }

    /* ── Pendientes ───────────────────────────── */
    function pendientes() {
        var tareas = [], hab = [], pagos = [], leer = [];
        // Solo lo que puede hacer: matrícula activa en su grupo actual y sin el tiempo vencido (ES9)
        D.evaluaciones.forEach(function (e) { if (e.participa && !e.respuesta && !e.cerrada && !(e.intento && e.intento.expirada)) tareas.push(e); });
        D.actividades.forEach(function (a) { if (a.participa && !a.entrega && !a.cerrada) tareas.push(a); });
        tareas.sort(function (a, b) { return a.cierra < b.cierra ? -1 : 1; });
        // La habilitación es después de que el módulo termina (como para su docente)
        D.modulos.forEach(function (m) { if (m.estadoNota === 'por_habilitar' && m.estado === 'terminado') hab.push(m); });

        var P = D.pagos, R = P ? P.resumen : null;
        if (P && P.puedeElegir) pagos.push({ tipo: 'plan' });
        if (R && R.vencido > 0) pagos.push({ tipo: 'vencido' });
        else if (P && P.plan && R.proxima && diasEntre(D.hoy, R.proxima.vence) <= 7) pagos.push({ tipo: 'pronto' });
        // Lo vencido de otra matrícula (una retirada que quedó debiendo) también se cobra (ES5)
        var otras = P && P.otras ? P.otras.filter(function (o) { return o.vencido > 0; }) : [];
        if (otras.length) pagos.push({ tipo: 'otra', otras: otras });

        D.comunicados.forEach(function (c) { if (!c.leido) leer.push(c); });
        return { tareas: tareas, hab: hab, pagos: pagos, leer: leer, total: tareas.length + hab.length + pagos.length + leer.length };
    }

    /* ── Encabezado ───────────────────────────── */
    function pintarEncabezado() {
        var c = claseHoy(), p = pendientes().total, txt;
        var cosas = plural(p, 'cosa pendiente', 'cosas pendientes');
        var clase = c ? 'Hoy tienes clase de <b>' + esc(rango(c.ini, c.fin)) + '</b>' : 'Hoy no tienes clase';
        txt = p ? clase + ' y <b>' + esc(cosas) + '</b>.' : clase + ' y estás al día.';
        // Matrícula aplazada, retirada o graduada: eso es lo primero que debe leer
        if (ESTADO_MAT[M.estado]) {
            txt = 'Tu matrícula está <b>' + ESTADO_MAT[M.estado] + '</b>' + (M.estadoFecha ? ' desde el ' + esc(fechaLarga(M.estadoFecha)) : '') +
                (p ? ' y tienes <b>' + esc(cosas) + '</b>.' : '.');
        }
        $('ini-resumen').innerHTML = esc(capital(fechaLarga(D.hoy))) + '. ' + txt;
    }

    /* ── Clase de hoy ─────────────────────────── */
    var firmaHoy = '';
    function cuentaTxt(c, est) {
        var m = ahoraMin();
        if (est === 'en_curso')  return 'Termina en ' + duracion(Math.max(1, minutos(c.fin) - m));
        if (est === 'mas_tarde') return 'Empieza en ' + duracion(Math.max(1, minutos(c.ini) - m));
        return '';
    }
    function proximaTxt(px) {
        var n = diasEntre(D.hoy, px.fecha);
        var dia = n === 0 ? 'hoy' : (n === 1 ? 'mañana, ' + fechaLarga(px.fecha) : 'el ' + fechaLarga(px.fecha));
        return 'Tu próxima clase es ' + dia + ', de ' + px.ini + NB + 'a' + NB + px.fin + '.';
    }
    function pintarHoy() {
        var c = claseHoy(), body = $('ini-hoy-body'), sub = $('ini-hoy-sub');
        var est = c ? estadoClase(c) : '';
        firmaHoy = est;
        if (!c) {
            sub.textContent = '';
            var px = proximaClase();
            body.innerHTML = '<div class="ini-empty">' +
                '<div class="ini-empty-icono ini-empty-calma" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></div>' +
                '<p class="ini-empty-titulo">Hoy no tienes clase</p>' +
                '<p class="ini-empty-txt">' + esc(px ? proximaTxt(px) : 'No tienes más clases programadas en tu grupo.') + '</p>' +
            '</div>';
            return;
        }
        var mod = moduloDe(c.moduloId), chip = CHIP[est];
        sub.textContent = grupoTxt + ' · ' + G.jornada;
        var html = '<ul class="ini-clases"><li class="ini-clase sin-accion is-' + est + '">' +
            '<div class="ini-clase-hora"><span class="ini-clase-ini">' + esc(c.ini) + '</span><span class="ini-clase-fin">hasta ' + esc(c.fin) + '</span></div>' +
            '<div class="ini-clase-info">' +
                '<div class="ini-clase-tags"><span class="app-status-tag ' + chip[0] + '">' + chip[1] + '</span></div>' +
                '<h4 class="ini-clase-prog">' + esc(mod ? mod.nombre : M.programa) + '</h4>' +
                '<p class="ini-clase-meta">Módulo ' + (mod ? mod.numero + ' de ' + D.modulos.length : '') + ' · Docente: <b>' + esc(G.docente) + '</b></p>' +
                '<p class="ini-clase-cuenta" data-ini="' + esc(c.ini) + '" data-fin="' + esc(c.fin) + '" data-est="' + est + '">' + esc(cuentaTxt(c, est)) + '</p>' +
            '</div>' +
        '</li></ul>';
        if (est === 'terminada') {
            var px2 = proximaClase();
            if (px2) html += '<div class="ini-proxima"><div class="ini-proxima-txt"><div class="ini-proxima-cuando"><span>Próxima clase:</span> ' +
                esc(capital(fechaLarga(px2.fecha))) + ' · ' + esc(rango(px2.ini, px2.fin)) + '</div></div></div>';
        }
        body.innerHTML = html;
    }

    /* ── Tu avance ────────────────────────────── */
    function pintarAvance() {
        var total = D.clases.length;
        var dictadas = D.clases.filter(function (c) { return c.estado !== 'futura' && c.estado !== 'hoy'; }).length;
        var pct = total ? Math.round(dictadas * 100 / total) : 0;
        var mod = D.moduloActual, A = D.asistencia;
        $('ini-avance-sub').textContent = M.programa + ' · ' + G.nombre + ' · termina el ' + fechaLarga(G.fin);

        var modTxt = mod
            ? '<p class="ini-modulo">Módulo ' + mod.numero + ' de ' + D.modulos.length + ': <b>' + esc(mod.nombre) + '</b> <span>· hasta el ' + esc(fechaCorta(mod.fin)) + '</span></p>'
            : '<p class="ini-modulo"><span>Ningún módulo en curso esta semana.</span></p>';
        var tN = tonoNota(D.promedio), tA = tonoAsis(A.pct);
        $('ini-avance-body').innerHTML =
            '<div class="ini-avance-fila">' +
                '<div class="ini-barra" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100" aria-label="Avance de la carrera"><span style="width:' + pct + '%"></span></div>' +
                '<span class="ini-avance-pct">' + pct + NB + '% de la carrera</span>' +
            '</div>' + modTxt +
            '<div class="ini-datos">' +
                '<div class="ini-dato"><span class="ini-dato-lbl">Promedio</span><span class="ini-dato-val ini-tono-' + tN + '">' + esc(nota(D.promedio)) + '</span>' +
                    '<span class="ini-dato-sub">De los módulos con nota</span></div>' +
                '<div class="ini-dato"><span class="ini-dato-lbl">Módulos aprobados</span><span class="ini-dato-val">' + D.aprobados + ' <small>de ' + D.modulos.length + '</small></span>' +
                    '<span class="ini-dato-sub">' + esc(plural(D.modulos.filter(function (m) { return m.estado === 'por_iniciar'; }).length, 'por iniciar', 'por iniciar')) + '</span></div>' +
                '<div class="ini-dato"><span class="ini-dato-lbl">Asistencia</span><span class="ini-dato-val ini-tono-' + tA + '">' + (A.pct == null ? '—' : A.pct + NB + '%') + '</span>' +
                    '<span class="ini-dato-sub">' + (A.pct != null && A.pct < 90 ? 'La referencia es el 80' + NB + '%' : 'Faltaste a ' + esc(plural(A.A, 'clase', 'clases'))) + '</span></div>' +
            '</div>' +
            '<div class="ini-avance-pie"><a class="ini-btn ini-btn-ghost" href="' + esc(URL.programa) + '">' + ICON.notas + 'Ver notas y asistencia</a></div>';
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
        items.forEach(function (it, i) { html += i < MAX_PEND ? it : it.replace('<li>', '<li data-extra hidden>'); });
        html += '</ul>';
        if (items.length > MAX_PEND) {
            html += '<button type="button" class="ini-mas" aria-expanded="false" data-mas="' + (items.length - MAX_PEND) + '">Ver ' + (items.length - MAX_PEND) + ' más</button>';
        }
        return html + '</div>';
    }
    function fichaFecha(iso, clase) {
        var f = fecha(iso);
        return '<span class="ini-ficha ' + clase + '" aria-hidden="true"><small>' + MESES_C[f.getMonth()] + '</small><b>' + f.getDate() + '</b></span>';
    }

    function pintarPendientes() {
        var p = pendientes(), R = D.pagos.resumen;

        var tareas = p.tareas.map(function (t) {
            var esEval = t.tipo === 'evaluacion';
            var dia = t.cierra.slice(0, 10);
            return itemHtml(enlace(URL.aula, { id: t.tipo + '-' + t.id }), fichaFecha(dia, 'ini-ficha-pend'), t.titulo,
                (esEval ? 'Evaluación · cierra ' : 'Actividad · se entrega hasta ') + cuando(dia) + ' a las ' + t.cierra.slice(11));
        });
        var hab = p.hab.map(function (m) {
            return itemHtml(URL.programa, '<span class="ini-ficha ini-ficha-riesgo"><small>nota</small><b>' + esc(nota(m.n)) + '</b></span>',
                'Habilitación de ' + m.nombre, 'Perdiste el módulo ' + m.numero + ': tu docente te dirá la fecha');
        });
        var pagos = p.pagos.map(function (x) {
            if (x.tipo === 'plan') {
                return itemHtml(URL.pagos, '<span class="ini-ficha ini-ficha-pend" aria-hidden="true">' + ICON.plata + '</span>',
                    'Elige tu plan de pago', 'Mensual o ' + D.pagos.opciones.periodo.nombre.toLowerCase() + '. Sin plan no se generan tus cuotas');
            }
            if (x.tipo === 'otra') {
                var debe = x.otras.reduce(function (s, o) { return s + o.vencido; }, 0);
                return itemHtml(URL.pagos, '<span class="ini-ficha ini-ficha-riesgo" aria-hidden="true">' + ICON.plata + '</span>',
                    'Tienes ' + plata(debe) + ' vencido en ' + (x.otras.length === 1 ? 'tu otra matrícula' : 'tus otras matrículas'),
                    x.otras.map(function (o) { return o.programa; }).join(', ') + ' · se paga en la sede');
            }
            var venc = D.pagos.cobros.filter(function (c) { return c.estado === 'vencido'; });
            if (x.tipo === 'vencido') {
                return itemHtml(URL.pagos, '<span class="ini-ficha ini-ficha-riesgo" aria-hidden="true">' + ICON.plata + '</span>',
                    'Tienes ' + plata(R.vencido) + ' vencido',
                    (venc.length === 1 ? venc[0].concepto + ' · venció el ' + fechaCorta(venc[0].vence) : plural(venc.length, 'cobro vencido', 'cobros vencidos')) + ' · se paga en la sede');
            }
            return itemHtml(URL.pagos, fichaFecha(R.proxima.vence, 'ini-ficha-pend'),
                'Tu cuota vence ' + cuando(R.proxima.vence), R.proxima.concepto + ' · ' + plata(R.proxima.restante));
        });
        var leer = p.leer.map(function (c) {
            return itemHtml(enlace(URL.comunicados, { id: c.id }), fichaFecha(c.fecha, 'ini-ficha-leer'), c.titulo, c.de.nombre + ' · ' + hace(c.fecha));
        });

        $('ini-pend-body').innerHTML =
            bloqueHtml('tareas', 'Por responder y entregar', tareas) +
            bloqueHtml('hab', 'Por habilitar', hab) +
            bloqueHtml('pagos', 'Pagos', pagos) +
            bloqueHtml('leer', 'Comunicados sin leer', leer);
        $('ini-pend-empty').hidden = p.total > 0;
    }
    $('ini-pend-body').addEventListener('click', function (e) {
        var btn = e.target.closest('.ini-mas');
        if (!btn) return;
        var abierto = btn.getAttribute('aria-expanded') === 'true';
        btn.closest('.ini-pend-bloque').querySelectorAll('[data-extra]').forEach(function (li) { li.hidden = abierto; });
        btn.setAttribute('aria-expanded', abierto ? 'false' : 'true');
        btn.textContent = abierto ? 'Ver ' + btn.dataset.mas + ' más' : 'Ver menos';
    });

    /* ── Mi horario (el de Admin › Horarios y el Inicio del Docente) ── */
    function jornadaDeHora(h) { return h < 12 ? 'Mañana' : (h < 18 ? 'Tarde' : 'Noche'); }
    function jornadaClase(j) { return j === 'Fines de semana' ? 'fds' : j.toLowerCase().replace('ñ', 'n'); }
    function fmtDuracion(min) { var h = Math.floor(min / 60), m = min % 60; if (!h) return m + ' min'; return m ? h + 'h ' + m + 'm' : h + 'h'; }
    var ahoraEl = null, rangoActual = null;
    function yDe(min) {
        if (!rangoActual || min < rangoActual.ini * 60 || min > rangoActual.fin * 60) return null;
        return (min - rangoActual.ini * 60) / 60 * HORA_PX;
    }
    function lunesDeLaSemana() {
        var h = fecha(D.hoy), iso = diaIso(h);
        return iso === 7 ? masDias(h, 1) : masDias(h, 1 - iso);
    }
    function pintarHorario() {
        var ses = M.horario.map(function (h) { return { h: h, d: h.diaIso - 1, ini: minutos(h.ini), fin: minutos(h.fin) }; })
            .filter(function (s) { return s.d >= 0 && s.d <= 5; });
        var grid = $('ini-sem'), lista = $('ini-sem-lista');
        // Un grupo sin horario todavía (se publica aparte en Horarios): sin esta guarda el rango de horas no termina (ES3)
        var vacio = !ses.length;
        $('ini-sem-empty').hidden = !vacio;
        grid.hidden = vacio;
        lista.hidden = vacio;
        if (vacio) {
            $('ini-horario-sub').textContent = G.jornada + ' · ' + G.nombre + ' (' + G.codigo + ')';
            grid.innerHTML = '';
            lista.innerHTML = '';
            return;
        }
        var horas = ses.reduce(function (s, x) { return s + x.fin - x.ini; }, 0);
        $('ini-horario-sub').textContent = plural(ses.length, 'clase', 'clases') + ' a la semana · ' + duracion(horas) + ' · ' + G.jornada + ' · ' + G.nombre + ' (' + G.codigo + ')';

        var lunes = lunesDeLaSemana();
        var hoyIdx = diaIso(fecha(D.hoy)) - 1;
        var porDia = SEMANA.map(function () { return []; });
        ses.forEach(function (s) { porDia[s.d].push(s); });

        var hIni = Math.floor(Math.min.apply(null, ses.map(function (x) { return x.ini; })) / 60);
        var hFin = Math.ceil(Math.max.apply(null, ses.map(function (x) { return x.fin; })) / 60);
        while (hFin - hIni < RANGO_MIN_H) {
            if (hFin < HORA_MAX) hFin++;
            if (hFin - hIni < RANGO_MIN_H && hIni > HORA_MIN) hIni--;
            if (hIni === HORA_MIN && hFin === HORA_MAX) break;
        }
        rangoActual = { ini: hIni, fin: hFin };
        var alto = (hFin - hIni) * HORA_PX;

        var html = '<div class="ini-sem-esquina" aria-hidden="true"></div>';
        SEMANA.forEach(function (d, i) {
            var f = masDias(lunes, i);
            html += '<div class="ini-sem-head' + (i === hoyIdx ? ' is-hoy' : '') + (porDia[i].length ? '' : ' is-libre') + '">' +
                d + '<span class="ini-sem-fecha">' + f.getDate() + '</span>' + (i === hoyIdx ? '<span class="ini-hoy-pill">Hoy</span>' : '') + '</div>';
        });
        html += '<div class="ini-sem-horas" style="height:' + alto + 'px" aria-hidden="true">';
        for (var h = hIni; h <= hFin; h++) {
            var jor = h === 12 ? 'Tarde' : (h === 18 ? 'Noche' : (h === hIni ? jornadaDeHora(h) : ''));
            html += '<span class="ini-sem-hora" style="top:' + ((h - hIni) * HORA_PX) + 'px">' + pad(h) + ':00' +
                (jor && h < hFin ? '<em class="ini-sem-jor ini-sem-jor-' + jornadaClase(jor) + '">' + jor + '</em>' : '') + '</span>';
        }
        html += '</div>';

        SEMANA.forEach(function (d, i) {
            var iso = ymd(masDias(lunes, i));
            html += '<div class="ini-sem-col' + (i === hoyIdx ? ' is-hoy' : '') + '" style="height:' + alto + 'px">';
            [12, 18].forEach(function (hj) {
                if (hj > hIni && hj < hFin) html += '<span class="ini-sem-sep" style="top:' + ((hj - hIni) * HORA_PX) + 'px"></span>';
            });
            porDia[i].forEach(function (s) {
                var top = yDe(s.ini), altoB = (s.fin - s.ini) / 60 * HORA_PX;
                var mod = moduloEnFecha(iso);
                var etiqueta = SEMANA[s.d] + ' ' + masDias(lunes, i).getDate() + ', de ' + s.h.ini + ' a ' + s.h.fin + ': ' + (mod ? mod.nombre : M.programa);
                html += '<div class="ini-sem-bloque ini-c-grupo' + (altoB < 90 ? ' is-corto' : '') + (i === hoyIdx ? ' is-hoy' : '') + '"' +
                    ' title="' + esc(etiqueta) + '" role="img" aria-label="' + esc(etiqueta) + '"' +
                    ' style="top:' + (top + 3) + 'px;height:' + (altoB - 6) + 'px;left:5px;width:calc(100% - 10px)">' +
                        '<span class="ini-sem-bloque-hora">' + esc(s.h.ini + ' – ' + s.h.fin) + '<span class="ini-sem-bloque-dur">' + esc(fmtDuracion(s.fin - s.ini)) + '</span></span>' +
                        '<span class="ini-sem-bloque-prog">' + esc(mod ? mod.nombre : M.programa) + '</span>' +
                        '<span class="ini-sem-bloque-grupo">' + esc(G.nombre + ' · ' + G.codigo) + '</span>' +
                    '</div>';
            });
            if (i === hoyIdx) html += '<span class="ini-sem-ahora" data-ahora hidden></span>';
            html += '</div>';
        });
        grid.innerHTML = html;
        ahoraEl = grid.querySelector('[data-ahora]');
        moverAhora();

        lista.innerHTML = SEMANA.map(function (d, i) {
            if (!porDia[i].length) return '';
            var f = masDias(lunes, i), mod = moduloEnFecha(ymd(f));
            return '<div class="ini-dia' + (i === hoyIdx ? ' is-hoy' : '') + '">' +
                '<h4 class="ini-dia-nombre">' + d + ' <span>' + f.getDate() + ' ' + MESES_C[f.getMonth()] + '</span>' +
                    (i === hoyIdx ? '<span class="ini-hoy-pill">Hoy</span>' : '') + '</h4>' +
                '<ul class="ini-dia-lista">' + porDia[i].map(function (s) {
                    return '<li><div class="ini-dia-clase ini-c-grupo">' +
                        '<span class="ini-dia-hora">' + esc(rango(s.h.ini, s.h.fin)) + '<span class="ini-sem-bloque-dur">' + esc(fmtDuracion(s.fin - s.ini)) + '</span></span>' +
                        '<span class="ini-dia-info"><b>' + esc(mod ? mod.nombre : M.programa) + '</b><small>' + esc(G.nombre + ' · ' + G.jornada) + '</small></span>' +
                    '</div></li>';
                }).join('') + '</ul></div>';
        }).join('');
    }
    function moverAhora() {
        if (!ahoraEl) return;
        var y = yDe(ahoraMin());
        ahoraEl.hidden = y === null;
        if (y !== null) ahoraEl.style.top = y + 'px';
    }

    /* ── Reloj: estado y cuenta regresiva de la clase de hoy ── */
    function tick() {
        var c = claseHoy();
        if (c && estadoClase(c) !== firmaHoy) pintarHoy();
        else {
            var p = $('ini-hoy-body').querySelector('.ini-clase-cuenta');
            if (p) p.textContent = cuentaTxt({ ini: p.dataset.ini, fin: p.dataset.fin }, p.dataset.est);
        }
        moverAhora();
    }

    /* ── Sin matrícula vigente o sin grupo ───── */
    function sinMatricula() {
        $('ini-resumen').innerHTML = esc(capital(fechaLarga(D.hoy))) + '.';
        app.querySelector('.ini-cols').hidden = true;
        app.querySelector('.ini-horario').hidden = true;
        var sec = document.createElement('section');
        sec.className = 'ini-card';
        sec.innerHTML = '<div class="ini-empty">' +
            '<div class="ini-empty-icono ini-empty-calma" aria-hidden="true">' + ICON.notas + '</div>' +
            '<p class="ini-empty-titulo">' + (M ? 'Tu matrícula todavía no tiene grupo' : 'No tienes una matrícula vigente') + '</p>' +
            '<p class="ini-empty-txt">' + (M
                ? 'Cuando el administrativo te asigne un grupo, aquí verás tu horario, tus clases y tu avance.'
                : 'Cuando el administrativo de tu sede te matricule en una carrera, aquí verás tus clases, tus notas, tu Aula virtual y tus pagos. Mientras tanto puedes participar en el foro de tu sede.') + '</p>' +
        '</div>';
        app.appendChild(sec);
    }

    pintarEncabezado();
    pintarHoy();
    pintarAvance();
    pintarPendientes();
    pintarHorario();
    setInterval(tick, 30000);

    // Al volver con "Atrás" el navegador puede mostrar la página guardada en memoria: recargar para leer lo nuevo
    window.addEventListener('pageshow', function (e) { if (e.persisted) window.location.reload(); });
})();
