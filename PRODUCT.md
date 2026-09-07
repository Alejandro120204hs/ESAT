# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel Blade views (server-rendered). Hand-written CSS in `public/assets/css/` and hand-written JS in `public/assets/js/`, loaded directly (not through the Vite pipeline the default Laravel scaffold ships with, which sits unused under `resources/`). Two libraries are loaded via CDN on top of that hand-written base, added later at explicit user request: **Bootstrap 5** (`bootstrap.min.css` / `bootstrap.bundle.min.js` — used for the hero's auto-rotating photo carousel; the rest of the page still uses the site's own component CSS, not Bootstrap's) and **AOS** (Animate On Scroll, `aos.css` / `aos.js` — replaced an earlier hand-rolled IntersectionObserver reveal system; every scroll-reveal element carries `data-aos`). Because Bootstrap's Reboot resets (element-selector `h1-h6`, `p`, `ul/ol` margins) outrank a universal `*{margin/padding:0}` reset on specificity regardless of load order, `css_website.css` carries an explicit "Bootstrap Reboot hardening" block re-zeroing those — necessary context if either CDN dependency is ever swapped or upgraded.

## Users

Primary: prospective students in Colombia, concentrated in Cundinamarca and Caldas (Bogotá, Sasaima, Guaduas, La Dorada and surrounding municipalities — Villeta, Cachipay, Albán, La Vega, Supatá, Anolaima), looking for certified technical/vocational training (programas técnicos laborales) that gets them into the job market quickly. Includes working adults seeking a career change or mandatory occupational-health certification, not just school leavers.

Secondary: institutional and government evaluators (alcaldías, entidades like INPEC, Fiscalía, universities, clinics) assessing ESAT's legitimacy for partnership or referral.

## Product Purpose

ESAT (Escuela Nacional de Educación ESAT) is a licensed Colombian technical institute offering: técnico laboral programs across 7 schools, cursos obligatorios en salud, and educación continuada. The site's job is to let a prospective student find the right program and sede fast, understand ESAT is a real accredited institution, and convert to an inquiry/enrollment action (WhatsApp/phone contact, "ver más" on a convocatoria, or certificate lookup for existing students).

## Positioning

A regionally-rooted, officially licensed institution (Licencia de Funcionamiento 5850 issued 2019-09-23 by the Secretaría de Educación de Cundinamarca, regulated under Decreto 4904-2009 / Ministerio de Educación Nacional) with four physical sedes and documented institutional alliances (municipal governments, INPEC, Fiscalía/CTI, hospitals, veterinary clinics, universities) — distinct from a single-city or online-only training shop.

## Operating Context

- Multi-sede: Bogotá, Sasaima, Guaduas, La Dorada, each with a real street address.
- Modalities: presencial and virtual.
- Enrollment/contact happens via phone/WhatsApp, not an online checkout.
- Existing students look up certificates online ("ver mi certificado online").
- Programs are grouped into a large catalog: 7 schools containing dozens of named programs, plus separate tracks for cursos obligatorios en salud and educación continuada, plus seasonal convocatorias (open intakes) that rotate.
- Institutional convenios are a recurring credibility signal shown across alcaldías, government entities, and specialized-sector partners (clinics, farms, arts centers, universities).
- Confirmed two-phase plan: Phase 1 (current) is a single-page site — everything lives in one Blade view with in-page anchor navigation, and every program/certificate CTA routes to a WhatsApp deep-link since no backend page exists for any of them yet. Phase 2 (future, not started) is real subpages — e.g. a page per school or program, a real contact page — so visitors can actually navigate the site instead of everything funneling through WhatsApp.

## Capabilities and Constraints

