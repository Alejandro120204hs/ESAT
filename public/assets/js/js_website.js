(function () {
    "use strict";

    var prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ============================================================
       Header: solid state on scroll
       ============================================================ */
    var header = document.getElementById("site-header");
    function updateHeaderState() {
        if (!header) return;
        if (window.scrollY > 12) header.classList.add("is-scrolled");
        else header.classList.remove("is-scrolled");
    }
    updateHeaderState();
    window.addEventListener("scroll", updateHeaderState, { passive: true });

    /* ============================================================
       Mega menu (desktop): click to open/close, no hover — a hover
       trigger meant the menu could pop open just from the cursor
       passing over it on the way to something else.
       ============================================================ */
    var megaTrigger = document.getElementById("megaTrigger");
    var megaItem = megaTrigger ? megaTrigger.closest(".has-mega") : null;
    // The dropdown panel is a sibling of the trigger's <li> under
    // <header>, not a descendant of it, so outside-click and link
    // handling both have to watch the panel explicitly too.
    var megaPanel = document.getElementById("mega-programas");

    function openMega() {
        if (!megaItem) return;
        megaItem.classList.add("is-open");
        if (header) header.classList.add("mega-open");
        megaTrigger.setAttribute("aria-expanded", "true");
    }
    function closeMega() {
        if (!megaItem) return;
        megaItem.classList.remove("is-open");
        if (header) header.classList.remove("mega-open");
        megaTrigger.setAttribute("aria-expanded", "false");
    }

    if (megaItem && megaTrigger) {
        megaTrigger.addEventListener("click", function (e) {
            e.stopPropagation();
            if (megaItem.classList.contains("is-open")) closeMega();
            else openMega();
        });
        if (megaPanel) {
            megaPanel.addEventListener("click", function (e) {
                if (e.target.closest("a")) closeMega();
            });
        }
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") closeMega();
        });
        document.addEventListener("click", function (e) {
            var inTrigger = megaItem.contains(e.target);
            var inPanel = megaPanel && megaPanel.contains(e.target);
            if (!inTrigger && !inPanel) closeMega();
        });
    }

    /* ============================================================
       Simple dropdowns (desktop): small single-column menus that
       hang below their own nav item, e.g. "Educación Continuada".
       Same click-only pattern as the mega menu — no hover trigger.
       ============================================================ */
    document.querySelectorAll(".has-dropdown").forEach(function (item) {
        var trigger = item.querySelector(".dropdown-trigger");
        if (!trigger) return;

        function openDropdown() {
            item.classList.add("is-open");
            trigger.setAttribute("aria-expanded", "true");
        }
        function closeDropdown() {
            item.classList.remove("is-open");
            trigger.setAttribute("aria-expanded", "false");
        }

        trigger.addEventListener("click", function (e) {
            e.stopPropagation();
            if (item.classList.contains("is-open")) closeDropdown();
            else openDropdown();
        });
        item.querySelectorAll(".simple-dropdown a").forEach(function (a) {
            a.addEventListener("click", closeDropdown);
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") closeDropdown();
        });
        document.addEventListener("click", function (e) {
            if (!item.contains(e.target)) closeDropdown();
        });
    });

    /* ============================================================
       Nuevas carreras: custom carousel — 3 cards visible on desktop,
       2 on tablet, 1 on mobile, advancing exactly one card per click.
       Bootstrap's carousel component only supports one full-width
       slide at a time, so this drives the same visual language
       (rounded arrow controls, dot indicators) with a small
       purpose-built track instead.
       ============================================================ */
    (function () {
        var carousel = document.getElementById("careersCarousel");
        if (!carousel) return;

        var track = carousel.querySelector(".careers-carousel-track");
        var realCards = Array.prototype.slice.call(track.children);
        var prevBtn = carousel.querySelector(".careers-carousel-prev");
        var nextBtn = carousel.querySelector(".careers-carousel-next");
        var dotsWrap = carousel.querySelector(".careers-carousel-dots");

        // Seamless loop: clone the last N cards in front and the first
        // N cards behind (N = the largest items-per-view this carousel
        // ever shows), so "next" past the last page and "prev" before
        // the first keep sliding the same direction into a clone that
        // looks identical to the real page — instead of the track
        // jumping straight back, which reads as reversing direction.
        // Once that one-card overshoot's transition finishes, the
        // track snaps (transition disabled for one frame) to the real
        // equivalent position; invisible since the clone and the real
        // page are pixel-identical.
        var CLONE_COUNT = 3;

        function cloneSet(source) {
            return source.map(function (el) {
                var clone = el.cloneNode(true);
                clone.setAttribute("aria-hidden", "true");
                Array.prototype.forEach.call(clone.querySelectorAll("a, button"), function (el) {
                    el.tabIndex = -1;
                });
                return clone;
            });
        }

        var headClones = cloneSet(realCards.slice(-CLONE_COUNT));
        var tailClones = cloneSet(realCards.slice(0, CLONE_COUNT));
        track.innerHTML = "";
        headClones.concat(realCards, tailClones).forEach(function (el) { track.appendChild(el); });

        var offset = CLONE_COUNT; // DOM index of the real first card
        var pos = offset; // current DOM-space track position
        var resetting = false;

        function itemsPerView() {
            var w = window.innerWidth;
            if (w >= 981) return 3;
            if (w >= 640) return 2;
            return 1;
        }

        // A single-card-step slider over N cards has N valid stopping
        // positions, not N-itemsPerView — every card gets a turn as
        // the leftmost visible one (with wraparound). Undercounting
        // this is what made the wrap-around reset land on content
        // that didn't match what the overshoot step had just shown.
        function maxLogicalIndex() {
            return Math.max(0, realCards.length - 1);
        }

        function buildDots() {
            dotsWrap.innerHTML = "";
            var pages = maxLogicalIndex() + 1;
            for (var i = 0; i < pages; i++) {
                var dot = document.createElement("button");
                dot.type = "button";
                dot.setAttribute("aria-label", "Ir a la posición " + (i + 1));
                dot.addEventListener("click", (function (targetIndex) {
                    return function () { goToPage(targetIndex); };
                })(i));
                dotsWrap.appendChild(dot);
            }
        }

        function paint(animated) {
            track.style.transition = animated ? "" : "none";
            var cardWidth = realCards[0].getBoundingClientRect().width;
            var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || "20") || 20;
            track.style.transform = "translateX(-" + (cardWidth + gap) * pos + "px)";
            if (!animated) void track.offsetHeight; // force reflow so the next transition re-enables cleanly

            var pageCount = maxLogicalIndex() + 1;
            var activePage = ((pos - offset) % pageCount + pageCount) % pageCount;
            Array.prototype.forEach.call(dotsWrap.children, function (dot, i) {
                dot.classList.toggle("is-active", i === activePage);
            });
        }

        function settleAfterTransition(realPos) {
            resetting = true;
            track.addEventListener("transitionend", function handler(e) {
                if (e.target !== track) return;
                track.removeEventListener("transitionend", handler);
                pos = realPos;
                paint(false);
                resetting = false;
            });
        }

        function next() {
            if (resetting) return;
            pos++;
            paint(true);
            if (pos === offset + maxLogicalIndex() + 1) settleAfterTransition(offset);
        }
        function prev() {
            if (resetting) return;
            pos--;
            paint(true);
            if (pos === offset - 1) settleAfterTransition(offset + maxLogicalIndex());
        }
        function goToPage(i) {
            if (resetting) return;
            pos = offset + Math.min(Math.max(i, 0), maxLogicalIndex());
            paint(true);
        }

        prevBtn.addEventListener("click", prev);
        nextBtn.addEventListener("click", next);

        buildDots();
        paint(false);

        var resizeTimer = null;
        window.addEventListener("resize", function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                resetting = false;
                pos = offset + Math.min(pos - offset, maxLogicalIndex());
                buildDots();
                paint(false);
            }, 150);
        });
    })();

    /* ============================================================
       Mobile nav
       ============================================================ */
    var navToggle = document.getElementById("navToggle");
    var mobileNav = document.getElementById("mobileNav");
    var mobileNavClose = document.getElementById("mobileNavClose");
    var mobileNavBackdrop = document.getElementById("mobileNavBackdrop");

    function openMobileNav() {
        mobileNav.classList.add("is-open");
        mobileNavBackdrop.classList.add("is-open");
        navToggle.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden";
    }
    function closeMobileNav() {
        mobileNav.classList.remove("is-open");
        mobileNavBackdrop.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    }
    if (navToggle) navToggle.addEventListener("click", openMobileNav);
    if (mobileNavClose) mobileNavClose.addEventListener("click", closeMobileNav);
    if (mobileNavBackdrop) mobileNavBackdrop.addEventListener("click", closeMobileNav);

    document.querySelectorAll(".mobile-nav a").forEach(function (link) {
        link.addEventListener("click", closeMobileNav);
    });

    document.querySelectorAll(".mobile-accordion-trigger").forEach(function (accordionTrigger) {
        accordionTrigger.addEventListener("click", function () {
            var wrap = accordionTrigger.closest(".mobile-accordion");
            var isOpen = wrap.classList.toggle("is-open");
            accordionTrigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });
    });

    /* ============================================================
       Scroll cue: jump to next section
       ============================================================ */
    var scrollCue = document.getElementById("scrollCue");
    if (scrollCue) {
        scrollCue.addEventListener("click", function () {
            var next = document.getElementById("por-que-esat");
            if (next) next.scrollIntoView({ behavior: prefersReducedMotion ? "auto" : "smooth" });
        });
    }

    /* ============================================================
       Scroll reveal — AOS (Animate On Scroll), loaded via CDN.
       Respects prefers-reduced-motion by disabling entrance motion
       entirely rather than just speeding it up.
       ============================================================ */
    if (window.AOS) {
        window.AOS.init({
            duration: 700,
            easing: "ease-out-cubic",
            once: true,
            offset: 60,
            disable: prefersReducedMotion
        });
    }

    /* ============================================================
       Newsletter form (client-side confirmation only)
       ============================================================ */
    var newsletterForm = document.getElementById("newsletterForm");
    var newsletterNote = document.getElementById("newsletterNote");

    if (newsletterForm && newsletterNote) {
        newsletterForm.addEventListener("submit", function (e) {
            e.preventDefault();
            var emailInput = document.getElementById("newsletterEmail");
            var email = emailInput.value.trim();
            var isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

            if (!isValid) {
                newsletterNote.textContent = "Ingresa un correo electrónico válido.";
                newsletterNote.classList.add("is-error");
                return;
            }

            newsletterNote.classList.remove("is-error");
            newsletterNote.textContent = "¡Gracias! Te escribiremos con las próximas novedades académicas.";
            newsletterForm.reset();
        });
    }
})();
