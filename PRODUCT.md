# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel Blade views (server-rendered). Pure CSS in `public/assets/css/` and pure JS in `public/assets/js/` — no Bootstrap, no Tailwind, no JS framework. (The repo ships a default Laravel + Vite + Tailwind scaffold under `resources/`, but the ESAT site itself bypasses that pipeline and loads hand-written assets from `public/assets/` directly, per explicit user instruction.)

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

## Capabilities and Constraints

- Rebuilding `resources/views/welcome.blade.php` as the ESAT homepage; currently holds only Laravel's default scaffold markup.
- Navigation requires a mega-dropdown for "Programas Técnicos" listing all 7 schools and their programs, collapsing to a usable mobile pattern.
- Hero requires a mouse-reactive animated 3D element; must stay performant on mid-range mobile (explicit constraint — cannot tank scroll/frame rate on low-end devices).
- Scroll-triggered reveal/fade-in animation expected per section.
- Real photography now exists at `public/assets/img/` (see Evidence on Hand) and is used in the hero. No standalone logo file exists yet — the brand mark stays a placeholder built from CSS/SVG until one is supplied. Beyond the 3 hero photos, other visuals remain CSS/SVG/iconography rather than fabricated stock photography.
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
5. Escuela Ciencias — Producción Agropecuaria y Zootecnia; Asistente de Veterinaria y Zootecnia; Obras Civiles y Arquitectura; Criminalística; Energías Renovables; Asistente Lab. Clínico Veterinario; Electricista; Electromecánica; Mecánica y Electrónica de Motos.
6. Escuela de Educación e Idiomas — Inglés A1-B2; Francés A1-B2; Asistente de Preescolar.
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

**Photography:** three real photos now live at `public/assets/img/` (`imagenhero1.png`, `imagenhero2.png`, `imagenhero3.png`) — students in uniform with the real ESAT logo visible, and a graduation photo. Used as the homepage hero's auto-rotating Bootstrap carousel background. The real ESAT logo mark (an abstract orange/green/navy swoosh) is visible embedded in these photos but no standalone logo file has been supplied — the header/footer brand mark is still a placeholder typographic lockup, not the real logo.

**Absent:** no standalone logo file, no testimonials, no pricing, no specific social-media URLs, no map/geo API key. Future work must not fabricate any of these.

## Product Principles

1. Legal, contact, and address facts are sacred — reproduce exactly, never invent or approximate.
2. Every page reads as employment-outcome-first: fast, practical, certified path into the job market.
3. Institutional credibility is shown through licensing and convenios, not just asserted in copy.
4. Content is organized consistently around the 7-school taxonomy across nav, program cards, and footer.
5. Regional, multi-sede identity stays visible as a first-class section, not buried in the footer.
6. No stock-photo realism where no real photography exists — visuals lean on CSS/SVG/iconography/abstract 3D true to the institution rather than fabricated people or facilities.

## Accessibility & Inclusion

No specific standard mandated by the user beyond explicit mobile-first responsiveness and mid-range-mobile animation performance. Standard semantic/keyboard-accessible practice applies as a baseline (no elevated requirement recorded).
