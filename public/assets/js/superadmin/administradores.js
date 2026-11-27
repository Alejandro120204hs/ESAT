// ESAT — panel Superadmin, página "Admin de ESAT": crear, editar, activar/desactivar y restablecer la clave de la
// cuenta del Admin (una sola). Las secretarías las crea el Admin desde su panel.
document.addEventListener('DOMContentLoaded', function () {
    var csrf   = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content');
    var cuenta = document.querySelector('.app-adm-cuenta');
    var AVISO  = 'esat.superadmin.aviso';

    // ---- Aviso de lo que se guardó antes de recargar ----
    var avisoEl = document.querySelector('[data-aviso]');
    try {
        var guardado = sessionStorage.getItem(AVISO);
        if (guardado && avisoEl) { avisoEl.textContent = guardado; avisoEl.hidden = false; }
        sessionStorage.removeItem(AVISO);
    } catch (e) { /* sin sessionStorage la página sigue igual */ }

    function recargarConAviso(mensaje) {
        try { sessionStorage.setItem(AVISO, mensaje); } catch (e) { /* nada */ }
        location.reload();
    }

    /** fetch con CSRF. Resuelve con el JSON; rechaza con {status, data}. 401/419: la sesión terminó, se recarga. */
    function enviar(metodo, url, cuerpo) {
        return fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: cuerpo ? JSON.stringify(cuerpo) : null
        }).then(function (res) {
            if (res.status === 401 || res.status === 419) { location.reload(); return Promise.reject({ status: res.status, data: {} }); }
            return res.json().catch(function () { return {}; }).then(function (data) {
                return res.ok ? data : Promise.reject({ status: res.status, data: data });
            });
        });
    }

    function mensajeError(err) {
        var d = (err && err.data) || {};
        if (d.errors) { var k = Object.keys(d.errors)[0]; if (k) return d.errors[k][0]; }
        return d.message || 'No se pudo guardar. Revisa tu conexión e intenta de nuevo.';
    }

    // ---- Combobox propio (departamento / ciudad de nacimiento): el <select> nativo no se puede estilizar ni
    // controlar hacia dónde abre su lista de opciones. ----
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
            // Abre hacia abajo sin salirse de la pantalla: la lista se encoge a lo que quepa
            var espacioDisponible = window.innerHeight - (rect.bottom + 6) - 12;
            var buscadorAltura = search.closest('.app-combobox-search-wrap').offsetHeight;
            list.style.maxHeight = Math.max(espacioDisponible - buscadorAltura - 14, 40) + 'px';
        }
        // El scroll de su propia lista no lo recoloca: posicionar() reinicia la altura y la lista volvía al inicio
        function alScroll(e) {
            if (panel.contains(e.target)) return;
            posicionar();
        }

        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            posicionar();
            search.value = '';
            filtrar('');
            search.focus();
            window.addEventListener('scroll', alScroll, true);
            window.addEventListener('resize', posicionar);
        }

        function cerrar() {
            panel.hidden = true;
            root.classList.remove('is-open');
            window.removeEventListener('scroll', alScroll, true);
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

        trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(); });
        search.addEventListener('input', function () { filtrar(search.value); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });
        // Escape con la lista abierta cierra solo la lista, no el modal
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) { cerrar(); e.stopImmediatePropagation(); }
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
                li.addEventListener('click', function () { root.setValue(texto); cerrar(); });
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
            if (!(opts && opts.silent)) hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        };

        root.reset = function (nuevoPlaceholder, opts) {
            if (nuevoPlaceholder) placeholderTexto = nuevoPlaceholder;
            if (!(opts && opts.keepOptions)) list.innerHTML = '';
            else list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.remove('is-selected'); });
            hiddenInput.value = '';
            label.textContent = placeholderTexto;
        };
    }

    document.querySelectorAll('[data-combobox]').forEach(initCombobox);

    var colombiaData = null;
    var deptoNacBox = document.querySelector('[data-combobox="departamento_nacimiento"]');
    var ciudadNacBox = document.querySelector('[data-combobox="ciudad_nacimiento"]');
    var deptoNacInput = deptoNacBox.querySelector('[data-combobox-value]');
    var CIUDAD_NAC_PLACEHOLDER = 'Elige el departamento';
    var pendiente = null;   // departamento/ciudad a preseleccionar si el listado todavía no llegó

    function poblarCiudadesNacimiento(nombreDepartamento, ciudadAPreseleccionar) {
        if (!nombreDepartamento || !colombiaData) { ciudadNacBox.reset(CIUDAD_NAC_PLACEHOLDER); return; }
        var depto = colombiaData.find(function (d) { return d.departamento === nombreDepartamento; });
        var ciudades = depto ? depto.ciudades : [];
        ciudadNacBox.reset('Selecciona una ciudad');
        ciudadNacBox.setOptions(ciudades);
        if (ciudadAPreseleccionar && ciudades.indexOf(ciudadAPreseleccionar) !== -1) {
            ciudadNacBox.setValue(ciudadAPreseleccionar, { silent: true });
        }
    }

    fetch('/assets/data/co-departamentos-ciudades.json')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            colombiaData = data;
            deptoNacBox.setOptions(data.map(function (d) { return d.departamento; }));
            if (pendiente) {
                deptoNacBox.setValue(pendiente[0], { silent: true });
                poblarCiudadesNacimiento(pendiente[0], pendiente[1]);
                pendiente = null;
            }
        });
    deptoNacInput.addEventListener('change', function () { poblarCiudadesNacimiento(deptoNacInput.value); });

    // ---- Asistente: crear / editar la cuenta del Admin ----
    var modal = document.querySelector('[data-modal="modal-administrador"]');
    var pasos = Array.prototype.slice.call(modal.querySelectorAll('[data-wizard-panel]'));
    var indicadores = Array.prototype.slice.call(modal.querySelectorAll('[data-wizard-step-indicator]'));
    var tituloEl = modal.querySelector('[data-wizard-title]');
    var backBtn = modal.querySelector('[data-wizard-back]');
    var nextBtn = modal.querySelector('[data-wizard-next]');
    var finishBtn = modal.querySelector('[data-wizard-finish]');
    var progressEl = modal.querySelector('[data-wizard-progress]');
    var wizardError = modal.querySelector('[data-wizard-error]');
    var notaCrear = modal.querySelector('[data-wizard-note-crear]');
    var totalPasos = pasos.length;
    var pasoActual = 1;
    var editId = null;

    var CAMPOS = ['nombres', 'apellidos', 'genero', 'fecha_nacimiento', 'departamento_nacimiento', 'ciudad_nacimiento',
                  'tipo_documento', 'numero_documento', 'telefono', 'correo'];
    var GENERO_TEXTO = { femenino: 'Femenino', masculino: 'Masculino', otro: 'Otro' };

    function campo(clave) { return document.getElementById('administrador-' + clave); }

    function calcularEdad(fechaISO) {
        var nacimiento = new Date(fechaISO + 'T00:00:00');
        if (isNaN(nacimiento.getTime())) return null;
        var hoy = new Date();
        var edad = hoy.getFullYear() - nacimiento.getFullYear();
        if (hoy.getMonth() < nacimiento.getMonth() || (hoy.getMonth() === nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate())) edad -= 1;
        return edad;
    }

    var edadInput = modal.querySelector('[data-edad-input]');
    var edadPreview = modal.querySelector('[data-edad-preview]');
    function mostrarEdad() {
        var edad = calcularEdad(edadInput.value);
        edadPreview.textContent = edad !== null && edad >= 0 ? edad + ' años' : '';
    }
    edadInput.addEventListener('change', mostrarEdad);

    /** Documento como lo guarda el servidor: pasaporte con letras y números en mayúscula; los demás, solo dígitos. */
    function limpiarDoc() {
        var d = campo('numero_documento').value;
        return campo('tipo_documento').value === 'PA' ? d.replace(/[^A-Za-z0-9]/g, '').toUpperCase() : d.replace(/\D/g, '');
    }

    function marcar(clave, texto) {
        var el = campo(clave);
        if (!el) return;
        var field = el.closest('.app-field');
        field.classList.toggle('has-error', !!texto);
        var err = field.querySelector('[data-field-error]');
        if (err) err.textContent = texto || '';
    }

    function mostrarPaso(numero) {
        pasoActual = numero;
        pasos.forEach(function (panel) { panel.hidden = parseInt(panel.dataset.wizardPanel, 10) !== numero; });
        indicadores.forEach(function (ind) {
            var n = parseInt(ind.dataset.wizardStepIndicator, 10);
            ind.classList.toggle('is-active', n === numero);
            ind.classList.toggle('is-done', n < numero);
        });
        backBtn.hidden = numero === 1;
        nextBtn.hidden = numero === totalPasos;
        finishBtn.hidden = numero !== totalPasos;
        progressEl.textContent = 'Paso ' + numero + ' de ' + totalPasos;
        wizardError.textContent = '';
        if (numero === totalPasos) renderResumen();
    }

    /** Lo mismo que revisa AdminRequest, para no esperar al servidor. */
    function validarPasoActual() {
        var panel = pasos[pasoActual - 1];
        var valido = true;
        panel.querySelectorAll('[data-required]').forEach(function (el) {
            var vacio = !el.value || !el.value.trim();
            marcar(el.id.replace('administrador-', ''), vacio ? 'Este dato es obligatorio.' : '');
            if (vacio) valido = false;
        });
        if (!valido) return false;
        if (pasoActual === 1) {
            var edad = calcularEdad(edadInput.value);
            if (edad === null || edad < 18) { marcar('fecha_nacimiento', 'El Admin debe ser mayor de edad.'); return false; }
            if (edad > 90) { marcar('fecha_nacimiento', 'Revisa la fecha de nacimiento.'); return false; }
        }
        if (pasoActual === 2) {
            var doc = limpiarDoc(), pasaporte = campo('tipo_documento').value === 'PA';
            if (pasaporte ? !/^[A-Z0-9]{5,15}$/.test(doc) : !/^\d{6,12}$/.test(doc)) {
                marcar('numero_documento', pasaporte ? 'El pasaporte lleva solo letras y números (de 5 a 15).' : 'El número de documento lleva solo números (de 6 a 12).');
                valido = false;
            }
            if (!/^3\d{9}$/.test(campo('telefono').value.replace(/\D/g, ''))) {
                marcar('telefono', 'Escribe un celular de 10 dígitos que empiece por 3.');
                valido = false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(campo('correo').value.trim())) {
                marcar('correo', 'Escribe un correo válido.');
                valido = false;
            }
        }
        return valido;
    }

    function renderResumen() {
        var lista = modal.querySelector('[data-wizard-summary-list]');
        var tipo = campo('tipo_documento');
        var filas = [
            ['Nombre', campo('nombres').value.trim() + ' ' + campo('apellidos').value.trim()],
            ['Género', GENERO_TEXTO[campo('genero').value] || ''],
            ['Fecha de nacimiento', campo('fecha_nacimiento').value + ' (' + calcularEdad(campo('fecha_nacimiento').value) + ' años)'],
            ['Lugar de nacimiento', campo('ciudad_nacimiento').value + ', ' + campo('departamento_nacimiento').value],
            ['Documento', (tipo.options[tipo.selectedIndex] || {}).text + ' ' + limpiarDoc()],
            ['Celular', campo('telefono').value.replace(/\D/g, '')],
            ['Correo (usuario)', campo('correo').value.trim().toLowerCase()]
        ];
        lista.innerHTML = '';
        filas.forEach(function (f) {
            var div = document.createElement('div');
            var dt = document.createElement('dt');
            var dd = document.createElement('dd');
            dt.textContent = f[0];
            dd.textContent = f[1];
            div.appendChild(dt);
            div.appendChild(dd);
            lista.appendChild(div);
        });
    }

    function limpiarAsistente() {
        modal.querySelectorAll('input, select').forEach(function (el) {
            el.value = '';
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
        });
        CAMPOS.forEach(function (c) { marcar(c, ''); });
        edadPreview.textContent = '';
        deptoNacBox.reset(undefined, { keepOptions: true });
        ciudadNacBox.reset(CIUDAD_NAC_PLACEHOLDER);
        finishBtn.disabled = false;
        mostrarPaso(1);
    }

    function abrirAsistente(editar) {
        editId = editar ? cuenta.dataset.id : null;
        tituloEl.textContent = editar ? tituloEl.dataset.editTitle : tituloEl.dataset.createTitle;
        finishBtn.textContent = editar ? finishBtn.dataset.editLabel : finishBtn.dataset.createLabel;
        notaCrear.hidden = !!editar;
        limpiarAsistente();
        if (editar) {
            ['nombres', 'apellidos', 'genero', 'fecha_nacimiento', 'tipo_documento', 'numero_documento', 'telefono', 'correo'].forEach(function (c) {
                campo(c).value = cuenta.dataset[c] || '';
            });
            mostrarEdad();
            if (colombiaData) {
                deptoNacBox.setValue(cuenta.dataset.departamento_nacimiento, { silent: true });
                poblarCiudadesNacimiento(cuenta.dataset.departamento_nacimiento, cuenta.dataset.ciudad_nacimiento);
            } else {
                pendiente = [cuenta.dataset.departamento_nacimiento, cuenta.dataset.ciudad_nacimiento];
            }
        }
        modal.hidden = false;
        setTimeout(function () { campo('nombres').focus(); }, 30);
    }

    var crearBtn = document.querySelector('[data-open-wizard]');
    if (crearBtn) crearBtn.addEventListener('click', function () { abrirAsistente(false); });
    var editarBtn = document.querySelector('[data-edit-wizard]');
    if (editarBtn) editarBtn.addEventListener('click', function () { abrirAsistente(true); });

    nextBtn.addEventListener('click', function () { if (validarPasoActual() && pasoActual < totalPasos) mostrarPaso(pasoActual + 1); });
    backBtn.addEventListener('click', function () { if (pasoActual > 1) mostrarPaso(pasoActual - 1); });
    CAMPOS.forEach(function (c) {
        var el = campo(c);
        el.addEventListener('input', function () { marcar(c, ''); });
        el.addEventListener('change', function () { marcar(c, ''); });
    });

    // Paso en el que está cada campo, para llevar ahí al que tenga error del servidor
    var PASO_DE = { nombres: 1, apellidos: 1, genero: 1, fecha_nacimiento: 1, departamento_nacimiento: 1, ciudad_nacimiento: 1,
                    tipo_documento: 2, numero_documento: 2, telefono: 2, correo: 2 };

    finishBtn.addEventListener('click', function () {
        var payload = {};
        CAMPOS.forEach(function (c) { payload[c] = campo(c).value; });
        payload.numero_documento = limpiarDoc();
        payload.telefono = payload.telefono.replace(/\D/g, '');

        finishBtn.disabled = true;
        enviar(editId ? 'PUT' : 'POST', editId ? '/superadmin/administradores/' + editId : '/superadmin/administradores', payload)
            .then(function (r) { recargarConAviso(r.mensaje || 'Se guardó la cuenta del Admin.'); })
            .catch(function (err) {
                finishBtn.disabled = false;
                var errores = (err.data && err.data.errors) || {};
                var primero = Object.keys(errores)[0];
                if (primero && PASO_DE[primero]) {
                    mostrarPaso(PASO_DE[primero]);
                    Object.keys(errores).forEach(function (k) { if (PASO_DE[k] === PASO_DE[primero]) marcar(k, errores[k][0]); });
                } else {
                    wizardError.textContent = mensajeError(err);
                }
            });
    });

    modal.querySelectorAll('[data-close-wizard]').forEach(function (btn) {
        btn.addEventListener('click', function () { modal.hidden = true; });
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });

    // ---- Confirmar: restablecer clave / activar o desactivar ----
    var conf = document.querySelector('[data-modal="modal-confirmar"]');
    var confTitulo = conf.querySelector('[data-confirmar-titulo]');
    var confTexto = conf.querySelector('[data-confirmar-texto]');
    var confError = conf.querySelector('[data-confirmar-error]');
    var confOk = conf.querySelector('[data-confirmar-ok]');
    var accion = null;

    document.querySelectorAll('[data-confirmar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            accion = btn.dataset.confirmar;
            var nombre = cuenta.dataset.nombres + ' ' + cuenta.dataset.apellidos;
            var activo = cuenta.dataset.activo === '1';
            if (accion === 'clave') {
                confTitulo.textContent = 'Restablecer clave';
                confTexto.textContent = 'La clave de ' + nombre + ' volverá a ser su número de documento (' + cuenta.dataset.numero_documento +
                    ') y se cerrarán sus sesiones abiertas. Si la cuenta estaba bloqueada, queda desbloqueada.';
                confOk.textContent = 'Restablecer clave';
            } else {
                confTitulo.textContent = activo ? 'Desactivar la cuenta del Admin' : 'Activar la cuenta del Admin';
                confTexto.textContent = activo
                    ? nombre + ' no podrá ingresar y su sesión abierta se cerrará. Mientras tanto nadie maneja ESAT desde el panel del Admin: los administrativos siguen trabajando en sus sedes.'
                    : nombre + ' podrá volver a ingresar con su correo y su clave de siempre.';
                confOk.textContent = activo ? 'Desactivar cuenta' : 'Activar cuenta';
            }
            confError.textContent = '';
            confOk.disabled = false;
            conf.hidden = false;
        });
    });

    confOk.addEventListener('click', function () {
        var url = '/superadmin/administradores/' + cuenta.dataset.id + (accion === 'clave' ? '/clave' : '/toggle');
        confOk.disabled = true;
        enviar('PATCH', url)
            .then(function (r) { recargarConAviso(r.mensaje); })
            .catch(function (err) { confOk.disabled = false; confError.textContent = mensajeError(err); });
    });
    conf.querySelectorAll('[data-close-confirmar]').forEach(function (btn) {
        btn.addEventListener('click', function () { conf.hidden = true; });
    });
    conf.addEventListener('click', function (e) { if (e.target === conf) conf.hidden = true; });

    // Escape cierra el modal abierto (con una lista de combobox abierta, el combobox lo detiene y se cierra solo ella)
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        modal.hidden = true;
        conf.hidden = true;
    });
});
