/* ESAT — Admin: Docentes */
(function () {
    'use strict';

    var data  = window.DOC_DATA || [];
    var csrf  = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var FLASH = 'esat.admin.docentes.aviso';

    /* ── Backend ──────────────────────────────── */
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
    /* Guarda el aviso y recarga: tabla y KPI salen del servidor */
    function recargarConAviso(msg) {
        try { sessionStorage.setItem(FLASH, msg); } catch (e) {}
        location.reload();
    }
    var toastEl = document.getElementById('doc-toast');
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 5000);
    }
    try {
        var aviso = sessionStorage.getItem(FLASH);
        if (aviso) { sessionStorage.removeItem(FLASH); toast(aviso); }
    } catch (e) {}

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
    /* El color de la escuela sale de su sigla (sal, tur, adm...) */
    function escClass(d) {
        return d.sigla || 'adm';
    }
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
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
        var sig = escClass(d);
        var ini = initials(d);

        // Header avatar
        var avatarEl = document.getElementById('dw-avatar');
        if (avatarEl) {
            avatarEl.textContent = ini;
            avatarEl.className   = 'doc-drawer-avatar doc-avatar doc-esc-' + sig;
        }

        set('dw-nombre',    d.nombres + ' ' + d.apellidos);
        set('dw-cedula-txt', d.tipo_doc + ' ' + d.cedula);

        html('dw-badges', badge(d.estado) + ' ' + vincBadge(d.vinculacion) +
            ' <span class="doc-esc-tag doc-esc-' + sig + '">' + esc(d.escuela) + '</span>');

        // Tab General
        set('dw-email',     d.email);
        set('dw-telefono',  d.telefono);
        set('dw-fecha-nac', d.fecha_nac);
        set('dw-tipo-doc',  TIPOS_DOC[d.tipo_doc] || d.tipo_doc);
        set('dw-num-doc',   d.cedula);
        set('dw-direccion', d.direccion);

        // Tab Académico
        set('dw-titulo',      d.titulo);
        set('dw-especialidad',d.especialidad);
        set('dw-escuela-txt', d.escuela);
        set('dw-vinc-txt',    { tiempo_completo: 'Tiempo completo', hora_catedra: 'Hora cátedra', medio_tiempo: 'Medio tiempo' }[d.vinculacion] || d.vinculacion);

        var progList = document.getElementById('dw-programas-list');
        if (progList) {
            progList.innerHTML = (d.programas || []).length
                ? d.programas.map(function (p) { return '<span class="doc-prog-chip">' + esc(p) + '</span>'; }).join('')
                : '<span class="doc-person-cedula">Todavía no tiene cursos asignados en ningún programa.</span>';
        }

        // Tab Cursos
        var tbody    = document.getElementById('dw-cursos-body');
        var emptyMsg = document.getElementById('dw-cursos-empty');
        if (tbody) {
            var cursos = d.cursos || [];
            tbody.innerHTML = cursos.map(function (c) {
                return '<tr>'
                    + '<td><div class="doc-curso-nombre">' + esc(c.nombre) + '</div></td>'
                    + '<td><span class="doc-curso-prog">' + esc(c.programa) + '</span></td>'
                    + '<td>' + esc(c.grupo) + '</td>'
                    + '<td>' + esc(c.horario) + '</td>'
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

    // Activar / desactivar desde el drawer
    var toggleEstadoBtn = document.getElementById('doc-drawer-toggle-btn');
    toggleEstadoBtn.addEventListener('click', function () {
        if (!currentDoc) return;
        toggleEstadoBtn.disabled = true;
        enviar('PATCH', '/admin/docentes/' + currentDoc.id + '/estado')
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (err) { toggleEstadoBtn.disabled = false; toast(err.message); });
    });

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
    var editandoId   = null;
    var formError    = document.getElementById('doc-form-error');
    var TIPOS_DOC    = { CC: 'Cédula de ciudadanía', CE: 'Cédula de extranjería', PA: 'Pasaporte' };
    var VINC_LBL     = { tiempo_completo: 'Tiempo completo', medio_tiempo: 'Medio tiempo', hora_catedra: 'Hora cátedra' };
    var ESTADO_LBL   = { activo: 'Activo', en_proceso: 'En proceso', inactivo: 'Inactivo' };

    function campo(id) { return document.getElementById(id); }
    function valor(id) { return (campo(id).value || '').trim(); }

    function openModal(id) {
        currentStep = 1;
        editandoId = id || null;
        limpiarErrores();
        var d = id ? data.find(function (x) { return x.id === id; }) : null;
        modalTitle.textContent = d ? 'Editar docente' : 'Nuevo docente';
        document.getElementById('doc-nota-clave').hidden = !!d;

        campo('doc-inp-nombres').value      = d ? d.nombres : '';
        campo('doc-inp-apellidos').value    = d ? d.apellidos : '';
        campo('doc-inp-cedula').value       = d ? d.documento : '';
        campo('doc-inp-email').value        = d ? d.email : '';
        campo('doc-inp-telefono').value     = d ? d.telefono : '';
        campo('doc-inp-fecha-nac').value    = d ? (d.fecha_nac_iso || '') : '';
        campo('doc-inp-direccion').value    = d ? (d.direccion_raw || '') : '';
        campo('doc-inp-titulo').value       = d ? (d.titulo || '') : '';
        campo('doc-inp-especialidad').value = d ? (d.especialidad || '') : '';

        var tipo = d ? d.tipo_doc : 'CC';
        cbTipoDoc.setValue(tipo, TIPOS_DOC[tipo], { silent: true });
        if (d) cbEscuela.setValue(String(d.escuela_id), d.escuela, { silent: true });
        else cbEscuela.reset(null, { keepOptions: true });
        var vinc = d ? d.vinculacion : 'tiempo_completo';
        cbVinc.setValue(vinc, VINC_LBL[vinc], { silent: true });
        var est = d ? d.estado : 'activo';
        cbEstado.setValue(est, ESTADO_LBL[est], { silent: true });

        // Residencia: el departamento llena las ciudades y luego se marca la ciudad
        docCbDepto.reset(null, { keepOptions: true });
        docCbCiudad.reset('Elige el departamento primero');
        if (d && d.depto) {
            colombiaListo.then(function () {
                docCbDepto.setValue(d.depto, d.depto);
                if (d.ciudad) docCbCiudad.setValue(d.ciudad, d.ciudad, { silent: true });
            });
        }

        renderStep();
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
        if (btnNext) btnNext.textContent = currentStep === totalSteps ? (editandoId ? 'Guardar cambios' : 'Guardar docente') : 'Siguiente';
        // La nota de la contraseña acompaña el último paso al registrar
        var nota = document.getElementById('doc-nota-clave');
        if (nota) nota.hidden = !!editandoId || currentStep !== totalSteps;
        if (formError) formError.hidden = true;
        var body = modal.querySelector('.doc-modal-body');
        if (body) body.scrollTop = 0;
    }

    /* ── Validación por paso (el servidor vuelve a validar todo) ── */
    function limpiarErrores() {
        if (formError) formError.hidden = true;
        modal.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    }
    function marcarError(msg, id) {
        formError.textContent = msg;
        formError.hidden = false;
        var el = id && campo(id);
        if (el) {
            el.classList.add('is-invalid');
            (el.querySelector ? (el.querySelector('[data-combobox-trigger]') || el) : el).focus();
        }
        return false;
    }
    function validarPaso(paso) {
        limpiarErrores();
        if (paso === 1) {
            if (valor('doc-inp-nombres').length < 2) return marcarError('Escribe los nombres del docente.', 'doc-inp-nombres');
            if (valor('doc-inp-apellidos').length < 2) return marcarError('Escribe los apellidos del docente.', 'doc-inp-apellidos');
            if (!cbTipoDoc.value()) return marcarError('Selecciona el tipo de documento.', 'doc-cb-tipo-doc');
            var doc = valor('doc-inp-cedula').replace(cbTipoDoc.value() === 'PA' ? /[^A-Za-z0-9]/g : /\D/g, '');
            if (doc.length < 5 || doc.length > 15) return marcarError('El número de documento debe tener entre 5 y 15 caracteres.', 'doc-inp-cedula');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor('doc-inp-email'))) return marcarError('Escribe un correo válido.', 'doc-inp-email');
            if (!/^3\d{9}$/.test(valor('doc-inp-telefono').replace(/\D/g, ''))) return marcarError('El teléfono debe ser un celular de 10 dígitos que empiece por 3.', 'doc-inp-telefono');
            var nac = valor('doc-inp-fecha-nac');
            if (nac) {
                var lim = new Date(); lim.setFullYear(lim.getFullYear() - 18);
                if (new Date(nac + 'T00:00:00') > lim) return marcarError('El docente debe ser mayor de edad.', 'doc-inp-fecha-nac');
            }
            if (docCbDepto.value() && !docCbCiudad.value()) return marcarError('Selecciona la ciudad de residencia.', 'doc-cb-ciudad');
        }
        if (paso === 2) {
            if (valor('doc-inp-titulo').length < 3) return marcarError('Escribe el título profesional.', 'doc-inp-titulo');
            if (valor('doc-inp-especialidad').length < 3) return marcarError('Escribe la especialidad o énfasis.', 'doc-inp-especialidad');
            if (!cbEscuela.value()) return marcarError('Selecciona la escuela.', 'doc-cb-escuela');
        }
        if (paso === 3) {
            if (!cbVinc.value()) return marcarError('Selecciona el tipo de vinculación.', 'doc-cb-vinculacion');
            if (!cbEstado.value()) return marcarError('Selecciona el estado.', 'doc-cb-estado');
        }
        return true;
    }

    /* Campo del servidor → campo del formulario y paso donde está */
    var CAMPOS = {
        nombres: ['doc-inp-nombres', 1], apellidos: ['doc-inp-apellidos', 1], tipo_documento: ['doc-cb-tipo-doc', 1],
        numero_documento: ['doc-inp-cedula', 1], email: ['doc-inp-email', 1], telefono: ['doc-inp-telefono', 1],
        fecha_nacimiento: ['doc-inp-fecha-nac', 1], direccion: ['doc-inp-direccion', 1],
        departamento_residencia: ['doc-cb-depto', 1], ciudad_residencia: ['doc-cb-ciudad', 1],
        profesion: ['doc-inp-titulo', 2], titulo_academico: ['doc-inp-especialidad', 2], escuela_id: ['doc-cb-escuela', 2],
        vinculacion: ['doc-cb-vinculacion', 3], estado: ['doc-cb-estado', 3]
    };

    function guardar() {
        var cuerpo = {
            nombres: valor('doc-inp-nombres'), apellidos: valor('doc-inp-apellidos'),
            tipo_documento: cbTipoDoc.value(), numero_documento: valor('doc-inp-cedula'),
            email: valor('doc-inp-email'), telefono: valor('doc-inp-telefono'),
            fecha_nacimiento: valor('doc-inp-fecha-nac') || null, direccion: valor('doc-inp-direccion') || null,
            departamento_residencia: docCbDepto.value() || null, ciudad_residencia: docCbCiudad.value() || null,
            profesion: valor('doc-inp-titulo'), titulo_academico: valor('doc-inp-especialidad'),
            escuela_id: cbEscuela.value(), vinculacion: cbVinc.value(), estado: cbEstado.value()
        };
        btnNext.disabled = true;
        enviar(editandoId ? 'PUT' : 'POST', editandoId ? '/admin/docentes/' + editandoId : '/admin/docentes', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (err) {
                btnNext.disabled = false;
                var clave = err.errores && Object.keys(err.errores)[0];
                if (clave && CAMPOS[clave]) {
                    currentStep = CAMPOS[clave][1];
                    renderStep();
                    marcarError(err.errores[clave][0], CAMPOS[clave][0]);
                } else {
                    marcarError(err.message);
                }
            });
    }

    if (btnNext) btnNext.addEventListener('click', function () {
        if (!validarPaso(currentStep)) return;
        if (currentStep < totalSteps) { currentStep++; renderStep(); }
        else { guardar(); }
    });
    modal.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); formError.hidden = true; });
    modal.addEventListener('change', function (e) {
        var cb = e.target.closest('.app-combobox');
        if (cb) cb.classList.remove('is-invalid');
        formError.hidden = true;
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
        /* Se recoloca cuando la página se desplaza, pero no con el scroll de su propia
           lista: recolocar reinicia el alto de la lista y la devolvía al inicio. */
        function alScroll(e) {
            if (panel.contains(e.target)) return;
            posicionar();
        }
        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            if (!esAbsoluto) {
                posicionar();
                window.addEventListener('scroll', alScroll, true);
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
                window.removeEventListener('scroll', alScroll, true);
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
            root.classList.remove('is-invalid');
        };
        root.value = function () { return hidden.value; };
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
    initCombobox(cbEscuela);
    // Escuelas reales del catálogo
    cbEscuela.setOptions((window.DOC_ESCUELAS || []).map(function (e) { return { value: String(e.id), label: e.nombre }; }));

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
    var colombiaListo = Promise.resolve();
    if (docCbDepto) {
        colombiaListo = fetch('/assets/data/co-departamentos-ciudades.json')
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
