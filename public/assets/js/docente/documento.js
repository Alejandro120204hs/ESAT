/* ESAT — Docente: documentos imprimibles (lista de clase y planilla de notas), copiado del panel Estudiante.
   DOC_DOC.imprimir({ titulo, numero, extra, contenido }) arma la hoja de documento.css con el encabezado de ESAT (datos
   legales de Admin › Configuración, que pasa docente/partials/datos.blade.php) y abre el diálogo de impresión, donde
   también se puede guardar en PDF. `contenido` ya viene escapado por quien lo arma. */
(function () {
    'use strict';

    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    window.DOC_DOC = {
        esc: esc,
        imprimir: function (doc) {
            var S = window.DOC_SERVIDOR || {}, inst = S.institucion || {}, sede = S.sede || null;
            var cont = document.getElementById('doc-imp');
            if (!cont) {
                cont = document.createElement('div');
                cont.id = 'doc-imp';
                cont.className = 'doc-imp';
                document.body.appendChild(cont);
            }
            var lineaSede = sede ? 'Sede ' + sede.nombre + (sede.direccion ? ' · ' + sede.direccion : '') + (sede.ciudad ? ' · ' + sede.ciudad : '') : '';
            cont.innerHTML =
                '<main class="rc-hoja">' +
                    (doc.sello ? '<div class="rc-sello" aria-hidden="true">' + esc(doc.sello) + '</div>' : '') +
                    '<header class="rc-cabecera">' +
                        '<div class="rc-marca">' +
                            '<img src="' + esc(S.logo || '') + '" alt="ESAT">' +
                            '<div>' +
                                '<strong>' + esc(inst.nombre || 'ESAT') + '</strong>' +
                                '<span>NIT ' + esc(inst.nit || '') + ' · Lic. Funcionamiento ' + esc(inst.licencia || '') + '</span>' +
                                (lineaSede ? '<span>' + esc(lineaSede) + '</span>' : '') +
                            '</div>' +
                        '</div>' +
                        '<div class="rc-numero">' +
                            '<span>' + esc(doc.titulo) + '</span>' +
                            (doc.numero ? '<strong>' + esc(doc.numero) + '</strong>' : '') +
                            (doc.extra ? '<span>' + esc(doc.extra) + '</span>' : '') +
                        '</div>' +
                    '</header>' +
                    doc.contenido +
                '</main>';
            // Esperar a que cargue el logo para que salga en la impresión
            var img = cont.querySelector('img');
            var listo = function () { window.print(); };
            if (img && !img.complete) { img.addEventListener('load', listo, { once: true }); img.addEventListener('error', listo, { once: true }); }
            else listo();
        }
    };
})();
