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
