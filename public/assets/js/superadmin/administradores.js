// ESAT — panel Superadmin, página "Administradores": tabla con búsqueda +
// paginación, interruptor de estado activo/inactivo, y el asistente de
// 4 pasos para crear/editar un administrador (vista previa, no guarda
// nada todavía — falta el backend: controlador, validación real,
// creación del usuario con role=admin y contraseña = documento).
document.addEventListener('DOMContentLoaded', function () {
    // ---- Tabla: búsqueda + paginación ----
    function setupTable(table) {
        var name = table.dataset.table;
        var perPage = parseInt(table.dataset.perPage, 10) || 15;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var searchInput = document.querySelector('[data-table-search="' + name + '"]');
        var pagination = document.querySelector('[data-pagination-for="' + name + '"]');
        var current = 1;

        function matches(row) {
            if (!searchInput || !searchInput.value.trim()) return true;
            var q = searchInput.value.trim().toLowerCase();
            return row.textContent.toLowerCase().indexOf(q) !== -1;
        }

        function render() {
            var visible = rows.filter(matches);
            var totalPages = Math.max(1, Math.ceil(visible.length / perPage));
            if (current > totalPages) current = totalPages;

            var start = (current - 1) * perPage;
            var end = start + perPage;
            var slice = visible.slice(start, end);

            rows.forEach(function (row) { row.hidden = true; });
            slice.forEach(function (row) { row.hidden = false; });

            if (pagination) {
                pagination.hidden = totalPages <= 1;
                pagination.querySelector('[data-page-label]').textContent = 'Página ' + current + ' de ' + totalPages;
                pagination.querySelector('[data-page-prev]').disabled = current === 1;
                pagination.querySelector('[data-page-next]').disabled = current === totalPages;
            }
        }

        if (searchInput) searchInput.addEventListener('input', function () { current = 1; render(); });
        if (pagination) {
            pagination.querySelector('[data-page-prev]').addEventListener('click', function () { if (current > 1) { current -= 1; render(); } });
            pagination.querySelector('[data-page-next]').addEventListener('click', function () { current += 1; render(); });
        }

        render();
    }

    document.querySelectorAll('[data-table]').forEach(setupTable);

    // ---- Interruptor activo/inactivo (vista previa, no persiste) ----
    document.querySelectorAll('[data-status-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var activo = btn.classList.toggle('is-active');
            btn.setAttribute('aria-pressed', activo ? 'true' : 'false');
            btn.querySelector('[data-status-label]').textContent = activo ? 'Activo' : 'Inactivo';
        });
    });

    // ---- Combobox propio (departamento / ciudad de nacimiento): mismo
    // componente y estilo que en Sedes — el <select> nativo no se puede
    // estilizar ni controlar hacia dónde abre su lista de opciones. ----
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

            // El panel siempre abre hacia abajo (nunca hacia arriba), pero
            // no debe salirse de la pantalla: si queda poco espacio entre
            // el campo y el borde inferior de la ventana, la lista se
            // encoge a lo que quepa, en vez de cortarse sin avisar.
            var margenInferior = 12;
            var espacioDisponible = window.innerHeight - (rect.bottom + 6) - margenInferior;
            var buscadorAltura = search.closest('.app-combobox-search-wrap').offsetHeight;
            var alturaLista = Math.max(espacioDisponible - buscadorAltura - 14, 40);
            list.style.maxHeight = alturaLista + 'px';
        }

        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            posicionar();
            search.value = '';
            filtrar('');
            search.focus();
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
            if (nuevoPlaceholder) placeholderTexto = nuevoPlaceholder;
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

    var colombiaData = null;
    var deptoNacBox = document.querySelector('[data-combobox="departamento_nacimiento"]');
    var ciudadNacBox = document.querySelector('[data-combobox="ciudad_nacimiento"]');
    var deptoNacInput = deptoNacBox ? deptoNacBox.querySelector('[data-combobox-value]') : null;
    var CIUDAD_NAC_PLACEHOLDER = 'Elige el departamento';

    function poblarCiudadesNacimiento(nombreDepartamento, ciudadAPreseleccionar) {
        if (!ciudadNacBox) return;

        if (!nombreDepartamento || !colombiaData) {
            ciudadNacBox.reset(CIUDAD_NAC_PLACEHOLDER);
            return;
        }

        var depto = colombiaData.find(function (d) { return d.departamento === nombreDepartamento; });
        var ciudades = depto ? depto.ciudades : [];
        ciudadNacBox.reset('Selecciona una ciudad');
        ciudadNacBox.setOptions(ciudades);
        if (ciudadAPreseleccionar && ciudades.indexOf(ciudadAPreseleccionar) !== -1) {
            ciudadNacBox.setValue(ciudadAPreseleccionar, { silent: true });
        }
    }

    if (deptoNacBox) {
        fetch('/assets/data/co-departamentos-ciudades.json')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                colombiaData = data;
                deptoNacBox.setOptions(data.map(function (d) { return d.departamento; }));
            });

        deptoNacInput.addEventListener('change', function () {
            poblarCiudadesNacimiento(deptoNacInput.value);
        });
    }

    // ---- Asistente: crear / editar administrador ----
    var modal = document.querySelector('[data-modal="modal-administrador"]');
    if (!modal) return;

    var pasos = Array.prototype.slice.call(modal.querySelectorAll('[data-wizard-panel]'));
    var indicadores = Array.prototype.slice.call(modal.querySelectorAll('[data-wizard-step-indicator]'));
    var tituloEl = modal.querySelector('[data-wizard-title]');
    var backBtn = modal.querySelector('[data-wizard-back]');
    var nextBtn = modal.querySelector('[data-wizard-next]');
    var finishBtn = modal.querySelector('[data-wizard-finish]');
    var progressEl = modal.querySelector('[data-wizard-progress]');
    var totalPasos = pasos.length;
    var pasoActual = 1;

    var ETIQUETAS = {
        nombres: 'Nombres', apellidos: 'Apellidos', genero: 'Género',
        fecha_nacimiento: 'Fecha de nacimiento',
        tipo_documento: 'Tipo de documento', numero_documento: 'Número de documento',
        telefono: 'Teléfono', correo: 'Correo', sede: 'Sede asignada'
    };
    var GENERO_TEXTO = { femenino: 'Femenino', masculino: 'Masculino', otro: 'Otro' };

    function calcularEdad(fechaISO) {
        var nacimiento = new Date(fechaISO + 'T00:00:00');
        if (isNaN(nacimiento.getTime())) return null;
        var hoy = new Date();
        var edad = hoy.getFullYear() - nacimiento.getFullYear();
        var noHaCumplido = (hoy.getMonth() < nacimiento.getMonth()) ||
            (hoy.getMonth() === nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate());
        if (noHaCumplido) edad -= 1;
        return edad;
    }

    var edadInput = modal.querySelector('[data-edad-input]');
    var edadPreview = modal.querySelector('[data-edad-preview]');
    if (edadInput && edadPreview) {
        edadInput.addEventListener('change', function () {
            var edad = calcularEdad(edadInput.value);
            edadPreview.textContent = edad !== null && edad >= 0 ? edad + ' años' : '';
        });
    }

    function mostrarPaso(numero) {
        pasoActual = numero;
        pasos.forEach(function (panel) {
            panel.hidden = parseInt(panel.dataset.wizardPanel, 10) !== numero;
        });
        indicadores.forEach(function (ind) {
            var n = parseInt(ind.dataset.wizardStepIndicator, 10);
            ind.classList.toggle('is-active', n === numero);
            ind.classList.toggle('is-done', n < numero);
        });
        backBtn.hidden = numero === 1;
        nextBtn.hidden = numero === totalPasos;
        finishBtn.hidden = numero !== totalPasos;
        progressEl.textContent = 'Paso ' + numero + ' de ' + totalPasos;

        if (numero === totalPasos) {
            renderResumen();
        }
    }

    function validarPasoActual() {
        var panel = pasos[pasoActual - 1];
        var valido = true;
        panel.querySelectorAll('[data-required]').forEach(function (campo) {
            var field = campo.closest('.app-field');
            var errorEl = field.querySelector('[data-field-error]');
            var vacio = !campo.value || !campo.value.trim();
            field.classList.toggle('has-error', vacio);
            if (errorEl) errorEl.textContent = vacio ? 'Este dato es obligatorio.' : '';
            if (vacio) valido = false;
        });
        return valido;
    }

    function renderResumen() {
        var lista = modal.querySelector('[data-wizard-summary-list]');
        if (!lista) return;
        lista.innerHTML = '';

        var claves = ['nombres', 'apellidos', 'genero', 'fecha_nacimiento', 'tipo_documento', 'numero_documento', 'telefono', 'correo', 'sede'];
        claves.forEach(function (clave) {
            var campo = document.getElementById('administrador-' + clave);
            if (!campo || !campo.value) return;

            var valor = campo.value;
            if (clave === 'genero') valor = GENERO_TEXTO[valor] || valor;
            if (clave === 'fecha_nacimiento') {
                var edad = calcularEdad(valor);
                valor = valor + (edad !== null ? ' (' + edad + ' años)' : '');
            }

            var div = document.createElement('div');
            var dt = document.createElement('dt');
            dt.textContent = ETIQUETAS[clave] || clave;
            var dd = document.createElement('dd');
            dd.textContent = valor;
            div.appendChild(dt);
            div.appendChild(dd);
            lista.appendChild(div);
        });

        var ciudadNac = document.getElementById('administrador-ciudad_nacimiento');
        var deptoNac = document.getElementById('administrador-departamento_nacimiento');
        if (ciudadNac && deptoNac && ciudadNac.value && deptoNac.value) {
            var divLugar = document.createElement('div');
            var dtLugar = document.createElement('dt');
            dtLugar.textContent = 'Lugar de nacimiento';
            var ddLugar = document.createElement('dd');
            ddLugar.textContent = ciudadNac.value + ', ' + deptoNac.value;
            divLugar.appendChild(dtLugar);
            divLugar.appendChild(ddLugar);
            lista.appendChild(divLugar);
        }
    }

    var sedeSelect = document.getElementById('administrador-sede');
    if (sedeSelect) {
        sedeSelect.addEventListener('change', renderResumen);
    }

    nextBtn.addEventListener('click', function () {
        if (!validarPasoActual()) return;
        if (pasoActual < totalPasos) mostrarPaso(pasoActual + 1);
    });

    backBtn.addEventListener('click', function () {
        if (pasoActual > 1) mostrarPaso(pasoActual - 1);
    });

    function limpiarAsistente() {
        modal.querySelectorAll('input, select').forEach(function (campo) {
            campo.value = '';
            if (campo.tagName === 'SELECT') campo.selectedIndex = 0;
        });
        modal.querySelectorAll('.app-field').forEach(function (field) {
            field.classList.remove('has-error');
        });
        modal.querySelectorAll('[data-field-error]').forEach(function (el) { el.textContent = ''; });
        if (edadPreview) edadPreview.textContent = '';
        if (deptoNacBox) deptoNacBox.reset(undefined, { keepOptions: true });
        if (ciudadNacBox) ciudadNacBox.reset(CIUDAD_NAC_PLACEHOLDER);
        mostrarPaso(1);
    }

    document.querySelectorAll('[data-open-wizard]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            tituloEl.textContent = tituloEl.dataset.createTitle;
            finishBtn.textContent = finishBtn.dataset.createLabel;
            limpiarAsistente();
            modal.hidden = false;
        });
    });

    document.querySelectorAll('[data-edit-wizard]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var fila = btn.closest('tr');
            tituloEl.textContent = tituloEl.dataset.editTitle;
            finishBtn.textContent = finishBtn.dataset.editLabel;
            limpiarAsistente();

            Object.keys(fila.dataset).forEach(function (key) {
                if (key === 'departamento_nacimiento' || key === 'ciudad_nacimiento') return;
                var campo = document.getElementById('administrador-' + key);
                if (campo) campo.value = fila.dataset[key];
            });
            if (edadInput && edadPreview) {
                var edad = calcularEdad(edadInput.value);
                edadPreview.textContent = edad !== null ? edad + ' años' : '';
            }
            if (deptoNacBox) {
                deptoNacBox.setValue(fila.dataset.departamento_nacimiento, { silent: true });
                poblarCiudadesNacimiento(fila.dataset.departamento_nacimiento, fila.dataset.ciudad_nacimiento);
            }

            modal.hidden = false;
        });
    });

    modal.querySelectorAll('[data-close-wizard]').forEach(function (btn) {
        btn.addEventListener('click', function () { modal.hidden = true; });
    });
    finishBtn.addEventListener('click', function () {
        if (!validarPasoActual()) return;
        modal.hidden = true;
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.hidden = true;
    });
});
