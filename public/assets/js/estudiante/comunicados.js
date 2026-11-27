/* ESAT — Estudiante: Comunicados
   Lee window.EST_DATOS.comunicados y marca como leído con window.EST_API.marcarLeido al abrir (datos.js: queda en
   comunicado_lecturas y la Secretaría lo ve). Lista + lector; en pantallas angostas, uno a la vez. */
(function () {
    'use strict';

    var D = window.EST_DATOS, API = window.EST_API;
    var app = document.getElementById('com-app');
    if (!D || !API || !app) return;

    var DIAS    = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var MESES   = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var NB = ' ';   // espacio que no parte la línea

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function $(id) { return document.getElementById(id); }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function diasEntre(a, b) { return Math.round((fecha(b) - fecha(a)) / 86400000); }
    function cuando(iso) {
        var n = diasEntre(iso, D.hoy);
        if (n <= 0) return 'Hoy ' + iso.slice(11, 16);
        if (n === 1) return 'Ayer';
        var f = fecha(iso);
        return f.getDate() + NB + MESES_C[f.getMonth()];
    }
    function fechaLarga(iso) { var f = fecha(iso); return DIAS[f.getDay()] + ' ' + f.getDate() + ' de ' + MESES[f.getMonth()] + ' de ' + f.getFullYear() + ', ' + iso.slice(11, 16); }
    function iniciales(n) { var p = String(n).split(' ').filter(Boolean); return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase(); }
    function previa(t) { return String(t).replace(/\s+/g, ' ').slice(0, 140); }

    var vista = 'todos', abierto = null;
    var idParam = +((window.location.search.match(/[?&]id=(\d+)/) || [])[1] || 0);

    function render() {
        var sinLeer = D.comunicados.filter(function (c) { return !c.leido; });
        $('com-num-sinleer').textContent = sinLeer.length || '';
        var lista = vista === 'sinleer' ? sinLeer : D.comunicados;
        // El que se está leyendo se queda en "Sin leer" hasta cambiar de pestaña, para que no salte la lista
        if (vista === 'sinleer' && abierto && lista.indexOf(abierto) === -1) lista = [abierto].concat(lista);
        $('com-lista').innerHTML = lista.map(function (c) {
            return '<li><button type="button" class="com-item' + (c.leido ? '' : ' is-nuevo') + (abierto && abierto.id === c.id ? ' is-sel' : '') + '" data-id="' + c.id + '"' +
                (abierto && abierto.id === c.id ? ' aria-current="true"' : '') + '>' +
                '<span class="com-punto" aria-hidden="true"></span>' +
                '<span class="com-item-top"><span class="com-de">' + esc(c.de.nombre) + '</span><span class="com-fecha">' + esc(cuando(c.fecha)) + '</span></span>' +
                '<span class="com-item-titulo">' + (c.leido ? '' : '<span class="sr-solo">Sin leer: </span>') + esc(c.titulo) + '</span>' +
                '<span class="com-item-prev">' + esc(previa(c.cuerpo)) + '</span>' +
            '</button></li>';
        }).join('');
        $('com-lista-vacia').hidden = lista.length > 0;
        pintarLector();
    }

    function pintarLector() {
        var c = abierto, el = $('com-lector');
        if (!c) {
            el.innerHTML = '<div class="com-lector-vacio"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>' +
                '<b>Elige un comunicado para leerlo</b><span>Los que no has leído tienen un punto naranja.</span></div>';
            return;
        }
        el.innerHTML =
            '<button type="button" class="com-volver" id="com-volver"><svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>Todos los comunicados</button>' +
            (c.urgente ? '<span class="app-status-tag app-status-danger com-urgente">Urgente</span>' : '') +
            '<h3 class="com-lector-titulo">' + esc(c.titulo) + '</h3>' +
            '<div class="com-remitente"><span class="com-av" aria-hidden="true">' + esc(iniciales(c.de.nombre)) + '</span>' +
                '<span class="com-remitente-info"><span class="com-remitente-nombre">' + esc(c.de.nombre) + ' · ' + esc(c.de.rol) + '</span>' +
                '<span class="com-remitente-det">Para ' + esc(c.para.charAt(0).toLowerCase() + c.para.slice(1)) + ' · ' + esc(fechaLarga(c.fecha)) + '</span></span></div>' +
            '<div class="com-cuerpo">' + String(c.cuerpo).split(/\n\s*\n/).map(function (p) { return '<p>' + esc(p) + '</p>'; }).join('') + '</div>' +
            (c.adjunto ? '<a class="com-adjunto" href="' + esc(c.adjunto.url) + '" target="_blank" rel="noopener">' +
                '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>' +
                '<span><strong>' + esc(c.adjunto.nombre) + '</strong><small>Abrir el adjunto</small></span></a>' : '');
        $('com-volver').addEventListener('click', function () {
            var id = abierto && abierto.id;
            abierto = null;
            $('com-layout').classList.remove('is-leyendo');
            ponerUrl(null);
            render();
            var b = $('com-lista').querySelector('[data-id="' + id + '"]');
            if (b) b.focus();
        });
    }

    function abrir(id, enfocar) {
        var c = D.comunicados.filter(function (x) { return x.id === id; })[0];
        if (!c) return;
        abierto = c;
        $('com-layout').classList.add('is-leyendo');
        ponerUrl(id);
        render();
        if (enfocar) $('com-lector').focus({ preventScroll: false });
        if (!c.leido) {
            API.marcarLeido(id).then(function () {
                abierto = D.comunicados.filter(function (x) { return x.id === id; })[0] || abierto;
                render();
            });
        }
    }
    function ponerUrl(id) {
        try {
            var u = new URL(window.location.href);
            if (id) u.searchParams.set('id', id); else u.searchParams.delete('id');
            window.history.replaceState(null, '', u.pathname + u.search);
        } catch (err) { /* sin History API */ }
    }

    $('com-lista').addEventListener('click', function (e) {
        var b = e.target.closest('.com-item');
        if (b) abrir(+b.dataset.id, window.matchMedia('(max-width: 900px)').matches);
    });

    var tabs = Array.prototype.slice.call(app.querySelectorAll('.com-vista-btn'));
    function mostrar(v, enfocar) {
        vista = v;
        tabs.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            if (on) { $('com-panel').setAttribute('aria-labelledby', b.id); if (enfocar) b.focus(); }
        });
        render();
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

    render();
    if (idParam) abrir(idParam, false);
})();
