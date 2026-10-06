# ESAT — Contexto del proyecto

Escuela Nacional de Educación ESAT (instituto técnico colombiano legalmente
licenciado — Lic. Funcionamiento 5850, NIT 900820764-1). El proyecto tiene
dos partes:

1. **Sitio público** (`resources/views/welcome.blade.php` y páginas de
   `paginas.*`) — ya construido: programas técnicos, sedes, convenios,
   educación continuada. Todos los datos legales/de contacto/sedes ahí son
   **reales**, nunca inventados.
2. **Plataforma interna** (paneles por rol) — en construcción activa, es el
   foco actual. Todo lo de abajo es sobre esta parte.

Stack: Laravel 12 + Breeze (Blade), MySQL (`esat`, XAMPP, `root` sin clave),
`php artisan serve --port=8765` para probar local. Todo en **español**,
nunca inglés, en toda la UI.

## Roles y acceso

Enum `App\Enums\RolUsuario`: `superadmin | admin | estudiante | docente | acudiente`.
Cada rol tiene **su propia carpeta** de vistas/CSS/JS
(`resources/views/{rol}/`, `public/assets/css/{rol}/`, `public/assets/js/{rol}/`).
Redirección post-login por rol vía `User::panelUrl()`.

**Convención de contraseña**: la clave inicial de cualquier cuenta creada
(admin, estudiante, etc.) es su número de documento/cédula — no se pide
contraseña en los formularios de alta, se genera sola.

## Límite de responsabilidad: Superadmin vs. Admin

- **Superadmin** = dueño del sistema. Solo le importa: estructura (sedes),
  cuentas de administrador, plata (recaudo/cartera), supervisión
  (estudiantes en modo consulta, auditoría). **No** gestiona escuelas,
  programas, asignación de programas a sede, ni periodos académicos — eso
  es trabajo del **Admin** de cada sede.
- Ese trabajo (Escuelas/Programas/Asignaciones/Periodos) ya está
  **construido y probado**, pero vive comentado dentro de
  `resources/views/superadmin/sedes-programas.blade.php` (bloque grande
  `{{-- RESERVADO PARA EL PANEL ADMIN --}}`). Cuando se construya el rol
  Admin, mover ese bloque a su propia vista/CSS/JS y descomentar, no
  reconstruir desde cero.

## Flujo de trabajo esperado

1. Antes de programar una pantalla nueva: **definir el contenido con el
   usuario primero** (dar ideas, que elija, confirmar).
2. Construir **solo frontend** primero — sin migraciones/controladores/
   persistencia real — con datos de ejemplo abundantes y realistas
   (nombres/sedes/programas reales cuando existan, el resto marcado como
   mock en un comentario Blade al inicio del archivo).
3. Todo debe **funcionar de verdad en el navegador** aunque no persista:
   pestañas, búsqueda, filtros, paginación, modales de crear/editar
   prellenados, validación — no dejar botones inertes.
4. Después de cada cambio visual: correr el detector de diseño
   (`bash .claude/skills/impeccable/scripts/impeccable detect <archivos>`,
   debe dar 0 hallazgos) y verificar con captura de Playwright antes de
   decir que quedó listo.
5. **Nunca inventar** datos legales/de contacto/reales. Los datos de
   ejemplo (precios, personas, matrículas) sí pueden ser ficticios, pero
   deben marcarse como tal en un comentario al inicio del archivo Blade.

## Convención de CSS/JS

Cada **página** dentro de un rol tiene su propio archivo, no solo cada rol:
- `layout.css` / `layout.js`: solo lo compartido por todas las páginas del
  panel (sidebar, topbar, dropdown de usuario, animaciones base).
- `dashboard.css/js`, `sedes-programas.css/js`, `administradores.css/js`,
  `estudiantes.css/js`, `auditoria.css/js`: cada uno su propio archivo,
  cargado vía `@push('styles')`/`@push('scripts')` desde su vista. Esto
  significa que hay bastante CSS/JS duplicado entre archivos (tablas,
  modales, botones) — es intencional, no "arreglar" unificándolo.

## Estructura del backend (MVC)

- **Controladores por rol y por panel**: `app/Http/Controllers/{Rol}/{Panel}/`
  (ej. `Superadmin/Sedes/SedeController`, `Admin/Programas/ProgramaController`).
  Validación en Form Requests con la misma estructura:
  `app/Http/Requests/{Rol}/{Panel}/`, mensajes en español.
- **Modelos por dominio** (no por rol, porque varios roles comparten las
  mismas tablas): `app/Models/Academico/` (Escuela, Programa,
  ProgramaModulo, Curso, Periodo, Asistencia, actividades/evaluaciones),
  `Finanzas/` (Matricula, Pago), `Institucional/` (Sede, Configuracion),
  `Sistema/` (Auditoria). `User` se queda en `app/Models/`.
- Catálogo **global** de escuelas y programas; la oferta de cada sede va en
  `programa_sede`. El admin solo ve/modifica lo ofertado en su sede.
