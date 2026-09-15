// ESAT — comportamiento propio del panel Superadmin: menú lateral en
// móvil y el desplegable de usuario (perfil / cerrar sesión).
document.addEventListener('DOMContentLoaded', function () {
    var shell = document.querySelector('.app-shell');
    var toggle = document.querySelector('.app-menu-toggle');
    var backdrop = document.querySelector('.app-sidebar-backdrop');

    if (shell && toggle && backdrop) {
        backdrop.addEventListener('click', function () {
            shell.classList.remove('is-nav-open');
        });
        toggle.addEventListener('click', function () {
            shell.classList.toggle('is-nav-open');
        });
    }

    var userMenu = document.querySelector('.app-user');
    var userTrigger = document.querySelector('.app-user-trigger');

    if (userMenu && userTrigger) {
        userTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = userMenu.classList.toggle('is-open');
            userTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('is-open');
                userTrigger.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                userMenu.classList.remove('is-open');
                userTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
