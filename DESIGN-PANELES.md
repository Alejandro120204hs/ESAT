# Diseño de los paneles internos de ESAT

Sistema visual de **todos los paneles por rol** (Docente, Secretaría, Admin y los que faltan: Estudiante y Acudiente).
`DESIGN.md` es solo del **sitio público** (franjas azules, fotos, cifras grandes): no se usa en los paneles.

Modo: **Operate**. Quien entra a un panel viene a hacer algo (tomar asistencia, cobrar, revisar), no a mirar. Se
prioriza leer rápido, entender qué falta y actuar. La marca vive en los detalles, no en adornos.

**Referencia viva: el panel Docente** (`resources/views/docente`, `public/assets/{css,js}/docente`). Es el más simple
y el que primero se rehízo con estas reglas (9-11-2026). La Secretaría y el Admin se igualaron a él el 11-11-2026.

## 1. Cómo arrancar un panel nuevo

1. Copiar el layout del Docente: `docente/layout.blade.php` + `partials/{sidebar,topbar}.blade.php`,
   `css/docente/layout.css` y `js/docente/layout.js` a la carpeta del rol (`resources/views/{rol}/`,
   `public/assets/{css,js}/{rol}/`). No inventar otro layout.
2. Una vista por pantalla con su CSS y su JS propios y un **prefijo de 3 letras** en las clases (`ini-`, `grp-`, `tas-`…).
   Lo compartido va solo en `layout.css` (convención de CLAUDE.md: la duplicación entre pantallas es intencional).
3. Copiar componentes de una pantalla existente parecida antes de crear uno nuevo: tabla con buscador y filtros
   (Secretaría › Estudiantes), ficha en cajón lateral (Docente › Mis grupos), planilla editable con barra de guardar
   (Docente › Asistencia / Notas), horario semanal (Docente › Inicio = Secretaría › Horarios), combobox de Colombia.
4. Al terminar: detector de diseño en 0 (sus avisos `design-system-*` comparan con `DESIGN.md` del sitio y no
   aplican a los paneles), captura en escritorio y celular, y que nada se salga a 375 px.

## 2. Base (layout.css, igual en los tres paneles)

| Token | Valor | Uso |
|---|---|---|
| `--navy` / `--navy-2` / `--navy-3` | `#10284A` / `#173B67` / `#0A1B33` | Texto fuerte, títulos, menú lateral (`navy-3`), neutro de marca |
| `--orange` / `--orange-2` | `#D97B2E` / `#BE661F` | Acción y pendiente (botón principal, ítem activo, foco) |
| `--green` | `#40763A` | Marca; el "bien" de los estados usa `#2D7A3A` / `#2D6A27` / `#E6F4E4` |
| `--red` | `#B91C1C` | Riesgo y error |
| `--bg` | `#EEF2F7` | Fondo de la página |
| `--white`, `--text` `#1A1A1A`, `--muted` `#6B7280` | | Superficies, texto, texto secundario |
| `--line` | `rgba(16,40,74,0.12)` | Bordes y separadores |
| `--radius-md` / `--radius-lg` | 10 px / 16 px | Botones, campos, etiquetas / tarjetas y paneles |
| `--shadow-sm` / `--shadow-md` | azuladas y suaves | Solo para lo que flota (cajones, menús, diálogos) |

- Fuentes: **Archivo** (títulos, cifras, botones; 700–900) y **Public Sans** (texto). Cifras con `tabular-nums`.
- Menú lateral fijo de 270 px en `navy-3`; barra superior de 64 px con el chip de la sede y el usuario; el contenido
  (`.app-content`) tiene 32 px arriba y 36 px a los lados y es lo único que hace scroll. A ≤ 900 px el menú se vuelve
  un cajón con botón.
- Entrada de cada bloque con `.app-anim` (0,45 s, suave); con `prefers-reduced-motion` no hay animaciones.
- Etiquetas de estado `.app-status-tag` + `-active` (verde), `-warn` (ámbar), `-danger` (rojo), `-muted` (gris).

## 3. Color con significado

| Color | Significa | Ejemplos |
|---|---|---|
| **Naranja** | Acción o pendiente | Botón principal, "3 sin registrar", "Requieren atención", "Por matricular", borradores |
| **Rojo** | Riesgo o error | Mora, bajo el 80 %, reprobado, error de validación, "Urgente" |
| **Verde** (`#2D7A3A`) | Bien o completo | Activo, pagado, aprobado, al día, paso terminado |
| **Gris / azul de la marca** (`#EEF2F7` + `navy-2`/`navy-3`) | Neutro | Cifras de conteo, escuela, jornada, vinculación, categorías, accesos rápidos |

