/* ESAT — Admin: Documentos imprimibles de Matrículas (recibo, devolución, constancia, paz y salvo) */
(function () {
    'use strict';

    var btn = document.getElementById('rc-imprimir');
    if (btn) btn.addEventListener('click', function () { window.print(); });
}());
