# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel Blade views (server-rendered). Hand-written CSS in `public/assets/css/sitio/` (`base.css` shared by every page, `inicio.css` for the home, `subpaginas.css` for program / continuing-education / school pages) and hand-written JS in `public/assets/js/sitio/` (`motor.js` shared, plus `inicio.js` and `subpaginas.js`), loaded directly with a file-mtime version query (`$recurso`), not through the unused Vite pipeline. On top of that base, three CDN libraries pinned to exact versions: **GSAP 3.13 + ScrollTrigger** and **Lenis 1.3.4** (smooth scroll and scroll-linked motion, all disabled under `prefers-reduced-motion`), and **Leaflet 1.9.4** (campus maps). Bootstrap and AOS were removed in the November 2026 redesign. Site data lives in `config/sitio.php` (schools, programs, campuses, WhatsApp numbers, social URLs) and `config/contenido.php` (the text of every program and continuing-education page); one template per page type renders it.

## Users

Primary: prospective students in Colombia, concentrated in Cundinamarca and Caldas (Bogotá, Sasaima, Guaduas, La Dorada and surrounding municipalities — Villeta, Cachipay, Albán, La Vega, Supatá, Anolaima), looking for certified technical/vocational training (programas técnicos laborales) that gets them into the job market quickly. Includes working adults seeking a career change or mandatory occupational-health certification, not just school leavers.

Secondary: institutional and government evaluators (alcaldías, entidades like INPEC, Fiscalía, universities, clinics) assessing ESAT's legitimacy for partnership or referral.

## Product Purpose

ESAT (Escuela Nacional de Educación ESAT) is a licensed Colombian technical institute offering: técnico laboral programs across 8 schools, cursos obligatorios en salud, and educación continuada. The site's job is to let a prospective student find the right program and sede fast, understand ESAT is a real accredited institution, and convert to an inquiry/enrollment action (WhatsApp/phone contact, "ver más" on a convocatoria, or certificate lookup for existing students).

## Positioning

A regionally-rooted, officially licensed institution (Licencia de Funcionamiento 5850 issued 2019-09-23 by the Secretaría de Educación de Cundinamarca, regulated under Decreto 4904-2009 / Ministerio de Educación Nacional) with four physical sedes and documented institutional alliances (municipal governments, INPEC, Fiscalía/CTI, hospitals, veterinary clinics, universities) — distinct from a single-city or online-only training shop.

## Operating Context

- Multi-sede: Bogotá, Sasaima, Guaduas, La Dorada, each with a real street address.
- Modalities: presencial and virtual.
- Enrollment/contact happens via phone/WhatsApp, not an online checkout.
- Existing students look up certificates online ("ver mi certificado online").
- Programs are grouped into a large catalog: 8 schools containing 41 of named programs, plus separate tracks for cursos obligatorios en salud and educación continuada, plus seasonal convocatorias (open intakes) that rotate.
- Institutional convenios are a recurring credibility signal shown across alcaldías, government entities, and specialized-sector partners (clinics, farms, arts centers, universities).
- The single-page phase is over: real subpages exist for each of the 41 programs, the 7 continuing-education lines and the 8 schools (`/escuelas/{slug}`), with breadcrumbs, a sticky enrolment bar and a program-specific footer close. Enrolment and contact still happen through WhatsApp deep-links; there is no backend form.

## Capabilities and Constraints

