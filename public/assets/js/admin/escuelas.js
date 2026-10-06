/* ESAT — Admin: Escuelas */
(function () {
    'use strict';

    /* ── Elementos ── */
    const tabBtns    = document.querySelectorAll('.prg-tab-btn');
    const tabPanels  = document.querySelectorAll('.prg-page-panel');
    const btnNuevo   = document.getElementById('btn-nuevo');
    const btnNuevaEc = document.getElementById('btn-nueva-esc');
    const modal      = document.getElementById('esc-modal');
    const modalTitle = document.getElementById('esc-modal-title');
    const modalClose = document.getElementById('esc-modal-close');
    const btnCancel  = document.getElementById('esc-btn-cancel');
    const btnSave    = document.getElementById('esc-btn-save');
    const inpNombre  = document.getElementById('esc-inp-nombre');
    const formEl     = document.getElementById('esc-form');
    const confirmEl  = document.getElementById('esc-confirm');
    const errorEl    = document.getElementById('esc-error');
    const mainTitle  = document.getElementById('prg-main-title');
    const mainSub    = document.getElementById('prg-main-sub');
    const escuelas   = typeof ESC_DATA !== 'undefined' ? ESC_DATA : [];
    const util       = window.PRG_UTIL;
    const TAB_KEY    = 'esat.admin.programas.tab';

    const DESC_PROGRAMAS = 'Aquí podrás registrar nuevos programas académicos, consultar y editar los ya existentes, actualizar su estado y organizarlos por nivel, escuela y modalidad.';
    const DESC_ESCUELAS  = 'Gestiona las áreas académicas de la sede. Cada programa pertenece a una escuela.';

    let editingId = null;
    let modo = 'guardar'; // guardar | eliminar

    /* ── Tab switching ── */
    function mostrarTab(target) {
        tabBtns.forEach(b => b.classList.toggle('is-active', b.dataset.tab === target));
        tabPanels.forEach(p => p.classList.toggle('is-active', p.id === 'tab-' + target));
        const esEsc = target === 'escuelas';
        mainTitle.textContent    = esEsc ? 'Escuelas' : 'Programas académicos';
        mainSub.textContent      = esEsc ? DESC_ESCUELAS : DESC_PROGRAMAS;
        btnNuevo.style.display   = esEsc ? 'none' : 'flex';
        btnNuevaEc.style.display = esEsc ? 'flex' : 'none';
    }
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            mostrarTab(btn.dataset.tab);
            try { sessionStorage.setItem(TAB_KEY, btn.dataset.tab); } catch (e) {}
        });
    });
    // Tras guardar se recarga la página: volver a la pestaña en la que estaba
    try { if (sessionStorage.getItem(TAB_KEY) === 'escuelas') mostrarTab('escuelas'); } catch (e) {}

    /* ── Modal ── */
    function abrirModal(id = null, eliminar = false) {
        editingId = id;
        modo = eliminar ? 'eliminar' : 'guardar';
        const esc = escuelas.find(e => e.id === id);
        errorEl.hidden = true;
        inpNombre.classList.remove('is-invalid');
        btnSave.disabled = false;

        formEl.hidden = eliminar;
        confirmEl.hidden = !eliminar;
        btnSave.className = 'prg-btn ' + (eliminar ? 'prg-btn-danger' : 'prg-btn-primary');

        if (eliminar) {
            modalTitle.textContent = 'Eliminar escuela';
            btnSave.textContent = 'Eliminar';
            confirmEl.innerHTML = esc.programas > 0
                ? '<strong>' + esc.nombre + '</strong> tiene ' + esc.programas + (esc.programas === 1 ? ' programa' : ' programas') + ' en tu sede. Para eliminarla, primero cambia esos programas a otra escuela.'
                : '¿Eliminar la escuela <strong>' + esc.nombre + '</strong>? Esta acción no se puede deshacer.';
            btnSave.disabled = esc.programas > 0;
        } else {
            modalTitle.textContent = id ? 'Editar escuela' : 'Nueva escuela';
            btnSave.textContent = 'Guardar';
            inpNombre.value = esc ? esc.nombre : '';
        }
        modal.hidden = false;
        (eliminar ? btnCancel : inpNombre).focus();
    }

    function cerrarModal() {
        modal.hidden = true;
        inpNombre.value = '';
        editingId = null;
    }

    function mostrarError(msg) {
        errorEl.textContent = msg;
        errorEl.hidden = false;
        if (modo === 'guardar') { inpNombre.classList.add('is-invalid'); inpNombre.focus(); }
    }

    if (btnNuevaEc) btnNuevaEc.addEventListener('click', () => abrirModal());
    if (modalClose) modalClose.addEventListener('click', cerrarModal);
    if (btnCancel)  btnCancel.addEventListener('click', cerrarModal);
    inpNombre.addEventListener('input', () => { inpNombre.classList.remove('is-invalid'); errorEl.hidden = true; });
    inpNombre.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); btnSave.click(); } });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) cerrarModal(); });

    /* Cerrar al hacer clic en backdrop */
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });

    /* ── Botones de las tarjetas ── */
    document.querySelectorAll('.esc-btn-edit').forEach(btn => {
        btn.addEventListener('click', () => abrirModal(parseInt(btn.dataset.id)));
    });
    document.querySelectorAll('.esc-btn-del').forEach(btn => {
        btn.addEventListener('click', () => abrirModal(parseInt(btn.dataset.id), true));
    });

    /* ── Guardar / eliminar ── */
    btnSave.addEventListener('click', () => {
        errorEl.hidden = true;
        let peticion;
        if (modo === 'eliminar') {
            peticion = util.enviar('DELETE', '/admin/escuelas/' + editingId);
        } else {
            const nombre = inpNombre.value.trim();
            if (nombre.length < 3) { mostrarError('Escribe el nombre de la escuela (mínimo 3 letras).'); return; }
            peticion = editingId
                ? util.enviar('PUT', '/admin/escuelas/' + editingId, { nombre })
                : util.enviar('POST', '/admin/escuelas', { nombre });
        }
        btnSave.disabled = true;
        peticion
            .then(r => util.recargarConAviso(r.mensaje))
            .catch(err => {
                btnSave.disabled = false;
                mostrarError(err.errores && err.errores.nombre ? err.errores.nombre[0] : err.message);
            });
    });

})();
