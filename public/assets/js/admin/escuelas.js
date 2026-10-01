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
    const mainTitle  = document.getElementById('prg-main-title');
    const mainSub    = document.getElementById('prg-main-sub');

    const DESC_PROGRAMAS = 'Aquí podrás registrar nuevos programas académicos, consultar y editar los ya existentes, actualizar su estado y organizarlos por nivel, escuela y modalidad.';
    const DESC_ESCUELAS  = 'Gestiona las áreas académicas de la sede. Cada programa pertenece a una escuela.';

    let editingId = null;

    /* ── Tab switching ── */
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;
            tabBtns.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            tabPanels.forEach(p => p.classList.toggle('is-active', p.id === 'tab-' + target));

            if (target === 'escuelas') {
                mainTitle.textContent    = 'Escuelas';
                mainSub.textContent      = DESC_ESCUELAS;
                btnNuevo.style.display   = 'none';
                btnNuevaEc.style.display = 'flex';
            } else {
                mainTitle.textContent    = 'Programas académicos';
                mainSub.textContent      = DESC_PROGRAMAS;
                btnNuevo.style.display   = 'flex';
                btnNuevaEc.style.display = 'none';
            }
        });
    });

    /* ── Abrir modal nueva escuela ── */
    function abrirModal(id = null) {
        editingId = id;
        if (id) {
            const esc = (typeof ESC_DATA !== 'undefined' ? ESC_DATA : []).find(e => e.id === id);
            modalTitle.textContent = 'Editar escuela';
            inpNombre.value = esc ? esc.nombre : '';
        } else {
            modalTitle.textContent = 'Nueva escuela';
            inpNombre.value = '';
        }
        modal.hidden = false;
        inpNombre.focus();
    }

    function cerrarModal() {
        modal.hidden = true;
        inpNombre.value = '';
        editingId = null;
    }

    if (btnNuevaEc) btnNuevaEc.addEventListener('click', () => abrirModal());
    if (modalClose) modalClose.addEventListener('click', cerrarModal);
    if (btnCancel)  btnCancel.addEventListener('click', cerrarModal);

    /* Cerrar al hacer clic en backdrop */
    if (modal) {
        modal.addEventListener('click', e => {
            if (e.target === modal) cerrarModal();
        });
    }

    /* ── Botones editar en las cards ── */
    document.querySelectorAll('.esc-btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id);
            abrirModal(id);
        });
    });

    /* ── Guardar (mock: solo cierra modal) ── */
    if (btnSave) {
        btnSave.addEventListener('click', () => {
            const nombre = inpNombre.value.trim();
            if (!nombre) { inpNombre.focus(); return; }
            cerrarModal();
        });
    }

    /* ── Botones eliminar (mock: solo feedback visual) ── */
    document.querySelectorAll('.esc-btn-del').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.esc-card');
            if (card) {
                card.style.opacity = '0.4';
                card.style.pointerEvents = 'none';
            }
        });
    });

})();
