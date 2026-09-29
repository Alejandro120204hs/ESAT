document.addEventListener('DOMContentLoaded', function () {

    /* ── Animar barras del gráfico ─────────────────── */
    var bars = document.querySelectorAll('.dash-bar[data-pct]');
    bars.forEach(function (bar) {
        var pct = parseFloat(bar.dataset.pct) || 0;
        bar.style.height = '0%';
        setTimeout(function () {
            bar.style.height = pct + '%';
        }, 200);
    });

    /* ── Animar barras de progreso de programas ─────── */
    var progBars = document.querySelectorAll('.dash-prog-bar-fill[data-w]');
    progBars.forEach(function (pb) {
        var w = parseFloat(pb.dataset.w) || 0;
        pb.style.width = '0%';
        setTimeout(function () {
            pb.style.width = w + '%';
        }, 300);
    });

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
        var html  = item.dataset.actTextoHtml || item.dataset.actTexto || '';
        var time  = item.dataset.actTime || '';

        modalDot.textContent = ini;
        modalDot.className = 'dash-act-modal-dot dot-' + dot;
        modalText.innerHTML = html;
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
