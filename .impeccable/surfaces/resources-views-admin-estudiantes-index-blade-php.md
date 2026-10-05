---
version: 1
slug: "resources-views-admin-estudiantes-index-blade-php"
primary_target: "resources/views/admin/estudiantes/index.blade.php"
related_targets: []
---

# Admin · Estudiantes

Scope: panel Admin (rol admin de una sede), pantalla Estudiantes. Mode: Operate.
Audience: secretaría/administración de la sede. Job: inscribir estudiantes (con acudiente si es menor), ubicar a uno rápido, ver su situación y actuar (estado, grupo, contraseña).
Constraints: solo frontend con datos mock; sistema visual existente del panel Admin (layout.css); CSS/JS propios de la página; todo en español.
Content confirmed with user: inscribir (asistente 5 pasos), ficha, cambiar estado, cambiar de grupo, restablecer contraseña en la ficha; lista muestra programa/grupo, estado, alertas, contacto rápido.

## Direction contract

THESIS: La tabla (mismo formato que Docentes) es la vista principal por decisión del usuario; cada estudiante tiene además su carné de ESAT como vista alternativa y como encabezado de su ficha.
OWN-WORLD: Carné horizontal blanco con franja superior en el color de su escuela, iniciales en recuadro de foto, sello de estado, código estudiantil en cifras tabulares; navy #10284A, naranja #D97B2E solo para acción y selección; Archivo para nombres, Public Sans para datos.
STORY: La secretaria ve de un vistazo quién está activo, aplazado o con alertas, encuentra a alguien por nombre o documento, abre su ficha y resuelve.
FIRST VIEWPORT: Título y descripción con "Inscribir estudiante" a la derecha; pestañas de estado con conteos y franja de alertas clicables; buscador, programa, grupo y selector Tabla/Carnés (se recuerda); tabla como Docentes con escuela, programa y grupo, estado, alertas y WhatsApp.
FORM: Carnés estudiantiles, posición 6 de 7 en la lista ordenada, seed 5808e678.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
