/* ESAT — Admin: Cursos */
(function () {
    'use strict';

    var data = window.CUR_DATA || [];

    /* ── Utilidades ───────────────────────────── */
    function estadoTag(estado) {
        var map = {
            activo:        '<span class="cur-estado-tag cur-estado-activo">Activo</span>',
            planificacion: '<span class="cur-estado-tag cur-estado-planificacion">En planificación</span>',
            finalizado:    '<span class="cur-estado-tag cur-estado-finalizado">Finalizado</span>',
        };
        return map[estado] || '';
    }
    function escClass(escuela) {
        var m = { 'Salud':'sal','Cocina y Turismo':'tur','Administrativa':'adm','Educación e Idiomas':'edu','Deporte y Cultura':'dep','Ciencias':'cie','Belleza':'bel' };
        return m[escuela] || 'adm';
    }
    var avatarColors = ['#e07b39','#2563eb','#16a34a','#7c3aed','#0369a1','#b91c1c','#ca8a04','#0d9488'];
    function avatarColor(i) { return avatarColors[i % avatarColors.length]; }
    function initials(nombre) {
        var parts = nombre.trim().split(' ');
        return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
    }

    /* ── Filtros ──────────────────────────────── */
    var search       = document.getElementById('cur-search');
    var selPrg       = document.getElementById('cur-filter-programa');
    var selEst       = document.getElementById('cur-filter-estado');
    var countBdg     = document.getElementById('cur-count');
    var emptyEl      = document.getElementById('cur-empty');
    var paginationEl = document.getElementById('cur-pagination');
    var pagesEl      = document.getElementById('cur-pg-pages');
    var btnPrev      = document.getElementById('cur-pg-prev');
    var btnNext      = document.getElementById('cur-pg-next');

    var PAGE_SIZE   = 10;
    var currentPage = 1;
    var filteredRows = [];

    function applyFilters() {
        var q   = (search ? search.value : '').toLowerCase().trim();
        var prg = selPrg ? selPrg.value : '';
        var est = selEst ? selEst.value : '';
        var allRows = document.querySelectorAll('#cur-table .cur-row');

        filteredRows = [];
        allRows.forEach(function (row) {
            var match =
                (!q   || row.dataset.nombre.includes(q)) &&
                (!prg || row.dataset.programa === prg) &&
                (!est || row.dataset.estado   === est);
            if (match) filteredRows.push(row);
        });
        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        var total   = filteredRows.length;
        var pages   = Math.max(1, Math.ceil(total / PAGE_SIZE));
        var allRows = document.querySelectorAll('#cur-table .cur-row');
        var start   = (currentPage - 1) * PAGE_SIZE;
        var end     = start + PAGE_SIZE;

        allRows.forEach(function (row) { row.classList.add('is-hidden'); });
        filteredRows.forEach(function (row, i) {
            if (i >= start && i < end) row.classList.remove('is-hidden');
        });

        if (countBdg) countBdg.textContent = total + ' grupo' + (total !== 1 ? 's' : '');
        if (emptyEl)  emptyEl.style.display = total === 0 ? 'block' : 'none';

        if (!paginationEl) return;
        paginationEl.style.display = pages <= 1 ? 'none' : '';
        if (pages > 1) {
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === pages;
            pagesEl.innerHTML = '';
            buildPageRange(currentPage, pages).forEach(function (p) {
                if (p === '...') {
                    var sp = document.createElement('span');
                    sp.className = 'cur-pg-ellipsis'; sp.textContent = '…';
                    pagesEl.appendChild(sp);
                } else {
                    var btn = document.createElement('button');
                    btn.className = 'cur-pg-num' + (p === currentPage ? ' is-active' : '');
                    btn.textContent = p;
                    (function (pg) { btn.addEventListener('click', function () { goToPage(pg); }); }(p));
                    pagesEl.appendChild(btn);
                }
            });
        }
    }

    function buildPageRange(current, total) {
        if (total <= 7) { var r = []; for (var i = 1; i <= total; i++) r.push(i); return r; }
        var range = [1];
        if (current > 3) range.push('...');
        for (var i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) range.push(i);
        if (current < total - 2) range.push('...');
        range.push(total);
        return range;
    }

    function goToPage(p) {
        currentPage = p; renderPage();
        var wrap = document.querySelector('.cur-table-wrap');
        if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (btnPrev) btnPrev.addEventListener('click', function () { if (currentPage > 1) goToPage(currentPage - 1); });
    if (btnNext) btnNext.addEventListener('click', function () {
        var pages = Math.ceil(filteredRows.length / PAGE_SIZE);
        if (currentPage < pages) goToPage(currentPage + 1);
    });
    if (search) search.addEventListener('input', applyFilters);
    if (selPrg) selPrg.addEventListener('change', applyFilters);
    if (selEst) selEst.addEventListener('change', applyFilters);

    /* ── Drawer ───────────────────────────────── */
    var drawer        = document.getElementById('cur-drawer');
    var drawerOverlay = document.getElementById('cur-drawer-overlay');
    var drawerClose   = document.getElementById('cur-drawer-close');
    var currentCur    = null;

    function openDrawer(id) {
        currentCur = data.find(function (c) { return c.id === id; });
        if (!currentCur) return;
        fillDrawer(currentCur);
        setDrawerTab('general');
        drawer.classList.add('is-open');
        drawerOverlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawerOverlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }
    if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);
    if (drawerClose)   drawerClose.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDrawer(); closeModal(); }
    });

    function fillDrawer(c) {
        set('dw-nombre',      c.nombre);
        set('dw-codigo',      c.codigo);
        set('dw-programa',    c.programa);
        set('dw-escuela-val', c.escuela);
        set('dw-docente-val', c.docente);
        set('dw-modalidad',   c.modalidad);
        set('dw-jornada-val', c.jornada);
        set('dw-fecha-inicio',c.fecha_inicio);
        set('dw-fecha-fin',   c.fecha_fin);
        set('dw-cupo-num',    c.inscritos + ' / ' + c.cupo_max + ' estudiantes');

        var pct = Math.round((c.inscritos / c.cupo_max) * 100);
        var fill = document.getElementById('dw-cupo-fill');
        if (fill) { fill.style.width = pct + '%'; fill.className = 'cur-cupo-drawer-fill' + (pct >= 100 ? ' is-full' : pct < 50 ? ' is-low' : ''); }

        // badges
        var escCls = escClass(c.escuela);
        html('dw-badges',
            estadoTag(c.estado) +
            ' <span class="cur-esc-tag cur-esc-' + escCls + '">' + c.escuela + '</span>' +
            ' <span class="cur-jornada-tag">' + c.jornada + '</span>'
        );

        // Horario
        var diasHtml = (c.dias || []).map(function (d) { return '<span class="cur-dia-chip">' + d + '</span>'; }).join('');
        html('dw-dias', diasHtml);
        set('dw-hora',       c.hora_inicio + ' – ' + c.hora_fin);
        set('dw-duracion',   calcDuracion(c.hora_inicio, c.hora_fin));

        // Estudiantes
        var estBody = document.getElementById('dw-est-list');
        if (estBody) {
            estBody.innerHTML = (c.estudiantes || []).map(function (est, i) {
                return '<div class="cur-est-item">'
                    + '<div class="cur-est-avatar" style="background:' + avatarColor(i) + '">' + initials(est.nombre) + '</div>'
                    + '<div><div class="cur-est-nombre">' + est.nombre + '</div><div class="cur-est-doc">CC ' + est.cedula + '</div></div>'
                    + '</div>';
            }).join('');
        }

        // Footer btn
        var toggleBtn = document.getElementById('cur-drawer-toggle-btn');
        if (toggleBtn) {
            if (c.estado === 'activo') {
                toggleBtn.textContent = 'Finalizar grupo';
                toggleBtn.className = 'cur-btn cur-btn-danger';
            } else if (c.estado === 'planificacion') {
                toggleBtn.textContent = 'Activar grupo';
                toggleBtn.className = 'cur-btn cur-btn-secondary';
            } else {
                toggleBtn.textContent = 'Reactivar grupo';
                toggleBtn.className = 'cur-btn cur-btn-secondary';
            }
        }
    }

    function calcDuracion(inicio, fin) {
        try {
            var h1 = parseInt(inicio.split(':')[0]), m1 = parseInt(inicio.split(':')[1]);
            var h2 = parseInt(fin.split(':')[0]),   m2 = parseInt(fin.split(':')[1]);
            var mins = (h2 * 60 + m2) - (h1 * 60 + m1);
            var horas = Math.floor(mins / 60), rest = mins % 60;
            return horas + (rest ? 'h ' + rest + 'min' : 'h') + ' por sesión';
        } catch(e) { return ''; }
    }

    function set(id, text) { var el = document.getElementById(id); if (el) el.textContent = text || '—'; }
    function html(id, markup) { var el = document.getElementById(id); if (el) el.innerHTML = markup; }

    function setDrawerTab(tab) {
        document.querySelectorAll('.cur-dtab').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.tab === tab);
        });
        document.querySelectorAll('.cur-tab-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.panel === tab);
        });
    }
    document.querySelectorAll('.cur-dtab').forEach(function (btn) {
        btn.addEventListener('click', function () { setDrawerTab(btn.dataset.tab); });
    });

    document.querySelectorAll('[data-action="ver"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); openDrawer(Number(btn.dataset.id)); });
    });
    document.querySelectorAll('[data-action="editar"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); openModal(Number(btn.dataset.id)); });
    });
    document.querySelectorAll('.cur-row').forEach(function (row) {
        row.addEventListener('click', function () { openDrawer(Number(row.dataset.id)); });
    });

    var editFromDrawer = document.getElementById('cur-drawer-edit-btn');
    if (editFromDrawer) {
        editFromDrawer.addEventListener('click', function () {
            if (currentCur) { closeDrawer(); openModal(currentCur.id); }
        });
    }

    /* ── Modal wizard ─────────────────────────── */
    var modal        = document.getElementById('cur-modal');
    var modalOverlay = document.getElementById('cur-modal-overlay');
    var modalClose   = document.getElementById('cur-modal-close');
    var btnWizNext   = document.getElementById('cur-btn-next');
    var btnWizBack   = document.getElementById('cur-btn-back');
    var stepLbl      = document.getElementById('cur-step-lbl');
    var modalTitle   = document.getElementById('cur-modal-title');
    var currentStep  = 1;
    var totalSteps   = 2;

    function openModal(id) {
        currentStep = 1;
        renderStep();
        if (modalTitle) modalTitle.textContent = id ? 'Editar grupo' : 'Nuevo grupo';
        if (modal) { modal.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
        if (modalOverlay) modalOverlay.classList.add('is-open');
    }
    function closeModal() {
        if (modal) modal.classList.remove('is-open');
        if (modalOverlay) modalOverlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }
    if (modalOverlay) modalOverlay.addEventListener('click', function (e) { if (e.target === modalOverlay) closeModal(); });
    if (modalClose)   modalClose.addEventListener('click', closeModal);

    var btnNuevo = document.getElementById('btn-nuevo-cur');
    if (btnNuevo) btnNuevo.addEventListener('click', function () { openModal(null); });

    function renderStep() {
        document.querySelectorAll('.cur-mstep').forEach(function (s) {
            s.classList.toggle('is-active', Number(s.dataset.step) === currentStep);
        });
        document.querySelectorAll('.cur-wstep').forEach(function (s) {
            var n = Number(s.dataset.step);
            s.classList.toggle('is-active', n === currentStep);
            s.classList.toggle('is-done',   n < currentStep);
        });
        if (stepLbl)    stepLbl.textContent = 'Paso ' + currentStep + ' de ' + totalSteps;
        if (btnWizBack) btnWizBack.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        if (btnWizNext) btnWizNext.textContent = currentStep === totalSteps ? 'Guardar grupo' : 'Siguiente';
    }

    if (btnWizNext) btnWizNext.addEventListener('click', function () {
        if (currentStep < totalSteps) { currentStep++; renderStep(); } else { closeModal(); }
    });
    if (btnWizBack) btnWizBack.addEventListener('click', function () {
        if (currentStep > 1) { currentStep--; renderStep(); }
    });


    /* ── Combobox genérico ────────────────────── */
    function initCombobox(root) {
        var trigger     = root.querySelector('[data-combobox-trigger]');
        var panel       = root.querySelector('[data-combobox-panel]');
        var list        = root.querySelector('[data-combobox-list]');
        var searchInput = root.querySelector('[data-combobox-search]');
        var hidden      = root.querySelector('[data-combobox-value]');
        var label       = root.querySelector('[data-combobox-label]');
        var placeholder = label.textContent;
        var esAbsoluto  = root.dataset.position === 'absolute';

        function posicionar() {
            var rect  = trigger.getBoundingClientRect();
            var dirUp = root.dataset.direction === 'up';
            var margen = 12;
            panel.style.left  = rect.left + 'px';
            panel.style.width = rect.width + 'px';
            var espacio;
            if (dirUp) {
                panel.style.top    = '';
                panel.style.bottom = (window.innerHeight - rect.top + 6) + 'px';
                espacio = Math.min(rect.top - 6 - margen, 500);
            } else {
                panel.style.bottom = '';
                panel.style.top    = (rect.bottom + 6) + 'px';
                espacio = window.innerHeight - (rect.bottom + 6) - margen;
                if (espacio < 120) {
                    root.dataset.direction = 'up';
                    return posicionar();
                }
                if (espacio > 320) espacio = 320;
            }
            var searchWrap = searchInput && searchInput.closest('.app-combobox-search-wrap');
            var searchAlto = searchWrap ? searchWrap.offsetHeight : 0;
            list.style.maxHeight = Math.max(espacio - searchAlto - 14, 40) + 'px';
            if (root.dataset.listMaxHeight) list.style.maxHeight = root.dataset.listMaxHeight + 'px';
        }

        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            if (!esAbsoluto) {
                posicionar();
                window.addEventListener('scroll', posicionar, true);
                window.addEventListener('resize', posicionar);
            }
            if (searchInput) { searchInput.value = ''; filtrar(''); searchInput.focus(); }
        }
        function cerrar() {
            panel.hidden = true;
            root.classList.remove('is-open');
            if (!esAbsoluto) {
                window.removeEventListener('scroll', posicionar, true);
                window.removeEventListener('resize', posicionar);
            }
        }
        function filtrar(q) {
            var q2 = q.toLowerCase();
            var items = list.querySelectorAll('li[data-value]');
            var visible = 0;
            items.forEach(function (li) {
                var match = li.dataset.label.toLowerCase().includes(q2);
                li.hidden = !match;
                if (match) visible++;
            });
            var empty = list.querySelector('.app-combobox-empty');
            if (empty) empty.hidden = visible > 0;
        }

        trigger.addEventListener('click', function () { panel.hidden ? abrir() : cerrar(); });
        if (searchInput) searchInput.addEventListener('input', function () { filtrar(searchInput.value); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });

        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (opcion) {
                var val = typeof opcion === 'object' ? opcion.value : opcion;
                var lbl = typeof opcion === 'object' ? opcion.label : opcion;
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = val; li.dataset.label = lbl;
                var span = document.createElement('span'); span.textContent = lbl;
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                li.appendChild(span); li.appendChild(svg);
                li.addEventListener('click', function () { root.setValue(val, lbl); cerrar(); });
                list.appendChild(li);
            });
            var empty = document.createElement('li');
            empty.className = 'app-combobox-empty';
            empty.hidden = opciones.length > 0;
            empty.textContent = 'Sin resultados';
            list.appendChild(empty);
        };
        root.setValue = function (valor, etiqueta, opts) {
            hidden.value = valor || '';
            label.textContent = etiqueta || valor || placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === valor);
            });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        root.reset = function () {
            hidden.value = ''; label.textContent = placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.remove('is-selected'); });
        };
    }

    /* ── Filtros — comboboxes ────────────────── */
    var cbFiltPrg = document.getElementById('cur-cb-filter-programa');
    var cbFiltEst = document.getElementById('cur-cb-filter-estado');

    if (cbFiltPrg) {
        initCombobox(cbFiltPrg);
        var prgOpts = JSON.parse(cbFiltPrg.dataset.options || '[]');
        cbFiltPrg.setOptions([{value:'', label:'Todos los programas'}].concat(prgOpts.map(function (v) { return {value:v, label:v}; })));
        cbFiltPrg.setValue('', 'Todos los programas', {silent:true});
    }
    if (cbFiltEst) {
        initCombobox(cbFiltEst);
        cbFiltEst.setOptions([
            {value:'', label:'Todos los estados'},
            {value:'activo',        label:'Activo'},
            {value:'planificacion', label:'En planificación'},
            {value:'finalizado',    label:'Finalizado'},
        ]);
        cbFiltEst.setValue('', 'Todos los estados', {silent:true});
    }

    /* ── Wizard — comboboxes ─────────────────── */
    var cbEscuela   = document.getElementById('cur-cb-escuela');
    var cbPrograma  = document.getElementById('cur-cb-programa');
    var cbModalidad = document.getElementById('cur-cb-modalidad');
    var cbDocente   = document.getElementById('cur-cb-docente');
    var cbJornada   = document.getElementById('cur-cb-jornada');
    var cbEstado    = document.getElementById('cur-cb-estado');

    var escPrgData = window.CUR_ESC_PRG || [];

    if (cbEscuela) {
        initCombobox(cbEscuela);
        cbEscuela.setOptions(escPrgData.map(function (e) { return { value: String(e.id), label: e.nombre }; }));

        var hiddenEsc = document.getElementById('cur-val-escuela');
        if (hiddenEsc) {
            hiddenEsc.addEventListener('change', function () {
                var escId = hiddenEsc.value;
                var esc = escPrgData.find(function (e) { return String(e.id) === escId; });
                if (cbPrograma) {
                    cbPrograma.reset();
                    cbPrograma.setOptions(esc && esc.programas.length
                        ? esc.programas
                        : []
                    );
                }
            });
        }
    }

    if (cbPrograma) {
        initCombobox(cbPrograma);
        cbPrograma.setOptions([]);
    }
    if (cbModalidad) {
        initCombobox(cbModalidad);
        cbModalidad.setOptions(['Presencial', 'Virtual', 'Mixta']);
        cbModalidad.setValue('Presencial', 'Presencial', {silent:true});
    }
    if (cbDocente) {
        initCombobox(cbDocente);
        cbDocente.setOptions([
            'María González Ruiz',
            'Alejandro Ríos Mora',
            'Laura Martínez Peña',
            'Andrés Zapata Villa',
            'Patricia López Castro',
            'Roberto Díaz Sierra',
            'Diana Vargas Nieto',
        ]);
    }
    if (cbJornada) {
        initCombobox(cbJornada);
        cbJornada.setOptions(['Mañana', 'Tarde', 'Noche']);
        cbJornada.setValue('Mañana', 'Mañana', {silent:true});
    }
    if (cbEstado) {
        initCombobox(cbEstado);
        cbEstado.setOptions([
            {value:'activo',        label:'Activo'},
            {value:'planificacion', label:'En planificación'},
            {value:'finalizado',    label:'Finalizado'},
        ]);
        cbEstado.setValue('activo', 'Activo', {silent:true});
    }

    /* ── Init ─────────────────────────────────── */
    applyFilters();
    renderStep();

}());
