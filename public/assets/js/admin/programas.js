/* ESAT — Admin: Programas */
(function () {
    'use strict';

    var data  = window.PRG_DATA || [];
    var cuota = window.PRG_CUOTA || 0;
    var csrf  = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var FLASH = 'esat.admin.programas.aviso';

    /* ── Utilidades ───────────────────────────── */
    function fmt(n) {
        return '$ ' + Number(n).toLocaleString('es-CO');
    }
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }

    /* Petición JSON al backend; los errores de validación (422) llegan con su mensaje */
    function enviar(metodo, url, cuerpo) {
        return fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) {
                    var err = new Error(j.message || 'No se pudo guardar. Intenta de nuevo.');
                    err.errores = j.errors || null;
                    throw err;
                }
                return j;
            });
        });
    }
    /* Guarda el aviso y recarga: la tabla y los KPI salen del servidor */
    function recargarConAviso(msg) {
        try { sessionStorage.setItem(FLASH, msg); } catch (e) {}
        location.reload();
    }
    var toastEl = document.getElementById('prg-toast');
    function toast(msg) {
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 4200);
    }
    try {
        var aviso = sessionStorage.getItem(FLASH);
        if (aviso) { sessionStorage.removeItem(FLASH); toast(aviso); }
    } catch (e) {}
    function badge(estado) {
        var map = {
            activo:         '<span class="app-status-tag app-status-active">Activo</span>',
            en_aprobacion:  '<span class="app-status-tag app-status-warn">En aprobación</span>',
            inactivo:       '<span class="app-status-tag app-status-muted">Inactivo</span>',
        };
        return map[estado] || '';
    }
    /* El color de la escuela sale de su sigla (sal, tur, adm...) */
    function escClass(p) {
        return p.sigla || 'adm';
    }

    /* ── Filtros / búsqueda / paginación ─────────────── */
    var search       = document.getElementById('prg-search');
    var selEsc       = document.getElementById('prg-filter-escuela');
    var selEst       = document.getElementById('prg-filter-estado');
    var countBdg     = document.getElementById('prg-count');
    var emptyEl      = document.getElementById('prg-empty');
    var paginationEl = document.getElementById('prg-pagination');
    var pagesEl      = document.getElementById('prg-pg-pages');
    var btnPrev      = document.getElementById('prg-pg-prev');
    var btnNext      = document.getElementById('prg-pg-next');

    var PAGE_SIZE    = 10;
    var currentPage  = 1;
    var filteredRows = [];

    function applyFilters() {
        var q   = (search.value || '').toLowerCase().trim();
        var esc = selEsc.value;
        var est = selEst.value;
        var allRows = document.querySelectorAll('#prg-table .prg-row');

        filteredRows = [];
        allRows.forEach(function(row) {
            var match =
                (!q   || row.dataset.nombre.includes(q)) &&
                (!esc || row.dataset.escuela === esc) &&
                (!est || row.dataset.estado  === est);
            if (match) filteredRows.push(row);
        });

        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        var total   = filteredRows.length;
        var pages   = Math.max(1, Math.ceil(total / PAGE_SIZE));
        var allRows = document.querySelectorAll('#prg-table .prg-row');
        var start   = (currentPage - 1) * PAGE_SIZE;
        var end     = start + PAGE_SIZE;

        allRows.forEach(function(row) { row.classList.add('is-hidden'); });
        filteredRows.forEach(function(row, i) {
            if (i >= start && i < end) row.classList.remove('is-hidden');
        });

        countBdg.textContent = total + ' programa' + (total !== 1 ? 's' : '');
        emptyEl.style.display = total === 0 ? 'block' : 'none';

        if (!paginationEl) return;
        paginationEl.style.display = pages <= 1 ? 'none' : '';

        if (pages > 1) {
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === pages;

            pagesEl.innerHTML = '';
            buildPageRange(currentPage, pages).forEach(function(p) {
                if (p === '...') {
                    var sp = document.createElement('span');
                    sp.className = 'prg-pg-ellipsis';
                    sp.textContent = '…';
                    pagesEl.appendChild(sp);
                } else {
                    var btn = document.createElement('button');
                    btn.className = 'prg-pg-num' + (p === currentPage ? ' is-active' : '');
                    btn.textContent = p;
                    (function(pg) {
                        btn.addEventListener('click', function() { goToPage(pg); });
                    }(p));
                    pagesEl.appendChild(btn);
                }
            });
        }
    }

    function buildPageRange(current, total) {
        if (total <= 7) {
            var r = [];
            for (var i = 1; i <= total; i++) r.push(i);
            return r;
        }
        var range = [1];
        if (current > 3) range.push('...');
        for (var i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) range.push(i);
        if (current < total - 2) range.push('...');
        range.push(total);
        return range;
    }

    function goToPage(p) {
        currentPage = p;
        renderPage();
        var wrap = document.querySelector('.prg-table-wrap');
        if (wrap) wrap.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    if (btnPrev) btnPrev.addEventListener('click', function() {
        if (currentPage > 1) goToPage(currentPage - 1);
    });
    if (btnNext) btnNext.addEventListener('click', function() {
        var pages = Math.ceil(filteredRows.length / PAGE_SIZE);
        if (currentPage < pages) goToPage(currentPage + 1);
    });

    if (search)  search.addEventListener('input',  applyFilters);
    if (selEsc)  selEsc.addEventListener('change', applyFilters);
    if (selEst)  selEst.addEventListener('change', applyFilters);

    /* ── Drawer ───────────────────────────────── */
    var drawer        = document.getElementById('prg-drawer');
    var drawerOverlay = document.getElementById('prg-drawer-overlay');
    var drawerClose   = document.getElementById('prg-drawer-close');
    var currentPrg    = null;

    function openDrawer(id) {
        currentPrg = data.find(function (p) { return p.id === id; });
        if (!currentPrg) return;
        fillDrawer(currentPrg);
        // reset to first tab
        setDrawerTab('general');
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);
    if (drawerClose)   drawerClose.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDrawer(); closeModal(); }
    });

    function fillDrawer(p) {
        set('dw-codigo',     p.codigo);
        set('dw-nombre',     p.nombre);
        set('dw-escuela',    p.escuela);
        set('dw-nivel',      p.nivel);
        set('dw-modalidad',  p.modalidad);
        set('dw-duracion',   Number(p.horas).toLocaleString('es-CO') + ' h · ' + p.meses + ' meses');
        set('dw-jornadas',   p.jornadas.join(' · '));
        set('dw-cupo',       p.cupo + ' estudiantes por grupo');
        set('dw-resolucion', p.resolucion || 'Sin registrar');
        set('dw-descripcion',p.descripcion || 'Sin descripción.');
        set('dw-perfil',     p.perfil_egreso || 'Sin registrar.');
        set('dw-valor-programa', fmt(p.valor_programa));
        set('dw-matricula',      fmt(p.matricula));
        set('dw-valor-mensual',  fmt(cuota) + ' / mes');
        set('dw-periodos',       p.periodos + ' ' + (p.tipo_periodo === 'semestre' ? (p.periodos === 1 ? 'semestre' : 'semestres') : (p.periodos === 1 ? 'trimestre' : 'trimestres')));
        html('dw-badges',        badge(p.estado) + ' <span class="prg-esc-tag prg-esc-' + escClass(p) + '">' + esc(p.escuela) + '</span>');
        set('dw-doc-res',    p.resolucion || 'Sin registrar');
        set('dw-est-txt',    p.estudiantes === 1 ? '1 estudiante matriculado en este programa' : p.estudiantes + ' estudiantes matriculados en este programa');

        // Módulos
        var tbody = document.getElementById('dw-modulos-body');
        if (tbody) {
            var totalH = 0;
            tbody.innerHTML = p.modulos.length ? p.modulos.map(function (m, i) {
                totalH += m.horas;
                return '<tr>'
                    + '<td class="prg-mod-num">' + (i+1) + '</td>'
                    + '<td>' + esc(m.nombre) + '</td>'
                    + '<td class="prg-mod-horas">' + m.horas + ' h</td>'
                    + '<td class="prg-mod-docente">' + esc(m.docente) + '</td>'
                    + '</tr>';
            }).join('') : '<tr><td colspan="4" class="prg-mod-docente">Este programa todavía no tiene módulos registrados.</td></tr>';
            set('dw-horas-total', Number(totalH).toLocaleString('es-CO') + ' h totales');
        }

        // Grupos
        var gruposGrid = document.getElementById('dw-grupos-grid');
        if (gruposGrid) {
            gruposGrid.innerHTML = !p.grupos.length
                ? '<div class="prg-placeholder"><p>Este programa todavía no tiene grupos en tu sede</p><span>Los grupos se crean en el módulo Grupos</span></div>'
                : p.grupos.map(function (g) {
                var pct = Math.round((g.inscritos / g.cupo) * 100);
                return '<div class="prg-grupo-card">'
                    + '<div>'
                        + '<div class="prg-grupo-nombre">' + esc(g.nombre) + '</div>'
                        + '<div class="prg-grupo-meta">Jornada ' + esc(g.jornada) + '</div>'
                    + '</div>'
                    + '<div class="prg-grupo-cupo">'
                        + '<div class="prg-grupo-cupo-num">' + g.inscritos + ' / ' + g.cupo + '</div>'
                        + '<div class="prg-grupo-cupo-lbl">inscritos</div>'
                    + '</div>'
                    + '<div class="prg-grupo-bar"><div class="prg-grupo-fill" style="width:' + pct + '%"></div></div>'
                    + '<div class="prg-grupo-fechas">'
                        + '<span>Inicio: <strong>' + esc(g.inicio) + '</strong></span>'
                        + '<span>Fin: <strong>' + esc(g.fin) + '</strong></span>'
                    + '</div>'
                    + '</div>';
            }).join('');
        }

    }

    function set(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }
    function html(id, markup) {
        var el = document.getElementById(id);
        if (el) el.innerHTML = markup;
    }

    // Tabs del drawer
    function setDrawerTab(tab) {
        document.querySelectorAll('.prg-dtab').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.tab === tab);
        });
        document.querySelectorAll('.prg-tab-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.panel === tab);
        });
    }
    document.querySelectorAll('.prg-dtab').forEach(function (btn) {
        btn.addEventListener('click', function () { setDrawerTab(btn.dataset.tab); });
    });

    // Acciones de la tabla → abrir drawer
    document.querySelectorAll('[data-action="ver"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openDrawer(Number(btn.dataset.id));
        });
    });
    document.querySelectorAll('.prg-row').forEach(function (row) {
        row.addEventListener('click', function () {
            openDrawer(Number(row.dataset.id));
        });
    });

    /* ── Dejar de ofrecer el programa en la sede (no con grupos vigentes; lo revisa el servidor) ── */
    var quitarBtn = document.getElementById('prg-drawer-quitar-btn');
    if (quitarBtn) {
        quitarBtn.addEventListener('click', function () {
            if (!currentPrg) return;
            quitarBtn.disabled = true;
            enviar('PATCH', '/admin/programas/' + currentPrg.id + '/oferta')
                .then(function (r) { recargarConAviso(r.mensaje); })
                .catch(function (err) { quitarBtn.disabled = false; toast(err.message); });
        });
    }

    /* ── Agregar del catálogo: los programas activos que la sede todavía no ofrece ── */
    var catModal = document.getElementById('prg-cat-modal');
    if (catModal) {
        var volverCat = null;
        var abrirCat = function () { volverCat = document.activeElement; catModal.hidden = false; var b = catModal.querySelector('button'); if (b) b.focus(); };
        var cerrarCat = function () { if (catModal.hidden) return; catModal.hidden = true; if (volverCat && document.contains(volverCat)) volverCat.focus(); };
        document.getElementById('btn-catalogo').addEventListener('click', abrirCat);
        catModal.querySelectorAll('[data-cerrar-cat]').forEach(function (b) { b.addEventListener('click', cerrarCat); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarCat(); });
        catModal.addEventListener('click', function (e) {
            var b = e.target.closest('[data-ofrecer]'); if (!b) return;
            b.disabled = true;
            enviar('PATCH', '/admin/programas/' + b.dataset.ofrecer + '/oferta')
                .then(function (r) { recargarConAviso(r.mensaje); })
                .catch(function (err) { b.disabled = false; toast(err.message); });
        });
    }

    /* ── Combobox genérico ────────────────────── */
    function initCombobox(root) {
        var trigger     = root.querySelector('[data-combobox-trigger]');
        var panel       = root.querySelector('[data-combobox-panel]');
        var list        = root.querySelector('[data-combobox-list]');
        var searchInput = root.querySelector('[data-combobox-search]');
        var hidden      = root.querySelector('[data-combobox-value]');
        var label       = root.querySelector('[data-combobox-label]');
        var placeholder = label.textContent;

        /* Abre hacia donde quepa: mide el alto real del panel (buscador + márgenes)
           y decide en cada apertura. data-direction="up" es solo una preferencia. */
        function posicionar() {
            var rect   = trigger.getBoundingClientRect();
            var margen = 12;
            panel.style.left  = rect.left + 'px';
            panel.style.width = rect.width + 'px';

            var abajo  = window.innerHeight - rect.bottom - 6 - margen;
            var arriba = rect.top - 6 - margen;
            list.style.maxHeight = '';
            var extra = panel.offsetHeight - list.offsetHeight;
            var tope  = parseInt(root.dataset.listMaxHeight) || 320;
            var necesario = Math.min(list.scrollHeight, tope) + extra;
            var haciaArriba = root.dataset.direction === 'up'
                ? (arriba >= necesario || arriba > abajo)
                : (abajo < necesario && arriba > abajo);
            var espacio = haciaArriba ? arriba : abajo;

            panel.style.top    = haciaArriba ? '' : (rect.bottom + 6) + 'px';
            panel.style.bottom = haciaArriba ? (window.innerHeight - rect.top + 6) + 'px' : '';
            list.style.maxHeight = Math.max(Math.min(tope, espacio - extra), 40) + 'px';
        }
        var esAbsoluto = root.dataset.position === 'absolute';
        /* Se recoloca cuando la página se desplaza, pero no con el scroll de su propia
           lista: recolocar reinicia el alto de la lista y la devolvía al inicio. */
        function alScroll(e) {
            if (panel.contains(e.target)) return;
            posicionar();
        }
        function abrir() {
            panel.hidden = false;
            root.classList.add('is-open');
            if (!esAbsoluto) {
                posicionar();
                window.addEventListener('scroll', alScroll, true);
                window.addEventListener('resize', posicionar);
            }
            if (searchInput) {
                searchInput.value = '';
                filtrar('');
                searchInput.focus();
            }
        }
        function cerrar() {
            panel.hidden = true;
            root.classList.remove('is-open');
            if (!esAbsoluto) {
                window.removeEventListener('scroll', alScroll, true);
                window.removeEventListener('resize', posicionar);
            }
        }
        function filtrar(q) {
            var q2 = q.toLowerCase();
            var items = list.querySelectorAll('li[data-value]');
            var visible = 0;
            items.forEach(function (li) {
                var match = li.dataset.label.toLowerCase().includes(q2);
                li.hidden = !match;
                if (match) visible++;
            });
            var empty = list.querySelector('.app-combobox-empty');
            if (empty) empty.hidden = visible > 0;
        }

        trigger.addEventListener('click', function () {
            panel.hidden ? abrir() : cerrar();
        });
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                filtrar(searchInput.value);
            });
        }
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) cerrar();
        });

        root.setOptions = function (opciones) {
            list.innerHTML = '';
            opciones.forEach(function (opcion) {
                var val = typeof opcion === 'object' ? opcion.value : opcion;
                var lbl = typeof opcion === 'object' ? opcion.label : opcion;
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = val;
                li.dataset.label = lbl;
                var span = document.createElement('span');
                span.textContent = lbl;
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                li.appendChild(span);
                li.appendChild(svg);
                li.addEventListener('click', function () { root.setValue(val, lbl); cerrar(); });
                list.appendChild(li);
            });
            var empty = document.createElement('li');
            empty.className = 'app-combobox-empty';
            empty.hidden = opciones.length > 0;
            empty.textContent = 'Sin resultados';
            list.appendChild(empty);
        };
        root.setValue = function (valor, etiqueta, opts) {
            hidden.value = valor || '';
            label.textContent = etiqueta || valor || placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.toggle('is-selected', li.dataset.value === valor);
            });
            if (!(opts && opts.silent)) {
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        root.reset = function () {
            hidden.value = '';
            label.textContent = placeholder;
            root.classList.remove('is-invalid');
            list.querySelectorAll('li[data-value]').forEach(function (li) {
                li.classList.remove('is-selected');
            });
        };
        root.value = function () { return hidden.value; };
    }

    /* ── Filtros de la tabla ─────────────────── */
    var cbFiltEsc = document.getElementById('prg-cb-filter-escuela');
    var cbFiltEst = document.getElementById('prg-cb-filter-estado');

    if (cbFiltEsc) {
        initCombobox(cbFiltEsc);
        var pEscOpts = JSON.parse(cbFiltEsc.dataset.options || '[]');
        cbFiltEsc.setOptions([{value:'', label:'Todas las escuelas'}].concat(pEscOpts.map(function(v){return {value:v, label:v};})));
        cbFiltEsc.setValue('', 'Todas las escuelas', {silent:true});
    }
    if (cbFiltEst) {
        initCombobox(cbFiltEst);
        cbFiltEst.setOptions([
            {value:'', label:'Todos los estados'},
            {value:'activo', label:'Activo'},
            {value:'en_aprobacion', label:'En aprobación'},
            {value:'inactivo', label:'Inactivo'},
        ]);
        cbFiltEst.setValue('', 'Todos los estados', {silent:true});
    }

    // Inicializar
    applyFilters();

}());
