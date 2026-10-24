/* =========================================================
   ESAT — Panel Docente: Mis grupos
   Solo frontend: lee window.DOC_DATOS (datos-ejemplo.js). Esta pantalla no guarda nada;
   "Tomar asistencia" y "Poner notas" llevan a sus pantallas con ?grupo=ID.
   ========================================================= */
(function () {
    'use strict';

    var D   = window.DOC_DATOS;
    var app = document.getElementById('grp-app');
    if (!D || !app) return;

    var URL_ASISTENCIA = app.dataset.urlAsistencia;
    var URL_NOTAS      = app.dataset.urlNotas;
    var HOY            = D.hoy;
    var ANIO_HOY       = +HOY.slice(0, 4);

    var DIAS   = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var DIAS_C = { 'Lunes': 'Lun', 'Martes': 'Mar', 'Miércoles': 'Mié', 'Jueves': 'Jue', 'Viernes': 'Vie', 'Sábado': 'Sáb', 'Domingo': 'Dom' };
    var MESES  = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MES_C  = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    var ICON = {
        reloj:  '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        check:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        estrella: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
        ok:     '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
        flecha: '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>',
        cal:    '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        wa:     '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>'
    };

    /* ── Utilidades ─────────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function sinTildes(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); }
    function digitos(t) { return String(t == null ? '' : t).replace(/\D/g, ''); }
    // Devuelve HTML seguro: formatea solo si son 10 dígitos, lo demás se escapa (HALLAZGOS C1)
    function fmtTel(t) { t = String(t == null ? '' : t); return /^\d{10}$/.test(t) ? t.slice(0, 3) + ' ' + t.slice(3, 6) + ' ' + t.slice(6) : esc(t); }
    function plural(n, uno, varios) { return n + ' ' + (n === 1 ? uno : varios); }
    function primerNombre(n) { return String(n || '').split(' ')[0]; }
    function iniciales(n, a) { return (String(n || '').charAt(0) + String(a || '').charAt(0)).toUpperCase(); }

    function fecha(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    // "24 oct" (o "24 oct 2025" si no es de este año)
    function fCorta(iso, conAnio) {
        if (!iso) return '—';
        var f = fecha(iso), t = f.getDate() + ' ' + MES_C[f.getMonth()];
        return (conAnio || f.getFullYear() !== ANIO_HOY) ? t + ' ' + f.getFullYear() : t;
    }
    // "sábado 24 de octubre" (+ " de 2027" si no es de este año, o siempre con conAnio)
    function fLarga(iso, conAnio) {
        var f = fecha(iso), t = DIAS[f.getDay()] + ' ' + f.getDate() + ' de ' + MESES[f.getMonth()];
        return (conAnio || f.getFullYear() !== ANIO_HOY) ? t + ' de ' + f.getFullYear() : t;
    }
    function rango(a, b) {
        if (!a || !b) return '—';
        var fa = fecha(a), fb = fecha(b);
        if (fa.getFullYear() === fb.getFullYear()) {
            var ia = fa.getDate() + ' ' + MES_C[fa.getMonth()];
            return ia + ' – ' + fCorta(b, fb.getFullYear() !== ANIO_HOY);
        }
        return fCorta(a, true) + ' – ' + fCorta(b, true);
    }
    function minutos(hhmm) { var p = hhmm.split(':'); return +p[0] * 60 + +p[1]; }
    function fmtHoras(min) {
        var h = min / 60;
        return (Math.round(h * 10) / 10).toString().replace('.', ',') + ' h';
    }
    function fmtNota(v) { return v == null ? '—' : Number(v).toFixed(1).replace('.', ','); }
    function tonoPct(p) { return p == null ? 'none' : (p >= 90 ? 'ok' : (p >= D.MINIMO ? 'warn' : 'bad')); }
    function tonoNota(v) { return v == null ? 'none' : (v >= 4 ? 'ok' : (v >= D.APRUEBA ? 'warn' : 'bad')); }
    function pctTxt(p) { return p == null ? 'Sin datos' : p + ' %'; }

    function sigla(escuela) {
        var s = sinTildes(escuela);
        if (s.indexOf('salud') > -1) return 'sal';
        if (s.indexOf('turismo') > -1 || s.indexOf('cocina') > -1) return 'tur';
        if (s.indexOf('administra') > -1) return 'adm';
        if (s.indexOf('educacion') > -1 || s.indexOf('idioma') > -1) return 'edu';
        if (s.indexOf('deporte') > -1) return 'dep';
        if (s.indexOf('ciencia') > -1) return 'cie';
        if (s.indexOf('belleza') > -1) return 'bel';
        return 'otr';
    }

    function urlAsistencia(g) { return URL_ASISTENCIA + '?grupo=' + encodeURIComponent(g.id); }
    function urlNotas(g) { return URL_NOTAS + '?grupo=' + encodeURIComponent(g.id); }

    var toastEl = document.getElementById('grp-toast');
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

    /* ── Lo que se calcula de cada grupo ─────────── */
    function resumen(g) {
        var total = g.clases.length;
        var dadas = g.clases.filter(function (c) { return c.fecha < HOY; }).length;
        var notas = g.modulosSinNota.reduce(function (s, x) { return s + x.faltan; }, 0);
        var deHoy = g.soloConsulta ? null : (g.clases.filter(function (c) { return c.fecha === HOY; })[0] || null);
        var proxima = g.soloConsulta ? null : (g.clases.filter(function (c) {
            return c.fecha > HOY || (c.fecha === HOY && c.estado === 'hoy');
        })[0] || null);
        var siguiente = g.moduloActual ? null : (g.modulos.filter(function (m) { return m.estado === 'por_iniciar'; })[0] || null);
        var minSemana = g.horario.reduce(function (s, h) { return s + minutos(h.fin) - minutos(h.ini); }, 0);
        return {
            total: total, dadas: dadas,
            avance: g.soloConsulta ? 100 : (total ? Math.round(dadas * 100 / total) : 0),
            notasPorPoner: notas, deHoy: deHoy, proxima: proxima, siguiente: siguiente,
            minSemana: minSemana
        };
    }
    function horarioHtml(g) {
        var grupos = [];
        g.horario.forEach(function (h) {
            var r = h.ini + ' – ' + h.fin;
            var x = grupos.filter(function (y) { return y.rango === r; })[0];
            if (!x) { x = { rango: r, dias: [] }; grupos.push(x); }
            x.dias.push(DIAS_C[h.dia] || h.dia);
        });
        if (!grupos.length) return '<span class="grp-horas">Sin horario</span>';
        return grupos.map(function (x) {
            return '<span class="grp-dias">' + esc(x.dias.join(' · ')) + '</span> <span class="grp-horas">' + esc(x.rango) + '</span>';
        }).join(' ');
    }
    function numeroModulo(g, m) { return g.modulos.indexOf(m) + 1; }

    // De un estudiante: módulos terminados que cursó completos, aprobados, por habilitar, perdidos y notas por poner
    function notasEst(g, e) {
        var r = { cursados: 0, aprobados: 0, porHabilitar: 0, reprobados: 0, sinNota: 0 };
        g.modulos.forEach(function (m) {
            var x = e.notas[m.id];
            if (!x || x.estado === 'no_cursado') return;
            if (x.estado === 'por_habilitar') r.porHabilitar++;
            if (m.estado !== 'terminado') return;
            if (e.hasta && e.hasta <= m.fin) return; // se fue antes de que el módulo terminara
            r.cursados++;
            if (x.estado === 'aprobado' || x.estado === 'habilito') r.aprobados++;
            else if (x.estado === 'reprobado') r.reprobados++;
            else if (x.estado === 'sin_nota' && e.matricula.estado === 'activo') r.sinNota++;
        });
        return r;
    }

    /* ── Estado de la pantalla ───────────────────── */
    var grid       = document.getElementById('grp-grid');
    var vacio      = document.getElementById('grp-empty');
    var vacioTtl   = document.getElementById('grp-empty-ttl');
    var vacioSub   = document.getElementById('grp-empty-sub');
    var vacioLimp  = document.getElementById('grp-empty-limpiar');
    var vacioOtra  = document.getElementById('grp-empty-otra');
    var vacioAcc   = vacio.querySelector('.grp-empty-acc');
    var buscador   = document.getElementById('grp-search');
    var contador   = document.getElementById('grp-count');
    var panel      = document.getElementById('grp-panel');
    var tabsVista  = Array.prototype.slice.call(document.querySelectorAll('.grp-vista-btn'));
    var vista      = 'vigentes';

    function deVista(v) { return D.grupos.filter(function (g) { return v === 'finalizados' ? g.soloConsulta : !g.soloConsulta; }); }
    function coincide(g, q) {
        if (!q) return true;
        return sinTildes(g.programa + ' ' + g.codigo + ' ' + g.nombre).indexOf(q) > -1;
    }
    function ordenar(lista) {
        return lista.slice().sort(function (a, b) {
            // Vigentes: por programa; finalizados: el que terminó más recientemente primero
            if (vista === 'finalizados') return a.fin < b.fin ? 1 : (a.fin > b.fin ? -1 : 0);
            return a.programa.localeCompare(b.programa, 'es');
        });
    }

    function tarjeta(g) {
        var r = resumen(g), sg = sigla(g.escuela);
        var fin = g.soloConsulta;
        var estado = fin
            ? '<span class="app-status-tag app-status-muted">Finalizado</span>'
            : '<span class="app-status-tag app-status-active">Activo</span>';

        var modulo;
        if (fin) {
            modulo = '<strong>Terminó el ' + esc(fLarga(g.fin)) + '</strong>';
        } else if (g.moduloActual) {
            modulo = '<span>Módulo ' + numeroModulo(g, g.moduloActual) + ' de ' + g.modulos.length + ':</span> <strong>' + esc(g.moduloActual.nombre) + '</strong>';
        } else if (r.siguiente) {
            modulo = '<span>Sigue el módulo ' + numeroModulo(g, r.siguiente) + ':</span> <strong>' + esc(r.siguiente.nombre) + '</strong> <span>desde el ' + esc(fCorta(r.siguiente.ini)) + '</span>';
        } else {
            modulo = '<span>Sin módulos en curso</span>';
        }

        var estudiantes = fin
            ? '<span class="grp-dato-val">' + g.graduados + '</span><span class="grp-dato-sub">graduados de ' + g.estudiantes.length + '</span>'
            : '<span class="grp-dato-val">' + g.activos + ' <small>/ ' + g.cupo + '</small></span><span class="grp-dato-sub">activos / cupo</span>';
        var t = tonoPct(g.pct);
        var asistencia = '<span class="grp-dato-val grp-tono-' + t + '">' + esc(pctTxt(g.pct)) + '</span>' +
            '<span class="grp-dato-sub">' + (g.bajoMinimo ? plural(g.bajoMinimo, 'estudiante', 'estudiantes') + ' bajo el ' + D.MINIMO + ' %' : 'del grupo') + '</span>';

        var pend = [];
        if (fin) {
            pend.push('<span class="grp-pend grp-pend-muted">Solo consulta</span>');
        } else {
            if (g.sinRegistrar.length) pend.push('<span class="grp-pend grp-pend-asis">' + ICON.reloj + plural(g.sinRegistrar.length, 'asistencia sin registrar', 'asistencias sin registrar') + '</span>');
            if (r.notasPorPoner) pend.push('<span class="grp-pend grp-pend-notas">' + ICON.estrella + plural(r.notasPorPoner, 'nota por poner', 'notas por poner') + '</span>');
            if (!pend.length) pend.push('<span class="grp-pend grp-pend-ok">' + ICON.ok + 'Al día</span>');
        }

        var pie = fin
            ? '<span class="grp-card-nota">Consulta su asistencia y sus notas</span>'
            : '<a class="grp-btn grp-btn-primary grp-btn-sm" data-accion href="' + esc(urlAsistencia(g)) + '">' + ICON.check + 'Tomar asistencia</a>' +
              '<a class="grp-btn grp-btn-ghost grp-btn-sm" data-accion href="' + esc(urlNotas(g)) + '">' + ICON.estrella + 'Poner notas</a>';

        var hoyTag = r.deHoy ? '<span class="grp-hoy-tag">Clase hoy</span>' : '';

        return '<article class="grp-card grp-esc-' + sg + (fin ? ' is-finalizado' : '') + '" tabindex="0" data-id="' + g.id + '"' +
                ' aria-labelledby="grp-c' + g.id + '-t grp-c' + g.id + '-s">' +
            '<div class="grp-card-top"><span class="grp-esc-tag">' + esc(g.escuela) + '</span>' + estado + '</div>' +
            '<div class="grp-card-head">' +
                '<h3 class="grp-card-prog" id="grp-c' + g.id + '-t">' + esc(g.programa) + '</h3>' +
                '<span class="grp-card-grupo" id="grp-c' + g.id + '-s">' + esc(g.nombre) + ' · ' + esc(g.codigo) + '</span>' +
            '</div>' +
            '<div class="grp-card-horario"><span class="grp-jornada-tag">' + esc(g.jornada) + '</span>' + horarioHtml(g) + hoyTag + '</div>' +
            '<div class="grp-avance">' +
                '<div class="grp-avance-fila"><span>' + esc(fCorta(g.inicio, true)) + ' → ' + esc(fCorta(g.fin, true)) + '</span><span><b>' + r.avance + ' %</b> de las clases</span></div>' +
                '<div class="grp-barra" role="progressbar" aria-label="Clases dadas" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + r.avance + '"' +
                    ' aria-valuetext="' + r.dadas + ' de ' + r.total + ' clases dadas"><span style="width:' + r.avance + '%"></span></div>' +
                '<p class="grp-modulo">' + modulo + '</p>' +
            '</div>' +
            '<div class="grp-card-datos">' +
                '<div class="grp-card-dato"><span class="grp-dato-lbl">Estudiantes</span>' + estudiantes + '</div>' +
                '<div class="grp-card-dato"><span class="grp-dato-lbl">Asistencia</span>' + asistencia + '</div>' +
            '</div>' +
            '<div class="grp-pendientes">' + pend.join('') + '</div>' +
            '<div class="grp-card-pie">' + pie + '<span class="grp-card-ver" aria-hidden="true">Ver ficha' + ICON.flecha + '</span></div>' +
        '</article>';
    }

    function render() {
        var q = sinTildes(buscador.value);
        var cuenta = {
            vigentes: deVista('vigentes').filter(function (g) { return coincide(g, q); }).length,
            finalizados: deVista('finalizados').filter(function (g) { return coincide(g, q); }).length
        };
        document.getElementById('grp-num-vigentes').textContent = cuenta.vigentes;
        document.getElementById('grp-num-finalizados').textContent = cuenta.finalizados;

        var todos = deVista(vista);
        var lista = ordenar(todos.filter(function (g) { return coincide(g, q); }));
        grid.innerHTML = lista.map(tarjeta).join('');
        grid.hidden = lista.length === 0;

        contador.textContent = q ? plural(lista.length, 'resultado', 'resultados') : '';

        if (lista.length) { vacio.hidden = true; return; }
        var otra = vista === 'vigentes' ? 'finalizados' : 'vigentes';
        vacioLimp.hidden = true; vacioOtra.hidden = true;
        if (!todos.length) {
            vacioTtl.textContent = vista === 'vigentes' ? 'No tienes grupos vigentes' : 'Todavía no tienes grupos finalizados';
            vacioSub.textContent = vista === 'vigentes'
                ? 'Cuando la sede te asigne un grupo, aparecerá aquí con su horario y sus estudiantes.'
                : 'Los grupos que termines quedarán aquí para consultar su asistencia y sus notas.';
        } else {
            vacioTtl.textContent = 'Ningún grupo coincide con «' + buscador.value.trim() + '»';
            vacioSub.textContent = 'Busca por el nombre del programa, el código (por ejemplo CUR-ADM-014-A) o el grupo.';
            vacioLimp.hidden = false;
        }
        if (q && cuenta[otra]) {
            vacioOtra.textContent = 'Ver ' + plural(cuenta[otra], otra === 'vigentes' ? 'vigente' : 'finalizado', otra);
            vacioOtra.dataset.vista = otra;
            vacioOtra.hidden = false;
        }
        vacioAcc.hidden = vacioLimp.hidden && vacioOtra.hidden;
        vacio.hidden = false;
    }

    function mostrarVista(v, enfocar) {
        vista = v;
        tabsVista.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            if (on) { panel.setAttribute('aria-labelledby', b.id); if (enfocar) b.focus(); }
        });
        render();
    }
    tabsVista.forEach(function (b, i) {
        b.addEventListener('click', function () { mostrarVista(b.dataset.vista); });
        b.addEventListener('keydown', function (e) {
            var j = null;
            if (e.key === 'ArrowRight') j = (i + 1) % tabsVista.length;
            else if (e.key === 'ArrowLeft') j = (i - 1 + tabsVista.length) % tabsVista.length;
            else if (e.key === 'Home') j = 0;
            else if (e.key === 'End') j = tabsVista.length - 1;
            if (j === null) return;
            e.preventDefault();
            mostrarVista(tabsVista[j].dataset.vista, true);
        });
    });
    buscador.addEventListener('input', render);
    vacioLimp.addEventListener('click', function () { buscador.value = ''; render(); buscador.focus(); });
    vacioOtra.addEventListener('click', function () { mostrarVista(vacioOtra.dataset.vista, true); });

    // Toda la tarjeta abre la ficha; los botones de la tarjeta no
    grid.addEventListener('click', function (e) {
        if (e.target.closest('[data-accion]')) { e.stopPropagation(); return; }
        var card = e.target.closest('.grp-card');
        if (card) abrirFicha(+card.dataset.id, card);
    });
    grid.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var card = e.target.closest('.grp-card');
        if (!card || e.target !== card) return;
        e.preventDefault();
        abrirFicha(+card.dataset.id, card);
    });

    /* ── Ficha (cajón) ──────────────────────────── */
    var drawer    = document.getElementById('grp-drawer');
    var dwPanel   = drawer.querySelector('.grp-drawer-panel');
    var dwBody    = document.getElementById('grp-dw-body');
    var dwAvatar  = document.getElementById('grp-dw-avatar');
    var dwTitulo  = document.getElementById('grp-dw-titulo');
    var dwSub     = document.getElementById('grp-dw-sub');
    var dwBadges  = document.getElementById('grp-dw-badges');
    var dwSolo    = document.getElementById('grp-dw-solo');
    var dwSoloTxt = document.getElementById('grp-dw-solo-txt');
    var dwFoot    = document.getElementById('grp-dw-foot');
    var dwCerrar  = document.getElementById('grp-dw-cerrar');
    var dtabs     = Array.prototype.slice.call(drawer.querySelectorAll('.grp-dtab'));
    var paneles   = {
        estudiantes: document.getElementById('grp-dpanel-estudiantes'),
        malla:       document.getElementById('grp-dpanel-malla'),
        horario:     document.getElementById('grp-dpanel-horario')
    };
    var actual = null, ultimoFoco = null, filtroEst = 'todos';

    function abrirFicha(id, origen) {
        var g = D.grupos.filter(function (x) { return x.id === id; })[0];
        if (!g) return false;
        if (!drawer.classList.contains('is-open')) ultimoFoco = origen || document.activeElement;
        actual = g;
        filtroEst = 'todos';
        pintarFicha(g);
        mostrarTab('estudiantes');
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        dwBody.scrollTop = 0;
        ponerUrl(g.id);
        setTimeout(function () { dwCerrar.focus({ preventScroll: true }); }, 40);
        return true;
    }
    function cerrarFicha() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        var id = actual && actual.id;
        actual = null;
        ponerUrl(null);
        var volver = ultimoFoco && document.body.contains(ultimoFoco) ? ultimoFoco : grid.querySelector('.grp-card[data-id="' + id + '"]');
        if (volver && volver.focus) volver.focus({ preventScroll: false });
        ultimoFoco = null;
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarFicha); });
    document.addEventListener('keydown', function (e) {
        if (!drawer.classList.contains('is-open')) return;
        if (e.key === 'Escape') { e.preventDefault(); cerrarFicha(); return; }
        if (e.key === 'Tab') atraparFoco(e);
    });
    // Mantiene el foco dentro del cajón mientras está abierto
    function atraparFoco(e) {
        var focos = Array.prototype.slice.call(dwPanel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'))
            .filter(function (el) { return el.offsetParent !== null; });
        if (!focos.length) return;
        var primero = focos[0], ultimo = focos[focos.length - 1];
        if (e.shiftKey && (document.activeElement === primero || !dwPanel.contains(document.activeElement))) { e.preventDefault(); ultimo.focus(); }
        else if (!e.shiftKey && (document.activeElement === ultimo || !dwPanel.contains(document.activeElement))) { e.preventDefault(); primero.focus(); }
    }

    function ponerUrl(id) {
        try {
            var u = new URL(window.location.href);
            if (id) u.searchParams.set('grupo', id); else u.searchParams.delete('grupo');
            window.history.replaceState(null, '', u.pathname + u.search + u.hash);
        } catch (err) { /* sin History API: la ficha funciona igual */ }
    }

    function mostrarTab(tab, enfocar) {
        dtabs.forEach(function (b) {
            var on = b.dataset.tab === tab;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            if (on && enfocar) b.focus();
        });
        Object.keys(paneles).forEach(function (k) { paneles[k].hidden = k !== tab; });
        dwBody.scrollTop = 0;
    }
    dtabs.forEach(function (b, i) {
        b.addEventListener('click', function () { mostrarTab(b.dataset.tab); });
        b.addEventListener('keydown', function (e) {
            var j = null;
            if (e.key === 'ArrowRight') j = (i + 1) % dtabs.length;
            else if (e.key === 'ArrowLeft') j = (i - 1 + dtabs.length) % dtabs.length;
            else if (e.key === 'Home') j = 0;
            else if (e.key === 'End') j = dtabs.length - 1;
            if (j === null) return;
            e.preventDefault();
            mostrarTab(dtabs[j].dataset.tab, true);
        });
    });

    function pintarFicha(g) {
        var sg = sigla(g.escuela);
        dwPanel.className = 'grp-drawer-panel grp-esc-' + sg;
        dwTitulo.textContent = g.programa;
        dwSub.textContent = g.nombre + ' · ' + g.codigo;
        dwBadges.innerHTML =
            (g.soloConsulta ? '<span class="app-status-tag app-status-muted">Finalizado</span>' : '<span class="app-status-tag app-status-active">Activo</span>') +
            '<span class="grp-jornada-tag">' + esc(g.jornada) + '</span>' +
            '<span class="grp-esc-tag">' + esc(g.escuela) + '</span>';
        dwSolo.hidden = !g.soloConsulta;
        dwSoloTxt.textContent = g.soloConsulta ? 'Terminó el ' + fLarga(g.fin) + '; su asistencia y sus notas ya no se modifican.' : '';
        dwFoot.hidden = g.soloConsulta;
        if (!g.soloConsulta) {
            document.getElementById('grp-dw-asistencia').href = urlAsistencia(g);
            document.getElementById('grp-dw-notas').href = urlNotas(g);
        }
        document.getElementById('grp-dtab-num-estudiantes').textContent = g.estudiantes.length;
        document.getElementById('grp-dtab-num-malla').textContent = g.modulos.length;

        pintarEstudiantes(g);
        pintarMalla(g);
        pintarHorario(g);
    }

    /* Estudiantes */
    var ORDEN_ESTADO = { activo: 0, graduado: 0, aplazado: 1, retirado: 2 };
    var FILTROS = [
        { id: 'todos',     lbl: 'Todos',              fn: function () { return true; } },
        // En un grupo finalizado ya no hay activos: el filtro muestra a los graduados
        { id: 'activos',   lbl: 'Activos',            fn: function (e) { return e.matricula.estado === (actual && actual.soloConsulta ? 'graduado' : 'activo'); } },
        { id: 'fallas',    lbl: 'Con fallas (< 80 %)', fn: function (e) { return e.asistencia.situacion === 'fallas'; } },
        { id: 'habilitar', lbl: 'Por habilitar',      fn: function (e, n) { return n.porHabilitar > 0; } }
    ];

    function estadoMatricula(e) {
        var m = e.matricula;
        var mapa = {
            activo:   ['app-status-active', 'Activo'],
            aplazado: ['app-status-warn', 'Aplazado'],
            retirado: ['app-status-muted', 'Retirado'],
            graduado: ['grp-st-graduado', 'Graduado']
        }[m.estado] || ['app-status-muted', m.estado];
        var desde = (m.estado === 'aplazado' || m.estado === 'retirado') && m.estadoFecha
            ? '<span class="grp-est-desde">desde el ' + esc(fCorta(m.estadoFecha)) + '</span>' : '';
        return '<span class="app-status-tag ' + mapa[0] + '">' + esc(mapa[1]) + '</span>' + desde;
    }
    function telefonoHtml(tel, quien) {
        var d = digitos(tel);
        if (!d) return '<span class="grp-est-sub">Sin celular</span>';
        return '<span class="grp-tel-fila">' +
            '<a class="grp-tel" href="tel:+57' + d + '">' + fmtTel(tel) + '</a>' +
            '<a class="grp-wa" href="https://wa.me/57' + d + '" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp a ' + esc(quien) + '" title="WhatsApp">' + ICON.wa + '</a>' +
        '</span>';
    }
    function filaEstudiante(g, e) {
        var n = notasEst(g, e), a = e.asistencia, t = tonoPct(a.pct);
        var fuera = e.matricula.estado === 'aplazado' || e.matricula.estado === 'retirado';
        var nombre = e.nombres + ' ' + e.apellidos;

        var tags = [];
        if (n.porHabilitar) tags.push('<span class="grp-mini-tag grp-mini-warn">Por habilitar' + (n.porHabilitar > 1 ? ' (' + n.porHabilitar + ')' : '') + '</span>');
        if (n.reprobados) tags.push('<span class="grp-mini-tag grp-mini-bad">' + plural(n.reprobados, 'módulo perdido', 'módulos perdidos') + '</span>');
        if (n.sinNota && !g.soloConsulta) tags.push('<span class="grp-mini-tag grp-mini-pend">' + plural(n.sinNota, 'nota por poner', 'notas por poner') + '</span>');

        var acudiente = '';
        if (e.menor) {
            acudiente = e.acudiente
                ? '<div class="grp-acu"><span class="grp-est-lbl">Acudiente</span>' +
                    '<span class="grp-acu-nombre">' + esc(e.acudiente.nombre) + ' <span class="grp-est-sub">(' + esc(e.acudiente.parentesco) + ')</span></span>' +
                    telefonoHtml(e.acudiente.telefono, e.acudiente.nombre) + '</div>'
                : '<div class="grp-acu"><span class="grp-est-lbl">Acudiente</span><span class="grp-est-sub">Sin acudiente registrado</span></div>';
        }

        return '<li class="grp-est' + (fuera ? ' is-fuera' : '') + '">' +
            '<div class="grp-est-top">' +
                '<div class="grp-est-avatar" aria-hidden="true">' + esc(iniciales(e.nombres, e.apellidos)) + '</div>' +
                '<div class="grp-est-id">' +
                    '<div class="grp-est-nombre">' + esc(nombre) + '</div>' +
                    '<div class="grp-est-doc"><span>' + esc(e.tipoDoc) + ' ' + esc(e.documento) + '</span><span class="grp-sep" aria-hidden="true"></span><span>' + esc(e.edad) + ' años</span>' +
                        (e.menor ? '<span class="grp-menor">Menor</span>' : '') + '</div>' +
                '</div>' +
                '<div class="grp-est-estado">' + estadoMatricula(e) + '</div>' +
            '</div>' +
            '<div class="grp-est-datos">' +
                '<div class="grp-est-dato"><span class="grp-est-lbl">Asistencia</span>' +
                    '<span class="grp-est-val grp-tono-' + t + '">' +
                        (a.pct != null ? '<span class="grp-meter" aria-hidden="true"><span style="width:' + a.pct + '%"></span></span>' : '') +
                        esc(pctTxt(a.pct)) + '</span>' +
                    '<span class="grp-est-sub">' + a.P + ' P · ' + a.T + ' T · ' + a.A + ' A · ' + a.E + ' E</span>' +
                '</div>' +
                '<div class="grp-est-dato"><span class="grp-est-lbl">Notas</span>' +
                    '<span class="grp-est-val grp-tono-' + tonoNota(e.promedio) + '">' + esc(fmtNota(e.promedio)) + ' <small>' + (e.promedio == null ? 'sin notas' : 'promedio') + '</small></span>' +
                    '<span class="grp-est-sub">' + (n.cursados
                        ? plural(n.aprobados, 'aprobado', 'aprobados') + ' de ' + plural(n.cursados, 'módulo cursado', 'módulos cursados')
                        : 'Sin módulos terminados') + '</span>' +
                    (tags.length ? '<span class="grp-est-tags">' + tags.join('') + '</span>' : '') +
                '</div>' +
                '<div class="grp-est-dato grp-est-contacto"><span class="grp-est-lbl">Celular</span>' +
                    telefonoHtml(e.telefono, primerNombre(e.nombres)) + acudiente +
                '</div>' +
            '</div>' +
        '</li>';
    }

    // Un conteo que pide atención se pinta con su tono; en cero queda neutro
    function conTono(n, tono) { return n ? '<span class="grp-tono-' + tono + '">' + n + '</span>' : '0'; }

    function pintarEstudiantes(g) {
        var p = paneles.estudiantes;
        var info = {};
        g.estudiantes.forEach(function (e) { info[e.id] = notasEst(g, e); });
        var cuenta = {};
        FILTROS.forEach(function (f) { cuenta[f.id] = g.estudiantes.filter(function (e) { return f.fn(e, info[e.id]); }).length; });

        var retirados = g.estudiantes.filter(function (e) { return e.matricula.estado === 'retirado'; }).length;
        var t = tonoPct(g.pct);
        var datos = g.soloConsulta
            ? [['Graduados', g.graduados + ' <small>de ' + g.estudiantes.length + '</small>'],
               ['Asistencia', '<span class="grp-tono-' + t + '">' + esc(pctTxt(g.pct)) + '</span>'],
               ['Retirados', String(retirados)],
               ['Bajo el ' + D.MINIMO + ' %', conTono(g.bajoMinimo, 'bad')]]
            : [['Activos', g.activos + ' <small>/ ' + g.cupo + '</small>'],
               ['Asistencia', '<span class="grp-tono-' + t + '">' + esc(pctTxt(g.pct)) + '</span>'],
               ['Bajo el ' + D.MINIMO + ' %', conTono(g.bajoMinimo, 'bad')],
               ['Por habilitar', conTono(g.porHabilitar, 'warn')]];

        p.innerHTML =
            '<div class="grp-datos">' + datos.map(function (d) {
                return '<div class="grp-dato"><span class="grp-dato-lbl">' + esc(d[0]) + '</span><span class="grp-dato-val">' + d[1] + '</span></div>';
            }).join('') + '</div>' +
            '<div class="grp-seccion-fila">' +
                '<div class="grp-chips" role="radiogroup" aria-label="Filtrar estudiantes">' + FILTROS.map(function (f) {
                    return '<label class="grp-chip"><input type="radio" name="grp-filtro-est" value="' + f.id + '"' + (f.id === filtroEst ? ' checked' : '') + '>' +
                        '<span>' + esc(f.id === 'activos' && g.soloConsulta ? 'Graduados' : f.lbl) + ' <b>' + cuenta[f.id] + '</b></span></label>';
                }).join('') + '</div>' +
                '<p class="grp-leyenda"><b>P</b> presente · <b>T</b> tarde · <b>A</b> ausente · <b>E</b> excusa</p>' +
            '</div>' +
            '<ul class="grp-est-lista grp-esc-' + sigla(g.escuela) + '" id="grp-est-lista" aria-label="Estudiantes del grupo"></ul>' +
            '<p class="grp-lista-vacia" id="grp-est-vacio" hidden></p>';

        p.querySelectorAll('input[name="grp-filtro-est"]').forEach(function (r) {
            r.addEventListener('change', function () { filtroEst = r.value; pintarListaEst(g, info); });
        });
        pintarListaEst(g, info);
    }
    function pintarListaEst(g, info) {
        var lista = document.getElementById('grp-est-lista');
        var vacioEst = document.getElementById('grp-est-vacio');
        var f = FILTROS.filter(function (x) { return x.id === filtroEst; })[0] || FILTROS[0];
        var est = g.estudiantes.filter(function (e) { return f.fn(e, info[e.id]); }).sort(function (a, b) {
            var oa = ORDEN_ESTADO[a.matricula.estado] || 0, ob = ORDEN_ESTADO[b.matricula.estado] || 0;
            if (oa !== ob) return oa - ob;
            return (a.apellidos + ' ' + a.nombres).localeCompare(b.apellidos + ' ' + b.nombres, 'es');
        });
        lista.innerHTML = est.map(function (e) { return filaEstudiante(g, e); }).join('');
        lista.hidden = est.length === 0;
        vacioEst.hidden = est.length > 0;
        vacioEst.textContent = {
            todos: 'Este grupo todavía no tiene estudiantes.',
            activos: g.soloConsulta ? 'Ningún estudiante se graduó en este grupo.' : 'No hay estudiantes activos en este grupo.',
            fallas: 'Ningún estudiante está por debajo del ' + D.MINIMO + ' % de asistencia.',
            habilitar: 'Ningún estudiante tiene módulos por habilitar.'
        }[f.id];
    }

    /* Malla */
    var ESTADO_MOD = {
        terminado:   ['app-status-active', 'Terminado'],
        en_curso:    ['app-status-warn', 'En curso'],
        por_iniciar: ['app-status-muted', 'Por iniciar']
    };
    function pintarMalla(g) {
        var p = paneles.malla;
        if (!g.modulos.length) { p.innerHTML = '<p class="grp-lista-vacia">El programa todavía no tiene módulos registrados.</p>'; return; }
        var faltan = {};
        g.modulosSinNota.forEach(function (x) { faltan[x.modulo.id] = x.faltan; });
        var horasTotal = g.modulos.reduce(function (s, m) { return s + m.horas; }, 0);
        var terminados = g.modulos.filter(function (m) { return m.estado === 'terminado'; }).length;

        p.innerHTML =
            '<div class="grp-seccion-fila"><h4 class="grp-seccion-titulo">Módulos en orden</h4>' +
                '<span class="grp-leyenda">' + terminados + ' de ' + g.modulos.length + ' terminados</span></div>' +
            '<ol class="grp-malla">' + g.modulos.map(function (m, i) {
                var est = ESTADO_MOD[m.estado] || ESTADO_MOD.por_iniciar;
                var esActual = g.moduloActual && g.moduloActual.id === m.id;
                var av = '';
                if (esActual) {
                    var clases = g.clases.filter(function (c) { return c.fecha >= m.ini && c.fecha <= m.fin; });
                    var dadas = clases.filter(function (c) { return c.fecha < HOY; }).length;
                    var pct = clases.length ? Math.round(dadas * 100 / clases.length) : 0;
                    av = '<div class="grp-mod-av"><div class="grp-barra" aria-hidden="true"><span style="width:' + pct + '%"></span></div>' +
                        dadas + ' de ' + plural(clases.length, 'clase', 'clases') + '</div>';
                }
                var falta = faltan[m.id] ? '<span class="grp-mini-tag grp-mini-pend">' + plural(faltan[m.id], 'nota por poner', 'notas por poner') + '</span>' : '';
                return '<li class="grp-mod is-' + esc(m.estado.replace('_', '-')) + (esActual ? ' is-actual' : '') + (m.estado === 'terminado' ? ' is-terminado' : '') + '"' +
                        (esActual ? ' aria-current="step"' : '') + '>' +
                    '<span class="grp-mod-num" aria-hidden="true">' + (i + 1) + '</span>' +
                    '<div><div class="grp-mod-nombre">' + esc(m.nombre) + '</div>' +
                        '<div class="grp-mod-sub">' + esc(m.horas) + ' h · ' + esc(rango(m.ini, m.fin)) + '</div>' + av + '</div>' +
                    '<div class="grp-mod-der"><span class="app-status-tag ' + est[0] + '">' + est[1] + '</span>' + falta + '</div>' +
                '</li>';
            }).join('') + '</ol>' +
            '<div class="grp-malla-total"><span>Total del programa</span><span><b>' + horasTotal + ' h</b> · ' + plural(g.modulos.length, 'módulo', 'módulos') + '</span></div>';
    }

    /* Horario */
    function pintarHorario(g) {
        var p = paneles.horario, r = resumen(g);
        var proxima;
        if (g.soloConsulta) {
            proxima = '<div class="grp-proxima is-fin">' + ICON.cal + '<p><strong>El grupo ya terminó.</strong> Su última clase fue el ' +
                esc(fLarga(g.clases.length ? g.clases[g.clases.length - 1].fecha : g.fin)) + '.</p></div>';
        } else if (r.proxima) {
            var esHoy = r.proxima.fecha === HOY;
            proxima = '<div class="grp-proxima">' + ICON.cal + '<p>Próxima clase: <strong>' + (esHoy ? 'hoy, ' : '') + esc(fLarga(r.proxima.fecha)) + '</strong> · ' +
                esc(r.proxima.ini) + ' – ' + esc(r.proxima.fin) + '</p></div>';
        } else {
            proxima = '<div class="grp-proxima is-fin">' + ICON.cal + '<p><strong>No quedan clases por dictar</strong> antes del cierre del grupo.</p></div>';
        }

        p.innerHTML = proxima +
            '<div class="grp-seccion-fila"><h4 class="grp-seccion-titulo">Clases por semana</h4><span class="grp-leyenda">Jornada: ' + esc(g.jornada) + '</span></div>' +
            (g.horario.length
                ? '<ul class="grp-ses-lista">' + g.horario.map(function (h) {
                    return '<li class="grp-ses"><span class="grp-ses-dia">' + esc(h.dia) + '</span>' +
                        '<span><span class="grp-ses-hora">' + esc(h.ini) + ' – ' + esc(h.fin) + '</span><span class="grp-ses-dur">' + esc(fmtHoras(minutos(h.fin) - minutos(h.ini))) + '</span></span></li>';
                }).join('') + '</ul>'
                : '<p class="grp-lista-vacia">El grupo todavía no tiene horario asignado.</p>') +
            '<div class="grp-info-grid">' +
                '<div class="grp-info-item"><span class="grp-info-lbl">Horas por semana</span><span class="grp-info-val">' + esc(fmtHoras(r.minSemana)) + '</span></div>' +
                '<div class="grp-info-item"><span class="grp-info-lbl">Clases dadas</span><span class="grp-info-val">' + r.dadas + ' de ' + r.total + '<small>' + r.avance + ' % del programa</small></span></div>' +
                '<div class="grp-info-item"><span class="grp-info-lbl">Inicio del grupo</span><span class="grp-info-val">' + esc(fLarga(g.inicio, true)) + '</span></div>' +
                '<div class="grp-info-item"><span class="grp-info-lbl">Fin del grupo</span><span class="grp-info-val">' + esc(fLarga(g.fin, true)) + '</span></div>' +
            '</div>';
    }

    /* ── Inicio: ?grupo=ID abre su ficha en la pestaña correcta ── */
    var pedido = null;
    try { pedido = new URLSearchParams(window.location.search).get('grupo'); } catch (err) { pedido = null; }
    if (pedido) {
        var g0 = D.grupos.filter(function (x) { return String(x.id) === String(pedido); })[0];
        if (g0) {
            mostrarVista(g0.soloConsulta ? 'finalizados' : 'vigentes');
            abrirFicha(g0.id, grid.querySelector('.grp-card[data-id="' + g0.id + '"]'));
        } else {
            mostrarVista('vigentes');
            ponerUrl(null);
            toast('No encontramos ese grupo entre los tuyos.');
        }
    } else {
        mostrarVista('vigentes');
    }
})();
