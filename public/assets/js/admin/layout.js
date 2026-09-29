document.addEventListener('DOMContentLoaded', function () {
    var shell   = document.querySelector('.app-shell');
    var toggle  = document.querySelector('.app-menu-toggle');
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
});
