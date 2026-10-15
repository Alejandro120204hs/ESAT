---
version: 1
slug: "resources-views-admin-asistencia-index-blade-php"
primary_target: "resources/views/admin/asistencia/index.blade.php"
related_targets: ["public/assets/css/admin/asistencia.css","public/assets/js/admin/asistencia.js"]
---

# Admin · Asistencia

Scope: panel Admin (rol admin de una sede), pantalla Asistencia. Mode: Operate.
Audience: administración de la sede. Job: supervisar la asistencia que toman los docentes (solo consulta): ver qué grupos y estudiantes faltan mucho y qué docentes no han tomado asistencia.
Constraints: solo frontend con datos mock; sistema visual del panel Admin (layout.css), estructura de Docentes; CSS/JS propios; todo en español. La asistencia es informativa: no bloquea nada. Mínimo de referencia 80 %. Tarde cuenta como asistió; la excusa no cuenta como falla.
Content confirmed with user: resumen 4 KPIs, pestañas Por grupo / Por estudiante / Por docente, detalle de grupo con planilla, historial por estudiante, Exportar.

## Direction contract

THESIS: La planilla de asistencia en papel (estudiantes × fechas) hecha pantalla; no un tablero de gráficas.
OWN-WORLD: Mismo mundo de Docentes: KPI chips, tabla blanca, cajón lateral; celdas de planilla cuadradas con letra (P T A E), verde/ámbar/rojo/azul, fechas sin tomar rayadas en naranja; Archivo + Public Sans, cifras tabulares.
STORY: El admin ve quién falta y qué docente no ha tomado lista, abre el grupo o la persona y entiende el patrón en segundos.
FIRST VIEWPORT: Título y descripción; 4 KPIs; pestañas con conteos; buscador + filtros + Exportar; tabla de grupos con medidor de % y "sin tomar".
FORM: Estructura de Docentes fijada por el usuario (coherencia entre pantallas); sin concept-seed.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
