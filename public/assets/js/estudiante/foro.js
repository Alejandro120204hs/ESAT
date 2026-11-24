/* ESAT — Estudiante: Foro de la sede
   Lee window.EST_DATOS.foro y guarda con window.EST_API (abrirTema, responderTema, reportar; datos.js: ForoService
   en el servidor, con las reglas de la secretaría). Lista de temas (fijados primero, luego por última
   actividad) con categorías y búsqueda; la conversación se abre en el cajón lateral. ?tema=ID abre uno. */
(function () {
    'use strict';

    var D = window.EST_DATOS, API = window.EST_API;
    var app = document.getElementById('for-app');
    if (!D || !API || !app) return;

    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var NB = ' ';   // espacio que no parte la línea
    var ROL = { e: 'Estudiante', d: 'Docente', a: 'Acudiente', x: 'Secretaría' };
    var EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    var ICON = {
        pin:     '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v5"/><path d="M9 10.76V6h6v4.76l2 3.24H7z"/><path d="M8 6h8"/></svg>',
        candado: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        bandera: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        clip:    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
        enviar:  '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>'
    };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function $(id) { return document.getElementById(id); }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function diasEntre(a, b) { return Math.round((fecha(b) - fecha(a)) / 86400000); }
    function cuando(iso) {
        var n = diasEntre(iso, D.hoy);
        if (n <= 0) return 'hoy a las ' + iso.slice(11, 16);
        if (n === 1) return 'ayer';
        if (n < 7) return 'hace' + NB + n + NB + 'días';
        var f = fecha(iso);
        return 'el ' + f.getDate() + NB + MESES_C[f.getMonth()];
    }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }
    function iniciales(n) { var p = String(n).split(' ').filter(Boolean); return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase(); }
    function sinTildes(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    function extension(n) { return (String(n).split('.').pop() || '').toLowerCase(); }
    function tamano(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + NB + 'MB' : Math.max(1, Math.round(b / 1024)) + NB + 'KB'; }
    function rolHtml(a) { return '<span class="for-rol"><i class="r-' + esc(a.rol) + '"></i>' + (a.yo ? 'Tú' : ROL[a.rol]) + '</span>'; }

    /* ── Lista ────────────────────────────────── */
    var categoria = 'todas', abierto = null;
    function pintarCats() {
        var html = '<legend class="sr-solo">Categoría</legend>' + chip('todas', 'Todas');
        D.foro.categorias.forEach(function (c) { html += chip(c, c); });
        $('for-cats').innerHTML = html;
        function chip(v, t) { return '<label class="for-chip"><input type="radio" name="for-cat" value="' + esc(v) + '"' + (v === categoria ? ' checked' : '') + '><span>' + esc(t) + '</span></label>'; }
    }
    $('for-cats').addEventListener('change', function (e) { if (e.target.name === 'for-cat') { categoria = e.target.value; pintarTemas(); } });
    $('for-buscar').addEventListener('input', pintarTemas);

    function pintarTemas() {
        var q = sinTildes($('for-buscar').value.trim());
        var lista = D.foro.temas.filter(function (t) {
            if (categoria !== 'todas' && t.categoria !== categoria) return false;
            if (!q) return true;
            return sinTildes(t.titulo + ' ' + t.mensajes.map(function (m) { return m.texto + ' ' + m.autor.nombre; }).join(' ')).indexOf(q) !== -1;
        }).sort(function (a, b) {
            if (a.fijado !== b.fijado) return a.fijado ? -1 : 1;
            return a.ultimo < b.ultimo ? 1 : -1;
        });
        $('for-temas').innerHTML = lista.map(function (t) {
            var autor = t.mensajes[0].autor, resp = t.mensajes.length - 1;
            return '<li><button type="button" class="for-tema' + (t.fijado ? ' is-fijado' : '') + '" data-tema="' + t.id + '">' +
                '<span class="for-av r-' + esc(autor.rol) + '" aria-hidden="true">' + esc(iniciales(autor.nombre)) + '</span>' +
                '<span class="for-tema-info">' +
                    '<span class="for-tema-titulo">' + esc(t.titulo) + '</span>' +
                    '<span class="for-tema-meta"><span>' + esc(autor.yo ? 'Abierto por ti' : autor.nombre) + '</span>' + (autor.yo ? '' : rolHtml(autor)) +
                        '<span class="for-tags"><span class="for-cat">' + esc(t.categoria) + '</span>' +
                        (t.fijado ? '<span class="for-pin">' + ICON.pin + 'Fijado</span>' : '') +
                        (t.cerrado ? '<span class="app-status-tag app-status-muted">Cerrado</span>' : '') + '</span>' +
                    '</span>' +
                '</span>' +
                '<span class="for-tema-der"><span class="for-tema-num">' + resp + '<small>' + (resp === 1 ? 'respuesta' : 'respuestas') + '</small></span>' +
                    '<span class="for-tema-ult">' + esc(cuando(t.ultimo)) + '</span></span>' +
            '</button></li>';
        }).join('');
        $('for-vacio').hidden = lista.length > 0;
    }
    $('for-temas').addEventListener('click', function (e) {
        var b = e.target.closest('[data-tema]');
        if (b) abrir(+b.dataset.tema, b);
    });

    /* ── Conversación ─────────────────────────── */
    var drawer = $('for-drawer'), panel = drawer.querySelector('.for-drawer-panel'), ultimoFoco = null, adjunto = null, enviando = false;
    function temaDe(id) { return D.foro.temas.filter(function (t) { return t.id === id; })[0] || null; }
    function abrir(id, origen) {
        var t = temaDe(id);
        if (!t) return;
        if (!drawer.classList.contains('is-open')) ultimoFoco = origen || document.activeElement;
        abierto = t;
        adjunto = null;
        pintarHilo();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        ponerUrl(id);
        setTimeout(function () { $('for-dw-cerrar').focus({ preventScroll: true }); }, 40);
    }
    function cerrar() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        var id = abierto && abierto.id;
        abierto = null;
        ponerUrl(null);
        var volver = ultimoFoco && document.body.contains(ultimoFoco) ? ultimoFoco : $('for-temas').querySelector('[data-tema="' + id + '"]');
        if (volver && volver.focus) volver.focus();
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrar); });
    function ponerUrl(id) {
        try {
            var u = new URL(window.location.href);
            if (id) u.searchParams.set('tema', id); else u.searchParams.delete('tema');
            window.history.replaceState(null, '', u.pathname + u.search);
        } catch (err) { /* sin History API */ }
    }

    function pintarHilo(alFinal) {
        var t = abierto;
        $('for-dw-titulo').textContent = t.titulo;
        $('for-dw-sub').innerHTML = '<span class="for-cat">' + esc(t.categoria) + '</span>' +
            (t.fijado ? '<span class="for-pin">' + ICON.pin + 'Fijado</span>' : '') +
            (t.cerrado ? '<span class="app-status-tag app-status-muted">Cerrado</span>' : '') +
            '<span>' + esc(plural(t.mensajes.length - 1, 'respuesta', 'respuestas')) + '</span>';
        $('for-dw-body').innerHTML = '<ol class="for-hilo">' + t.mensajes.map(function (m, i) {
            var pie = '';
            if (!m.autor.yo) {
                pie = m.reportado ? '<span class="for-reportado">Lo reportaste: la secretaría lo revisará</span>'
                    : '<button type="button" class="for-link-btn" data-reportar="' + m.id + '" aria-label="' + esc('Reportar el mensaje de ' + m.autor.nombre) + '">' + ICON.bandera + 'Reportar</button>';
            }
            return '<li class="for-msg' + (i === 0 ? ' is-primero' : '') + (m.autor.yo ? ' is-mio' : '') + '" id="for-msg-' + m.id + '">' +
                '<span class="for-av r-' + esc(m.autor.rol) + '" aria-hidden="true">' + esc(iniciales(m.autor.nombre)) + '</span>' +
                '<div>' +
                    '<div class="for-msg-cab"><span class="for-msg-quien"><span class="for-msg-nombre">' + esc(m.autor.nombre) + '</span>' + rolHtml(m.autor) + '</span>' +
                        '<span class="for-msg-fecha">' + esc(cuando(m.fecha)) + '</span></div>' +
                    '<p class="for-msg-texto">' + esc(m.texto) + '</p>' +
                    (m.adjunto ? '<a class="for-adjunto" href="' + esc(m.adjunto.url) + '" target="_blank" rel="noopener">' + ICON.clip + '<span><strong>' + esc(m.adjunto.nombre) + '</strong><small>' + esc(tamano(m.adjunto.tamano)) + '</small></span></a>' : '') +
                    '<div class="for-msg-pie">' + pie + '</div>' +
                '</div>' +
            '</li>';
        }).join('') + '</ol>';

        var r = $('for-responder');
        if (t.cerrado) {
            r.innerHTML = '<p class="for-cerrado">' + ICON.candado + 'Este tema está cerrado: ya no recibe respuestas.</p>';
        } else {
            r.innerHTML = '<label class="sr-solo" for="for-resp-texto">Tu respuesta</label>' +
                '<textarea class="for-textarea" id="for-resp-texto" placeholder="Escribe tu respuesta..." maxlength="3000"></textarea>' +
                '<p class="for-error" id="for-resp-error" role="alert"></p>' +
                '<div class="for-responder-pie">' + adjuntoHtml('for-resp-adj') +
                    '<button type="button" class="for-btn for-btn-primary" id="for-resp-enviar">' + ICON.enviar + 'Responder</button></div>';
            conectarAdjunto('for-resp-adj', function (a) { adjunto = a; }, 'for-resp-error');
            $('for-resp-enviar').addEventListener('click', responder);
        }
        var body = $('for-dw-body');
        body.scrollTop = alFinal ? body.scrollHeight : 0;
    }

    function adjuntoHtml(id) {
        return '<span class="for-adj"><input type="file" id="' + id + '" accept=".pdf,.jpg,.jpeg,.png">' +
            '<label for="' + id + '" class="for-btn for-btn-ghost for-btn-sm" tabindex="0" role="button" data-adj-btn>' + ICON.clip + 'Adjuntar</label>' +
            '<span class="for-adj-elegido" data-adj-nombre></span>' +
            '<button type="button" class="for-adj-quitar" data-adj-quitar hidden>Quitar</button></span>';
    }
    function conectarAdjunto(id, alCambiar, errorId) {
        var input = $(id), cont = input.parentNode;
        var nombre = cont.querySelector('[data-adj-nombre]'), quitar = cont.querySelector('[data-adj-quitar]');
        cont.querySelector('[data-adj-btn]').addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            $(errorId).textContent = '';
            if (!f) return;
            if (EXT.indexOf(extension(f.name)) === -1) { $(errorId).textContent = 'El adjunto debe ser PDF, JPG o PNG.'; input.value = ''; return; }
            if (f.size > D.limites.adjunto) { $(errorId).textContent = 'El adjunto pesa ' + tamano(f.size) + ': el máximo es 5 MB.'; input.value = ''; return; }
            nombre.textContent = f.name;
            quitar.hidden = false;
            alCambiar(f);
        });
        quitar.addEventListener('click', function () { input.value = ''; nombre.textContent = ''; quitar.hidden = true; alCambiar(null); });
    }

    function responder() {
        if (enviando) return;
        var txt = $('for-resp-texto');
        txt.oninput = function () { txt.classList.remove('is-invalido'); $('for-resp-error').textContent = ''; };
        if (!txt.value.trim()) { $('for-resp-error').textContent = 'Escribe tu respuesta.'; txt.classList.add('is-invalido'); txt.focus(); return; }
        enviando = true;
        $('for-resp-enviar').disabled = true;
        API.responderTema(abierto.id, { texto: txt.value, adjunto: adjunto }).then(function (r) {
            enviando = false;
            if (!r.ok) { $('for-resp-error').textContent = r.mensaje; $('for-resp-enviar').disabled = false; return; }
            toast(r.mensaje);
            abierto = temaDe(abierto.id);
            adjunto = null;
            pintarHilo(true);
            pintarTemas();
            $('for-resp-texto').focus();
        });
    }

    $('for-dw-body').addEventListener('click', function (e) {
        var b = e.target.closest('[data-reportar]');
        if (b) dialogoReportar(+b.dataset.reportar, b);
    });

    /* ── Diálogo: abrir tema / reportar ───────── */
    var alEnviar = null, focoAntes = null, adjTema = null;
    function abrirDialogo(titulo, boton, cuerpo, fn) {
        focoAntes = document.activeElement;
        alEnviar = fn;
        $('for-dlg-titulo').textContent = titulo;
        $('for-dlg-si').textContent = boton;
        $('for-dlg-si').disabled = false;
        $('for-dlg-cuerpo').innerHTML = cuerpo;
        $('for-dlg-error').textContent = '';
        $('for-dialogo').hidden = false;
        var primero = $('for-dlg-cuerpo').querySelector('input:not([type=file]), textarea');
        if (primero) primero.focus();
    }
    function cerrarDialogo() {
        $('for-dialogo').hidden = true;
        alEnviar = null;
        if (focoAntes && focoAntes.focus) focoAntes.focus();
    }
    $('for-dialogo').querySelectorAll('[data-no]').forEach(function (b) { b.addEventListener('click', cerrarDialogo); });
    $('for-dlg-form').addEventListener('submit', function (e) { e.preventDefault(); if (alEnviar) alEnviar(); });
    // Al corregir un campo se quita la marca de error
    $('for-dlg-form').addEventListener('input', function (e) { if (e.target.classList) e.target.classList.remove('is-invalido'); });

    $('for-nuevo').addEventListener('click', function () {
        adjTema = null;
        var cats = D.foro.categorias.map(function (c, i) {
            return '<label class="for-chip"><input type="radio" name="for-nt-cat" value="' + esc(c) + '"' + (categoria === c || (categoria === 'todas' && i === 0) ? ' checked' : '') + '><span>' + esc(c) + '</span></label>';
        }).join('');
        abrirDialogo('Abrir un tema', 'Publicar',
            '<div class="for-campo"><fieldset><legend>Categoría</legend><div class="for-chips">' + cats + '</div></fieldset></div>' +
            '<div class="for-campo"><label for="for-nt-titulo">Título</label><input class="for-input" id="for-nt-titulo" maxlength="120" autocomplete="off" placeholder="Ej.: ¿A qué hora es la práctica del sábado?"></div>' +
            '<div class="for-campo"><label for="for-nt-texto">Mensaje</label><textarea class="for-textarea" id="for-nt-texto" maxlength="3000" placeholder="Cuenta tu pregunta o lo que quieres compartir"></textarea>' +
                '<span class="for-ayuda">Lo leen estudiantes, docentes, acudientes y la secretaría de tu sede. No publiques datos personales de otras personas.</span></div>' +
            '<div class="for-campo">' + adjuntoHtml('for-nt-adj') + '<span class="for-ayuda">Opcional: un PDF, JPG o PNG de hasta 5 MB.</span></div>',
            function () {
                var cat = ($('for-dlg-cuerpo').querySelector('input[name=for-nt-cat]:checked') || {}).value;
                var titulo = $('for-nt-titulo'), texto = $('for-nt-texto');
                titulo.classList.remove('is-invalido'); texto.classList.remove('is-invalido');
                if (titulo.value.trim().length < 5) { $('for-dlg-error').textContent = 'Escribe un título de al menos 5 letras.'; titulo.classList.add('is-invalido'); titulo.focus(); return; }
                if (texto.value.trim().length < 10) { $('for-dlg-error').textContent = 'Escribe tu mensaje (al menos 10 caracteres).'; texto.classList.add('is-invalido'); texto.focus(); return; }
                $('for-dlg-si').disabled = true;
                API.abrirTema({ categoria: cat, titulo: titulo.value, texto: texto.value, adjunto: adjTema }).then(function (r) {
                    if (!r.ok) { $('for-dlg-error').textContent = r.mensaje; $('for-dlg-si').disabled = false; return; }
                    cerrarDialogo();
                    toast(r.mensaje);
                    categoria = 'todas';
                    $('for-buscar').value = '';
                    pintarCats();
                    pintarTemas();
                    if (r.tema) abrir(r.tema, $('for-nuevo'));
                });
            });
        conectarAdjunto('for-nt-adj', function (a) { adjTema = a; }, 'for-dlg-error');
    });

    function dialogoReportar(mensajeId, origen) {
        abrirDialogo('Reportar el mensaje', 'Reportar',
            '<p class="for-texto-dlg">La secretaría de tu sede lo revisará y decidirá si lo oculta. Quien lo escribió no sabrá que fuiste tú.</p>' +
            '<div class="for-campo"><label for="for-rep-motivo">¿Por qué lo reportas?</label><textarea class="for-textarea" id="for-rep-motivo" maxlength="500" placeholder="Ej.: es ofensivo, publica datos de otra persona, es publicidad"></textarea></div>',
            function () {
                var m = $('for-rep-motivo');
                if (m.value.trim().length < 5) { $('for-dlg-error').textContent = 'Cuéntale a la secretaría por qué lo reportas (al menos 5 caracteres).'; m.classList.add('is-invalido'); m.focus(); return; }
                $('for-dlg-si').disabled = true;
                API.reportar(mensajeId, m.value).then(function (r) {
                    if (!r.ok) { $('for-dlg-error').textContent = r.mensaje; $('for-dlg-si').disabled = false; return; }
                    cerrarDialogo();
                    toast(r.mensaje);
                    abierto = temaDe(abierto.id);
                    pintarHilo();
                    var el = $('for-msg-' + mensajeId);
                    if (el) el.scrollIntoView({ block: 'center' });
                    $('for-dw-cerrar').focus();
                });
            });
        focoAntes = origen;
    }

    /* ── Teclado: Escape y foco atrapado ──────── */
    document.addEventListener('keydown', function (e) {
        if (!$('for-dialogo').hidden) {
            if (e.key === 'Escape') { e.preventDefault(); cerrarDialogo(); }
            if (e.key === 'Tab') atrapar(e, $('for-dlg-form'));
            return;
        }
        if (!drawer.classList.contains('is-open')) return;
        if (e.key === 'Escape') { e.preventDefault(); cerrar(); return; }
        if (e.key === 'Tab') atrapar(e, panel);
    });
    function atrapar(e, cont) {
        var focos = Array.prototype.slice.call(cont.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type=file]), textarea, [tabindex]:not([tabindex="-1"])'))
            .filter(function (el) { return el.offsetParent !== null; });
        if (!focos.length) return;
        var primero = focos[0], ultimo = focos[focos.length - 1];
        if (e.shiftKey && (document.activeElement === primero || !cont.contains(document.activeElement))) { e.preventDefault(); ultimo.focus(); }
        else if (!e.shiftKey && (document.activeElement === ultimo || !cont.contains(document.activeElement))) { e.preventDefault(); primero.focus(); }
    }

    var toastT = null;
    function toast(msg, error) {
        if (!msg) return;
        var t = $('for-toast');
        t.textContent = msg;
        t.classList.toggle('is-error', !!error);
        t.hidden = false;
        requestAnimationFrame(function () { t.classList.add('is-visible'); });
        clearTimeout(toastT);
        toastT = setTimeout(function () { t.classList.remove('is-visible'); setTimeout(function () { t.hidden = true; }, 220); }, 4000);
    }

    pintarCats();
    pintarTemas();
    var tParam = +((window.location.search.match(/[?&]tema=(\d+)/) || [])[1] || 0);
    if (tParam) abrir(tParam);
})();
