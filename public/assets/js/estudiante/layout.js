document.addEventListener('DOMContentLoaded', function () {
    var shell    = document.querySelector('.app-shell');
    var toggle   = document.querySelector('.app-menu-toggle');
    var backdrop = document.querySelector('.app-sidebar-backdrop');
    var userBtn  = document.querySelector('.app-user-trigger');
    var userEl   = document.querySelector('.app-user');

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

    // Al cerrar sesión se borra lo que el Aula guardó en este navegador (marcas de evaluaciones): en un computador
    // compartido el siguiente estudiante no lo encuentra. Se borra al salir de verdad de la página: si el navegador
    // pregunta "¿Salir del sitio?" (evaluación corriendo) y elige quedarse, las marcas siguen (reauditoría, N4)
    var saliendo = false;
    document.querySelectorAll('form[action$="/logout"]').forEach(function (f) {
        f.addEventListener('submit', function () {
            saliendo = true;
            // Si se quedó, lo siguiente que hace en la página (un clic o una tecla) dice que no salió
            var quedarse = function () { saliendo = false; };
            document.addEventListener('pointerdown', quedarse, { once: true, capture: true });
            document.addEventListener('keydown', quedarse, { once: true, capture: true });
        });
    });
    window.addEventListener('pagehide', function () {
        if (!saliendo) return;
        try {
            [window.localStorage, window.sessionStorage].forEach(function (a) {
                for (var i = a.length - 1; i >= 0; i--) {
                    var k = a.key(i);
                    if (k && k.indexOf('aul-marcas-') === 0) a.removeItem(k);
                }
            });
        } catch (e) { /* sin almacenamiento */ }
    });
});
