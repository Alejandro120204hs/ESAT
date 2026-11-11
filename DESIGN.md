---
name: ESAT · Sitio público
description: Institución técnica verificable, en bloques limpios sobre blanco, bandas azul marino y acción en naranja.
colors:
  navy: "#10284A"
  navy-2: "#173B67"
  navy-3: "#0A1B33"
  navy-4: "#081527"
  orange: "#D97B2E"
  orange-2: "#BE661F"
  orange-ink: "#A4531A"
  orange-tint: "#FBE9D8"
  orange-cta: "#B85A1E"
  orange-cta-2: "#9C4A16"
  orange-claro: "#F7A766"
  green: "#40763A"
  green-2: "#325C2D"
  white: "#FFFFFF"
  bg-soft: "#F2F5FA"
  blue-tint-1: "#EEF2F7"
  text: "#1A1F2B"
  muted: "#59626F"
  line: "rgba(16, 40, 74, 0.12)"
  line-strong: "rgba(16, 40, 74, 0.24)"
typography:
  display:
    fontFamily: "Archivo, Public Sans, system-ui, sans-serif"
    fontSize: "clamp(2.4rem, 1.3rem + 3.2vw, 3.8rem)"
    fontWeight: 800
    lineHeight: 1.02
    letterSpacing: "-0.035em"
  headline:
    fontFamily: "Archivo, Public Sans, system-ui, sans-serif"
    fontSize: "clamp(2.1rem, 1.3rem + 2.8vw, 3.6rem)"
    fontWeight: 800
    lineHeight: 1.08
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Archivo, Public Sans, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 800
    lineHeight: 1.08
    letterSpacing: "-0.015em"
  figure:
    fontFamily: "Archivo, Public Sans, system-ui, sans-serif"
    fontSize: "clamp(2.3rem, 1.5rem + 2.6vw, 3.5rem)"
    fontWeight: 900
    lineHeight: 1
    letterSpacing: "-0.03em"
    fontFeature: "tnum"
  lead:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "clamp(1.05rem, 0.98rem + 0.35vw, 1.25rem)"
    fontWeight: 400
    lineHeight: 1.55
  body:
    fontFamily: "Public Sans, system-ui, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.65
  label:
    fontFamily: "Archivo, Public Sans, system-ui, sans-serif"
    fontSize: "0.95rem"
    fontWeight: 700
    letterSpacing: "-0.005em"
rounded:
  md: "16px"
  lg: "24px"
  xl: "32px"
  pill: "999px"
spacing:
  s-2: "8px"
  s-3: "12px"
  s-4: "16px"
  s-5: "24px"
  s-6: "32px"
  s-7: "48px"
  s-8: "64px"
  s-9: "96px"
  gutter: "clamp(20px, 5vw, 72px)"
  section-y: "clamp(88px, 10vw, 160px)"
  container: "1480px"
components:
  button-accent:
    backgroundColor: "{colors.orange-cta}"
    textColor: "{colors.white}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0 24px"
    height: "48px"
  button-accent-hover:
    backgroundColor: "{colors.orange-cta-2}"
  button-whatsapp:
    backgroundColor: "{colors.green}"
    textColor: "{colors.white}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0 24px"
    height: "48px"
  button-whatsapp-hover:
    backgroundColor: "{colors.green-2}"
  button-navy:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0 24px"
    height: "48px"
  button-navy-hover:
    backgroundColor: "{colors.navy-2}"
  button-outline:
    textColor: "{colors.navy}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0 24px"
    height: "48px"
  card-escuela:
    backgroundColor: "{colors.bg-soft}"
    rounded: "{rounded.lg}"
    padding: "32px 32px 24px"
  card-escuela-hover:
    backgroundColor: "{colors.white}"
  card-foto:
    backgroundColor: "{colors.navy-3}"
    textColor: "{colors.white}"
    rounded: "{rounded.lg}"
    padding: "28px"
  card-carrera:
    backgroundColor: "{colors.navy-2}"
    textColor: "{colors.white}"
    rounded: "{rounded.lg}"
    padding: "32px 32px 28px"
  banda-cifras:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
    typography: "{typography.figure}"
    rounded: "{rounded.lg}"
  icono-tesela:
    backgroundColor: "{colors.bg-soft}"
    textColor: "{colors.navy}"
    rounded: "13px"
    size: "44px"
  chip:
    backgroundColor: "{colors.bg-soft}"
    textColor: "{colors.navy}"
    rounded: "{rounded.pill}"
    padding: "6px 12px"
  nav-link:
    textColor: "{colors.navy}"
    rounded: "{rounded.pill}"
    padding: "0 14px"
    height: "42px"
