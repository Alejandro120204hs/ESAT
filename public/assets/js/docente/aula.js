/* ESAT — Docente: Aula virtual
   Lee window.DAU_SERVIDOR (AulaService::paraDocente: sus grupos con malla, estudiantes activos, evaluaciones con sus
   respuestas y actividades con sus entregas) y guarda con fetch (CSRF). Cada respuesta trae los grupos recalculados.
   Reglas (las mismas del servidor): una evaluación se edita o elimina mientras nadie la responda, con 1 a 30 preguntas
   de 2 a 6 opciones y una sola correcta (27-11-2026: con respuestas se corrige —textos, cierre y la correcta— sin agregar ni
   quitar preguntas u opciones, y puede tener tiempo límite); una actividad se elimina sin entregas; una entrega se califica de 1,0 a 5,0
   con un decimal. Un grupo finalizado es solo consulta. ?grupo=ID elige el grupo.
   27-11-2026: una actividad puede llevar un archivo de apoyo (la guía) y cada grupo tiene material de clase (archivo,
   enlace o los dos, de un módulo o de todo el grupo). Lo que lleva archivo se envía como FormData. */
(function () {
    'use strict';

    var S = window.DAU_SERVIDOR || {};
    var RUTAS = S.rutas || {};
    var D = S.datos || { hoy: '', grupos: [] };
    var app = document.getElementById('dau-app');
    if (!app) return;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    var MESES_C = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var DIAS_C = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    var NB = ' ';
    var ICON = {
        evaluacion: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        actividad:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        reloj:      '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        x:          '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        mas:        '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        check:      '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
        material:   '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        enlace:     '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'
    };
    var ACEPTA = '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png';
    var DURACIONES = [10, 15, 20, 30, 45, 60, 90, 120];   // Evaluacion::DURACIONES (minutos)
    var MAX_MB = 10;

    /* ── Utilidades ───────────────────────────── */
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function $(id) { return document.getElementById(id); }
    function fecha(iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function fechaCorta(iso) { var f = fecha(iso); return DIAS_C[f.getDay()] + NB + f.getDate() + NB + MESES_C[f.getMonth()]; }
    function hora(iso) { return String(iso).slice(11, 16); }
    function plural(n, uno, varios) { return n + NB + (n === 1 ? uno : varios); }
    function nota(v) { return v == null ? '—' : Number(v).toFixed(1).replace('.', ','); }
    function tono(n) { return n == null ? '' : (n >= 4 ? 'ok' : (n >= 3 ? 'warn' : 'bad')); }
    function tamano(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + NB + 'MB' : Math.max(1, Math.round(b / 1024)) + NB + 'KB'; }
    function ruta(nombre, id) { return String(RUTAS[nombre] || '').replace('__ID__', encodeURIComponent(id)); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function programaCorto(p) { return String(p || '').replace(/^Técnico Laboral (por competencias )?en /i, ''); }
    function cuantas(obj) { return Object.keys(obj || {}).length; }

    /* ── Servidor ─────────────────────────────── */
    function enviar(metodo, url, cuerpo) {
        // Con archivo va como FormData: el PUT viaja como POST con _method (PHP no lee archivos de un PUT)
        var form = cuerpo instanceof FormData;
        if (form && metodo !== 'POST') { cuerpo.append('_method', metodo); metodo = 'POST'; }
        var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
        if (!form) headers['Content-Type'] = 'application/json';
        return fetch(url, {
            method: metodo,
            headers: headers,
            body: cuerpo ? (form ? cuerpo : JSON.stringify(cuerpo)) : undefined,
            credentials: 'same-origin'
        }).then(function (r) {
            if (r.status === 401 || r.status === 419) { window.location.reload(); return { ok: false, mensaje: 'Tu sesión terminó. Vuelve a ingresar.' }; }
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (r.ok && j.ok) {
                    if (j.datos) D = j.datos;
                    return j;
                }
                var errores = j.errors || {};
                var k = Object.keys(errores)[0];
                return { ok: false, campo: k || null, mensaje: k ? errores[k][0] : (j.message && r.status === 403 ? j.message : 'No se pudo guardar. Revisa tu conexión e intenta de nuevo.') };
            });
        }).catch(function () { return { ok: false, mensaje: 'No se pudo guardar. Revisa tu conexión e intenta de nuevo.' }; });
    }

    /* ── Grupo elegido ────────────────────────── */
    var grupoId = +((window.location.search.match(/[?&]grupo=(\d+)/) || [])[1] || 0);
    function grupo() { return D.grupos.filter(function (g) { return g.id === grupoId; })[0] || null; }
    if (!grupo()) {
        var primero = D.grupos.filter(function (g) { return !g.soloConsulta; })[0] || D.grupos[0];
        grupoId = primero ? primero.id : 0;
    }
    function moduloDe(g, id) { return (g.modulos || []).filter(function (m) { return m.id === id; })[0] || null; }

    function pintarGrupos() {
        var html = '<legend class="sr-solo">Grupo</legend>' + D.grupos.map(function (g) {
            return '<label class="dau-chip"><input type="radio" name="dau-grupo" value="' + g.id + '"' + (g.id === grupoId ? ' checked' : '') + '>' +
                '<span>' + esc(programaCorto(g.programa) + ' · ' + g.nombre) + (g.soloConsulta ? ' (finalizado)' : '') + '</span></label>';
        }).join('');
        $('dau-grupos').innerHTML = html;
    }
    $('dau-grupos').addEventListener('change', function (e) {
        if (e.target.name !== 'dau-grupo') return;
        grupoId = +e.target.value;
        try {
            var u = new URL(window.location.href);
            u.searchParams.set('grupo', grupoId);
            window.history.replaceState(null, '', u.pathname + u.search);
        } catch (err) { /* sin History API */ }
        render();
    });

    /* ── Tarjetas ─────────────────────────────── */
    function porCalificar(a) { return Object.keys(a.entregas || {}).filter(function (k) { return a.entregas[k].nota == null; }).length; }
    function promedio(lista) {
        var n = lista.filter(function (x) { return x != null; });
        return n.length ? Math.round(n.reduce(function (s, x) { return s + x; }, 0) / n.length * 10) / 10 : null;
    }

    function tarjeta(g, x, tipo) {
        var m = moduloDe(g, x.moduloId), total = g.estudiantes.length;
        var cerrada = x.cerrada, cifras, botones = '', tag;
        var editable = !g.soloConsulta;
        if (tipo === 'evaluacion') {
            var resp = x.respuestas || {}, nResp = cuantas(resp);
            var prom = promedio(Object.keys(resp).map(function (k) { return resp[k].nota; }));
            cifras = '<span><b>' + nResp + '</b> de ' + total + ' respondieron</span>' + (prom != null ? '<span>Promedio <b class="dau-tono-' + tono(prom) + '">' + esc(nota(prom)) + '</b></span>' : '') +
                '<span>' + esc(plural(x.preguntas.length, 'pregunta', 'preguntas')) + '</span>' + (x.duracion ? '<span>' + esc(x.duracion + ' min') + '</span>' : '');
            // Se corrige aunque ya la hayan respondido (sin agregar ni quitar preguntas); se elimina solo si nadie la ha comenzado
            botones = '<button type="button" class="dau-btn dau-btn-ghost dau-btn-sm" data-ver="' + x.id + '" data-tipo="evaluacion">Ver resultados</button>' +
                (editable ? '<button type="button" class="dau-link" data-editar="' + x.id + '" data-tipo="evaluacion">' + (x.conIntentos ? 'Corregir' : 'Editar') + '</button>' : '') +
                (editable && !x.conIntentos ? '<button type="button" class="dau-link is-peligro" data-eliminar="' + x.id + '" data-tipo="evaluacion">Eliminar</button>' : '');
        } else {
            var nEnt = cuantas(x.entregas), pend = porCalificar(x);
            cifras = '<span><b>' + nEnt + '</b> de ' + total + ' entregaron</span>' + (pend ? '<span><b>' + pend + '</b> sin calificar</span>' : '');
            botones = (pend && editable
                    ? '<button type="button" class="dau-btn dau-btn-primary dau-btn-sm" data-ver="' + x.id + '" data-tipo="actividad">Calificar <span class="dau-btn-num">' + pend + '</span></button>'
                    : '<button type="button" class="dau-btn dau-btn-ghost dau-btn-sm" data-ver="' + x.id + '" data-tipo="actividad">Ver entregas</button>') +
                (editable ? '<button type="button" class="dau-link" data-editar="' + x.id + '" data-tipo="actividad">Editar</button>' : '') +
                (editable && !nEnt ? '<button type="button" class="dau-link is-peligro" data-eliminar="' + x.id + '" data-tipo="actividad">Eliminar</button>' : '');
        }
        tag = cerrada ? '<span class="app-status-tag app-status-muted">Cerrada</span>' : '<span class="app-status-tag app-status-active">Abierta</span>';
        return '<article class="dau-card dau-card-fija">' +
            '<div class="dau-card-head"><span class="dau-card-tipo">' + ICON[tipo] + (tipo === 'evaluacion' ? 'Evaluación' : 'Actividad') + '</span>' + tag + '</div>' +
            '<div><h3 class="dau-card-titulo">' + esc(x.titulo) + '</h3><p class="dau-card-mod">' + esc(m ? m.nombre : 'Sin módulo') + '</p></div>' +
            '<p class="dau-cierre">' + ICON.reloj + '<span>' + esc((cerrada ? 'Cerró el ' : (tipo === 'evaluacion' ? 'Cierra el ' : 'Se entrega hasta el ')) + fechaCorta(x.cierra) + ' a las ' + hora(x.cierra)) + '</span></p>' +
            '<p class="dau-card-cifras">' + cifras + '</p>' +
            '<div class="dau-card-pie">' + botones + '</div>' +
        '</article>';
    }

    function render() {
        var g = grupo();
        pintarGrupos();
        $('dau-acciones').hidden = !g || g.soloConsulta;
        $('dau-solo').hidden = !g || !g.soloConsulta;
        if (!g) {
            $('dau-grid').innerHTML = '';
            $('dau-resumen').textContent = '';
            $('dau-vacio').hidden = false;
            $('dau-vacio-ttl').textContent = 'No tienes grupos con horario';
            $('dau-vacio-sub').textContent = 'Cuando la sede te asigne un grupo, aquí podrás publicarle evaluaciones y actividades.';
            return;
        }
        var items = g.evaluaciones.map(function (x) { return { x: x, t: 'evaluacion' }; })
            .concat(g.actividades.map(function (x) { return { x: x, t: 'actividad' }; }))
            // Las abiertas primero (la que cierra antes arriba); después las cerradas, de la más reciente a la más vieja
            .sort(function (a, b) {
                if (a.x.cerrada !== b.x.cerrada) return a.x.cerrada ? 1 : -1;
                return a.x.cerrada ? (a.x.cierra < b.x.cierra ? 1 : -1) : (a.x.cierra < b.x.cierra ? -1 : 1);
            });
        var pend = g.actividades.reduce(function (s, a) { return s + porCalificar(a); }, 0);
        $('dau-resumen').innerHTML = esc(plural(g.evaluaciones.length, 'evaluación', 'evaluaciones')) + ' · ' + esc(plural(g.actividades.length, 'actividad', 'actividades')) +
            ' · ' + esc(plural(g.estudiantes.length, 'estudiante activo', 'estudiantes activos')) + (pend ? ' · <b>' + esc(plural(pend, 'entrega', 'entregas')) + ' sin calificar</b>' : '');
        $('dau-grid').innerHTML = items.map(function (i) { return tarjeta(g, i.x, i.t); }).join('');
        $('dau-vacio').hidden = items.length > 0;
        if (!items.length) {
            $('dau-vacio-ttl').textContent = g.soloConsulta ? 'Este grupo no tuvo evaluaciones ni actividades' : 'Todavía no hay nada en el Aula de este grupo';
            $('dau-vacio-sub').textContent = g.soloConsulta ? '' : 'Publica una evaluación de selección múltiple o una actividad para que tus estudiantes la vean en su panel.';
        }
        pintarMaterial(g);
    }

    /* ── Material de clase ────────────────────── */
    function archivoHtml(a, txt) {
        return '<a class="dau-archivo" href="' + esc(a.url) + '" target="_blank" rel="noopener">' +
            '<span class="dau-archivo-icono" aria-hidden="true">' + esc(a.tipo || 'arch') + '</span>' +
            '<span class="dau-archivo-info"><span class="dau-archivo-nombre">' + esc(a.nombre) + '</span>' +
            '<span class="dau-archivo-sub">' + esc((txt ? txt + ' · ' : '') + tamano(a.tamano)) + '</span></span></a>';
    }
    function enlaceHtml(url) {
        var corto = String(url).replace(/^https?:\/\//, '').replace(/\/$/, '');
        return '<a class="dau-archivo" href="' + esc(url) + '" target="_blank" rel="noopener noreferrer">' +
            '<span class="dau-archivo-icono" aria-hidden="true">' + ICON.enlace + '</span>' +
            '<span class="dau-archivo-info"><span class="dau-archivo-nombre">' + esc(corto.length > 60 ? corto.slice(0, 57) + '…' : corto) + '</span>' +
            '<span class="dau-archivo-sub">Enlace · se abre en otra pestaña</span></span></a>';
    }
    function pintarMaterial(g) {
        var lista = (g && g.materiales) || [];
        $('dau-material').hidden = !g;
        $('dau-mat-resumen').textContent = lista.length ? plural(lista.length, 'publicación', 'publicaciones') : '';
        $('dau-mat-lista').innerHTML = lista.map(function (m) {
            var mod = moduloDe(g, m.moduloId);
            return '<article class="dau-card dau-card-fija dau-mat">' +
                '<div class="dau-card-head"><span class="dau-card-tipo">' + ICON.material + 'Material</span>' +
                    '<span class="dau-mat-fecha">' + esc('Publicado el ' + fechaCorta(m.fecha)) + '</span></div>' +
                '<div><h3 class="dau-card-titulo">' + esc(m.titulo) + '</h3><p class="dau-card-mod">' + esc(mod ? mod.nombre : 'Todo el grupo') + '</p></div>' +
                (m.descripcion ? '<p class="dau-texto">' + esc(m.descripcion) + '</p>' : '') +
                '<div class="dau-mat-archivos">' + (m.archivo ? archivoHtml(m.archivo) : '') + (m.enlace ? enlaceHtml(m.enlace) : '') + '</div>' +
                (g.soloConsulta ? '' : '<div class="dau-card-pie"><button type="button" class="dau-link" data-editar-mat="' + m.id + '">Editar</button>' +
                    '<button type="button" class="dau-link is-peligro" data-eliminar-mat="' + m.id + '">Eliminar</button></div>') +
            '</article>';
        }).join('');
        $('dau-mat-vacio').hidden = lista.length > 0;
        $('dau-mat-vacio').textContent = g && g.soloConsulta ? 'Este grupo no tuvo material de clase.'
            : 'Sube guías, presentaciones, lecturas o enlaces a videos para que tus estudiantes los consulten cuando quieran.';
    }
    $('dau-mat-lista').addEventListener('click', function (e) {
        var b = e.target.closest('[data-editar-mat],[data-eliminar-mat]');
        if (!b) return;
        var id = +(b.dataset.editarMat || b.dataset.eliminarMat);
        var m = (grupo().materiales || []).filter(function (x) { return x.id === id; })[0];
        if (!m) return;
        if (b.dataset.editarMat) formMaterial(m, b); else confirmarEliminar(m, 'material');
    });

    $('dau-grid').addEventListener('click', function (e) {
        var b = e.target.closest('[data-ver],[data-editar],[data-eliminar]');
        if (!b) return;
        var g = grupo(), tipo = b.dataset.tipo;
        var lista = tipo === 'evaluacion' ? g.evaluaciones : g.actividades;
        var id = +(b.dataset.ver || b.dataset.editar || b.dataset.eliminar);
        var x = lista.filter(function (y) { return y.id === id; })[0];
        if (!x) return;
        if (b.dataset.ver) { tipo === 'evaluacion' ? verResultados(x, b) : verEntregas(x, b); }
        else if (b.dataset.editar) { tipo === 'evaluacion' ? formEvaluacion(x, b) : formActividad(x, b); }
        else confirmarEliminar(x, tipo);
    });
    $('dau-nueva-ev').addEventListener('click', function () { formEvaluacion(null, this); });
    $('dau-nueva-act').addEventListener('click', function () { formActividad(null, this); });
    $('dau-nuevo-mat').addEventListener('click', function () { formMaterial(null, this); });

    /* ── Cajón ────────────────────────────────── */
    var drawer = $('dau-drawer'), panel = drawer.querySelector('.dau-drawer-panel'), ultimoFoco = null, abierto = null;
    function abrirCajon(titulo, sub, cuerpo, pie, origen) {
        if (!drawer.classList.contains('is-open')) ultimoFoco = origen || document.activeElement;
        $('dau-dw-titulo').textContent = titulo;
        $('dau-dw-sub').textContent = sub;
        $('dau-dw-body').innerHTML = cuerpo;
        $('dau-dw-foot').innerHTML = pie || '';
        $('dau-dw-foot').hidden = !pie;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        $('dau-dw-body').scrollTop = 0;
        setTimeout(function () { $('dau-dw-cerrar').focus({ preventScroll: true }); }, 40);
    }
    function cerrarCajon() {
        if (!drawer.classList.contains('is-open')) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        abierto = null;
        if (ultimoFoco && document.body.contains(ultimoFoco)) ultimoFoco.focus();
        ultimoFoco = null;
    }
    drawer.querySelectorAll('[data-cerrar]').forEach(function (el) { el.addEventListener('click', cerrarCajon); });
    document.addEventListener('keydown', function (e) {
        if (!$('dau-dialogo').hidden) {
            if (e.key === 'Escape') { e.preventDefault(); cerrarDialogo(); }
            if (e.key === 'Tab') atrapar(e, $('dau-dialogo').querySelector('.dau-dialogo-caja'));
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

    function info(pares) {
        return '<div class="dau-info">' + pares.map(function (p) { return '<div><span class="dau-info-lbl">' + esc(p[0]) + '</span><span class="dau-info-val">' + esc(p[1]) + '</span></div>'; }).join('') + '</div>';
    }

    /* Resultados de una evaluación */
    function verResultados(ev, origen) {
        var g = grupo(), resp = ev.respuestas || {};
        abierto = { tipo: 'resultados', id: ev.id };
        var comenzadas = ev.comenzadas || {};
        var cuerpo = info([['Cierra', fechaCorta(ev.cierra) + ' · ' + hora(ev.cierra)], ['Preguntas', String(ev.preguntas.length)],
                ['Tiempo', ev.duracion ? ev.duracion + ' minutos' : 'Sin límite'], ['Respondieron', cuantas(resp) + ' de ' + g.estudiantes.length]]) +
            (ev.descripcion ? '<p class="dau-texto">' + esc(ev.descripcion) + '</p>' : '') +
            '<div><p class="dau-seccion-titulo">Estudiantes</p><ul class="dau-est-lista">' + (g.estudiantes.length ? g.estudiantes.map(function (e) {
                var r = resp[e.id], c = comenzadas[e.id];
                return '<li class="dau-est"><div class="dau-est-top"><div><p class="dau-est-nombre">' + esc(e.nombre) + '</p>' +
                    '<p class="dau-est-sub">' + esc(r ? 'Respondió el ' + fechaCorta(r.fecha) + ' · acertó ' + r.aciertos + ' de ' + ev.preguntas.length
                        : (c ? 'La comenzó el ' + fechaCorta(c.inicio) + ' a las ' + hora(c.inicio) + (c.vencido ? ' y se le acabó el tiempo sin enviar' : ': la está respondiendo')
                        : (ev.cerrada ? 'No respondió' : 'Todavía no responde'))) + '</p></div>' +
                    '<div class="dau-est-der">' + (r ? '<span class="dau-est-nota dau-tono-' + tono(r.nota) + '">' + esc(nota(r.nota)) + '</span>'
                        : (c && !c.vencido ? '<span class="app-status-tag app-status-warn">Respondiendo</span>'
                        : '<span class="app-status-tag ' + (ev.cerrada || (c && c.vencido) ? 'app-status-danger' : 'app-status-muted') + '">' + (ev.cerrada || (c && c.vencido) ? 'Sin responder' : 'Pendiente') + '</span>')) + '</div></div></li>';
            }).join('') : '<li class="dau-est"><p class="dau-est-sub">El grupo no tiene estudiantes activos.</p></li>') + '</ul></div>' +
            '<div><p class="dau-seccion-titulo">Preguntas y respuesta correcta</p><ol class="dau-preguntas">' + ev.preguntas.map(function (p, i) {
                return '<li class="dau-preg"><div><p class="dau-preg-leyenda"><span class="dau-preg-num" aria-hidden="true">' + (i + 1) + '</span><span>' + esc(p.enunciado) + '</span></p>' +
                    '<ul class="dau-ops">' + p.opciones.map(function (o) {
                        return '<li class="dau-op is-solo' + (o.correcta ? ' is-correcta' : '') + '"><span class="dau-op-marca" aria-hidden="true">' + (o.correcta ? ICON.check : '') + '</span><span>' + esc(o.texto) + '</span>' +
                            (o.correcta ? '<span class="dau-op-tag">Correcta</span>' : '') + '</li>';
                    }).join('') + '</ul></div></li>';
            }).join('') + '</ol></div>';
        abrirCajon(ev.titulo, 'Evaluación · ' + (moduloDe(g, ev.moduloId) || {}).nombre, cuerpo, '', origen);
    }

    /* Entregas de una actividad (calificar) */
    function verEntregas(a, origen) {
        var g = grupo(), ent = a.entregas || {};
        abierto = { tipo: 'entregas', id: a.id };
        var cuerpo = info([['Fecha límite', fechaCorta(a.cierra) + ' · ' + hora(a.cierra)], ['Entregaron', cuantas(ent) + ' de ' + g.estudiantes.length], ['Sin calificar', String(porCalificar(a))]]) +
            '<div><p class="dau-seccion-titulo">Instrucciones</p><p class="dau-texto">' + esc(a.instrucciones) + '</p>' +
                (a.adjunto ? '<div class="dau-guia">' + archivoHtml(a.adjunto, 'Archivo de apoyo') + '</div>' : '') + '</div>' +
            '<div><p class="dau-seccion-titulo">Entregas</p><ul class="dau-est-lista">' + (g.estudiantes.length ? g.estudiantes.map(function (e) {
                var x = ent[e.id];
                if (!x) {
                    return '<li class="dau-est"><div class="dau-est-top"><div><p class="dau-est-nombre">' + esc(e.nombre) + '</p>' +
                        '<p class="dau-est-sub">' + (a.cerrada ? 'No entregó' : 'Todavía no entrega') + '</p></div>' +
                        '<span class="app-status-tag ' + (a.cerrada ? 'app-status-danger' : 'app-status-muted') + '">' + (a.cerrada ? 'Sin entrega' : 'Pendiente') + '</span></div></li>';
                }
                return '<li class="dau-est" data-entrega="' + x.id + '"><div class="dau-est-top"><div><p class="dau-est-nombre">' + esc(e.nombre) + '</p>' +
                    '<p class="dau-est-sub"><a class="dau-archivo-nombre" href="' + esc(x.archivo.url) + '" target="_blank" rel="noopener">' + esc(x.archivo.nombre) + '</a> · ' +
                        esc(tamano(x.archivo.tamano) + ' · entregó el ' + fechaCorta(x.fecha) + ' a las ' + hora(x.fecha)) + '</p></div>' +
                    (x.nota != null ? '<span class="dau-est-nota dau-tono-' + tono(x.nota) + '">' + esc(nota(x.nota)) + '</span>' : '<span class="app-status-tag app-status-warn">Sin calificar</span>') + '</div>' +
                    (g.soloConsulta ? (x.comentario ? '<p class="dau-comentario">' + esc(x.comentario) + '</p>' : '') :
                        '<div class="dau-calif">' +
                            '<label class="sr-solo" for="dau-n-' + x.id + '">Nota de ' + esc(e.nombre) + '</label>' +
                            '<input class="dau-input" id="dau-n-' + x.id + '" inputmode="decimal" maxlength="3" placeholder="Nota" value="' + (x.nota != null ? esc(nota(x.nota)) : '') + '">' +
                            '<label class="sr-solo" for="dau-c-' + x.id + '">Comentario para ' + esc(e.nombre) + '</label>' +
                            '<textarea class="dau-input" id="dau-c-' + x.id + '" maxlength="1000" rows="1" placeholder="Comentario para el estudiante (opcional)">' + esc(x.comentario || '') + '</textarea>' +
                            '<button type="button" class="dau-btn dau-btn-ghost dau-btn-sm" data-calificar="' + x.id + '">' + (x.nota != null ? 'Corregir' : 'Guardar nota') + '</button>' +
                            '<p class="dau-calif-err" role="alert"></p>' +
                        '</div>') +
                '</li>';
            }).join('') : '<li class="dau-est"><p class="dau-est-sub">El grupo no tiene estudiantes activos.</p></li>') + '</ul></div>';
        abrirCajon(a.titulo, 'Actividad · ' + (moduloDe(g, a.moduloId) || {}).nombre, cuerpo, '', origen);
    }
    $('dau-dw-body').addEventListener('click', function (e) {
        var b = e.target.closest('[data-calificar]');
        if (!b) return;
        var id = +b.dataset.calificar, li = b.closest('[data-entrega]'), err = li.querySelector('.dau-calif-err');
        var nTxt = $('dau-n-' + id).value.trim().replace(',', '.');
        err.textContent = '';
        if (!/^\d(\.\d)?$/.test(nTxt) || +nTxt < 1 || +nTxt > 5) { err.textContent = 'Escribe una nota de 1,0 a 5,0 con un decimal (por ejemplo 4,5).'; $('dau-n-' + id).focus(); return; }
        b.disabled = true;
        enviar('PATCH', ruta('calificar', id), { nota: nTxt, comentario: $('dau-c-' + id).value }).then(function (r) {
            b.disabled = false;
            if (!r.ok) { err.textContent = r.mensaje; return; }
            toast(r.mensaje);
            render();
            var a = (grupo().actividades || []).filter(function (x) { return abierto && x.id === abierto.id; })[0];
            if (a) { verEntregas(a); var sig = $('dau-dw-body').querySelector('[data-entrega] .dau-input'); if (sig) sig.focus(); }
        });
    });

    /* ── Formularios ──────────────────────────── */
    function aLocal(iso) { return iso ? iso.replace(' ', 'T') : ''; }
    function enUnaSemana() {
        var d = new Date(); d.setDate(d.getDate() + 7);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T23:59';
    }
    function opcionesModulo(g, sel) {
        var actual = (g.modulos || []).filter(function (m) { return m.estado === 'en_curso'; })[0];
        var elegido = sel || (actual ? actual.id : (g.modulos[0] || {}).id);
        return g.modulos.map(function (m, i) {
            return '<option value="' + m.id + '"' + (m.id === elegido ? ' selected' : '') + '>' + esc((i + 1) + '. ' + m.nombre + (m.estado === 'en_curso' ? ' (en curso)' : (m.estado === 'terminado' ? ' (terminado)' : ''))) + '</option>';
        }).join('');
    }

    function formActividad(a, origen) {
        var g = grupo();
        abierto = { tipo: 'form-actividad', id: a ? a.id : null };
        var cuerpo = '<div class="dau-campo"><label for="dau-f-titulo">Título</label><input class="dau-input" id="dau-f-titulo" maxlength="150" value="' + esc(a ? a.titulo : '') + '" placeholder="Ej.: Informe de la práctica en vía"></div>' +
            '<div class="dau-fila"><div class="dau-campo"><label for="dau-f-modulo">Módulo</label><select class="dau-input" id="dau-f-modulo">' + opcionesModulo(g, a && a.moduloId) + '</select></div>' +
                '<div class="dau-campo"><label for="dau-f-cierra">Se entrega hasta</label><input class="dau-input" id="dau-f-cierra" type="datetime-local" value="' + esc(a ? aLocal(a.cierra) : enUnaSemana()) + '"></div></div>' +
            '<div class="dau-campo"><label for="dau-f-instr">Instrucciones</label><textarea class="dau-input" id="dau-f-instr" maxlength="3000" rows="6" placeholder="Qué deben hacer y qué archivo entregar">' + esc(a ? a.instrucciones : '') + '</textarea>' +
                '<span class="dau-ayuda">Tus estudiantes suben un archivo PDF, Word, JPG o PNG de hasta 10 MB y pueden cambiarlo hasta la fecha límite o hasta que lo califiques.</span></div>' +
            campoArchivo('adjunto', 'Archivo de apoyo', '(opcional: la guía, el caso o la rúbrica)', a && a.adjunto) +
            '<p class="dau-error" id="dau-f-error" role="alert"></p>';
        var pie = '<button type="button" class="dau-btn dau-btn-ghost" data-cerrar-form>Cancelar</button><button type="button" class="dau-btn dau-btn-primary" id="dau-f-guardar">' + (a ? 'Guardar cambios' : 'Publicar actividad') + '</button>';
        abrirCajon(a ? 'Editar actividad' : 'Nueva actividad', programaCorto(g.programa) + ' · ' + g.nombre, cuerpo, pie, origen);
        $('dau-f-guardar').addEventListener('click', function () {
            var datos = { titulo: $('dau-f-titulo').value.trim(), modulo_id: +$('dau-f-modulo').value, cierra: $('dau-f-cierra').value, instrucciones: $('dau-f-instr').value.trim() };
            if (datos.titulo.length < 5) return errorForm('El título debe tener al menos 5 letras.', 'dau-f-titulo');
            if (!datos.cierra) return errorForm('Elige la fecha y la hora límite de entrega.', 'dau-f-cierra');
            if (datos.instrucciones.length < 10) return errorForm('Escribe las instrucciones: qué deben hacer y entregar.', 'dau-f-instr');
            var fd = conArchivo(datos, 'adjunto');
            if (!fd) return;
            guardar(a ? 'PUT' : 'POST', a ? ruta('actividad', a.id) : ruta('crearActividad', g.id), fd, this);
        });
    }

    /* Campo de archivo del docente (guía de una actividad o material): muestra el que tiene y deja quitarlo */
    function campoArchivo(nombre, etiqueta, ayuda, actual) {
        return '<div class="dau-campo"><label for="dau-f-' + nombre + '">' + esc(etiqueta) + (ayuda ? ' <span class="dau-ayuda">' + esc(ayuda) + '</span>' : '') + '</label>' +
            (actual ? '<div class="dau-actual" id="dau-f-' + nombre + '-actual">' + archivoHtml(actual, 'Archivo actual') +
                '<label class="dau-quitar-arch"><input type="checkbox" id="dau-f-quitar-' + nombre + '"> Quitar este archivo</label></div>' : '') +
            // Botón propio (el del navegador sale en el idioma del equipo): el input queda oculto y el nombre elegido al lado
            '<div class="dau-subir-fila"><input class="dau-file-oculto" id="dau-f-' + nombre + '" type="file" accept="' + ACEPTA + '">' +
                '<label for="dau-f-' + nombre + '" class="dau-btn dau-btn-ghost dau-btn-sm" tabindex="0" role="button" data-elegir>' + ICON.mas + (actual ? 'Elegir otro archivo' : 'Elegir archivo') + '</label>' +
                '<span class="dau-elegido" id="dau-f-' + nombre + '-elegido">Ningún archivo elegido</span></div>' +
            '<span class="dau-ayuda">PDF, Word, PowerPoint, Excel, JPG o PNG de hasta ' + MAX_MB + ' MB.' + (actual ? ' Si eliges otro, reemplaza al actual.' : '') + '</span></div>';
    }
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t.classList || !t.classList.contains('dau-file-oculto')) return;
        var f = t.files && t.files[0], el = $(t.id + '-elegido');
        if (el) el.textContent = f ? f.name + ' · ' + tamano(f.size) : 'Ningún archivo elegido';
    });
    document.addEventListener('keydown', function (e) {
        var b = e.target.closest && e.target.closest('[data-elegir]');
        if (b && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); $(b.getAttribute('for')).click(); }
    });

    /* Los datos como FormData con el archivo elegido (o la orden de quitarlo); null si el archivo no sirve */
    function conArchivo(datos, nombre) {
        var fd = new FormData();
        Object.keys(datos).forEach(function (k) { if (datos[k] != null) fd.append(k, datos[k]); });
        var input = $('dau-f-' + nombre), f = input && input.files[0];
        if (f) {
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            if (ACEPTA.split(',').indexOf('.' + ext) === -1) { errorForm('El archivo debe ser PDF, Word, PowerPoint, Excel, JPG o PNG.', 'dau-f-' + nombre); return null; }
            if (f.size > MAX_MB * 1048576) { errorForm('El archivo pesa ' + tamano(f.size) + ': el máximo es ' + MAX_MB + ' MB.', 'dau-f-' + nombre); return null; }
            fd.append(nombre, f);
        } else if ($('dau-f-quitar-' + nombre) && $('dau-f-quitar-' + nombre).checked) {
            fd.append('quitar_' + nombre, '1');
        }
        return fd;
    }

    /* Material de clase: título, módulo (o todo el grupo), descripción, y un archivo, un enlace o los dos */
    function formMaterial(m, origen) {
        var g = grupo();
        abierto = { tipo: 'form-material', id: m ? m.id : null };
        var opciones = '<option value="">Todo el grupo</option>' + (g.modulos || []).map(function (x, i) {
            return '<option value="' + x.id + '"' + (m && m.moduloId === x.id ? ' selected' : '') + '>' + esc((i + 1) + '. ' + x.nombre + (x.estado === 'en_curso' ? ' (en curso)' : '')) + '</option>';
        }).join('');
        var cuerpo = '<div class="dau-campo"><label for="dau-f-titulo">Título</label><input class="dau-input" id="dau-f-titulo" maxlength="150" value="' + esc(m ? m.titulo : '') + '" placeholder="Ej.: Presentación de la clase 3"></div>' +
            '<div class="dau-campo"><label for="dau-f-modulo">Módulo</label><select class="dau-input" id="dau-f-modulo">' + opciones + '</select></div>' +
            '<div class="dau-campo"><label for="dau-f-desc">Descripción <span class="dau-ayuda">(opcional)</span></label><textarea class="dau-input" id="dau-f-desc" maxlength="1000" rows="3" placeholder="Para qué sirve o qué deben leer">' + esc(m ? m.descripcion : '') + '</textarea></div>' +
            campoArchivo('archivo', 'Archivo', '', m && m.archivo) +
            '<div class="dau-campo"><label for="dau-f-enlace">Enlace <span class="dau-ayuda">(opcional: un video, una página o una carpeta)</span></label>' +
                '<input class="dau-input" id="dau-f-enlace" type="url" maxlength="500" inputmode="url" value="' + esc(m && m.enlace ? m.enlace : '') + '" placeholder="https://"></div>' +
            '<p class="dau-ayuda">Sube un archivo, escribe un enlace o las dos cosas.</p>' +
            '<p class="dau-error" id="dau-f-error" role="alert"></p>';
        var pie = '<button type="button" class="dau-btn dau-btn-ghost" data-cerrar-form>Cancelar</button><button type="button" class="dau-btn dau-btn-primary" id="dau-f-guardar">' + (m ? 'Guardar cambios' : 'Publicar material') + '</button>';
        abrirCajon(m ? 'Editar material' : 'Nuevo material', programaCorto(g.programa) + ' · ' + g.nombre, cuerpo, pie, origen);
        $('dau-f-guardar').addEventListener('click', function () {
            var datos = { titulo: $('dau-f-titulo').value.trim(), modulo_id: $('dau-f-modulo').value, descripcion: $('dau-f-desc').value.trim(), enlace: $('dau-f-enlace').value.trim() };
            if (datos.titulo.length < 3) return errorForm('El título debe tener al menos 3 letras.', 'dau-f-titulo');
            if (datos.enlace && !/^https?:\/\/\S+\.\S+/i.test(datos.enlace)) return errorForm('El enlace debe ser una dirección completa, que empiece por https://', 'dau-f-enlace');
            var tieneArchivo = $('dau-f-archivo').files.length || (m && m.archivo && !($('dau-f-quitar-archivo') && $('dau-f-quitar-archivo').checked));
            if (!tieneArchivo && !datos.enlace) return errorForm('Sube un archivo o escribe un enlace: el material necesita al menos uno.', 'dau-f-archivo');
            var fd = conArchivo(datos, 'archivo');
            if (!fd) return;
            guardar(m ? 'PUT' : 'POST', m ? ruta('material', m.id) : ruta('crearMaterial', g.id), fd, this);
        });
    }

    var borrador = null, bloqueada = false;
    function formEvaluacion(ev, origen) {
        var g = grupo();
        bloqueada = !!(ev && ev.conIntentos);
        abierto = { tipo: 'form-evaluacion', id: ev ? ev.id : null };
        borrador = ev ? ev.preguntas.map(function (p) {
            return { enunciado: p.enunciado, opciones: p.opciones.map(function (o) { return { texto: o.texto, correcta: !!o.correcta }; }) };
        }) : [preguntaVacia()];
        var cuerpo = '<div class="dau-campo"><label for="dau-f-titulo">Título</label><input class="dau-input" id="dau-f-titulo" maxlength="150" value="' + esc(ev ? ev.titulo : '') + '" placeholder="Ej.: Quiz de señales de tránsito"></div>' +
            '<div class="dau-fila"><div class="dau-campo"><label for="dau-f-modulo">Módulo</label><select class="dau-input" id="dau-f-modulo">' + opcionesModulo(g, ev && ev.moduloId) + '</select></div>' +
                // Una evaluación que ya cerró no se reabre: sus estudiantes ya pudieron ver las correctas
                '<div class="dau-campo"><label for="dau-f-cierra">Cierra</label><input class="dau-input" id="dau-f-cierra" type="datetime-local" value="' + esc(ev ? aLocal(ev.cierra) : enUnaSemana()) + '"' +
                    (ev && ev.cerrada ? ' disabled aria-describedby="dau-f-cierra-ayuda"><span class="dau-ayuda" id="dau-f-cierra-ayuda">Ya cerró: para dar más tiempo publica una nueva.</span>' : '>') + '</div></div>' +
            '<div class="dau-campo"><label for="dau-f-duracion">Tiempo para responder</label><select class="dau-input" id="dau-f-duracion"' + (bloqueada ? ' disabled' : '') + '>' +
                '<option value="">Sin límite (hasta el cierre)</option>' + DURACIONES.map(function (m) {
                    return '<option value="' + m + '"' + (ev && ev.duracion === m ? ' selected' : '') + '>' + (m < 60 ? m + ' minutos' : (m === 60 ? '1 hora' : (m / 60).toString().replace('.', ',') + ' horas')) + '</option>';
                }).join('') + '</select>' +
                '<span class="dau-ayuda">' + (bloqueada ? 'Alguien ya la comenzó: el tiempo no se cambia.' : 'Con tiempo, cada estudiante ve las preguntas al comenzarla y se envía sola cuando se acaba.') + '</span></div>' +
            '<div class="dau-campo"><label for="dau-f-desc">Descripción <span class="dau-ayuda">(opcional)</span></label><textarea class="dau-input" id="dau-f-desc" maxlength="1000" rows="2" placeholder="Qué evalúa y cómo se responde">' + esc(ev ? ev.descripcion : '') + '</textarea></div>' +
            (bloqueada
                ? '<div class="dau-aviso dau-aviso-neutro"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><p>Ya la comenzaron o la respondieron: puedes <b>corregir los textos, el cierre (mientras no haya cerrado) y la respuesta correcta</b>. Si cambias la correcta se recalculan las notas de quienes ya respondieron. No se pueden agregar ni quitar preguntas u opciones.</p></div>'
                : '<div class="dau-aviso dau-aviso-neutro"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><p>Cada estudiante la responde una sola vez; la nota sale sola (5,0 × aciertos ÷ preguntas) y ve las respuestas correctas después del cierre. Después podrás corregir los textos y la respuesta correcta.</p></div>') +
            '<div><p class="dau-seccion-titulo">Preguntas</p><ol class="dau-pregs" id="dau-pregs"></ol></div>' +
            (bloqueada ? '' : '<button type="button" class="dau-btn dau-btn-ghost dau-btn-sm" id="dau-f-mas">' + ICON.mas + 'Agregar pregunta</button>') +
            '<p class="dau-error" id="dau-f-error" role="alert"></p>';
        var pie = '<button type="button" class="dau-btn dau-btn-ghost" data-cerrar-form>Cancelar</button><button type="button" class="dau-btn dau-btn-primary" id="dau-f-guardar">' + (ev ? 'Guardar cambios' : 'Publicar evaluación') + '</button>';
        abrirCajon(ev ? (bloqueada ? 'Corregir evaluación' : 'Editar evaluación') : 'Nueva evaluación', programaCorto(g.programa) + ' · ' + g.nombre, cuerpo, pie, origen);
        pintarPreguntas();
        if ($('dau-f-mas')) $('dau-f-mas').addEventListener('click', function () {
            if (borrador.length >= 30) return errorForm('Una evaluación puede tener hasta 30 preguntas.');
            borrador.push(preguntaVacia());
            pintarPreguntas();
            var ult = $('dau-pregs').querySelector('li:last-child textarea');
            if (ult) ult.focus();
        });
        $('dau-f-guardar').addEventListener('click', function () {
            var datos = { titulo: $('dau-f-titulo').value.trim(), modulo_id: +$('dau-f-modulo').value, cierra: $('dau-f-cierra').value, descripcion: $('dau-f-desc').value.trim(),
                duracion: $('dau-f-duracion').value ? +$('dau-f-duracion').value : null, preguntas: borrador };
            $('dau-pregs').querySelectorAll('.is-falta').forEach(function (el) { el.classList.remove('is-falta'); });
            if (datos.titulo.length < 5) return errorForm('El título debe tener al menos 5 letras.', 'dau-f-titulo');
            if (!datos.cierra) return errorForm('Elige la fecha y la hora en que cierra.', 'dau-f-cierra');
            for (var i = 0; i < borrador.length; i++) {
                var p = borrador[i], li = $('dau-pregs').children[i];
                var falla = p.enunciado.trim().length < 3 ? 'Escribe el enunciado de la pregunta ' + (i + 1) + '.'
                    : p.opciones.some(function (o) { return !o.texto.trim(); }) ? 'Escribe el texto de todas las opciones de la pregunta ' + (i + 1) + '.'
                    : p.opciones.filter(function (o) { return o.correcta; }).length !== 1 ? 'Marca una sola respuesta correcta en la pregunta ' + (i + 1) + '.' : null;
                if (falla) { li.classList.add('is-falta'); li.scrollIntoView({ block: 'center', behavior: 'smooth' }); return errorForm(falla); }
            }
            guardar(ev ? 'PUT' : 'POST', ev ? ruta('evaluacion', ev.id) : ruta('crearEvaluacion', g.id), datos, this);
        });
    }
    function preguntaVacia() { return { enunciado: '', opciones: [{ texto: '', correcta: true }, { texto: '', correcta: false }, { texto: '', correcta: false }] }; }
    function pintarPreguntas() {
        $('dau-pregs').innerHTML = borrador.map(function (p, i) {
            return '<li class="dau-preg-ed" data-i="' + i + '">' +
                '<div class="dau-preg-cab"><b>Pregunta ' + (i + 1) + '</b>' +
                    (borrador.length > 1 && !bloqueada ? '<button type="button" class="dau-link is-peligro" data-quitar-preg="' + i + '">Quitar</button>' : '') + '</div>' +
                '<label class="sr-solo" for="dau-p-' + i + '">Enunciado de la pregunta ' + (i + 1) + '</label>' +
                '<textarea class="dau-input" id="dau-p-' + i + '" data-enunciado="' + i + '" maxlength="500" rows="2" placeholder="Escribe la pregunta">' + esc(p.enunciado) + '</textarea>' +
                '<fieldset><legend class="sr-solo">Opciones de la pregunta ' + (i + 1) + ': marca la correcta</legend><ul class="dau-ops-ed">' + p.opciones.map(function (o, j) {
                    return '<li class="dau-op-ed"><input type="radio" name="dau-c-' + i + '" data-correcta="' + i + '-' + j + '"' + (o.correcta ? ' checked' : '') + ' aria-label="Opción ' + (j + 1) + ' es la correcta">' +
                        '<input class="dau-input" data-opcion="' + i + '-' + j + '" maxlength="300" value="' + esc(o.texto) + '" placeholder="Opción ' + (j + 1) + '" aria-label="Texto de la opción ' + (j + 1) + '">' +
                        (bloqueada ? '' : '<button type="button" class="dau-quitar" data-quitar-op="' + i + '-' + j + '" aria-label="Quitar la opción ' + (j + 1) + '"' + (p.opciones.length <= 2 ? ' disabled' : '') + '>' + ICON.x + '</button>') + '</li>';
                }).join('') + '</ul></fieldset>' +
                (p.opciones.length < 6 && !bloqueada ? '<button type="button" class="dau-link" data-mas-op="' + i + '">+ Agregar opción</button>' : '') +
                '<span class="dau-ayuda">Marca con el círculo la respuesta correcta.</span>' +
            '</li>';
        }).join('');
    }
    // El borrador se mantiene al escribir (sin volver a pintar, para no perder el foco)
    document.addEventListener('input', function (e) {
        if (!borrador || !drawer.classList.contains('is-open')) return;
        var t = e.target;
        if (t.dataset.enunciado !== undefined) borrador[+t.dataset.enunciado].enunciado = t.value;
        if (t.dataset.opcion !== undefined) { var k = t.dataset.opcion.split('-'); borrador[+k[0]].opciones[+k[1]].texto = t.value; }
    });
    document.addEventListener('change', function (e) {
        if (!borrador || !drawer.classList.contains('is-open')) return;
        var t = e.target;
        if (t.dataset.correcta !== undefined) {
            var k = t.dataset.correcta.split('-');
            borrador[+k[0]].opciones.forEach(function (o, j) { o.correcta = j === +k[1]; });
        }
    });
    $('dau-dw-body').addEventListener('click', function (e) {
        if (!borrador) return;
        var b = e.target.closest('[data-quitar-preg],[data-quitar-op],[data-mas-op]');
        if (!b) return;
        if (b.dataset.quitarPreg !== undefined) borrador.splice(+b.dataset.quitarPreg, 1);
        if (b.dataset.masOp !== undefined) borrador[+b.dataset.masOp].opciones.push({ texto: '', correcta: false });
        if (b.dataset.quitarOp !== undefined) {
            var k = b.dataset.quitarOp.split('-'), ops = borrador[+k[0]].opciones, eraCorrecta = ops[+k[1]].correcta;
            ops.splice(+k[1], 1);
            if (eraCorrecta && ops.length) ops[0].correcta = true;
        }
        pintarPreguntas();
    });
    $('dau-dw-foot').addEventListener('click', function (e) { if (e.target.closest('[data-cerrar-form]')) cerrarCajon(); });

    function errorForm(msg, campo) {
        var el = $('dau-f-error');
        if (el) el.textContent = msg;
        if (campo && $(campo)) { $(campo).classList.add('is-invalido'); $(campo).focus(); }
        return false;
    }
    document.addEventListener('input', function (e) { if (e.target.classList && e.target.classList.contains('is-invalido')) e.target.classList.remove('is-invalido'); });

    var CAMPOS = { titulo: 'dau-f-titulo', modulo_id: 'dau-f-modulo', cierra: 'dau-f-cierra', instrucciones: 'dau-f-instr', descripcion: 'dau-f-desc', duracion: 'dau-f-duracion',
        adjunto: 'dau-f-adjunto', archivo: 'dau-f-archivo', enlace: 'dau-f-enlace' };
    function guardar(metodo, url, datos, btn) {
        btn.disabled = true;
        $('dau-f-error').textContent = '';
        enviar(metodo, url, datos).then(function (r) {
            btn.disabled = false;
            if (!r.ok) return errorForm(r.mensaje, r.campo ? CAMPOS[r.campo.split('.')[0]] : null);
            borrador = null;
            cerrarCajon();
            render();
            toast(r.mensaje);
        });
    }

    /* ── Eliminar ─────────────────────────────── */
    var aEliminar = null, focoDlg = null;
    function confirmarEliminar(x, tipo) {
        aEliminar = { x: x, tipo: tipo };
        focoDlg = document.activeElement;
        $('dau-dlg-titulo').textContent = tipo === 'evaluacion' ? '¿Eliminar la evaluación?' : (tipo === 'material' ? '¿Eliminar el material?' : '¿Eliminar la actividad?');
        $('dau-dlg-txt').textContent = tipo === 'material' ? '«' + x.titulo + '» desaparece del Aula virtual de tus estudiantes, con su archivo.'
            : '«' + x.titulo + '» desaparece del Aula virtual de tus estudiantes. Nadie la ha ' + (tipo === 'evaluacion' ? 'respondido' : 'entregado') + ' todavía.';
        $('dau-dlg-si').textContent = 'Sí, eliminar';
        $('dau-dialogo').hidden = false;
        $('dau-dlg-si').focus();
    }
    function cerrarDialogo() {
        $('dau-dialogo').hidden = true;
        if (focoDlg && document.body.contains(focoDlg)) focoDlg.focus();
    }
    $('dau-dialogo').querySelectorAll('[data-no]').forEach(function (b) { b.addEventListener('click', cerrarDialogo); });
    $('dau-dlg-si').addEventListener('click', function () {
        var x = aEliminar, btn = this;
        if (!x) return;
        btn.disabled = true;
        enviar('DELETE', ruta(x.tipo, x.x.id)).then(function (r) {
            btn.disabled = false;
            $('dau-dialogo').hidden = true;
            focoDlg = null;
            toast(r.mensaje, !r.ok);
            if (r.ok) render();
            $('dau-grupos').querySelector('input:checked') && $('dau-grupos').querySelector('input:checked').focus();
        });
    });

    var toastT = null;
    function toast(msg, error) {
        if (!msg) return;
        var t = $('dau-toast');
        t.textContent = msg;
        t.classList.toggle('is-error', !!error);
        t.setAttribute('role', error ? 'alert' : 'status');
        t.hidden = false;
        requestAnimationFrame(function () { t.classList.add('is-visible'); });
        clearTimeout(toastT);
        toastT = setTimeout(function () { t.classList.remove('is-visible'); setTimeout(function () { t.hidden = true; }, 220); }, 4500);
    }

    render();
})();