- Una tarjeta de cifra lleva color **solo** si la cifra es un pendiente, un riesgo o algo bueno. "Total de grupos",
  "Tiempo completo" o "Egresos" son neutras.
- La **escuela, la jornada, la vinculación y las categorías no llevan color** (son identidad, no estado). Avatares de
  personas: fondo `#E6ECF4` con iniciales en `navy-2` (o `navy-2` sólido con blanco en la cabecera de un cajón).
- **Excepciones** (el color codifica datos y se mantiene): el horario semanal con un color por escuela
  (`hor-c-*` / `ini-c-*`, y las jornadas de su columna de horas), la paleta de sedes de las gráficas (`--sede-1..8`,
  validada para daltonismo), las leyendas de gráficas (categorías y medios de pago en Contabilidad y Pagos), la
  asistencia **P/T/A/E** (verde, ámbar, rojo, azul para la excusa) y los roles del foro.
- Nada de morados, rosados, cian ni azules vivos fuera de esas excepciones.

## 4. Reglas de contenido (lo que hace que se sienta tranquilo)

- **Cada cifra aparece una sola vez por pantalla.** Si el saludo ya dice "17 cosas pendientes", la lista no repite el
  total en una insignia y no hay una tarjeta que lo vuelva a decir.
- **Ningún elemento "porque sí":** cada chip, color, insignia o tarjeta dice algo que no está ya a la vista.
- **Encabezado de pantalla:** título (h1/h2 de 1,85 rem, 900, `navy-3`) + una línea de explicación en `muted`. **Sin
  antetítulos** (etiquetas en mayúscula sobre un título).
- **Resumen en una línea** en vez de filas de 4–5 tarjetas de cifras cuando la pantalla es de trabajo
  ("Promedio 3,6 · 2 aprobaron"). Las pantallas de supervisión (Inicio, listados de la Secretaría y del Admin)
  conservan sus tarjetas de cifras, con el color de la regla 3.
- **Un solo botón naranja** por bloque: el de la acción que más urge. Lo demás, botones con borde.
- El número de pendientes va **dentro del botón** que los resuelve, no en un chip aparte.
- Vacíos de una línea cuando no hay nada que hacer ("Todos tus estudiantes van sobre el 80 %"); vacíos con ícono y
  acción solo cuando la pantalla entera está vacía.
- Todo en español, sin "por favor" ni signos de exclamación; los errores dicen qué pasó y qué hacer, con el nombre de
  la persona cuando aplica.

## 5. Componentes

- **Botones**: principal naranja (Archivo 700, ~0,85 rem, radio 10 px, alto ≥ 36 px), con borde (`--line`, texto
  `navy-3`, hover a naranja), pequeño (`-sm`). Íconos de trazo a 15–18 px.
- **Tablas**: encabezado en mayúscula 0,7 rem `muted` sobre `--bg`; filas de 13–14 px con línea `--line`; la fila
  entera abre la ficha cuando la tabla es de consultar (patrón de fila clicable de CLAUDE.md).
- **Tarjetas y paneles**: blanco, borde 1,5 px `--line`, radio 16 px, sin sombra en reposo.
- **Pestañas**: grupo segmentado sobre `#E3E9F1`, la activa en blanco; conteo en un chip pequeño.
- **Cajón lateral** (ficha) y **diálogos**: `role="dialog"`, `aria-modal`, foco atrapado y Escape para cerrar.
- **Barra de guardar** fija abajo en las planillas: estado ("Cambios sin guardar") + resumen + Guardar/Descartar.
- **Avisos** en línea: fondo tenue del color de su significado (naranja pendiente, rojo riesgo, neutro informativo).
- **Toasts** con `role="status"` y errores con `role="alert"`.
- **Combobox de Colombia**: copiar el de un panel Admin con `alScroll` (CLAUDE.md).

## 6. Accesibilidad (piso)

- Foco visible en todo (anillo naranja), navegación completa con teclado, `hidden` respetado
  (`.clase[hidden] { display: none; }`), nada de texto debajo de 12 px en lo que se lee.
- Controles de al menos 24 px (ideal 44 px en celular) y ningún scroll horizontal a 375 px.
- **Pendiente (auditoría del 11-11-2026)**: el texto blanco sobre el naranja `#D97B2E` da 3,07:1 y el `muted` sobre
  `--bg` 4,3:1 (AA pide 4,5:1). Cuando se corrija en `layout.css`, los paneles nuevos lo heredan.
