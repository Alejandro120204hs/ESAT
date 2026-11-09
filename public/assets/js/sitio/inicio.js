/* =========================================================
   ESAT — sitio público · Inicio
   Lo propio de esta página; lo común (scroll suave, entradas,
   parallax, contadores) lo pone motor.js con atributos data-*.
   - Portada: las fotos rotan con su barra de avance y, al bajar,
     se alejan y se oscurecen.
   - Por qué ESAT: la línea naranja avanza con el scroll y enciende
     cada beneficio al pasar.
   - Nuevas carreras: en escritorio la sección se fija y la pista
     corre en horizontal; en celular se desliza con el dedo.
   - Sedes: cada tarjeta mueve el mapa a su sede.
   - Convenios: la cinta de municipios acelera con el scroll.
   ========================================================= */
(function () {
    "use strict";

    var ESAT = window.ESAT || {};
    var reducir = !!ESAT.reducir;
    var animar = !!ESAT.animar;
    var gsap = window.gsap;
    var ScrollTrigger = window.ScrollTrigger;

    /* ---------- Portada: rotación de fotos ---------- */
    (function () {
        var fotos = document.querySelectorAll(".ini-hero-fotos img");
        var pasos = document.getElementById("heroPasos");
        if (fotos.length < 2 || !pasos) return;
        var botones = pasos.querySelectorAll("button");
        var DURACION = 6000;
        var actual = 0, temporizador = null;
        pasos.style.setProperty("--paso", DURACION / 1000 + "s");

        function ir(i) {
            fotos[actual].classList.remove("is-activa");
            botones[actual].removeAttribute("aria-current");
            actual = (i + fotos.length) % fotos.length;
            fotos[actual].classList.add("is-activa");
            Array.prototype.forEach.call(botones, function (b, j) { b.classList.toggle("es-visto", j < actual); });
            // Reiniciar la animación de la barra del paso actual.
            var barra = botones[actual].querySelector("span");
            barra.style.display = "none"; void barra.offsetWidth; barra.style.display = "";
            botones[actual].setAttribute("aria-current", "true");
        }
        function programar() {
            clearTimeout(temporizador);
            if (reducir) return;
            temporizador = setTimeout(function () { ir(actual + 1); programar(); }, DURACION);
        }
        Array.prototype.forEach.call(botones, function (b, i) {
            b.addEventListener("click", function () { ir(i); programar(); });
        });
        var visible = true;
        function revisar() {
            var correr = visible && !document.hidden;
            pasos.classList.toggle("is-pausa", !correr);
            if (correr) programar(); else clearTimeout(temporizador);
        }
        document.addEventListener("visibilitychange", revisar);
        if ("IntersectionObserver" in window) {
            new IntersectionObserver(function (e) { visible = e[0].isIntersecting; revisar(); }).observe(pasos.parentElement);
        }
        programar();
    })();

    /* ---------- Novedades (el formulario solo confirma) ---------- */
    (function () {
        var form = document.getElementById("newsletterForm");
        var nota = document.getElementById("newsletterNote");
        if (!form || !nota) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var input = document.getElementById("newsletterEmail");
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim())) {
                nota.textContent = "Ingresa un correo electrónico válido.";
                nota.classList.add("is-error");
                input.focus();
                return;
            }
            nota.classList.remove("is-error");
            nota.textContent = "¡Gracias! Te escribiremos con las próximas novedades académicas.";
            form.reset();
        });
    })();

    /* ---------- Nuevas carreras ---------- */
    var seccionCarreras = document.getElementById("nuevas-carreras");
    var ventana = document.getElementById("carrerasVentana");
    var pista = document.getElementById("carrerasPista");
    var avance = document.getElementById("carrerasAvance");
    var antes = document.getElementById("carrerasAntes");
    var despues = document.getElementById("carrerasDespues");

    function pintarAvance(p) {
        if (!avance || !pista) return;
        var visible = Math.min(1, ventana.clientWidth / pista.scrollWidth);
        avance.style.transform = "scaleX(" + (visible + (1 - visible) * p).toFixed(3) + ")";
    }

    // Modo deslizable (celular, movimiento reducido o sin GSAP).
    if (ventana && pista) {
        var pasoTarjeta = function () {
            var t = pista.querySelector(".carrera");
            return t ? t.getBoundingClientRect().width + 20 : 300;
        };
        var alDeslizar = function () {
            var max = ventana.scrollWidth - ventana.clientWidth;
            var p = max > 0 ? ventana.scrollLeft / max : 0;
            pintarAvance(p);
            if (antes) antes.disabled = ventana.scrollLeft <= 2;
            if (despues) despues.disabled = ventana.scrollLeft >= max - 2;
        };
        ventana.addEventListener("scroll", alDeslizar, { passive: true });
        if (antes) antes.addEventListener("click", function () { ventana.scrollBy({ left: -pasoTarjeta(), behavior: reducir ? "auto" : "smooth" }); });
        if (despues) despues.addEventListener("click", function () { ventana.scrollBy({ left: pasoTarjeta(), behavior: reducir ? "auto" : "smooth" }); });
        ventana.addEventListener("keydown", function (e) {
            if (e.key === "ArrowRight") { e.preventDefault(); ventana.scrollBy({ left: pasoTarjeta() }); }
            if (e.key === "ArrowLeft") { e.preventDefault(); ventana.scrollBy({ left: -pasoTarjeta() }); }
        });
        window.addEventListener("resize", alDeslizar);
        alDeslizar();
    }

    /* ---------- Sedes: mapa y tarjetas ---------- */
    var sedeActiva = -1;
    var activarSede = function () {};
    (function () {
        var mapaEl = document.getElementById("sedesMapa");
        var tarjetas = document.querySelectorAll(".sede");
        if (!mapaEl || !tarjetas.length) return;
        var sedes = JSON.parse(mapaEl.getAttribute("data-sedes") || "[]");
        var marcadores = [];
        var mapa = null;
        var vuelo = null;

        function armarMapa(L) {
            if (mapa || !sedes.length) return;
            mapa = L.map(mapaEl, { zoomControl: false, scrollWheelZoom: false, attributionControl: false, dragging: !L.Browser.mobile, tap: false });
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, subdomains: "abc" }).addTo(mapa);
            L.control.zoom({ position: "bottomright" }).addTo(mapa);
            sedes.forEach(function (s, i) {
                var icono = L.divIcon({ className: "sede-marcador", html: "<span></span>", iconSize: [22, 22], iconAnchor: [11, 11] });
                var m = L.marker([s.lat, s.lng], { icon: icono, keyboard: false }).addTo(mapa);
                m.bindTooltip(s.nombre, { direction: "top", offset: [0, -12] });
                m.on("click", function () { activarSede(i, true); });
                marcadores.push(m);
            });
            mapa.fitBounds(sedes.map(function (s) { return [s.lat, s.lng]; }), { padding: [48, 48] });
            if (sedeActiva >= 0) { var i = sedeActiva; sedeActiva = -1; activarSede(i, true); }
        }
        if (ESAT.cargarMapa) ESAT.alAcercarse(mapaEl, function () { ESAT.cargarMapa().then(armarMapa).catch(function () {}); });

        activarSede = function (i, volar) {
            if (i === sedeActiva) return;
            sedeActiva = i;
            Array.prototype.forEach.call(tarjetas, function (t, j) { t.classList.toggle("is-activa", j === i); });
            marcadores.forEach(function (m, j) {
                var el = m.getElement();
                if (el) el.classList.toggle("is-activa", j === i);
                if (j === i) m.openTooltip(); else m.closeTooltip();
            });
            // Si el scroll pasa rápido por varias sedes, solo vuela a la última.
            clearTimeout(vuelo);
            if (mapa && volar !== false && sedes[i]) {
                vuelo = setTimeout(function () {
                    mapa.flyTo([sedes[i].lat, sedes[i].lng], 12, { duration: reducir ? 0 : 1 });
                }, 180);
            }
        };

        Array.prototype.forEach.call(tarjetas, function (t, i) {
            t.addEventListener("click", function (e) {
                if (e.target.closest("a")) return;
                activarSede(i, true);
            });
        });

        // El scroll mueve el mapa solo donde el mapa queda fijo al lado
        // (pantallas anchas); en celular el mapa está arriba y se usa el toque.
        if (animar) {
            gsap.matchMedia().add("(min-width: 901px)", function () {
                Array.prototype.forEach.call(tarjetas, function (t, i) {
                    ScrollTrigger.create({
                        trigger: t, start: "top 62%", end: "bottom 62%",
                        onToggle: function (self) { if (self.isActive) activarSede(i, true); }
                    });
                });
            });
        }
    })();

    if (!animar) return;

    /* ---------- Lo que solo existe con movimiento ---------- */

    // Portada: al bajar, las fotos se alejan y el texto se va apagando. En
    // celular la foto va arriba en el flujo, así que ahí no se mueve.
    gsap.matchMedia().add("(min-width: 601px)", function () {
        gsap.to(".ini-hero-fotos", {
            yPercent: 6.5, scale: 1.04, ease: "none",
            scrollTrigger: { trigger: ".ini-hero-marco", start: "top top", end: "bottom top", scrub: true }
        });
        gsap.to(".ini-hero-texto", {
            yPercent: -12, opacity: 0.25, ease: "none",
            scrollTrigger: { trigger: ".ini-hero-marco", start: "40% top", end: "bottom top", scrub: true }
        });
    });

    // Por qué ESAT: la línea avanza y enciende cada beneficio.
    var espina = document.getElementById("porqueEspina");
    var lista = document.getElementById("porqueLista");
    if (espina && lista) {
        gsap.to(espina, {
            scaleY: 1, ease: "none",
            scrollTrigger: { trigger: lista, start: "top 70%", end: "bottom 55%", scrub: 0.4 }
        });
        lista.querySelectorAll("li").forEach(function (li) {
            ScrollTrigger.create({ trigger: li, start: "top 68%", toggleClass: { targets: li, className: "is-activo" } });
        });
    }

    // Nuevas carreras: pista horizontal fijada solo en pantallas anchas y con
    // alto suficiente; en ventanas bajas queda deslizable, sin fijar.
    if (seccionCarreras && pista) {
        var mm = gsap.matchMedia();
        mm.add("(min-width: 1100px) and (min-height: 620px)", function () {
            seccionCarreras.classList.add("es-fija");
            ventana.scrollLeft = 0;
            var distancia = function () { return Math.max(0, pista.scrollWidth - ventana.clientWidth + parseFloat(getComputedStyle(ventana).paddingLeft)); };
            var tween = gsap.to(pista, {
                x: function () { return -distancia(); },
                ease: "none",
                scrollTrigger: {
                    trigger: seccionCarreras,
                    pin: "#carrerasFijo",
                    start: "top top",
                    end: function () { return "+=" + distancia(); },
                    scrub: 0.6,
                    invalidateOnRefresh: true,
                    onUpdate: function (self) { pintarAvance(self.progress); }
                }
            });
            // matchMedia deshace la animación y el pin al salir del rango;
            // aquí solo se devuelve la sección a su modo deslizable.
            return function () {
                seccionCarreras.classList.remove("es-fija");
                gsap.set(pista, { clearProps: "transform" });
            };
        });
    }

    // Convenios: la cinta corre sola y acelera con la velocidad del scroll.
    var cinta = document.getElementById("cintaMunicipios");
    if (cinta) {
        var pistas = cinta.querySelectorAll(".cinta-pista");
        cinta.classList.add("es-js");
        var x = 0, extra = 0, pausa = false, enPantalla = false;
        var ancho = pistas[0].offsetWidth;
        var mover = pistas.length > 1 ? [gsap.quickSetter(pistas[0], "x", "px"), gsap.quickSetter(pistas[1], "x", "px")] : [];
        window.addEventListener("resize", function () { ancho = pistas[0].offsetWidth; });
        cinta.addEventListener("mouseenter", function () { pausa = true; });
        cinta.addEventListener("mouseleave", function () { pausa = false; });
        new IntersectionObserver(function (e) { enPantalla = e[0].isIntersecting; }).observe(cinta);
        if (ESAT.lenis) ESAT.lenis.on("scroll", function (e) { if (enPantalla) extra = Math.min(Math.abs(e.velocity) * 0.6, 14); });
        gsap.ticker.add(function (t, dt) {
            if (pausa || !enPantalla || !ancho) return;
            x -= (0.045 + extra * 0.02) * dt;
            extra *= 0.92;
            if (x <= -ancho) x += ancho;
            mover.forEach(function (fijar) { fijar(x); });
        });
    }

    // La pista fijada agrega altura a la página: los disparadores creados
    // antes (entradas de motor.js, sedes) se reordenan y se recalculan.
    ScrollTrigger.sort();
    ScrollTrigger.refresh();
    window.addEventListener("load", function () { ScrollTrigger.refresh(); });
})();
