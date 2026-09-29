// ESAT — panel Superadmin, página "Auditoría" (solo consulta): tabla con
// búsqueda + filtros (administrador, tipo de acción, periodo) + paginación,
// y un modal de detalle de solo lectura por evento. Falta backend real:
// esto debería salir de la tabla `auditorias`, registrada automáticamente
// cada vez que un administrador crea/edita/elimina algo.
document.addEventListener('DOMContentLoaded', function () {
    function fechaLimite(dias) {
        var d = new Date();
        d.setDate(d.getDate() - dias);
        return d.toISOString().slice(0, 10);
    }

    function setupTable(table) {
        var name = table.dataset.table;
        var perPage = parseInt(table.dataset.perPage, 10) || 15;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var searchInput = document.querySelector('[data-table-search="' + name + '"]');
        var quienFilter = document.querySelector('[data-filter-quien][data-table-target="' + name + '"]');
        var tipoFilter = document.querySelector('[data-filter-tipo][data-table-target="' + name + '"]');
        var periodoFilter = document.querySelector('[data-filter-periodo]');
        var pagination = document.querySelector('[data-pagination-for="' + name + '"]');
        var current = 1;

        function matches(row) {
            if (searchInput && searchInput.value.trim()) {
                var q = searchInput.value.trim().toLowerCase();
                if (row.textContent.toLowerCase().indexOf(q) === -1) return false;
            }
            if (quienFilter && quienFilter.value !== 'todos' && row.dataset.quien !== quienFilter.value) return false;
            if (tipoFilter && tipoFilter.value !== 'todos' && row.dataset.tipo !== tipoFilter.value) return false;
            if (periodoFilter && periodoFilter.value !== 'todo') {
                var dias = periodoFilter.value === 'hoy' ? 0 : periodoFilter.value === 'semana' ? 6 : 29;
                if (row.dataset.fecha < fechaLimite(dias)) return false;
            }
            return true;
        }

        function render() {
            var visible = rows.filter(matches);
            var totalPages = Math.max(1, Math.ceil(visible.length / perPage));
            if (current > totalPages) current = totalPages;

            var start = (current - 1) * perPage;
            var slice = visible.slice(start, start + perPage);

            rows.forEach(function (row) { row.hidden = true; });
            slice.forEach(function (row) { row.hidden = false; });

            if (pagination) {
                pagination.hidden = totalPages <= 1;
                pagination.querySelector('[data-page-label]').textContent = 'Página ' + current + ' de ' + totalPages;
                pagination.querySelector('[data-page-prev]').disabled = current === 1;
                pagination.querySelector('[data-page-next]').disabled = current === totalPages;
            }
        }

        [searchInput, quienFilter, tipoFilter, periodoFilter].forEach(function (control) {
            if (control) control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', function () {
                current = 1; render();
            });
        });
        if (pagination) {
            pagination.querySelector('[data-page-prev]').addEventListener('click', function () { if (current > 1) { current -= 1; render(); } });
            pagination.querySelector('[data-page-next]').addEventListener('click', function () { current += 1; render(); });
        }

        render();
    }

    document.querySelectorAll('[data-table]').forEach(setupTable);

    // ---- Detalle del evento (solo lectura) ----
    var dataEl = document.getElementById('app-eventos-data');
    var modal = document.querySelector('[data-modal="modal-evento"]');
    if (!dataEl || !modal) return;

    var eventos = JSON.parse(dataEl.textContent);

    // Toda la fila abre el detalle (no solo el botón del ojo), para no
    // obligar a acertarle a un ícono pequeño.
    document.querySelectorAll('[data-table="eventos"] tbody tr').forEach(function (fila) {
        fila.addEventListener('click', function () {
            var evento = eventos[parseInt(fila.dataset.eventoIndex, 10)];
            if (!evento) return;

            modal.querySelector('[data-detalle-desc]').textContent = evento.quien + ' ' + evento.descripcion;
            modal.querySelector('[data-detalle-quien]').textContent = evento.quien;
            modal.querySelector('[data-detalle-tipo]').textContent = evento.tipo.charAt(0).toUpperCase() + evento.tipo.slice(1);
            modal.querySelector('[data-detalle-sede]').textContent = evento.sede || 'Todo el sistema';
            modal.querySelector('[data-detalle-fecha]').textContent = evento.fechaTexto + ' · ' + evento.hora;

            modal.hidden = false;
        });
    });

    modal.querySelectorAll('[data-close-event]').forEach(function (btn) {
        btn.addEventListener('click', function () { modal.hidden = true; });
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.hidden = true;
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') modal.hidden = true;
    });
});
