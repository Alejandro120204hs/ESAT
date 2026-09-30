document.addEventListener('DOMContentLoaded', function () {
    var shell   = document.querySelector('.app-shell');
    var toggle  = document.querySelector('.app-menu-toggle');
    var backdrop = document.querySelector('.app-sidebar-backdrop');
    var userBtn  = document.querySelector('.app-user-trigger');
    var userEl   = document.querySelector('.app-user');

    // Flyout lateral del sidebar (Finanzas, Comunicación)
    var flyoutBtns = document.querySelectorAll('.app-nav-flyout-btn');

    function closeAllFlyouts() {
        document.querySelectorAll('.app-nav-flyout.is-visible').forEach(function (f) {
            f.classList.remove('is-visible');
        });
        flyoutBtns.forEach(function (b) { b.classList.remove('is-open'); });
    }

    flyoutBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var flyout = document.getElementById(btn.dataset.flyout);
            if (!flyout) return;
            var isOpen = flyout.classList.contains('is-visible');
            closeAllFlyouts();
            if (!isOpen) {
                var rect = btn.getBoundingClientRect();
                flyout.style.top = rect.top + 'px';
                flyout.classList.add('is-visible');
                btn.classList.add('is-open');
            }
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.app-nav-flyout') && !e.target.closest('.app-nav-flyout-btn')) {
            closeAllFlyouts();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllFlyouts();
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
