/* =========================================================
   ESAT — Panel Admin (ESAT general): Académico
   Consulta de todas las sedes o de una: Docentes, Grupos, Asistencia y Notas (filtros por pestaña, detalle de
   docente y de grupo con su malla, exportar). Todo lo de los datos va por esc().
   ========================================================= */
(function () {
    var D = window.GEN_DATOS;
    if (!D) return;

    var POR_PAGINA = 12;
    var ICON = {
        docente: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/></svg>',
        grupo: '<svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        ok: '<svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        alerta: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
    };

    /* ── Utilidades ─────────────────────────────── */
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function num(n) { return Math.round(n || 0).toLocaleString('es-CO'); }
    function $(id) { return document.getElementById(id); }
    function normal(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    function fechaLarga(f) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f); return d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear(); }
    function hace(iso) {
        var d = Math.floor((Date.now() - new Date(iso)) / 86400000);
        return d <= 0 ? 'hoy' : d === 1 ? 'ayer' : 'hace ' + d + ' días';
    }

    var toastEl = $('aca-toast'), toastT;
    function toast(msg) {
        clearTimeout(toastT);
        toastEl.textContent = msg; toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        toastT = setTimeout(function () { toastEl.classList.remove('is-visible'); setTimeout(function () { toastEl.hidden = true; }, 220); }, 3800);
    }

    /* Paginación: botones numerados con saltos (…) */
    function paginar(nav, total, pagina, alIr) {
        var paginas = Math.ceil(total / POR_PAGINA);
        nav.hidden = paginas <= 1;
        if (paginas <= 1) { nav.innerHTML = ''; return; }
        var r = [1];
        if (pagina > 3) r.push('…');
        for (var i = Math.max(2, pagina - 1); i <= Math.min(paginas - 1, pagina + 1); i++) r.push(i);
        if (pagina < paginas - 2) r.push('…');
        r.push(paginas);
        nav.innerHTML = '<button type="button" class="aca-pg-btn" data-pg="' + (pagina - 1) + '"' + (pagina === 1 ? ' disabled' : '') + '>Anterior</button>' +
            r.map(function (p) {
                return p === '…' ? '<span class="aca-pg-gap">…</span>'
                    : '<button type="button" class="aca-pg-num' + (p === pagina ? ' is-active' : '') + '" data-pg="' + p + '"' + (p === pagina ? ' aria-current="page"' : '') + '>' + p + '</button>';
            }).join('') +
            '<button type="button" class="aca-pg-btn" data-pg="' + (pagina + 1) + '"' + (pagina === paginas ? ' disabled' : '') + '>Siguiente</button>';
        nav.onclick = function (e) { var b = e.target.closest('[data-pg]'); if (b && !b.disabled) alIr(+b.dataset.pg); };
    }

    /* ── Combobox (copiado del Admin: abre hacia donde quepa, alScroll ignora el scroll de su lista) ── */
    function initCombobox(root) {
        var trigger = root.querySelector('[data-combobox-trigger]'), label = root.querySelector('[data-combobox-label]');
        var panel = root.querySelector('[data-combobox-panel]'), searchInput = root.querySelector('[data-combobox-search]');
        var list = root.querySelector('[data-combobox-list]'), hidden = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;
        function posicionar() {
            var rect = trigger.getBoundingClientRect(), margen = 12;
            panel.style.left = rect.left + 'px'; panel.style.width = rect.width + 'px';
            var abajo = window.innerHeight - rect.bottom - 6 - margen, arriba = rect.top - 6 - margen;
            list.style.maxHeight = '';
            var extra = panel.offsetHeight - list.offsetHeight, tope = 320;
            var necesario = Math.min(list.scrollHeight, tope) + extra;
            var haciaArriba = abajo < necesario && arriba > abajo, espacio = haciaArriba ? arriba : abajo;
            panel.style.top = haciaArriba ? '' : (rect.bottom + 6) + 'px';
            panel.style.bottom = haciaArriba ? (window.innerHeight - rect.top + 6) + 'px' : '';
            list.style.maxHeight = Math.max(Math.min(tope, espacio - extra), 40) + 'px';
        }
        var esAbsoluto = root.dataset.position === 'absolute';   // filtros: el CSS lo pone debajo del botón
        function alScroll(e) { if (panel.contains(e.target)) return; posicionar(); }
        function abrir() {
            panel.hidden = false; root.classList.add('is-open');
            if (!esAbsoluto) { posicionar(); window.addEventListener('scroll', alScroll, true); window.addEventListener('resize', posicionar); }
            if (searchInput) { searchInput.value = ''; filtrar(''); searchInput.focus(); }
        }
        function cerrar() {
            if (panel.hidden) return;
            panel.hidden = true; root.classList.remove('is-open');
            if (!esAbsoluto) { window.removeEventListener('scroll', alScroll, true); window.removeEventListener('resize', posicionar); }
        }
        function filtrar(q) {
            var qq = normal(q.trim()), vis = 0;
            list.querySelectorAll('li[data-value]').forEach(function (li) { var ok = !qq || normal(li.textContent).indexOf(qq) !== -1; li.hidden = !ok; if (ok) vis++; });
            var vacio = list.querySelector('.app-combobox-empty'); if (vacio) vacio.hidden = vis > 0;
        }
        trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(); });
        if (searchInput) searchInput.addEventListener('input', function () { filtrar(searchInput.value); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });
        root.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { e.stopPropagation(); cerrar(); trigger.focus(); } });
        root.setOptions = function (ops) {
            list.innerHTML = '';
            ops.forEach(function (o) {
                var val = typeof o === 'object' ? o.value : o, lbl = typeof o === 'object' ? o.label : o;
                var li = document.createElement('li');
                li.setAttribute('role', 'option'); li.dataset.value = val; li.tabIndex = -1;
                li.innerHTML = '<span></span><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>';
                li.firstChild.textContent = lbl;
                li.addEventListener('click', function () { root.setValue(val, lbl); cerrar(); trigger.focus(); });
                li.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); li.click(); } });
                list.appendChild(li);
            });
            var vacio = document.createElement('li');
            vacio.className = 'app-combobox-empty'; vacio.hidden = ops.length > 0; vacio.textContent = 'Sin resultados';
            list.appendChild(vacio);
        };
        root.setValue = function (val, lbl, opts) {
            hidden.value = val || ''; label.textContent = lbl || val || placeholder;
            root.classList.remove('is-invalid');
            list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.toggle('is-selected', li.dataset.value === String(val)); });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        root.reset = function (ph) { if (ph) placeholder = ph; list.innerHTML = ''; hidden.value = ''; label.textContent = placeholder; };
        root.value = function () { return hidden.value; };
        root.isOpen = function () { return !panel.hidden; };
    }

    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    function descargarCsv(base, filas) {
        var csv = '﻿' + filas.map(function (f) { return f.map(celdaCsv).join(';'); }).join('\r\n');
        var h = new Date(), dd = function (n) { return (n < 10 ? '0' : '') + n; };
        var nombre = base + '-' + h.getFullYear() + '-' + dd(h.getMonth() + 1) + '-' + dd(h.getDate()) + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a'); a.href = url; a.download = nombre;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        toast('Se descargó ' + nombre + ' con ' + (filas.length - 1) + (filas.length === 2 ? ' fila.' : ' filas.'));
    }
    var MES_C = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
    function fechaMini(f) { var d = new Date(f.length === 10 ? f + 'T12:00:00' : f); return d.getDate() + ' ' + MES_C[d.getMonth()] + ' ' + d.getFullYear(); }

    /* ── Datos ──────────────────────────────────── */
    var EST_G = { en_clases: ['En clases', 'active'], por_iniciar: ['Por iniciar', 'warn'], finalizado: ['Finalizado', 'muted'] };
    function sede(id) { return D.sedes.find(function (s) { return s.id === +id; }); }
    function programa(id) { return D.programas.find(function (p) { return p.id === +id; }); }
    function docente(id) { return D.docentes.find(function (d) { return d.id === +id; }); }
    function corto(n) { return String(n || '').replace('Técnico Laboral en ', ''); }
    function nombreDoc(d) { return d ? d.nombres + ' ' + d.apellidos : 'Sin docente'; }
    /* Las cifras de cada grupo vienen del servidor con las reglas de la secretaría (AsistenciaService y CalificacionService) */
    function asistenciaDe(g) { return g.asistencia; }
    function bajo80(g) { return g.bajo80; }
    function promedioDe(g) { return g.promedio; }
    function porHabilitar(g) { return g.por_habilitar; }
    function modulo(g) { var p = programa(g.programa_id); return g.modulo_actual != null && p.modulos[g.modulo_actual] ? p.modulos[g.modulo_actual] : null; }
    function pctTxt(v) { return v == null ? '—' : v + ' %'; }
    function tag(par) { return '<span class="app-status-tag app-status-' + par[1] + '">' + esc(par[0]) + '</span>'; }
    function medidor(pct, bajo) { return '<div class="aca-medidor"><div class="aca-bar"><span class="' + (bajo ? 'is-baja' : '') + '" style="width:' + Math.max(0, Math.min(100, pct)) + '%"></span></div><b>' + pct + ' %</b></div>'; }
    function nota(n) { return n ? n.toFixed(1).replace('.', ',') : '—'; }

    /* ── Sede, pestaña y filtros ────────────────── */
    var sel = '', ptab = 'grupos', pagina = 1, filas = [];
    try { sel = localStorage.getItem('esat.general.academico.sede') || ''; ptab = localStorage.getItem('esat.general.academico.tab') || 'grupos'; } catch (e) {}
    if (sel && !sede(sel)) sel = '';
    function enSede(x) { return !sel || String(x.sede_id) === sel; }
    var chips = $('aca-chips');
    D.sedes.forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'aca-chip'; b.setAttribute('role', 'radio'); b.dataset.sede = s.id; b.textContent = s.nombre;
        chips.appendChild(b);
    });
    chips.addEventListener('click', function (e) {
        var b = e.target.closest('.aca-chip'); if (!b) return;
        sel = b.dataset.sede || ''; try { localStorage.setItem('esat.general.academico.sede', sel); } catch (err) {}
        pintar();
    });
    var cb = $('aca-cb-filtro'); initCombobox(cb);
    var FILTROS = {
        grupos: { buscar: 'Buscar programa, grupo o docente...', ops: [{ value: 'vigentes', label: 'Grupos vigentes' }, { value: '', label: 'Todos' }, { value: 'en_clases', label: 'En clases' }, { value: 'por_iniciar', label: 'Por iniciar' }, { value: 'finalizado', label: 'Finalizados' }] },
        asistencia: { buscar: 'Buscar programa, grupo o docente...', ops: [{ value: '', label: 'Todos los grupos en clases' }, { value: 'sin_registrar', label: 'Con clases sin registrar' }, { value: 'bajo', label: 'Con estudiantes bajo 80 %' }] },
        notas: { buscar: 'Buscar programa, grupo o docente...', ops: [{ value: '', label: 'Todos los grupos en clases' }, { value: 'sin_nota', label: 'Con módulos sin nota' }, { value: 'habilitar', label: 'Con estudiantes por habilitar' }] }
    };
    function prepararFiltro() {
        var f = FILTROS[ptab];
        $('aca-buscar').placeholder = f.buscar; $('aca-buscar').value = '';
        cb.setOptions(f.ops); cb.setValue(f.ops[0].value, f.ops[0].label, { silent: true });
    }
    cb.querySelector('[data-combobox-value]').addEventListener('change', function () { pagina = 1; pintarTabla(); });
    $('aca-buscar').addEventListener('input', function () { pagina = 1; pintarTabla(); });
    document.querySelectorAll('.aca-ptab').forEach(function (b) {
        b.addEventListener('click', function () {
            ptab = b.dataset.ptab; try { localStorage.setItem('esat.general.academico.tab', ptab); } catch (e) {}
            prepararFiltro(); pagina = 1; pintar();
        });
    });

    /* ── KPI ────────────────────────────────────── */
    function pintarKpis() {
        var gs = D.grupos.filter(enSede), enClases = gs.filter(function (g) { return g.estado === 'en_clases'; });
        var docs = D.docentes.filter(function (d) { return d.estado === 'activo' && (!sel || d.sedes.indexOf(+sel) >= 0); });
        var conAsis = enClases.filter(function (g) { return g.asistencia != null && g.estudiantes; }), pesos = conAsis.reduce(function (t, g) { return t + g.estudiantes; }, 0);
        var asis = pesos ? Math.round(conAsis.reduce(function (t, g) { return t + g.asistencia * g.estudiantes; }, 0) / pesos) : 0;
        var sinReg = enClases.reduce(function (t, g) { return t + g.sin_registrar; }, 0), sinNota = enClases.reduce(function (t, g) { return t + g.sin_nota; }, 0);
        var k = [['blue', 'docente', num(docs.length), 'Docentes activos'], ['green', 'grupo', num(enClases.length), 'Grupos en clases · ' + gs.filter(function (g) { return g.estado === 'por_iniciar'; }).length + ' por iniciar'],
            [asis < 80 ? 'orange' : 'green', 'ok', asis + ' %', 'Asistencia promedio'], [sinReg + sinNota ? 'red' : 'green', 'alerta', num(sinReg) + ' · ' + num(sinNota), 'Clases sin registrar · módulos sin nota']];
        $('aca-kpis').innerHTML = k.map(function (x) { return '<div class="aca-kpi aca-kpi-' + x[0] + '"><div class="aca-kpi-icon">' + ICON[x[1]] + '</div><div><div class="aca-kpi-val">' + esc(x[2]) + '</div><div class="aca-kpi-lbl">' + esc(x[3]) + '</div></div></div>'; }).join('');
    }

    /* ── Tablas por pestaña ─────────────────────── */
    function textoGrupo(g) { var p = programa(g.programa_id), d = docente(g.docente_id); return corto(p.nombre) + ' ' + g.nombre + ' ' + nombreDoc(d) + ' ' + sede(g.sede_id).nombre; }
    var TABLAS = {
        grupos: {
            cols: ['Grupo', 'Sede', 'Docente', 'Jornada', 'Fechas', 'Avance', 'Estudiantes', 'Estado'], num: [6],
            datos: function (q, f) {
                return D.grupos.filter(function (g) {
                    if (!enSede(g)) return false;
                    if (f === 'vigentes' && g.estado === 'finalizado') return false;
                    if (f && f !== 'vigentes' && g.estado !== f) return false;
                    return !q || normal(textoGrupo(g)).indexOf(q) !== -1;
                }).sort(function (a, b) { var o = { en_clases: 0, por_iniciar: 1, finalizado: 2 }; return o[a.estado] - o[b.estado] || a.inicio.localeCompare(b.inicio); });
            },
            fila: function (g) {
                var p = programa(g.programa_id), d = docente(g.docente_id), mod = modulo(g);
                var avance = g.estado === 'en_clases' ? (mod ? '<div class="aca-muted aca-modulo">Módulo ' + (g.modulo_actual + 1) + ' de ' + p.modulos.length + ': ' + esc(mod.nombre) + '</div>' : '') + medidor(g.avance) : g.estado === 'por_iniciar' ? '<span class="aca-muted">Empieza el ' + esc(fechaMini(g.inicio)) + '</span>' : '<span class="aca-muted">Terminó</span>';
                return '<tr class="aca-row" tabindex="0" data-grupo="' + g.id + '"><td><div class="aca-strong">' + esc(corto(p.nombre)) + '</div><div class="aca-muted">' + esc(g.nombre) + '</div></td>' +
                    '<td>' + esc(sede(g.sede_id).nombre) + '</td><td>' + esc(nombreDoc(d)) + '</td><td>' + esc(g.jornada) + '</td>' +
                    '<td class="aca-num">' + esc(fechaMini(g.inicio)) + '<div class="aca-muted">a ' + esc(fechaMini(g.fin)) + '</div></td><td>' + avance + '</td>' +
                    '<td class="aca-num">' + (g.estado === 'finalizado' ? '—' : g.estudiantes + ' / ' + g.cupo) + '</td><td>' + tag(EST_G[g.estado]) + '</td></tr>';
            },
            csv: function (g) { var p = programa(g.programa_id); return [p.nombre, g.nombre, sede(g.sede_id).nombre, nombreDoc(docente(g.docente_id)), g.jornada, g.inicio, g.fin, EST_G[g.estado][0], g.estudiantes, g.cupo, g.estado === 'en_clases' && modulo(g) ? modulo(g).nombre : '']; },
            csvCols: ['Programa', 'Grupo', 'Sede', 'Docente', 'Jornada', 'Inicio', 'Fin', 'Estado', 'Estudiantes', 'Cupo', 'Módulo actual']
        },
        asistencia: {
            nota: 'La asistencia es informativa: no bloquea grados ni matrículas. El 80 % es solo una referencia.',
            cols: ['Grupo', 'Sede', 'Docente', 'Asistencia', 'Clases sin registrar', 'Bajo 80 %'], num: [4, 5],
            datos: function (q, f) {
                return D.grupos.filter(function (g) {
                    if (!enSede(g) || g.estado !== 'en_clases') return false;
                    if (f === 'sin_registrar' && !g.sin_registrar) return false;
                    if (f === 'bajo' && !bajo80(g)) return false;
                    return !q || normal(textoGrupo(g)).indexOf(q) !== -1;
                }).sort(function (a, b) { return (b.sin_registrar - a.sin_registrar) || ((asistenciaDe(a) == null ? 101 : asistenciaDe(a)) - (asistenciaDe(b) == null ? 101 : asistenciaDe(b))); });
            },
            fila: function (g) {
                var p = programa(g.programa_id), a = asistenciaDe(g), b = bajo80(g);
                return '<tr class="aca-row" tabindex="0" data-grupo="' + g.id + '"><td><div class="aca-strong">' + esc(corto(p.nombre)) + '</div><div class="aca-muted">' + esc(g.nombre + ' · ' + g.jornada) + '</div></td>' +
                    '<td>' + esc(sede(g.sede_id).nombre) + '</td><td>' + esc(nombreDoc(docente(g.docente_id))) + '</td><td>' + (a == null ? '<span class="aca-muted">Sin clases registradas</span>' : medidor(a, a < 80)) + '</td>' +
                    '<td class="aca-num' + (g.sin_registrar ? ' aca-mala' : '') + '">' + g.sin_registrar + '</td><td class="aca-num' + (b ? ' aca-mala' : '') + '">' + b + ' de ' + g.estudiantes + '</td></tr>';
            },
            csv: function (g) { return [corto(programa(g.programa_id).nombre), g.nombre, sede(g.sede_id).nombre, nombreDoc(docente(g.docente_id)), pctTxt(asistenciaDe(g)), g.sin_registrar, bajo80(g), g.estudiantes]; },
            csvCols: ['Programa', 'Grupo', 'Sede', 'Docente', 'Asistencia', 'Clases sin registrar', 'Estudiantes bajo 80 %', 'Estudiantes']
        },
        notas: {
            nota: 'Escala de 1,0 a 5,0; se aprueba con 3,0. Un módulo terminado sin nota es pendiente del docente.',
            cols: ['Grupo', 'Sede', 'Docente', 'Módulo actual', 'Promedio', 'Módulos sin nota', 'Por habilitar'], num: [4, 5, 6],
            datos: function (q, f) {
                return D.grupos.filter(function (g) {
                    if (!enSede(g) || g.estado !== 'en_clases') return false;
                    if (f === 'sin_nota' && !g.sin_nota) return false;
                    if (f === 'habilitar' && !porHabilitar(g)) return false;
                    return !q || normal(textoGrupo(g)).indexOf(q) !== -1;
                }).sort(function (a, b) { return (b.sin_nota - a.sin_nota) || (porHabilitar(b) - porHabilitar(a)); });
            },
            fila: function (g) {
                var p = programa(g.programa_id), pr = promedioDe(g), h = porHabilitar(g);
                return '<tr class="aca-row" tabindex="0" data-grupo="' + g.id + '"><td><div class="aca-strong">' + esc(corto(p.nombre)) + '</div><div class="aca-muted">' + esc(g.nombre) + '</div></td>' +
                    '<td>' + esc(sede(g.sede_id).nombre) + '</td><td>' + esc(nombreDoc(docente(g.docente_id))) + '</td>' +
                    '<td>' + (modulo(g) ? (g.modulo_actual + 1) + ' de ' + p.modulos.length + '<div class="aca-muted">' + esc(modulo(g).nombre) + '</div>' : '<span class="aca-muted">—</span>') + '</td>' +
                    '<td class="aca-num"><span class="' + (pr < 3.5 ? 'aca-mala' : 'aca-strong') + '">' + nota(pr) + '</span></td>' +
                    '<td class="aca-num' + (g.sin_nota ? ' aca-mala' : '') + '">' + g.sin_nota + '</td><td class="aca-num' + (h ? ' aca-mala' : '') + '">' + h + '</td></tr>';
            },
            csv: function (g) { var p = programa(g.programa_id); return [corto(p.nombre), g.nombre, sede(g.sede_id).nombre, nombreDoc(docente(g.docente_id)), modulo(g) ? (g.modulo_actual + 1) + ' de ' + p.modulos.length : '', nota(promedioDe(g)), g.sin_nota, porHabilitar(g)]; },
            csvCols: ['Programa', 'Grupo', 'Sede', 'Docente', 'Módulo actual', 'Promedio', 'Módulos sin nota', 'Estudiantes por habilitar']
        }
    };
    function pintarTabla() {
        var t = TABLAS[ptab];
        filas = t.datos(normal($('aca-buscar').value.trim()), cb.value());
        $('aca-head').innerHTML = '<tr>' + t.cols.map(function (c, i) { return '<th scope="col"' + (t.num.indexOf(i) >= 0 ? ' class="aca-num"' : '') + '>' + esc(c) + '</th>'; }).join('') + '</tr>';
        $('aca-nota').hidden = !t.nota; $('aca-nota').textContent = t.nota || '';
        var desde = (pagina - 1) * POR_PAGINA;
        $('aca-count').textContent = filas.length + (filas.length === 1 ? ' grupo' : ' grupos');
        $('aca-empty').hidden = filas.length > 0;
        $('aca-body').innerHTML = filas.slice(desde, desde + POR_PAGINA).map(t.fila).join('');
        paginar($('aca-paginacion'), filas.length, pagina, function (p) { pagina = p; pintarTabla(); });
    }
    $('aca-exportar').addEventListener('click', function () {
        var t = TABLAS[ptab];
        descargarCsv(ptab + (sel ? '-' + normal(sede(sel).nombre).replace(/\s+/g, '-') : ''), [t.csvCols].concat(filas.map(t.csv)));
    });

    /* ── Detalle ────────────────────────────────── */
    var modal = $('aca-modal'), volver = null, sedeDetalle = null;
    function abrir(titulo, sub, html, sedeId) {
        volver = document.activeElement; sedeDetalle = sedeId;
        $('aca-md-titulo').textContent = titulo; $('aca-md-sub').textContent = sub; $('aca-md-cuerpo').innerHTML = html;
        $('aca-md-entrar').hidden = !sedeId;
        modal.hidden = false; modal.querySelector('.aca-close').focus();
    }
    function cerrar() { if (modal.hidden) return; modal.hidden = true; if (volver && document.contains(volver)) volver.focus(); }
    modal.querySelectorAll('[data-cerrar-modal]').forEach(function (b) { b.addEventListener('click', cerrar); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !document.querySelector('.app-combobox.is-open')) cerrar(); });

    /* ── Entrar a la sede: grupos y horarios se manejan con las pantallas de la secretaría (EntrarSedeController) ── */
    function entrarASede(id, destino) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = '/general/sedes/' + encodeURIComponent(id) + '/entrar';
        [['_token', document.querySelector('meta[name="csrf-token"]').content], ['destino', destino || 'dashboard']].forEach(function (c) {
            var i = document.createElement('input'); i.type = 'hidden'; i.name = c[0]; i.value = c[1]; f.appendChild(i);
        });
        document.body.appendChild(f); f.submit();
    }
    $('aca-md-entrar').addEventListener('click', function () {
        this.disabled = true;
        entrarASede(sedeDetalle, 'cursos');
    });
    function info(f) { return '<div class="aca-info">' + f.map(function (x) { return '<div' + (x[2] ? ' class="aca-info-full"' : '') + '><span class="aca-info-lbl">' + esc(x[0]) + '</span><span class="aca-info-val">' + esc(x[1]) + '</span></div>'; }).join('') + '</div>'; }
    function abrirGrupo(id) {
        var g = D.grupos.find(function (x) { return x.id === id; }), p = programa(g.programa_id);
        var horas = 0;
        abrir(corto(p.nombre) + ' · ' + g.nombre, 'Sede ' + sede(g.sede_id).nombre + ' · ' + EST_G[g.estado][0],
            info([['Docente', nombreDoc(docente(g.docente_id))], ['Jornada', g.jornada], ['Inicio', fechaMini(g.inicio)], ['Fin', fechaMini(g.fin)],
                ['Estudiantes', g.estado === 'finalizado' ? '—' : g.estudiantes + ' de ' + g.cupo], ['Asistencia', pctTxt(asistenciaDe(g))],
                ['Promedio', nota(promedioDe(g))], ['Clases sin registrar', String(g.sin_registrar)]]) +
            '<h3 class="aca-seccion">Malla</h3><ol class="aca-malla">' + p.modulos.map(function (m, i) {
                horas += m.horas;
                var est2 = g.estado === 'finalizado' || (g.estado === 'en_clases' && g.modulo_actual != null && i < g.modulo_actual) ? ['Terminado', 'muted'] : g.estado === 'en_clases' && i === g.modulo_actual ? ['En curso', 'active'] : ['Pendiente', 'warn'];
                return '<li><span class="aca-malla-n">' + (i + 1) + '</span><span>' + esc(m.nombre) + '<span class="aca-muted"> · ' + m.horas + ' h</span></span>' + tag(est2) + '</li>';
            }).join('') + '</ol>', g.sede_id);
    }
    $('aca-body').addEventListener('click', function (e) {
        var g = e.target.closest('[data-grupo]');
        if (g) abrirGrupo(+g.dataset.grupo);
    });
    $('aca-body').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (e.target.matches('[data-grupo]')) { e.preventDefault(); abrirGrupo(+e.target.dataset.grupo); }
    });

    function pintar() {
        chips.querySelectorAll('.aca-chip').forEach(function (c) { var on = (c.dataset.sede || '') === sel; c.classList.toggle('is-active', on); c.setAttribute('aria-checked', on); });
        document.querySelectorAll('.aca-ptab').forEach(function (b) { var on = b.dataset.ptab === ptab; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on); });
        pintarKpis(); pintarTabla();
    }
    if (!TABLAS[ptab]) ptab = 'grupos';
    prepararFiltro();
    pintar();
}());
