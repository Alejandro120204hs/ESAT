/* =========================================================
   ESAT — sitio público · motor de scroll y navegación
   Lenis da el scroll suave y GSAP ScrollTrigger las animaciones
   ligadas al scroll. Si el visitante pidió reducir el movimiento, o
   si las librerías no cargan, todo queda visible y quieto: la
   cabecera, los menús y los enlaces funcionan igual.

   Lo que pueden usar las páginas (atributos en el HTML):
   - data-revelar="subir|escalonar|mascara|lado" (+ data-retraso="0.2"):
     entrada al aparecer. "escalonar" anima los hijos uno tras otro.
   - data-parallax="0.12": la capa se desplaza con el scroll dentro de
     su marco (el padre, o el ancestro con data-parallax-marco).
   - data-contar="27" (+ data-prefijo, data-sufijo): el número sube
     al aparecer. El texto del HTML ya trae el valor final; para
     lectores de pantalla, poner el valor en un .sr-only aparte y
     marcar el número animado con aria-hidden.
   Las páginas pueden leer window.ESAT (lenis, reducir, animar).
   ========================================================= */
(function () {
    "use strict";

    var reducir = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var animar = !reducir && !!(window.gsap && window.ScrollTrigger);
    var raiz = document.documentElement;
    var lenis = null;

    /* ---------- Scroll suave ---------- */
    if (!reducir && window.Lenis) {
        lenis = new window.Lenis({ lerp: 0.11, smoothWheel: true });
        if (animar) {
            lenis.on("scroll", window.ScrollTrigger.update);
            window.gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
            window.gsap.ticker.lagSmoothing(0);
        } else {
            var cuadro = function (t) { lenis.raf(t); requestAnimationFrame(cuadro); };
            requestAnimationFrame(cuadro);
        }
    }
    /* Leaflet (mapas) se descarga solo cuando un mapa está por aparecer:
       la mayoría de visitas nunca bajan hasta el footer. */
    var promesaMapa = null;
    function cargarMapa() {
        if (window.L) return Promise.resolve(window.L);
        if (promesaMapa) return promesaMapa;
        promesaMapa = new Promise(function (ok, falla) {
            var css = document.createElement("link");
            css.rel = "stylesheet";
            css.href = "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css";
            // Integridad (SRI): si el CDN sirve otro archivo, el navegador no lo usa (HALLAZGOS B31)
            css.integrity = "sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H";
            css.crossOrigin = "anonymous";
            document.head.appendChild(css);
            var js = document.createElement("script");
            js.src = "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js";
            js.integrity = "sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH";
            js.crossOrigin = "anonymous";
            js.onload = function () { ok(window.L); };
            js.onerror = falla;
            document.head.appendChild(js);
        });
        return promesaMapa;
    }
    // Ejecuta fn cuando el elemento está a menos de una pantalla de distancia.
    function alAcercarse(el, fn) {
        if (!el) return;
        if (!("IntersectionObserver" in window)) { fn(); return; }
        var io = new IntersectionObserver(function (e) {
            if (!e[0].isIntersecting) return;
            io.disconnect();
            fn();
        }, { rootMargin: "100% 0px" });
        io.observe(el);
    }

    window.ESAT = { lenis: lenis, reducir: reducir, animar: animar, cargarMapa: cargarMapa, alAcercarse: alAcercarse };

    // Si la página tiene un mapa, Leaflet se descarga en un momento libre
    // después de cargar, para no compilarlo en medio del scroll.
    window.addEventListener("load", function () {
        if (!document.querySelector("#sedesMapa, #footerSedesMap")) return;
        var libre = window.requestIdleCallback || function (fn) { return setTimeout(fn, 1200); };
        libre(function () { cargarMapa().catch(function () {}); }, { timeout: 4000 });
    });

    function detenerScroll() { if (lenis) lenis.stop(); raiz.style.overflow = "hidden"; }
    function reanudarScroll() { raiz.style.overflow = ""; if (lenis) lenis.start(); }

    /* ---------- Cabecera: se compacta y marca el avance ---------- */
    var cabecera = document.getElementById("cabecera");
    var progreso = document.getElementById("cabeceraProgreso");
    var pendiente = false;

    function pintarCabecera() {
        pendiente = false;
        var y = window.scrollY;
        cabecera.classList.toggle("is-compacta", y > 30);
        if (progreso) {
            var max = raiz.scrollHeight - window.innerHeight;
            progreso.style.transform = "scaleX(" + (max > 0 ? Math.min(y / max, 1) : 0).toFixed(4) + ")";
        }
    }
    if (cabecera) {
        window.addEventListener("scroll", function () {
            if (!pendiente) { pendiente = true; requestAnimationFrame(pintarCabecera); }
        }, { passive: true });
        pintarCabecera();
    }

    function alturaCabecera() {
        var v = parseFloat(getComputedStyle(raiz).getPropertyValue("--cab-alto-compacta"));
        return isNaN(v) ? 64 : v;
    }

    /* ---------- Megamenú de programas (solo clic, nunca hover) ---------- */
    var megaBoton = document.getElementById("megaTrigger");
    var mega = document.getElementById("megamenu");

    function cerrarMega(devolverFoco) {
        if (!mega || !mega.classList.contains("is-open")) return;
        mega.classList.remove("is-open");
        megaBoton.setAttribute("aria-expanded", "false");
        if (devolverFoco) megaBoton.focus();
    }
    function abrirMega() {
        cerrarDesplegables();
        mega.classList.add("is-open");
        megaBoton.setAttribute("aria-expanded", "true");
    }
    if (megaBoton && mega) {
        megaBoton.addEventListener("click", function (e) {
            e.stopPropagation();
            if (mega.classList.contains("is-open")) cerrarMega(); else abrirMega();
        });
        mega.addEventListener("click", function (e) { if (e.target.closest("a")) cerrarMega(); });
        document.addEventListener("click", function (e) {
            if (!mega.contains(e.target) && !megaBoton.contains(e.target)) cerrarMega();
        });
    }

    /* ---------- Desplegables simples (Educación Continuada) ---------- */
    var desplegables = Array.prototype.slice.call(document.querySelectorAll(".has-desplegable"));

    function cerrarDesplegables(devolverFoco) {
        desplegables.forEach(function (item) {
            if (!item.classList.contains("is-open")) return;
            var boton = item.querySelector("button");
            item.classList.remove("is-open");
            boton.setAttribute("aria-expanded", "false");
            if (devolverFoco) boton.focus();
        });
    }
    desplegables.forEach(function (item) {
        var boton = item.querySelector("button");
        boton.addEventListener("click", function (e) {
            e.stopPropagation();
            var abierto = item.classList.contains("is-open");
            cerrarDesplegables();
            cerrarMega();
            if (!abierto) { item.classList.add("is-open"); boton.setAttribute("aria-expanded", "true"); }
        });
        document.addEventListener("click", function (e) {
            if (!item.contains(e.target) && item.classList.contains("is-open")) {
                item.classList.remove("is-open");
                boton.setAttribute("aria-expanded", "false");
            }
        });
    });

    /* ---------- Menú móvil (hoja lateral con foco atrapado) ---------- */
    var menuAbrir = document.getElementById("menuMovilAbrir");
    var menu = document.getElementById("menuMovil");
    var menuCerrar = document.getElementById("menuMovilCerrar");
    var menuFondo = document.getElementById("menuMovilFondo");

    function menuAbierto() { return menu && menu.classList.contains("is-open"); }
    function abrirMenu() {
        menu.classList.add("is-open");
        menuFondo.classList.add("is-open");
        menuAbrir.setAttribute("aria-expanded", "true");
        detenerScroll();
        setTimeout(function () { menuCerrar.focus(); }, 60);
    }
    function cerrarMenu(devolverFoco) {
        if (!menuAbierto()) return;
        menu.classList.remove("is-open");
        menuFondo.classList.remove("is-open");
        menuAbrir.setAttribute("aria-expanded", "false");
        reanudarScroll();
        if (devolverFoco) menuAbrir.focus();
    }
    if (menu && menuAbrir) {
        menuAbrir.addEventListener("click", abrirMenu);
        menuCerrar.addEventListener("click", function () { cerrarMenu(true); });
        menuFondo.addEventListener("click", function () { cerrarMenu(true); });
        menu.addEventListener("click", function (e) { if (e.target.closest("a")) cerrarMenu(); });
        menu.addEventListener("keydown", function (e) {
            if (e.key !== "Tab") return;
            var focos = menu.querySelectorAll("a[href], button:not([disabled])");
            var visibles = Array.prototype.filter.call(focos, function (el) { return el.offsetParent !== null; });
            if (!visibles.length) return;
            var primero = visibles[0], ultimo = visibles[visibles.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        });
        menu.querySelectorAll(".menu-movil-acordeon > button").forEach(function (boton) {
            boton.addEventListener("click", function () {
                var abierto = boton.parentElement.classList.toggle("is-open");
                boton.setAttribute("aria-expanded", abierto ? "true" : "false");
            });
        });
        window.matchMedia("(min-width: 1101px)").addEventListener("change", function (m) {
            if (m.matches) cerrarMenu();
        });
    }

    document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        if (menuAbierto()) { cerrarMenu(true); return; }
        cerrarMega(true);
        cerrarDesplegables(true);
    });

    /* ---------- Enlaces a secciones de la misma página ---------- */
    document.addEventListener("click", function (e) {
        var a = e.target.closest('a[href*="#"]');
        if (!a || a.target === "_blank") return;
        var url = new URL(a.href, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || !url.hash) return;
        var destino = document.getElementById(decodeURIComponent(url.hash.slice(1)));
        if (!destino) return;
        e.preventDefault();
        cerrarMega();
        cerrarMenu();
        var desfase = -(alturaCabecera() + 16);
        if (lenis) lenis.scrollTo(destino, { offset: desfase, duration: 1.25 });
        else window.scrollTo({ top: destino.getBoundingClientRect().top + window.scrollY + desfase, behavior: reducir ? "auto" : "smooth" });
        if (history.pushState) history.pushState(null, "", url.hash);
    });

    /* ---------- Animaciones ligadas al scroll ---------- */
    if (animar) {
        var gsap = window.gsap;
        var ScrollTrigger = window.ScrollTrigger;
        gsap.registerPlugin(ScrollTrigger);
        ScrollTrigger.config({ ignoreMobileResize: true });

        var entradas = {
            subir: function (el) { return { targets: el, vars: { y: 32, autoAlpha: 0, duration: 1, ease: "expo.out" } }; },
            escalonar: function (el) { return { targets: el.children, vars: { y: 28, autoAlpha: 0, duration: 0.9, ease: "expo.out", stagger: 0.08 } }; },
            mascara: function (el) { return { targets: el, vars: { clipPath: "inset(12% 8% 12% 8% round 32px)", scale: 1.05, duration: 1.4, ease: "expo.out" } }; },
            lado: function (el) { return { targets: el, vars: { x: -40, autoAlpha: 0, duration: 1, ease: "expo.out" } }; }
        };

        document.querySelectorAll("[data-revelar], [data-aos]").forEach(function (el) {
            var tipo = el.getAttribute("data-revelar") || "subir";
            var retraso = parseFloat(el.getAttribute("data-retraso"));
            if (isNaN(retraso)) retraso = (parseInt(el.getAttribute("data-aos-delay") || "0", 10) || 0) / 1000;
            var entrada = (entradas[tipo] || entradas.subir)(el);
            entrada.vars.delay = retraso;
            entrada.vars.scrollTrigger = { trigger: el, start: "top 88%", once: true };
            // Al terminar, el elemento vuelve a sus estilos de CSS (hover, transiciones).
            entrada.vars.clearProps = "transform,opacity,visibility,clipPath";
            gsap.from(entrada.targets, entrada.vars);
        });

        document.querySelectorAll("[data-parallax]").forEach(function (el) {
            var f = parseFloat(el.getAttribute("data-parallax")) || 0.12;
            var marco = el.closest("[data-parallax-marco]") || el.parentElement;
            gsap.fromTo(el, { yPercent: -f * 100 }, {
                yPercent: f * 100, ease: "none",
                scrollTrigger: { trigger: marco, start: "top bottom", end: "bottom top", scrub: true }
            });
        });

        var numero = new Intl.NumberFormat("es-CO");
        document.querySelectorAll("[data-contar]").forEach(function (el) {
            var fin = parseFloat(el.getAttribute("data-contar"));
            if (isNaN(fin)) return;
            var pre = el.getAttribute("data-prefijo") || "";
            var suf = el.getAttribute("data-sufijo") || "";
            var estado = { v: 0 };
            el.textContent = pre + "0" + suf;
            gsap.to(estado, {
                v: fin, duration: 1.8, ease: "expo.out",
                scrollTrigger: { trigger: el, start: "top 92%", once: true },
                onUpdate: function () { el.textContent = pre + numero.format(Math.round(estado.v)) + suf; }
            });
        });

        window.addEventListener("load", function () { ScrollTrigger.refresh(); });
    }

    /* ---------- Footer: mapa de las sedes ----------
       Mapa real (Leaflet + teselas de OpenStreetMap, sin clave) con las
       cuatro sedes. Al pasar sobre un punto se ve el nombre y la
       dirección; la misma lista está escrita al lado del mapa. */
    alAcercarse(document.getElementById("footerSedesMap"), function () {
        cargarMapa().then(mapaDelPie).catch(function () {});
    });
    function mapaDelPie() {
        var mapaEl = document.getElementById("footerSedesMap");
        if (!mapaEl || !window.L) return;
        var puntos = JSON.parse(mapaEl.getAttribute("data-points") || "[]");
        if (!puntos.length) return;

        var L = window.L;
        var mapa = L.map(mapaEl, { zoomControl: false, scrollWheelZoom: false, attributionControl: false, maxZoom: 18 });
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, subdomains: "abc" }).addTo(mapa);

        var icono = L.divIcon({
            className: "footer-sedes-marker",
            html: '<span class="footer-sedes-marker-dot"></span>',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });

        function esc(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
            });
        }

        var limites = [];
        var marcadores = puntos.map(function (p) {
            limites.push([p.lat, p.lng]);
            var m = L.marker([p.lat, p.lng], { icon: icono, keyboard: false }).addTo(mapa);
            m._sede = p;
            return m;
        });

        mapa.invalidateSize();
        mapa.fitBounds(limites, { padding: [28, 28] });

        // El tooltip abre hacia donde haya espacio: abajo si el punto está
        // en la mitad de arriba, y se corre hacia adentro cerca de los bordes.
        var tam = mapa.getSize();
        marcadores.forEach(function (m) {
            var pt = mapa.latLngToContainerPoint(m.getLatLng());
            var abajo = pt.y < tam.y * 0.45;
            var izq = 90 - pt.x, der = pt.x + 90 - tam.x;
            var corrimiento = izq > 0 ? izq : (der > 0 ? -der : 0);
            m.bindTooltip("<strong>" + esc(m._sede.name) + "</strong><span>" + esc(m._sede.address) + "</span>", {
                direction: abajo ? "bottom" : "top",
                offset: [corrimiento, abajo ? 6 : -6]
            });
        });

        mapa.dragging.disable();
        mapa.touchZoom.disable();
        mapa.doubleClickZoom.disable();
        mapa.boxZoom.disable();
        mapa.keyboard.disable();
    }
})();
