// ESAT — panel Superadmin, página "Estudiantes" (solo consulta): tabla con
// búsqueda + filtros (sede, programa, estado de pago) + paginación, y un
// modal de detalle de solo lectura con el historial de pagos. No hay
// crear/editar aquí — eso es del Admin de cada sede. Falta backend real
// (consultas a matriculas/pagos).
document.addEventListener('DOMContentLoaded', function () {
    function setupTable(table) {
        var name = table.dataset.table;
        var perPage = parseInt(table.dataset.perPage, 10) || 15;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var searchInput = document.querySelector('[data-table-search="' + name + '"]');
        var sedeFilter = document.querySelector('[data-filter-sede][data-table-target="' + name + '"]');
        var programaFilter = document.querySelector('[data-filter-programa][data-table-target="' + name + '"]');
        var pagoFilter = document.querySelector('[data-filter-pago][data-table-target="' + name + '"]');
        var pagination = document.querySelector('[data-pagination-for="' + name + '"]');
        var current = 1;

        function matches(row) {
            if (searchInput && searchInput.value.trim()) {
                var q = searchInput.value.trim().toLowerCase();
                if (row.textContent.toLowerCase().indexOf(q) === -1) return false;
            }
            if (sedeFilter && sedeFilter.value !== 'todas' && row.dataset.sede !== sedeFilter.value) return false;
            if (programaFilter && programaFilter.value !== 'todos' && row.dataset.programa !== programaFilter.value) return false;
            if (pagoFilter && pagoFilter.value !== 'todos' && row.dataset.pago !== pagoFilter.value) return false;
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

        [searchInput, sedeFilter, programaFilter, pagoFilter].forEach(function (control) {
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

    // ---- Detalle de estudiante (solo lectura) ----
    var dataEl = document.getElementById('app-estudiantes-data');
    var modal = document.querySelector('[data-modal="modal-estudiante"]');
    if (!dataEl || !modal) return;

    var estudiantes = JSON.parse(dataEl.textContent);

    function formatoMonto(valor) {
        return '$' + Number(valor).toLocaleString('es-CO');
    }

    // Toda la fila abre el detalle (no solo el botón del ojo), para no
    // obligar a acertarle a un ícono pequeño.
    document.querySelectorAll('[data-table="estudiantes"] tbody tr').forEach(function (fila) {
        fila.addEventListener('click', function () {
            var est = estudiantes[parseInt(fila.dataset.estIndex, 10)];
            if (!est) return;

            modal.querySelector('[data-detalle-nombre]').textContent = est.nombres + ' ' + est.apellidos;
            modal.querySelector('[data-detalle-info]').textContent =
                'CC ' + est.documento + ' · ' + est.sede + ' · ' + est.programa;

            var cuerpo = modal.querySelector('[data-detalle-historial]');
            cuerpo.innerHTML = '';
            est.historial.forEach(function (pago) {
                var tr = document.createElement('tr');

                var tdMes = document.createElement('td');
                tdMes.textContent = pago.mes + ' 2026';
                var tdMonto = document.createElement('td');
                tdMonto.textContent = formatoMonto(pago.monto);
                var tdEstado = document.createElement('td');
                var tag = document.createElement('span');
                tag.className = 'app-status-tag ' + (pago.estado === 'Pagado' ? 'app-status-active' : pago.estado === 'Pendiente' ? 'app-status-warn' : 'app-status-danger');
                tag.textContent = pago.estado;
                tdEstado.appendChild(tag);
                var tdFecha = document.createElement('td');
                tdFecha.textContent = pago.fecha_pago || '—';

                tr.appendChild(tdMes);
                tr.appendChild(tdMonto);
                tr.appendChild(tdEstado);
                tr.appendChild(tdFecha);
                cuerpo.appendChild(tr);
            });

            modal.hidden = false;
        });
    });

    modal.querySelectorAll('[data-close-student]').forEach(function (btn) {
        btn.addEventListener('click', function () { modal.hidden = true; });
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.hidden = true;
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') modal.hidden = true;
    });
});
