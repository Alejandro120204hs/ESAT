// ESAT — panel Superadmin, página "Sedes y programas": pestañas, tablas
// con búsqueda + paginación (15 filas por página), checkboxes de
// asignación por sede, selector de periodos por programa, y los modales
// de creación (vista previa, no guardan nada todavía — falta el backend).
document.addEventListener('DOMContentLoaded', function () {
    // ---- Pestañas ----
    var tabs = document.querySelectorAll('.app-tab');
    var panels = document.querySelectorAll('.app-tab-panel');
    var pageIntro = document.querySelector('[data-page-intro]');

    var introPorPestana = {
        sedes: 'Aquí podrás crear nuevas sedes, editar su dirección y ciudad, y desactivar las que ya no estén en funcionamiento.',
        escuelas: 'Aquí podrás crear nuevas escuelas y ver cuántos programas tiene cada una.',
        programas: 'Aquí podrás crear programas nuevos, definir su precio, duración y tipo de periodo, y editar los que ya existen.',
        asignaciones: 'Aquí podrás elegir qué programas ofrece cada sede y guardar los cambios.',
        periodos: 'Aquí podrás crear los semestres o trimestres reales de cada programa, con sus fechas de inicio y fin.'
    };

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');

            var target = tab.dataset.tab;
            panels.forEach(function (panel) {
                panel.hidden = panel.dataset.tabPanel !== target;
            });

            if (pageIntro && introPorPestana[target]) {
                pageIntro.textContent = introPorPestana[target];
            }
        });
    });

    // ---- Tablas: búsqueda + filtro (opcional) + paginación ----
    function setupTable(table) {
        var name = table.dataset.table;
        var perPage = parseInt(table.dataset.perPage, 10) || 15;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var searchInput = document.querySelector('[data-table-search="' + name + '"]');
        var escuelaFilter = document.querySelector('[data-filter-escuela][data-table-target="' + name + '"]');
        var pagination = document.querySelector('[data-pagination-for="' + name + '"]');
        var current = 1;

        function matches(row) {
            if (searchInput && searchInput.value.trim()) {
                var q = searchInput.value.trim().toLowerCase();
                if (row.textContent.toLowerCase().indexOf(q) === -1) {
                    return false;
                }
            }
            if (escuelaFilter && escuelaFilter.value !== 'todas') {
                if (row.dataset.escuela !== escuelaFilter.value) {
                    return false;
                }
            }
            return true;
        }

        function render() {
            var visible = rows.filter(matches);
            var totalPages = Math.max(1, Math.ceil(visible.length / perPage));
            if (current > totalPages) {
                current = totalPages;
            }

            var start = (current - 1) * perPage;
            var end = start + perPage;
            var visibleSlice = visible.slice(start, end);

            rows.forEach(function (row) { row.hidden = true; });
            visibleSlice.forEach(function (row) { row.hidden = false; });

            if (pagination) {
                pagination.hidden = totalPages <= 1;
                pagination.querySelector('[data-page-label]').textContent = 'Página ' + current + ' de ' + totalPages;
                pagination.querySelector('[data-page-prev]').disabled = current === 1;
                pagination.querySelector('[data-page-next]').disabled = current === totalPages;
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () { current = 1; render(); });
        }
        if (escuelaFilter) {
            escuelaFilter.addEventListener('change', function () { current = 1; render(); });
        }
        if (pagination) {
            pagination.querySelector('[data-page-prev]').addEventListener('click', function () {
                if (current > 1) { current -= 1; render(); }
            });
            pagination.querySelector('[data-page-next]').addEventListener('click', function () {
                current += 1; render();
            });
        }

        render();
    }

    document.querySelectorAll('[data-table]').forEach(setupTable);

    // ---- Asignaciones por sede ----
    var assignData = document.getElementById('app-asignaciones-data');
    var sedePills = document.querySelectorAll('[data-sede-pill]');
    var checkboxes = document.querySelectorAll('[data-programa-index]');

    if (assignData && sedePills.length && checkboxes.length) {
        var asignaciones = JSON.parse(assignData.textContent);

        function pintarSede(sede) {
            var asignados = asignaciones[sede] || [];
            checkboxes.forEach(function (checkbox) {
                var idx = parseInt(checkbox.dataset.programaIndex, 10);
                checkbox.checked = asignados.indexOf(idx) !== -1;
            });
        }

        sedePills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                sedePills.forEach(function (p) { p.classList.remove('is-active'); });
                pill.classList.add('is-active');
                pintarSede(pill.dataset.sedePill);
            });
        });

        pintarSede(sedePills[0].dataset.sedePill);
    }

    // ---- Guardar cambios (asignaciones) — vista previa, no persiste ----
    var guardarBtn = document.querySelector('[data-save-asignaciones]');
    var confirmMsg = document.querySelector('[data-save-confirm]');
    var confirmTimeout;

    if (guardarBtn && confirmMsg) {
        guardarBtn.addEventListener('click', function () {
            confirmMsg.hidden = false;
            clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(function () {
                confirmMsg.hidden = true;
            }, 2500);
        });
    }

    // ---- Selector de programa (periodos académicos) ----
    var selectPrograma = document.querySelector('[data-select-programa]');
    var periodosSets = document.querySelectorAll('[data-periodos-for]');

    if (selectPrograma && periodosSets.length) {
        selectPrograma.addEventListener('change', function () {
            var programa = selectPrograma.value;
            periodosSets.forEach(function (set) {
                set.hidden = set.dataset.periodosFor !== programa;
            });
        });
    }

    // ---- Combobox propio (departamento / ciudad): siempre abre hacia abajo,
    // con buscador (Cundinamarca sola tiene 117 ciudades) y estilos reales,
    // porque el <select> nativo no se puede estilizar ni controlar hacia
    // dónde abre su lista de opciones. ----
    function initCombobox(root) {
        var trigger = root.querySelector('[data-combobox-trigger]');
        var label = root.querySelector('[data-combobox-label]');
        var panel = root.querySelector('[data-combobox-panel]');
        var search = root.querySelector('[data-combobox-search]');
        var list = root.querySelector('[data-combobox-list]');
        var hiddenInput = root.querySelector('[data-combobox-value]');
        var placeholderTexto = label.textContent;

        function posicionar() {
            var rect = trigger.getBoundingClientRect();
            panel.style.left = rect.left + 'px';
            panel.style.top = (rect.bottom + 6) + 'px';
            panel.style.width = rect.width + 'px';
        }

        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            posicionar();
            search.value = '';
            filtrar('');
            search.focus();
            // "true" para capturar también el scroll interno del modal
            // (los contenedores con overflow no burbujean su scroll).
            window.addEventListener('scroll', posicionar, true);
            window.addEventListener('resize', posicionar);
        }

        function cerrar() {
            panel.hidden = true;
            root.classList.remove('is-open');
            window.removeEventListener('scroll', posicionar, true);
            window.removeEventListener('resize', posicionar);
        }

        function filtrar(q) {
            var qq = q.trim().toLowerCase();
            var visibles = 0;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                var coincide = qq === '' || li.textContent.toLowerCase().indexOf(qq) !== -1;
                li.hidden = !coincide;
                if (coincide) visibles += 1;
            });
            var vacio = list.querySelector('.app-combobox-empty');
            if (vacio) vacio.hidden = visibles > 0;
        }

        trigger.addEventListener('click', function () {
            if (panel.hidden) abrir(); else cerrar();
        });
        search.addEventListener('input', function () { filtrar(search.value); });
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) cerrar();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') cerrar();
        });

        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (texto) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = texto;
                var span = document.createElement('span');
                span.textContent = texto;
                var check = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                check.setAttribute('viewBox', '0 0 24 24');
                check.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                li.appendChild(span);
                li.appendChild(check);
                li.addEventListener('click', function () {
                    root.setValue(texto);
                    cerrar();
                });
                list.appendChild(li);
            });

            var vacio = document.createElement('li');
            vacio.className = 'app-combobox-empty';
            vacio.hidden = opciones.length > 0;
            vacio.textContent = 'Sin resultados';
            list.appendChild(vacio);
        };

        root.setValue = function (valor, opts) {
            hiddenInput.value = valor || '';
            label.textContent = valor || placeholderTexto;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === valor);
            });
            if (!(opts && opts.silent)) {
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        root.reset = function (nuevoPlaceholder, opts) {
            if (nuevoPlaceholder) {
                placeholderTexto = nuevoPlaceholder;
            }
            if (!(opts && opts.keepOptions)) {
                list.innerHTML = '';
            } else {
                list.querySelectorAll('li[data-value]').forEach(function (li) {
                    li.classList.remove('is-selected');
                });
            }
            hiddenInput.value = '';
            label.textContent = placeholderTexto;
        };
    }

    document.querySelectorAll('[data-combobox]').forEach(initCombobox);

    // ---- Departamentos y ciudades de Colombia (dataset real, no inventado) ----
    var colombiaData = null;
    var departamentoBox = document.querySelector('[data-combobox="departamento"]');
    var ciudadBox = document.querySelector('[data-combobox="ciudad"]');
    var departamentoSelect = departamentoBox ? departamentoBox.querySelector('[data-combobox-value]') : null;
    var CIUDAD_PLACEHOLDER = 'Elige el departamento';

    function poblarCiudades(nombreDepartamento, ciudadAPreseleccionar) {
        if (!ciudadBox) return;

        if (!nombreDepartamento || !colombiaData) {
            ciudadBox.reset(CIUDAD_PLACEHOLDER);
            return;
        }

        var depto = colombiaData.find(function (d) { return d.departamento === nombreDepartamento; });
        var ciudades = depto ? depto.ciudades : [];
        ciudadBox.reset('Selecciona una ciudad');
        ciudadBox.setOptions(ciudades);
        if (ciudadAPreseleccionar && ciudades.indexOf(ciudadAPreseleccionar) !== -1) {
            ciudadBox.setValue(ciudadAPreseleccionar, { silent: true });
        }
    }

    if (departamentoBox) {
        fetch('/assets/data/co-departamentos-ciudades.json')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                colombiaData = data;
                departamentoBox.setOptions(data.map(function (d) { return d.departamento; }));
            });

        departamentoSelect.addEventListener('change', function () {
            poblarCiudades(departamentoSelect.value);
        });
    }

    // ---- Modales: crear (formulario vacío) o editar (formulario prellenado) ----
    function limpiarCampos(modal) {
        modal.querySelectorAll('.app-field input, .app-field select').forEach(function (campo) {
            campo.value = '';
            if (campo.tagName === 'SELECT') {
                campo.selectedIndex = 0;
            }
        });

        if (departamentoBox) {
            departamentoBox.reset(undefined, { keepOptions: true });
        }
        if (ciudadBox) {
            ciudadBox.reset(CIUDAD_PLACEHOLDER);
        }
    }

    function ponerModoCrear(modal) {
        var titulo = modal.querySelector('[data-modal-title]');
        var guardar = modal.querySelector('[data-modal-save]');
        if (titulo) titulo.textContent = titulo.dataset.createTitle;
        if (guardar) guardar.textContent = guardar.dataset.createLabel;
        limpiarCampos(modal);
    }

    function ponerModoEditar(modal, tipo, datos) {
        var titulo = modal.querySelector('[data-modal-title]');
        var guardar = modal.querySelector('[data-modal-save]');
        if (titulo) titulo.textContent = titulo.dataset.editTitle;
        if (guardar) guardar.textContent = guardar.dataset.editLabel;

        Object.keys(datos).forEach(function (key) {
            if (tipo === 'sede' && (key === 'departamento' || key === 'ciudad')) {
                return;
            }
            var campo = modal.querySelector('#' + tipo + '-' + key);
            if (campo) {
                campo.value = datos[key];
            }
        });

        if (tipo === 'sede' && departamentoBox) {
            departamentoBox.setValue(datos.departamento, { silent: true });
            poblarCiudades(datos.departamento, datos.ciudad);
        }
    }

    document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.querySelector('[data-modal="' + btn.dataset.openModal + '"]');
            if (modal) {
                ponerModoCrear(modal);
                modal.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-edit-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tipo = btn.dataset.editModal;
            var modal = document.querySelector('[data-modal="modal-' + tipo + '"]');
            var fila = btn.closest('tr');
            if (modal && fila) {
                ponerModoEditar(modal, tipo, fila.dataset);
                modal.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('.app-modal-backdrop');
            if (modal) {
                modal.hidden = true;
            }
        });
    });

    document.querySelectorAll('.app-modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) {
                backdrop.hidden = true;
            }
        });
    });
});
