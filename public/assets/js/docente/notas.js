/* =========================================================
   ESAT — Panel Docente: Notas
   Lee SOLO de window.DOC_DATOS y guarda SOLO con window.DOC_API.guardarNotas
   (datos-ejemplo.js; sessionStorage mientras no exista el backend).
   Reglas (las mismas de CalificacionService): nota por módulo de 1,0 a 5,0 con un
   decimal, aprueba 3,0; una sola habilitación por módulo perdido y solo con el módulo
   terminado; grupo finalizado y matrículas no activas = solo consulta.
   ========================================================= */
(function () {
    'use strict';

    var D = window.DOC_DATOS, API = window.DOC_API;
    var app = document.getElementById('not-app');
    if (!app || !D || !API) return;

    var APRUEBA = D.APRUEBA;
    var CLAVE_GRUPO = 'esat.docente.notas.grupo';
    var CLAVE_VISTA = 'esat.docente.notas.vista';
    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var ANIO_HOY = +D.hoy.slice(0, 4);

    var ESTADO = {
        aprobado:      ['app-status-active', 'Aprobado'],
        habilito:      ['app-status-active', 'Habilitó'],
        por_habilitar: ['app-status-warn', 'Por habilitar'],
        reprobado:     ['app-status-danger', 'Reprobado'],
        sin_nota:      ['app-status-warn', 'Sin nota'],
        no_cursado:    ['app-status-muted', 'No cursado'],
        por_iniciar:   ['app-status-muted', 'Por iniciar'],
        invalida:      ['app-status-danger', 'Revisar nota']
    };
    var MATRICULA = { aplazado: 'Aplazado', retirado: 'Retirado', graduado: 'Graduado' };
    var ESTADO_MOD = { terminado: 'Terminado', en_curso: 'En curso', por_iniciar: 'Por iniciar' };

    var ICON = {
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        reloj: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        candado: '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        check: '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>',
        lapiz: '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>'
    };

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fmt(v) { return v == null ? '—' : v.toFixed(1).replace('.', ','); }
    function tono(v) { return v == null ? 'none' : (v >= 4 ? 'ok' : (v >= APRUEBA ? 'warn' : 'bad')); }
    function iniciales(n, a) { return ((n || '').charAt(0) + (a || '').charAt(0)).toUpperCase(); }
    function primera(t) { return String(t || '').split(' ')[0]; }
    function nombreCorto(e) { return primera(e.nombres) + ' ' + primera(e.apellidos); }
    function plural(n, uno, varios) { return n + ' ' + (n === 1 ? uno : varios); }
    function fecha(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function fechaCorta(s) {
        if (!s) return '';
        var d = fecha(s);
        return d.getDate() + ' ' + MESES[d.getMonth()] + (d.getFullYear() !== ANIO_HOY ? ' ' + d.getFullYear() : '');
    }
    /* "5 ago – 2 oct" · "17 feb – 9 abr 2027" · "15 nov 2025 – 15 nov 2026" (el año solo si no es el actual o si cambia) */
    function diaMes(s) { var d = fecha(s); return d.getDate() + ' ' + MESES[d.getMonth()]; }
    function rango(a, b) {
        var ya = +a.slice(0, 4), yb = +b.slice(0, 4);
        if (ya !== yb) return diaMes(a) + ' ' + ya + ' – ' + diaMes(b) + ' ' + yb;
        return diaMes(a) + ' – ' + diaMes(b) + (ya !== ANIO_HOY ? ' ' + ya : '');
    }
    /* Aplazados y retirados: lo que no tienen no es tarea pendiente del docente */
    function seFue(e) { return e.matricula.estado === 'aplazado' || e.matricula.estado === 'retirado'; }
    function sigla(escuela) {
        var t = String(escuela || '').toLowerCase();
        var mapa = [['salud', 'sal'], ['turis', 'tur'], ['admin', 'adm'], ['educa', 'edu'], ['deport', 'dep'], ['ciencia', 'cie'], ['belleza', 'bel']];
        for (var i = 0; i < mapa.length; i++) if (t.indexOf(mapa[i][0]) !== -1) return mapa[i][1];
        return 'otra';
    }
    function promedio(lista) {
        if (!lista.length) return null;
        return Math.round(lista.reduce(function (s, v) { return s + v; }, 0) / lista.length * 10) / 10;
    }
    function leerLocal(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function guardarLocal(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ } }

    /* Lee lo que escribió el docente: acepta coma o punto, un solo decimal, de 1,0 a 5,0 */
    function leerNota(texto) {
        var t = String(texto == null ? '' : texto).trim();
        if (!t) return { ok: true, v: null };
        var norm = t.replace(',', '.');
        if (!/^\d+(\.\d*)?$/.test(norm)) return { ok: false, err: 'Escribe un número, por ejemplo 3,5.' };
        if ((norm.split('.')[1] || '').length > 1) return { ok: false, err: 'Usa un solo decimal, por ejemplo 3,5.' };
        var v = parseFloat(norm);
        if (!(v >= 1 && v <= 5)) return { ok: false, err: 'La nota va de 1,0 a 5,0.' };
        return { ok: true, v: Math.round(v * 10) / 10 };
    }
    function valorInput(v) { return v == null ? '' : fmt(v); }

    function chip(estado, apagado) {
        var e = ESTADO[estado] || ESTADO.sin_nota;
        var clase = estado === 'sin_nota' && apagado ? 'app-status-muted' : e[0];
        return '<span class="app-status-tag ' + clase + '">' + e[1] + '</span>';
    }
    function celda(n, h, estado, titulo) {
        var conNota = n != null && estado !== 'sin_nota' && estado !== 'no_cursado' && estado !== 'por_iniciar' && estado !== 'en_curso';
        var txt = conNota ? fmt(D.notaFinal(n, h)) + (h != null ? '<sup>H</sup>' : '') : (estado === 'sin_nota' ? '' : '·');
        return '<span class="not-celda not-c-' + estado + '"' + (titulo ? ' title="' + esc(titulo) + '"' : '') + '>' + txt + '</span>';
    }
    function aviso(clase, icono, html) {
        return '<div class="not-aviso' + (clase ? ' ' + clase : '') + '">' + icono + '<p>' + html + '</p></div>';
    }

    /* ── Toast ────────────────────────────────── */
    var toastEl = document.getElementById('not-toast');
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

    /* ── Datos ────────────────────────────────── */
    function grupoPorId(id) { return D.grupos.filter(function (g) { return g.id === +id; })[0] || null; }
    function moduloPorId(g, id) { return g ? g.modulos.filter(function (m) { return m.id === +id; })[0] || null : null; }
    function cuenta(e) { return e.matricula.estado === 'activo' || e.matricula.estado === 'graduado'; }
    function ordenados(g) {
        return g.estudiantes.slice().sort(function (a, b) {
            var aa = a.matricula.estado === 'activo' ? 0 : 1, bb = b.matricula.estado === 'activo' ? 0 : 1;
            return (aa - bb) || a.apellidos.localeCompare(b.apellidos, 'es') || a.nombres.localeCompare(b.nombres, 'es');
        });
    }
    function notasPorPoner(g) { return g.modulosSinNota.reduce(function (s, x) { return s + x.faltan; }, 0); }
    /* Pendientes de un módulo para el docente: solo estudiantes activos y solo con el módulo terminado */
    function pendientesModulo(g, m) {
        var r = { faltan: 0, hab: 0, conNota: 0, activos: 0 };
        if (g.soloConsulta || m.estado === 'por_iniciar') return r;
        g.estudiantes.forEach(function (e) {
            if (e.matricula.estado !== 'activo') return;
            var s = e.notas[m.id].estado;
            if (s === 'no_cursado') return;
            r.activos++;
            if (s !== 'sin_nota') r.conNota++;
            if (m.estado === 'terminado' && s === 'sin_nota') r.faltan++;
            if (m.estado === 'terminado' && s === 'por_habilitar') r.hab++;
        });
        return r;
    }
    /* Módulo por defecto: el primero terminado con notas por poner; si no, el en curso; si no, el último terminado */
    function moduloPorDefecto(g) {
        var terminados = g.modulos.filter(function (m) { return m.estado === 'terminado'; });
        var conFaltantes = terminados.filter(function (m) { return pendientesModulo(g, m).faltan > 0; })[0];
        if (conFaltantes) return conFaltantes;
        var enCurso = g.modulos.filter(function (m) { return m.estado === 'en_curso'; })[0];
        if (enCurso) return enCurso;
        return terminados.length ? terminados[terminados.length - 1] : null;
    }

    /* ── Estado de la pantalla ────────────────── */
    var st = { g: null, m: null, vista: 'modulo', filas: [] };
    var guardando = false;

    /* ── Elementos ────────────────────────────── */
    var $ = function (id) { return document.getElementById(id); };
    var elVigentes = $('not-grupos-vigentes'), elFinalizados = $('not-grupos-finalizados'), elFinZona = $('not-grupos-fin-zona');
    var elModulos = $('not-modulos'), elHoja = $('not-hoja'), elHojaHead = $('not-hoja-head'), elAvisos = $('not-hoja-avisos');
    var elKpis = $('not-kpis'), elTablaWrap = $('not-tabla-wrap'), elFilas = $('not-filas'), elPie = $('not-tabla-pie');
    var elVacia = $('not-hoja-vacia'), elVaciaTxt = $('not-hoja-vacia-txt');
    var elBarra = $('not-guardar'), elCambios = $('not-cambios'), btnGuardar = $('not-guardar-btn'), txtGuardar = $('not-guardar-txt');
    var btnDescartar = $('not-descartar'), elErrGuardar = $('not-guardar-error');
    var tabs = { modulo: $('not-tab-modulo'), completa: $('not-tab-completa') };
    var paneles = { modulo: $('not-panel-modulo'), completa: $('not-panel-completa') };

    /* ═════════ Grupos ═════════ */
    function tarjetaGrupo(g) {
        var activo = st.g && st.g.id === g.id;
        var faltan = notasPorPoner(g), hab = g.soloConsulta ? 0 : g.porHabilitar;
        var tags = '';
        if (g.soloConsulta) {
            tags = '<span class="not-tag not-tag-gris">Terminó el ' + esc(fechaCorta(g.fin)) + '</span>' +
                (g.graduados ? '<span class="not-tag not-tag-ok">' + plural(g.graduados, 'graduado', 'graduados') + '</span>' : '');
        } else {
            if (faltan) tags += '<span class="not-tag not-tag-sin">' + plural(faltan, 'nota por poner', 'notas por poner') + '</span>';
            if (hab) tags += '<span class="not-tag not-tag-hab">' + hab + ' por habilitar</span>';
            if (!faltan && !hab) tags = '<span class="not-tag not-tag-ok">Notas al día</span>';
        }
        var sub = g.nombre + ' · ' + g.jornada + ' · ' + (g.soloConsulta
            ? plural(g.estudiantes.length, 'estudiante', 'estudiantes')
            : plural(g.activos, 'activo', 'activos'));
        return '<button type="button" class="not-grupo' + (activo ? ' is-activo' : '') + (g.soloConsulta ? ' is-fin' : '') + '" data-grupo="' + g.id + '" aria-pressed="' + (activo ? 'true' : 'false') + '">' +
            '<span class="not-grupo-avatar not-esc-' + sigla(g.escuela) + '" aria-hidden="true">' + esc(g.nombre.slice(-1)) + '</span>' +
            '<span class="not-grupo-info">' +
                '<span class="not-grupo-prog">' + esc(g.programa) + '</span>' +
                '<span class="not-grupo-sub">' + esc(sub) + '</span>' +
                '<span class="not-grupo-tags">' + tags + '</span>' +
            '</span></button>';
    }
    function pintarGrupos() {
        var vig = D.grupos.filter(function (g) { return !g.soloConsulta; });
        var fin = D.grupos.filter(function (g) { return g.soloConsulta; });
        elVigentes.innerHTML = vig.map(tarjetaGrupo).join('');
        $('not-grupos-vig-zona').hidden = !vig.length;
        elFinalizados.innerHTML = fin.map(tarjetaGrupo).join('');
        elFinZona.hidden = !fin.length;
    }
    function alClickGrupo(ev) {
        var b = ev.target.closest('[data-grupo]');
        if (!b) return;
        var id = +b.dataset.grupo;
        if (st.g && st.g.id === id) return;
        conCambios(function () { elegirGrupo(id); enfocar('[data-grupo="' + id + '"]'); }, b);
    }
    elVigentes.addEventListener('click', alClickGrupo);
    elFinalizados.addEventListener('click', alClickGrupo);

    function pintarContexto() {
        var g = st.g;
        $('not-ctx-titulo').textContent = g.programa + ' · ' + g.nombre;
        var partes = [g.jornada, g.escuela, rango(g.inicio, g.fin)];
        partes.push(g.soloConsulta ? 'Finalizado · solo consulta' : plural(g.activos, 'estudiante activo', 'estudiantes activos'));
        $('not-ctx-sub').textContent = partes.join(' · ');
    }

    /* ═════════ Módulos (pestañas) ═════════ */
    function pintarModulos() {
        var g = st.g, total = g.modulos.length;
        elModulos.innerHTML = g.modulos.map(function (m, i) {
            var activo = st.m && st.m.id === m.id, p = pendientesModulo(g, m);
            var sin = p.faltan > 0;
            var pie;
            if (m.estado === 'por_iniciar') pie = '<span class="not-mod-gris">Empieza el ' + esc(fechaCorta(m.ini)) + '</span>';
            else if (m.estado === 'en_curso') pie = '<span class="not-mod-gris">' + p.conNota + ' de ' + p.activos + ' con nota</span>';
            else if (p.faltan || p.hab) {
                pie = (p.faltan ? '<span class="not-tag not-tag-sin">' + (p.faltan === 1 ? 'Falta 1 nota' : 'Faltan ' + p.faltan + ' notas') + '</span>' : '') +
                    (p.hab ? '<span class="not-tag not-tag-hab">' + p.hab + ' por habilitar</span>' : '');
            } else pie = '<span class="not-mod-ok">' + ICON.check + (g.soloConsulta ? 'Cerrado' : 'Completo') + '</span>';
            var etiqueta = 'Módulo ' + (i + 1) + ' de ' + total + ': ' + m.nombre + ', ' + rango(m.ini, m.fin) + ', ' + ESTADO_MOD[m.estado].toLowerCase() +
                (p.faltan ? ', faltan ' + p.faltan + ' notas' : '') + (p.hab ? ', ' + p.hab + ' por habilitar' : '');
            return '<button type="button" role="tab" id="not-tab-mod-' + m.id + '" class="not-mod' + (sin ? ' is-sin' : '') + ' is-' + m.estado + (activo ? ' is-activo' : '') + '"' +
                ' data-modulo="' + m.id + '" aria-selected="' + (activo ? 'true' : 'false') + '" aria-controls="not-hoja" aria-label="' + esc(etiqueta) + '"' +
                ' tabindex="' + (activo ? '0' : '-1') + '"' + (m.estado === 'por_iniciar' ? ' disabled' : '') + '>' +
                '<span class="not-mod-top"><span class="not-mod-n">' + (i + 1) + '</span><span class="not-mod-estado">' + ESTADO_MOD[m.estado] + '</span></span>' +
                '<span class="not-mod-nombre">' + esc(m.nombre) + '</span>' +
                '<span class="not-mod-fechas">' + esc(rango(m.ini, m.fin)) + '</span>' +
                '<span class="not-mod-pie">' + pie + '</span></button>';
        }).join('');
        // Si no hay módulo elegido, el primero que se pueda abrir recibe el foco con Tab
        if (!st.m) {
            var primero = elModulos.querySelector('.not-mod:not(:disabled)');
            if (primero) primero.tabIndex = 0;
        }
        var sel = elModulos.querySelector('.not-mod.is-activo');
        if (sel) {
            var r = sel.getBoundingClientRect(), c = elModulos.getBoundingClientRect();
            if (r.left < c.left || r.right > c.right) elModulos.scrollLeft += r.left - c.left - 12;
        }
    }
    elModulos.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-modulo]');
        if (!b || b.disabled) return;
        var id = +b.dataset.modulo;
        if (st.m && st.m.id === id) return;
        conCambios(function () { elegirModulo(id); enfocar('#not-tab-mod-' + id); }, b);
    });
    /* Flechas: mueven el foco entre módulos (se abre con Enter/Espacio o clic) */
    elModulos.addEventListener('keydown', function (ev) {
        var teclas = ['ArrowRight', 'ArrowLeft', 'Home', 'End'];
        if (teclas.indexOf(ev.key) === -1) return;
        var lista = Array.prototype.slice.call(elModulos.querySelectorAll('.not-mod:not(:disabled)'));
        var i = lista.indexOf(document.activeElement);
        if (i === -1 || !lista.length) return;
        ev.preventDefault();
        var j = ev.key === 'Home' ? 0 : ev.key === 'End' ? lista.length - 1 : (i + (ev.key === 'ArrowRight' ? 1 : -1) + lista.length) % lista.length;
        lista.forEach(function (b) { b.tabIndex = -1; });
        lista[j].tabIndex = 0;
        lista[j].focus();
    });

    /* ═════════ Planilla del módulo ═════════ */
    function pintarHoja() {
        var g = st.g, m = st.m;
        elErrGuardar.hidden = true;
        st.filas = [];
        if (!m) {
            var primero = g.modulos[0];
            elHoja.removeAttribute('aria-labelledby');
            elHoja.setAttribute('aria-label', 'Notas del módulo');
            elHojaHead.hidden = true;
            elAvisos.innerHTML = '';
            elKpis.hidden = true;
            elTablaWrap.hidden = true;
            elBarra.hidden = true;
            elVacia.hidden = false;
            elVaciaTxt.textContent = primero
                ? 'Ningún módulo ha empezado todavía. Podrás poner notas desde el ' + fechaCorta(primero.ini) + ', cuando empiece ' + primero.nombre + '.'
                : 'Este grupo todavía no tiene módulos en su malla.';
            return;
        }
        var idx = g.modulos.indexOf(m), enCurso = m.estado === 'en_curso';
        elHoja.removeAttribute('aria-label');
        elHoja.setAttribute('aria-labelledby', 'not-tab-mod-' + m.id);
        elHojaHead.hidden = false;
        elHojaHead.innerHTML =
            '<div class="not-min0">' +
                '<div class="not-hoja-kicker">Módulo ' + (idx + 1) + ' de ' + g.modulos.length + '</div>' +
                '<h3 class="not-hoja-titulo" id="not-hoja-titulo" tabindex="-1">' + esc(m.nombre) + '</h3>' +
                '<div class="not-hoja-sub">' + esc(rango(m.ini, m.fin)) + ' · ' + m.horas + ' horas</div>' +
            '</div>' +
            '<span class="app-status-tag ' + (enCurso ? 'app-status-active' : 'app-status-muted') + '">' + ESTADO_MOD[m.estado] + '</span>';

        var personas = ordenados(g);
        var editableGrupo = !g.soloConsulta;
        var p = pendientesModulo(g, m);

        // Avisos
        var av = '';
        if (g.soloConsulta) {
            av += aviso('is-gris', ICON.candado, '<strong>Grupo finalizado: solo consulta.</strong> Terminó el ' + esc(fechaCorta(g.fin)) + ' y sus notas ya no se pueden cambiar.');
        } else if (enCurso) {
            av += aviso('is-azul', ICON.info, 'El módulo va hasta el ' + esc(fechaCorta(m.fin)) + ': ya puedes ir poniendo notas. La habilitación se abre cuando el módulo termine, para quien quede por debajo de 3,0.');
        } else if (p.faltan) {
            av += aviso('', ICON.reloj, 'El módulo terminó el ' + esc(fechaCorta(m.fin)) + ' y ' + (p.faltan === 1 ? 'falta <strong>1 nota</strong>' : 'faltan <strong>' + p.faltan + ' notas</strong>') +
                '. Mientras falten, el módulo no cuenta en el promedio ni para graduar.');
        }
        if (!g.soloConsulta && !enCurso && p.hab) {
            av += aviso('', ICON.alerta, (p.hab === 1 ? '<strong>1 estudiante</strong> perdió' : '<strong>' + p.hab + ' estudiantes</strong> perdieron') +
                ' el módulo y ' + (p.hab === 1 ? 'tiene' : 'tienen') + ' derecho a una habilitación. Escribe su nota en la columna Habilitación.');
        }
        elAvisos.innerHTML = av;

        if (!personas.length) {
            elKpis.hidden = true;
            elTablaWrap.hidden = true;
            elBarra.hidden = true;
            elVacia.hidden = false;
            elVaciaTxt.textContent = 'Este grupo todavía no tiene estudiantes.';
            return;
        }
        elVacia.hidden = true;
        elKpis.hidden = false;
        elTablaWrap.hidden = false;
        $('not-tabla-cap').textContent = 'Notas de ' + m.nombre + ', ' + g.programa + ' ' + g.nombre;

        var html = '', separado = false, hayActivos = personas.some(function (e) { return e.matricula.estado === 'activo'; });
        personas.forEach(function (e) {
            var x = e.notas[m.id];
            var f = {
                e: e,
                orig: { n: x.n, h: x.h },
                noCursado: x.estado === 'no_cursado',
                editable: editableGrupo && e.matricula.estado === 'activo' && x.estado !== 'no_cursado'
            };
            if (!separado && hayActivos && e.matricula.estado !== 'activo' && editableGrupo) {
                separado = true;
                html += '<tr class="not-sep"><th colspan="5" scope="rowgroup">Aplazados, retirados y graduados · solo consulta</th></tr>';
            }
            html += filaHtml(f);
            st.filas.push(f);
        });
        elFilas.innerHTML = html;
        st.filas.forEach(function (f) {
            f.tr = elFilas.querySelector('tr[data-id="' + f.e.id + '"]');
            f.nIn = f.tr.querySelector('[data-campo="n"]');
            f.hIn = f.tr.querySelector('[data-campo="h"]');
            f.hCampo = f.tr.querySelector('[data-hab-campo]');
            f.hTxt = f.tr.querySelector('[data-hab-txt]');
            f.tdFinal = f.tr.querySelector('[data-final]');
            f.tdEstado = f.tr.querySelector('[data-estado]');
            actualizarFila(f);
        });

        var noCursados = st.filas.filter(function (f) { return f.noCursado; }).length;
        var pie = [];
        if (noCursados) pie.push(plural(noCursados, 'estudiante se matriculó', 'estudiantes se matricularon') + ' cuando el módulo ya había terminado: aparece' + (noCursados === 1 ? '' : 'n') + ' como No cursado.');
        if (editableGrupo && st.filas.some(function (f) { return f.e.matricula.estado !== 'activo'; })) pie.push('Las matrículas aplazadas, retiradas o graduadas solo se consultan y no cuentan en el resumen.');
        if (editableGrupo) pie.push('Enter o las flechas ↑ ↓ pasan al siguiente estudiante.');
        elPie.textContent = pie.join(' ');

        var hayEditables = st.filas.some(function (f) { return f.editable; });
        elBarra.hidden = !hayEditables;
        if (editableGrupo && !hayEditables) {
            elAvisos.insertAdjacentHTML('beforeend', aviso('is-gris', ICON.candado, 'Ningún estudiante de este módulo tiene la matrícula activa: las notas solo se consultan.'));
        }
        actualizarResumen();
        actualizarCambios();
    }

    function filaHtml(f) {
        // En un grupo finalizado todos son graduados: la etiqueta solo se pone a quien se salió del grupo
        var e = f.e, corto = nombreCorto(e), inactiva = e.matricula.estado !== 'activo' && !(st.g.soloConsulta && e.matricula.estado === 'graduado');
        var idn = 'not-n-' + e.id, idh = 'not-h-' + e.id;
        var chips = '';
        if (inactiva) {
            var mat = MATRICULA[e.matricula.estado] || e.matricula.estado;
            var cuando = e.matricula.estadoFecha ? (e.matricula.estado === 'graduado' ? ' · ' : ' desde el ') + fechaCorta(e.matricula.estadoFecha) : '';
            chips = '<div class="not-person-chips"><span class="app-status-tag ' + (e.matricula.estado === 'graduado' ? 'app-status-active' : 'app-status-muted') + '">' + esc(mat + cuando) + '</span></div>';
        }
        var dis = f.editable ? '' : ' disabled';
        var celdaN, celdaH;
        if (f.noCursado) {
            celdaN = '<span class="not-na" title="No cursó este módulo en el grupo">—</span>';
            celdaH = '<span class="not-na">—</span>';
        } else {
            celdaN = '<div class="not-campo"><input type="text" class="not-input" id="' + idn + '" data-campo="n" inputmode="decimal" autocomplete="off" spellcheck="false" enterkeyhint="next"' +
                ' value="' + esc(valorInput(f.orig.n)) + '" placeholder="' + (f.editable ? '' : '—') + '" aria-label="Nota de ' + esc(corto) + '"' + dis + '>' +
                '<p class="not-err" id="' + idn + '-err" hidden></p></div>';
            celdaH = '<div class="not-campo" data-hab-campo hidden><input type="text" class="not-input" id="' + idh + '" data-campo="h" inputmode="decimal" autocomplete="off" spellcheck="false" enterkeyhint="next"' +
                ' value="' + esc(valorInput(f.orig.h)) + '" placeholder="' + (f.editable ? '' : '—') + '" aria-label="Habilitación de ' + esc(corto) + '"' + dis + '>' +
                '<p class="not-err" id="' + idh + '-err" hidden></p></div>' +
                '<span class="not-hab-txt" data-hab-txt></span>';
        }
        return '<tr class="not-fila' + (seFue(e) ? ' is-inactiva' : '') + '" data-id="' + e.id + '">' +
            '<td><div class="not-person-cell">' +
                '<div class="not-avatar not-esc-' + sigla(st.g.escuela) + '" aria-hidden="true">' + esc(iniciales(e.nombres, e.apellidos)) + '</div>' +
                '<div class="not-min0"><div class="not-person-name">' + esc(e.nombres + ' ' + e.apellidos) +
                    '<span class="not-sucio" title="Cambios sin guardar"></span></div>' +
                '<div class="not-person-sub">' + esc(e.tipoDoc + ' ' + e.documento) + '</div>' + chips + '</div>' +
            '</div></td>' +
            '<td data-label="Nota">' + celdaN + '</td>' +
            '<td data-label="Habilitación">' + celdaH + '</td>' +
            '<td data-label="Nota final" data-final></td>' +
            '<td data-label="Estado" data-estado></td></tr>';
    }

    /* Calcula la fila con lo que está escrito ahora (sin guardar) */
    function evaluar(f) {
        if (f.noCursado) return { valido: true, estado: 'no_cursado', n: null, h: null, final: null, aplicaH: false, rn: { ok: true }, rh: { ok: true } };
        var terminado = st.m.estado === 'terminado';
        var rn = leerNota(f.nIn.value);
        var perdio = rn.ok && rn.v !== null && rn.v < APRUEBA;
        var aplicaH = perdio && terminado;
        var rh = aplicaH ? leerNota(f.hIn.value) : { ok: true, v: null };
        var valido = rn.ok && rh.ok;
        return {
            rn: rn, rh: rh, aplicaH: aplicaH, perdio: perdio, valido: valido,
            n: rn.ok ? rn.v : null, h: rh.ok ? rh.v : null,
            estado: valido ? D.estadoNota(rn.v, rh.v) : 'invalida',
            final: valido && rn.v !== null ? D.notaFinal(rn.v, rh.v) : null
        };
    }
    function esCambio(f, ev) {
        if (!f.editable) return false;
        if (!ev.rn.ok || (ev.aplicaH && !ev.rh.ok)) return true;
        return ev.n !== f.orig.n || ev.h !== (ev.aplicaH ? f.orig.h : null) || (!ev.aplicaH && f.orig.h !== null);
    }
    function actualizarFila(f) {
        var ev = evaluar(f);
        f.ev = ev;
        f.cambiada = esCambio(f, ev);
        f.tr.classList.toggle('is-cambiada', f.cambiada);
        if (f.hCampo) {
            f.hCampo.hidden = !ev.aplicaH;
            if (!ev.aplicaH) limpiarError(f.hIn);
            f.hTxt.hidden = ev.aplicaH;
            if (!ev.aplicaH) {
                if (ev.perdio && st.m.estado === 'en_curso') {
                    f.hTxt.textContent = 'Cuando termine el módulo';
                    f.hTxt.className = 'not-hab-txt';
                } else {
                    f.hTxt.textContent = '—';
                    f.hTxt.className = 'not-na';
                }
                f.hTxt.title = ev.perdio ? '' : 'Solo para quien saca menos de 3,0';
            }
        }
        var apagado = st.m.estado === 'en_curso' || seFue(f.e);
        f.tdFinal.innerHTML = ev.final == null
            ? '<span class="not-na">—</span>'
            : celda(ev.n, ev.h, ev.estado, ev.h != null ? 'Nota ' + fmt(ev.n) + ' · habilitación ' + fmt(ev.h) : '');
        f.tdEstado.innerHTML = chip(ev.estado, apagado);
    }

    function actualizarResumen() {
        var finales = [], c = { aprob: 0, hab: 0, rep: 0, sin: 0 };
        st.filas.forEach(function (f) {
            if (!cuenta(f.e) || !f.ev) return;
            var s = f.ev.estado;
            if (f.ev.final != null) finales.push(f.ev.final);
            if (s === 'aprobado' || s === 'habilito') c.aprob++;
            else if (s === 'por_habilitar') c.hab++;
            else if (s === 'reprobado') c.rep++;
            else if (s === 'sin_nota') c.sin++;
        });
        var prom = promedio(finales), elProm = $('not-k-prom');
        elProm.textContent = fmt(prom);
        $('not-k-aprob').textContent = c.aprob;
        $('not-k-hab').textContent = c.hab;
        $('not-k-rep').textContent = c.rep;
        $('not-k-sin').textContent = c.sin;
    }

    function cambiadas() { return st.filas.filter(function (f) { return f.cambiada; }); }
    function actualizarCambios() {
        var n = cambiadas().length;
        elCambios.textContent = guardando ? 'Guardando…' : (n ? plural(n, 'estudiante con cambios sin guardar', 'estudiantes con cambios sin guardar') : 'Sin cambios por guardar');
        elBarra.classList.toggle('is-sucia', n > 0);
        btnGuardar.disabled = !n || guardando;
        btnDescartar.disabled = !n || guardando;
    }

    /* Errores en línea (aria-invalid + aria-describedby) */
    function errorDe(inp) { return inp ? $(inp.id + '-err') : null; }
    function mostrarError(inp, msg) {
        var p = errorDe(inp);
        if (!p) return;
        p.textContent = msg;
        p.hidden = false;
        inp.classList.add('is-invalid');
        inp.setAttribute('aria-invalid', 'true');
        inp.setAttribute('aria-describedby', p.id);
    }
    function limpiarError(inp) {
        var p = errorDe(inp);
        if (!p) return;
        p.hidden = true;
        p.textContent = '';
        inp.classList.remove('is-invalid');
        inp.removeAttribute('aria-invalid');
        inp.removeAttribute('aria-describedby');
    }
    function filaDe(inp) {
        var tr = inp.closest('tr[data-id]');
        return tr ? st.filas.filter(function (f) { return f.tr === tr; })[0] : null;
    }

    elFilas.addEventListener('input', function (ev) {
        var inp = ev.target;
        if (!inp.classList.contains('not-input')) return;
        if (inp.classList.contains('is-invalid') && leerNota(inp.value).ok) limpiarError(inp);
        var f = filaDe(inp);
        if (!f) return;
        actualizarFila(f);
        actualizarResumen();
        actualizarCambios();
    });
    /* Al salir del campo: normaliza (3 → 3,0 · 3.5 → 3,5) o muestra el error */
    elFilas.addEventListener('focusout', function (ev) {
        var inp = ev.target;
        if (!inp.classList || !inp.classList.contains('not-input') || inp.disabled) return;
        var r = leerNota(inp.value);
        if (r.ok) {
            inp.value = valorInput(r.v);
            limpiarError(inp);
        } else {
            mostrarError(inp, r.err);
        }
        var f = filaDe(inp);
        if (f) { actualizarFila(f); actualizarResumen(); actualizarCambios(); }
    });
    /* Enter / ↓ pasa al siguiente estudiante; Mayús+Enter / ↑ al anterior */
    elFilas.addEventListener('keydown', function (ev) {
        var inp = ev.target;
        if (!inp.classList.contains('not-input')) return;
        if (ev.key !== 'Enter' && ev.key !== 'ArrowDown' && ev.key !== 'ArrowUp') return;
        ev.preventDefault();
        moverFoco(inp, ev.key === 'ArrowUp' || (ev.key === 'Enter' && ev.shiftKey) ? -1 : 1);
    });
    function visible(inp) { return inp && !inp.disabled && !(inp.closest('[data-hab-campo]') || {}).hidden; }
    function moverFoco(inp, dir) {
        var campo = inp.dataset.campo, f = filaDe(inp);
        var lista = st.filas.filter(function (x) { return x.editable; });
        var i = lista.indexOf(f);
        for (var j = i + dir; j >= 0 && j < lista.length; j += dir) {
            var destino = campo === 'h' ? lista[j].hIn : lista[j].nIn;
            if (campo === 'h' && !visible(destino)) continue;
            if (visible(destino)) { destino.focus(); destino.select(); return; }
        }
        // Sin más estudiantes en esa columna
        if (dir > 0) {
            inp.blur();
            if (!btnGuardar.disabled) btnGuardar.focus();
            else inp.focus();
        }
    }

    /* ── Guardar ──────────────────────────────── */
    function validarCambios() {
        var malos = [];
        cambiadas().forEach(function (f) {
            if (!f.ev.rn.ok) { mostrarError(f.nIn, f.ev.rn.err); malos.push(f.nIn); }
            if (f.ev.aplicaH && !f.ev.rh.ok) { mostrarError(f.hIn, f.ev.rh.err); malos.push(f.hIn); }
        });
        return malos;
    }
    function avisarInvalidos(malos) {
        elErrGuardar.textContent = 'Revisa ' + (malos.length === 1 ? 'la nota marcada' : 'las ' + malos.length + ' notas marcadas') + ' en rojo antes de guardar.';
        elErrGuardar.hidden = false;
        malos[0].focus();
        malos[0].select();
    }
    function ponerGuardando(si) {
        guardando = si;
        txtGuardar.textContent = si ? 'Guardando…' : 'Guardar notas';
        btnGuardar.setAttribute('aria-busy', si ? 'true' : 'false');
        actualizarCambios();
    }
    /* Devuelve una promesa con {ok} · {ok:false, tipo:'validacion'} · {ok:false, tipo:'api', mensaje} */
    function guardar() {
        if (guardando) return Promise.resolve({ ok: false, tipo: 'ocupado' });
        elErrGuardar.hidden = true;
        var lista = cambiadas();
        if (!lista.length) return Promise.resolve({ ok: true });
        var malos = validarCambios();
        if (malos.length) return Promise.resolve({ ok: false, tipo: 'validacion', malos: malos });

        var payload = {};
        lista.forEach(function (f) { payload[f.e.id] = { n: f.ev.n, h: f.ev.h }; });
        ponerGuardando(true);
        return API.guardarNotas(st.g.id, st.m.id, payload).then(function (r) {
            ponerGuardando(false);
            toast((r && r.mensaje ? r.mensaje : 'Guardaste las notas.') + ' ' + plural(lista.length, 'estudiante actualizado.', 'estudiantes actualizados.'));
            refrescar();
            return { ok: true };
        }, function (err) {
            ponerGuardando(false);
            return { ok: false, tipo: 'api', mensaje: (err && err.message) || 'No se pudieron guardar las notas. Intenta de nuevo.' };
        });
    }
    btnGuardar.addEventListener('click', function () {
        guardar().then(function (r) {
            if (r.ok) {
                var t = $('not-hoja-titulo');
                if (t) t.focus({ preventScroll: true });
            } else if (r.tipo === 'validacion') {
                avisarInvalidos(r.malos);
            } else if (r.tipo === 'api') {
                elErrGuardar.textContent = r.mensaje;
                elErrGuardar.hidden = false;
            }
        });
    });
    btnDescartar.addEventListener('click', function () {
        var n = cambiadas().length;
        pintarHoja();
        pintarMatriz();
        toast('Descartaste los cambios de ' + plural(n, 'estudiante.', 'estudiantes.'));
        var t = $('not-hoja-titulo');
        if (t) t.focus({ preventScroll: true });
    });

    /* Re-pinta todo desde DOC_DATOS (después de guardar) */
    function refrescar() {
        st.g = grupoPorId(st.g.id);
        st.m = st.m ? moduloPorId(st.g, st.m.id) : null;
        pintarGrupos();
        pintarContexto();
        pintarModulos();
        pintarHoja();
        pintarMatriz();
    }

    /* ── Diálogo de cambios sin guardar ───────── */
    var dlg = $('not-dialogo'), dlgError = $('not-dlg-error'), dlgFoco = null, pendiente = null;
    function conCambios(accion, origen) {
        var n = cambiadas().length;
        if (!n) { accion(); return; }
        pendiente = accion;
        dlgFoco = origen || document.activeElement;
        $('not-dlg-texto').textContent = 'Hay ' + plural(n, 'estudiante', 'estudiantes') + ' con notas sin guardar en ' + st.m.nombre +
            ' (' + st.g.programa + ' · ' + st.g.nombre + '). ¿Las guardas antes de cambiar?';
        dlgError.hidden = true;
        dlg.hidden = false;
        dlg.querySelector('[data-dlg="guardar"]').focus();
    }
    function cerrarDialogo(volver) {
        dlg.hidden = true;
        pendiente = null;
        dlg.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
        if (volver && dlgFoco && document.contains(dlgFoco)) dlgFoco.focus();
    }
    dlg.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-dlg]');
        if (!b || b.disabled) return;
        var accion = pendiente, que = b.dataset.dlg;
        if (que === 'cancelar') { cerrarDialogo(true); return; }
        if (que === 'descartar') { cerrarDialogo(false); if (accion) accion(); return; }
        // Guardar: si hay notas mal escritas, se cierra el diálogo y se marcan en la planilla
        var malos = validarCambios();
        if (malos.length) {
            cerrarDialogo(false);
            mostrarVista('modulo');
            avisarInvalidos(malos);
            return;
        }
        dlgError.hidden = true;
        dlg.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
        b.textContent = 'Guardando…';
        guardar().then(function (r) {
            b.textContent = 'Guardar';
            if (r.ok) { cerrarDialogo(false); if (accion) accion(); return; }
            dlg.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
            dlgError.textContent = r.mensaje || 'No se pudieron guardar las notas.';
            dlgError.hidden = false;
            b.focus();
        });
    });
    document.addEventListener('keydown', function (ev) {
        if (dlg.hidden) return;
        if (ev.key === 'Escape') { ev.preventDefault(); if (!guardando) cerrarDialogo(true); return; }
        if (ev.key === 'Tab') {
            var foco = Array.prototype.slice.call(dlg.querySelectorAll('button:not(:disabled)'));
            if (!foco.length) { ev.preventDefault(); return; }
            var i = foco.indexOf(document.activeElement);
            if (ev.shiftKey && (i <= 0)) { ev.preventDefault(); foco[foco.length - 1].focus(); }
            else if (!ev.shiftKey && (i === -1 || i === foco.length - 1)) { ev.preventDefault(); foco[0].focus(); }
        }
    });
    window.addEventListener('beforeunload', function (ev) {
        if (!cambiadas().length) return;
        ev.preventDefault();
        ev.returnValue = '';
    });

    /* ═════════ Planilla completa ═════════ */
    function pintarMatriz() {
        var g = st.g, wrap = $('not-matriz-wrap'), avisos = '';
        if (g.soloConsulta) avisos += aviso('is-gris', ICON.candado, '<strong>Grupo finalizado: solo consulta.</strong>');
        var n = cambiadas().length;
        if (n && st.m) avisos += aviso('', ICON.lapiz, 'Tienes ' + plural(n, 'estudiante', 'estudiantes') + ' con cambios sin guardar en <strong>' + esc(st.m.nombre) + '</strong>. Aquí se ve lo que ya está guardado.');
        $('not-completa-avisos').innerHTML = avisos;

        var personas = ordenados(g);
        if (!personas.length) { wrap.innerHTML = '<p class="not-empty">Este grupo todavía no tiene estudiantes.</p>'; return; }

        var html = '<table class="not-matriz"><caption class="not-sr">Notas finales de ' + esc(g.programa + ' ' + g.nombre) + ' por módulo</caption><thead><tr><th class="not-mz-nombre" scope="col">Estudiante</th>';
        g.modulos.forEach(function (m, i) {
            var p = pendientesModulo(g, m), est = p.faltan ? 'sin' : m.estado;
            var lbl = { terminado: 'terminado', en_curso: 'en curso', por_iniciar: 'por iniciar', sin: 'sin nota' }[est];
            var actual = st.m && st.m.id === m.id;
            html += '<th class="not-mz-mod is-' + est + (actual ? ' is-actual' : '') + '" scope="col">' +
                '<button type="button" class="not-mz-mod-btn" data-abrir-modulo="' + m.id + '"' + (m.estado === 'por_iniciar' ? ' disabled' : '') +
                ' title="' + esc((i + 1) + '. ' + m.nombre + ' · ' + rango(m.ini, m.fin)) + '"' +
                ' aria-label="' + esc('Módulo ' + (i + 1) + ': ' + m.nombre + ' (' + lbl + ')' + (m.estado === 'por_iniciar' ? '' : '. Abrir sus notas')) + '">' +
                '<b>M' + (i + 1) + '</b><i>' + lbl + '</i></button></th>';
        });
        html += '<th class="not-mz-prom" scope="col">Prom.</th></tr></thead><tbody>';

        var porModulo = g.modulos.map(function () { return []; }), todas = [];
        personas.forEach(function (e) {
            var inactiva = e.matricula.estado !== 'activo' && !(g.soloConsulta && e.matricula.estado === 'graduado');
            html += '<tr' + (seFue(e) ? ' class="is-inactiva"' : '') + '><th class="not-mz-nombre" scope="row">' +
                '<span class="not-mz-persona" title="' + esc(e.nombres + ' ' + e.apellidos) + '">' + esc(e.apellidos + ', ' + primera(e.nombres)) + '</span>' +
                (inactiva ? '<span class="not-mz-mat">' + esc(MATRICULA[e.matricula.estado] || e.matricula.estado) + '</span>' : '') + '</th>';
            g.modulos.forEach(function (m, i) {
                var x = e.notas[m.id], s = x.estado;
                var tit;
                if (s === 'sin_nota' && m.estado === 'en_curso') { s = 'en_curso'; tit = 'En curso, sin nota aún'; }
                else if (s === 'sin_nota' && seFue(e)) { s = 'no_cursado'; tit = 'Sin nota · ' + (MATRICULA[e.matricula.estado] || '').toLowerCase(); }
                else tit = (ESTADO[s] || ESTADO.sin_nota)[1];
                tit = m.nombre + ' · ' + tit +
                    (x.n != null ? ' · nota ' + fmt(x.n) + (x.h != null ? ', habilitación ' + fmt(x.h) : '') : '');
                html += '<td class="not-mz-celda">' + celda(x.n, x.h, s, tit) + '</td>';
                if (x.n != null && cuenta(e)) { var fin = D.notaFinal(x.n, x.h); porModulo[i].push(fin); todas.push(fin); }
            });
            html += '<td class="not-mz-prom not-tono-' + tono(e.promedio) + '">' + fmt(e.promedio) + '</td></tr>';
        });
        var promGrupo = promedio(todas);
        html += '</tbody><tfoot><tr><th class="not-mz-nombre" scope="row">Promedio del módulo</th>' +
            porModulo.map(function (l) { var p = promedio(l); return '<td class="not-mz-celda"><span class="not-nota not-tono-' + tono(p) + '">' + fmt(p) + '</span></td>'; }).join('') +
            '<td class="not-mz-prom not-tono-' + tono(promGrupo) + '">' + fmt(promGrupo) + '</td></tr></tfoot></table>';
        wrap.innerHTML = html;
    }
    $('not-matriz-wrap').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-abrir-modulo]');
        if (!b || b.disabled) return;
        var id = +b.dataset.abrirModulo;
        if (st.m && st.m.id === id) { mostrarVista('modulo', true); enfocar('#not-tab-mod-' + id); return; }
        conCambios(function () { elegirModulo(id); mostrarVista('modulo', true); enfocar('#not-tab-mod-' + id); }, b);
    });

    /* ── Vistas (pestañas) ────────────────────── */
    function mostrarVista(v, recordar) {
        st.vista = v;
        Object.keys(tabs).forEach(function (k) {
            var on = k === v;
            tabs[k].classList.toggle('is-active', on);
            tabs[k].setAttribute('aria-selected', on ? 'true' : 'false');
            tabs[k].tabIndex = on ? 0 : -1;
            paneles[k].hidden = !on;
        });
        if (v === 'completa') pintarMatriz();
        if (recordar !== false) guardarLocal(CLAVE_VISTA, v);
    }
    Object.keys(tabs).forEach(function (k) {
        tabs[k].addEventListener('click', function () { mostrarVista(k); });
        tabs[k].addEventListener('keydown', function (ev) {
            if (ev.key !== 'ArrowRight' && ev.key !== 'ArrowLeft') return;
            ev.preventDefault();
            var otro = k === 'modulo' ? 'completa' : 'modulo';
            mostrarVista(otro);
            tabs[otro].focus();
        });
    });

    /* ── Elegir grupo / módulo ────────────────── */
    function actualizarUrl() {
        try {
            var u = new URL(window.location.href);
            u.searchParams.set('grupo', st.g.id);
            if (st.m) u.searchParams.set('modulo', st.m.id); else u.searchParams.delete('modulo');
            history.replaceState(null, '', u.pathname + u.search + u.hash);
        } catch (e) { /* navegador sin URL/history: no pasa nada */ }
    }
    function elegirGrupo(id, moduloId) {
        var g = grupoPorId(id);
        if (!g) return;
        st.g = g;
        var m = moduloId ? moduloPorId(g, moduloId) : null;
        st.m = m && m.estado !== 'por_iniciar' ? m : moduloPorDefecto(g);
        guardarLocal(CLAVE_GRUPO, String(g.id));
        pintarGrupos();
        pintarContexto();
        pintarModulos();
        pintarHoja();
        pintarMatriz();
        actualizarUrl();
    }
    function elegirModulo(id) {
        var m = moduloPorId(st.g, id);
        if (!m || m.estado === 'por_iniciar') return;
        st.m = m;
        pintarModulos();
        pintarHoja();
        pintarMatriz();
        actualizarUrl();
    }
    function enfocar(selector) {
        var el = app.querySelector(selector);
        if (el) el.focus({ preventScroll: true });
    }

    /* ── Inicio ───────────────────────────────── */
    if (!D.grupos.length) {
        $('not-sin-grupos').hidden = false;
        $('not-grupos-zona').hidden = true;
        $('not-trabajo').hidden = true;
        return;
    }
    var params = new URLSearchParams(window.location.search);
    var mUrl = params.get('modulo');
    // ?modulo= sin ?grupo=: el grupo es el dueño de ese módulo
    var gUrl = grupoPorId(params.get('grupo')) ||
        (mUrl ? D.grupos.filter(function (g) { return !!moduloPorId(g, mUrl); })[0] || null : null);
    var gLocal = grupoPorId(leerLocal(CLAVE_GRUPO));
    var vigentes = D.grupos.filter(function (g) { return !g.soloConsulta; });
    var gInicial = gUrl || gLocal ||
        vigentes.filter(function (g) { return notasPorPoner(g) > 0; })[0] || vigentes[0] || D.grupos[0];
    if (!gUrl || !moduloPorId(gUrl, mUrl)) mUrl = null;
    elegirGrupo(gInicial.id, mUrl);

    var vistaInicial = mUrl ? 'modulo' : (leerLocal(CLAVE_VISTA) === 'completa' ? 'completa' : 'modulo');
    mostrarVista(vistaInicial, false);
}());
