/* ESAT — Admin: Programas */
(function () {
    'use strict';

    var data = window.PRG_DATA || [];

    /* ── Utilidades ───────────────────────────── */
    function fmt(n) {
        return '$ ' + Number(n).toLocaleString('es-CO');
    }
    function badge(estado) {
        var map = {
            activo:         '<span class="app-status-tag app-status-active">Activo</span>',
            en_aprobacion:  '<span class="app-status-tag app-status-warn">En aprobación</span>',
            inactivo:       '<span class="app-status-tag app-status-muted">Inactivo</span>',
        };
        return map[estado] || '';
    }
    function escClass(escuela) {
        var m = { 'Salud':'sal','Cocina y Turismo':'tur','Administrativa':'adm','Educación e Idiomas':'edu','Deporte y Cultura':'dep','Ciencias':'cie','Belleza':'bel' };
        return m[escuela] || 'adm';
    }

    /* ── Filtros / búsqueda / paginación ─────────────── */
    var search       = document.getElementById('prg-search');
    var selEsc       = document.getElementById('prg-filter-escuela');
    var selEst       = document.getElementById('prg-filter-estado');
    var countBdg     = document.getElementById('prg-count');
    var emptyEl      = document.getElementById('prg-empty');
    var paginationEl = document.getElementById('prg-pagination');
    var pagesEl      = document.getElementById('prg-pg-pages');
    var btnPrev      = document.getElementById('prg-pg-prev');
    var btnNext      = document.getElementById('prg-pg-next');

    var PAGE_SIZE    = 10;
    var currentPage  = 1;
    var filteredRows = [];

    function applyFilters() {
        var q   = (search.value || '').toLowerCase().trim();
        var esc = selEsc.value;
        var est = selEst.value;
        var allRows = document.querySelectorAll('#prg-table .prg-row');

        filteredRows = [];
        allRows.forEach(function(row) {
            var match =
                (!q   || row.dataset.nombre.includes(q)) &&
                (!esc || row.dataset.escuela === esc) &&
                (!est || row.dataset.estado  === est);
            if (match) filteredRows.push(row);
        });

        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        var total   = filteredRows.length;
        var pages   = Math.max(1, Math.ceil(total / PAGE_SIZE));
        var allRows = document.querySelectorAll('#prg-table .prg-row');
        var start   = (currentPage - 1) * PAGE_SIZE;
        var end     = start + PAGE_SIZE;

        allRows.forEach(function(row) { row.classList.add('is-hidden'); });
        filteredRows.forEach(function(row, i) {
            if (i >= start && i < end) row.classList.remove('is-hidden');
        });

        countBdg.textContent = total + ' programa' + (total !== 1 ? 's' : '');
        emptyEl.style.display = total === 0 ? 'block' : 'none';

        if (!paginationEl) return;
        paginationEl.style.display = pages <= 1 ? 'none' : '';

        if (pages > 1) {
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === pages;

            pagesEl.innerHTML = '';
            buildPageRange(currentPage, pages).forEach(function(p) {
                if (p === '...') {
                    var sp = document.createElement('span');
                    sp.className = 'prg-pg-ellipsis';
                    sp.textContent = '…';
                    pagesEl.appendChild(sp);
                } else {
                    var btn = document.createElement('button');
                    btn.className = 'prg-pg-num' + (p === currentPage ? ' is-active' : '');
                    btn.textContent = p;
                    (function(pg) {
                        btn.addEventListener('click', function() { goToPage(pg); });
                    }(p));
                    pagesEl.appendChild(btn);
                }
            });
        }
    }

    function buildPageRange(current, total) {
        if (total <= 7) {
            var r = [];
            for (var i = 1; i <= total; i++) r.push(i);
            return r;
        }
        var range = [1];
        if (current > 3) range.push('...');
        for (var i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) range.push(i);
        if (current < total - 2) range.push('...');
        range.push(total);
        return range;
    }

    function goToPage(p) {
        currentPage = p;
        renderPage();
        var wrap = document.querySelector('.prg-table-wrap');
        if (wrap) wrap.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    if (btnPrev) btnPrev.addEventListener('click', function() {
        if (currentPage > 1) goToPage(currentPage - 1);
    });
    if (btnNext) btnNext.addEventListener('click', function() {
        var pages = Math.ceil(filteredRows.length / PAGE_SIZE);
        if (currentPage < pages) goToPage(currentPage + 1);
    });

    if (search)  search.addEventListener('input',  applyFilters);
    if (selEsc)  selEsc.addEventListener('change', applyFilters);
    if (selEst)  selEst.addEventListener('change', applyFilters);

    /* ── Drawer ───────────────────────────────── */
    var drawer        = document.getElementById('prg-drawer');
    var drawerOverlay = document.getElementById('prg-drawer-overlay');
    var drawerClose   = document.getElementById('prg-drawer-close');
    var currentPrg    = null;

    function openDrawer(id) {
        currentPrg = data.find(function (p) { return p.id === id; });
        if (!currentPrg) return;
        fillDrawer(currentPrg);
        // reset to first tab
        setDrawerTab('general');
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);
    if (drawerClose)   drawerClose.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDrawer(); closeModal(); }
    });

    function fillDrawer(p) {
        set('dw-codigo',     p.codigo);
        set('dw-nombre',     p.nombre);
        set('dw-escuela',    p.escuela);
        set('dw-nivel',      p.nivel);
        set('dw-modalidad',  p.modalidad);
        set('dw-duracion',   Number(p.horas).toLocaleString('es-CO') + ' h · ' + p.meses + ' meses');
        set('dw-jornadas',   p.jornadas.join(' · '));
        set('dw-cupo',       p.cupo + ' estudiantes por grupo');
        set('dw-resolucion', p.resolucion);
        set('dw-descripcion',p.descripcion);
        set('dw-perfil',     p.perfil_egreso);
        set('dw-valor-programa', fmt(p.valor_programa));
        set('dw-matricula',      fmt(p.matricula));
        set('dw-valor-mensual',  fmt(p.valor_mensual) + ' / mes');
        html('dw-badges',        badge(p.estado) + ' <span class="prg-esc-tag prg-esc-' + escClass(p.escuela) + '">' + p.escuela + '</span>');
        set('dw-doc-res',    p.resolucion);
        set('dw-est-txt',    p.estudiantes + ' estudiantes matriculados en este programa');

        // Módulos
        var tbody = document.getElementById('dw-modulos-body');
        if (tbody) {
            var totalH = 0;
            tbody.innerHTML = p.modulos.map(function (m, i) {
                totalH += m.horas;
                return '<tr>'
                    + '<td class="prg-mod-num">' + (i+1) + '</td>'
                    + '<td>' + m.nombre + '</td>'
                    + '<td class="prg-mod-horas">' + m.horas + ' h</td>'
                    + '<td class="prg-mod-docente">' + m.docente + '</td>'
                    + '</tr>';
            }).join('');
            set('dw-horas-total', Number(totalH).toLocaleString('es-CO') + ' h totales');
        }

        // Grupos
        var gruposGrid = document.getElementById('dw-grupos-grid');
        if (gruposGrid) {
            gruposGrid.innerHTML = p.grupos.map(function (g) {
                var pct = Math.round((g.inscritos / g.cupo) * 100);
                return '<div class="prg-grupo-card">'
                    + '<div>'
                        + '<div class="prg-grupo-nombre">' + g.nombre + '</div>'
                        + '<div class="prg-grupo-meta">Jornada ' + g.jornada + '</div>'
                    + '</div>'
                    + '<div class="prg-grupo-cupo">'
                        + '<div class="prg-grupo-cupo-num">' + g.inscritos + ' / ' + g.cupo + '</div>'
                        + '<div class="prg-grupo-cupo-lbl">inscritos</div>'
                    + '</div>'
                    + '<div class="prg-grupo-bar"><div class="prg-grupo-fill" style="width:' + pct + '%"></div></div>'
                    + '<div class="prg-grupo-fechas">'
                        + '<span>Inicio: <strong>' + g.inicio + '</strong></span>'
                        + '<span>Fin: <strong>' + g.fin + '</strong></span>'
                    + '</div>'
                    + '</div>';
            }).join('');
        }

        // Footer button
        var toggleBtn = document.getElementById('prg-drawer-toggle-btn');
        if (toggleBtn) {
            toggleBtn.textContent = p.estado === 'activo' ? 'Desactivar programa' : 'Activar programa';
            toggleBtn.className = 'prg-btn ' + (p.estado === 'activo' ? 'prg-btn-danger' : 'prg-btn-secondary');
        }
    }

    function set(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }
    function html(id, markup) {
        var el = document.getElementById(id);
        if (el) el.innerHTML = markup;
    }

    // Tabs del drawer
    function setDrawerTab(tab) {
        document.querySelectorAll('.prg-dtab').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.tab === tab);
        });
        document.querySelectorAll('.prg-tab-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.panel === tab);
        });
    }
    document.querySelectorAll('.prg-dtab').forEach(function (btn) {
        btn.addEventListener('click', function () { setDrawerTab(btn.dataset.tab); });
    });

    // Acciones de la tabla → abrir drawer
    document.querySelectorAll('[data-action="ver"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openDrawer(Number(btn.dataset.id));
        });
    });
    document.querySelectorAll('[data-action="editar"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openModal(Number(btn.dataset.id));
        });
    });
    document.querySelectorAll('.prg-row').forEach(function (row) {
        row.addEventListener('click', function () {
            openDrawer(Number(row.dataset.id));
        });
    });

    // Editar desde drawer
    var editFromDrawer = document.getElementById('prg-drawer-edit-btn');
    if (editFromDrawer) {
        editFromDrawer.addEventListener('click', function () {
            if (currentPrg) { closeDrawer(); openModal(currentPrg.id); }
        });
    }

    /* ── Modal wizard ─────────────────────────── */
    var modal        = document.getElementById('prg-modal');
    var modalOverlay = document.getElementById('prg-modal-overlay');
    var modalClose   = document.getElementById('prg-modal-close');
    var btnNext      = document.getElementById('prg-btn-next');
    var btnBack      = document.getElementById('prg-btn-back');
    var stepLbl      = document.getElementById('prg-step-lbl');
    var modalTitle   = document.getElementById('prg-modal-title');
    var currentStep  = 1;
    var totalSteps   = 3;

    function openModal(id) {
        currentStep = 1;
        renderStep();
        if (id) {
            modalTitle.textContent = 'Editar programa';
        } else {
            modalTitle.textContent = 'Nuevo programa';
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    if (modalOverlay) modalOverlay.addEventListener('click', closeModal);
    if (modalClose)   modalClose.addEventListener('click', closeModal);

    document.getElementById('btn-nuevo')?.addEventListener('click', function () { openModal(null); });

    function renderStep() {
        // Steps
        document.querySelectorAll('.prg-mstep').forEach(function (s) {
            s.classList.toggle('is-active', Number(s.dataset.step) === currentStep);
        });
        document.querySelectorAll('.prg-wstep').forEach(function (s) {
            var n = Number(s.dataset.step);
            s.classList.toggle('is-active', n === currentStep);
            s.classList.toggle('is-done', n < currentStep);
        });
        if (stepLbl) stepLbl.textContent = 'Paso ' + currentStep + ' de ' + totalSteps;
        if (btnBack) btnBack.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        if (btnNext) btnNext.textContent = currentStep === totalSteps ? 'Guardar programa' : 'Siguiente';
    }

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (currentStep < totalSteps) {
                currentStep++;
                renderStep();
            } else {
                closeModal();
            }
        });
    }
    if (btnBack) {
        btnBack.addEventListener('click', function () {
            if (currentStep > 1) { currentStep--; renderStep(); }
        });
    }

    // (sin cálculo en paso 3 — el sistema calcula totales en rol estudiante)

    /* ── Combobox genérico ────────────────────── */
    function initCombobox(root) {
        var trigger     = root.querySelector('[data-combobox-trigger]');
        var panel       = root.querySelector('[data-combobox-panel]');
        var list        = root.querySelector('[data-combobox-list]');
        var searchInput = root.querySelector('[data-combobox-search]');
        var hidden      = root.querySelector('[data-combobox-value]');
        var label       = root.querySelector('[data-combobox-label]');
        var placeholder = label.textContent;

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
                espacio = Math.min(rect.top - 6 - margen, 320);
            } else {
                panel.style.bottom = '';
                panel.style.top    = (rect.bottom + 6) + 'px';
                espacio = window.innerHeight - (rect.bottom + 6) - margen;
            }
            var searchWrap = searchInput && searchInput.closest('.app-combobox-search-wrap');
            var searchAlto = searchWrap ? searchWrap.offsetHeight : 0;
            list.style.maxHeight = Math.max(espacio - searchAlto - 14, 40) + 'px';
        }
        var esAbsoluto = root.dataset.position === 'absolute';
        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            if (!esAbsoluto) {
                posicionar();
                window.addEventListener('scroll', posicionar, true);
                window.addEventListener('resize', posicionar);
            }
            if (searchInput) {
                searchInput.value = '';
                filtrar('');
                searchInput.focus();
            }
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

        trigger.addEventListener('click', function () {
            panel.hidden ? abrir() : cerrar();
        });
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                filtrar(searchInput.value);
            });
        }
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) cerrar();
        });

        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (opcion) {
                var val = typeof opcion === 'object' ? opcion.value : opcion;
                var lbl = typeof opcion === 'object' ? opcion.label : opcion;
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = val;
                li.dataset.label = lbl;
                var span = document.createElement('span');
                span.textContent = lbl;
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                li.appendChild(span);
                li.appendChild(svg);
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
            if (!(opts && opts.silent)) {
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        root.reset = function () {
            hidden.value = '';
            label.textContent = placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.remove('is-selected');
            });
        };
    }

    /* ── Filtros de la tabla ─────────────────── */
    var cbFiltEsc = document.getElementById('prg-cb-filter-escuela');
    var cbFiltEst = document.getElementById('prg-cb-filter-estado');

    if (cbFiltEsc) {
        initCombobox(cbFiltEsc);
        var pEscOpts = JSON.parse(cbFiltEsc.dataset.options || '[]');
        cbFiltEsc.setOptions([{value:'', label:'Todas las escuelas'}].concat(pEscOpts.map(function(v){return {value:v, label:v};})));
        cbFiltEsc.setValue('', 'Todas las escuelas', {silent:true});
    }
    if (cbFiltEst) {
        initCombobox(cbFiltEst);
        cbFiltEst.setOptions([
            {value:'', label:'Todos los estados'},
            {value:'activo', label:'Activo'},
            {value:'en_aprobacion', label:'En aprobación'},
            {value:'inactivo', label:'Inactivo'},
        ]);
        cbFiltEst.setValue('', 'Todos los estados', {silent:true});
    }

    /* ── Inicializar comboboxes del wizard ───── */
    var cbNivel    = document.getElementById('prg-cb-nivel');
    var cbEscuela  = document.getElementById('prg-cb-escuela');
    var cbModalidad= document.getElementById('prg-cb-modalidad');
    var cbEstado   = document.getElementById('prg-cb-estado');

    if (cbNivel) {
        initCombobox(cbNivel);
        cbNivel.setOptions(['Técnico Laboral', 'Técnico Laboral por Competencias', 'Auxiliar']);
    }
    if (cbEscuela) {
        initCombobox(cbEscuela);
        cbEscuela.setOptions(['Salud', 'Cocina y Turismo', 'Administrativa', 'Deporte y Cultura', 'Ciencias', 'Educación e Idiomas', 'Belleza']);
    }
    if (cbModalidad) {
        initCombobox(cbModalidad);
        cbModalidad.setOptions(['Presencial', 'Virtual', 'Mixta']);
        cbModalidad.setValue('Presencial', 'Presencial', { silent: true });
    }
    if (cbEstado) {
        initCombobox(cbEstado);
        cbEstado.setOptions([
            { value: 'activo',        label: 'Activo' },
            { value: 'en_aprobacion', label: 'En proceso de aprobación' },
            { value: 'inactivo',      label: 'Inactivo' },
        ]);
        cbEstado.setValue('activo', 'Activo', { silent: true });
    }

    // Inicializar
    applyFilters();
    renderStep();

}());
