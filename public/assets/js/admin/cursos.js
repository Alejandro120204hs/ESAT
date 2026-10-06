/* ESAT — Admin: Grupos (cursos) */
(function () {
    'use strict';

    var data       = window.CUR_DATA || [];
    var escPrgData = window.CUR_ESC_PRG || [];
    var docentes   = window.CUR_DOCENTES || [];
    var csrf       = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var FLASH      = 'esat.admin.grupos.aviso';

    var ESTADO_LBL = { activo: 'Activo', planificacion: 'En planificación', finalizado: 'Finalizado' };
    var DIA_CORTO  = { 'Lunes':'Lun','Martes':'Mar','Miércoles':'Mié','Jueves':'Jue','Viernes':'Vie','Sábado':'Sáb' };

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
    function recargarConAviso(msg) {
        try { sessionStorage.setItem(FLASH, msg); } catch (e) {}
        location.reload();
    }
    var toastEl = document.getElementById('cur-toast');
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
    function estadoTag(estado) {
        var map = {
            activo:        '<span class="cur-estado-tag cur-estado-activo">Activo</span>',
            planificacion: '<span class="cur-estado-tag cur-estado-planificacion">En planificación</span>',
            finalizado:    '<span class="cur-estado-tag cur-estado-finalizado">Finalizado</span>',
        };
        return map[estado] || '';
    }
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }
    var avatarColors = ['#e07b39','#2563eb','#16a34a','#7c3aed','#0369a1','#b91c1c','#ca8a04','#0d9488'];
    function avatarColor(i) { return avatarColors[i % avatarColors.length]; }
    function initials(nombre) {
        var parts = nombre.trim().split(' ');
        return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
    }
    function fmtNum(n) { return Number(n).toLocaleString('es-CO'); }
    function toMin(t) { var p = t.split(':'); return parseInt(p[0]) * 60 + parseInt(p[1]); }
    function programaDe(id) {
        for (var i = 0; i < escPrgData.length; i++) {
            var p = escPrgData[i].programas.find(function (x) { return x.id === id; });
            if (p) return { programa: p, escuela: escPrgData[i] };
        }
        return null;
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
        set('dw-nombre',      c.grupo + ' · ' + c.programa.replace('Técnico Laboral en ', ''));
        set('dw-codigo',      c.codigo);
        set('dw-programa',    c.programa);
        set('dw-escuela-val', c.escuela);
        set('dw-docente-val', c.docente);
        set('dw-modalidad',   c.modalidad);
        set('dw-jornada-val', c.jornada);
        set('dw-fecha-inicio',c.fecha_inicio);
        set('dw-fecha-fin',   c.fecha_fin);
        set('dw-cupo-num',    c.inscritos + ' / ' + c.cupo_max + ' estudiantes');

        var pct = c.cupo_max ? Math.round((c.inscritos / c.cupo_max) * 100) : 0;
        var fill = document.getElementById('dw-cupo-fill');
        if (fill) { fill.style.width = pct + '%'; fill.className = 'cur-cupo-drawer-fill' + (pct >= 100 ? ' is-full' : pct < 50 ? ' is-low' : ''); }

        html('dw-badges',
            estadoTag(c.estado) +
            ' <span class="cur-esc-tag cur-esc-' + esc(c.sigla) + '">' + esc(c.escuela) + '</span>' +
            ' <span class="cur-jornada-tag">' + esc(c.jornada) + '</span>'
        );

        // Horario: sesiones del grupo y horas frente al tope del programa
        html('dw-dias', c.sesiones.length
            ? '<div class="cur-ses-lista">' + c.sesiones.map(function (s) {
                var min = toMin(s.hora_fin) - toMin(s.hora_inicio);
                return '<div class="cur-ses-item"><strong>' + s.dia + '</strong><span>' + s.hora_inicio + ' – ' + s.hora_fin + ' · ' + (min / 60).toLocaleString('es-CO') + ' h</span></div>';
            }).join('') + '</div>'
            : '<p class="cur-form-hint">Este grupo todavía no tiene días ni horas de clase. Se asignan en Horarios.</p>');
        set('dw-hora', c.sesiones.length ? c.horas_semana.toLocaleString('es-CO') + ' h de clase' : '—');
        set('dw-duracion', c.sesiones.length
            ? '≈ ' + fmtNum(c.horas_programadas) + ' h de ' + fmtNum(c.horas_programa) + ' h del programa'
            : fmtNum(c.horas_programa) + ' h del programa');

        // Estudiantes
        var estBody = document.getElementById('dw-est-list');
        if (estBody) {
            estBody.innerHTML = c.estudiantes.length
                ? c.estudiantes.map(function (est, i) {
                    return '<div class="cur-est-item">'
                        + '<div class="cur-est-avatar" style="background:' + avatarColor(i) + '">' + esc(initials(est.nombre)) + '</div>'
                        + '<div><div class="cur-est-nombre">' + esc(est.nombre) + '</div><div class="cur-est-doc">' + esc(est.cedula) + '</div></div>'
                        + '</div>';
                }).join('')
                : '<p class="cur-form-hint">Todavía no hay estudiantes inscritos en este grupo.</p>';
        }

        var toggleBtn = document.getElementById('cur-drawer-toggle-btn');
        if (c.estado === 'activo') {
            toggleBtn.textContent = 'Finalizar grupo';
            toggleBtn.className = 'cur-btn cur-btn-danger';
        } else {
            toggleBtn.textContent = c.estado === 'planificacion' ? 'Activar grupo' : 'Reabrir grupo';
            toggleBtn.className = 'cur-btn cur-btn-secondary';
        }
        toggleBtn.disabled = false;
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
    editFromDrawer.addEventListener('click', function () {
        if (currentCur) { closeDrawer(); openModal(currentCur.id); }
    });

    // Activar / finalizar / reabrir desde la ficha
    var toggleEstadoBtn = document.getElementById('cur-drawer-toggle-btn');
    toggleEstadoBtn.addEventListener('click', function () {
        if (!currentCur) return;
        toggleEstadoBtn.disabled = true;
        enviar('PATCH', '/admin/cursos/' + currentCur.id + '/estado')
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (err) { toggleEstadoBtn.disabled = false; toast(err.message); });
    });

    /* ── Modal wizard ─────────────────────────── */
    var modal        = document.getElementById('cur-modal');
    var modalOverlay = document.getElementById('cur-modal-overlay');
    var modalClose   = document.getElementById('cur-modal-close');
    var btnWizNext   = document.getElementById('cur-btn-next');
    var btnWizBack   = document.getElementById('cur-btn-back');
    var stepLbl      = document.getElementById('cur-step-lbl');
    var modalTitle   = document.getElementById('cur-modal-title');
    var formError    = document.getElementById('cur-form-error');
    var finHint      = document.getElementById('cur-fin-hint');
    var currentStep  = 1;
    var totalSteps   = 2;
    var editandoId   = null;

    function campo(id) { return document.getElementById(id); }
    function valor(id) { return (campo(id).value || '').trim(); }

    function openModal(id) {
        currentStep = 1;
        editandoId = id || null;
        limpiarErrores();
        var c = id ? data.find(function (x) { return x.id === id; }) : null;
        if (modalTitle) modalTitle.textContent = c ? 'Editar grupo' : 'Nuevo grupo';

        campo('cur-inp-codigo').value = c ? c.codigo : '';
        campo('cur-inp-grupo').value  = c ? c.grupo : '';
        campo('cur-inp-inicio').value = c ? c.fecha_inicio_iso : '';

        cbEscuela.reset(); cbPrograma.reset(); cbPrograma.setOptions([]);
        pintarDelPrograma();
        cbJornada.reset(); cbJornada.setOptions([]);
        if (c) {
            cbEscuela.setValue(String(c.escuela_id), c.escuela);                   // carga sus programas
            cbPrograma.setValue(String(c.programa_id), c.programa);               // carga jornadas y topes
            cbJornada.setValue(c.jornada, c.jornada, { silent: true });
            cbEstado.setValue(c.estado, ESTADO_LBL[c.estado], { silent: true });
            if (c.docente_id) cbDocente.setValue(String(c.docente_id), c.docente, { silent: true });
            else cbDocente.reset();
        } else {
            cbEstado.setValue('planificacion', ESTADO_LBL.planificacion, { silent: true });
            cbDocente.reset();
        }
        pintarFinHint();
        renderStep();
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
        if (btnWizNext) btnWizNext.textContent = currentStep === totalSteps ? (editandoId ? 'Guardar cambios' : 'Guardar grupo') : 'Siguiente';
        if (formError)  formError.hidden = true;
    }

    /* Fecha de fin sugerida = inicio + duración del programa */
    function programaActual() {
        var r = programaDe(parseInt(cbPrograma.value()));
        return r ? r.programa : null;
    }
    function sumarMeses(iso, meses) {
        var p = iso.split('-').map(Number);
        var d = new Date(p[0], p[1] - 1 + meses, 1);
        var ultimo = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
        d.setDate(Math.min(p[2], ultimo));
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    /* La fecha de fin no se escribe: es el inicio más la duración del programa (igual que en el servidor) */
    function pintarFinHint() {
        var p = programaActual();
        var ini = valor('cur-inp-inicio');
        campo('cur-inp-fin').value = '';
        if (!p) { finHint.textContent = ''; return; }
        var dura = 'El programa dura ' + p.meses + (p.meses === 1 ? ' mes' : ' meses');
        if (!ini) { finHint.textContent = dura + '.'; return; }
        var f = sumarMeses(ini, p.meses).split('-');
        campo('cur-inp-fin').value = f[2] + '/' + f[1] + '/' + f[0];
        finHint.textContent = dura + ': la fecha de fin se calcula sola.';
    }
    campo('cur-inp-inicio').addEventListener('input', pintarFinHint);

    /* ── Validación por paso (el servidor vuelve a validar todo) ── */
    function limpiarErrores() {
        if (formError) formError.hidden = true;
        document.querySelectorAll('#cur-modal .is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
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
            if (!cbEscuela.value()) return marcarError('Selecciona la escuela.', 'cur-cb-escuela');
            if (!cbPrograma.value()) return marcarError('Selecciona el programa.', 'cur-cb-programa');
        }
        if (paso === 2) {
            if (cbEstado.value() === 'activo' && !cbDocente.value()) return marcarError('Asigna un docente para dejar el grupo activo.', 'cur-cb-docente');
            if (!cbJornada.value()) return marcarError('Selecciona la jornada.', 'cur-cb-jornada');
            if (!valor('cur-inp-inicio')) return marcarError('Indica la fecha de inicio.', 'cur-inp-inicio');
            if (!cbEstado.value()) return marcarError('Selecciona el estado.', 'cur-cb-estado');
        }
        return true;
    }

    var CAMPOS = {
        programa_id: ['cur-cb-programa', 1], codigo: ['cur-inp-codigo', 1], nombre: ['cur-inp-grupo', 1],
        docente_id: ['cur-cb-docente', 2], jornada: ['cur-cb-jornada', 2],
        fecha_inicio: ['cur-inp-inicio', 2], estado: ['cur-cb-estado', 2]
    };

    function guardar() {
        var cuerpo = {
            programa_id: cbPrograma.value(), codigo: valor('cur-inp-codigo'), nombre: valor('cur-inp-grupo'),
            docente_id: cbDocente.value() || null, jornada: cbJornada.value(),
            fecha_inicio: valor('cur-inp-inicio'),
            estado: cbEstado.value()
        };
        btnWizNext.disabled = true;
        enviar(editandoId ? 'PUT' : 'POST', editandoId ? '/admin/cursos/' + editandoId : '/admin/cursos', cuerpo)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (err) {
                btnWizNext.disabled = false;
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

    btnWizNext.addEventListener('click', function () {
        if (!validarPaso(currentStep)) return;
        if (currentStep < totalSteps) { currentStep++; renderStep(); } else { guardar(); }
    });
    btnWizBack.addEventListener('click', function () {
        if (currentStep > 1) { currentStep--; renderStep(); }
    });
    modal.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); formError.hidden = true; });
    modal.addEventListener('change', function (e) {
        var cb = e.target.closest('.app-combobox');
        if (cb) cb.classList.remove('is-invalid');
        formError.hidden = true;
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
            if (searchInput) { searchInput.value = ''; filtrar(''); searchInput.focus(); }
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
            root.classList.remove('is-invalid');
            list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.remove('is-selected'); });
        };
        root.value = function () { return hidden.value; };
    }

    /* ── Filtros — comboboxes ────────────────── */
    var cbFiltPrg = document.getElementById('cur-cb-filter-programa');
    var cbFiltEst = document.getElementById('cur-cb-filter-estado');

    initCombobox(cbFiltPrg);
    var prgOpts = JSON.parse(cbFiltPrg.dataset.options || '[]');
    cbFiltPrg.setOptions([{value:'', label:'Todos los programas'}].concat(prgOpts.map(function (v) { return {value:v, label:v}; })));
    cbFiltPrg.setValue('', 'Todos los programas', {silent:true});

    initCombobox(cbFiltEst);
    cbFiltEst.setOptions([{value:'', label:'Todos los estados'}].concat(Object.keys(ESTADO_LBL).map(function (k) { return {value:k, label:ESTADO_LBL[k]}; })));
    cbFiltEst.setValue('', 'Todos los estados', {silent:true});

    /* ── Wizard — comboboxes ─────────────────── */
    var cbEscuela   = document.getElementById('cur-cb-escuela');
    var cbPrograma  = document.getElementById('cur-cb-programa');
    var cbDocente   = document.getElementById('cur-cb-docente');
    var cbJornada   = document.getElementById('cur-cb-jornada');
    var cbEstado    = document.getElementById('cur-cb-estado');
    [cbEscuela, cbPrograma, cbDocente, cbJornada, cbEstado].forEach(initCombobox);

    // Escuelas → sus programas ofertados en la sede (los no activos no admiten grupos nuevos)
    cbEscuela.setOptions(escPrgData.map(function (e) { return { value: String(e.id), label: e.nombre }; }));
    document.getElementById('cur-val-escuela').addEventListener('change', function (e) {
        var escuela = escPrgData.find(function (x) { return String(x.id) === e.target.value; });
        cbPrograma.reset();
        cbPrograma.setOptions(escuela ? escuela.programas
            .filter(function (p) { return p.estado === 'activo' || editandoId; })
            .map(function (p) { return { value: String(p.id), label: p.nombre }; }) : []);
        cbJornada.reset(); cbJornada.setOptions([]);
        pintarDelPrograma();
        pintarFinHint();
    });

    /* Modalidad y cupo: solo lectura, siempre los del programa elegido */
    function pintarDelPrograma() {
        var p = programaActual();
        campo('cur-inp-modalidad').value = p ? p.modalidad : '';
        campo('cur-inp-cupo').value      = p ? p.cupo + ' estudiantes' : '';
    }

    // Programa → jornadas que ofrece, modalidad, cupo y fecha de fin sugerida
    document.getElementById('cur-val-programa').addEventListener('change', function () {
        var p = programaActual();
        pintarDelPrograma();
        if (!p) return;
        cbJornada.reset();
        cbJornada.setOptions(p.jornadas);
        if (p.jornadas.length === 1) cbJornada.setValue(p.jornadas[0], p.jornadas[0], { silent: true });
        pintarFinHint();
    });
    cbDocente.setOptions(docentes.map(function (d) { return { value: String(d.id), label: d.nombre + ' · ' + d.escuela }; }));
    cbEstado.setOptions(Object.keys(ESTADO_LBL).map(function (k) { return { value: k, label: ESTADO_LBL[k] }; }));

    /* ── Init ─────────────────────────────────── */
    applyFilters();
    renderStep();

}());
