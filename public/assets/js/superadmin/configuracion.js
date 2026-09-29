/* =========================================================
   ESAT — Panel Superadmin: configuracion.js
   ========================================================= */
(function () {
    'use strict';

    // ---------- Pestañas ----------
    const tabs   = document.querySelectorAll('.cfg-tab');
    const panels = document.querySelectorAll('.cfg-panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;

            tabs.forEach(t => {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('is-active');
            tab.setAttribute('aria-selected', 'true');

            panels.forEach(panel => {
                if (panel.id === 'tab-' + target) {
                    panel.hidden = false;
                    // Relanza la animación de entrada
                    panel.style.animation = 'none';
                    panel.offsetHeight; // forzar reflow
                    panel.style.animation = '';
                } else {
                    panel.hidden = true;
                }
            });
        });
    });

    // ---------- Steppers numéricos (+/-) ----------
    document.querySelectorAll('.cfg-step-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const step  = parseInt(btn.dataset.step, 10);
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const min  = parseInt(input.min, 10) || 0;
            const max  = parseInt(input.max, 10) || 9999;
            const next = Math.min(max, Math.max(min, parseInt(input.value, 10) + step));
            input.value = next;
        });
    });

    // ---------- Logo: previsualizar archivo ----------
    const logoTrigger  = document.getElementById('cfg-logo-trigger');
    const logoInput    = document.getElementById('cfg-logo-input');
    const logoImg      = document.getElementById('cfg-logo-img');
    const logoFilename = document.getElementById('cfg-logo-filename');

    if (logoTrigger && logoInput) {
        logoTrigger.addEventListener('click', () => logoInput.click());

        logoInput.addEventListener('change', () => {
            const file = logoInput.files[0];
            if (!file) return;

            if (logoFilename) logoFilename.textContent = file.name;

            if (logoImg) {
                const reader = new FileReader();
                reader.onload = e => { logoImg.src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });
    }

    // ---------- Desbloquear usuarios (mock) ----------
    const badge     = document.getElementById('cfg-bloqueados-badge');
    const emptyMsg  = document.getElementById('cfg-bloq-empty');
    const tableWrap = document.getElementById('cfg-bloq-table-wrap');

    function actualizarBloqueados() {
        const filas = document.querySelectorAll('.cfg-bloq-row:not(.is-removing)');
        const n = filas.length;
        if (badge) badge.textContent = n || '';
        if (emptyMsg && tableWrap) {
            emptyMsg.hidden = n > 0;
            tableWrap.hidden = n === 0;
        }
    }

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-unlock]');
        if (!btn) return;
        const fila = btn.closest('.cfg-bloq-row');
        if (!fila) return;
        fila.classList.add('is-removing');
        setTimeout(() => {
            fila.remove();
            actualizarBloqueados();
        }, 320);
    });

    // ---------- Botones guardar (mock — muestra toast) ----------
    const toast = document.getElementById('cfg-toast');

    document.querySelectorAll('[data-save]').forEach(btn => {
        btn.addEventListener('click', mostrarToast);
    });

    function mostrarToast() {
        if (!toast) return;
        clearTimeout(toast._timer);
        toast.hidden = false;
        toast.offsetHeight; // forzar repaint antes de la transición
        toast.classList.add('is-visible');
        toast._timer = setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => { toast.hidden = true; }, 300);
        }, 3000);
    }

})();
