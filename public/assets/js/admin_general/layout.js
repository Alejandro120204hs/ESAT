document.addEventListener('DOMContentLoaded', function () {
    var shell    = document.querySelector('.app-shell');
    var toggle   = document.querySelector('.app-menu-toggle');
    var backdrop = document.querySelector('.app-sidebar-backdrop');
    var userBtn  = document.querySelector('.app-user-trigger');
    var userEl   = document.querySelector('.app-user');

    /* Grupos del menú que se despliegan a un lado (Supervisión), igual que en la secretaría */
    var flyoutBtns = document.querySelectorAll('.app-nav-flyout-btn');
    function closeAllFlyouts() {
        document.querySelectorAll('.app-nav-flyout.is-visible').forEach(function (f) { f.classList.remove('is-visible'); });
        flyoutBtns.forEach(function (b) { b.classList.remove('is-open'); b.setAttribute('aria-expanded', 'false'); });
    }
    /* Abre alineado con el botón; si no cabe hacia abajo (Supervisión está al final del menú), sube hasta que su
       borde inferior quede con el del botón, y nunca se sale de la pantalla */
    function ubicarFlyout(btn, flyout) {
        var margen = 12, b = btn.getBoundingClientRect(), alto = flyout.offsetHeight;
        var top = b.top;
        if (top + alto > window.innerHeight - margen) top = b.bottom - alto;
        top = Math.max(margen, Math.min(top, window.innerHeight - margen - alto));
        flyout.style.top = top + 'px';
    }
    window.addEventListener('resize', function () { closeAllFlyouts(); });

    flyoutBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var flyout = document.getElementById(btn.dataset.flyout);
            if (!flyout) return;
            var isOpen = flyout.classList.contains('is-visible');
            closeAllFlyouts();
            if (!isOpen) {
                ubicarFlyout(btn, flyout);
                flyout.classList.add('is-visible');
                btn.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
                var primero = flyout.querySelector('a');
                if (primero && e.detail === 0) primero.focus();   // con teclado, el foco pasa a la primera opción
            }
        });
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.app-nav-flyout') && !e.target.closest('.app-nav-flyout-btn')) closeAllFlyouts();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var abierto = document.querySelector('.app-nav-flyout-btn.is-open');
        closeAllFlyouts();
        if (abierto) abierto.focus();
    });
    var navLateral = document.querySelector('.app-sidebar-nav');
    /* Si el menú se desplaza, el panel acompaña a su botón; solo se cierra cuando el botón sale de la vista */
    if (navLateral) navLateral.addEventListener('scroll', function () {
        var btn = document.querySelector('.app-nav-flyout-btn.is-open');
        if (!btn) return;
        var b = btn.getBoundingClientRect(), n = navLateral.getBoundingClientRect();
        if (b.bottom < n.top || b.top > n.bottom) { closeAllFlyouts(); return; }
        ubicarFlyout(btn, document.getElementById(btn.dataset.flyout));
    });

    if (toggle && shell) {
        toggle.addEventListener('click', function () {
            shell.classList.toggle('is-nav-open');
        });
    }

    if (backdrop && shell) {
        backdrop.addEventListener('click', function () {
            shell.classList.remove('is-nav-open');
        });
    }

    if (userBtn && userEl) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            userEl.classList.toggle('is-open');
        });
        document.addEventListener('click', function () {
            userEl.classList.remove('is-open');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') userEl.classList.remove('is-open');
        });
    }
});
