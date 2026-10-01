/* ESAT — Admin: Horarios */
(function () {
    'use strict';

    var data = window.HOR_DATA || [];

    var DIAS_FULL = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
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

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function toMin(t) { var p = t.split(':'); return parseInt(p[0]) * 60 + parseInt(p[1]); }

    /* ── Combobox ── */
    function initCombobox(root) {
        if (!root) return;
        var trigger = root.querySelector('[data-combobox-trigger]');
        var panel   = root.querySelector('[data-combobox-panel]');
        var list    = root.querySelector('[data-combobox-list]');
        var hidden  = root.querySelector('[data-combobox-value]');
        var lbl     = root.querySelector('[data-combobox-label]');
        var placeholder = lbl.textContent;
        var maxH    = root.dataset.listMaxHeight;

        if (maxH) list.style.maxHeight = maxH + 'px';

        function open()  { panel.hidden = false; root.classList.add('is-open'); }
        function close() { panel.hidden = true;  root.classList.remove('is-open'); }

        trigger.addEventListener('click', function () { panel.hidden ? open() : close(); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });

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

        root.setValue = function (val, lab) {
            hidden.value = val || '';
            lbl.textContent = lab || val || placeholder;
            list.querySelectorAll('li').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === val);
            });
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
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

    /* ── Tabla filter ── */
    function applyFilters() {
        if (!tbodyEl) return;
        var rows = tbodyEl.querySelectorAll('.hor-row');
        var visible = 0;

        rows.forEach(function (row) {
            var matchEsc = !filtroEscuela || row.dataset.escuela === filtroEscuela;
            var matchJor = !filtroJornada || row.dataset.jornada === filtroJornada;
            var matchTxt = !filtroTexto   ||
                row.dataset.grupo.indexOf(filtroTexto)    >= 0 ||
                row.dataset.programa.indexOf(filtroTexto) >= 0 ||
                row.dataset.docente.indexOf(filtroTexto)  >= 0;

            var show = matchEsc && matchJor && matchTxt;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (emptyEl)    emptyEl.style.display = visible === 0 ? 'flex' : 'none';
        if (countBadge) countBadge.textContent = visible + ' horario' + (visible !== 1 ? 's' : '');
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

    /* ── Grilla semanal ── */
    function buildGrilla() {
        if (!grillaEl) return;

        // Limpiar filas anteriores (mantener los 7 headers)
        var headers = grillaEl.querySelectorAll('.hor-grilla-head-cell');
        while (grillaEl.children.length > headers.length) grillaEl.removeChild(grillaEl.lastChild);

        var grupos = data.filter(function (g) {
            if (!filtroEscuela && !filtroJornada && !filtroTexto) return true;
            var matchEsc = !filtroEscuela || g.escuela === filtroEscuela;
            var matchJor = !filtroJornada || g.jornada === filtroJornada;
            return matchEsc && matchJor;
        });

        // Construir mapa: hora_dia -> [grupos]
        var mapa = {};
        grupos.forEach(function (g) {
            (g.dias || []).forEach(function (dia) {
                var diaIdx = DIAS_FULL.indexOf(dia);
                if (diaIdx < 0) return;
                var hI = parseInt(g.hora_inicio.split(':')[0]);
                var hF = parseInt(g.hora_fin.split(':')[0]);
                for (var h = hI; h < hF; h++) {
                    var key = h + '_' + diaIdx;
                    if (!mapa[key]) mapa[key] = [];
                    mapa[key].push(g);
                }
            });
        });

        for (var h = HORA_MIN; h < HORA_MAX; h++) {
            // Columna hora
            var timeCell = document.createElement('div');
            timeCell.className = 'hor-grilla-time';
            timeCell.textContent = pad(h) + ':00';
            grillaEl.appendChild(timeCell);

            for (var d = 0; d < 6; d++) {
                var cell = document.createElement('div');
                cell.className = 'hor-grilla-cell';
                var key = h + '_' + d;
                var bloques = mapa[key] || [];

                if (bloques.length > 0) {
                    var g0  = bloques[0];
                    var cls = escClass(g0.escuela);
                    var blk = document.createElement('div');
                    blk.className = 'hor-bloque hor-esc-' + cls;

                    var shortName = g0.programa.replace('Técnico Laboral en ', '');
                    blk.innerHTML =
                        '<div class="hor-bloque-nombre">' + shortName + '</div>' +
                        '<div class="hor-bloque-grupo">' + g0.grupo + '</div>';

                    if (bloques.length > 1) {
                        var mas = document.createElement('span');
                        mas.className = 'hor-bloque-mas';
                        mas.textContent = '+' + (bloques.length - 1);
                        blk.appendChild(mas);
                    }

                    cell.appendChild(blk);
                }

                grillaEl.appendChild(cell);
            }
        }
    }

    /* ══ Modal ══ */
    var overlay   = document.getElementById('hor-modal-overlay');
    var modalClose = document.getElementById('hor-modal-close');
    var modalCancel = document.getElementById('hor-modal-cancel');
    var btnNuevo  = document.getElementById('hor-btn-nuevo');
    var modalTitle = document.getElementById('hor-modal-title');

    var cbGrupoModal   = document.getElementById('hor-cb-grupo-modal');
    var hidGrupoModal  = document.getElementById('hor-val-grupo-modal');
    var cbDocenteModal = document.getElementById('hor-cb-docente-modal');
    var inpPrograma    = document.getElementById('hor-inp-programa');
    var inpEscuela     = document.getElementById('hor-inp-escuela');
    var inpHInicio     = document.getElementById('hor-inp-hinicio');
    var inpHFin        = document.getElementById('hor-inp-hfin');
    var inpJornada     = document.getElementById('hor-inp-jornada');

    function openModal(id) {
        if (!overlay) return;
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        if (id) {
            var g = data.find(function (x) { return x.id === id; });
            if (g) {
                modalTitle.textContent = 'Editar horario';
                cbGrupoModal.setValue(String(g.id), g.grupo + ' — ' + g.codigo);
                inpPrograma.value = g.programa;
                inpEscuela.value  = g.escuela;
                inpHInicio.value  = g.hora_inicio;
                inpHFin.value     = g.hora_fin;
                inpJornada.value  = g.jornada;

                // Marcar días
                document.querySelectorAll('.hor-dia-cb').forEach(function (cb) {
                    cb.checked = g.dias.indexOf(cb.value) >= 0;
                });

                // Docente
                cbDocenteModal.setValue(g.docente, g.docente);
            }
        } else {
            modalTitle.textContent = 'Nuevo horario';
            cbGrupoModal.reset();
            if (inpPrograma) inpPrograma.value = '';
            if (inpEscuela)  inpEscuela.value  = '';
            if (inpHInicio)  inpHInicio.value  = '07:00';
            if (inpHFin)     inpHFin.value     = '11:00';
            if (inpJornada)  inpJornada.value  = 'Mañana';
            document.querySelectorAll('.hor-dia-cb').forEach(function (cb) { cb.checked = false; });
            cbDocenteModal.reset();
        }
    }

    function closeModal() {
        if (!overlay) return;
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    if (btnNuevo)     btnNuevo.addEventListener('click',  function () { openModal(null); });
    if (modalClose)   modalClose.addEventListener('click',  closeModal);
    if (modalCancel)  modalCancel.addEventListener('click', closeModal);
    if (overlay)      overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

    // Editar desde tabla
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.hor-btn-edit');
        if (!btn) return;
        openModal(parseInt(btn.dataset.id));
    });

    /* Guardar (mock) */
    var btnSave = document.getElementById('hor-modal-save');
    if (btnSave) {
        btnSave.addEventListener('click', function () {
            closeModal();
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

    /* Auto-calcular jornada al cambiar hora inicio */
    if (inpHInicio) {
        inpHInicio.addEventListener('change', function () {
            if (inpJornada) inpJornada.value = jornadaDeHora(inpHInicio.value);
        });
    }

    /* ── Init ── */
    applyFilters();

}());
