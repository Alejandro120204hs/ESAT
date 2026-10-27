document.addEventListener('DOMContentLoaded', function () {

    /* Las barras del gráfico y de los programas entran con una animación de CSS (transform) */
    var bars = document.querySelectorAll('.dash-bar[data-pct]');

    /* ── Animar KPI counters ─────────────────────────── */
    var kpiValues = document.querySelectorAll('.dash-kpi-value[data-target]');
    kpiValues.forEach(function (el) {
        var target = parseInt(el.dataset.target, 10) || 0;
        var prefix = el.dataset.prefix || '';
        var suffix = el.dataset.suffix || '';
        var duration = 900;
        var start = null;

        function step(ts) {
            if (!start) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var ease = 1 - Math.pow(1 - progress, 3);
            var current = Math.round(ease * target);
            el.textContent = prefix + current.toLocaleString('es-CO') + suffix;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });

    /* ── Tooltip en barras del gráfico ──────────────── */
    bars.forEach(function (bar) {
        bar.addEventListener('mouseenter', function () {
            this.setAttribute('data-val', this.dataset.val || '');
        });
    });

    /* ── Modal de actividad reciente ─────────────────── */
    var overlay   = document.getElementById('actModal');
    var modalDot  = document.getElementById('actModalDot');
    var modalText = document.getElementById('actModalText');
    var modalTime = document.getElementById('actModalTime');
    var closeBtn  = document.getElementById('actModalClose');

    function openActModal(item) {
        var dot   = item.dataset.actDot  || 'blue';
        var ini   = item.dataset.actIni  || '';
        var quien = item.dataset.actQuien || '';
        var texto = item.dataset.actTexto || '';
        var time  = item.dataset.actTime || '';

        modalDot.textContent = ini;
        modalDot.className = 'dash-act-modal-dot dot-' + dot;
        // Texto de la auditoría: siempre como texto, nunca como HTML
        modalText.textContent = '';
        var b = document.createElement('strong');
        b.textContent = quien;
        modalText.appendChild(b);
        modalText.appendChild(document.createTextNode(' · ' + texto));
        modalTime.textContent = time;
        overlay.hidden = false;
        closeBtn.focus();
    }

    function closeActModal() {
        overlay.hidden = true;
    }

    document.querySelectorAll('.dash-activity-item').forEach(function (item) {
        item.addEventListener('click', function () { openActModal(item); });
        item.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openActModal(item); }
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeActModal);
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeActModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && !overlay.hidden) closeActModal();
    });
});
