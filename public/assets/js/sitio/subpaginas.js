/* =========================================================
   ESAT — sitio público · subpáginas
   - Barra de inscripción: aparece siempre que el botón "Quiero
     inscribirme" de la portada no está a la vista (también al cargar, si
     en un celular quedó debajo del borde) y se va al llegar al cierre del
     footer, que ya invita a inscribirse. Mientras está oculta queda inert.
   - Foto de práctica: se abre con el scroll, de un marco angosto a
     todo el ancho.
   Lo común (entradas, parallax) lo pone motor.js.
   ========================================================= */
(function () {
    "use strict";

    var ESAT = window.ESAT || {};

    /* ---------- Barra de inscripción ---------- */
    (function () {
        var barra = document.getElementById("subBarra");
        var portada = document.querySelector("#portada .sub-hero-acciones") || document.getElementById("portada");
        var cierre = document.getElementById("contacto");
        if (!barra || !portada || !("IntersectionObserver" in window)) return;

        var portadaVisible = true, cierreVisible = false;
        function pintar() {
            var mostrar = !portadaVisible && !cierreVisible;
            barra.classList.toggle("is-visible", mostrar);
            document.body.classList.toggle("con-barra", mostrar);
            if (mostrar) barra.removeAttribute("inert"); else barra.setAttribute("inert", "");
        }
        new IntersectionObserver(function (e) { portadaVisible = e[0].intersectionRatio > 0.99; pintar(); }, { rootMargin: "-80px 0px 0px 0px", threshold: [0, 1] }).observe(portada);
        if (cierre) new IntersectionObserver(function (e) { cierreVisible = e[0].isIntersecting; pintar(); }).observe(cierre);
    })();

    if (!ESAT.animar) return;
    var gsap = window.gsap;

    /* ---------- Foto de práctica ---------- */
    var marco = document.getElementById("practicaMarco");
    if (marco && !marco.classList.contains("sin-foto")) {
        var linea = gsap.timeline({
            scrollTrigger: { trigger: marco, start: "top 90%", end: "center 55%", scrub: 0.5 }
        });
        linea.fromTo(marco, { clipPath: "inset(7% 13% 7% 13% round 32px)" }, { clipPath: "inset(0% 0% 0% 0% round 32px)", ease: "none" }, 0)
             .fromTo(marco.querySelector("img"), { scale: 1.18 }, { scale: 1, ease: "none" }, 0);
    }
})();