---

# Design System: ESAT · Sitio público

> **Alcance.** Este documento describe solo el **sitio público** de ESAT: `resources/views/layouts/app.blade.php`, `partials/{header,footer,icon-sprite}`, `welcome.blade.php`, `paginas/{programa,continuada,escuela}` y `paginas/partes/*`, con sus estilos en `public/assets/css/sitio/` y su movimiento en `public/assets/js/sitio/`. La **plataforma interna** (paneles de superadmin, admin, docente, login: `resources/views/{admin,superadmin,docente}/**`, `public/assets/{css,js}/{admin,superadmin,docente,login}/**`) es otro mundo visual y **no** se rige por estos tokens ni reglas. Su sistema visual está en **`DESIGN-PANELES.md`** (vale para todos los paneles, también los que faltan).

## Overview

**Creative North Star: "La institución verificable"**

El sitio presenta a ESAT como algo que se puede comprobar: licencia, siete escuelas, cuatro sedes con dirección y convenios con nombre. La forma lo acompaña con bloques limpios sobre blanco, franjas azul claro (#F2F5FA) que marcan el ritmo y bandas azul marino de lado a lado para los momentos de peso (cifras, nuevas carreras, cierre y pie). Las fotos de ESAT van siempre dentro de marcos redondeados grandes, nunca a sangre completa con texto centrado encima. Las cifras salen de los datos reales y suben al hacer scroll.

El color tiene una gramática estricta: el naranja es **acción y avance** (CTA en píldora, la palabra resaltada, la línea de progreso, la espina que se llena), el verde es **solo WhatsApp** y todo lo demás (íconos, categorías, estados activos) es azul. La densidad es media-baja: secciones con aire vertical generoso (88–160 px), una escala de espaciado de base 4 px, pocas cajas (listas con filetes y columnas abiertas en lugar de tarjetas con borde) y cada sección con su propia forma (lista con encabezado fijo, mosaico de fotos, pista horizontal, mapa con tarjetas) para no repetir la misma tarjeta en toda la página.

El movimiento es de intensidad media: Lenis da el scroll suave y GSAP ScrollTrigger las entradas `expo.out`, el parallax dentro de los marcos, los contadores y la pista horizontal fijada. El contenido es visible por defecto: el movimiento solo anima desde ese estado, y con `prefers-reduced-motion` todo queda quieto y visible.

**Key Characteristics:**
- Blanco de base, franjas `bg-soft` alternadas y bandas `navy` a todo el ancho.
- Fotos en marcos de 24–32 px de radio, con velo azul solo del lado del texto.
- Naranja = acción y avance (botones en `orange-cta` para que el texto blanco pase AA); verde = WhatsApp; azul = todo lo demás.
- Encabezados de sección divididos: titular a la izquierda, entrada abajo a la derecha.
- Archivo 800 en titulares con tracking negativo; Public Sans en el texto.
- Botones en píldora; íconos en teselas de esquina suave, siempre en azul.
- Movimiento medio, ligado al scroll, que nunca oculta contenido sin JS.

## Colors

Una paleta de marca de tres tintas (azul marino, naranja, verde) sobre blanco, con roles fijos y no intercambiables.

### Primary
- **Azul Marino ESAT** (`navy`): el color de la institución. Titulares, íconos, enlaces de navegación, estados activos (sede seleccionada, marcadores del mapa) y las bandas completas: barra de cifras, "Nuevas carreras", convenio de alcaldías, cierre del pie y pie de página. También es `theme-color`.
- **Azul Noche** (`navy-2`): hover del botón azul, íconos secundarios dentro de listas (convenios, sellos de respaldo), lemas bajo un titular.
- **Azul Profundo** (`navy-3`): barra de servicio de la cabecera, base del pie, y fondo de todo marco de foto (lo que se ve mientras carga o si no hay foto).
- **Azul Abismo** (`navy-4`): extremo oscuro de los velos y de los campos sin foto; tooltips del mapa del pie.

### Secondary
- **Naranja Avance** (`orange`): el naranja de la marca, solo para marcas que no son texto: línea de progreso de lectura bajo la cabecera, espina de "Por qué estudiar en ESAT" y sus nodos encendidos, barra de avance de la pista de carreras, subrayado del enlace de navegación, anillo de foco, borde de foco del campo, cursor de los campos.
- **Naranja Botón** (`orange-cta`): fondo de todos los botones de acción y de la selección de texto. Texto blanco encima a 4.65:1; sobre `orange` daba 3.08:1 y no pasaba AA.
- **Naranja Botón Hundido** (`orange-cta-2`): hover de los botones de acción.
- **Naranja Claro** (`orange-claro`): la palabra resaltada sobre azul (h1 de la portada, cierre del pie, encabezados claros de sección).
- **Naranja Hundido** (`orange-2`): tono de la marca; ya no es fondo de botón.
- **Naranja Tinta** (`orange-ink`): el naranja cuando es texto sobre blanco (palabra resaltada en secciones claras, hover de enlaces del megamenú); da 5:1 de contraste donde `orange` no alcanza.
- **Naranja Velo** (`orange-tint`): fondo de hover en el desplegable de Educación Continuada y halo del nodo encendido de la espina.

### Tertiary
- **Verde WhatsApp** (`green`) / **Verde Hundido** (`green-2`, hover): exclusivamente botones y enlaces que abren WhatsApp: botón de la cabecera, botón flotante, botones "Escribir por WhatsApp", enlace por curso en Educación Continuada y el hover del botón secundario del hero, que es el de WhatsApp.

### Neutral
- **Blanco** (`white`): fondo base, tarjetas, panel del megamenú, menú móvil.
- **Azul Niebla** (`bg-soft`): franjas alternas de sección, teselas de íconos, chips, hover de navegación, pie del megamenú y del menú móvil.
- **Azul Bruma** (`blue-tint-1`): fondo de los marcos de foto claros (foto de "Por qué", mapa de sedes) mientras carga.
- **Tinta** (`text`): texto corrido.
- **Gris Pizarra** (`muted`): párrafos de apoyo bajo titulares, direcciones, migas de pan.
- **Línea** (`line`) / **Línea Firme** (`line-strong`): bordes de tarjetas y separadores de listas; la firme para bordes de botones con contorno y los puntos de las listas del megamenú.

### Named Rules
**La Regla de la Gramática de Color.** Naranja = acción y avance. Verde = WhatsApp. Azul = íconos, categorías y estados activos. Si un elemento no es un CTA ni marca progreso, no es naranja; si no abre WhatsApp, no es verde.

**La Regla de la Tinta Naranja.** El naranja de la marca (`orange`) nunca lleva ni es texto: queda para líneas, nodos y anillos. Texto blanco sobre naranja va en `orange-cta`; naranja como texto sobre blanco es `orange-ink`; sobre azul, `orange-claro`.

**La Regla de las Bandas Enteras.** El azul marino no se usa en recuadros sueltos dentro de una sección clara para "dar contraste": ocupa bandas de lado a lado o tarjetas que son el ancla de su sección (barra de cifras, convenio de alcaldías, tarjeta del asesor de escuelas).

## Typography

**Display Font:** Archivo (con Public Sans, system-ui)
**Body Font:** Public Sans (con system-ui)

**Character:** Archivo en peso 800 con tracking negativo da titulares compactos y firmes, de institución seria pero actual; Public Sans, la tipografía de gobierno, sostiene el texto con neutralidad y buena legibilidad. Ambas cargan desde Google Fonts.

### Hierarchy
- **Display** (800, `clamp(2.4rem, 1.3rem + 3.2vw, 3.8rem)` en el inicio y hasta 4.4rem en subpáginas, 1.02, -0.035em): solo el h1 de la portada, en blanco sobre el marco de foto. Si el nombre del programa pasa de 26 caracteres, la portada lleva `.es-largo` y el h1 baja a `clamp(2rem, 1.2rem + 2.4vw, 3.1rem)`.
- **Headline** (800, `clamp(2.1rem, 1.3rem + 2.8vw, 3.6rem)`, 1.08, -0.025em): h2 de sección, en azul marino y con `text-wrap: balance`.
- **Title** (800, 1.1–1.4rem, -0.015em): h3 de tarjetas, escuelas, competencias, sedes.
- **Figure** (900, `clamp(2.3rem, 1.5rem + 2.6vw, 3.5rem)`, 1, cifras tabulares): los números contados de la barra de cifras.
- **Lead** (400, `clamp(1.05rem, …, 1.25rem)`, 1.55): párrafo bajo el titular de sección, en `muted`, máximo ~46–62ch.
- **Body** (400, 1.0625rem, 1.65): texto corrido, `text-wrap: pretty`, líneas de 52–60ch.
- **Label** (Archivo 700, 0.92–0.95rem): botones, enlaces de acción con flecha, navegación (600). Las únicas mayúsculas con tracking son los encabezados de columna del pie (≥0.875rem, 800, 0.08em) y los nombres de escuela en el acordeón del menú móvil.

### Named Rules
**La Regla del Piso de 14 px.** El texto pequeño informativo nunca baja de 14 px (0.875rem): conteo de programas de cada escuela, encabezados de columna y base del pie, migas de pan (0.9rem), barra de servicio (0.85rem) y etiqueta de la barra fija de inscripción (0.85rem).

**La Regla de la Palabra Resaltada.** Un titular puede tener una sola palabra o frase en naranja (`.resalte`), la que nombra la acción o lo que se gana. Nunca dos, nunca el titular entero.

**La Regla del Titular Sin Antetítulo.** Los titulares de sección van solos con su párrafo de apoyo: sin etiquetas pequeñas en mayúsculas encima.

## Layout

Contenedor de 1480 px con márgenes laterales fluidos (`gutter`: 20–72 px) y separación vertical de sección `section-y` (88–160 px). El espaciado interno sale de una escala de base 4 px (`s-2` 8 · `s-3` 12 · `s-4` 16 · `s-5` 24 · `s-6` 32 · `s-7` 48 · `s-8` 64 · `s-9` 96); los bloques de texto con foto usan `clamp(28px, 5vw, 72px)`.

**Encabezado de sección.** Dos formas. *Dividido* (`es-dividida`): a ≥1000 px, cuadrícula 1.2fr / 0.8fr con separación de 24 px / 96 px; el titular (máx. 18ch) a la izquierda y la entrada alineada abajo a la derecha. Se usa en Áreas, Escuelas, Sedes, Convenios, Convocatorias y Proyección. *Apilado* (titular sobre entrada, máx. 660 px): Por qué, Nuevas carreras y Formación práctica. Por debajo de 1000 px todos se apilan.

**Portadas.** El marco de la portada del inicio usa alto mínimo (no alto fijo) y el texto va en el flujo, alineado abajo con flex, para que nada se recorte con titulares largos o pantallas bajas. Los rellenos verticales de las portadas (inicio y subpáginas) se miden en `vh` (`clamp(48px, 9vh, 150px)` arriba), no en `vw`: en una pantalla ancha pero baja el titular y los CTA siguen dentro de la primera vista. La foto de "Por qué estudiar en ESAT" tampoco pasa de `100svh` menos la cabecera.

El ritmo de página alterna fondos: blanco → `bg-soft` → blanco → banda `navy` → `bg-soft`…, y cada sección adopta una forma distinta (columna con foto fija y recorrido con espina; mosaico de una tarjeta grande y dos pequeñas; cuadrícula de escuelas; pista horizontal fijada; mapa fijo con lista de sedes; cinta de convenios; lista de convocatorias). En subpáginas: encabezado fijo a la izquierda con lista a la derecha, fotos de salida laboral, la foto de práctica que se abre con el scroll, respaldo institucional y "más programas".

Los marcos de foto grandes (portadas, práctica) se salen del contenedor (las portadas hasta 1520 px) con un margen de 10–18 px a los lados, para que se lean como objetos redondeados y no como fondos a sangre.

La cabecera es fija: barra de servicio de 38 px (licencia, WhatsApp, certificado, ingreso) + barra principal de 78 px (70 px a ≤1100 px). Al bajar, la barra de servicio se recoge; la principal no cambia de alto.

**Puntos de quiebre** (de escritorio hacia abajo): 1240 px (navegación más apretada, megamenú a 3 columnas), 1100 px (la navegación pasa a menú lateral; aparecen los botones de ícono de WhatsApp y menú; se oculta el WhatsApp flotante), 1000 px (los encabezados divididos se apilan), 900 px (las cuadrículas de dos columnas se apilan; mapa y encabezados dejan de ser fijos), 760 px (se oculta la barra de servicio), 600 px (portadas en columna, botones del hero a ancho completo), 420 px.

### Named Rules
**La Regla de la Portada en Celular.** A ≤600 px, en el inicio y en las subpáginas, la foto va arriba, en el flujo y entera (16:10 en el inicio, 16:9 en subpáginas), sin velo ni parallax; el titular y los CTA van debajo, sobre azul. En subpáginas solo queda el CTA de inscripción (el secundario se oculta); si aun así queda debajo del borde, la barra fija de inscripción aparece desde que carga la página.

**La Regla de la Pantalla Baja.** Una pantalla de 1920×1080 con Windows al 125 % es un viewport de unos 1536×704: el diseño se revisa también a 1536×704 y 1366×650, no solo a 1440×900. Nada que ocupe `100svh` centra su contenido sin protección: la pista fijada de "Nuevas carreras" usa `justify-content: safe center`, deja el alto de la cabecera como relleno superior, y su titular, el alto de las fotos y los márgenes se miden en `vh`. La pista solo se fija a ≥1100 px de ancho **y** ≥620 px de alto; por debajo es deslizable con flechas.

**La Regla de un Solo WhatsApp Flotante.** A ≤1100 px el botón flotante de WhatsApp se oculta, porque la cabecera ya lleva su botón de WhatsApp siempre visible.

## Elevation & Depth

Sistema mayormente plano con sombras azuladas suaves y difusas, siempre teñidas del azul profundo (`rgba(10, 27, 51, …)`), nunca negras ni duras. La profundidad la dan sobre todo el cambio de fondo (blanco / `bg-soft` / `navy`) y los velos en degradado sobre las fotos. Las sombras aparecen en objetos que flotan sobre otros (barra de cifras montada sobre la portada, megamenú, sello sobre la foto) o como respuesta a hover/estado activo.

### Shadow Vocabulary
- **Media** (`box-shadow: 0 10px 28px -10px rgba(10, 27, 51, 0.22), 0 2px 6px rgba(10, 27, 51, 0.06)`): hover de tarjetas (escuela, convocatoria, "otro programa"), sede activa, mapa de sedes, hover del botón azul.
- **Alta** (`box-shadow: 0 28px 64px -20px rgba(10, 27, 51, 0.34), 0 6px 16px rgba(10, 27, 51, 0.08)`): barra de cifras, megamenú, desplegable, sello sobre la foto.
- **Resplandor de acción** (`0 8px 20px -8px rgba(217, 123, 46, 0.6)`; verde para WhatsApp): sombra teñida del propio color bajo los botones de acción.

### Named Rules
**La Regla de la Sombra Teñida.** Toda sombra es del azul de la marca (o del color del propio botón) y difusa, con desplazamiento negativo de expansión. Nada de sombras sólidas desplazadas.

**La Regla del Velo del Lado del Texto.** Sobre una foto, el velo azul (`navy-4` en degradado) cubre solo la zona del texto (izquierda o abajo) y deja libre el resto de la foto.

**La Regla del Contraste Medido.** El texto sobre una foto debe dar ≥4.5:1 (≥3:1 si es grande) contra el píxel más claro del percentil 90 que tiene detrás. Si no lo da, se espesa el velo o el texto sale de la foto (como en la tarjeta partida).

## Shapes

Esquinas generosas y consistentes: 32 px (`xl`) para los marcos de foto grandes (portadas, práctica, foto de "Por qué", mapa de sedes, caja de novedades, respaldo), 24 px (`lg`) para tarjetas, megamenú y barra de cifras, 16 px (`md`) para el desplegable y el mapa del pie. Los botones, chips, enlaces de navegación y el campo de correo son píldoras (999 px). Los íconos viven en teselas cuadradas de esquina suave (12–17 px de radio, 38–58 px de lado) o en círculos (nodos de la espina, competencias, redes sociales, flechas). Los íconos son de trazo (1.7) del sprite propio; los rellenos solo para logos de marcas como WhatsApp.

En celular, los marcos de 32 px bajan a 24 px. Las fotos sin archivo se muestran como un campo azul liso (degradado dentro de la gama `navy-2`→`navy-4`), nunca como un aviso de "foto pendiente".

## Components

### Buttons
Píldoras firmes y táctiles que suben 2 px al pasar el cursor.
- **Shape:** píldora (999 px), alto 48 px (56 px en `lg`, 40 px en `sm`), borde de 1.5 px.
- **Acento (naranja):** el CTA principal ("Ver programas", "Inscribirme"); `orange-cta` con texto blanco (4.65:1) y resplandor del mismo tono; hover a `orange-cta-2`.
- **WhatsApp (verde):** cualquier botón que abra WhatsApp; hover a `green-2`.
- **Azul:** acción secundaria sólida sobre fondos claros; hover a `navy-2` con sombra media.
- **Contorno / Contorno claro / Fantasma:** acciones terciarias; contorno `line-strong` que se vuelve `navy` en hover; la versión clara (borde blanco al 40 %) va sobre fotos y bandas azules.
- **Hover / Focus:** sube 2 px; la flecha final se corre 3 px a la derecha; al presionar, escala 0.98. Foco: anillo naranja de 2.5 px separado 3 px.

### Chips
- **Style:** píldoras de 6×12 px, `bg-soft` con texto `navy` (claves del programa) o contorno `line-strong` de 46 px de alto (otras líneas de Educación Continuada); sobre fotos (áreas), 40 px de alto mínimo, blanco al 12 % con borde blanco al 24 %.
- **State:** el hover rellena: azul con texto blanco sobre fondo claro, blanco con texto azul sobre fotos.

### Cards / Containers
- **Corner Style:** 24 px (`lg`).
- **Panel de escuela:** sin borde, fondo `bg-soft`, 32 px de relleno; en hover pasa a blanco con sombra media y sube 4 px. Las filas de programas miden al menos 46 px y se separan con filetes `line-strong`.
- **Tarjeta clara (sede, "otro programa"):** fondo blanco, borde `line` de 1–1.5 px, sombra media en hover o en estado activo; la sede activa se marca con borde `navy`.
- **Lista editorial (convocatorias):** sin cajas; filas separadas por filetes `line-strong`, con tesela de ícono blanca; en hover la fila se pone blanca.
- **Columnas abiertas (convenios):** sin fondo ni borde, solo un filete superior `navy` de 2 px; únicamente "Alcaldías" es un panel `navy`.
- **Tarjeta de foto (área, salida laboral):** fondo `navy-3`, foto a sangre dentro de la tarjeta con velo de abajo hacia arriba, texto blanco abajo, tesela de ícono blanca con ícono azul y enlace con subrayado blanco; la foto escala a 1.05 en hover. En las áreas el velo (0°) va de 0.95 → 0.84 (45 %) → 0.50 (72 %) → 0.20, y a ≤900 px se espesa a 0.96 → 0.88 (55 %) → 0.60 (85 %) → 0.40; los párrafos son blanco sólido.
- **Tarjeta partida (nuevas carreras):** foto arriba en 3:2, limpia, sin texto encima; cuerpo abajo sobre `navy-2` con 32 px de relleno. La tesela de ícono blanca de 46 px monta sobre el borde de la foto (margen superior −55 px, sombra corta). Título `clamp(1.2rem, 1.1rem + 0.35vw, 1.4rem)`; el enlace se empuja al fondo (`margin-top: auto`) para que los enlaces queden alineados entre tarjetas. Sin insignias por tarjeta: "Doble titulación" vive solo en la entrada de la sección. En hover la tarjeta sube 6 px y la foto se acerca. Debajo de la pista, un riel de avance a todo el ancho (pista de 3 px blanca al 16 % con relleno `orange`) y, solo en el modo deslizable, las flechas circulares.
- **Fotos de escuela:** sin parallax; en hover se acercan a 1.05.
- **Contenedor de franja:** `bg-soft` sin borde (respaldo institucional, caja de novedades).
- **Internal Padding:** 20–32 px; 24–40 px en tarjetas de foto.

### Inputs / Fields
- **Style:** el único campo (novedades) es una píldora blanca con borde `line-strong` de 1.5 px que contiene el botón.
- **Focus:** borde naranja y halo naranja al 18 % (`0 0 0 4px`).

### Navigation
- **Cabecera:** barra blanca sólida, sin `backdrop-filter`, con borde `line`; logo en color a 52 px que se reduce a 0.86 al compactarse. Enlaces Archivo 600 en azul, píldoras de 42 px de alto; hover con fondo `bg-soft` y un subrayado naranja de 2 px que crece desde la izquierda (también marca la página actual).
- **Línea de progreso:** 2 px naranja al pie de la cabecera que se llena con el scroll.
- **Megamenú (Programas Técnicos):** panel blanco de 24 px de radio con sombra alta que se abre con un recorte de arriba hacia abajo; 4 columnas de escuelas con tesela de ícono azul; pie en `bg-soft`. Se abre solo con clic.
- **Desplegable (Educación Continuada):** una columna, hover en `orange-tint` con texto `orange-ink` y flecha que aparece.
- **Móvil (≤1100 px):** hoja lateral blanca desde la derecha (máx. 440 px), acordeones con chevrón, pie en `bg-soft` con los botones de acción a ancho completo.
- **Migas de pan:** 0.9rem en `muted`; la página actual en `navy` 700.

### Barra de cifras (firma)
Banda `navy` de 24 px de radio con sombra alta, montada sobre el borde inferior de la portada (margen negativo). Cuatro cifras separadas por líneas blancas al 14 %; número en Archivo 900 que sube al aparecer (`data-contar`) y etiqueta debajo. Pasa a 2×2 a ≤900 px.

### Espina de avance (firma)
Lista vertical de razones con nodos circulares de 48 px unidos por una línea de 2 px. Una capa naranja llena la línea con el scroll y cada nodo alcanzado se enciende en naranja con halo `orange-tint`. Con movimiento reducido, la espina aparece llena.

### Barra fija de inscripción (subpáginas)
Barra blanca translúcida al pie de la pantalla, con el nombre del programa y su CTA. Aparece siempre que el CTA "Quiero inscribirme" de la portada no está entero a la vista (también al cargar, si en un celular quedó debajo del borde) y se va al llegar al cierre del pie, que ya invita a inscribirse; oculta queda `inert`. Empuja el WhatsApp flotante 80 px hacia arriba.

### Movimiento
Entradas por atributo (`data-revelar`: `subir` 32 px, `escalonar` con 0.08 s entre hijos, `mascara` con recorte redondeado de 32 px, `lado` 40 px), todas `expo.out` de 0.9–1.4 s; parallax con `data-parallax` dentro de un marco más alto que el visible; contadores de 1.8 s; transiciones CSS con `cubic-bezier(0.16, 1, 0.3, 1)`. Las barras y líneas animan `transform` (escala), no alto ni ancho. Con `prefers-reduced-motion` no hay scroll suave, entradas ni desplazamientos, pero las transiciones de color, fondo, borde, sombra y opacidad se mantienen a 0.2 s para que hover, foco y estados sigan dando respuesta.

## Do's and Don'ts

### Do:
- **Do** usar `orange-cta` como fondo de todo botón con texto blanco y `orange` solo para marcas sin texto (progreso, espina, barra de la pista, subrayado, foco).
- **Do** usar `orange-ink` cuando el naranja es texto sobre blanco y `orange-claro` cuando es texto sobre azul.
- **Do** preferir filetes y columnas abiertas a tarjetas con borde; una caja tiene que ganarse su lugar.
- **Do** medir el texto sobre fotos: ≥4.5:1 (≥3:1 grande) contra el percentil 90 más claro de lo que tiene detrás.
- **Do** mantener todo texto informativo en 14 px (0.875rem) o más.
- **Do** cargar Leaflet bajo demanda (`ESAT.cargarMapa`: precarga en tiempo libre e inicio cuando el mapa está a una pantalla de distancia), nunca en el layout.
- **Do** precargar la foto de la portada del inicio con `fetchpriority="high"`.
- **Do** en los bucles de `gsap.ticker`, guardar las medidas en caché, escribir con `quickSetter` y pausar fuera de pantalla.
- **Do** dar a todo control táctil al menos 40–44 px de alto (pasos de la portada, enlaces y chips de áreas, filas de programas).
- **Do** reservar `green`/`green-2` para lo que abre WhatsApp.
- **Do** poner las fotos en marcos de 24–32 px con fondo `navy-3` y velo solo del lado del texto.
- **Do** mostrar un campo azul liso cuando no hay foto para un programa.
- **Do** apilar la portada en celular: foto arriba en el flujo, titular y CTA debajo sobre azul.
- **Do** dejar el contenido visible sin JS y con movimiento reducido; el movimiento solo anima desde ese estado. Con movimiento reducido se cortan animaciones y desplazamientos, pero los cambios de color, fondo, borde, sombra y opacidad siguen (0.2 s) como respuesta de estado.
- **Do** sacar cada cifra y cada dato legal, de sede o de convenio de `config/sitio.php` / `config/contenido.php`; nunca inventarlos.

### Don't:
- **Don't** hacer un hero oscuro a pantalla completa con texto centrado.
- **Don't** usar verde para éxito, categorías o decoración: es el color de WhatsApp.
- **Don't** pintar categorías, etiquetas o íconos en naranja; van en azul sobre `bg-soft`.
- **Don't** poner antetítulos o etiquetas en mayúsculas sobre los titulares de sección.
- **Don't** poner texto blanco sobre el naranja de la marca `#D97B2E` (3.08:1, no pasa AA).
- **Don't** usar sombras negras, duras o desplazadas sin difuminado.
- **Don't** poner `backdrop-filter` en la cabecera ni en nada fijo que se repinte en cada cuadro de scroll: la cabecera es blanca sólida.
- **Don't** poner `mask-image` en algo que se mueve; la cinta de municipios desvanece sus bordes con dos degradados fijos (`::before`/`::after`) y sus pistas llevan `will-change: transform`.
- **Don't** leer el layout (medidas, `getBoundingClientRect`) en cada cuadro de un bucle de `gsap.ticker`.
- **Don't** poner insignias en cada tarjeta de carrera ni texto encima de su foto.
- **Don't** mostrar avisos de "foto pendiente" ni fotos de banco que no sean de ESAT.
- **Don't** mostrar el WhatsApp flotante a ≤1100 px, donde la cabecera ya lo tiene.
- **Don't** abrir el megamenú o el desplegable con hover: solo con clic.
- **Don't** llevar estos tokens a la plataforma interna (paneles): es otro mundo visual.

## Pendiente

- **Fotografía real.** Las fotos del sitio (`public/assets/img`: portada, áreas, escuelas, carreras, programas) parecen generadas con IA aunque se recibieron como material de ESAT; deben reemplazarse por fotografía real de ESAT. La procedencia está en `.impeccable/procedencia-imagenes.md`. Hasta entonces, no se tratan como fotos reales de la institución.
