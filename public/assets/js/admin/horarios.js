/* ESAT — Admin: Horarios */
(function () {
    'use strict';

    var data     = window.HOR_DATA || [];       // grupos no finalizados de la sede
    var docentes = window.HOR_DOCENTES || [];   // docentes activos de la sede: {id, nombre, escuela}
    var rango    = window.HOR_RANGO || { min: '07:00', max: '22:00' };
    var rangosJornada = window.HOR_JORNADAS || {};   // horas y días que admite cada jornada (Curso::RANGOS_JORNADA)
    var csrf     = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var FLASH    = 'esat.admin.horarios.aviso';

    var DIAS_FULL = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    var DIA_COD   = { 'Lunes':'L','Martes':'M','Miércoles':'Mi','Jueves':'J','Viernes':'V','Sábado':'S' };
    var JORNADAS  = ['Mañana','Tarde','Noche'];
    var HORA_MIN  = parseInt(rango.min, 10);
    var HORA_MAX  = parseInt(rango.max, 10);

    /* Solo los grupos que ya tienen días de clase se listan como horario */
    function horarios() { return data.filter(function (g) { return g.sesiones.length > 0; }); }

    /* ── Utilidades ── */
    /* El color de la escuela sale de su sigla (sal, tur, adm...); sin color propio queda neutro */
    function escClass(nombre) {
        var g = data.find(function (x) { return x.escuela === nombre; });
        return g ? g.sigla : '';
    }

    function jornadaDeHora(hora) {
        var h = parseInt(hora.split(':')[0]);
        if (h < 12) return 'Mañana';
        if (h < 18) return 'Tarde';
        return 'Noche';
    }

    function jornadaClass(j) { return j === 'Fines de semana' ? 'fds' : j.toLowerCase().replace('ñ', 'n'); }

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

        /* Se recoloca cuando la página se desplaza, pero no con el scroll de su propia
           lista: recolocar reinicia el alto de la lista y la devolvía al inicio. */
        function alScroll(e) {
            if (panel.contains(e.target)) return;
            posicionar();
        }
        function open() {
            panel.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            posicionar();
            // La opción elegida queda a la vista (listas largas como las de horas)
            var sel = list.querySelector('li.is-selected');
            if (sel) list.scrollTop = sel.offsetTop - list.clientHeight / 2 + sel.offsetHeight / 2;
            window.addEventListener('scroll', alScroll, true);
            window.addEventListener('resize', posicionar);
        }
        function close() {
            if (panel.hidden) return;
            panel.hidden = true;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            window.removeEventListener('scroll', alScroll, true);
            window.removeEventListener('resize', posicionar);
        }
        root.close = close;

        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function () { panel.hidden ? open() : close(); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });
        // Escape cierra solo la lista abierta, no el modal que la contiene
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || panel.hidden) return;
            close();
            e.stopImmediatePropagation();
        });

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
                li.appendChild(span);
                // Nota opcional a la derecha (ej. la duración en la lista de hora fin)
                if (typeof o === 'object' && o.nota) {
                    var nota = document.createElement('small');
                    nota.className = 'app-combobox-nota';
                    nota.textContent = o.nota;
                    li.appendChild(nota);
                }
                li.appendChild(svg);
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
        var matchJor = !filtroJornada || g.jornada === filtroJornada;
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

        // La jornada es la del grupo (se elige en Grupos), no se deduce de las horas
        var jornadas = '<span class="hor-jornada-tag hor-jornada-' + jornadaClass(g.jornada) + '">' + esc(g.jornada) + '</span>';

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
        tbodyEl.innerHTML = horarios().map(filaHTML).join('');
        applyFilters();
    }

    /* ── Paginación: 10 por página, igual que las demás tablas ── */
    var PAGE_SIZE    = 10;
    var currentPage  = 1;
    var filteredRows = [];
    var paginationEl = document.getElementById('hor-pagination');
    var pagesEl      = document.getElementById('hor-pg-pages');
    var btnPgPrev    = document.getElementById('hor-pg-prev');
    var btnPgNext    = document.getElementById('hor-pg-next');

    function applyFilters() {
        if (!tbodyEl) return;
        filteredRows = [];
        data.forEach(function (g) {
            var row = tbodyEl.querySelector('.hor-row[data-id="' + g.id + '"]');
            if (row && coincide(g)) filteredRows.push(row);
        });
        currentPage = 1;
        renderPage();
        if (vistaActual === 'grilla') buildGrilla();
    }

    function renderPage() {
        var total = filteredRows.length;
        var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        var start = (currentPage - 1) * PAGE_SIZE;

        tbodyEl.querySelectorAll('.hor-row').forEach(function (row) { row.style.display = 'none'; });
        filteredRows.forEach(function (row, i) {
            if (i >= start && i < start + PAGE_SIZE) row.style.display = '';
        });

        if (emptyEl)    emptyEl.style.display = total === 0 ? 'flex' : 'none';
        if (countBadge) countBadge.textContent = total + ' horario' + (total !== 1 ? 's' : '');

        if (!paginationEl) return;
        paginationEl.hidden = pages <= 1;
        if (pages <= 1) return;
        btnPgPrev.disabled = currentPage === 1;
        btnPgNext.disabled = currentPage === pages;
        pagesEl.innerHTML = '';
        buildPageRange(currentPage, pages).forEach(function (p) {
            if (p === '...') {
                var sp = document.createElement('span');
                sp.className   = 'hor-pg-ellipsis';
                sp.textContent = '…';
                pagesEl.appendChild(sp);
            } else {
                var btn = document.createElement('button');
                btn.type        = 'button';
                btn.className   = 'hor-pg-num' + (p === currentPage ? ' is-active' : '');
                btn.textContent = p;
                btn.addEventListener('click', function () { goToPage(p); });
                pagesEl.appendChild(btn);
            }
        });
    }

    function buildPageRange(current, total) {
        if (total <= 7) {
            var r = [];
            for (var i = 1; i <= total; i++) r.push(i);
            return r;
        }
        var range = [1];
        if (current > 3) range.push('...');
        for (var j = Math.max(2, current - 1); j <= Math.min(total - 1, current + 1); j++) range.push(j);
        if (current < total - 2) range.push('...');
        range.push(total);
        return range;
    }

    function goToPage(p) {
        currentPage = p;
        renderPage();
        var wrap = document.querySelector('.hor-table-wrap');
        if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (btnPgPrev) btnPgPrev.addEventListener('click', function () { if (currentPage > 1) goToPage(currentPage - 1); });
    if (btnPgNext) btnPgNext.addEventListener('click', function () {
        if (currentPage < Math.ceil(filteredRows.length / PAGE_SIZE)) goToPage(currentPage + 1);
    });

    /* ── KPIs ── */
    function renderKpis() {
        var cuenta = { 'Mañana': 0, 'Tarde': 0, 'Noche': 0 };
        horarios().forEach(function (g) { if (g.jornada in cuenta) cuenta[g.jornada]++; });
        var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
        set('hor-kpi-total',  horarios().length);
        set('hor-kpi-manana', cuenta['Mañana']);
        set('hor-kpi-tarde',  cuenta['Tarde']);
        set('hor-kpi-noche',  cuenta['Noche']);
    }

    /* ── Comboboxes filtro ── */
    if (cbEscuela) {
        initCombobox(cbEscuela);
        var escuelas = [];
        horarios().forEach(function (g) { if (g.escuela && escuelas.indexOf(g.escuela) < 0) escuelas.push(g.escuela); });
        escuelas.sort();
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
            {value:'Fines de semana',label:'Fines de semana'}
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
    function rangoTexto(j) { var r = rangosJornada[j]; return r ? r.desde + ' – ' + r.hasta : ''; }
    var cbVer         = document.getElementById('hor-cb-ver');
    var hidVer        = document.getElementById('hor-val-ver');
    var calInfoEl     = document.getElementById('hor-cal-info');
    var calEmptyEl    = document.getElementById('hor-cal-empty');

    function programaCorto(g) { return g.programa.replace('Técnico Laboral en ', ''); }

    /* Opciones del selector según el modo y los filtros de la barra */
    function opcionesVer() {
        var visibles = horarios().filter(coincide);
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
        var lista  = horarios();
        var grupos = calSel === '*'
            ? lista.filter(coincide)
            : calModo === 'grupo'
                ? lista.filter(function (g) { return String(g.id) === calSel; })
                : lista.filter(function (g) { return g.docente === calSel && coincide(g); });
        var out = [];
        grupos.forEach(function (g) {
            g.sesiones.forEach(function (s) {
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

    /* Horario general: tabla fila × día; en cada celda las clases de esa fila
       ordenadas por hora (con muchos grupos simultáneos, bloques del alto de la
       hora quedarían ilegibles). Por grupo las filas son las jornadas; por
       docente, una fila por docente para ver de un vistazo quién da clase cuándo. */
    function pintarGeneral(sel) {
        var hoyIdx = new Date().getDay() - 1;
        var porDocente = calModo === 'docente';
        var celdas = {};   // 'Mañana|0' o 'Docente|0' -> [sesiones]
        var minDoc = {};   // minutos semanales de cada docente
        sel.sesiones.forEach(function (e) {
            var fila = porDocente ? e.g.docente : jornadaDeHora(e.s.hora_inicio);
            var k = fila + '|' + DIAS_FULL.indexOf(e.s.dia);
            (celdas[k] = celdas[k] || []).push(e);
            minDoc[e.g.docente] = (minDoc[e.g.docente] || 0) + (e.fin - e.ini);
        });
        var filas = porDocente
            ? Object.keys(minDoc).sort(function (a, b) { return a.localeCompare(b, 'es'); })
            : JORNADAS.filter(function (j) {
                return DIAS_FULL.some(function (d, i) { return celdas[j + '|' + i]; });
            });

        var html = '<div class="hor-cal-corner"></div>';
        DIAS_FULL.forEach(function (d, i) {
            var n = sel.sesiones.filter(function (e) { return e.s.dia === d; }).length;
            html += '<div class="hor-cal-head' + (i === hoyIdx ? ' is-hoy' : '') + (n ? '' : ' is-libre') + '">' +
                d + (i === hoyIdx ? '<span class="hor-cal-hoy">Hoy</span>' : '') + '</div>';
        });

        filas.forEach(function (j) {
            html += porDocente
                ? '<div class="hor-cal-fila-jor hor-cal-fila-doc">' +
                    '<span class="hor-cal-fila-doc-nombre">' + esc(j) + '</span>' +
                    '<span class="hor-cal-fila-jor-rango">' + fmtDuracion(minDoc[j]) + ' semanales</span>' +
                  '</div>'
                : '<div class="hor-cal-fila-jor hor-cal-jor-' + jornadaClass(j) + '">' +
                    '<span class="hor-cal-fila-jor-nombre">' + j + '</span>' +
                    '<span class="hor-cal-fila-jor-rango">' + rangoTexto(j) + '</span>' +
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
                            // En la fila del docente su nombre sobra
                            (porDocente ? '' : '<span class="hor-bloque-meta">' + esc(e.g.docente) + '</span>') +
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
                ? horarios().find(function (x) { return String(x.id) === calSel; })
                : horarios().find(function (x) { return x.docente === calSel && coincide(x); });
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
    var btnSave     = document.getElementById('hor-modal-save');

    var cbGrupoModal   = document.getElementById('hor-cb-grupo-modal');
    var hidGrupoModal  = document.getElementById('hor-val-grupo-modal');
    var cbDocenteModal = document.getElementById('hor-cb-docente-modal');
    var hidDocente     = document.getElementById('hor-val-docente-modal');
    var inpPrograma    = document.getElementById('hor-inp-programa');
    var inpEscuela     = document.getElementById('hor-inp-escuela');
    var inpJornada     = document.getElementById('hor-inp-jornada');
    var notaJornadaEl  = document.getElementById('hor-jornada-nota');
    var chkMismo       = document.getElementById('hor-mismo-horario');
    var boxComunes     = document.getElementById('hor-horas-comunes');
    var boxPorDia      = document.getElementById('hor-horas-por-dia');
    var sesListEl      = document.getElementById('hor-ses-list');
    var sesVacioEl     = document.getElementById('hor-ses-vacio');
    var resumenEl      = document.getElementById('hor-ses-resumen');
    var errorEl        = document.getElementById('hor-form-error');
    var diaCbs         = document.querySelectorAll('.hor-dia-cb');

    /* ── Selector de horas: inicio y fin en pasos de 30 min dentro del rango de la sede.
       La lista de fin solo ofrece horas posteriores al inicio y muestra la duración. ── */
    var PASO = 30;

    function aHora(min) { return pad(Math.floor(min / 60)) + ':' + pad(min % 60); }

    function horasEntre(desde, hasta) {
        var out = [];
        for (var m = desde; m <= hasta; m += PASO) out.push(aHora(m));
        return out;
    }

    /* Markup de un combobox de hora (para las filas de "Horas por día") */
    function htmlHora(etiqueta) {
        return '<div class="app-combobox hor-hora-cb">' +
            '<button type="button" class="app-combobox-trigger" data-combobox-trigger aria-label="' + esc(etiqueta) + '">' +
                '<span data-combobox-label>—</span>' +
                '<svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>' +
            '</button>' +
            '<div class="app-combobox-panel" data-combobox-panel hidden><ul class="app-combobox-list" data-combobox-list role="listbox"></ul></div>' +
            '<input type="hidden" data-combobox-value>' +
        '</div>';
    }

    /* Une dos combobox (inicio y fin). onCambio(hi, hf) se llama cuando el usuario cambia algo. */
    function crearParHoras(cbIni, cbFin, onCambio) {
        initCombobox(cbIni);
        initCombobox(cbFin);
        var hidIni = cbIni.querySelector('[data-combobox-value]');
        var hidFin = cbFin.querySelector('[data-combobox-value]');
        var minR, maxR;
        function ponerRango(desde, hasta) {
            minR = toMin(desde); maxR = toMin(hasta);
            cbIni.setOptions(horasEntre(minR, maxR - PASO));
        }
        ponerRango(rango.min, rango.max);

        function opcionesFin(hi) {
            var desde = hi ? toMin(hi) + PASO : minR + PASO;
            cbFin.setOptions(horasEntre(desde, maxR).map(function (h) {
                return { value: h, label: h, nota: hi ? fmtDuracion(toMin(h) - toMin(hi)) : '' };
            }));
        }

        function poner(hi, hf) {
            opcionesFin(hi);
            cbIni.setValue(hi, hi, true);
            cbFin.setValue(hf, hf, true);
        }

        // Al mover el inicio se conserva la duración (sin pasarse del cierre de la sede)
        var iniPrevio = '';
        hidIni.addEventListener('change', function () {
            var hi = hidIni.value, hf = hidFin.value;
            var dur = (iniPrevio && hf && toMin(hf) > toMin(iniPrevio)) ? toMin(hf) - toMin(iniPrevio) : 4 * 60;
            var fin = Math.min(toMin(hi) + dur, maxR);
            poner(hi, aHora(fin));
            iniPrevio = hi;
            onCambio(hi, hidFin.value);
        });
        hidFin.addEventListener('change', function () { onCambio(hidIni.value, hidFin.value); });

        return {
            get: function () { return { hi: hidIni.value, hf: hidFin.value }; },
            rango: function (desde, hasta) { ponerRango(desde, hasta); },
            set: function (hi, hf) { iniPrevio = hi; poner(hi, hf); },
            invalido: function (si) { cbIni.classList.toggle('is-invalid', si); cbFin.classList.toggle('is-invalid', si); }
        };
    }

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
            var h = chkMismo.checked ? comunes.get() : horasDia[d];
            return { dia: d, hora_inicio: h.hi, hora_fin: h.hf };
        });
    }

    function sesionValida(s) {
        return s.hora_inicio && s.hora_fin && toMin(s.hora_fin) > toMin(s.hora_inicio);
    }

    /* Una fila por día; se crean una sola vez y se reutilizan (cada una tiene sus combobox) */
    var filasDia = {};

    function filaDia(d) {
        if (filasDia[d]) return filasDia[d];
        var row = document.createElement('div');
        row.className = 'hor-ses-row';
        row.dataset.dia = d;
        row.innerHTML =
            '<span class="hor-ses-dia">' + d + '</span>' +
            htmlHora('Hora inicio ' + d) +
            '<span class="hor-ses-sep">a</span>' +
            htmlHora('Hora fin ' + d) +
            '<span class="hor-ses-info"></span>';
        var cbs = row.querySelectorAll('.hor-hora-cb');
        row.par = crearParHoras(cbs[0], cbs[1], function (hi, hf) {
            horasDia[d] = { hi: hi, hf: hf };
            row.classList.remove('is-invalid');
            pintarInfoFila(row);
            actualizarResumen();
            limpiarError();
        });
        filasDia[d] = row;
        return row;
    }

    function renderSesList() {
        var dias = diasMarcados();
        sesListEl.innerHTML = '';
        sesVacioEl.hidden = dias.length > 0;

        dias.forEach(function (d) {
            var row = filaDia(d);
            row.classList.remove('is-invalid');
            var r = rangoActual();
            row.par.rango(r.desde, r.hasta);
            row.par.set(horasDia[d].hi, horasDia[d].hf);
            sesListEl.appendChild(row);
            pintarInfoFila(row);
        });
    }

    function pintarInfoFila(row) {
        var h = horasDia[row.dataset.dia];
        var info = row.querySelector('.hor-ses-info');
        var ok = h.hi && h.hf && toMin(h.hf) > toMin(h.hi);
        info.textContent = ok ? fmtDuracion(toMin(h.hf) - toMin(h.hi)) : 'Revisar horas';
        info.classList.toggle('is-error', !ok);
    }

    /* ── Tope de horas: el horario no puede sumar más horas que las del programa
       durante las fechas del grupo (mismo cálculo que HorarioService) ── */
    function semanasDe(g) {
        var dias = (new Date(g.fecha_fin_iso + 'T00:00:00') - new Date(g.fecha_inicio_iso + 'T00:00:00')) / 864e5;
        return Math.max(0, dias) / 7;
    }
    function horasTotales(g, ses) { return minutosSemana(ses) / 60 * semanasDe(g); }
    function fmtNum(n) { return String(Math.round(n * 10) / 10).replace('.', ','); }

    function grupoForm() {
        var id = parseInt(hidGrupoModal.value);
        return data.find(function (x) { return x.id === id; }) || null;
    }

    function actualizarResumen() {
        var ses = sesionesForm();
        var validas = ses.filter(sesionValida);
        resumenEl.classList.remove('is-error');

        if (!ses.length) { resumenEl.textContent = ''; return; }
        var txt = ses.length + (ses.length === 1 ? ' sesión' : ' sesiones') +
            ' a la semana · ' + fmtDuracion(minutosSemana(validas)) + ' de clase';

        var g = grupoForm();
        if (g) {
            var horas = horasTotales(g, validas);
            txt += ' · ' + fmtNum(Math.round(horas)) + ' h de ' + fmtNum(g.horas_programa) + ' h del programa';
            resumenEl.classList.toggle('is-error', horas > g.horas_programa);
        }
        resumenEl.textContent = txt;
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
                var base = previos.length ? horasDia[previos[previos.length - 1]] : comunes.get();
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
            if (dias.length && horasDia[dias[0]]) comunes.set(horasDia[dias[0]].hi, horasDia[dias[0]].hf);
            comunesEditadas = false;
        } else {
            dias.forEach(function (d) {
                if (comunesEditadas || !horasDia[d]) horasDia[d] = comunes.get();
            });
        }
        setModo(chkMismo.checked);
        limpiarError();
    });

    /* Horas comunes (interruptor encendido) */
    var comunes = crearParHoras(document.getElementById('hor-cb-hinicio'), document.getElementById('hor-cb-hfin'), function () {
        comunesEditadas = true;
        comunes.invalido(false);
        actualizarResumen();
        limpiarError();
    });

    /* ── Jornada: es la del grupo (se elige en Grupos). Las horas y los días solo
       se eligen dentro de ella; no hay horarios por fuera de la jornada. ── */
    function rangoActual() {
        var g = grupoForm();
        return (g && rangosJornada[g.jornada]) || { desde: rango.min, hasta: rango.max, dias: null };
    }

    /* Ajusta el formulario a la jornada del grupo elegido */
    function aplicarJornada(limpiar) {
        var g = grupoForm();
        var r = rangoActual();
        inpJornada.value = g ? g.jornada : '';
        comunes.rango(r.desde, r.hasta);
        diaCbs.forEach(function (cb) {
            var permitido = !r.dias || r.dias.indexOf(cb.value) >= 0;
            if (!permitido && limpiar) cb.checked = false;
            cb.disabled = !permitido && !cb.checked;
        });
        // Si la jornada admite un solo día (fines de semana), se marca de una vez
        if (limpiar && r.dias && r.dias.length === 1) {
            diaCbs.forEach(function (cb) { if (cb.value === r.dias[0]) cb.checked = true; });
        }
        pintarNotaJornada();
    }

    /* Recuerda entre qué horas va la jornada del grupo */
    function pintarNotaJornada() {
        var g = grupoForm();
        if (!g) { notaJornadaEl.textContent = 'Elige el grupo: las horas dependen de su jornada.'; return; }
        var r = rangoActual();
        notaJornadaEl.textContent = 'Jornada ' + g.jornada.toLowerCase() + ': las clases van de ' + r.desde + ' a ' + r.hasta +
            (r.dias ? ', solo el ' + r.dias.join(' y el ').toLowerCase() : '') + '.';
    }

    /* ── Errores ── */
    function mostrarError(msg) { errorEl.textContent = msg; errorEl.hidden = false; }
    function limpiarError() {
        errorEl.hidden = true; errorEl.textContent = '';
        overlay.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    }

    function marcarInvalida(dia) {
        if (chkMismo.checked) {
            comunes.invalido(true);
        } else {
            var row = sesListEl.querySelector('.hor-ses-row[data-dia="' + dia + '"]');
            if (row) row.classList.add('is-invalid');
        }
    }

    function validar(grupoId, docenteId, ses) {
        if (!grupoId) return 'Selecciona el grupo.';
        if (!ses.length) return 'Marca al menos un día de clase.';

        for (var i = 0; i < ses.length; i++) {
            var s = ses[i];
            if (!sesionValida(s)) {
                marcarInvalida(s.dia);
                return 'El ' + s.dia.toLowerCase() + ': la hora fin debe ser posterior a la hora inicio.';
            }
            if (toMin(s.hora_inicio) < toMin(rango.min) || toMin(s.hora_fin) > toMin(rango.max)) {
                marcarInvalida(s.dia);
                return 'El ' + s.dia.toLowerCase() + ': las clases deben estar entre las ' + rango.min + ' y las ' + rango.max + '.';
            }
        }

        // Debe caber en la jornada del grupo (mismo mensaje que HorarioService::fueraDeJornada)
        var gJ = data.find(function (x) { return x.id === grupoId; });
        var r = rangoActual();
        var nombreJ = gJ.jornada.toLowerCase();
        for (var n = 0; n < ses.length; n++) {
            var sj = ses[n];
            if (r.dias && r.dias.indexOf(sj.dia) < 0) {
                marcarInvalida(sj.dia);
                return 'El grupo es de jornada ' + nombreJ + ': solo puede tener clase el ' + r.dias.join(' y el ').toLowerCase() + '.';
            }
            if (toMin(sj.hora_inicio) < toMin(r.desde) || toMin(sj.hora_fin) > toMin(r.hasta)) {
                marcarInvalida(sj.dia);
                return 'El grupo es de jornada ' + nombreJ + ': las clases van entre las ' + r.desde + ' y las ' + r.hasta +
                    ' y el ' + sj.dia.toLowerCase() + ' quedó de ' + sj.hora_inicio + ' a ' + sj.hora_fin + '.';
            }
        }

        var g = data.find(function (x) { return x.id === grupoId; });
        var horas = horasTotales(g, ses);
        if (horas > g.horas_programa) {
            var maxSemana = Math.floor(g.horas_programa / Math.max(0.01, semanasDe(g)) * 10) / 10;
            return 'El horario suma ' + fmtNum(horas) + ' horas (' + fmtNum(minutosSemana(ses) / 60) + ' h por semana durante ' +
                fmtNum(semanasDe(g)) + ' semanas) y el programa tiene ' + fmtNum(g.horas_programa) +
                ' horas. Con estas fechas el máximo es ' + fmtNum(maxSemana) + ' h por semana.';
        }

        if (!docenteId) return 'Selecciona el docente.';

        /* Cruce: el docente no puede estar en dos grupos a la misma hora si sus fechas se cruzan */
        for (var j = 0; j < data.length; j++) {
            var otro = data[j];
            if (otro.id === grupoId || otro.docente_id !== docenteId) continue;
            if (otro.fecha_inicio_iso > g.fecha_fin_iso || otro.fecha_fin_iso < g.fecha_inicio_iso) continue;
            for (var k = 0; k < ses.length; k++) {
                var a = ses[k];
                var choque = otro.sesiones.find(function (b) {
                    return b.dia === a.dia &&
                        toMin(a.hora_inicio) < toMin(b.hora_fin) && toMin(b.hora_inicio) < toMin(a.hora_fin);
                });
                if (choque) {
                    marcarInvalida(a.dia);
                    return otro.docente + ' ya tiene clase el ' + a.dia.toLowerCase() + ' de ' +
                        choque.hora_inicio + ' a ' + choque.hora_fin + ' con el ' + otro.grupo + ' (' + otro.codigo + ').';
                }
            }
        }
        return '';
    }

    /* ── Abrir / cerrar ── */
    /* Horas por defecto: la jornada completa (fines de semana: las primeras 6 horas) */
    function horasPorDefecto() {
        var r = rangoActual();
        return { hi: r.desde, hf: aHora(Math.min(toMin(r.desde) + 6 * 60, toMin(r.hasta))) };
    }

    /* limpiar: en un horario nuevo se desmarcan los días que la jornada no admite;
       al editar no se toca lo guardado (si algo no cabe, lo marca la validación). */
    function cargarSesiones(sesiones, limpiar) {
        horasDia = {};
        var ses = ordenarSesiones(sesiones || []);
        ses.forEach(function (s) { horasDia[s.dia] = { hi: s.hora_inicio, hf: s.hora_fin }; });
        diaCbs.forEach(function (cb) { cb.checked = !!horasDia[cb.value]; });

        var tramos = tramosDe(ses);
        var mismo  = tramos.length <= 1;
        var f = horasPorDefecto();
        comunes.set(tramos.length ? tramos[0].hi : f.hi, tramos.length ? tramos[0].hf : f.hf);
        aplicarJornada(limpiar);
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
            cbGrupoModal.setOptions([opcionGrupo(g)]);
            cbGrupoModal.setValue(String(g.id), opcionGrupo(g).label, true);
            inpPrograma.value = g.programa;
            inpEscuela.value  = g.escuela;
            ponerDocente(g);
            cargarSesiones(g.sesiones);
        } else {
            // Nuevo: solo los grupos que todavía no tienen días de clase
            var libres = data.filter(function (x) { return !x.sesiones.length; });
            if (!libres.length) {
                toast('Todos los grupos ya tienen horario. Crea un grupo nuevo en Grupos para asignarle uno.');
                return;
            }
            modalTitle.textContent = 'Nuevo horario';
            cbGrupoModal.setOptions(libres.map(opcionGrupo));
            cbGrupoModal.reset();
            inpPrograma.value = '';
            inpEscuela.value  = '';
            cbDocenteModal.reset();
            cargarSesiones([], true);
        }
        btnSave.disabled = false;
        btnSave.textContent = 'Guardar horario';

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
        }, 5000);
    }
    try {
        var aviso = sessionStorage.getItem(FLASH);
        if (aviso) { sessionStorage.removeItem(FLASH); toast(aviso); }
    } catch (e) {}

    /* ── Backend ── */
    function enviar(metodo, url, cuerpo) {
        return fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) {
                    var err = new Error(j.message || 'No se pudo guardar. Intenta de nuevo.');
                    err.errores = j.errors || null;
                    throw err;
                }
                return j;
            });
        });
    }
    function recargarConAviso(msg) {
        try { sessionStorage.setItem(FLASH, msg); } catch (e) {}
        location.reload();
    }

    /* Guardar */
    btnSave.addEventListener('click', function () {
        limpiarError();
        var grupoId   = parseInt(hidGrupoModal.value) || null;
        var docenteId = parseInt(hidDocente.value) || null;
        var ses       = ordenarSesiones(sesionesForm());

        var err = validar(grupoId, docenteId, ses);
        if (err) { mostrarError(err); return; }

        btnSave.disabled = true;
        btnSave.textContent = 'Guardando…';
        enviar('PUT', '/admin/horarios/' + grupoId, { docente_id: docenteId, sesiones: ses })
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (e) {
                btnSave.disabled = false;
                btnSave.textContent = 'Guardar horario';
                var clave = e.errores && Object.keys(e.errores)[0];
                // sesiones.2.hora_fin → marcar la fila de ese día
                var m = clave && clave.match(/^sesiones\.(\d+)\./);
                if (m && ses[+m[1]]) marcarInvalida(ses[+m[1]].dia);
                mostrarError(clave ? e.errores[clave][0] : e.message);
            });
    });

    /* ── Init comboboxes modal ── */
    function opcionGrupo(g) {
        return { value: String(g.id), label: g.grupo + ' — ' + g.codigo + ' · ' + g.programa.replace('Técnico Laboral en ', '') };
    }

    /* "Nombre · Escuela", igual que en Grupos: así se ve si el docente es de otra escuela */
    function etiquetaDocente(d) { return d.nombre + ' · ' + d.escuela; }

    /* Docente del grupo; si ya no está activo no aparece en la lista y hay que elegir otro */
    function ponerDocente(g) {
        var d = docentes.find(function (x) { return x.id === g.docente_id; });
        if (d) cbDocenteModal.setValue(String(d.id), etiquetaDocente(d), true);
        else cbDocenteModal.reset();
    }

    if (cbGrupoModal) {
        initCombobox(cbGrupoModal);
        hidGrupoModal.addEventListener('change', function () {
            var g = grupoForm();
            if (!g) return;
            inpPrograma.value = g.programa;
            inpEscuela.value  = g.escuela;
            ponerDocente(g);
            // Las horas pasan a las de la jornada del grupo; los días marcados se conservan si la jornada los admite
            var f = horasPorDefecto();
            cargarSesiones(diasMarcados().map(function (d) { return { dia: d, hora_inicio: f.hi, hora_fin: f.hf }; }), true);
            limpiarError();
        });
    }

    if (cbDocenteModal) {
        initCombobox(cbDocenteModal);
        cbDocenteModal.setOptions(docentes.map(function (d) { return { value: String(d.id), label: etiquetaDocente(d) }; }));
        hidDocente.addEventListener('change', limpiarError);
    }

    /* ── Init ── */
    renderTabla();
    renderKpis();

}());
