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
- Grupos = tabla `cursos` (`nombre` = "Grupo A"). Modalidad y cupo NO se
  guardan en el grupo: son siempre los del programa (se editan en Programas).
  El programa no tiene fecha de inicio: la tiene cada grupo, y su `fecha_fin`
  no se escribe, es `Curso::finPara()` = inicio + duración del programa (si
  cambia la duración del programa se recalculan sus grupos). Editar un
  programa no puede invalidar sus grupos vigentes (cupo, jornadas, horas).
  Docente vía `curso_docente`
  (uno por grupo); horario semanal en `curso_sesiones`. Las reglas de horario
  (tope de horas del programa, cruces de docente) viven en
  `App\Services\Academico\HorarioService` para que Grupos y Horarios usen las mismas.
- Backend real hecho: Superadmin (Sedes, Administradores) y Admin
  (Programas y Escuelas, Docentes, Grupos, Horarios, Estudiantes, Acudientes, Matrículas, Asistencia). El resto del panel Admin sigue siendo maqueta.
- **Estudiantes ≠ Matrículas.** Estudiantes (`Admin/Estudiantes/EstudianteController` +
  `EstudianteService`) solo registra/edita a la **persona**: cuenta (rol estudiante,
  **correo obligatorio** = usuario, clave = documento), acudiente (rol acudiente,
  `acudiente_estudiante` con parentesco; obligatorio si es menor), lista de documentos
  (`estudiante_documentos`, del estudiante, `EstudianteDocumento::REQUERIDOS`) y restablecer
  clave. **No matricula.** Matrículas = programa + grupo (`curso_estudiante`),
  cambio de grupo, estado de la matrícula (activo/aplazado/retirado/graduado con fecha y
  motivo) y pagos. **Sin todos los documentos requeridos entregados no se matricula**
  (`EstudianteDocumento::pendientesDe`, en frontend y en `MatriculaRequest`). En la ficha del estudiante la matrícula es solo consulta.
  `matriculas.plan_pago` queda null: lo **elige el estudiante desde su panel**.
- **Acudientes** (`Admin/Acudientes/AcudienteController` + `AcudienteService`): cuenta rol
  acudiente (**correo obligatorio** = usuario, clave = documento, ocupación en `users.profesion`),
  residencia con el combobox de Colombia, estudiantes a cargo en `acudiente_estudiante`.
  **Un solo acudiente por estudiante** (vincular reemplaza al anterior) y **ningún menor de edad
  queda sin acudiente** (no se desvincula ni se quita al editar; se cambia vinculándole otro).
  Estado de cuenta: `activo=0` → inactiva; `ultimo_acceso` null → "nunca ha ingresado".
- **Matrículas** (`Admin/Matriculas/MatriculaController` + `PagoController`, `Academico/MatriculaService`,
  `Finanzas/PagoService`): la matrícula **guarda su grupo** (`matriculas.curso_id`; `curso_estudiante`
  sigue siendo la lista del grupo y se mantiene sincronizada). Una matrícula **vigente** (activa o
  aplazada) por estudiante; el cupo se revisa con el grupo bloqueado. Estados por
  `Matricula::TRANSICIONES` (activo → aplazado/retirado/graduado; aplazado → activo/retirado;
  retirado y graduado son finales). Aplazar/retirar anula las cuotas futuras; reintegrar las
  regenera; graduar exige grupo terminado y paz y salvo.