- `resources/views/welcome.blade.php` is the rebuilt ESAT homepage (was Laravel's default scaffold markup at project start).
- Navigation has two dropdowns, both click-only (hover-to-open was tried and explicitly rejected — it opened just from the cursor passing over the trigger): "Programas Técnicos" is a full-width mega-panel (all 7 schools and their programs); "Educación Continuada" is a small single-column dropdown (7 categories: Salud Ocupacional, Áreas Sociales, Pedagogía, Administración y Gerencia, Edificios y Afines, Hotelería y Turismo, Salud). Both collapse to accordions in the mobile menu.
- Hero background is an auto-rotating Bootstrap carousel of the 3 real photos (`data-bs-pause="false"` so it keeps advancing even though the cursor sits over the hero constantly). No dark tint sits over the photos themselves — legibility comes from a scrim confined to the text band, not a flat overlay across the image. An earlier hero treatment (floating mouse-reactive 3D icon badges over a flat navy gradient) was built, then explicitly removed once real photography replaced the flat background — it read as clutter over real photos.
- Scroll-reveal animation per section, via AOS (see Stack).
- Real photography and brand assets now exist at `public/assets/img/`: `logo.png` (color wordmark, transparent bg, used in the header where the background is always at least translucent-light) and `logo-negativo.png` (white wordmark, used on the dark footer — no glow/shadow trick needed, it's the real asset for dark grounds); `favicon.png` (icon mark only); `imagenhero1/2/3.png` (hero carousel); `programas-tecnicos.png` (a workshop/soldering photo used as the "Programas Técnicos" card's background, with a left-anchored navy scrim + text-shadow for legibility — keep the scrim's dark zone matched to the actual text column width, or it either washes out the photo (too dark/wide) or leaves the text illegible (too narrow/light); this was tuned back and forth once already). Areas of the site without a specific matching photo stay CSS/SVG/iconography rather than forcing a mismatched or fabricated photo.
- All legal, contact, and address data given below is real and must be reproduced exactly — never invented, altered, or approximated.

## Brand Commitments

- Name: "ESAT" / formally "Escuela Nacional de Educación ESAT".
- Palette derived from the ESAT logo: navy `#10284A` (primary), orange `#D97B2E` (accent/CTA), green `#40763A` (secondary accent), light background `#FAFAF8`, white `#FFFFFF`, base text `#1A1A1A`, muted text `#6B7280`.
- Tone: serious, professional educational institution, but modern and dynamic — explicitly not a generic template look.

## Evidence on Hand

**Legal/registration (footer, exact):** Escuela Nacional de Educación ESAT · Lic. Funcionamiento 5850 – 23 sept 2019 · Secretaría de Educación de Cundinamarca · NIT 900820764-1 · Decreto 4904-2009 · Ministerio de Educación Nacional.

**Contact:** Cel/WhatsApp 315 295 75 88 – 312 377 31 44. Social: Facebook, Twitter, Instagram, YouTube (handles/URLs not supplied — do not invent links).

**Sedes:**
- Bogotá — Cra 52 #71-18
- Sasaima — Urbanización San José, Cra 2 #8-84, Sasaima, Cundinamarca
- Guaduas — Cra 2 No. 1-83, Calle de la Pola, Cundinamarca
- La Dorada — Calle 9 No. 7-55, Barrio Magdalena, La Dorada, Caldas

**7 Schools and programs:**
1. Escuela de Salud — Servicios Geriátricos; Seguridad Ocupacional y Laboral; Auxiliar de Psiquiatría; Camillero Hospitalario.
2. Escuela de Cocina y Turismo — Servicios Hoteleros y Turísticos; Cocina Nacional e Internacional; Sommelier; Inspector de Calidad de Alimentos y Bebidas.
3. Escuela Administrativa — Auditoría y Facturación de Cuentas Médicas; Agente de Tránsito; Auxiliar Contable y Administrativo.
4. Escuela Deporte y Cultura — Salvamento Acuático; Gestión y Promoción Artística; Servicios de Recreación y Deportes.
5. Escuela Ciencias — Producción Agropecuaria y Zootecnia; Asistente de Veterinaria y Zootecnia; Obras Civiles y Arquitectura; Criminalística, Investigación Judicial y Ciencias Forenses; Energías Renovables; Asistente Lab. Clínico Veterinario; Electricista; Electromecánica; Mecánica y Electrónica de Motos.
6. Escuela de Educación e Idiomas — Inglés A1-A2-B1-B2; Francés A1-A2-B1-B2; Asistente de Preescolar.
7. Escuela de Belleza — Barbería.

Plus: Cursos Obligatorios en Salud, Educación Continuada, Contacto (top-level nav items alongside the schools).

**Educación Continuada categories (nav dropdown):** Salud Ocupacional; Áreas Sociales; Pedagogía; Administración y Gerencia; Edificios y Afines; Hotelería y Turismo; Salud.

**6 new dual-titulación programs (with short descriptions):**
1. Inteligencia Artificial (IA) — Desarrolla soluciones inteligentes para el mundo del mañana.
2. Marketing y Redes Sociales — Conecta marcas, crea contenido y genera impacto digital.
3. Podología y Estética de Pies — Cuida la salud y belleza desde la base.
4. Enfermería Veterinaria — Brinda atención y bienestar a nuestros mejores amigos.
5. Operación y Mantenimiento de Maquinaria Pesada — Maneja tecnología de alto impacto y construye el futuro.
6. Servicio Social y Comunitario — Sé agente de cambio y transforma tu comunidad.

**Convenios institucionales:** Alcaldías de Villeta, Cachipay, Albán, La Vega, Supatá, Anolaima, Guaduas, La Dorada. Entidades: Fiscalía y CTI de Villeta; INPEC (Honda, Norcasia, Puerto Triunfo); FTI Bogotá; Todo en Salud a la Mano S.A.S. Plus alliances with veterinary clinics, specialized farms, obras civiles, arts centers, and universities (no specific names given for these).

**Próximas convocatorias (examples given):** Curso de Actualización en Atención al Paciente; Curso Obligatorio Salud Ocupacional; Programa Técnico Laboral en el Área Administrativa; Educación Continuada en Normativa y Procedimientos.

**Photography and brand assets:** real files live at `public/assets/img/` — `imagenhero1.png`, `imagenhero2.png`, `imagenhero3.png` (hero carousel: students in uniform, a graduation photo), `programas-tecnicos.png` (workshop/soldering photo, ESAT logo visible on the uniform, used as the "Programas Técnicos" area card background), `logo.png` (real color wordmark lockup, transparent background), `logo-negativo.png` (real white wordmark for dark backgrounds), `favicon.png` (icon mark only, transparent background). The real logo — used as the actual header/footer brand mark now, not a placeholder — is a horizontal wordmark: "ESAT" in a serif face with a gold dot, "EDUCACIÓN, SALUD, ARTE Y TURISMO" tagline beneath a gold rule; there is also a separate abstract orange/green/navy swoosh icon mark (visible embedded on staff/student uniforms in the photos, and as the standalone favicon) that is a distinct asset from the wordmark, not a combined lockup.

**Additional real facts confirmed against the live site (esat.edu.co) but not yet used anywhere on this rebuild by the user's choice:** the institution's formal classification is "Educación para el Trabajo y Desarrollo Humano" (ETDH); ESAT offers a free (≤1 hour) conference/talk for companies' staff on request. Neither is currently on the site — the user chose not to add the conference offer for now, and the ETDH classification was not requested to be added either; don't add either without being asked. A "servicios médicos" list (Consulta Externa, Ginecobstetricia, Pediatría, Urgencias, UCI, etc.) surfaced during that same check but doesn't fit the rest of ESAT's content and was never confirmed as genuinely theirs — treat it as unverified and do not use it.

**Absent:** no testimonials, no pricing, no specific social-media URLs, no map/geo API key. Future work must not fabricate any of these.

## Product Principles

1. Legal, contact, and address facts are sacred — reproduce exactly, never invent or approximate.
2. Every page reads as employment-outcome-first: fast, practical, certified path into the job market.
3. Institutional credibility is shown through licensing and convenios, not just asserted in copy.
4. Content is organized consistently around the 7-school taxonomy across nav, program cards, and footer.
5. Regional, multi-sede identity stays visible as a first-class section, not buried in the footer.
6. No stock-photo realism where no real photography exists — visuals lean on CSS/SVG/iconography/abstract 3D true to the institution rather than fabricated people or facilities.

## Accessibility & Inclusion

No specific standard mandated by the user beyond explicit mobile-first responsiveness and mid-range-mobile animation performance. Standard semantic/keyboard-accessible practice applies as a baseline (no elevated requirement recorded).