- `resources/views/welcome.blade.php` is the rebuilt ESAT homepage (was Laravel's default scaffold markup at project start).
- Navigation has two dropdowns, both click-only (hover-to-open was tried and explicitly rejected — it opened just from the cursor passing over the trigger): "Programas Técnicos" is a full-width mega-panel with two panes (the 8 schools as tabs on the left, the chosen school's programs and photo on the right, so its height does not grow with the catalog); "Educación Continuada" is a small single-column dropdown (7 categories: Salud Ocupacional, Áreas Sociales, Pedagogía, Administración y Gerencia, Edificios y Afines, Hotelería y Turismo, Salud). Both collapse to accordions in the mobile menu.
- Home hero: the 3 real photos rotate (6 s each, with progress bars) inside a rounded frame, with a navy scrim confined to the text side and the stats card riding the frame's bottom edge. An earlier hero treatment (floating mouse-reactive 3D icon badges over a flat navy gradient) was built, then explicitly removed once real photography replaced the flat background — it read as clutter over real photos.
- Scroll motion is authored in `motor.js` through data attributes (`data-revelar`, `data-parallax`, `data-contar`) plus page-specific moments (pinned horizontal "Nuevas carreras" track, campus map that follows the cards, opening practice photo). Content is visible by default; motion only animates from that state.
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

**Sedes de franquicia** (list sent by ESAT on 2026-11-10, municipality names only — no addresses, phones or photos yet; never invent them): La Dorada, La Vega, El Vino, Supatá, San Francisco, El Rosal, Anolaima, Cachipay, Albán. Shown on the site as a separate strip under the four sedes (each name opens WhatsApp to ask where it is) and as a footer line, without map pins.

**8 Schools and programs** (catalog from ESAT's "Carreras técnicas" poster, 2026-11-16; the poster says every program has doble titulación):
1. Escuela de Salud Animal y Agropecuaria — Asistente de Medicina Veterinaria; Asistente de Veterinaria y Zootecnia; Producción Agropecuaria y Zootecnia; Asistente de Laboratorio Experto en Animales.
2. Escuela de Ingeniería y Tecnología — Electromecánica; Electricista; Mecánica de Motos; Mecánico Electrónico; Técnico en Robótica; Obras Civiles y Arquitectura; Operación y Mantenimiento de Maquinaria Pesada; Energías Alternativas y Renovables (Paneles Solares); Órtesis y Prótesis Médicas.
3. Escuela de Salud y Bienestar — Asistente de Preescolar; Auxiliar de Psiquiatría; Camillero Hospitalario; Servicio Social y Comunitario; Podología y Estética de Pies; Salvamento Acuático; Barbería; Salud Natural y Terapias Complementarias; Instructor de Natación.
4. Escuela de Artes y Cultura — Promoción y Gestión Artística; Titiritero; Compositor Coreográfico e Intérprete de Danzas.
5. Escuela de Gastronomía y Turismo — Chef de Alta Cocina; Sommelier; Gastronomía y Artes Culinarias; Servicios de Recreación y Deporte; Servicios Hoteleros y Turísticos; Líneas Aéreas, Marítimas y Terrestres.
6. Escuela Administrativa y Financiera — Auxiliar Contable y Administrativo; Auditoría y Facturación de Cuentas Médicas; Marketing y Redes Sociales; Asesor de Negocios Institucional PYME.
7. Escuela de Seguridad y Justicia — Agente de Tránsito; Criminalística, Investigación Judicial y Ciencias Forenses; Inspector de Control de Calidad de Alimentos y Bebidas; Seguridad y Salud en el Trabajo.
8. Escuela de Idiomas — Inglés A1-A2-B1-B2; Francés A1-A2-B1-B2.

The previous catalog (7 schools, 27 programs) is gone; its URLs redirect (301) to the current program or school (`sitio.redirecciones`). Servicios Geriátricos is no longer offered. The poster also shows a Marquetalia (Caldas) campus and per-campus phone numbers; they are not on the site until the user confirms them.

Plus: Cursos Obligatorios en Salud, Educación Continuada, Contacto (top-level nav items alongside the schools).

**Educación Continuada categories (nav dropdown):** Salud Ocupacional; Áreas Sociales; Pedagogía; Administración y Gerencia; Edificios y Afines; Hotelería y Turismo; Salud.

**"Nuevas carreras técnicas" strip (home):** Técnico en Robótica, Marketing y Redes Sociales, Podología y Estética de Pies, Asistente de Medicina Veterinaria, Operación y Mantenimiento de Maquinaria Pesada, Servicio Social y Comunitario; each card links to its program page. Inteligencia Artificial (IA) and Enfermería Veterinaria were dropped: they are not on the 2026-11-16 poster.

**Convenios institucionales:** Alcaldías de Villeta, Cachipay, Albán, La Vega, Supatá, Anolaima, Guaduas, La Dorada. Entidades: Fiscalía y CTI de Villeta; INPEC (Honda, Norcasia, Puerto Triunfo); FTI Bogotá; Todo en Salud a la Mano S.A.S. Plus alliances with veterinary clinics, specialized farms, obras civiles, arts centers, and universities (no specific names given for these).

**Próximas convocatorias (examples given):** Curso de Actualización en Atención al Paciente; Curso Obligatorio Salud Ocupacional; Programa Técnico Laboral en el Área Administrativa; Educación Continuada en Normativa y Procedimientos.

**Photography and brand assets:** real files live at `public/assets/img/` — `imagenhero1.png`, `imagenhero2.png`, `imagenhero3.png` (hero carousel: students in uniform, a graduation photo), `programas-tecnicos.png` (workshop/soldering photo, ESAT logo visible on the uniform, used as the "Programas Técnicos" area card background), `logo.png` (real color wordmark lockup, transparent background), `logo-negativo.png` (real white wordmark for dark backgrounds), `favicon.png` (icon mark only, transparent background). The real logo — used as the actual header/footer brand mark now, not a placeholder — is a horizontal wordmark: "ESAT" in a serif face with a gold dot, "EDUCACIÓN, SALUD, ARTE Y TURISMO" tagline beneath a gold rule; there is also a separate abstract orange/green/navy swoosh icon mark (visible embedded on staff/student uniforms in the photos, and as the standalone favicon) that is a distinct asset from the wordmark, not a combined lockup.

**Additional real facts confirmed against the live site (esat.edu.co) but not yet used anywhere on this rebuild by the user's choice:** the institution's formal classification is "Educación para el Trabajo y Desarrollo Humano" (ETDH); ESAT offers a free (≤1 hour) conference/talk for companies' staff on request. Neither is currently on the site — the user chose not to add the conference offer for now, and the ETDH classification was not requested to be added either; don't add either without being asked. A "servicios médicos" list (Consulta Externa, Ginecobstetricia, Pediatría, Urgencias, UCI, etc.) surfaced during that same check but doesn't fit the rest of ESAT's content and was never confirmed as genuinely theirs — treat it as unverified and do not use it.

**Absent:** no testimonials, no pricing, no specific social-media URLs, no map/geo API key. Future work must not fabricate any of these.

## Product Principles

1. Legal, contact, and address facts are sacred — reproduce exactly, never invent or approximate.
2. Every page reads as employment-outcome-first: fast, practical, certified path into the job market.
3. Institutional credibility is shown through licensing and convenios, not just asserted in copy.
4. Content is organized consistently around the 8-school taxonomy across nav, program cards, and footer.
5. Regional, multi-sede identity stays visible as a first-class section, not buried in the footer.
6. No stock-photo realism where no real photography exists — visuals lean on CSS/SVG/iconography/abstract 3D true to the institution rather than fabricated people or facilities.

## Accessibility & Inclusion

No specific standard mandated by the user beyond explicit mobile-first responsiveness and mid-range-mobile animation performance. Standard semantic/keyboard-accessible practice applies as a baseline (no elevated requirement recorded).
