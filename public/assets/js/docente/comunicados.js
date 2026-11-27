/* ESAT — Docente: Comunicados
   Lee window.COM_SERVIDOR (ComunicadoDocenteService::datos) y guarda con fetch (CSRF); cada respuesta trae la pantalla
   recalculada. Reglas (las mismas del servidor, ComunicadoRequest del docente):
   - Recibidos (de la secretaría o del Admin): lista + lector del panel Estudiante; abrir uno lo marca leído.
   - Mis publicaciones: a uno o varios de sus grupos no finalizados; publicar ya, programar (fecha futura) o borrador; un
     adjunto PDF/JPG/PNG de hasta 5 MB. Publicado: cambia el texto, no los grupos. Archivar con motivo; solo se eliminan
     borradores. Quién lo leyó por grupo y WhatsApp a quien falta (a su contacto de emergencia si es menor).
   ?id=ID abre uno recibido · ?vista=mios abre Mis publicaciones. */
(function () {
    'use strict';

    var S = window.COM_SERVIDOR || {};
    var RUTAS = S.rutas || {};
    var D = S.datos || { recibidos: [], mios: [], grupos: [], categorias: {} };
    var app = document.getElementById('com-app');
    if (!app) return;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    var DIAS    = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var MESES   = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var NB = ' ';   // espacio que no parte la línea
    var EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    var MAX_ADJUNTO = 5 * 1024 * 1024;
    var ICON = {
        clip:     '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
        info:     '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
        archivar: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>',
        editar:   '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
        borrar:   '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>',
        reloj:    '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        wa:       '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/></svg>'
    };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function $(id) { return document.getElementById(id); }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function diasEntre(a, b) { return Math.round((fecha(b) - fecha(a)) / 86400000); }
    function cuando(iso) {
        var n = diasEntre(iso, D.hoy);
        if (n === 0) return 'Hoy ' + iso.slice(11, 16);
        if (n === 1) return 'Ayer';
        if (n === -1) return 'Mañana ' + iso.slice(11, 16);
        var f = fecha(iso);
        return f.getDate() + NB + MESES_C[f.getMonth()] + (iso.slice(0, 4) !== D.hoy.slice(0, 4) ? NB + iso.slice(0, 4) : '');
    }
    function fechaLarga(iso) { var f = fecha(iso); return DIAS[f.getDay()] + ' ' + f.getDate() + ' de ' + MESES[f.getMonth()] + ' de ' + f.getFullYear() + (iso.length > 10 ? ', ' + iso.slice(11, 16) : ''); }
    function iniciales(n) { var p = String(n).split(' ').filter(Boolean); return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase(); }
    function primerNombre(n) { return String(n || '').split(' ')[0]; }
    function previa(t) { return String(t || '').replace(/\s+/g, ' ').slice(0, 160); }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }
    function tamano(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + NB + 'MB' : Math.max(1, Math.round(b / 1024)) + NB + 'KB'; }
    function digitos(t) { return String(t || '').replace(/\D/g, ''); }
    function extension(n) { return (String(n).split('.').pop() || '').toLowerCase(); }
    function ruta(nombre, id) { return String(RUTAS[nombre] || '').replace('__ID__', encodeURIComponent(id)); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function parrafos(t) { return String(t || '').split(/\n\s*\n/).map(function (p) { return '<p>' + esc(p) + '</p>'; }).join(''); }

    /* ── Servidor ─────────────────────────────── */
    // cuerpo: objeto (JSON) o FormData (con el adjunto). Un PUT con FormData va como POST con _method.
    function enviar(metodo, url, cuerpo) {
        var esForm = typeof FormData !== 'undefined' && cuerpo instanceof FormData;
        var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
        if (esForm && metodo !== 'POST') { cuerpo.append('_method', metodo); metodo = 'POST'; }
        if (!esForm) headers['Content-Type'] = 'application/json';
        return fetch(url, { method: metodo, headers: headers, body: esForm ? cuerpo : (cuerpo ? JSON.stringify(cuerpo) : undefined), credentials: 'same-origin' })
            .then(function (r) {
                // Sesión vencida o cuenta desactivada: el login dice el motivo
                if (r.status === 401 || r.status === 419) { window.location.reload(); return { ok: false, mensaje: 'Tu sesión terminó. Vuelve a ingresar.' }; }
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (r.ok && j.ok) {
                        if (j.datos) D = j.datos;
                        return j;
                    }
                    var errores = j.errors || {};
                    var k = Object.keys(errores)[0];
                    var mensaje = k ? errores[k][0]
                        : (j.message && j.message !== 'This action is unauthorized.' && (r.status === 403 || r.status === 422) ? j.message
                        : r.status === 403 ? 'No tienes permiso para hacer esto.'
                        : r.status === 404 ? 'Ya no existe. Recarga la página.'
                        : r.status === 413 ? 'El adjunto pesa demasiado.'
                        : 'No se pudo guardar. Revisa tu conexión e intenta de nuevo.');
                    return { ok: false, campo: k ? k.split('.')[0] : null, mensaje: mensaje };
                });
            })
            .catch(function () { return { ok: false, mensaje: 'No se pudo guardar. Revisa tu conexión e intenta de nuevo.' }; });
    }

    /* ── Pestañas ─────────────────────────────── */
    var vista = /[?&]vista=mios\b/.test(window.location.search) ? 'mios' : 'recibidos';
    var tabs = Array.prototype.slice.call(app.querySelectorAll('.com-vista-btn'));
    function mostrar(v, enfocar) {
        vista = v;
        tabs.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            if (on && enfocar) b.focus();
        });
        $('com-panel-recibidos').hidden = v !== 'recibidos';
        $('com-panel-mios').hidden = v !== 'mios';
        try {
            var u = new URL(window.location.href);
            if (v === 'mios') u.searchParams.set('vista', 'mios'); else u.searchParams.delete('vista');
            if (v === 'mios') u.searchParams.delete('id');
            window.history.replaceState(null, '', u.pathname + u.search);
        } catch (err) { /* sin History API */ }
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

    function render() {
        var sinLeer = D.recibidos.filter(function (c) { return !c.leido; }).length;
        $('com-num-sinleer').textContent = sinLeer || '';
        if (vista === 'recibidos') {
            $('com-resumen').innerHTML = !D.recibidos.length ? '' : (sinLeer ? 'Tienes <b>' + sinLeer + '</b> sin leer.' : 'Los leíste todos.');
            renderRecibidos();
        } else {
            var n = { publicado: 0, programado: 0, borrador: 0 };
            D.mios.forEach(function (c) { if (n[c.estado] !== undefined) n[c.estado]++; });
            var partes = [];
            if (n.publicado) partes.push('<b>' + n.publicado + '</b> ' + (n.publicado === 1 ? 'publicado' : 'publicados'));
            if (n.programado) partes.push('<b>' + n.programado + '</b> ' + (n.programado === 1 ? 'programado' : 'programados'));
            if (n.borrador) partes.push('<b>' + n.borrador + '</b> ' + (n.borrador === 1 ? 'borrador' : 'borradores'));
            $('com-resumen').innerHTML = partes.join(' · ');
            renderMios();
        }
    }

    /* ═══════════ Recibidos ═══════════ */
    var abierto = null;
    var idParam = +((window.location.search.match(/[?&]id=(\d+)/) || [])[1] || 0);

    function renderRecibidos() {
        $('com-lista').innerHTML = D.recibidos.map(function (c) {
            var sel = abierto && abierto.id === c.id;
            return '<li><button type="button" class="com-item' + (c.leido ? '' : ' is-nuevo') + (sel ? ' is-sel' : '') + '" data-id="' + c.id + '"' + (sel ? ' aria-current="true"' : '') + '>' +
                '<span class="com-punto" aria-hidden="true"></span>' +
                '<span class="com-item-top"><span class="com-de">' + esc(c.de.nombre) + '</span><span class="com-fecha">' + esc(cuando(c.fecha)) + '</span></span>' +
                '<span class="com-item-titulo">' + (c.leido ? '' : '<span class="sr-solo">Sin leer: </span>') + esc(c.titulo) + '</span>' +
                '<span class="com-item-prev">' + esc(previa(c.cuerpo)) + '</span>' +
            '</button></li>';
        }).join('');
        $('com-lista-vacia').hidden = D.recibidos.length > 0;
        pintarLector();
    }

    function adjuntoHtml(a) {
        return '<a class="com-adjunto" href="' + esc(a.url) + '" target="_blank" rel="noopener">' + ICON.clip +
            '<span><strong>' + esc(a.nombre) + '</strong><small>Abrir el adjunto · ' + esc(tamano(a.tamano)) + '</small></span></a>';
    }

    function pintarLector() {
        var c = abierto, el = $('com-lector');
        if (!c) {
            el.innerHTML = '<div class="com-lector-vacio"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>' +
                (D.recibidos.length ? '<b>Elige un comunicado para leerlo</b><span>Los que no has leído tienen un punto naranja.</span>'
                    : '<b>No tienes comunicados</b><span>Aquí llegan los avisos del administrativo y del Admin para los docentes.</span>') + '</div>';
            return;
        }
        el.innerHTML =
            '<button type="button" class="com-volver" id="com-volver"><svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>Todos los comunicados</button>' +
            (c.urgente ? '<span class="app-status-tag app-status-danger com-urgente">Urgente</span>' : '') +
            '<h3 class="com-lector-titulo">' + esc(c.titulo) + '</h3>' +
            '<div class="com-remitente"><span class="com-av" aria-hidden="true">' + esc(iniciales(c.de.nombre)) + '</span>' +
                '<span class="com-remitente-info"><span class="com-remitente-nombre">' + esc(c.de.nombre) + ' · ' + esc(c.de.rol) + '</span>' +
                '<span class="com-remitente-det">Para ' + esc(c.para.charAt(0).toLowerCase() + c.para.slice(1)) + ' · ' + esc(fechaLarga(c.fecha)) + '</span></span></div>' +
            '<div class="com-cuerpo">' + parrafos(c.cuerpo) + '</div>' +
            (c.adjunto ? adjuntoHtml(c.adjunto) : '');
        $('com-volver').addEventListener('click', function () {
            var id = abierto && abierto.id;
            abierto = null;
            $('com-layout').classList.remove('is-leyendo');
            ponerId(null);
            render();
            var b = $('com-lista').querySelector('[data-id="' + id + '"]');
            if (b) b.focus();
        });
    }

    function abrirRecibido(id, enfocar) {
        var c = D.recibidos.filter(function (x) { return x.id === id; })[0];
        if (!c) return;
        abierto = c;
        $('com-layout').classList.add('is-leyendo');
        ponerId(id);
        render();
        if (enfocar) $('com-lector').focus({ preventScroll: false });
        if (!c.leido) {
            enviar('POST', ruta('leido', id)).then(function () {
                abierto = D.recibidos.filter(function (x) { return x.id === id; })[0] || abierto;
                render();
            });
        }
    }
    function ponerId(id) {
        try {
            var u = new URL(window.location.href);
            if (id) u.searchParams.set('id', id); else u.searchParams.delete('id');
            window.history.replaceState(null, '', u.pathname + u.search);
        } catch (err) { /* sin History API */ }
    }
    $('com-lista').addEventListener('click', function (e) {
        var b = e.target.closest('.com-item');
        if (b) abrirRecibido(+b.dataset.id, window.matchMedia('(max-width: 900px)').matches);
    });

    /* ═══════════ Mis publicaciones ═══════════ */
    function delMio(id) { return D.mios.filter(function (c) { return c.id === id; })[0] || null; }
    function grupoDe(id) { return D.grupos.filter(function (g) { return g.id === id; })[0] || null; }
    function gruposTxt(c) { return c.grupos.map(function (g) { return g.nombre + ' · ' + g.programa; }).join(', '); }
    // Quiénes lo reciben: los estudiantes de los grupos donde sigue vivo (no los de una copia que archivó la secretaría)
    function lectura(c) {
        var total = 0, leidos = 0;
        c.grupos.forEach(function (g) {
            if (g.archivado) return;
            total += g.personas.length;
            leidos += g.personas.filter(function (p) { return p.leido; }).length;
        });
        return { total: total, leidos: leidos };
    }
    function estadoTag(c) {
        return {
            publicado:  '<span class="app-status-tag app-status-active">Publicado</span>',
            programado: '<span class="app-status-tag com-tag-neutro">Programado</span>',
            borrador:   '<span class="app-status-tag com-tag-pend">Borrador</span>',
            archivado:  '<span class="app-status-tag app-status-danger">Archivado</span>'
        }[c.estado] || '';
    }
    function categoriaTag(c) {
        if (c.categoria === 'urgente') return '<span class="app-status-tag app-status-danger">Urgente</span>';
        return '<span class="com-card-tipo">' + esc(D.categorias[c.categoria] || 'General') + '</span>';
    }

    function tarjeta(c) {
        var l = lectura(c), pie;
        if (c.estado === 'publicado' || c.estado === 'archivado') pie = '<span>Leído por <b>' + l.leidos + '</b> de ' + l.total + '</span><span class="com-card-cuando">' + esc(cuando(c.publicado_en)) + '</span>';
        else if (c.estado === 'programado') pie = '<span>Se publica solo</span><span class="com-card-cuando">' + esc(cuando(c.publicado_en)) + '</span>';
        else pie = '<span>Nadie lo ve todavía</span><span class="com-card-cuando">Creado ' + esc(cuando(c.creado).toLowerCase()) + '</span>';
        return '<article class="com-card" tabindex="0" role="button" data-mio="' + c.id + '" aria-label="' + esc('Ver «' + c.titulo + '»') + '">' +
            '<div class="com-card-head">' + estadoTag(c) + categoriaTag(c) + '</div>' +
            '<div><h3 class="com-card-titulo">' + esc(c.titulo) + '</h3><p class="com-card-grupos">Para ' + esc(gruposTxt(c)) + '</p></div>' +
            (c.mensaje ? '<p class="com-card-prev">' + esc(previa(c.mensaje)) + '</p>' : '') +
            '<div class="com-card-pie">' + pie + '</div>' +
        '</article>';
    }
    function renderMios() {
        $('com-grid').innerHTML = D.mios.map(tarjeta).join('');
        $('com-grid').hidden = !D.mios.length;
        $('com-vacio').hidden = D.mios.length > 0;
    }
    $('com-grid').addEventListener('click', function (e) {
        var c = e.target.closest('[data-mio]');
        if (c) verFicha(delMio(+c.dataset.mio), c);
    });
    $('com-grid').addEventListener('keydown', function (e) {
        var c = e.target.closest('[data-mio]');
        if (c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); verFicha(delMio(+c.dataset.mio), c); }
    });

    /* ── Cajón ────────────────────────────────── */
    var drawer = $('com-drawer'), panel = drawer.querySelector('.com-drawer-panel'), ultimoFoco = null, enCajon = null;
    function abrirCajon(titulo, sub, cuerpo, pie, origen) {
        if (!drawer.classList.contains('is-open')) ultimoFoco = origen || document.activeElement;
        $('com-dw-titulo').textContent = titulo;
        $('com-dw-sub').textContent = sub;
        $('com-dw-body').innerHTML = cuerpo;
        $('com-dw-foot').innerHTML = pie || '';
        $('com-dw-foot').hidden = !pie;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        $('com-dw-body').scrollTop = 0;
        setTimeout(function () { $('com-dw-cerrar').focus({ preventScroll: true }); }, 40);
    }
    function cerrarCajon() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        enCajon = null;
        if (ultimoFoco && document.body.contains(ultimoFoco)) ultimoFoco.focus();
        ultimoFoco = null;
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarCajon); });

    function aviso(tono, icono, html) { return '<div class="com-aviso com-aviso-' + tono + '">' + icono + '<span>' + html + '</span></div>'; }
    function info(pares) {
        return '<div class="com-info">' + pares.map(function (p) { return '<div><span class="com-info-lbl">' + esc(p[0]) + '</span><span class="com-info-val">' + esc(p[1]) + '</span></div>'; }).join('') + '</div>';
    }

    /* WhatsApp a quien no lo ha leído: a un estudiante menor se le escribe a su contacto de emergencia */
    function whatsapp(p, c) {
        var a = p.menor && p.contacto ? p.contacto : null;
        var tel = digitos(a ? a.celular : p.celular);
        if (tel.length !== 10) return null;
        var txt = 'Hola ' + primerNombre(a ? a.nombre : p.nombre) + ', le escribimos de ESAT. ' +
            (a ? 'Hay un comunicado del docente para ' + primerNombre(p.nombre) : 'Tiene un comunicado de su docente') +
            ' en la plataforma: «' + c.titulo + '». Por favor ingrese a la plataforma para leerlo. ¡Gracias!';
        return { url: 'https://wa.me/57' + tel + '?text=' + encodeURIComponent(txt), contacto: a };
    }

    var pestanaLectura = 'faltan';
    function lecturas(c) {
        var faltan = 0, leyeron = 0;
        c.grupos.forEach(function (g) { if (!g.archivado) g.personas.forEach(function (p) { if (p.leido) leyeron++; else faltan++; }); });
        var archivado = c.estado === 'archivado';
        var html = '<div><p class="com-seccion-titulo">Quién lo ha leído</p>' +
            '<div class="com-subtabs" role="tablist" aria-label="Lectura">' +
                '<button type="button" class="com-subtab' + (pestanaLectura === 'faltan' ? ' is-active' : '') + '" data-lectura="faltan" role="tab" aria-selected="' + (pestanaLectura === 'faltan') + '">No lo han leído · ' + faltan + '</button>' +
                '<button type="button" class="com-subtab' + (pestanaLectura === 'leyeron' ? ' is-active' : '') + '" data-lectura="leyeron" role="tab" aria-selected="' + (pestanaLectura === 'leyeron') + '">Ya lo leyeron · ' + leyeron + '</button>' +
            '</div></div>';
        c.grupos.forEach(function (g) {
            var lista = g.personas.filter(function (p) { return pestanaLectura === 'faltan' ? !p.leido : !!p.leido; });
            if (pestanaLectura === 'leyeron') lista.sort(function (x, y) { return y.leido.localeCompare(x.leido); });
            var leidos = g.personas.filter(function (p) { return p.leido; }).length;
            html += '<div><div class="com-grupo-cab"><b>' + esc(g.nombre + ' · ' + g.programa) + '</b><span>' + (g.archivado ? 'Archivado aquí' : leidos + ' de ' + g.personas.length + ' lo leyeron') + '</span></div>';
            if (g.archivado) {
                html += aviso('riesgo', ICON.archivar, 'El administrativo lo archivó en este grupo (' + esc(g.archivado.por) + '): ' + esc(g.archivado.motivo) + ' Sus estudiantes ya no lo ven.') + '</div>';
                return;
            }
            if (!g.personas.length) { html += '<p class="com-personas-vacia">Este grupo no tiene estudiantes con matrícula vigente.</p></div>'; return; }
            if (!lista.length) {
                html += '<p class="com-personas-vacia' + (pestanaLectura === 'faltan' ? ' is-bien' : '') + '">' + (pestanaLectura === 'faltan' ? 'Todos lo leyeron.' : 'Nadie lo ha leído todavía.') + '</p></div>';
                return;
            }
            html += '<ul class="com-personas">' + lista.map(function (p) {
                var der;
                if (p.leido) der = 'Lo leyó ' + esc(cuando(p.leido).toLowerCase());
                else {
                    var w = archivado ? null : whatsapp(p, c);
                    der = w ? '<a class="com-btn com-btn-wa com-btn-sm" href="' + esc(w.url) + '" target="_blank" rel="noopener" title="' + esc(w.contacto ? 'Es menor: se le escribe a ' + w.contacto.nombre : 'Escribirle por WhatsApp') + '">' +
                        ICON.wa + (w.contacto ? 'A su contacto' : 'WhatsApp') + '</a>' : (archivado ? '' : 'Sin celular');
                }
                var sub = p.menor && p.contacto ? 'Menor de edad · ' + p.contacto.parentesco + ': ' + p.contacto.nombre : 'Estudiante';
                return '<li><span class="com-persona-av" aria-hidden="true">' + esc(iniciales(p.nombre)) + '</span>' +
                    '<div class="com-min0"><div class="com-persona-nombre">' + esc(p.nombre) + '</div><div class="com-persona-sub">' + esc(sub) + '</div></div>' +
                    '<div class="com-persona-der">' + der + '</div></li>';
            }).join('') + '</ul></div>';
        });
        return html;
    }

    function verFicha(c, origen, mantener) {
        if (!c) return;
        if (!mantener) pestanaLectura = 'faltan';
        enCajon = { tipo: 'ficha', id: c.id };
        var l = lectura(c), cuerpo = '';
        if (c.estado === 'archivado' && c.archivado) {
            cuerpo += aviso('riesgo', ICON.archivar, (c.archivado.por === 'ti' ? 'Lo archivaste' : 'Lo archivó ' + esc(c.archivado.por)) + ' el ' + esc(fechaLarga(c.archivado.fecha)) + ': ' + esc(c.archivado.motivo) + ' Ya nadie lo ve.');
        } else if (c.estado === 'programado') {
            cuerpo += aviso('neutro', ICON.reloj, 'Se publicará solo el <strong>' + esc(fechaLarga(c.publicado_en)) + '</strong>. Hasta entonces nadie lo ve.');
        } else if (c.estado === 'borrador') {
            cuerpo += aviso('pend', ICON.info, 'Es un borrador: nadie lo ve hasta que lo publiques.');
        }
        cuerpo += info([
            [c.estado === 'programado' ? 'Se publica' : (c.estado === 'borrador' ? 'Creado' : 'Publicado'), c.estado === 'borrador' ? cuando(c.creado) : cuando(c.publicado_en)],
            ['Visible hasta', c.visible_hasta ? cuando(c.visible_hasta) : 'Siempre'],
            ['Le llega a', plural(l.total, 'estudiante', 'estudiantes')]
        ]);
        cuerpo += '<div><p class="com-seccion-titulo">Así lo ven tus estudiantes</p><div class="com-preview">' +
            '<h4 class="com-preview-titulo">' + esc(c.titulo) + '</h4>' +
            '<p class="com-preview-msg">' + esc(c.mensaje || 'Todavía no tiene mensaje.') + '</p>' + (c.adjunto ? adjuntoHtml(c.adjunto) : '') + '</div></div>';
        if (c.estado === 'publicado' || c.estado === 'archivado') cuerpo += lecturas(c);

        var pie = '';
        if (c.estado === 'borrador') {
            pie = '<button type="button" class="com-btn com-btn-ghost" data-accion="eliminar">' + ICON.borrar + 'Eliminar borrador</button>' +
                '<button type="button" class="com-btn com-btn-primary" data-accion="editar">' + ICON.editar + 'Editar y publicar</button>';
        } else if (c.estado !== 'archivado') {
            pie = '<button type="button" class="com-btn com-btn-ghost" data-accion="archivar">' + ICON.archivar + 'Archivar</button>' +
                '<button type="button" class="com-btn com-btn-ghost" data-accion="editar">' + ICON.editar + 'Editar</button>';
        }
        abrirCajon(c.titulo, 'Para ' + gruposTxt(c), cuerpo, pie, origen);
    }
    $('com-dw-body').addEventListener('click', function (e) {
        var t = e.target.closest('[data-lectura]');
        if (!t || !enCajon || enCajon.tipo !== 'ficha') return;
        pestanaLectura = t.dataset.lectura;
        var top = $('com-dw-body').scrollTop;
        verFicha(delMio(enCajon.id), null, true);
        $('com-dw-body').scrollTop = top;
        var b = $('com-dw-body').querySelector('[data-lectura="' + pestanaLectura + '"]');
        if (b) b.focus();
    });
    $('com-dw-foot').addEventListener('click', function (e) {
        if (e.target.closest('[data-cerrar-form]')) { cerrarCajon(); return; }
        var b = e.target.closest('[data-accion]');
        if (!b || !enCajon) return;
        var c = delMio(enCajon.id);
        if (b.dataset.accion === 'editar') formulario(c);
        if (b.dataset.accion === 'archivar') confirmar('archivar', c);
        if (b.dataset.accion === 'eliminar') confirmar('eliminar', c);
    });

    /* ── Formulario ───────────────────────────── */
    function mananaTemprano() {
        var d = new Date(); d.setDate(d.getDate() + 1);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T07:00';
    }
    function formulario(c, origen) {
        var publicado = !!c && c.estado === 'publicado';
        var elegidos = c ? c.grupos.map(function (g) { return g.id; }) : (D.grupos.filter(function (g) { return g.abierto; }).length === 1 ? [D.grupos.filter(function (g) { return g.abierto; })[0].id] : []);
        // Los grupos que puede elegir: los suyos sin finalizar, y los que ya tenía este comunicado
        var opciones = D.grupos.filter(function (g) { return g.abierto || elegidos.indexOf(g.id) !== -1; });
        var cuando0 = c && c.estado === 'programado' ? 'programar' : 'publicar';
        enCajon = { tipo: 'form', id: c ? c.id : null };

        var cuerpo = '';
        if (!opciones.length) {
            abrirCajon('Nuevo comunicado', 'A tus grupos', aviso('neutro', ICON.info, 'No tienes grupos sin finalizar: los comunicados se publican a los grupos que tienes a cargo.'), '<button type="button" class="com-btn com-btn-ghost" data-cerrar-form>Cerrar</button>', origen);
            return;
        }
        cuerpo += '<div class="com-campo"><fieldset class="com-cuando" id="com-f-grupos"><legend class="com-lbl">Para</legend><div class="com-chips com-chips-grupos">' +
            opciones.map(function (g) {
                return '<label class="com-chip"><input type="checkbox" name="com-f-grupo" value="' + g.id + '"' + (elegidos.indexOf(g.id) !== -1 ? ' checked' : '') + (publicado ? ' disabled' : '') + '><span>' + esc(g.nombre + ' · ' + g.programa) + '</span></label>';
            }).join('') + '</div></fieldset>' +
            '<span class="com-ayuda">' + (publicado ? 'Ya está publicado: los grupos no se cambian.' : 'Les llega a los estudiantes con matrícula vigente en cada grupo.') + '</span></div>';
        cuerpo += '<div class="com-campo"><label for="com-f-titulo">Título</label><input class="com-input" id="com-f-titulo" maxlength="120" autocomplete="off" value="' + esc(c ? c.titulo : '') + '" placeholder="Ej.: Cambio de salón para la clase del jueves"></div>';
        cuerpo += '<div class="com-campo"><label for="com-f-categoria">Categoría</label><select class="com-input" id="com-f-categoria">' +
            Object.keys(D.categorias).map(function (k) { return '<option value="' + esc(k) + '"' + ((c ? c.categoria : 'academico') === k ? ' selected' : '') + '>' + esc(D.categorias[k]) + '</option>'; }).join('') + '</select></div>';
        cuerpo += '<div class="com-campo"><label for="com-f-mensaje">Mensaje</label><textarea class="com-input" id="com-f-mensaje" maxlength="2000" rows="7" placeholder="Lo que tus estudiantes deben saber">' + esc(c ? c.mensaje : '') + '</textarea>' +
            '<span class="com-ayuda">Hasta 2.000 caracteres. Deja una línea en blanco para separar párrafos.</span></div>';
        cuerpo += '<div class="com-campo"><span class="com-lbl">Adjunto</span>' +
            (c && c.adjunto ? '<div class="com-adj-actual" id="com-f-adj-actual">' + ICON.clip + '<span>' + esc(c.adjunto.nombre) + '</span><button type="button" class="com-link is-peligro" id="com-f-quitar">Quitar</button></div>' : '') +
            '<input type="file" class="com-adj-input" id="com-f-adjunto" accept=".pdf,.jpg,.jpeg,.png" aria-label="Adjunto">' +
            '<span class="com-ayuda">Opcional: un PDF, JPG o PNG de hasta 5 MB' + (c && c.adjunto ? '; si eliges otro, reemplaza al actual.' : '.') + '</span></div>';
        if (!publicado) {
            cuerpo += '<div class="com-campo"><fieldset class="com-cuando"><legend class="com-lbl">Cuándo</legend><div class="com-chips">' +
                '<label class="com-chip"><input type="radio" name="com-f-cuando" value="publicar"' + (cuando0 === 'publicar' ? ' checked' : '') + '><span>Publicar ahora</span></label>' +
                '<label class="com-chip"><input type="radio" name="com-f-cuando" value="programar"' + (cuando0 === 'programar' ? ' checked' : '') + '><span>Programar</span></label>' +
                '</div></fieldset></div>' +
                '<div class="com-campo" id="com-f-prog-campo"' + (cuando0 === 'programar' ? '' : ' hidden') + '><label for="com-f-publicar">Se publica el</label>' +
                '<input class="com-input" type="datetime-local" id="com-f-publicar" value="' + esc(c && c.publicado_en && c.estado === 'programado' ? c.publicado_en.replace(' ', 'T') : mananaTemprano()) + '"></div>';
        }
        cuerpo += '<div class="com-campo"><label for="com-f-visible">Visible hasta (opcional)</label><input class="com-input" type="date" id="com-f-visible" value="' + esc(c && c.visible_hasta ? c.visible_hasta : '') + '">' +
            '<span class="com-ayuda">Después de esa fecha deja de verse. Vacío: se ve siempre.</span></div>';
        var pie = '<p class="com-error" id="com-f-error" role="alert"></p>' +
            '<button type="button" class="com-btn com-btn-ghost" data-cerrar-form>Cancelar</button>' +
            (publicado ? '' : '<button type="button" class="com-btn com-btn-ghost" id="com-f-borrador">Guardar borrador</button>') +
            '<button type="button" class="com-btn com-btn-primary" id="com-f-guardar">' + (publicado ? 'Guardar cambios' : (cuando0 === 'programar' ? 'Programar' : 'Publicar')) + '</button>';
        abrirCajon(c ? 'Editar comunicado' : 'Nuevo comunicado', c ? 'Para ' + gruposTxt(c) : 'A tus grupos', cuerpo, pie, origen);

        var quitar = false;
        if ($('com-f-quitar')) $('com-f-quitar').addEventListener('click', function () {
            quitar = true;
            $('com-f-adj-actual').hidden = true;
            $('com-f-adjunto').focus();
        });
        $('com-f-adjunto').addEventListener('change', function () {
            var f = this.files && this.files[0];
            $('com-f-error').textContent = '';
            this.classList.remove('is-invalido');
            if (!f) return;
            if (EXT.indexOf(extension(f.name)) === -1) { this.value = ''; errorForm('El adjunto debe ser PDF, JPG o PNG.', 'com-f-adjunto'); return; }
            if (f.size > MAX_ADJUNTO) { this.value = ''; errorForm('El adjunto pesa ' + tamano(f.size) + ': el máximo es 5 MB.', 'com-f-adjunto'); }
        });
        $('com-dw-body').querySelectorAll('input[name=com-f-cuando]').forEach(function (r) {
            r.addEventListener('change', function () {
                var prog = this.value === 'programar';
                $('com-f-prog-campo').hidden = !prog;
                $('com-f-guardar').textContent = prog ? 'Programar' : 'Publicar';
            });
        });
        if ($('com-f-borrador')) $('com-f-borrador').addEventListener('click', function () { guardar(c, 'borrador', quitar, this); });
        $('com-f-guardar').addEventListener('click', function () {
            var accion = publicado ? 'publicar' : (($('com-dw-body').querySelector('input[name=com-f-cuando]:checked') || {}).value || 'publicar');
            guardar(c, accion, quitar, this);
        });
    }

    function errorForm(msg, campo) {
        var el = $('com-f-error');
        if (el) el.textContent = msg;
        if (campo && $(campo)) {
            $(campo).classList.add('is-invalido');
            var foco = $(campo).matches('fieldset') ? $(campo).querySelector('input:not(:disabled)') : $(campo);
            if (foco) foco.focus();
        }
        return false;
    }
    document.addEventListener('input', function (e) { if (e.target.classList && e.target.classList.contains('is-invalido')) e.target.classList.remove('is-invalido'); });

    var CAMPOS = { grupos: 'com-f-grupos', titulo: 'com-f-titulo', categoria: 'com-f-categoria', mensaje: 'com-f-mensaje', adjunto: 'com-f-adjunto', publicar_en: 'com-f-publicar', visible_hasta: 'com-f-visible' };
    function guardar(c, accion, quitar, btn) {
        var body = $('com-dw-body');
        var grupos = Array.prototype.map.call(body.querySelectorAll('input[name=com-f-grupo]:checked'), function (i) { return i.value; });
        var titulo = $('com-f-titulo').value.trim(), mensaje = $('com-f-mensaje').value.trim();
        var publicar = $('com-f-publicar') ? $('com-f-publicar').value : '';
        var archivo = $('com-f-adjunto').files && $('com-f-adjunto').files[0];
        $('com-f-error').textContent = '';
        if (!grupos.length) return errorForm('Elige al menos un grupo.', 'com-f-grupos');
        if (titulo.length < 5) return errorForm('Escribe un título de al menos 5 letras.', 'com-f-titulo');
        if (accion !== 'borrador' && mensaje.length < 10) return errorForm('Escribe el mensaje del comunicado.', 'com-f-mensaje');
        if (accion === 'programar') {
            if (!publicar) return errorForm('Elige la fecha y la hora en que se publica.', 'com-f-publicar');
            if (publicar.replace('T', ' ') <= D.ahora) return errorForm('La fecha y hora de publicación deben ser futuras.', 'com-f-publicar');
        }

        var f = new FormData();
        f.append('accion', accion);
        grupos.forEach(function (g) { f.append('grupos[]', g); });
        f.append('titulo', titulo);
        f.append('categoria', $('com-f-categoria').value);
        f.append('mensaje', mensaje);
        if (accion === 'programar') f.append('publicar_en', publicar.replace('T', ' '));
        f.append('visible_hasta', $('com-f-visible').value);
        if (archivo) f.append('adjunto', archivo);
        if (quitar && !archivo) f.append('quitar_adjunto', '1');

        var botones = $('com-dw-foot').querySelectorAll('button');   // el pie también trae el error
        botones.forEach(function (b) { b.disabled = true; });
        enviar(c ? 'PUT' : 'POST', c ? ruta('editar', c.id) : RUTAS.crear, f).then(function (r) {
            botones.forEach(function (b) { b.disabled = false; });
            if (!r.ok) return errorForm(r.mensaje, r.campo ? CAMPOS[r.campo] : null);
            cerrarCajon();
            if (vista !== 'mios') mostrar('mios'); else render();
            toast(r.mensaje);
            var t = $('com-grid').querySelector('[data-mio="' + r.comunicado + '"]');
            if (t) t.focus();
        });
        return true;
    }
    $('com-nuevo').addEventListener('click', function () { formulario(null, this); });

    /* ── Archivar / eliminar ──────────────────── */
    var aConfirmar = null, focoDlg = null;
    function confirmar(tipo, c) {
        aConfirmar = { tipo: tipo, c: c };
        focoDlg = document.activeElement;
        var archivar = tipo === 'archivar';
        $('com-dlg-titulo').textContent = archivar ? '¿Archivar el comunicado?' : '¿Eliminar el borrador?';
        $('com-dlg-txt').textContent = archivar
            ? '«' + c.titulo + '» deja de verse en el panel de tus estudiantes de ' + gruposTxt(c) + '. Queda aquí con quién lo leyó.'
            : '«' + c.titulo + '» se elimina. Nadie lo ha visto todavía.';
        $('com-dlg-motivo-campo').hidden = !archivar;
        $('com-dlg-motivo').value = '';
        $('com-dlg-motivo').classList.remove('is-invalido');
        $('com-dlg-error').textContent = '';
        $('com-dlg-si').textContent = archivar ? 'Sí, archivar' : 'Sí, eliminar';
        $('com-dlg-si').disabled = false;
        $('com-dialogo').hidden = false;
        (archivar ? $('com-dlg-motivo') : $('com-dlg-si')).focus();
    }
    function cerrarDialogo() {
        $('com-dialogo').hidden = true;
        aConfirmar = null;
        if (focoDlg && document.body.contains(focoDlg)) focoDlg.focus();
    }
    $('com-dialogo').querySelectorAll('[data-no]').forEach(function (b) { b.addEventListener('click', cerrarDialogo); });
    $('com-dlg-si').addEventListener('click', function () {
        var x = aConfirmar, btn = this;
        if (!x) return;
        var motivo = $('com-dlg-motivo').value.trim();
        if (x.tipo === 'archivar' && motivo.length < 5) {
            $('com-dlg-error').textContent = 'Escribe por qué lo archivas (al menos 5 letras).';
            $('com-dlg-motivo').classList.add('is-invalido');
            $('com-dlg-motivo').focus();
            return;
        }
        btn.disabled = true;
        var p = x.tipo === 'archivar' ? enviar('PATCH', ruta('archivar', x.c.id), { motivo: motivo }) : enviar('DELETE', ruta('eliminar', x.c.id));
        p.then(function (r) {
            btn.disabled = false;
            if (!r.ok) { $('com-dlg-error').textContent = r.mensaje; return; }
            $('com-dialogo').hidden = true;
            aConfirmar = null;
            focoDlg = null;
            render();
            if (x.tipo === 'archivar' && delMio(x.c.id)) verFicha(delMio(x.c.id));
            else { cerrarCajon(); $('com-nuevo').focus(); }
            toast(r.mensaje);
        });
    });

    /* ── Teclado: Escape y foco atrapado ──────── */
    document.addEventListener('keydown', function (e) {
        if (!$('com-dialogo').hidden) {
            if (e.key === 'Escape') { e.preventDefault(); cerrarDialogo(); }
            if (e.key === 'Tab') atrapar(e, $('com-dialogo').querySelector('.com-dialogo-caja'));
            return;
        }
        if (!drawer.classList.contains('is-open')) return;
        if (e.key === 'Escape') { e.preventDefault(); cerrarCajon(); return; }
        if (e.key === 'Tab') atrapar(e, panel);
    });
    function atrapar(e, cont) {
        var focos = Array.prototype.slice.call(cont.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'))
            .filter(function (el) { return el.offsetParent !== null; });
        if (!focos.length) return;
        var p = focos[0], u = focos[focos.length - 1];
        if (e.shiftKey && (document.activeElement === p || !cont.contains(document.activeElement))) { e.preventDefault(); u.focus(); }
        else if (!e.shiftKey && (document.activeElement === u || !cont.contains(document.activeElement))) { e.preventDefault(); p.focus(); }
    }

    var toastT = null;
    function toast(msg, error) {
        if (!msg) return;
        var t = $('com-toast');
        t.textContent = msg;
        t.classList.toggle('is-error', !!error);
        t.setAttribute('role', error ? 'alert' : 'status');
        t.hidden = false;
        requestAnimationFrame(function () { t.classList.add('is-visible'); });
        clearTimeout(toastT);
        toastT = setTimeout(function () { t.classList.remove('is-visible'); setTimeout(function () { t.hidden = true; }, 220); }, 4500);
    }

    mostrar(vista);
    if (idParam && vista === 'recibidos') abrirRecibido(idParam, false);
})();
