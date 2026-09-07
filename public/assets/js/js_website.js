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