- **Pagos**: cada fila de `pagos` es un cobro (`matricula` = derecho de matrícula, o cuota
  `mensual`/`periodo` con `numero_cuota` y `fecha_vencimiento` el día `Pago::DIA_VENCIMIENTO`=10).
  Lo que se paga se registra en recaudos (ver abajo). `vencido` lo calcula `Pago::estadoActual()` por
  fecha y lo guarda el comando diario `pagos:vencer`. El resumen de pagos (Matrículas, Estudiantes y
  Acudientes) sale solo de `PagoService::resumen()`. El historial de una matrícula es la auditoría
  con `entidad_tipo = 'Matricula'` (registrar ahí también los pagos).
  - **Pagos = recaudos** (`Finanzas/Recaudo`, tabla `recaudos` + pivote `recaudo_pago.monto`): la
    admin escribe un **monto libre** y `PagoService::registrarPago` lo aplica **en orden** (lo más viejo
    primero; el derecho en espera al final): completa cobros y lo que sobra queda **abonado**
    (`pagos.abonado`, `Pago::restante()`). Un recibo por recaudo (`RC-AAAA-NNNNN`, imprimible en
    `admin.matriculas.recibo`). Anular un pago anula el recaudo entero (`anularRecaudo`): devuelve lo
    abonado y el recibo queda marcado como anulado. No se paga más de lo que se debe. Lo atrasado se
    acumula con lo del mes ("A pagar este mes" = vencido + del mes).
  - **Descuento** (beca/convenio): `matriculas.descuento_pct` + motivo; rebaja solo el valor del
    programa (`pagos.descuento`; no el derecho ni la cuota del sistema), en las cuotas **sin abonos** y
    en las que se generen después. 0 lo quita.
  - **Anular matrícula** (estado `anulado`, por `TRANSICIONES`, con motivo): solo si no tiene recaudos
    vigentes; anula sus cobros, sale de la lista del grupo, no cuenta en trayectoria ni en la
    matrícula que muestran Estudiantes/Acudientes; queda en Finalizadas. El estudiante vuelve a
    "Por matricular".
  - **Derecho de matrícula**: vence al iniciar clases del grupo (`vencimientoDerecho`). Desde ese día,
    si no está pagado, **el estudiante no puede ingresar** (`LoginRequest` + `derechoVencido`); sin
    matrícula o antes de vencer sí entra. Al **aplazar** sin haberlo pagado queda **en espera**
    (`fecha_vencimiento` null) y vence al iniciar el grupo al que se reintegre; si ya lo pagó se le
    guarda. Al **retirarse** antes de que venza, se anula. (Cuando exista el panel Estudiante,
    aplicar la misma regla a las sesiones abiertas, no solo al login.)
  - **Graduar** exige grupo terminado, plan elegido con todas sus cuotas pagadas y paz y salvo.
    Los pagos de una matrícula graduada no se anulan.
  - Un cambio de estado no puede tener fecha anterior a la matrícula ni al último cambio de estado.
  - El consecutivo de recibos vive en `configuraciones` (`ultimo_recibo_AAAA`) y nunca retrocede:
    un recibo anulado no se reutiliza.
  - Si cambia la fecha de inicio del grupo (cambio de grupo o edición en Grupos), el derecho de
    matrícula sin pagar vence el nuevo día de inicio (`reprogramarDerecho`).
  - **Dinero adelantado** (`PagoService::adelantado`): en una matrícula aplazada o retirada, las cuotas
    pagadas que vencen después del cambio y los abonos que quedaron en cobros anulados. Siempre se
    usa **primero para cubrir lo vencido** de la misma matrícula; el resto:
    - **Devolución** (`registrarDevolucion`, tabla `devoluciones`, comprobante `DV-AAAA-NNNNN`).
    - **Cambio de programa** (`MatriculaService::cambiarPrograma`, opción de "Cambiar estado"): la vieja
      queda retirada ("Cambio de programa a…"), la nueva guarda `origen_id`; el derecho no se cobra otra
      vez si ya lo pagó; lo adelantado pasa a `matriculas.saldo_favor` de la nueva.
    - **Reintegro**: lo abonado a cuotas anuladas vuelve como saldo a favor.
    El saldo a favor se aplica solo, en orden, cuando hay cobros (`aplicarSaldoFavor`, también al generar
    cuotas). `pagos.reembolsado` = lo que salió de ese cobro; lo que realmente queda pagado es
    `Pago::disponible()` (abonado − reembolsado). Un recaudo con dinero ya devuelto/trasladado no se anula.
  - **Documentos imprimibles** (`DocumentoController`, plantilla `admin/matriculas/documento.blade.php` +
    `documento.css/js`): recibo, comprobante de devolución, constancia de matrícula (solo vigente) y paz y
    salvo (nada por pagar en ninguna de sus matrículas y ninguna vigente sin plan).
- **Asistencia** (`Admin/Asistencia/AsistenciaController` + `Academico/AsistenciaService`): el Admin **solo
  consulta**; la asistencia la toma el **docente** (panel Docente, pendiente) y es **informativa: no bloquea
  nada** (ni grados ni matrículas; 80 % es solo referencia). Clases = días del horario entre el inicio del grupo
  y su fin (la de hoy cuenta cuando termina). Clase **sin registrar** = clase dictada sin ningún registro del
  grupo; no cuenta en el %. % = (presente + tarde) / (presente + tarde + ausente): la excusa no cuenta como falla
  (`AsistenciaService::porcentaje`, la misma regla en las fichas de Estudiantes/Acudientes/Matrículas). De cada
  estudiante cuentan las clases desde su matrícula hasta que se aplazó/retiró. `asistencias.motivo` (excusa) y
  `registrado_por` (docente). Pruebas en `tests/Feature/Admin/AsistenciaTest.php`.
