# Procedencia de las imágenes del sitio público

Todas las fotos de `public/assets/img` son material que ESAT entregó con el
proyecto (fotos del sitio, logos y favicon). En el rediseño del sitio público
(rama `feat/rediseno-sitio-publico`, noviembre de 2026) **no se generó ni se
descargó ninguna imagen nueva**.

Los archivos `<nombre>.webp` y `<nombre>-960.webp` son conversiones mecánicas de
los originales PNG/JPEG con el mismo nombre, hechas con sharp 0.33.5
(`webp`, calidad 74, ancho máximo 1920 px y 960 px, sin agrandar). Los
originales se conservan junto a ellos. `logo.png`, `logo-negativo.png` y
`favicon.png` no se convirtieron.

La procedencia se anota aquí, en un solo archivo fuera de `public/`, en lugar
de un `.json` al lado de cada imagen: esos archivos quedarían publicados en el
sitio.

## Fotos reales de los cursos (18-11-2026)

ESAT envió por WhatsApp 42 fotos de sus cursos (`IMG-20261118-WA00NN.jpg`).
Son fotos reales de prácticas y actividades, no imágenes generadas. Se usaron 18 en 7
programas, cada una donde lo que muestra coincide con el texto del bloque. Los recortes
solo quitan marcas de agua o bordes; no se retocó nada más. Las versiones WebP se
hicieron con Pillow 12.2.0 con los mismos parámetros que las demás (calidad 74,
1920 y 960 px, sin agrandar). Ninguna lleva metadatos (EXIF ni GPS).

En los lugares de siempre (portada, proyección y práctica):

| Archivo | Original | Recorte | Dónde va |
|---|---|---|---|
| `criminalistica-escena.jpg` | WA0028 | — | Criminalística: portada |
| `criminalistica-practica.jpg` | WA0047 | — | Criminalística: práctica |
| `electricista-tablero.jpg` | WA0032 | abajo el 19 % (marca de fecha y dirección de la cámara) | Electricista: portada |
| `electromecanica-tablero-feria.jpg` | WA0039 | arriba el 27 % (letrero y globos de otro stand de la feria) | Electromecánica: portada |
| `robotica-brazo.jpg` | WA0046 | 2,5 % a la izquierda y 17 % a la derecha (un celular y una bolsa al borde) | Técnico en Robótica: práctica |
| `veterinaria-zootecnia-estudiantes.jpg` | WA0041 | — | Asistente de Veterinaria y Zootecnia: portada (el bordado del uniforme dice "Veterinaria… Zootec…") |
| `transito-practica-via.jpg` | WA0016 | — | Agente de Tránsito: práctica (reemplaza `transito-aprender`) |
| `preescolar-rincon-lectura.jpg` | WA0010 | abajo el 9,5 % (marca "REDMI Note 13", nombre y fecha) | Asistente de Preescolar: proyección "Jardines infantiles" (reemplaza `preescolar-1`) |

En la galería "Nuestros estudiantes" del programa (`galeria` en `config/contenido.php`), sin recortes
(el encuadre lo da `object-position`):

| Archivo | Original | Programa |
|---|---|---|
| `criminalistica-escena-equipo.jpg` | WA0009 | Criminalística |
| `electricista-montaje-tablero.jpg` | WA0051 | Electricista |
| `electromecanica-stand-tablero.jpg` | WA0033 | Electromecánica |
| `transito-estudiantes-grupo.jpg` | WA0042 | Agente de Tránsito |
| `transito-salida-grupo.jpg` | WA0043 | Agente de Tránsito (salida con Veterinaria y Zootecnia) |
| `transito-agentes-via.jpg` | WA0027 | Agente de Tránsito |
| `transito-campana-vial.jpg` | WA0036 | Agente de Tránsito |
| `transito-agente-via.jpg` | WA0052 | Agente de Tránsito |
| `veterinaria-zootecnia-grupo.jpg` | WA0022 | Asistente de Veterinaria y Zootecnia |
| `veterinaria-zootecnia-salida.jpg` | WA0023 | Asistente de Veterinaria y Zootecnia (salida con Agente de Tránsito) |

Tablero eléctrico: no hay forma de saber si las fotos son de Electricista o de Electromecánica (los dos
usan esos tableros); se repartieron entre ambos y falta que ESAT lo confirme.

Las 24 que no se usaron, y por qué:

- Con niños reconocibles (WA0015, WA0017, WA0021): falta confirmar que ESAT tiene la autorización
  de sus padres o acudientes para publicarlas.
- Uniforme turquesa de salud (WA0024, WA0026, WA0031, WA0034, WA0045) y la atención a adultos
  mayores (WA0049): el bordado parece decir "Aux. Servicios Geriátricos", un programa que ya no se
  ofrece. Falta que ESAT diga a qué programa de hoy corresponden.
- Casi iguales a una usada en la misma página: WA0037 (= WA0032), WA0035 (= WA0022), WA0044 (= WA0043).
- Poca calidad o confusas: capturas de pantalla (WA0012, WA0013, WA0019), una foto movida en la que no se
  entiende qué pasa (WA0038) y dos de la práctica en el río donde lo que más se ve es a personas sin camisa
  (WA0014, WA0018).
- No son de un curso: celebraciones (WA0040, WA0048), selfies o fotos de oficina (WA0020, WA0029, WA0050)
  y una clase sin programa identificable (WA0025).
