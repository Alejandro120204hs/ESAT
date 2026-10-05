/* ESAT — Admin: Docentes */
(function () {
    'use strict';

    var data = window.DOC_DATA || [];

    /* ── Utilidades ───────────────────────────── */
    function badge(estado) {
        var map = {
            activo:     '<span class="app-status-tag app-status-active">Activo</span>',
            en_proceso: '<span class="app-status-tag app-status-warn">En proceso</span>',
            inactivo:   '<span class="app-status-tag app-status-muted">Inactivo</span>',
        };
        return map[estado] || '';
    }
    function vincBadge(v) {
        var map = {
            tiempo_completo: '<span class="doc-vinc-tag doc-vinc-tc">Tiempo completo</span>',
            hora_catedra:    '<span class="doc-vinc-tag doc-vinc-hc">Hora cátedra</span>',
            medio_tiempo:    '<span class="doc-vinc-tag doc-vinc-mt">Medio tiempo</span>',
        };
        return map[v] || '';
    }
    function escClass(escuela) {
        var m = { 'Salud':'sal','Cocina y Turismo':'tur','Administrativa':'adm',
                  'Educación e Idiomas':'edu','Deporte y Cultura':'dep','Ciencias':'cie','Belleza':'bel' };
        return m[escuela] || 'adm';
    }
    function initials(d) {
        return ((d.nombres || '').charAt(0) + (d.apellidos || '').charAt(0)).toUpperCase();
    }

    /* ── Filtros / búsqueda / paginación ─────── */
    var search       = document.getElementById('doc-search');
    var selEsc       = document.getElementById('doc-filter-escuela');
    var selVinc      = document.getElementById('doc-filter-vinc');
    var selEst       = document.getElementById('doc-filter-estado');
    var countBdg     = document.getElementById('doc-count');
    var emptyEl      = document.getElementById('doc-empty');
    var paginationEl = document.getElementById('doc-pagination');
    var pagesEl      = document.getElementById('doc-pg-pages');
    var btnPrev      = document.getElementById('doc-pg-prev');
    var btnNext2     = document.getElementById('doc-pg-next');

    var PAGE_SIZE    = 10;
    var currentPage  = 1;
    var filteredRows = [];

    function applyFilters() {
        var q    = (search.value || '').toLowerCase().trim();
        var esc  = selEsc.value;
        var vinc = selVinc.value;
        var est  = selEst.value;
        var allRows = document.querySelectorAll('#doc-table .doc-row');

        filteredRows = [];
        allRows.forEach(function (row) {
            var match =
                (!q    || row.dataset.nombre.includes(q)) &&
                (!esc  || row.dataset.escuela    === esc) &&
                (!vinc || row.dataset.vinculacion === vinc) &&
                (!est  || row.dataset.estado      === est);
            if (match) filteredRows.push(row);
        });
        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        var total  = filteredRows.length;
        var pages  = Math.max(1, Math.ceil(total / PAGE_SIZE));
        var allRows = document.querySelectorAll('#doc-table .doc-row');
        var start  = (currentPage - 1) * PAGE_SIZE;
        var end    = start + PAGE_SIZE;

        allRows.forEach(function (row) { row.classList.add('is-hidden'); });
        filteredRows.forEach(function (row, i) {
            if (i >= start && i < end) row.classList.remove('is-hidden');
        });

        countBdg.textContent = total + ' docente' + (total !== 1 ? 's' : '');
        emptyEl.style.display = total === 0 ? 'block' : 'none';

        if (!paginationEl) return;
        paginationEl.style.display = pages <= 1 ? 'none' : '';
        if (pages > 1) {
            btnPrev.disabled  = currentPage === 1;
            btnNext2.disabled = currentPage === pages;
            pagesEl.innerHTML = '';
            buildPageRange(currentPage, pages).forEach(function (p) {
                if (p === '...') {
                    var sp = document.createElement('span');
                    sp.className   = 'doc-pg-ellipsis';
                    sp.textContent = '…';
                    pagesEl.appendChild(sp);
                } else {
                    var btn = document.createElement('button');
                    btn.className   = 'doc-pg-num' + (p === currentPage ? ' is-active' : '');
                    btn.textContent = p;
                    (function (pg) {
                        btn.addEventListener('click', function () { goToPage(pg); });
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
        var wrap = document.querySelector('.doc-table-wrap');
        if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (btnPrev)  btnPrev.addEventListener('click',  function () { if (currentPage > 1) goToPage(currentPage - 1); });
    if (btnNext2) btnNext2.addEventListener('click', function () {
        var pages = Math.ceil(filteredRows.length / PAGE_SIZE);
        if (currentPage < pages) goToPage(currentPage + 1);
    });
    if (search)  search.addEventListener('input',  applyFilters);
    if (selEsc)  selEsc.addEventListener('change', applyFilters);
    if (selVinc) selVinc.addEventListener('change', applyFilters);
    if (selEst)  selEst.addEventListener('change', applyFilters);

    /* ── Drawer ───────────────────────────────── */
    var drawer        = document.getElementById('doc-drawer');
    var drawerOverlay = document.getElementById('doc-drawer-overlay');
    var drawerClose   = document.getElementById('doc-drawer-close');
    var currentDoc    = null;

    function openDrawer(id) {
        currentDoc = data.find(function (d) { return d.id === id; });
        if (!currentDoc) return;
        fillDrawer(currentDoc);
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

    function fillDrawer(d) {
        var esc = escClass(d.escuela);
        var ini = initials(d);

        // Header avatar
        var avatarEl = document.getElementById('dw-avatar');
        if (avatarEl) {
            avatarEl.textContent = ini;
            avatarEl.className   = 'doc-drawer-avatar doc-avatar doc-esc-' + esc;
        }

        set('dw-nombre',    d.nombres + ' ' + d.apellidos);
        set('dw-cedula-txt', d.tipo_doc + ' ' + d.cedula);

        html('dw-badges', badge(d.estado) + ' ' + vincBadge(d.vinculacion) +
            ' <span class="doc-esc-tag doc-esc-' + esc + '">' + d.escuela + '</span>');

        // Tab General
        set('dw-email',     d.email);
        set('dw-telefono',  d.telefono);
        set('dw-fecha-nac', d.fecha_nac);
        set('dw-tipo-doc',  d.tipo_doc === 'CC' ? 'Cédula de ciudadanía' : d.tipo_doc);
        set('dw-num-doc',   d.cedula);
        set('dw-direccion', d.direccion);

        // Tab Académico
        set('dw-titulo',      d.titulo);
        set('dw-especialidad',d.especialidad);
        set('dw-escuela-txt', d.escuela);
        set('dw-vinc-txt',    { tiempo_completo: 'Tiempo completo', hora_catedra: 'Hora cátedra', medio_tiempo: 'Medio tiempo' }[d.vinculacion] || d.vinculacion);

        var progList = document.getElementById('dw-programas-list');
        if (progList) {
            progList.innerHTML = (d.programas || []).map(function (p) {
                return '<span class="doc-prog-chip">' + p + '</span>';
            }).join('');
        }

        // Tab Cursos
        var tbody    = document.getElementById('dw-cursos-body');
        var emptyMsg = document.getElementById('dw-cursos-empty');
        if (tbody) {
            var cursos = d.cursos || [];
            tbody.innerHTML = cursos.map(function (c) {
                return '<tr>'
                    + '<td><div class="doc-curso-nombre">' + c.nombre + '</div></td>'
                    + '<td><span class="doc-curso-prog">' + c.programa + '</span></td>'
                    + '<td>' + c.grupo + '</td>'
                    + '<td>' + c.horario + '</td>'
                    + '</tr>';
            }).join('');
            if (emptyMsg) emptyMsg.style.display = cursos.length === 0 ? 'block' : 'none';
        }

        // Footer toggle btn
        var toggleBtn = document.getElementById('doc-drawer-toggle-btn');
        if (toggleBtn) {
            toggleBtn.textContent = d.estado === 'activo' ? 'Desactivar docente' : 'Activar docente';
            toggleBtn.className   = 'doc-btn ' + (d.estado === 'activo' ? 'doc-btn-danger' : 'doc-btn-secondary');
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
        document.querySelectorAll('.doc-dtab').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.tab === tab);
        });
        document.querySelectorAll('.doc-tab-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.panel === tab);
        });
    }
    document.querySelectorAll('.doc-dtab').forEach(function (btn) {
        btn.addEventListener('click', function () { setDrawerTab(btn.dataset.tab); });
    });

    // Acciones de la tabla
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
    document.querySelectorAll('.doc-row').forEach(function (row) {
        row.addEventListener('click', function () {
            openDrawer(Number(row.dataset.id));
        });
    });

    // Editar desde drawer
    var editFromDrawer = document.getElementById('doc-drawer-edit-btn');
    if (editFromDrawer) {
        editFromDrawer.addEventListener('click', function () {
            if (currentDoc) { closeDrawer(); openModal(currentDoc.id); }
        });
    }

    /* ── Modal wizard ─────────────────────────── */
    var modal        = document.getElementById('doc-modal');
    var modalOverlay = document.getElementById('doc-modal-overlay');
    var modalClose   = document.getElementById('doc-modal-close');
    var btnNext      = document.getElementById('doc-btn-next');
    var btnBack      = document.getElementById('doc-btn-back');
    var stepLbl      = document.getElementById('doc-step-lbl');
    var modalTitle   = document.getElementById('doc-modal-title');
    var currentStep  = 1;
    var totalSteps   = 3;

    function openModal(id) {
        currentStep = 1;
        renderStep();
        modalTitle.textContent = id ? 'Editar docente' : 'Nuevo docente';
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

    var btnNuevo = document.getElementById('btn-nuevo-doc');
    if (btnNuevo) btnNuevo.addEventListener('click', function () { openModal(null); });

    function renderStep() {
        document.querySelectorAll('.doc-mstep').forEach(function (s) {
            s.classList.toggle('is-active', Number(s.dataset.step) === currentStep);
        });
        document.querySelectorAll('.doc-wstep').forEach(function (s) {
            var n = Number(s.dataset.step);
            s.classList.toggle('is-active', n === currentStep);
            s.classList.toggle('is-done',   n < currentStep);
        });
        if (stepLbl) stepLbl.textContent = 'Paso ' + currentStep + ' de ' + totalSteps;
        if (btnBack) btnBack.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        if (btnNext) btnNext.textContent = currentStep === totalSteps ? 'Guardar docente' : 'Siguiente';
    }

    if (btnNext) btnNext.addEventListener('click', function () {
        if (currentStep < totalSteps) { currentStep++; renderStep(); }
        else { closeModal(); }
    });
    if (btnBack) btnBack.addEventListener('click', function () {
        if (currentStep > 1) { currentStep--; renderStep(); }
    });

    /* ── Combobox (departamento / ciudad de residencia) ──── */
    function initCombobox(root) {
        var trigger     = root.querySelector('[data-combobox-trigger]');
        var label       = root.querySelector('[data-combobox-label]');
        var panel       = root.querySelector('[data-combobox-panel]');
        var searchInput = root.querySelector('[data-combobox-search]');
        var list        = root.querySelector('[data-combobox-list]');
        var hidden      = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;

        /* Abre hacia donde quepa: mide el alto real del panel (buscador + márgenes)
           y decide en cada apertura. data-direction="up" es solo una preferencia. */
        function posicionar() {
            var rect   = trigger.getBoundingClientRect();
            var margen = 12;
            panel.style.left  = rect.left + 'px';
            panel.style.width = rect.width + 'px';

            var abajo  = window.innerHeight - rect.bottom - 6 - margen;
            var arriba = rect.top - 6 - margen;
            list.style.maxHeight = '';
            var extra = panel.offsetHeight - list.offsetHeight;
            var tope  = parseInt(root.dataset.listMaxHeight) || 320;
            var necesario = Math.min(list.scrollHeight, tope) + extra;
            var haciaArriba = root.dataset.direction === 'up'
                ? (arriba >= necesario || arriba > abajo)
                : (abajo < necesario && arriba > abajo);
            var espacio = haciaArriba ? arriba : abajo;

            panel.style.top    = haciaArriba ? '' : (rect.bottom + 6) + 'px';
            panel.style.bottom = haciaArriba ? (window.innerHeight - rect.top + 6) + 'px' : '';
            list.style.maxHeight = Math.max(Math.min(tope, espacio - extra), 40) + 'px';
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
            var qq = q.trim().toLowerCase();
            var vis = 0;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                var ok = qq === '' || li.textContent.toLowerCase().indexOf(qq) !== -1;
                li.hidden = !ok;
                if (ok) vis++;
            });
            var empty = list.querySelector('.app-combobox-empty');
            if (empty) empty.hidden = vis > 0;
        }

        trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(); });
        if (searchInput) searchInput.addEventListener('input', function () { filtrar(searchInput.value); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });

        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (opcion) {
                var val = typeof opcion === 'object' ? opcion.value : opcion;
                var lbl = typeof opcion === 'object' ? opcion.label : opcion;
                var li   = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = val;
                li.dataset.label = lbl;
                var span = document.createElement('span');
                span.textContent = lbl;
                var svg  = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                li.appendChild(span);
                li.appendChild(svg);
                li.addEventListener('click', function () { root.setValue(val, lbl); cerrar(); });
                list.appendChild(li);
            });
            var empty = document.createElement('li');
            empty.className   = 'app-combobox-empty';
            empty.hidden      = opciones.length > 0;
            empty.textContent = 'Sin resultados';
            list.appendChild(empty);
        };
        root.setValue = function (valor, etiqueta, opts) {
            hidden.value      = valor || '';
            label.textContent = etiqueta || valor || placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === valor);
            });
            if (!(opts && opts.silent)) {
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        root.reset = function (nuevoPlaceholder, opts) {
            if (nuevoPlaceholder) placeholder = nuevoPlaceholder;
            if (!(opts && opts.keepOptions)) { list.innerHTML = ''; }
            else { list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.remove('is-selected'); }); }
            hidden.value    = '';
            label.textContent = placeholder;
        };
    }

    // Tipo de documento
    var cbTipoDoc = document.getElementById('doc-cb-tipo-doc');
    if (cbTipoDoc) {
        initCombobox(cbTipoDoc);
        cbTipoDoc.setOptions([
            { value: 'CC', label: 'Cédula de ciudadanía' },
            { value: 'CE', label: 'Cédula de extranjería' },
            { value: 'PA', label: 'Pasaporte' }
        ]);
        cbTipoDoc.setValue('CC', 'Cédula de ciudadanía', { silent: true });
    }

    // Escuela asignada
    var cbEscuela = document.getElementById('doc-cb-escuela');
    if (cbEscuela) {
        initCombobox(cbEscuela);
        cbEscuela.setOptions(['Salud','Cocina y Turismo','Administrativa',
            'Educación e Idiomas','Deporte y Cultura','Ciencias','Belleza']);
    }

    // Tipo de vinculación
    var cbVinc = document.getElementById('doc-cb-vinculacion');
    if (cbVinc) {
        initCombobox(cbVinc);
        cbVinc.setOptions([
            { value: 'tiempo_completo', label: 'Tiempo completo' },
            { value: 'medio_tiempo',    label: 'Medio tiempo' },
            { value: 'hora_catedra',    label: 'Hora cátedra' }
        ]);
        cbVinc.setValue('tiempo_completo', 'Tiempo completo', { silent: true });
    }

    // Estado
    var cbEstado = document.getElementById('doc-cb-estado');
    if (cbEstado) {
        initCombobox(cbEstado);
        cbEstado.setOptions([
            { value: 'activo',     label: 'Activo' },
            { value: 'en_proceso', label: 'En proceso' },
            { value: 'inactivo',   label: 'Inactivo' }
        ]);
        cbEstado.setValue('activo', 'Activo', { silent: true });
    }

    var docCbDepto  = document.getElementById('doc-cb-depto');
    var docCbCiudad = document.getElementById('doc-cb-ciudad');
    if (docCbDepto)  initCombobox(docCbDepto);
    if (docCbCiudad) initCombobox(docCbCiudad);

    var colombiaData = null;
    if (docCbDepto) {
        fetch('/assets/data/co-departamentos-ciudades.json')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                colombiaData = data;
                docCbDepto.setOptions(data.map(function (d) { return d.departamento; }));
            });

        var deptoHidden = docCbDepto.querySelector('[data-combobox-value]');
        deptoHidden.addEventListener('change', function () {
            if (!docCbCiudad) return;
            var nombre = deptoHidden.value;
            if (!nombre || !colombiaData) { docCbCiudad.reset('Elige el departamento primero'); return; }
            var depto   = colombiaData.find(function (d) { return d.departamento === nombre; });
            var ciudades = depto ? depto.ciudades : [];
            docCbCiudad.reset('Selecciona una ciudad');
            docCbCiudad.setOptions(ciudades);
        });
    }

    // Filtros de la tabla
    var cbFiltEsc  = document.getElementById('doc-cb-filter-escuela');
    var cbFiltVinc = document.getElementById('doc-cb-filter-vinc');
    var cbFiltEst  = document.getElementById('doc-cb-filter-estado');

    if (cbFiltEsc) {
        initCombobox(cbFiltEsc);
        var escOpts = JSON.parse(cbFiltEsc.dataset.options || '[]');
        cbFiltEsc.setOptions([{value:'', label:'Todas las escuelas'}].concat(escOpts.map(function(v){return {value:v, label:v};})));
        cbFiltEsc.setValue('', 'Todas las escuelas', {silent:true});
    }
    if (cbFiltVinc) {
        initCombobox(cbFiltVinc);
        cbFiltVinc.setOptions([
            {value:'', label:'Todas las vinculaciones'},
            {value:'tiempo_completo', label:'Tiempo completo'},
            {value:'hora_catedra', label:'Hora cátedra'},
            {value:'medio_tiempo', label:'Medio tiempo'},
        ]);
        cbFiltVinc.setValue('', 'Todas las vinculaciones', {silent:true});
    }
    if (cbFiltEst) {
        initCombobox(cbFiltEst);
        cbFiltEst.setOptions([
            {value:'', label:'Todos los estados'},
            {value:'activo', label:'Activo'},
            {value:'en_proceso', label:'En proceso'},
            {value:'inactivo', label:'Inactivo'},
        ]);
        cbFiltEst.setValue('', 'Todos los estados', {silent:true});
    }

    // Inicializar
    applyFilters();
    renderStep();

}());