- **Calificaciones** (`admin/calificaciones/index.blade.php` + `calificaciones.css/js`): **maqueta**
  (solo frontend, `Route::view`, datos de ejemplo en el `@php` de la vista). Nota **por módulo** de la malla
  (`programa_modulos`), escala 1.0–5.0, aprueba 3.0; la pone el **docente**, el Admin **solo consulta** (no
  corrige). Una sola **habilitación** por módulo perdido. Las fechas de cada módulo se **calculan**: las clases
  del grupo (horario, inicio → fin) se reparten en orden según las horas de cada módulo. Graduar exigirá todos
  los módulos aprobados. Certificado de notas imprimible dentro de la misma página.
- **Exportar a Excel**: Matrículas, Docentes, Estudiantes y Acudientes tienen un botón "Exportar" que
  descarga **lo que se ve con los filtros** como CSV (`;`, BOM UTF-8 para las tildes) armado en el
  navegador (`exportar()` + `celdaCsv()` en el JS de cada página; neutraliza celdas que empiezan con
  `= + - @` para que Excel no las tome como fórmulas). Al agregarlo a otra pantalla, copiar ese patrón.
  - La app corre en hora de Colombia (`config/app.php` → `America/Bogota`).
- **Trayectoria**: un estudiante puede tener varias matrículas en el tiempo (se gradúa o se retira y
  vuelve con una matrícula nueva; la vieja queda como historial). `EstudianteService::trayectoria()`
  las lista (ficha de Estudiantes › Matrícula › "Trayectoria en ESAT"); en Matrículas la ficha muestra
  "Otras matrículas de X". Matrículas tiene 3 pestañas: vigentes (activas/aplazadas), Por matricular
  (nunca matriculados) y Finalizadas (graduadas/retiradas, solo consulta).
- **Login** (`LoginRequest`): una cuenta con `activo=0` no puede ingresar, y cada ingreso
  guarda `users.ultimo_acceso`.
- `EstudianteService::matriculaVigente()` / `grupoDeMatricula()`: matrícula y grupo que se
  muestran de un estudiante (los usan Estudiantes y Acudientes; no duplicar en controladores).
- **Cupos**: solo las matrículas activas ocupan cupo → usar `Curso::estudiantesActivos()`
  / `cuposLibres()`, nunca `estudiantes()->count()`. Un grupo con estudiantes no
  puede cambiar de programa.
- Horarios (`Admin/Horarios/HorarioController`): `PUT /admin/horarios/{curso}`
  reemplaza las sesiones del grupo y su docente. "Nuevo horario" solo ofrece
  grupos sin sesiones; los finalizados no se listan ni se editan.
- **Lo definido arriba se respeta abajo** (Programa → Grupo → Horario): la
  jornada del horario es la del grupo (`Curso::RANGOS_JORNADA`: Mañana 6–12,
  Tarde 12–18, Noche 18–24, Fines de semana solo sábado; sin horarios
  "personalizados" por fuera de la jornada), nunca se deduce de
  las horas; validar en frontend y en servidor (`HorarioService::fueraDeJornada`).
  Y al revés: un cambio arriba no puede invalidar lo de abajo (cambiar jornada
  o duración, reabrir un grupo, desactivar un docente con grupos vigentes).

## Pruebas automáticas

`php artisan test` (SQLite en memoria, no toca la BD `esat`). `tests/TestCase.php`
registra `FIELD()` para SQLite. Las reglas de horarios (jornada, tope, cruces,
y lo que las protege desde Grupos/Docentes/Programas) están en
`tests/Feature/Admin/HorariosTest.php`: al cambiar una regla, correrlas; al
agregar una regla nueva, agregar su prueba. `UserFactory` usa nombres/apellidos
(no hay columna `name`).

## Bug recurrente a tener en cuenta

Cuando un elemento se oculta/muestra con el atributo `hidden` (JS:
`el.hidden = true/false`), y ese mismo elemento tiene una regla CSS propia
con `display: flex/grid/...`, esa regla **pisa** el `[hidden]` nativo del
navegador (el `display` de un autor gana sobre el `display:none` del
user-agent). Ya pasó varias veces (paginación, lista de combobox, panel de
combobox). Siempre que se le ponga `display:` a algo que también se oculta
vía `hidden`, agregar explícitamente `.clase[hidden] { display: none; }`.

Otro del combobox: se recoloca con `window.addEventListener('scroll', …, true)`,
que también captura el scroll de **su propia lista**; como `posicionar()`
reinicia `list.style.maxHeight`, la lista volvía al inicio y no dejaba bajar.
El listener debe ser `alScroll(e)`, que ignora `e.target` dentro del panel.
Al copiar el combobox a otra pantalla, copiar también `alScroll`.

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
