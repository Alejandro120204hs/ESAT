/* ESAT — Admin: Horarios */
(function () {
    'use strict';

    var data = window.HOR_DATA || [];

    var DIAS_FULL = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    var DIA_COD   = { 'Lunes':'L','Martes':'M','Miércoles':'Mi','Jueves':'J','Viernes':'V','Sábado':'S' };
    var JORNADAS  = ['Mañana','Tarde','Noche'];
    var HORA_MIN  = 7;
    var HORA_MAX  = 22;

    /* ── Utilidades ── */
    function escClass(esc) {
        var m = { 'Salud':'sal','Cocina y Turismo':'tur','Administrativa':'adm',
                  'Educación e Idiomas':'edu','Deporte y Cultura':'dep','Ciencias':'cie','Belleza':'bel' };
        return m[esc] || 'adm';
    }

    function jornadaDeHora(hora) {
        var h = parseInt(hora.split(':')[0]);
        if (h < 12) return 'Mañana';
        if (h < 18) return 'Tarde';
        return 'Noche';
    }

    function jornadaClass(j) { return j.toLowerCase().replace('ñ', 'n'); }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function toMin(t) { var p = t.split(':'); return parseInt(p[0]) * 60 + parseInt(p[1]); }

    function fmtDuracion(min) {
        var h = Math.floor(min / 60), m = min % 60;
        if (!h) return m + ' min';
        return m ? h + 'h ' + m + 'm' : h + 'h';
    }

    function esc(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }

    function ordenarSesiones(ses) {
        return ses.slice().sort(function (a, b) {
            return DIAS_FULL.indexOf(a.dia) - DIAS_FULL.indexOf(b.dia) || toMin(a.hora_inicio) - toMin(b.hora_inicio);
        });
    }

    /* Jornadas presentes en las sesiones, en orden Mañana → Noche */
    function jornadasDe(sesiones) {
        var set = {};
        sesiones.forEach(function (s) { set[jornadaDeHora(s.hora_inicio)] = true; });
        return JORNADAS.filter(function (j) { return set[j]; });
    }

    function textoJornadas(js) {
        if (!js.length) return '';
        var low = js.map(function (j, i) { return i ? j.toLowerCase() : j; });
        if (low.length === 1) return low[0];
        return low.slice(0, -1).join(', ') + ' y ' + low[low.length - 1];
    }

    function minutosSemana(sesiones) {
        return sesiones.reduce(function (t, s) { return t + (toMin(s.hora_fin) - toMin(s.hora_inicio)); }, 0);
    }

    /* Agrupa sesiones con el mismo rango de horas: [{dias:[...], hi, hf}] */
    function tramosDe(sesiones) {
        var tramos = [];
        ordenarSesiones(sesiones).forEach(function (s) {
            var t = tramos.find(function (x) { return x.hi === s.hora_inicio && x.hf === s.hora_fin; });
            if (t) t.dias.push(s.dia);
            else tramos.push({ dias: [s.dia], hi: s.hora_inicio, hf: s.hora_fin });
        });
        return tramos;
    }

    /* ── Combobox ──
       Panel con position: fixed, calculado contra el viewport: abre hacia abajo
       si cabe y, si no, hacia arriba (se decide en cada apertura). Así nunca
       queda cortado dentro del modal ni se sale de la pantalla. */
    var MARGEN_VIEWPORT = 12;

    function initCombobox(root) {
        if (!root) return;
        var trigger = root.querySelector('[data-combobox-trigger]');
        var panel   = root.querySelector('[data-combobox-panel]');
        var list    = root.querySelector('[data-combobox-list]');
        var hidden  = root.querySelector('[data-combobox-value]');
        var lbl     = root.querySelector('[data-combobox-label]');
        var placeholder = lbl.textContent;
        var maxLista    = parseInt(root.dataset.listMaxHeight) || 220;

        function posicionar() {
            var rect   = trigger.getBoundingClientRect();
            var abajo  = window.innerHeight - rect.bottom - 6 - MARGEN_VIEWPORT;
            var arriba = rect.top - 6 - MARGEN_VIEWPORT;
            var necesario = Math.min(list.scrollHeight, maxLista) + 18;
            var haciaArriba = abajo < necesario && arriba > abajo;
            var espacio = haciaArriba ? arriba : abajo;

            panel.style.left  = rect.left + 'px';
            panel.style.width = Math.max(rect.width, 180) + 'px';
            panel.style.top    = haciaArriba ? '' : (rect.bottom + 6) + 'px';
            panel.style.bottom = haciaArriba ? (window.innerHeight - rect.top + 6) + 'px' : '';
            list.style.maxHeight = Math.max(Math.min(maxLista, espacio - 18), 60) + 'px';
            root.classList.toggle('is-up', haciaArriba);
        }

        function open() {
            panel.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            posicionar();
            window.addEventListener('scroll', posicionar, true);
            window.addEventListener('resize', posicionar);
        }
        function close() {
            if (panel.hidden) return;
            panel.hidden = true;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            window.removeEventListener('scroll', posicionar, true);
            window.removeEventListener('resize', posicionar);
        }
        root.close = close;

        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function () { panel.hidden ? open() : close(); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

        root.setOptions = function (opts) {
            list.innerHTML = '';
            opts.forEach(function (o) {
                var val = typeof o === 'object' ? o.value : o;
                var lab = typeof o === 'object' ? o.label : o;
                var li  = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = val; li.dataset.label = lab;
                var span = document.createElement('span'); span.textContent = lab;
                var svg  = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                li.appendChild(span); li.appendChild(svg);
                li.addEventListener('click', function () { root.setValue(val, lab); close(); });
                list.appendChild(li);
            });
        };

        root.setValue = function (val, lab, silencioso) {
            hidden.value = val || '';
            lbl.textContent = lab || val || placeholder;
            list.querySelectorAll('li').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === val);
            });
            if (!silencioso) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };

        root.reset = function () { hidden.value = ''; lbl.textContent = placeholder; list.querySelectorAll('li').forEach(function(l){ l.classList.remove('is-selected'); }); };
    }

    /* ── Estado filtros ── */
    var filtroEscuela = '';
    var filtroJornada = '';
    var filtroTexto   = '';

    /* ── Referencias DOM ── */
    var viewTabla  = document.getElementById('hor-view-tabla');
    var viewGrilla = document.getElementById('hor-view-grilla');
    var btnTabla   = document.getElementById('hor-btn-tabla');
    var btnGrilla  = document.getElementById('hor-btn-grilla');
    var tbodyEl    = document.getElementById('hor-tbody');
    var emptyEl    = document.getElementById('hor-empty');
    var countBadge = document.getElementById('hor-count-badge');
    var searchInp  = document.getElementById('hor-search');
    var cbEscuela  = document.getElementById('hor-cb-escuela');
    var cbJornada  = document.getElementById('hor-cb-jornada');
    var hidEsc     = document.getElementById('hor-val-escuela');
    var hidJor     = document.getElementById('hor-val-jornada');
    var grillaEl   = document.getElementById('hor-grilla');

    function coincide(g) {
        var matchEsc = !filtroEscuela || g.escuela === filtroEscuela;
        var matchJor = !filtroJornada || jornadasDe(g.sesiones).indexOf(filtroJornada) >= 0;
        var matchTxt = !filtroTexto ||
            (g.grupo + ' ' + g.codigo).toLowerCase().indexOf(filtroTexto) >= 0 ||
            g.programa.toLowerCase().indexOf(filtroTexto) >= 0 ||
            g.docente.toLowerCase().indexOf(filtroTexto) >= 0;
        return matchEsc && matchJor && matchTxt;
    }

    /* ── Tabla ── */
    function filaHTML(g) {
        var diasActivos = {};
        g.sesiones.forEach(function (s) { diasActivos[s.dia] = true; });

        var chips = DIAS_FULL.map(function (d) {
            return '<span class="hor-dia-chip ' + (diasActivos[d] ? 'activo' : '') + '" title="' + d + '">' + DIA_COD[d] + '</span>';
        }).join('');

        var tramos = tramosDe(g.sesiones);
        var horario;
        if (tramos.length === 1) {
            horario =
                '<span class="hor-hora-rng">' + tramos[0].hi + ' – ' + tramos[0].hf + '</span>' +
                '<span class="hor-duracion">' + fmtDuracion(toMin(tramos[0].hf) - toMin(tramos[0].hi)) + ' por sesión</span>';
        } else {
            horario = tramos.map(function (t) {
                return '<span class="hor-tramo">' +
                    '<span class="hor-tramo-dias">' + t.dias.map(function (d) { return DIA_COD[d]; }).join(' · ') + '</span>' +
                    '<span class="hor-hora-rng">' + t.hi + ' – ' + t.hf + '</span>' +
                '</span>';
            }).join('') +
            '<span class="hor-duracion">' + fmtDuracion(minutosSemana(g.sesiones)) + ' semanales</span>';
        }

        var jornadas = jornadasDe(g.sesiones).map(function (j) {
            return '<span class="hor-jornada-tag hor-jornada-' + jornadaClass(j) + '">' + j + '</span>';
        }).join('');

        var programa = g.programa.length > 42 ? g.programa.slice(0, 42).trim() + '...' : g.programa;

        return '<tr class="hor-row" data-id="' + g.id + '">' +
            '<td><div class="hor-cell-grupo">' +
                '<span class="hor-grupo-txt">' + esc(g.grupo) + '</span>' +
                '<span class="hor-codigo-txt">' + esc(g.codigo) + '</span>' +
            '</div></td>' +
            '<td><div class="hor-cell-programa">' +
                '<span class="hor-programa-txt" title="' + esc(g.programa) + '">' + esc(programa) + '</span>' +
                '<span class="hor-esc-tag hor-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>' +
            '</div></td>' +
            '<td><div class="hor-dias-wrap">' + chips + '</div></td>' +
            '<td><div class="hor-horario-cell">' + horario + '</div></td>' +
            '<td><div class="hor-jornadas-wrap">' + jornadas + '</div></td>' +
            '<td class="hor-docente-cell">' + esc(g.docente) + '</td>' +
            '<td>' +
                '<button class="hor-action-btn hor-btn-edit" type="button" data-id="' + g.id + '" title="Editar" aria-label="Editar horario de ' + esc(g.grupo + ' ' + g.codigo) + '">' +
                    '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                '</button>' +
            '</td>' +
        '</tr>';
    }

    function renderTabla() {
        if (!tbodyEl) return;
        tbodyEl.innerHTML = data.map(filaHTML).join('');
        applyFilters();
    }

    function applyFilters() {
        if (!tbodyEl) return;
        var visible = 0;

        data.forEach(function (g) {
            var row = tbodyEl.querySelector('.hor-row[data-id="' + g.id + '"]');
            if (!row) return;
            var show = coincide(g);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (emptyEl)    emptyEl.style.display = visible === 0 ? 'flex' : 'none';
        if (countBadge) countBadge.textContent = visible + ' horario' + (visible !== 1 ? 's' : '');
        if (vistaActual === 'grilla') buildGrilla();
    }

    /* ── KPIs ── */
    function renderKpis() {
        var cuenta = { 'Mañana': 0, 'Tarde': 0, 'Noche': 0 };
        data.forEach(function (g) { jornadasDe(g.sesiones).forEach(function (j) { cuenta[j]++; }); });
        var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
        set('hor-kpi-total',  data.length);
        set('hor-kpi-manana', cuenta['Mañana']);
        set('hor-kpi-tarde',  cuenta['Tarde']);
        set('hor-kpi-noche',  cuenta['Noche']);
    }

    /* ── Comboboxes filtro ── */
    if (cbEscuela) {
        initCombobox(cbEscuela);
        var escuelas = [];
        data.forEach(function (g) { if (g.escuela && escuelas.indexOf(g.escuela) < 0) escuelas.push(g.escuela); });
        cbEscuela.setOptions([{value:'',label:'Todas las escuelas'}].concat(escuelas.map(function(e){ return {value:e,label:e}; })));
        cbEscuela.setValue('', 'Todas las escuelas');
        if (hidEsc) hidEsc.addEventListener('change', function () { filtroEscuela = hidEsc.value; applyFilters(); });
    }

    if (cbJornada) {
        initCombobox(cbJornada);
        cbJornada.setOptions([
            {value:'',label:'Todas las jornadas'},
            {value:'Mañana',label:'Mañana'},
            {value:'Tarde',label:'Tarde'},
            {value:'Noche',label:'Noche'},
        ]);
        cbJornada.setValue('', 'Todas las jornadas');
        if (hidJor) hidJor.addEventListener('change', function () { filtroJornada = hidJor.value; applyFilters(); });
    }

    if (searchInp) {
        searchInp.addEventListener('input', function () {
            filtroTexto = searchInp.value.toLowerCase().trim();
            applyFilters();
        });
    }

    /* ── Toggle vista ── */
    var vistaActual = 'tabla';

    function switchVista(v) {
        vistaActual = v;
        if (v === 'tabla') {
            viewTabla.style.display  = '';
            viewGrilla.style.display = 'none';
            btnTabla.classList.add('is-active');
            btnGrilla.classList.remove('is-active');
        } else {
            viewTabla.style.display  = 'none';
            viewGrilla.style.display = '';
            btnTabla.classList.remove('is-active');
            btnGrilla.classList.add('is-active');
            buildGrilla();
        }
    }

    if (btnTabla)  btnTabla.addEventListener('click',  function () { switchVista('tabla'); });
    if (btnGrilla) btnGrilla.addEventListener('click', function () { switchVista('grilla'); });

    /* ── Vista Horario ──
       Horario clásico (días en columnas, horas en filas) de UN grupo o de UN docente,
       como un horario impreso. Solo muestra el rango de horas que se usa, así cabe sin
       scroll propio. Cada sesión es un bloque de su hora inicio a su hora fin. */
    var HORA_PX       = 56;
    var RANGO_MIN_H   = 6;      // alto mínimo del horario, en horas
    var calModo       = 'grupo';
    var calSel        = '*';    // '*' = todos (horario general)
    var JORNADA_RANGO = { 'Mañana': '07:00 – 12:00', 'Tarde': '12:00 – 18:00', 'Noche': '18:00 – 22:00' };
    var cbVer         = document.getElementById('hor-cb-ver');
    var hidVer        = document.getElementById('hor-val-ver');
    var calInfoEl     = document.getElementById('hor-cal-info');
    var calEmptyEl    = document.getElementById('hor-cal-empty');

    function programaCorto(g) { return g.programa.replace('Técnico Laboral en ', ''); }

    /* Opciones del selector según el modo y los filtros de la barra */
    function opcionesVer() {
        var visibles = data.filter(coincide);
        var opts;
        if (calModo === 'grupo') {
            opts = visibles.map(function (g) {
                return { value: String(g.id), label: g.codigo + ' — ' + programaCorto(g) + ' (' + g.grupo + ')' };
            });
        } else {
            var docentes = [];
            visibles.forEach(function (g) { if (docentes.indexOf(g.docente) < 0) docentes.push(g.docente); });
            opts = docentes.sort().map(function (d) { return { value: d, label: d }; });
        }
        if (opts.length) opts.unshift({ value: '*', label: calModo === 'grupo' ? 'Todos los grupos' : 'Todos los docentes' });
        return opts;
    }

    /* Sesiones a pintar: [{g, s, ini, fin}] */
    function sesionesVer() {
        var grupos = calSel === '*'
            ? data.filter(coincide)
            : calModo === 'grupo'
                ? data.filter(function (g) { return String(g.id) === calSel; })
                : data.filter(function (g) { return g.docente === calSel && coincide(g); });
        var out = [];
        grupos.forEach(function (g) {
            g.sesiones.forEach(function (s) {
                // En el horario general el filtro de jornada aplica a cada clase, no solo al grupo
                if (calSel === '*' && filtroJornada && jornadaDeHora(s.hora_inicio) !== filtroJornada) return;
                out.push({ g: g, s: s, ini: toMin(s.hora_inicio), fin: toMin(s.hora_fin) });
            });
        });
        return { grupos: grupos, sesiones: out };
    }

    /* Carril por sesión, por si dos sesiones del mismo día se cruzan */
    function carriles(eventos) {
        eventos.sort(function (a, b) { return a.ini - b.ini || b.fin - a.fin; });
        var finCarril = [];
        eventos.forEach(function (e) {
            var c = 0;
            while (finCarril[c] !== undefined && finCarril[c] > e.ini) c++;
            e.carril = c;
            finCarril[c] = e.fin;
        });
        eventos.forEach(function (e) { e.carriles = finCarril.length; });
    }

    function stat(valor, etiqueta) {
        return '<div class="hor-cal-stat"><span class="hor-cal-stat-val">' + valor + '</span><span class="hor-cal-stat-lbl">' + etiqueta + '</span></div>';
    }

    function pintarInfo(sel) {
        if (!calInfoEl) return;
        var ses = sel.sesiones;
        var min = ses.reduce(function (t, e) { return t + (e.fin - e.ini); }, 0);
        var dias = {};
        ses.forEach(function (e) { dias[e.s.dia] = true; });
        var nDias = Object.keys(dias).length;

        var titulo, sub, extra, kicker;
        if (calSel === '*') {
            kicker = 'Horario general';
            titulo = calModo === 'grupo' ? 'Todos los grupos' : 'Todos los docentes';
            var escuelas = [];
            sel.grupos.forEach(function (g) { if (escuelas.indexOf(g.escuela) < 0) escuelas.push(g.escuela); });
            sub = escuelas.map(function (e) { return '<span class="hor-esc-tag hor-esc-' + escClass(e) + '">' + esc(e) + '</span>'; }).join('');
            var docs = [];
            sel.grupos.forEach(function (g) { if (docs.indexOf(g.docente) < 0) docs.push(g.docente); });
            extra = stat(sel.grupos.length, sel.grupos.length === 1 ? 'Grupo' : 'Grupos') +
                    stat(docs.length, docs.length === 1 ? 'Docente' : 'Docentes');
        } else if (calModo === 'grupo') {
            kicker = 'Horario del grupo';
            var g = sel.grupos[0];
            titulo = esc(g.grupo) + ' <span class="hor-cal-info-cod">' + esc(g.codigo) + '</span>';
            sub = '<span>' + esc(g.programa) + '</span><span class="hor-esc-tag hor-esc-' + escClass(g.escuela) + '">' + esc(g.escuela) + '</span>';
            extra = stat(esc(g.docente), 'Docente');
        } else {
            kicker = 'Horario del docente';
            titulo = esc(calSel);
            var progs = [];
            sel.grupos.forEach(function (g) { if (progs.indexOf(programaCorto(g)) < 0) progs.push(programaCorto(g)); });
            sub = '<span>' + esc(progs.join(' · ')) + '</span>';
            extra = stat(sel.grupos.length, sel.grupos.length === 1 ? 'Grupo' : 'Grupos');
        }

        calInfoEl.innerHTML =
            '<div class="hor-cal-info-main">' +
                '<span class="hor-cal-info-kicker">' + kicker + '</span>' +
                '<h3 class="hor-cal-info-titulo">' + titulo + '</h3>' +
                '<div class="hor-cal-info-sub">' + sub + '</div>' +
            '</div>' +
            '<div class="hor-cal-stats">' +
                stat(fmtDuracion(min), 'Semanales') +
                stat(ses.length, ses.length === 1 ? 'Sesión' : 'Sesiones') +
                (calSel === '*' ? '' : stat(nDias, nDias === 1 ? 'Día' : 'Días')) +
                extra +
            '</div>';
    }

    function buildGrilla() {
        if (!grillaEl || !cbVer) return;

        var opts = opcionesVer();
        cbVer.setOptions(opts);
        var vacio = !opts.length;
        if (calEmptyEl) calEmptyEl.hidden = !vacio;
        grillaEl.hidden = vacio;
        if (calInfoEl) calInfoEl.hidden = vacio;
        if (vacio) { calSel = ''; cbVer.reset(); return; }

        if (!opts.some(function (o) { return o.value === calSel; })) calSel = opts[0].value;
        var actual = opts.find(function (o) { return o.value === calSel; });
        cbVer.setValue(actual.value, actual.label, true);

        var sel = sesionesVer();
        pintarInfo(sel);
        grillaEl.classList.toggle('is-general', calSel === '*');
        if (calSel === '*') { pintarGeneral(sel); return; }

        // Rango de horas: solo el que usa el horario (mínimo RANGO_MIN_H horas)
        var hIni = Math.floor(Math.min.apply(null, sel.sesiones.map(function (e) { return e.ini; })) / 60);
        var hFin = Math.ceil(Math.max.apply(null, sel.sesiones.map(function (e) { return e.fin; })) / 60);
        while (hFin - hIni < RANGO_MIN_H) {
            if (hFin < HORA_MAX) hFin++;
            if (hFin - hIni < RANGO_MIN_H && hIni > HORA_MIN) hIni--;
            if (hIni === HORA_MIN && hFin === HORA_MAX) break;
        }
        var alto   = (hFin - hIni) * HORA_PX;
        var hoyIdx = new Date().getDay() - 1;

        var porDia = DIAS_FULL.map(function () { return []; });
        sel.sesiones.forEach(function (e) {
            var d = DIAS_FULL.indexOf(e.s.dia);
            if (d >= 0) porDia[d].push(e);
        });

        var html = '<div class="hor-cal-corner"></div>';
        DIAS_FULL.forEach(function (d, i) {
            html += '<div class="hor-cal-head' + (i === hoyIdx ? ' is-hoy' : '') + (porDia[i].length ? '' : ' is-libre') + '">' +
                d + (i === hoyIdx ? '<span class="hor-cal-hoy">Hoy</span>' : '') + '</div>';
        });

        // Columna de horas con inicio de cada jornada
        html += '<div class="hor-cal-horas" style="height:' + alto + 'px">';
        for (var h = hIni; h <= hFin; h++) {
            var jor = h === 12 ? 'Tarde' : (h === 18 ? 'Noche' : (h === hIni ? jornadaDeHora(pad(h) + ':00') : ''));
            html += '<span class="hor-cal-hora" style="top:' + ((h - hIni) * HORA_PX) + 'px">' + pad(h) + ':00' +
                (jor && h < hFin ? '<em class="hor-cal-jor hor-cal-jor-' + jornadaClass(jor) + '">' + jor + '</em>' : '') + '</span>';
        }
        html += '</div>';

        var ahora = new Date(), ahoraMin = ahora.getHours() * 60 + ahora.getMinutes();

        DIAS_FULL.forEach(function (d, i) {
            html += '<div class="hor-cal-col' + (i === hoyIdx ? ' is-hoy' : '') + '" style="height:' + alto + 'px">';
            [12, 18].forEach(function (hj) {
                if (hj > hIni && hj < hFin) html += '<span class="hor-cal-sep" style="top:' + ((hj - hIni) * HORA_PX) + 'px"></span>';
            });

            carriles(porDia[i]);
            porDia[i].forEach(function (e) {
                var top   = (e.ini - hIni * 60) / 60 * HORA_PX;
                var altoB = (e.fin - e.ini) / 60 * HORA_PX;
                var ancho = 100 / e.carriles;
                var linea = calModo === 'grupo' ? e.g.docente : e.g.grupo + ' · ' + e.g.codigo;
                var titulo = d + ' ' + e.s.hora_inicio + '–' + e.s.hora_fin + ' · ' + programaCorto(e.g) + ' · ' + e.g.grupo + ' (' + e.g.codigo + ') · ' + e.g.docente;

                html += '<div class="hor-bloque hor-c-' + escClass(e.g.escuela) + (altoB < 90 ? ' is-corto' : '') + '" data-id="' + e.g.id + '" role="button" tabindex="0"' +
                    ' title="' + esc(titulo) + '" aria-label="' + esc(titulo) + '"' +
                    ' style="top:' + (top + 3) + 'px;height:' + (altoB - 6) + 'px;left:calc(' + (e.carril * ancho) + '% + 5px);width:calc(' + ancho + '% - 10px)">' +
                        '<span class="hor-bloque-hora">' + e.s.hora_inicio + ' – ' + e.s.hora_fin + '<span class="hor-bloque-dur">' + fmtDuracion(e.fin - e.ini) + '</span></span>' +
                        '<span class="hor-bloque-nombre">' + esc(programaCorto(e.g)) + '</span>' +
                        '<span class="hor-bloque-meta">' + esc(linea) + '</span>' +
                    '</div>';
            });

            if (i === hoyIdx && ahoraMin > hIni * 60 && ahoraMin < hFin * 60) {
                html += '<span class="hor-cal-ahora" style="top:' + ((ahoraMin - hIni * 60) / 60 * HORA_PX) + 'px"></span>';
            }
            html += '</div>';
        });

        grillaEl.innerHTML = html;
    }

    /* Horario general: tabla jornada × día; en cada celda las clases de esa
       jornada ordenadas por hora (con muchos grupos simultáneos, bloques del
       alto de la hora quedarían ilegibles). */
    function pintarGeneral(sel) {
        var hoyIdx = new Date().getDay() - 1;
        var celdas = {};   // 'Mañana|0' -> [sesiones]
        sel.sesiones.forEach(function (e) {
            var k = jornadaDeHora(e.s.hora_inicio) + '|' + DIAS_FULL.indexOf(e.s.dia);
            (celdas[k] = celdas[k] || []).push(e);
        });
        var jornadas = JORNADAS.filter(function (j) {
            return DIAS_FULL.some(function (d, i) { return celdas[j + '|' + i]; });
        });

        var html = '<div class="hor-cal-corner"></div>';
        DIAS_FULL.forEach(function (d, i) {
            var n = sel.sesiones.filter(function (e) { return e.s.dia === d; }).length;
            html += '<div class="hor-cal-head' + (i === hoyIdx ? ' is-hoy' : '') + (n ? '' : ' is-libre') + '">' +
                d + (i === hoyIdx ? '<span class="hor-cal-hoy">Hoy</span>' : '') + '</div>';
        });

        jornadas.forEach(function (j) {
            html += '<div class="hor-cal-fila-jor hor-cal-jor-' + jornadaClass(j) + '">' +
                '<span class="hor-cal-fila-jor-nombre">' + j + '</span>' +
                '<span class="hor-cal-fila-jor-rango">' + JORNADA_RANGO[j] + '</span>' +
            '</div>';
            DIAS_FULL.forEach(function (d, i) {
                var evs = (celdas[j + '|' + i] || []).sort(function (a, b) { return a.ini - b.ini || a.fin - b.fin; });
                html += '<div class="hor-cal-celda' + (i === hoyIdx ? ' is-hoy' : '') + '">';
                if (!evs.length) html += '<span class="hor-cal-celda-vacia" aria-hidden="true">—</span>';
                evs.forEach(function (e) {
                    var titulo = d + ' ' + e.s.hora_inicio + '–' + e.s.hora_fin + ' · ' + programaCorto(e.g) + ' · ' + e.g.grupo + ' (' + e.g.codigo + ') · ' + e.g.docente;
                    html += '<div class="hor-bloque is-lista hor-c-' + escClass(e.g.escuela) + '" data-id="' + e.g.id + '" role="button" tabindex="0"' +
                        ' title="' + esc(titulo) + '" aria-label="' + esc(titulo) + '">' +
                            '<span class="hor-bloque-hora">' + e.s.hora_inicio + ' – ' + e.s.hora_fin + '<span class="hor-bloque-dur">' + fmtDuracion(e.fin - e.ini) + '</span></span>' +
                            '<span class="hor-bloque-nombre">' + esc(programaCorto(e.g)) + '</span>' +
                            '<span class="hor-bloque-meta">' + esc(e.g.grupo) + ' · <span class="hor-nowrap">' + esc(e.g.codigo) + '</span></span>' +
                            '<span class="hor-bloque-meta">' + esc(e.g.docente) + '</span>' +
                        '</div>';
                });
                html += '</div>';
            });
        });

        grillaEl.innerHTML = html;
    }

    /* Controles: modo, selector y flechas */
    if (cbVer) {
        initCombobox(cbVer);
        hidVer.addEventListener('change', function () {
            if (!hidVer.value || hidVer.value === calSel) return;
            calSel = hidVer.value;
            buildGrilla();
        });
    }

    document.querySelectorAll('.hor-cal-modo-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.modo === calModo) return;
            // Al cambiar de modo, conservar el contexto: grupo → su docente, docente → su primer grupo
            var g = calModo === 'grupo'
                ? data.find(function (x) { return String(x.id) === calSel; })
                : data.find(function (x) { return x.docente === calSel && coincide(x); });
            var todos = calSel === '*';
            calModo = btn.dataset.modo;
            calSel  = todos ? '*' : (g ? (calModo === 'grupo' ? String(g.id) : g.docente) : '*');
            document.querySelectorAll('.hor-cal-modo-btn').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
                b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
            });
            buildGrilla();
        });
    });

    function moverSel(paso) {
        var opts = opcionesVer();
        if (!opts.length) return;
        var i = opts.findIndex(function (o) { return o.value === calSel; });
        calSel = opts[(i + paso + opts.length) % opts.length].value;
        buildGrilla();
    }
    var btnPrev = document.getElementById('hor-cal-prev');
    var btnNext = document.getElementById('hor-cal-next');
    if (btnPrev) btnPrev.addEventListener('click', function () { moverSel(-1); });
    if (btnNext) btnNext.addEventListener('click', function () { moverSel(1); });

    // Teclado: Enter o Espacio sobre un bloque abre su edición
    if (grillaEl) {
        grillaEl.addEventListener('keydown', function (e) {
            var blk = e.target.closest('.hor-bloque');
            if (!blk || (e.key !== 'Enter' && e.key !== ' ')) return;
            e.preventDefault();
            openModal(parseInt(blk.dataset.id));
        });
    }

    /* ══ Modal ══ */
    var overlay     = document.getElementById('hor-modal-overlay');
    var modalClose  = document.getElementById('hor-modal-close');
    var modalCancel = document.getElementById('hor-modal-cancel');
    var btnNuevo    = document.getElementById('hor-btn-nuevo');
    var modalTitle  = document.getElementById('hor-modal-title');

    var cbGrupoModal   = document.getElementById('hor-cb-grupo-modal');
    var hidGrupoModal  = document.getElementById('hor-val-grupo-modal');
    var cbDocenteModal = document.getElementById('hor-cb-docente-modal');
    var hidDocente     = document.getElementById('hor-val-docente-modal');
    var inpPrograma    = document.getElementById('hor-inp-programa');
    var inpEscuela     = document.getElementById('hor-inp-escuela');
    var inpHInicio     = document.getElementById('hor-inp-hinicio');
    var inpHFin        = document.getElementById('hor-inp-hfin');
    var inpJornada     = document.getElementById('hor-inp-jornada');
    var chkMismo       = document.getElementById('hor-mismo-horario');
    var boxComunes     = document.getElementById('hor-horas-comunes');
    var boxPorDia      = document.getElementById('hor-horas-por-dia');
    var sesListEl      = document.getElementById('hor-ses-list');
    var sesVacioEl     = document.getElementById('hor-ses-vacio');
    var resumenEl      = document.getElementById('hor-ses-resumen');
    var errorEl        = document.getElementById('hor-form-error');
    var diaCbs         = document.querySelectorAll('.hor-dia-cb');

    /* Horas por día mientras se edita: { 'Lunes': {hi, hf}, ... }.
       Se conservan aunque se desmarque el día, por si se vuelve a marcar. */
    var horasDia  = {};
    var editingId = null;
    /* Si las horas comunes se tocaron con el interruptor encendido, al apagarlo
       se copian a todos los días; si no, se recuperan las horas que tenía cada día. */
    var comunesEditadas = false;

    function diasMarcados() {
        var out = [];
        diaCbs.forEach(function (cb) { if (cb.checked) out.push(cb.value); });
        return out;
    }

    /* Sesiones según el estado actual del formulario */
    function sesionesForm() {
        return diasMarcados().map(function (d) {
            var h = chkMismo.checked ? { hi: inpHInicio.value, hf: inpHFin.value } : horasDia[d];
            return { dia: d, hora_inicio: h.hi, hora_fin: h.hf };
        });
    }

    function sesionValida(s) {
        return s.hora_inicio && s.hora_fin && toMin(s.hora_fin) > toMin(s.hora_inicio);
    }

    function renderSesList() {
        var dias = diasMarcados();
        sesListEl.innerHTML = '';
        sesVacioEl.hidden = dias.length > 0;

        dias.forEach(function (d) {
            var h = horasDia[d];
            var row = document.createElement('div');
            row.className = 'hor-ses-row';
            row.dataset.dia = d;
            row.innerHTML =
                '<span class="hor-ses-dia">' + d + '</span>' +
                '<input class="hor-form-input hor-ses-time" type="time" data-campo="hi" value="' + h.hi + '" aria-label="Hora inicio ' + d + '">' +
                '<span class="hor-ses-sep">a</span>' +
                '<input class="hor-form-input hor-ses-time" type="time" data-campo="hf" value="' + h.hf + '" aria-label="Hora fin ' + d + '">' +
                '<span class="hor-ses-info"></span>';
            sesListEl.appendChild(row);
            pintarInfoFila(row);
        });
    }

    function pintarInfoFila(row) {
        var h = horasDia[row.dataset.dia];
        var info = row.querySelector('.hor-ses-info');
        var ok = h.hi && h.hf && toMin(h.hf) > toMin(h.hi);
        info.textContent = ok ? fmtDuracion(toMin(h.hf) - toMin(h.hi)) + ' · ' + jornadaDeHora(h.hi) : 'Revisar horas';
        info.classList.toggle('is-error', !ok);
    }

    function actualizarResumen() {
        var ses = sesionesForm();
        var validas = ses.filter(sesionValida);
        inpJornada.value = textoJornadas(jornadasDe(validas));

        if (!ses.length) { resumenEl.textContent = ''; return; }
        resumenEl.textContent = ses.length + (ses.length === 1 ? ' sesión' : ' sesiones') +
            ' a la semana · ' + fmtDuracion(minutosSemana(validas)) + ' de clase';
    }

    function setModo(mismo) {
        chkMismo.checked = mismo;
        boxComunes.hidden = !mismo;
        boxPorDia.hidden  = mismo;
        if (!mismo) renderSesList();
        actualizarResumen();
    }

    /* Días: al marcar uno nuevo, hereda las horas del último día marcado */
    diaCbs.forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (cb.checked && !horasDia[cb.value]) {
                var previos = diasMarcados().filter(function (d) { return d !== cb.value && horasDia[d]; });
                var base = previos.length ? horasDia[previos[previos.length - 1]] : { hi: inpHInicio.value, hf: inpHFin.value };
                horasDia[cb.value] = { hi: base.hi, hf: base.hf };
            }
            if (!chkMismo.checked) renderSesList();
            actualizarResumen();
            limpiarError();
        });
    });

    /* Interruptor "mismo horario": al encenderlo, las horas comunes toman las del
       primer día marcado; al apagarlo, ver comunesEditadas. */
    chkMismo.addEventListener('change', function () {
        var dias = diasMarcados();
        if (chkMismo.checked) {
            if (dias.length && horasDia[dias[0]]) {
                inpHInicio.value = horasDia[dias[0]].hi;
                inpHFin.value    = horasDia[dias[0]].hf;
            }
            comunesEditadas = false;
        } else {
            dias.forEach(function (d) {
                if (comunesEditadas || !horasDia[d]) horasDia[d] = { hi: inpHInicio.value, hf: inpHFin.value };
            });
        }
        setModo(chkMismo.checked);
        limpiarError();
    });

    sesListEl.addEventListener('input', function (e) {
        var inp = e.target.closest('.hor-ses-time');
        if (!inp) return;
        var row = inp.closest('.hor-ses-row');
        horasDia[row.dataset.dia][inp.dataset.campo] = inp.value;
        row.classList.remove('is-invalid');
        pintarInfoFila(row);
        actualizarResumen();
    });

    [inpHInicio, inpHFin].forEach(function (inp) {
        inp.addEventListener('input', function () {
            comunesEditadas = true;
            inp.classList.remove('is-invalid');
            actualizarResumen();
        });
    });

    /* ── Errores ── */
    function mostrarError(msg) { errorEl.textContent = msg; errorEl.hidden = false; }
    function limpiarError() {
        errorEl.hidden = true; errorEl.textContent = '';
        overlay.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    }

    function marcarInvalida(dia) {
        if (chkMismo.checked) {
            inpHInicio.classList.add('is-invalid');
            inpHFin.classList.add('is-invalid');
        } else {
            var row = sesListEl.querySelector('.hor-ses-row[data-dia="' + dia + '"]');
            if (row) row.classList.add('is-invalid');
        }
    }

    function validar(grupoId, docente, ses) {
        if (!grupoId) return 'Selecciona el grupo.';
        if (!ses.length) return 'Marca al menos un día de clase.';

        for (var i = 0; i < ses.length; i++) {
            var s = ses[i];
            if (!sesionValida(s)) {
                marcarInvalida(s.dia);
                return 'El ' + s.dia.toLowerCase() + ': la hora fin debe ser posterior a la hora inicio.';
            }
            if (toMin(s.hora_inicio) < HORA_MIN * 60 || toMin(s.hora_fin) > HORA_MAX * 60) {
                marcarInvalida(s.dia);
                return 'El ' + s.dia.toLowerCase() + ': las clases deben estar entre las ' + pad(HORA_MIN) + ':00 y las ' + HORA_MAX + ':00.';
            }
        }

        if (!docente) return 'Selecciona el docente.';

        /* Cruce: el docente no puede estar en dos grupos a la misma hora */
        for (var j = 0; j < data.length; j++) {
            var otro = data[j];
            if (otro.id === grupoId || otro.docente !== docente) continue;
            for (var k = 0; k < ses.length; k++) {
                var a = ses[k];
                var choque = otro.sesiones.find(function (b) {
                    return b.dia === a.dia &&
                        toMin(a.hora_inicio) < toMin(b.hora_fin) && toMin(b.hora_inicio) < toMin(a.hora_fin);
                });
                if (choque) {
                    marcarInvalida(a.dia);
                    return docente + ' ya tiene clase el ' + a.dia.toLowerCase() + ' de ' +
                        choque.hora_inicio + ' a ' + choque.hora_fin + ' con ' + otro.grupo + ' (' + otro.codigo + ').';
                }
            }
        }
        return '';
    }

    /* ── Abrir / cerrar ── */
    function cargarSesiones(sesiones) {
        horasDia = {};
        var ses = ordenarSesiones(sesiones || []);
        ses.forEach(function (s) { horasDia[s.dia] = { hi: s.hora_inicio, hf: s.hora_fin }; });
        diaCbs.forEach(function (cb) { cb.checked = !!horasDia[cb.value]; });

        var tramos = tramosDe(ses);
        var mismo  = tramos.length <= 1;
        inpHInicio.value = tramos.length ? tramos[0].hi : '07:00';
        inpHFin.value    = tramos.length ? tramos[0].hf : '11:00';
        comunesEditadas = false;
        setModo(mismo);
    }

    function openModal(id) {
        if (!overlay) return;
        limpiarError();
        editingId = id || null;

        var g = id ? data.find(function (x) { return x.id === id; }) : null;
        if (g) {
            modalTitle.textContent = 'Editar horario';
            cbGrupoModal.setValue(String(g.id), g.grupo + ' — ' + g.codigo);
            inpPrograma.value = g.programa;
            inpEscuela.value  = g.escuela;
            cargarSesiones(g.sesiones);
            cbDocenteModal.setValue(g.docente, g.docente);
        } else {
            modalTitle.textContent = 'Nuevo horario';
            cbGrupoModal.reset();
            inpPrograma.value = '';
            inpEscuela.value  = '';
            cargarSesiones([]);
            cbDocenteModal.reset();
        }

        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!overlay) return;
        [cbGrupoModal, cbDocenteModal].forEach(function (cb) { if (cb && cb.close) cb.close(); });
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    if (btnNuevo)     btnNuevo.addEventListener('click',  function () { openModal(null); });
    if (modalClose)   modalClose.addEventListener('click',  closeModal);
    if (modalCancel)  modalCancel.addEventListener('click', closeModal);
    if (overlay)      overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !overlay.classList.contains('is-open')) return;
        if (overlay.querySelector('.app-combobox.is-open')) return; // primero se cierra la lista
        closeModal();
    });

    // Toda la fila (o el bloque de la grilla) abre el editor
    document.addEventListener('click', function (e) {
        var src = e.target.closest('.hor-row, .hor-bloque');
        if (!src) return;
        openModal(parseInt(src.dataset.id));
    });

    /* ── Aviso ── */
    var toastEl = document.getElementById('hor-toast');
    var toastTimer = null;
    function toast(msg) {
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 200);
        }, 3200);
    }

    /* Guardar (mock: solo en memoria, se pierde al recargar) */
    var btnSave = document.getElementById('hor-modal-save');
    if (btnSave) {
        btnSave.addEventListener('click', function () {
            limpiarError();
            var grupoId = parseInt(hidGrupoModal.value) || null;
            var docente = hidDocente.value;
            var ses     = ordenarSesiones(sesionesForm());

            var err = validar(grupoId, docente, ses);
            if (err) { mostrarError(err); return; }

            var g = data.find(function (x) { return x.id === grupoId; });
            g.sesiones = ses;
            g.docente  = docente;

            renderTabla();
            renderKpis();
            closeModal();
            toast('Horario de ' + g.grupo + ' (' + g.codigo + ') guardado.');
        });
    }

    /* ── Init comboboxes modal ── */
    if (cbGrupoModal) {
        initCombobox(cbGrupoModal);
        cbGrupoModal.setOptions(data.map(function (g) {
            return { value: String(g.id), label: g.grupo + ' — ' + g.codigo };
        }));

        if (hidGrupoModal) {
            hidGrupoModal.addEventListener('change', function () {
                var gId = parseInt(hidGrupoModal.value);
                var g   = data.find(function (x) { return x.id === gId; });
                if (g) {
                    if (inpPrograma) inpPrograma.value = g.programa;
                    if (inpEscuela)  inpEscuela.value  = g.escuela;
                    cbDocenteModal.setValue(g.docente, g.docente);
                }
            });
        }
    }

    if (cbDocenteModal) {
        initCombobox(cbDocenteModal);
        var docentes = [];
        data.forEach(function (g) { if (g.docente && docentes.indexOf(g.docente) < 0) docentes.push(g.docente); });
        cbDocenteModal.setOptions(docentes.map(function (d) { return { value: d, label: d }; }));
    }

    /* ── Init ── */
    renderTabla();
    renderKpis();

}());
