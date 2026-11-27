/* ESAT — Secretaría › Escuelas y carreras: pestañas (Escuelas primero, solo consulta; las escuelas las maneja el Admin de ESAT) */
(function () {
    'use strict';

    const tabBtns   = document.querySelectorAll('.prg-tab-btn');
    const tabPanels = document.querySelectorAll('.prg-page-panel');
    const btnCat    = document.getElementById('btn-catalogo');
    const mainTitle = document.getElementById('prg-main-title');
    const mainSub   = document.getElementById('prg-main-sub');
    const TAB_KEY   = 'esat.admin.programas.tab';

    const DESC_PROGRAMAS = 'Las carreras que ofrece tu sede. El catálogo, la malla y los precios los maneja el Admin de ESAT: aquí eliges cuáles ofrece tu sede.';
    const DESC_ESCUELAS  = 'Las áreas académicas de ESAT. Cada carrera pertenece a una escuela; las crea y edita el Admin de ESAT.';

    function mostrarTab(target) {
        tabBtns.forEach(b => b.classList.toggle('is-active', b.dataset.tab === target));
        tabPanels.forEach(p => p.classList.toggle('is-active', p.id === 'tab-' + target));
        const esEsc = target === 'escuelas';
        mainTitle.textContent = esEsc ? 'Escuelas' : 'Carreras académicas';
        mainSub.textContent   = esEsc ? DESC_ESCUELAS : DESC_PROGRAMAS;
        if (btnCat) btnCat.hidden = esEsc;
    }
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            mostrarTab(btn.dataset.tab);
            try { sessionStorage.setItem(TAB_KEY, btn.dataset.tab); } catch (e) {}
        });
    });
    // Tras guardar se recarga la página: volver a la pestaña en la que estaba (sin nada guardado, Escuelas)
    try { if (sessionStorage.getItem(TAB_KEY) === 'programas') mostrarTab('programas'); } catch (e) {}
}());