- La **cuota del sistema** es global (`configuraciones.cuota_sistema_mensual`),
  no por programa.
- Toda acción de crear/editar/eliminar/activar/desactivar se registra con
  `Auditoria::registrar()`.
- Los datos de prueba se insertan **directo en la BD** (no seeders), sin
  marcarlos como ficticios.
- Datos propios de un rol van en una tabla de perfil 1 a 1 con `users`
  (ej. `docente_perfiles`: escuela, vinculación, estado), no como columnas
  nuevas de `users`. Título profesional = `users.profesion`, especialidad =
  `users.titulo_academico`. Residencia = `departamento_residencia` /
  `ciudad_residencia` (distinto del lugar de nacimiento).
- Backend real hecho: Superadmin (Sedes, Administradores) y Admin
  (Programas y Escuelas, Docentes). El resto del panel Admin sigue siendo maqueta.

## Bug recurrente a tener en cuenta

Cuando un elemento se oculta/muestra con el atributo `hidden` (JS:
`el.hidden = true/false`), y ese mismo elemento tiene una regla CSS propia
con `display: flex/grid/...`, esa regla **pisa** el `[hidden]` nativo del
navegador (el `display` de un autor gana sobre el `display:none` del
user-agent). Ya pasó varias veces (paginación, lista de combobox, panel de
combobox). Siempre que se le ponga `display:` a algo que también se oculta
vía `hidden`, agregar explícitamente `.clase[hidden] { display: none; }`.

## Combobox propio (departamento/ciudad de Colombia)

El `<select>` nativo no se puede estilizar ni controlar hacia dónde abre.
Se construyó un combobox propio (buscador + lista, `position: fixed`,
siempre abre hacia abajo, calcula el espacio disponible en pantalla para
no salirse del viewport) usando un dataset real de departamentos/ciudades
de Colombia vendido en `public/assets/data/co-departamentos-ciudades.json`
(32 departamentos, cientos de municipios reales). Ya implementado en
`sedes-programas.js` (Sedes) y `administradores.js` (lugar de nacimiento).
Si se necesita en otra pantalla, copiar el patrón (`initCombobox` +
`poblarCiudades*`), no reinventarlo.

## Patrón de fila clicable

En las tablas de solo-editar/ver (Sedes, Administradores, Estudiantes,
Auditoría), toda la fila abre el modal/asistente correspondiente, no solo
el ícono. Cuidado: si una fila tiene además un control propio (como el
interruptor activo/inactivo en Administradores), ese control debe llamar
`e.stopPropagation()` para no disparar también el click de la fila.

## Estado actual del panel Superadmin (todas construidas, solo frontend)

- **Inicio** (`dashboard.blade.php`): recaudo del mes + gráfica con tooltip
  al pasar el cursor, estado de cartera, alertas paginadas (4 por página),
  actividad reciente, franja de sedes/escuelas/programas/administradores.
- **Sedes** (`sedes-programas.blade.php`, ruta `/superadmin/sedes`): CRUD
  de sedes (nombre, departamento, ciudad, dirección) con buscador +
  paginación + modal crear/editar. Las 4 sedes reales: Bogotá, Sasaima,
  Guaduas, La Dorada.
- **Administradores** (`administradores.blade.php`): tabla + asistente de
  4 pasos (Datos personales → Identificación → Contacto → Sede y
  confirmación), con edad calculada en vivo desde la fecha de nacimiento
  (nunca guardada como número fijo), validación por paso, resumen final.
  3 administradores de ejemplo — La Dorada queda sin admin a propósito
  (coincide con la alerta del inicio).
- **Estudiantes** (`estudiantes.blade.php`): **solo consulta**, sin
  crear/editar. Resumen (total/al día/pendiente/vencido) + tabla con
  filtros (sede/programa/estado de pago) + detalle de historial de pagos
  por estudiante. 24 estudiantes de ejemplo.
- **Auditoría** (`auditoria.blade.php`): **solo consulta**. Log de
  acciones de administradores (crear/editar/eliminar/activar/desactivar)
  con filtros (administrador/tipo/periodo) + detalle por evento. 24
  eventos de ejemplo.
- **Configuración**: todavía no construida ("Pronto" en el sidebar).
- Layout compartido (`superadmin/layout.blade.php` +
  `superadmin/partials/{sidebar,topbar}.blade.php`): sidebar con el logo
  real de ESAT (ícono + wordmark, `logo-negativo.png`) fijo (no se mueve
  al hacer scroll — solo `.app-content` scrollea), topbar con dropdown de
  usuario (perfil real conectado a `route('profile.edit')` + cerrar
  sesión).

## Pendiente

- Roles Admin, Estudiante, Docente, Acudiente: sin construir. Admin
  reutiliza el bloque comentado en Sedes (ver arriba).
- Configuración (Superadmin): sin construir.
- Backend real para todo lo anterior: no hay controladores/validación real
  para ninguna de las pantallas del panel Superadmin todavía — todo es
  maqueta funcional en frontend con datos de ejemplo.
