/* ESAT — Estudiante: Aula virtual
   Lee window.EST_DATOS y guarda con window.EST_API (datos.js: AulaService en el servidor). Pestañas Por hacer / Resultados, chips por módulo y un cajón lateral para responder una evaluación
   (una sola vez, con confirmación), ver su resultado (las correctas solo después del cierre) o entregar y cambiar el
   archivo de una actividad hasta la fecha límite. ?id=ID abre una; ?modulo=ID filtra. */
(function () {
    'use strict';

    var D = window.EST_DATOS, API = window.EST_API;
    var app = document.getElementById('aul-app');
    if (!D || !API || !app) return;

    var DIAS_C  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var NB = ' ';   // espacio que no parte la línea
    var EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    var ICON = {
        evaluacion: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        actividad:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>',
        reloj:      '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        info:       '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
        alerta:     '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        check:      '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
        x:          '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        subir:      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
        enviar:     '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>'
    };

    /* ── Utilidades ───────────────────────────── */
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function $(id) { return document.getElementById(id); }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function diasEntre(a, b) { return Math.round((fecha(b) - fecha(a)) / 86400000); }
    function fechaCorta(iso) { var f = fecha(iso); return DIAS_C[f.getDay()] + NB + f.getDate() + NB + MESES_C[f.getMonth()]; }
    function hora(iso) { return String(iso).slice(11, 16); }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }
    function nota(v) { return v == null ? '—' : Number(v).toFixed(1).replace('.', ','); }
    function tono(n) { return n == null ? '' : (n >= 4 ? 'ok' : (n >= D.reglas.APRUEBA ? 'warn' : 'bad')); }
    function tamano(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + NB + 'MB' : Math.max(1, Math.round(b / 1024)) + NB + 'KB'; }
    function extension(n) { return (String(n).split('.').pop() || '').toLowerCase(); }
    function moduloDe(id) { return D.modulos.filter(function (m) { return m.id === id; })[0] || null; }

    /* ── Estado de cada evaluación o actividad ── */
    function todos() { return D.evaluaciones.concat(D.actividades); }
    // Evaluaciones y actividades vienen de tablas distintas y pueden tener el mismo número: se buscan por "tipo-id"
    function clave(x) { return x.tipo + '-' + x.id; }
    function buscar(c) { return todos().filter(function (x) { return clave(x) === c; })[0] || null; }
    function estadoDe(x) {
        if (x.tipo === 'evaluacion') return x.respuesta ? 'calificada' : (x.cerrada ? 'no_respondida' : 'por_hacer');
        if (!x.entrega) return x.cerrada ? 'no_entregada' : 'por_hacer';
        return x.entrega.nota != null ? 'calificada' : 'entregada';
    }
    function notaDe(x) { return x.tipo === 'evaluacion' ? (x.respuesta && x.respuesta.nota) : (x.entrega && x.entrega.nota); }
    var TAG = {
        calificada:    ['app-status-active', 'Calificada'],
        entregada:     ['app-status-muted', 'Esperando calificación'],
        no_respondida: ['app-status-danger', 'No respondida'],
        no_entregada:  ['app-status-danger', 'No entregada']
    };
    function faltaTxt(x) {
        var n = diasEntre(D.hoy, x.cierra);
        if (n <= 0) return { txt: 'cierra hoy', urgente: true };
        if (n === 1) return { txt: 'cierra mañana', urgente: false };
        return { txt: 'faltan ' + n + ' días', urgente: false };
    }

    /* ── Filtros y pestañas ───────────────────── */
    var vista = 'pendientes', modulo = 'todos';
    var mParam = (window.location.search.match(/[?&]modulo=(\d+)/) || [])[1];
    if (mParam && todos().some(function (x) { return String(x.moduloId) === mParam; })) modulo = mParam;

    function pintarChips() {
        var ids = [];
        todos().forEach(function (x) { if (ids.indexOf(x.moduloId) === -1) ids.push(x.moduloId); });
        ids.sort(function (a, b) { return a - b; });
        var html = '<legend class="sr-solo">Filtrar por módulo</legend>' + chip('todos', 'Todos los módulos');
        ids.forEach(function (id) { var m = moduloDe(id); html += chip(String(id), 'Módulo ' + id, m ? m.nombre : ''); });
        $('aul-modulos').innerHTML = html;
        function chip(v, txt, titulo) {
            return '<label class="aul-chip"' + (titulo ? ' title="' + esc(titulo) + '"' : '') + '><input type="radio" name="aul-modulo" value="' + v + '"' + (v === modulo ? ' checked' : '') + '><span>' + esc(txt) + '</span></label>';
        }
    }
    $('aul-modulos').addEventListener('change', function (e) { if (e.target.name === 'aul-modulo') { modulo = e.target.value; render(); } });

    var tabs = Array.prototype.slice.call(app.querySelectorAll('.aul-vista-btn'));
    function mostrarVista(v, enfocar) {
        vista = v;
        tabs.forEach(function (b) {
            var on = b.dataset.vista === v;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            if (on) { $('aul-panel').setAttribute('aria-labelledby', b.id); if (enfocar) b.focus(); }
        });
        render();
    }
    tabs.forEach(function (b, i) {
        b.addEventListener('click', function () { mostrarVista(b.dataset.vista); });
        b.addEventListener('keydown', function (e) {
            var j = e.key === 'ArrowRight' ? (i + 1) % tabs.length : (e.key === 'ArrowLeft' ? (i - 1 + tabs.length) % tabs.length : null);
            if (j === null) return;
            e.preventDefault();
            mostrarVista(tabs[j].dataset.vista, true);
        });
    });

    /* ── Tarjetas ─────────────────────────────── */
    function tipoTxt(x) {
        return x.tipo === 'evaluacion' ? 'Evaluación · ' + plural(x.preguntas.length, 'pregunta', 'preguntas') : 'Actividad · entrega de archivo';
    }
    function cierreHtml(x, est) {
        var txt, extra = '';
        if (est === 'por_hacer') {
            var f = faltaTxt(x);
            txt = (x.tipo === 'evaluacion' ? 'Cierra el ' : 'Se entrega hasta el ') + fechaCorta(x.cierra) + ' a las ' + hora(x.cierra);
            extra = ' <span class="aul-cierre-falta' + (f.urgente ? ' is-urgente' : '') + '">· ' + esc(f.txt) + '</span>';
        } else if (est === 'entregada' && !x.cerrada) {
            txt = 'Entregada el ' + fechaCorta(x.entrega.fecha) + '. Puedes cambiar el archivo hasta el ' + fechaCorta(x.cierra);
        } else if (est === 'calificada' || est === 'entregada') {
            txt = (x.tipo === 'evaluacion' ? 'Respondida el ' + fechaCorta(x.respuesta.fecha) : 'Entregada el ' + fechaCorta(x.entrega.fecha));
        } else {
            txt = 'Cerró el ' + fechaCorta(x.cierra);
        }
        return '<p class="aul-cierre">' + ICON.reloj + '<span>' + esc(txt) + extra + '</span></p>';
    }
    function tarjeta(x) {
        var est = estadoDe(x), m = moduloDe(x.moduloId), n = notaDe(x), tag = TAG[est];
        var btn;
        if (est === 'por_hacer') btn = '<button type="button" class="aul-btn aul-btn-primary aul-btn-sm" data-abrir="' + clave(x) + '">' + (x.tipo === 'evaluacion' ? ICON.evaluacion + 'Responder' : ICON.subir + 'Entregar') + '</button>';
        else if (est === 'entregada' && !x.cerrada) btn = '<button type="button" class="aul-btn aul-btn-ghost aul-btn-sm" data-abrir="' + clave(x) + '">Ver o cambiar</button>';
        else btn = '<button type="button" class="aul-btn aul-btn-ghost aul-btn-sm" data-abrir="' + clave(x) + '">' + (est === 'calificada' ? 'Ver resultado' : 'Ver detalle') + '</button>';
        return '<article class="aul-card" tabindex="0" data-id="' + clave(x) + '" aria-label="' + esc(x.titulo) + '">' +
            '<div class="aul-card-head">' +
                '<span class="aul-card-tipo">' + ICON[x.tipo] + esc(tipoTxt(x)) + '</span>' +
                (tag ? '<span class="app-status-tag ' + tag[0] + '">' + tag[1] + '</span>' : '') +
            '</div>' +
            '<div><h3 class="aul-card-titulo">' + esc(x.titulo) + '</h3>' +
                '<p class="aul-card-mod">' + esc(m ? 'Módulo ' + m.numero + ' · ' + m.nombre : '') + '</p></div>' +
            cierreHtml(x, est) +
            '<div class="aul-card-pie">' +
                (n != null ? '<span class="aul-card-nota">Nota <b class="aul-tono-' + tono(n) + '">' + esc(nota(n)) + '</b></span>' : '') +
                btn +
            '</div>' +
        '</article>';
    }

    function render() {
        var lista = todos().filter(function (x) { return modulo === 'todos' || String(x.moduloId) === modulo; });
        var pend = lista.filter(function (x) { return estadoDe(x) === 'por_hacer'; }).sort(function (a, b) { return a.cierra < b.cierra ? -1 : 1; });
        var res = lista.filter(function (x) { return estadoDe(x) !== 'por_hacer'; }).sort(function (a, b) { return a.cierra < b.cierra ? 1 : -1; });
        $('aul-num-pendientes').textContent = pend.length || '';
        $('aul-num-resultados').textContent = res.length || '';
        var ver = vista === 'pendientes' ? pend : res;

        // Resumen en una línea (solo en Resultados: el número de pendientes ya está en la pestaña)
        if (vista === 'resultados' && res.length) {
            var conNota = res.filter(function (x) { return notaDe(x) != null; });
            var prom = conNota.length ? Math.round(conNota.reduce(function (s, x) { return s + notaDe(x); }, 0) / conNota.length * 10) / 10 : null;
            var espera = res.filter(function (x) { return estadoDe(x) === 'entregada'; }).length;
            var faltan = res.filter(function (x) { return estadoDe(x) === 'no_respondida' || estadoDe(x) === 'no_entregada'; }).length;
            var partes = [];
            if (prom != null) partes.push('Promedio de lo calificado <b class="aul-tono-' + tono(prom) + '">' + esc(nota(prom)) + '</b>');
            if (espera) partes.push(esc(plural(espera, 'entrega espera', 'entregas esperan')) + ' calificación');
            if (faltan) partes.push(esc(plural(faltan, 'quedó sin hacer', 'quedaron sin hacer')));
            $('aul-resumen').innerHTML = partes.join(' · ');
        } else {
            $('aul-resumen').innerHTML = '';
        }

        $('aul-grid').innerHTML = ver.map(tarjeta).join('');
        $('aul-grid').hidden = !ver.length;
        $('aul-empty').hidden = !!ver.length;
        if (!ver.length) {
            $('aul-empty-ttl').textContent = !D.matricula ? 'No tienes una matrícula vigente' : (vista === 'pendientes' ? 'No tienes nada por hacer' : 'Todavía no tienes resultados');
            $('aul-empty-sub').textContent = !D.matricula ? 'Cuando la secretaría te matricule en un programa, aquí verás las evaluaciones y actividades de tu grupo.' : vista === 'pendientes'
                ? 'Cuando tu docente publique una evaluación o una actividad, aparecerá aquí con su fecha de cierre.'
                : 'Aquí verás tus notas y los comentarios de tu docente cuando respondas o entregues algo.';
        }
    }

    $('aul-grid').addEventListener('click', function (e) {
        var b = e.target.closest('[data-abrir]'), card = e.target.closest('.aul-card');
        if (b) { abrir(b.dataset.abrir, b); return; }
        if (card) abrir(card.dataset.id, card);
    });
    $('aul-grid').addEventListener('keydown', function (e) {
        var card = e.target.closest('.aul-card');
        if (card && e.target === card && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); abrir(card.dataset.id, card); }
    });

    /* ── Cajón ────────────────────────────────── */
    var drawer = $('aul-drawer'), panel = drawer.querySelector('.aul-drawer-panel');
    var actual = null, ultimoFoco = null, borrador = {}, archivoElegido = null, enviando = false;

    function abrir(id, origen) {
        var x = buscar(id);
        if (!x) return;
        if (!drawer.classList.contains('is-open')) ultimoFoco = origen || document.activeElement;
        actual = x;
        archivoElegido = null;
        pintarCajon();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        $('aul-dw-body').scrollTop = 0;
        ponerUrl(clave(x));
        setTimeout(function () { $('aul-dw-cerrar').focus({ preventScroll: true }); }, 40);
    }
    function cerrar() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        var id = actual && clave(actual);
        actual = null;
        ponerUrl(null);
        var volver = ultimoFoco && document.body.contains(ultimoFoco) ? ultimoFoco : $('aul-grid').querySelector('.aul-card[data-id="' + id + '"]');
        if (volver && volver.focus) volver.focus();
        ultimoFoco = null;
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrar); });
    document.addEventListener('keydown', function (e) {
        if (!$('aul-dialogo').hidden) {
            if (e.key === 'Escape') { e.preventDefault(); cerrarDialogo(false); }
            if (e.key === 'Tab') atrapar(e, $('aul-dialogo').querySelector('.aul-dialogo-caja'));
            return;
        }
        if (!drawer.classList.contains('is-open')) return;
        if (e.key === 'Escape') { e.preventDefault(); cerrar(); return; }
        if (e.key === 'Tab') atrapar(e, panel);
    });
    function atrapar(e, cont) {
        var focos = Array.prototype.slice.call(cont.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), [tabindex]:not([tabindex="-1"])'))
            .filter(function (el) { return el.offsetParent !== null || el.type === 'file'; });
        if (!focos.length) return;
        var primero = focos[0], ultimo = focos[focos.length - 1];
        if (e.shiftKey && (document.activeElement === primero || !cont.contains(document.activeElement))) { e.preventDefault(); ultimo.focus(); }
        else if (!e.shiftKey && (document.activeElement === ultimo || !cont.contains(document.activeElement))) { e.preventDefault(); primero.focus(); }
    }
    function ponerUrl(id) {
        try {
            var u = new URL(window.location.href);
            if (id) u.searchParams.set('id', id); else u.searchParams.delete('id');
            window.history.replaceState(null, '', u.pathname + u.search);
        } catch (err) { /* sin History API */ }
    }

    function aviso(tipo, icono, html) { return '<div class="aul-aviso aul-aviso-' + tipo + '">' + ICON[icono] + '<p>' + html + '</p></div>'; }
    function info(pares) {
        return '<div class="aul-info">' + pares.map(function (p) {
            return '<div><span class="aul-info-lbl">' + esc(p[0]) + '</span><span class="aul-info-val">' + esc(p[1]) + '</span></div>';
        }).join('') + '</div>';
    }

    function pintarCajon() {
        var x = actual, est = estadoDe(x), m = moduloDe(x.moduloId);
        $('aul-dw-avatar').innerHTML = ICON[x.tipo];
        $('aul-dw-titulo').textContent = x.titulo;
        $('aul-dw-sub').textContent = (x.tipo === 'evaluacion' ? 'Evaluación' : 'Actividad') + (m ? ' · Módulo ' + m.numero + ': ' + m.nombre : '');
        if (x.tipo === 'evaluacion') cajonEvaluacion(x, est); else cajonActividad(x, est);
    }

    /* Evaluación */
    function cajonEvaluacion(x, est) {
        var body = '', foot = $('aul-dw-foot');
        var cierreTxt = fechaCorta(x.cierra) + ' · ' + hora(x.cierra);
        if (est === 'por_hacer') {
            var b = borrador[x.id] || (borrador[x.id] = {});
            body = info([['Cierra', cierreTxt], ['Preguntas', String(x.preguntas.length)], ['Intentos', 'Una sola vez']]) +
                '<p class="aul-texto">' + esc(x.descripcion) + '</p>' +
                aviso('neutro', 'info', 'Revisa tus respuestas antes de enviar: <b>solo se puede responder una vez</b>. Tu nota sale al enviar y las respuestas correctas se ven después del cierre.') +
                '<ol class="aul-preguntas" id="aul-preguntas">' + x.preguntas.map(function (p) {
                    return '<li class="aul-preg" data-preg="' + p.id + '"><fieldset>' +
                        '<legend><span class="aul-preg-num">' + p.id + '</span><span>' + esc(p.enunciado) + '</span></legend>' +
                        '<div class="aul-ops">' + p.opciones.map(function (o) {
                            return '<label class="aul-op"><input type="radio" name="aul-p' + p.id + '" value="' + o.id + '"' + (b[p.id] === o.id ? ' checked' : '') + '><span>' + esc(o.texto) + '</span></label>';
                        }).join('') + '</div>' +
                    '</fieldset></li>';
                }).join('') + '</ol>';
            $('aul-dw-body').innerHTML = body;
            foot.hidden = false;
            foot.innerHTML = '<span class="aul-foot-txt" id="aul-progreso" aria-live="polite"></span>' +
                '<button type="button" class="aul-btn aul-btn-primary" id="aul-enviar">' + ICON.enviar + 'Enviar respuestas</button>';
            progreso();
            $('aul-preguntas').addEventListener('change', function (e) {
                if (!e.target.name || e.target.name.indexOf('aul-p') !== 0) return;
                b[+e.target.name.slice(5)] = +e.target.value;
                e.target.closest('.aul-preg').classList.remove('is-falta');
                progreso();
            });
            $('aul-enviar').addEventListener('click', enviarEvaluacion);
            return;
        }

        if (est === 'calificada') {
            var r = x.respuesta;
            body = '<div class="aul-resultado"><span class="aul-resultado-nota aul-tono-' + tono(r.nota) + '">' + esc(nota(r.nota)) + '</span>' +
                '<span class="aul-resultado-txt"><b>Acertaste ' + r.aciertos + ' de ' + x.preguntas.length + '</b>Respondida el ' + esc(fechaCorta(r.fecha)) + ' a las ' + esc(hora(r.fecha)) + '</span></div>';
            body += x.cerrada ? '' : aviso('neutro', 'info', 'Las respuestas correctas se muestran después del cierre, el <b>' + esc(fechaCorta(x.cierra)) + ' a las ' + esc(hora(x.cierra)) + '</b>. Mientras tanto ves lo que marcaste.');
        } else {
            body = aviso('riesgo', 'alerta', 'No la respondiste. Cerró el <b>' + esc(fechaCorta(x.cierra)) + ' a las ' + esc(hora(x.cierra)) + '</b>. Abajo están las respuestas correctas para que estudies.');
        }
        var marcadas = x.respuesta ? x.respuesta.marcadas : {};
        body += '<ol class="aul-preguntas">' + x.preguntas.map(function (p) {
            var mia = marcadas[p.id], ok = p.correcta != null ? mia === p.correcta : null;
            var cls = p.correcta != null && mia != null ? (ok ? ' is-ok' : ' is-mal') : '';
            return '<li class="aul-preg' + cls + '"><div class="aul-preg-cuerpo">' +
                '<p class="aul-preg-leyenda"><span class="aul-preg-num" aria-hidden="true">' + p.id + '</span><span>' + esc(p.enunciado) + '</span></p>' +
                '<ul class="aul-ops">' + p.opciones.map(function (o) {
                    var c = 'aul-op is-solo', tag = '', marca = '';
                    if (p.correcta != null && o.id === p.correcta) { c += ' is-correcta'; marca = ICON.check; tag = o.id === mia ? 'Tu respuesta · correcta' : 'Correcta'; }
                    else if (o.id === mia && p.correcta != null) { c += ' is-incorrecta'; marca = ICON.x; tag = 'Tu respuesta'; }
                    else if (o.id === mia) { c += ' is-mia'; marca = ICON.check; tag = 'Tu respuesta'; }
                    return '<li class="' + c + '"><span class="aul-op-marca" aria-hidden="true">' + marca + '</span><span>' + esc(o.texto) + '</span>' +
                        (tag ? '<span class="aul-op-tag">' + tag + '</span>' : '') + '</li>';
                }).join('') + '</ul></div></li>';
        }).join('') + '</ol>';
        $('aul-dw-body').innerHTML = body;
        foot.hidden = true;
        foot.innerHTML = '';
    }
    function progreso() {
        var x = actual, b = borrador[x.id] || {}, n = Object.keys(b).length;
        var el = $('aul-progreso');
        if (el) el.innerHTML = '<b>' + n + '</b> de ' + x.preguntas.length + ' respondidas';
    }
    function enviarEvaluacion() {
        var x = actual, b = borrador[x.id] || {};
        var falta = x.preguntas.filter(function (p) { return !b[p.id]; })[0];
        if (falta) {
            var li = $('aul-dw-body').querySelector('[data-preg="' + falta.id + '"]');
            li.classList.add('is-falta');
            li.scrollIntoView({ block: 'center', behavior: 'smooth' });
            var r = li.querySelector('input');
            if (r) r.focus({ preventScroll: true });
            toast('Te falta responder la pregunta ' + falta.id + '.', true);
            return;
        }
        confirmar('¿Enviar tus respuestas?', 'Respondiste las ' + x.preguntas.length + ' preguntas. Después de enviar no se pueden cambiar: la evaluación se responde una sola vez.', 'Sí, enviar', function () {
            if (enviando) return;
            enviando = true;
            $('aul-enviar').disabled = true;
            API.responderEvaluacion(x.id, b).then(function (res) {
                enviando = false;
                toast(res.mensaje, !res.ok);
                if (!res.ok) { $('aul-enviar').disabled = false; return; }
                delete borrador[x.id];
                actual = buscar(clave(x));
                pintarCajon();
                render();
                $('aul-dw-body').scrollTop = 0;
                $('aul-dw-cerrar').focus();
            });
        });
    }

    /* Actividad */
    function cajonActividad(x, est) {
        var foot = $('aul-dw-foot'), body;
        var abierta = !x.cerrada && est !== 'calificada';
        body = info([['Fecha límite', fechaCorta(x.cierra) + ' · ' + hora(x.cierra)], ['Formatos', 'PDF, Word, JPG o PNG'], ['Tamaño', 'Hasta 10 MB']]) +
            '<div><p class="aul-seccion-titulo">Instrucciones</p><p class="aul-texto">' + esc(x.instrucciones) + '</p></div>';

        if (est === 'calificada') {
            var e = x.entrega;
            body += '<div class="aul-resultado"><span class="aul-resultado-nota aul-tono-' + tono(e.nota) + '">' + esc(nota(e.nota)) + '</span>' +
                '<span class="aul-resultado-txt"><b>Calificada</b>' + esc((e.fechaNota ? 'El ' + fechaCorta(e.fechaNota) : 'Calificada') + (D.matricula && D.matricula.grupo && D.matricula.grupo.docente ? ' por ' + D.matricula.grupo.docente : '')) + '</span></div>' +
                (e.comentario ? '<p class="aul-comentario"><small>Comentario de tu docente</small>' + esc(e.comentario) + '</p>' : '');
        } else if (est === 'no_entregada') {
            body += aviso('riesgo', 'alerta', 'No la entregaste. La fecha límite fue el <b>' + esc(fechaCorta(x.cierra) + ' a las ' + hora(x.cierra)) + '</b> y ya no recibe entregas.');
        } else if (est === 'entregada' && x.cerrada) {
            body += aviso('neutro', 'info', 'Tu docente todavía no la califica. Cuando lo haga verás aquí tu nota y su comentario.');
        }

        if (x.entrega) {
            body += '<div><p class="aul-seccion-titulo">Tu entrega</p>' + archivoHtml(x.entrega.archivo, 'Entregado el ' + fechaCorta(x.entrega.fecha) + ' a las ' + hora(x.entrega.fecha)) + '</div>';
        }
        if (abierta) {
            body += '<div class="aul-subir">' +
                '<p class="aul-seccion-titulo">' + (x.entrega ? 'Cambiar el archivo' : 'Sube tu archivo') + '</p>' +
                '<div class="aul-subir-fila">' +
                    '<input type="file" id="aul-archivo" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">' +
                    '<label for="aul-archivo" class="aul-btn aul-btn-ghost aul-btn-sm" id="aul-elegir" tabindex="0" role="button">' + ICON.subir + 'Elegir archivo</label>' +
                    '<span class="aul-subir-elegido" id="aul-elegido"></span>' +
                '</div>' +
                '<p class="aul-subir-ayuda">' + (x.entrega ? 'El archivo nuevo reemplaza al anterior.' : 'Puedes cambiarlo hasta la fecha límite.') + ' PDF, Word, JPG o PNG, hasta 10 MB.</p>' +
                '<p class="aul-error" id="aul-error" role="alert"></p>' +
            '</div>';
        }
        $('aul-dw-body').innerHTML = body;

        if (!abierta) { foot.hidden = true; foot.innerHTML = ''; return; }
        foot.hidden = false;
        foot.innerHTML = '<span class="aul-foot-txt">' + esc((x.entrega ? 'Ya entregaste. ' : '') + 'Se recibe hasta el ' + fechaCorta(x.cierra) + ' a las ' + hora(x.cierra)) + '</span>' +
            '<button type="button" class="aul-btn aul-btn-primary" id="aul-entregar" disabled>' + ICON.subir + (x.entrega ? 'Cambiar archivo' : 'Entregar') + '</button>';
        var input = $('aul-archivo');
        $('aul-elegir').addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            archivoElegido = null;
            $('aul-error').textContent = '';
            $('aul-elegido').textContent = '';
            $('aul-entregar').disabled = true;
            if (!f) return;
            if (EXT.indexOf(extension(f.name)) === -1) { $('aul-error').textContent = 'El archivo debe ser PDF, Word (DOC o DOCX), JPG o PNG.'; return; }
            if (f.size > D.limites.archivo) { $('aul-error').textContent = 'El archivo pesa ' + tamano(f.size) + ': el máximo es 10 MB. Redúcelo o guárdalo en PDF.'; return; }
            archivoElegido = f;
            $('aul-elegido').textContent = f.name + ' · ' + tamano(f.size);
            $('aul-entregar').disabled = false;
        });
        $('aul-entregar').addEventListener('click', function () {
            if (!archivoElegido || enviando) return;
            enviando = true;
            $('aul-entregar').disabled = true;
            API.entregarActividad(x.id, archivoElegido).then(function (res) {
                enviando = false;
                toast(res.mensaje, !res.ok);
                if (!res.ok) { $('aul-entregar').disabled = false; return; }
                archivoElegido = null;
                actual = buscar(clave(x));
                pintarCajon();
                render();
                $('aul-dw-cerrar').focus();
            });
        });
    }
    function archivoHtml(a, sub) {
        return '<div class="aul-archivo"><span class="aul-archivo-icono" aria-hidden="true">' + esc(a.tipo || extension(a.nombre)) + '</span>' +
            '<span class="aul-archivo-info">' + (a.url
                ? '<a class="aul-archivo-nombre" href="' + esc(a.url) + '" target="_blank" rel="noopener">' + esc(a.nombre) + '</a>'
                : '<span class="aul-archivo-nombre">' + esc(a.nombre) + '</span>') +
            '<span class="aul-archivo-sub">' + esc(tamano(a.tamano) + ' · ' + sub) + '</span></span></div>';
    }

    /* ── Confirmación y avisos ────────────────── */
    var alConfirmar = null, focoDialogo = null;
    function confirmar(titulo, texto, boton, fn) {
        alConfirmar = fn;
        focoDialogo = document.activeElement;
        $('aul-dlg-titulo').textContent = titulo;
        $('aul-dlg-txt').textContent = texto;
        $('aul-dlg-si').textContent = boton;
        $('aul-dialogo').hidden = false;
        $('aul-dlg-si').focus();
    }
    function cerrarDialogo(si) {
        $('aul-dialogo').hidden = true;
        var fn = alConfirmar;
        alConfirmar = null;
        if (focoDialogo && focoDialogo.focus) focoDialogo.focus();
        if (si && fn) fn();
    }
    $('aul-dlg-si').addEventListener('click', function () { cerrarDialogo(true); });
    $('aul-dialogo').querySelectorAll('[data-no]').forEach(function (b) { b.addEventListener('click', function () { cerrarDialogo(false); }); });

    var toastT = null;
    function toast(msg, error) {
        if (!msg) return;
        var t = $('aul-toast');
        t.textContent = msg;
        t.classList.toggle('is-error', !!error);
        t.setAttribute('role', error ? 'alert' : 'status');
        t.hidden = false;
        requestAnimationFrame(function () { t.classList.add('is-visible'); });
        clearTimeout(toastT);
        toastT = setTimeout(function () { t.classList.remove('is-visible'); setTimeout(function () { t.hidden = true; }, 220); }, 4200);
    }

    /* ── Arranque ─────────────────────────────── */
    pintarChips();
    var idParam = (window.location.search.match(/[?&]id=((?:evaluacion|actividad)-\d+)/) || [])[1];
    var inicial = idParam && buscar(idParam);
    if (inicial && estadoDe(inicial) !== 'por_hacer') vista = 'resultados';
    mostrarVista(vista);
    if (inicial) abrir(clave(inicial));
})();
