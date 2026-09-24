// ESAT — panel Superadmin, página de inicio: paginación de la lista de
// alertas (4 por página). Solo de frontend por ahora: pagina el bloque de
// alertas ya renderizado; cuando exista backend real esto se reemplaza por
// paginación por consulta.
document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('.app-alert-item');
    var indicator = document.querySelector('[data-alerts-indicator]');
    var prevBtn = document.querySelector('[data-alerts-prev]');
    var nextBtn = document.querySelector('[data-alerts-next]');

    if (!items.length || !indicator || !prevBtn || !nextBtn) {
        return;
    }

    var totalPages = 1;
    items.forEach(function (item) {
        var page = parseInt(item.dataset.page, 10) || 0;
        totalPages = Math.max(totalPages, page + 1);
    });

    var current = 0;

    function render() {
        items.forEach(function (item) {
            var page = parseInt(item.dataset.page, 10) || 0;
            item.hidden = page !== current;
        });
        indicator.textContent = 'Página ' + (current + 1) + ' de ' + totalPages;
        prevBtn.disabled = current === 0;
        nextBtn.disabled = current === totalPages - 1;
    }

    prevBtn.addEventListener('click', function () {
        if (current > 0) {
            current -= 1;
            render();
        }
    });

    nextBtn.addEventListener('click', function () {
        if (current < totalPages - 1) {
            current += 1;
            render();
        }
    });

    render();
});
