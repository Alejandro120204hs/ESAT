# ESAT — Contexto del proyecto

Escuela Nacional de Educación ESAT (instituto técnico colombiano legalmente
licenciado — Lic. Funcionamiento 5850, NIT 900820764-1). El proyecto tiene
dos partes:

1. **Sitio público** (inicio, programas y escuelas) — rediseñado
   en noviembre de 2026 (ver "Sitio público" abajo). Todos los datos legales/de
   contacto/sedes ahí son **reales**, nunca inventados.
2. **Plataforma interna** (paneles por rol) — en construcción activa. Casi todo lo de
   abajo es sobre esta parte.

## Sitio público

- **Datos, no vistas sueltas.** `config/sitio.php`: escuelas (con su foto y sus programas
  `[nombre, slug]`), sedes (las 4 del afiche del 19-11-2026: Sasaima, Guaduas, La Dorada y Marquetalia, con dirección, coordenadas y
  teléfono de WhatsApp de cada una; también forman la cinta de Convenios; **sin sedes de franquicia**: se quitaron
  porque el afiche no las tiene), WhatsApp y
  redes (vacías hasta tener las URL reales). `config/contenido.php`: el texto de cada programa
  (`programas.{slug}`). Header,
  footer, inicio y subpáginas leen de ahí: un programa o una sede se agrega en esos archivos,
  no en la vista.
- **Rutas**: `routes/web.php` genera una ruta por programa (`/{slug}`, nombre `programas.{slug}`),
  y por escuela (`/escuelas/{slug}`, `escuelas.{slug}`), todas a
  `Sitio/PaginaController`. El catálogo es el del afiche "Carreras técnicas" (16-11-2026: 8 escuelas, 41 programas);
  las direcciones de programas y escuelas que cambiaron o salieron redirigen (301) con `sitio.redirecciones`. **Educación Continuada se quitó del sitio** (17-11-2026, lo pidió el usuario): sus 7 direcciones viejas llevan al inicio. Plantillas: `paginas/{programa,escuela}.blade.php` y
  `paginas/partes/{hero,barra,migas,respaldo}`. El inicio es `welcome.blade.php`.
- **Fotos**: `public/assets/img` tiene el original y su `.webp` + `-960.webp` (procedencia en
  `.impeccable/procedencia-imagenes.md`). En las vistas, `$foto('archivo.png')` devuelve
  `src`/`srcset` del WebP o null si la foto no existe; sin foto, la página se arma con el ícono de
  la escuela (nunca un aviso de "foto pendiente"). Un programa sin foto propia usa otra foto
  real del mismo programa (`PaginaController::conPortada`). Un programa puede tener además una `galeria` (fotos reales que envió ESAT,
  cada una con su descripción y su encuadre) que sale después de la práctica como "Nuestros estudiantes"; sin `galeria`
  la sección no aparece.
- **CSS/JS** en `public/assets/{css,js}/sitio/`: `base.css` + `motor.js` en todas las páginas,
  `inicio.*` en el inicio y `subpaginas.*` en programas, líneas y escuelas. Se cargan con
  `$recurso('ruta')` (versión por fecha del archivo). Librerías por CDN con versión exacta:
  GSAP 3.13 + ScrollTrigger, Lenis 1.3.4 y Leaflet 1.9.4 (sin Bootstrap ni AOS).
- **Movimiento** (`motor.js`, apagado con `prefers-reduced-motion`): `data-revelar`
  (`subir|escalonar|mascara|lado`, `data-retraso`), `data-parallax` (+ `data-parallax-marco`) y
  `data-contar`. El contenido es visible sin JS; las animaciones solo parten de ese estado. Un
  disparador creado antes de una sección fijada (la pista de Nuevas carreras) necesita
  `ScrollTrigger.sort()` + `refresh()` (ya está al final de `inicio.js`).
- **Footer**: su cierre (`#contacto`) es la invitación a escribir por WhatsApp; una página lo
  cambia con `@section('cierre_titulo' | 'cierre_texto' | 'cierre_tema' | 'cierre_boton')`.
- **Diseño**: el sistema visual está en `DESIGN.md`; el contrato de dirección, como comentario al
  inicio del `<body>` en `layouts/app.blade.php`.
- **Pendiente de ESAT**: cifras de estudiantes y egresados, foto de cada sede y la dirección de Marquetalia (las 4 sedes y sus teléfonos salen del afiche del 19-11-2026; Bogotá ya no está), URL de
  redes, fotos de 12 programas (los que salen solo con el ícono de su escuela) y decidir el formulario de Novedades (hoy solo muestra
  la confirmación, no guarda el correo). Las fotos reales que envió ESAT el 18-11-2026 (WhatsApp) y cuáles se usaron, con
  sus recortes y por qué no las otras, están en `.impeccable/procedencia-imagenes.md`. Antes de publicar una foto con niños
  reconocibles, ESAT debe confirmar la autorización de sus padres o acudientes.

Stack: Laravel 12 + Breeze (Blade), MySQL (`esat`, XAMPP, `root` sin clave),
`php artisan serve --port=8765` para probar local. Todo en **español**,
nunca inglés, en toda la UI. Instalación nueva: README (`.env.example` con MySQL `esat` y español,
`composer setup` y `php artisan db:seed`, que crea las 4 sedes reales y las cuentas Superadmin y Admin desde
`SUPERADMIN_*` / `ADMIN_GENERAL_*`; es lo único que siembra: los datos de prueba siguen yendo directo a la BD).
**Nunca deshacer migraciones** con datos: `AppServiceProvider::protegerMigraciones` lo niega (A12); se vuelve atrás
restaurando un respaldo. **Nada de dinero ni historial se borra**: la BD rechaza (RESTRICT) borrar un estudiante con
matrícula, una matrícula con cobros/recibos, y una cuenta que registró recibos, notas, asistencia o auditoría (B35); las
cuentas se desactivan. `database/esat.sql` es un volcado de la BD local con
datos personales, hashes y sesiones: **no subirlo a Git**.

Los problemas abiertos de la revisión completa (21-10-2026) están en `HALLAZGOS.md`,
con IDs (C1, A3, M2…) para referenciarlos en commits y PR.

## Roles y acceso

Enum `App\Enums\RolUsuario`: `superadmin | admin | admin_general | estudiante | docente`. **No hay rol acudiente** (quitado el
25-11-2026, lo pidió el usuario): cada estudiante tiene un **contacto de emergencia** (ver Estudiantes).
**El rol `admin` es el Administrativo de cada sede** (antes "Secretaría"; renombrado el 25-11-2026, lo pidió el usuario):
en pantalla siempre se llama "Administrativo" (Admin › Administrativos, el rol bajo el nombre, el foro y los comunicados
"como administrativo"); el lugar o la oficina es "la administración de la sede" ("pídelo en la administración de tu sede",
firmas y remitente "Administración de la sede"). "Secretaría de Educación" (la entidad del gobierno, en Reportes) no cambia.
Por dentro (enum, rutas `/general/secretarias`, carpetas, variables y comentarios del código) sigue siendo `admin` /
`secretaria`: en este archivo "secretaría" quiere decir ese rol. Lo ya registrado en la auditoría conserva "secretaría".
Hace todo lo del panel.
**"Programa" en pantalla se llama "Carrera"** (decidido con el usuario el 25-11-2026; la escuela sigue siendo
"Escuela"): menú, títulos, tablas, filtros, mensajes de validación y textos de la auditoría dicen carrera; por dentro
(modelo `Programa`, tablas, rutas `/programas`, variables y claves JSON) sigue siendo `programa`. Los **documentos impresos**
siguen diciendo "programa" (término oficial de la formación técnica laboral): constancia, paz y salvo, recibo
(`documento-estudiante`), certificado de notas, boletín del estudiante y Reportes. Al agregar texto, escribir "carrera".
"Módulo" es solo de la malla (no usarlo para la escuela). La pantalla del catálogo se llama **"Escuelas y carreras"** (menú del Admin y de
la secretaría): pestaña **Escuelas primero** (se crean antes que sus carreras) y luego Carreras; abre en Escuelas y, tras
guardar, vuelve a la pestaña en la que estaba (`sessionStorage`). Lo registrado antes en la auditoría queda con "programa".
**El rol `admin_general` es el Admin de toda ESAT** (en pantalla "Admin"; una sola cuenta, sin sede). El
**Superadmin es el creador del sistema** (el desarrollador). Decidido con el usuario: el Admin ve todo ESAT
consolidado con filtro por sede, **también actúa** en las sedes ("Entrar a la sede": las mismas pantallas de la
secretaría con una franja de la sede elegida, sin duplicarlas; todo a su nombre en la auditoría), y **lo del
Superadmin pasa al Admin** (sedes, secretarías, inicio, estudiantes, auditoría, configuración); al Superadmin le
queda crear la cuenta del Admin y lo técnico. Panel en construcción (ver "Panel Admin general").
Cada rol tiene **su propia carpeta** de vistas/CSS/JS
(`resources/views/{rol}/`, `public/assets/css/{rol}/`, `public/assets/js/{rol}/`).
Redirección post-login por rol vía `User::panelUrl()`. Superadmin, admin, docente y estudiante
tienen panel (ver "Panel Docente" y "Panel Estudiante"). La secretaría, el Admin, el superadmin,
el estudiante y el docente tienen su propio perfil (`estudiante/perfil` y `docente/perfil`, desde `ProfileController@edit`; ninguno de los dos cambia su nombre). **Nadie elimina su propia
cuenta** (no hay `DELETE /profile`: borraría en cascada matrículas, pagos y recibos).
El teléfono, también el de `/profile`, se guarda solo con dígitos y debe ser un celular
(`^3\d{9}$`).

**Convención de contraseña**: la clave inicial de cualquier cuenta creada
(admin, estudiante, etc.) es su número de documento/cédula — no se pide
contraseña en los formularios de alta, se genera sola.

## Límite de responsabilidad: Superadmin vs. Admin

- **Superadmin** = dueño del sistema. Solo le importa: estructura (sedes),
  cuentas de administrador, plata (recaudo/cartera), supervisión
  (estudiantes en modo consulta, auditoría). **No** gestiona escuelas,
  programas, asignación de programas a sede, ni periodos académicos — eso
  es trabajo del **Admin** de cada sede.
- Escuelas y Programas del Admin se **reconstruyeron** en
  `admin/programas/index.blade.php` (+ `programas.css/js`, `escuelas.css/js`), con
  backend real. La asignación a sede es implícita al crear el programa
  (`ProgramaController`). Asignaciones y Periodos académicos no existen en el Admin.
  El bloque `{{-- RESERVADO PARA EL PANEL ADMIN --}}` de
  `superadmin/sedes-programas.blade.php` (y su JS/CSS asociado) quedó como código
  muerto: no moverlo ni descomentarlo.

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

## Diseño de los paneles (regla general)

**Todo panel por rol sigue `DESIGN-PANELES.md`.** `DESIGN.md` es solo
del sitio público y no se usa en los paneles (los avisos `design-system-*` del detector comparan con él: no aplican aquí).
- **Partir del panel Docente**, que es la referencia viva: copiar `docente/layout.blade.php` + `partials/{sidebar,topbar}`,
  `layout.css` y `layout.js` a la carpeta del rol, y copiar los componentes de una pantalla parecida (tabla con filtros,
  ficha en cajón, planilla con barra de guardar, horario semanal) antes de inventar uno nuevo.
- **Contenido**: cada cifra aparece una sola vez por pantalla y ningún elemento está "porque sí"; título + una línea de
  explicación, sin antetítulos; en las pantallas de trabajo, un resumen en una línea en vez de filas de tarjetas de
  cifras; un solo botón naranja por bloque, y el número de pendientes dentro del botón que los resuelve.
- **Color con significado** (aplicado a Docente, Secretaría y Admin el 11-11-2026 sin quitar ningún elemento,
  comprobado con un inventario de antes y después de las 31 pantallas): naranja = acción o pendiente, rojo = riesgo,
  verde = bien (el del panel: `#2D7A3A`/`#2D6A27`/`#E6F4E4`, no esmeralda), gris/azul de la marca = neutro (`#EEF2F7` +
  `--navy-2`/`--navy-3`). Las tarjetas de cifras llevan color solo si la cifra es un pendiente, un riesgo o algo bueno;
  escuela, jornada, vinculación y categorías no llevan color (avatares `#E6ECF4` + `--navy-2`). Excepciones, porque el
  color codifica datos: el horario semanal (`hor-c-*`, `ini-c-*`), la paleta de sedes (`--sede-*`), las leyendas de
  gráficas de Contabilidad/Pagos, P/T/A/E de la asistencia, la categoría "Urgente" y los roles del foro. Los Inicios de
  la Secretaría y del Admin usan el encabezado claro del Docente ("Hola, …"), no la franja azul.
- Al cambiar algo general del diseño de un panel, actualizar `DESIGN-PANELES.md` en el mismo cambio.

## Convención de CSS/JS

Cada **página** dentro de un rol tiene su propio archivo, no solo cada rol:
- `layout.css` / `layout.js`: solo lo compartido por todas las páginas del
  panel (sidebar, topbar, dropdown de usuario, animaciones base).
- `dashboard.css/js`, `sedes-programas.css/js`, `administradores.css/js`,
  `estudiantes.css/js`, `auditoria.css/js`: cada uno su propio archivo,
  cargado vía `@push('styles')`/`@push('scripts')` desde su vista. Esto
  significa que hay bastante CSS/JS duplicado entre archivos (tablas,
  modales, botones) — es intencional, no "arreglar" unificándolo.
- Colores, componentes y reglas de contenido: ver "Diseño de los paneles" arriba y `DESIGN-PANELES.md`.

## Estructura del backend (MVC)

- **Controladores por rol y por panel**: `app/Http/Controllers/{Rol}/{Panel}/`
  (ej. `Superadmin/Sedes/SedeController`, `Admin/Programas/ProgramaController`).
  Validación en Form Requests con la misma estructura:
  `app/Http/Requests/{Rol}/{Panel}/`, mensajes en español.
- **Modelos por dominio** (no por rol, porque varios roles comparten las
  mismas tablas): `app/Models/Academico/` (Escuela, Programa,
  ProgramaModulo, Curso, CursoSesion, DocentePerfil, EstudianteDocumento,
  Asistencia, Calificacion, PeriodoAcademico —sin uso todavía— y el Aula virtual: Evaluacion, Pregunta, Opcion,
  RespuestaEstudiante, Actividad, Entrega), `Finanzas/` (Matricula, Pago, Recaudo, Devolucion),
  `Institucional/` (Sede, Configuracion), `Sistema/` (Auditoria). `User` se queda
  en `app/Models/`.
- Catálogo **global** de escuelas y programas; la oferta de cada sede va en
  `programa_sede`. El admin solo ve/modifica lo ofertado en su sede.
- La **cuota del sistema** es global (`configuraciones.cuota_sistema_mensual`),
  no por programa.
- Toda acción de crear/editar/eliminar/activar/desactivar se registra con
  `Auditoria::registrar()`.
- **Concurrencia (M13)**: cada petición que escribe en `/admin`, `/general` y `/docente` es una transacción
  (`TransaccionEscritura`; un 4xx/5xx deshace todo, auditoría incluida) y la conexión MySQL usa `READ COMMITTED`. Toda
  regla que dependa de datos que otra persona puede cambiar a la vez se **vuelve a revisar dentro del servicio con la
  fila bloqueada** (`lockForUpdate`, orden: estudiante → matrícula → grupo → consecutivo) y se rechaza con
  `ValidationException`; la Form Request sola no basta. Una matrícula vigente por estudiante también la exige la BD
  (`matriculas.vigente_de`, columna calculada: no se escribe; al insertar por SQL, nombrar las columnas). Códigos y
  consecutivos nuevos: `Configuracion::bloquear()`. Un choque de índice único responde 409 (`bootstrap/app.php`).
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
  no se digita: se guarda calculada con `Curso::finPara()` = inicio + duración del
  programa, al guardar el grupo y al cambiar la duración del programa (se recalculan
  sus grupos **no finalizados**). Los cálculos usan `Curso::fin()`: el `fecha_fin` guardado si el grupo ya finalizó (su
  historial no cambia), si no `finPara()`. Editar un
  programa no puede invalidar sus grupos vigentes (cupo, jornadas, horas).
  Docente vía `curso_docente`
  (uno por grupo); horario semanal en `curso_sesiones`. Las reglas de horario
  (tope de horas del programa, cruces de docente) viven en
  `App\Services\Academico\HorarioService` para que Grupos y Horarios usen las mismas.
- Backend real hecho: Superadmin (Sedes, Administradores), Admin
  (Programas y Escuelas, Docentes, Grupos, Horarios, Estudiantes, Matrículas, Asistencia, Calificaciones, Pagos, Contabilidad, Comunicados, Foro, Reportes, Inicio),
  Docente (Inicio, Mis grupos, Asistencia, Notas, Aula virtual) y Estudiante (todas sus pantallas).
  Las pestañas Documentos de Programas y Docentes (que eran maqueta) se quitaron el 26-11-2026: se construyen cuando ESAT
  diga qué documentos pide de cada carrera y de cada docente.
- **Estudiantes ≠ Matrículas.** Estudiantes (`Admin/Estudiantes/EstudianteController` +
  `EstudianteService`) solo registra/edita a la **persona**: cuenta (rol estudiante,
  **correo obligatorio** = usuario, clave = documento), **contacto de emergencia** (decidido con el usuario el
  25-11-2026: tabla `contactos_emergencia`, uno por estudiante, `ContactoEmergencia` con nombre, celular `^3\d{9}$` distinto
  al del estudiante y parentesco de `ContactoEmergencia::PARENTESCOS`; **obligatorio para todos**, al registrar y al editar,
  paso 3 del asistente, `Admin/Estudiantes/ValidaContacto`, `EstudianteService::guardarContacto`; no tiene cuenta ni panel;
  **sin él no se matricula**, `MatriculaRequest`; los WhatsApp de cobros y comunicados de un **menor** van a su contacto),
  lista de documentos
  (`estudiante_documentos`, del estudiante, `EstudianteDocumento::REQUERIDOS`) y restablecer
  clave. **No matricula.** Matrículas = programa + grupo (`curso_estudiante`),
  cambio de grupo, estado de la matrícula (activo/aplazado/retirado/graduado con fecha y
  motivo) y pagos. **Sin todos los documentos requeridos entregados no se matricula**
  (`EstudianteDocumento::pendientesDe`, en frontend y en `MatriculaRequest`). En la ficha del estudiante la matrícula es solo consulta.
  `matriculas.plan_pago` queda null al matricular: lo **elige el estudiante desde su panel** (Mis pagos, una sola vez) o,
  si todavía no lo eligió, **la Secretaría desde Matrículas** ("Elegir el plan por X" en la ficha,
  `PATCH admin/matriculas/{matricula}/plan`, `Admin/Matriculas/PlanRequest`; decidido con el usuario el 24-11-2026). Los
  dos usan `PagoService::elegirPlan` (matrícula bloqueada; solo activa, con grupo y sin plan; elegido, no cambia), que
  genera las cuotas **desde el inicio de clases** (`primeraCuota`: el día 10 del mes en que empieza el grupo, o del
  siguiente si empieza después del 10; las que ya pasaron quedan vencidas) y lo registra en la auditoría de la matrícula.
- **Matrículas** (`Admin/Matriculas/MatriculaController` + `PagoController`, `Academico/MatriculaService`,
  `Finanzas/PagoService`): la matrícula **guarda su grupo** (`matriculas.curso_id`; `curso_estudiante`
  sigue siendo la lista del grupo y se mantiene sincronizada). Una matrícula **vigente** (activa o
  aplazada) por estudiante; el cupo se revisa con el grupo bloqueado. Estados por
  `Matricula::TRANSICIONES` (activo → aplazado/retirado/graduado; aplazado → activo/retirado;
  retirado y graduado son finales). Aplazar/retirar anula las cuotas futuras (también las ya pagadas, que conservan su
  abono: ese dinero queda marcado como adelantado); reintegrar las regenera; graduar exige grupo terminado y paz y salvo.
- **Precio pactado**: al matricular (y al cambiar de programa) se guarda `matriculas.precio_programa`; las cuotas se
  calculan con `Matricula::precioPrograma()`, no con el precio vigente del catálogo (un cambio de precio solo aplica a
  matrículas nuevas). Las anteriores a la columna se llenaron con el precio del programa (todas cuadraban con él).
- **Pagos**: cada fila de `pagos` es un cobro (`matricula` = derecho de matrícula, o cuota
  `mensual`/`periodo` con `numero_cuota` y `fecha_vencimiento` el día `Pago::DIA_VENCIMIENTO`=10).
  Lo que se paga se registra en recaudos (ver abajo). `vencido` lo calcula `Pago::estadoActual()` por
  fecha y lo guarda el comando diario `pagos:vencer`. El resumen de pagos (Matrículas y Estudiantes)
  sale solo de `PagoService::resumen()`. El historial de una matrícula es la auditoría
  con `entidad_tipo = 'Matricula'` (registrar ahí también los pagos).
  Plan **por periodo**: cada periodo dura lo de su tipo (trimestre 3, semestre 6) y el último lo que quede
  (`Programa::mesesDelPeriodo`; 10 meses por trimestre = 3+3+3+1); la cuota vence al empezar su periodo y cobra la cuota
  del sistema de sus meses (B28). "Cuota N de total" usa `Matricula::totalCuotas()` (la última cuota que se le generó),
  nunca la duración de hoy del programa (B29).
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
    vigentes ni dinero (`PagoService::dineroEn`: tampoco saldo trasladado de otra matrícula, A6); anula sus cobros, sale de la lista del grupo, no cuenta en trayectoria ni en la
    matrícula que muestra Estudiantes; queda en Finalizadas. El estudiante vuelve a
    "Por matricular".
  - **Derecho de matrícula**: vence al iniciar clases del grupo (`vencimientoDerecho`). **No bloquea el ingreso**
    (decidido el 24-11-2026, lo pidió el cliente; antes no lo dejaba entrar): sin pagarlo entra igual, la deuda se
    acumula como cualquier cobro vencido y no se gradúa sin paz y salvo (`derechoVencido` ya no se usa para el acceso).
    Al **aplazar** sin haberlo pagado queda **en espera**
    (`fecha_vencimiento` null) y vence al iniciar el grupo al que se reintegre; si ya lo pagó se le
    guarda. Al **retirarse** antes de que venza, se anula (con abono parcial o pagado completo, A9): de lo
    pagado se devuelve el % de Admin › Configuración (`devolucion_derecho_pct`, 50 % por defecto; aplica a los retiros desde
    que se cambia) y el resto queda en `pagos.retenido` (ESAT se lo queda: no se devuelve ni se traslada). El cambio de
    programa no retiene nada.
  - **Graduar** exige grupo terminado, todos los módulos aprobados, plan elegido con todas sus cuotas pagadas y paz y salvo.
    Un programa **sin malla** no gradúa a nadie (`EstadoRequest`; Matrículas lo avisa con `sin_malla`).
    Los pagos de una matrícula graduada no se anulan.
  - Un cambio de estado no puede tener fecha anterior a la matrícula ni al último cambio de estado.
  - El consecutivo de recibos vive en `configuraciones` (`ultimo_recibo_AAAA`) y nunca retrocede:
    un recibo anulado no se reutiliza.
  - Si cambia la fecha de inicio del grupo (cambio de grupo o edición en Grupos), el derecho de
    matrícula sin pagar vence el nuevo día de inicio (`reprogramarDerecho`).
  - **Dinero adelantado** (`PagoService::adelantado`): los abonos que quedaron en **cobros anulados** (las cuotas de
    después de un aplazamiento o retiro se anulan con su abono, y el derecho abonado de quien se retira antes de
    empezar). No depende de `estado_fecha`, así que dos cambios seguidos no lo pierden (A5). Al anular un recibo de una
    aplazada o retirada solo vuelve a ser deuda lo que ya se debía (A4). Siempre se
    usa **primero para cubrir lo vencido** de la misma matrícula; el resto:
    - **Devolución** (`registrarDevolucion`, tabla `devoluciones`, comprobante `DV-AAAA-NNNNN`).
    - **Cambio de programa** (`MatriculaService::cambiarPrograma`, opción de "Cambiar estado"): la vieja
      queda retirada ("Cambio de programa a…"), la nueva guarda `origen_id`; el derecho no se cobra otra
      vez si ya lo pagó; lo adelantado pasa a `matriculas.saldo_favor` de la nueva.
    - **Reintegro**: lo abonado a cuotas anuladas vuelve como saldo a favor.
    El saldo a favor se aplica solo, en orden, cuando hay cobros (`aplicarSaldoFavor`, también al generar
    cuotas). `pagos.reembolsado` = lo que salió de ese cobro; lo que realmente queda pagado es
    `Pago::disponible()` (abonado − reembolsado − retenido). Un recaudo con dinero ya devuelto/trasladado no se anula.
    Lo cubierto con saldo a favor **no tiene recibo**: lo que se debía en una fecha pasada (mora del Inicio, Reportes) se
    calcula con `Pago::pagosFechados()` (recibos sin anular + lo cubierto sin recibo), nunca sumando solo los recaudos. La
    cartera de un estudiante suma lo vencido de **todas** sus matrículas (también una retirada que quedó debiendo).
  - **Documentos imprimibles** (`DocumentoController`, plantilla `admin/matriculas/documento.blade.php` +
    `documento.css/js`): recibo, comprobante de devolución, constancia de matrícula (solo vigente) y paz y
    salvo (nada por pagar en ninguna de sus matrículas, **de cualquier sede**, y ninguna vigente sin plan).
- **Asistencia** (`Admin/Asistencia/AsistenciaController` + `Academico/AsistenciaService`): el Admin **solo
  consulta**; la asistencia la toma el **docente** (panel Docente) y es **informativa: no bloquea
  nada** (ni grados ni matrículas; 80 % es solo referencia). Clases = días del horario entre el inicio del grupo
  y su fin (la de hoy cuenta cuando termina). Clase **sin registrar** = clase dictada sin ningún registro del
  grupo; no cuenta en el %. % = (presente + tarde) / (presente + tarde + ausente): la excusa no cuenta como falla
  (`AsistenciaService::porcentaje`, la misma regla en las fichas de Estudiantes y Matrículas). De cada
  estudiante cuentan las clases desde su matrícula hasta que se aplazó/retiró. `asistencias.motivo` (excusa) y
  `registrado_por` (docente). Pruebas en `tests/Feature/Admin/AsistenciaTest.php`.
- **Calificaciones** (`Admin/Calificaciones/CalificacionController` + `Academico/CalificacionService`, modelo
  `Academico/Calificacion`, tabla `calificaciones`): nota **por módulo** de la malla (`programa_modulos`) y **por
  matrícula** (sigue a la matrícula si cambia de grupo), escala 1.0–5.0, aprueba 3.0 (`Calificacion::APRUEBA`); la
  pone el **docente** (`registrado_por`), el Admin **solo consulta**. Una sola **habilitación** por módulo perdido
  (`Calificacion::estado()`: aprobado · habilito · por_habilitar · reprobado). Si pierde la habilitación repite el
  módulo (con qué grupo / quién lo asigna / si cuesta: sin decidir). Las fechas de cada módulo se **calculan**
  (`fechasModulos`: las clases del grupo se reparten en orden según las horas). Un módulo terminado sin nota es
  pendiente del docente, salvo que terminara antes de la matrícula del estudiante (`no_cursado`). **Graduar exige
  todos los módulos aprobados** (`pendientesParaGraduar`, en `EstadoRequest` y aviso en Matrículas). Certificado de
  notas imprimible dentro de la misma página. Pruebas en `tests/Feature/Admin/CalificacionesTest.php`.
- **Pagos (Finanzas)** (`Admin/Pagos/CarteraController` + `Finanzas/CarteraService`, vista `admin/pagos/index.blade.php`
  + `pagos.css/js`): pestañas Por cobrar (vencido, este mes, por pagar, WhatsApp de recordatorio —a su contacto de emergencia si es
  menor—), Pagos recibidos (recibos: ver/imprimir/anular), **Resumen de caja por rango de fechas** (no por día: los
  pagos llegan por mes/periodo; por medio de pago, quién registró, días con pagos; imprimible; cada pago cuenta el día en que
  **se registró** —`registrado_en`— y cada anulación el día en que se hizo, así un periodo impreso no cambia, M25), Por programa y
  Devoluciones (solo consulta; se registran en Matrículas). KPI de estudiantes sin plan. **Registrar y anular un pago
  usan las rutas de Matrículas** (`matriculas.pagos.store` / `matriculas.pagos.anular`, `PagoRequest` /
  `AnularPagoRequest` + `PagoService`): una sola regla de cobro y el historial queda en la matrícula. Con
  transferencia/Nequi/Daviplata/Tarjeta la referencia es obligatoria. `anulable` del recibo sigue `AnularPagoRequest`.
  Pruebas en `tests/Feature/Admin/PagosTest.php`.
- **Contabilidad (Finanzas)** (`Admin/Contabilidad/ContabilidadController` + `EgresoController`, `Finanzas/ContabilidadService`,
  modelo `Finanzas/Egreso`, tabla `egresos`, vista `admin/contabilidad/index.blade.php` + `contabilidad.css/js`): pestañas
  Resumen del mes (ingresos − egresos, gráfica de 6 meses, egresos por categoría, imprimible), Egresos e Ingresos (solo
  consulta: recaudos de la sede sin anulados, separados en derecho, valor del programa y cuota del sistema —lo abonado a una
  cuota se reparte en la proporción del cobro—, `ingresosPorMes`). Egresos: solo 3 categorías (`Egreso::CATEGORIAS`:
  nómina —un docente de la sede, el beneficiario es su nombre—, arriendo y servicios, materiales e insumos); registrar,
  editar y anular con motivo (`EgresoRequest` / `AnularEgresoRequest`, fecha no futura ni de hace más de un año, referencia
  obligatoria si no es efectivo, un anulado no se edita), comprobante `CE-AAAA-NNNNN` (`ultimo_egreso_AAAA`), auditoría.
  Las devoluciones de Matrículas entran solas como egreso (solo consulta). Sin otros ingresos ni cierre de mes (decidido con
  el usuario). Los egresos de la sede 7 en la BD salen de los horarios reales: nómina = salario fijo (tiempo completo /
  medio tiempo) o horas dictadas × $ 25.000 (hora cátedra) en los meses con clases; la sede da pérdida porque hay 15 grupos
  para 27 estudiantes (el usuario lo eligió así). Pruebas en `tests/Feature/Admin/ContabilidadTest.php`.
- **Comunicados (Comunicación)** (`Admin/Comunicados/ComunicadoController`, `Comunicacion/ComunicadoService`, modelo
  `Comunicacion/Comunicado`, tablas `comunicados` + `comunicado_lecturas`, vista `admin/comunicados/index.blade.php` +
  `comunicados.css/js`). Decidido con el usuario: el admin publica a toda la sede o a un rol (`DESTINOS_ADMIN`); el
  **docente** publica a sus grupos (`destino = grupo` + `curso_id`) desde su panel (Docente › Comunicados, a uno o varios grupos: una fila por grupo con el mismo `lote`; `solo_general`
  solo marca los lotes del Admin) y el admin solo los ve y los **archiva** (con motivo); el borrador de un docente no le sale a
  nadie más (ni a la secretaría ni al Admin) y la secretaría no lo elimina. Estados borrador / programado / publicado / archivado; un programado
  sale solo cuando llega `publicado_en` (`estadoActual()`, sin tareas). Los destinatarios se calculan al leer (estudiantes
  con matrícula vigente y cuenta activa, docentes activos, o los estudiantes del grupo; ya no hay destino "acudientes"). `ComunicadoRequest`:
  un borrador solo pide destinatarios y título; programar exige fecha y hora futuras; un publicado no cambia de
  destinatarios ni vuelve a borrador; los de docentes y los archivados no se editan. Adjunto PDF/JPG/PNG ≤ 5 MB en el disco
  privado (`storage/app/private/comunicados/{sede}`), se descarga por `admin.comunicados.adjunto` solo desde la misma sede;
  se borra al quitarlo o al eliminar el borrador. El PUT llega como POST con `_method` (FormData). Solo se eliminan
  borradores. Lectura: `comunicado_lecturas.leido_en` (los paneles Estudiante y Docente lo marcan al abrir) y WhatsApp a quien no lo ha leído (a su contacto de emergencia si es menor). En la BD (sede 7): 18
  comunicados y 240 lecturas simuladas (el usuario lo eligió); quien leyó tiene `ultimo_acceso` posterior y los estudiantes
  con derecho vencido no leen. Pruebas en `tests/Feature/Admin/ComunicadosTest.php`.
- **Foro (Comunicación)** (`Admin/Foro/ForoController`, `Comunicacion/ForoService`, modelos `Comunicacion/{ForoTema,
  ForoMensaje,ForoReporte}`, tablas `foro_temas` + `foro_mensajes` + `foro_reportes`, vista `admin/foro/index.blade.php` +
  `foro.css/js`). Decidido con el usuario: **un solo foro general por sede**; participan estudiantes, docentes y
  administración; todos abren temas (`ForoTema::CATEGORIAS`: Dudas académicas, Prácticas y salidas, Trámites y pagos, Vida en
  la sede) y responden; un adjunto por mensaje (PDF/JPG/PNG ≤ 5 MB, disco privado `foro/{sede}`, se descarga por
  `admin.foro.adjunto`). El primer mensaje (menor id) abre el tema: ocultarlo oculta el tema. Moderación **después, con
  reportes** (uno por persona y mensaje; pendiente → atendido al ocultar / descartado): el admin abre temas (`TemaRequest`),
  responde (`RespuestaRequest`: no en temas cerrados ni ocultos), fija y cierra/reabre (`EstadoTemaRequest`), oculta con
  motivo (`OcultarMensajeRequest`), restaura y descarta reportes; todo en la auditoría. En `foro.js` cada persona va con una
  clave `e|d|a|x` + id (`ForoService::personas`). En la BD (sede 7): 16 temas, 43 mensajes, 6 reportes y 4 adjuntos reales;
  cada persona es de la sede, activa, el estudiante con matrícula vigente y sin derecho vencido, y su `ultimo_acceso` es
  posterior a lo que escribió. Los paneles Estudiante y Docente leen, publican y reportan (`ForoService::panel`). Pruebas en
  `tests/Feature/Admin/ForoTest.php`.
- **Reportes** (`Admin/Reportes/ReporteController` + `Reportes/ReporteService`, vista `admin/reportes/index.blade.php` +
  `reportes.css/js`; solo consulta, sin tablas propias). Son los **documentos formales que la sede entrega** y no repiten
  nada del Inicio ni de otras pantallas. **Por mes** (decidido con el usuario): arriba se elige el mes y cada documento sale
  como estaba **al cierre de ese mes** (el mes en curso, hasta hoy); no se guarda nada ni hay historial. 9 documentos: para la
  Secretaría de Educación (matriculados, certificados, retiros y aplazamientos del mes; el formato oficial no se inventa:
  Excel con todas las columnas), para el aula (lista de clase, planilla de notas, asistencia del mes) y para la
  administración (cartera por edades, estudiantes en riesgo, caracterización). `ReporteService::datosSede` pasa los datos
  con sus fechas: matrículas (`estado_fecha`, motivo), grupos (clases de `fechasDictadas`, horario, docente y fechas de cada
  módulo de `fechasModulos`, también los finalizados), notas con `fecha_nota`/`fecha_habilitacion`, **asistencia por
  matrícula** (un código P/T/A/E/- por clase, de la tabla `asistencias`; quien vuelve con otra matrícula no mezcla la del
  grupo viejo) y cobros sin anular con sus pagos fechados (recaudos sin anular). `reportes.js` reconstruye el mes: estado
  según `estado_fecha`, nota solo si su fecha es anterior al corte, saldo = valor − pagos hasta el corte. Limitación: solo
  se ve el último cambio de estado (el anterior está en la auditoría). Cada documento es un modelo `{titulo, sub, para,
  resumen, grafica, tablas, notas, firmas}` (una tabla puede traer `excel` con más columnas que en pantalla). La hoja es un
  papel de 1000 px que se reduce entera si no cabe (`escalar()`): nunca hay scroll horizontal. Pruebas en
  `tests/Feature/Admin/ReportesTest.php`.
- **Inicio** (`Admin/Inicio/InicioController` + `Inicio/InicioService`, vista `admin/dashboard.blade.php` + `dashboard.css/js`):
  datos reales de hoy, solo consulta. KPI con variación frente al cierre del mes anterior (estudiantes activos, ingresos del
  mes —recaudos sin anular— frente al mes anterior completo, grupos en clases y por iniciar, estudiantes en mora —saldo de
  cobros vencidos con los pagos hasta esa fecha—; la flecha sigue el signo y el color el tono: menos mora es verde),
  matrículas nuevas de los últimos 6 meses, actividad reciente (auditoría de los usuarios de la sede, siempre escapada; el
  modal pinta texto, nunca HTML), mejores promedios (`notaFinal`, matrículas vigentes con 2+ notas), ocupación de cupos por
  programa (`estudiantesActivos`) y pendientes de hoy con enlace (grupos en planificación cuya fecha de inicio ya pasó,
  reportes del foro, cobros que vencen en 7 días, clases sin
  asistencia y módulos sin nota de los resúmenes de Asistencia/Calificaciones, habilitaciones, comunicado programado).
  Accesos rápidos a Estudiantes, Pagos, Programas y Reportes. Las barras entran con `transform` (no animan alto/ancho).
  Pruebas en `tests/Feature/Admin/InicioTest.php`.
- **Exportar a Excel**: Matrículas, Docentes, Estudiantes, Asistencia, Calificaciones y Pagos
  tienen un botón "Exportar" que
  descarga **lo que se ve con los filtros** como CSV (`;`, BOM UTF-8 para las tildes) armado en el
  navegador (`exportar()` + `celdaCsv()` en el JS de cada página; neutraliza celdas que empiezan con
  `= + - @` para que Excel no las tome como fórmulas). Al agregarlo a otra pantalla, copiar ese patrón.
  - La app corre en hora de Colombia (`config/app.php` → `America/Bogota`).
- **Trayectoria**: un estudiante puede tener varias matrículas en el tiempo (se gradúa o se retira y
  vuelve con una matrícula nueva; la vieja queda como historial). `EstudianteService::trayectoria()`
  las lista (ficha de Estudiantes › Matrícula › "Trayectoria en ESAT"); en Matrículas la ficha muestra
  "Otras matrículas de X". Matrículas tiene 3 pestañas: vigentes (activas/aplazadas), Por matricular
  (sin matrícula vigente: nunca matriculados, o retirados/graduados que vuelven) y Finalizadas
  (graduadas/retiradas, solo consulta).
- **Login** (`LoginRequest`): una cuenta con `activo=0` no puede ingresar, y cada ingreso
  guarda `users.ultimo_acceso` (también el de la cookie "Recordarme": listener de `Login` en `AppServiceProvider`, B22). El middleware `EnsureAccountIsEnabled` (agregado al grupo `web` en
  `bootstrap/app.php`) revisa lo mismo **en cada petición**: si la cuenta se desactiva o se bloquea,
  cierra la sesión abierta (y con ella la cookie "Recordarme") y vuelve al login
  con el motivo; a un `fetch` le responde 401. La regla vive en un solo lugar,
  `EnsureAccountIsEnabled::motivo()`, y `LoginRequest` usa la misma: si se agrega otra razón para
  bloquear el acceso, va ahí. `User::$attributes` pone `activo = true` también en memoria; en
  pruebas, un modelo que otra petición cambió hay que `refresh()`-arlo antes de `update()`.
  `pagos:vencer` está programado en `routes/console.php`, pero nada ejecuta el scheduler.
- `EstudianteService::matriculaVigente()` / `grupoDeMatricula()`: matrícula y grupo que se
  muestran de un estudiante (los usa Estudiantes; no duplicar en controladores).
- **Grupo cerrado** (`Curso::cerrado()` / `motivoCerrado()`): finalizado **o con sus clases ya terminadas por fecha**
  (el estado lo cambia la secretaría a mano). Es la regla de matricular, cambiar de grupo o de programa y reintegrar
  (`abierto()` en `matriculas.js`).
- **Grupo en curso** (decidido con el usuario el 26-11-2026, `MatriculaService::noAlcanza`, revisada con el grupo
  bloqueado en matricular, cambiarGrupo, reintegro y cambiarPrograma): **nadie empieza una carrera en un grupo que ya
  empezó** (matrícula nueva y cambio de carrera: solo grupos con inicio hoy o después; `paraEmpezar()` en `matriculas.js`).
  Quien ya venía estudiando entra a uno en curso solo si no le hace perder módulos: **reintegro** = el grupo todavía no
  empezó el primer módulo que le falta (lo aprobado se conserva); **cambio de grupo** = el grupo nuevo no empezó ese módulo
  antes que el suyo (dos grupos al mismo ritmo sí). Matrículas recibe `no_alcanza` (grupo → motivo) de cada matrícula
  vigente y solo muestra los grupos que sirven (`sirveA()`). En pruebas se matricula con `alIniciar()` de
  `DatosAcademicos`. Pruebas en `tests/Feature/Admin/GrupoEnCursoTest.php`.
- **Grupos: fechas y finalizados** (revisión del Administrativo, 27-11-2026, `GrupoRequest` + `cursos.js`): un grupo nuevo, o uno
  que corre su inicio, no empieza antes de hoy (antes se podía mover al pasado un grupo con estudiantes y el derecho quedaba
  descuadrado). Un grupo **finalizado no se edita** (ni su docente: es historia); primero se reabre con el botón de su ficha.
  Un grupo **activo que ya empezó no vuelve a planificación** (decidido con el usuario el 27-11-2026); si todavía no empieza, sí.
  Departamento y ciudad de estudiantes y docentes se revisan también en el servidor con `CiudadDeColombia` en las Form
  Requests de la secretaría (el panel general las hereda). Pruebas en `HistorialGruposTest`, `EstudiantesTest` y `AuditoriaLogicaTest`.
- **Nacido en otro país** (decidido con el usuario el 27-11-2026, migración `2026_11_27_000001`): `users.pais_nacimiento` (null =
  Colombia). Solo con cédula de extranjería o PPT (`EstudianteRequest::DOCUMENTOS_EXTRANJERO`): país de `public/assets/data/paises.json`
  (`EstudianteRequest::paises()`, sin Colombia) y la ciudad escrita en `lugar_nacimiento`, sin departamento. Los formularios de la
  secretaría y del Admin general preguntan "¿Dónde nació?" solo con esos documentos.
- **Historial de grupos de la matrícula** (decidido con el usuario el 27-11-2026, tabla `matricula_grupos`, modelo
  `Academico/MatriculaGrupo`): cada paso por un grupo con `desde` (incluido) y `hasta` (sin incluir). Lo abren y cierran
  `MatriculaService` al matricular, cambiar de grupo (el viejo hasta hoy, el nuevo desde hoy), aplazar, retirar, graduar,
  anular, reintegrar (en el mismo grupo o en otro: desde el reintegro) y cambiar de carrera; `asegurarHistorial` crea el
  paso de una matrícula anterior a la tabla (las de la BD se llenaron con SQL). **La regla única** de en qué fechas cuenta un
  estudiante en un grupo es `Matricula::ventanasEn($curso)` (+ `Matricula::enVentanas`): la usan la lista de cada clase y
  las notas del docente (`PanelDocenteService::estudiantes/planilla`, `datos.js` `enVentana`), Admin › Asistencia y
  Calificaciones, el % de las fichas (`EstudianteService::porcentajeAsistencia` con la matrícula), el panel del estudiante
  (sus clases de todos los grupos) y Reportes (`pasos` de cada matrícula). Las matrículas de un grupo, también las que ya
  se cambiaron: `Curso::matriculasEnGrupo()` (cargar `matriculas` y `matriculasDelHistorial` con `grupos`). Quien se cambió
  aparece en el grupo viejo como **"trasladado"** ("Cambió de grupo", solo consulta); su docente viejo solo califica los
  módulos que terminó antes de irse (`NotasRequest`). Pruebas en `tests/Feature/Admin/HistorialGruposMatriculaTest.php`.
- **Festivos y "No hubo clase"** (decidido con el usuario el 27-11-2026): `App\Support\Festivos` calcula los festivos de
  Colombia (ley Emiliani, Pascua por Meeus); `config('academico.festivos')` los apaga (las pruebas de fechas fijas lo hacen).
  El docente marca "No hubo clase" con motivo (`POST docente/asistencia/{curso}/sin-clase` y `/con-clase` para deshacer,
  `SinClaseRequest`, tabla `clases_suspendidas`; no en días con asistencia tomada; también una clase que viene). **La regla
  de si hubo clase un día es `Curso::claseEn($d)`**: día del horario (`sesionEn`), sin "No hubo clase" y, si es festivo,
  solo con asistencia tomada (algunos grupos sí estudian en festivos). La usan `fechasDictadas`, `fechasModulos`, el panel
  del estudiante y la clase de hoy del Admin; el docente recibe `festivos` y `sinClase` y `datos.js` les da los estados
  `festivo` y `sin_clase` (no son pendientes). Al agregar un cálculo de clases, usar `claseEn` (no `sesionEn`). Pruebas en
  `tests/Feature/Docente/FestivosTest.php`.
- **Cuotas y fecha de inicio**: al pasar a otro grupo o correr el inicio del suyo, las cuotas que debe vuelven a contar desde
  `primeraCuota` del grupo (`PagoService::reprogramarCuotas`; las pagadas conservan su fecha; no después de un aplazamiento).
  Al reintegrarse, las cuotas cuentan desde el reintegro o desde que empieza el grupo al que vuelve, lo que sea después.
  Una cuota que queda en $ 0 (beca del 100 % sin cuota del sistema) queda **pagada sin dinero**; si se quita la beca,
  vuelve a deberse.
- **Cuenta del estudiante** (`PATCH admin/estudiantes/{id}/estado`): la secretaría la desactiva solo sin matrícula vigente
  (retirado, graduado o nunca matriculado); matricularlo de nuevo la reactiva. Marcar un documento recibe cómo debe quedar
  (`entregado`), no "cambiar". Quien se registró con TI y cumplió 18 se sigue pudiendo editar.
- **Cupos**: solo las matrículas activas ocupan cupo → usar `Curso::estudiantesActivos()`
  / `cuposLibres()`, nunca `estudiantes()->count()`. Un grupo con estudiantes no
  puede cambiar de programa.
- Horarios (`Admin/Horarios/HorarioController`): `PUT /admin/horarios/{curso}`
  guarda las sesiones del grupo y su docente. **Lo ya dictado no cambia (A7)**: si el grupo ya empezó y tiene asistencia
  o notas, el horario de antes queda con `hasta` = hoy y el nuevo con `desde` = mañana (`curso_sesiones.desde/hasta`);
  si no, se reemplaza. `Curso::sesiones()` es el vigente (pantallas, tope, cruces); todo lo que calcula fechas de clase usa
  `historialSesiones` + `Curso::sesionEn($dia)` (Asistencia, Calificaciones, PanelDocente y `docente/datos.js`, Reportes):
  al agregar un cálculo de fechas, cargar `historialSesiones` y usar `sesionEn`. Un grupo que ya empezó no cambia su
  fecha de inicio (salvo uno en planificación sin asistencia ni notas: corre su inicio a hoy o después; el Inicio avisa de
  los que siguen en planificación con la fecha ya pasada), y antes de su fin solo se finaliza sin matrículas vigentes (`Curso::impedimentoParaFinalizar`, A8);
  graduar exige que haya terminado por fecha. "Nuevo horario" solo ofrece
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
(no hay columna `name`). Las demás reglas del Admin están en
`tests/Feature/Admin/{Matriculas,Pagos,Contabilidad,Comunicados,Foro,Reportes,Inicio,Estudiantes,Asistencia,Calificaciones}Test.php`
(datos de apoyo en `DatosAcademicos.php`). Superadmin: `tests/Feature/Superadmin/AdministradoresTest.php`
(Sedes todavía sin pruebas). Sesión y cuentas desactivadas: `tests/Feature/Auth/CuentaHabilitadaTest.php`.
Dos envíos a la vez, transacción por petición y choques de índice único: `tests/Feature/Admin/ConcurrenciaTest.php`.
Dinero adelantado al aplazar, retirar o cambiar de programa (A4, A5, A6, A9): `tests/Feature/Admin/DineroAdelantadoTest.php`.
Caja, recibos de anuladas, doble devolución, plan por periodo, total de cuotas, borrado restringido, consecutivo y
`pagos:vencer` (M25, B26–B29, B35): `tests/Feature/Admin/CobrosRevisionTest.php`. Restablecer clave, "Recordarme" y
recuperar la clave de una cuenta desactivada (B21–B23): `tests/Feature/Auth/SesionesYClavesTest.php`.
Historial de horarios, fecha de inicio y finalizar a tiempo (A7, A8): `tests/Feature/Admin/HistorialGruposTest.php`.
Instalación nueva y protección de migraciones (A11, A12): `tests/Feature/InstalacionTest.php`.
Auditoría de lógica de Secretaría y Admin (24-11-2026: grupo cerrado por fecha, cuotas que siguen al grupo, sacar un programa
de activo, bloqueo temporal, cuenta del estudiante y foro, malla con Aula, beca completa, paz y salvo de todas las sedes,
envíos del panel general, pasaporte, TI a los 18, documentos): `tests/Feature/Admin/AuditoriaLogicaTest.php`.
Panel Admin general: `tests/Feature/AdminGeneral/`. Panel Docente: acceso por rol y redirección en `tests/Feature/Docente/PanelDocenteTest.php`; lo que ve y lo que
guarda (asistencia y notas, con sus reglas y lo que luego consulta el Admin) en `tests/Feature/Docente/RegistroDocenteTest.php`;
sus comunicados, foro y perfil en `tests/Feature/Docente/ComunicacionDocenteTest.php`; guía de actividades, material de clase,
el Aula en Notas y lo que imprime en `tests/Feature/Docente/AulaMaterialTest.php`.
Panel Estudiante: acceso por rol, redirección y su perfil en `tests/Feature/Estudiante/PanelEstudianteTest.php`; lo
que ve y lo que guarda (plan de pago —también el que elige la Secretaría—, comunicados leídos, foro, evaluaciones,
entregas y lo que crea y califica el docente en su Aula virtual) en `tests/Feature/Estudiante/BackendEstudianteTest.php`.
Sitio público (una página por programa y escuela, rutas de siempre, cierre del footer):
`tests/Feature/Sitio/PaginasTest.php`.

Las pruebas necesitan `pdo_sqlite`, que está desactivado en `C:\php\php.ini`:
sin él fallan casi todas (casi todas las 372 actuales). Activarlo en el `php.ini` o correr
`php -d extension=pdo_sqlite vendor/bin/phpunit`.

## Páginas de error

`resources/views/errors/` (403, 404, 419, 429, 500, 503 sobre `errors/layout.blade.php`, CSS en
`public/assets/css/errores.css`): en español, sin consultar la base (un 500 puede venir de ahí); con sesión el botón lleva a
`panelUrl()`, sin sesión al inicio del sitio; el 419 lleva al login. Con `APP_DEBUG=true` un 500 muestra la pantalla de
depuración de Laravel en vez de la página: en producción va en `false`. Pruebas en `tests/Feature/PaginasErrorTest.php`.

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

Combobox dentro de un drawer/modal con su propio Escape: el Escape del drawer
debe ignorar la tecla mientras haya un `.app-combobox.is-open` (como en
`horarios.js`), o cierra la acción en vez de solo la lista.

**Escapar todo lo que venga de la BD antes de pintarlo con `innerHTML`** (`esc()`
de cada archivo), incluidos teléfonos y valores "que siempre son números". En
Estudiantes y Matrículas, `fmtTel()` formatea solo si son 10 dígitos y
escapa todo lo demás, y `waLink()`/`tel:` usan solo dígitos (`digitos()`). Al copiar
esas funciones a otra pantalla, copiar esta versión (HALLAZGOS C1).

## Combobox propio (departamento/ciudad de Colombia)

El `<select>` nativo no se puede estilizar ni controlar hacia dónde abre.
Se construyó un combobox propio (buscador + lista, `position: fixed`,
abre hacia abajo, calcula el espacio disponible en pantalla para
no salirse del viewport) usando un dataset real de departamentos/ciudades
de Colombia vendido en `public/assets/data/co-departamentos-ciudades.json`
(32 departamentos, cientos de municipios reales). Las copias del Admin abren
hacia arriba solo si abajo no cabe y arriba hay más espacio (`haciaArriba`).
Con departamento/ciudad está en `admin/estudiantes.js`,
`admin/docentes.js` y en los dos del Superadmin (`sedes-programas.js`,
`administradores.js`). **Copiar desde uno del Admin**: los del Superadmin todavía
no tienen `alScroll` (HALLAZGOS M3). Copiar el patrón (`initCombobox` +
`poblarCiudades*` + `alScroll`), no reinventarlo.

## Patrón de fila clicable

En las tablas de solo-editar/ver (Sedes, Estudiantes,
Auditoría), toda la fila abre el modal/asistente correspondiente, no solo
el ícono. Cuidado: si una fila tiene además un control propio (como el
interruptor activo/inactivo), ese control debe llamar
`e.stopPropagation()` para no disparar también el click de la fila.

## Estado actual del panel Superadmin

Sedes y Admin de ESAT tienen backend real (`SedeController`,
`AdministradorController`, `fetch` con CSRF). Sedes todavía sin Form Request ni
auditoría (HALLAZGOS M9). Inicio, Estudiantes, Auditoría y Configuración siguen
siendo maqueta con datos fijos en el Blade (rutas closure en `routes/web.php`),
aunque `auditorias`, `pagos` y `recaudos` ya tienen datos reales para conectarlas.


- **Inicio** (`dashboard.blade.php`): recaudo del mes + gráfica con tooltip
  al pasar el cursor, estado de cartera, alertas paginadas (4 por página),
  actividad reciente, franja de sedes/escuelas/programas/administradores.
- **Sedes** (`sedes-programas.blade.php`, ruta `/superadmin/sedes`): crear y
  editar sedes, sin eliminar ni desactivar (nombre, departamento, ciudad, dirección) con buscador +
  paginación + modal crear/editar. Las 4 sedes reales: Bogotá, Sasaima,
  Guaduas, La Dorada.
- **Admin de ESAT** (`administradores.blade.php`, ruta `/superadmin/administradores`; decidido con el usuario el
  24-11-2026): el Superadmin crea y maneja **la cuenta del Admin** (rol `admin_general`, **una sola**, sin sede); las
  secretarías las crea el Admin desde su panel. Tarjeta del Admin (documento, correo, celular, último ingreso) con
  Editar, Restablecer clave (también desbloquea) y Activar/Desactivar con confirmación; sin Admin, el botón para crearlo
  (`store` responde 422 si ya hay uno). Asistente de 3 pasos (Datos personales → Identificación y contacto →
  Confirmación), edad en vivo, mayor de edad, celular `^3\d{9}$`, pasaporte con letras. `Requests/Superadmin/
  Administradores/AdminRequest` + auditoría; las rutas solo tocan cuentas `admin_general` (`asegurarAdmin`, 404 para
  cualquier otra). La alerta "La Dorada sin admin" del Inicio sigue siendo fija.
- **Estudiantes** (`estudiantes.blade.php`): **solo consulta**, sin
  crear/editar. Resumen (total/al día/pendiente/vencido) + tabla con
  filtros (sede/programa/estado de pago) + detalle de historial de pagos
  por estudiante. 24 estudiantes de ejemplo.
- **Auditoría** (`auditoria.blade.php`): **solo consulta**. Log de
  acciones de administradores (crear/editar/eliminar/activar/desactivar)
  con filtros (administrador/tipo/periodo) + detalle por evento. 24
  eventos de ejemplo.
- **Configuración** (`configuracion.blade.php`): maqueta de frontend con 3
  pestañas (Institución, Seguridad, Accesos bloqueados) y un toast al guardar.
  Ojo: trae datos de contacto ficticios (marcados en el comentario) aunque los
  reales existen, y no tiene el campo de **cuota del sistema**, que Programas del
  Admin dice que se configura aquí (HALLAZGOS B16).
- Layout compartido (`superadmin/layout.blade.php` +
  `superadmin/partials/{sidebar,topbar}.blade.php`): sidebar con el logo
  real de ESAT (ícono + wordmark, `logo-negativo.png`) fijo (no se mueve
  al hacer scroll — solo `.app-content` scrollea), topbar con dropdown de
  usuario (perfil real conectado a `route('profile.edit')` + cerrar
  sesión).

## Panel Admin general (backend real)

- Rol `admin_general` (migración `2026_11_09_000001_add_admin_general_to_users_role`), `User::isAdminGeneral()`,
  `panelUrl()` → `general.dashboard`. Rutas `/general` (`role:admin_general`, un controlador por pantalla en
  `AdminGeneral/{Panel}/`, Form Requests en `Requests/AdminGeneral/{Panel}/`). Layout propio:
  `admin_general/layout.blade.php` + `partials/{sidebar,topbar}` (copiado del Docente), CSS/JS en
  `public/assets/{css,js}/admin_general/`. **Mi perfil** sí tiene backend real: `admin_general/profile.blade.php` +
  `profile.css` (el diseño del de la secretaría), guarda con `ProfileController@update` y la clave con `password.update`.
- **Catálogo (decidido con el usuario, 2026-11-09)**: el **Admin** crea y edita escuelas y programas (malla, duración,
  cupo, jornadas) y fija el **precio, uno solo para todas las sedes**. La **secretaría solo elige cuáles programas del
  catálogo ofrece su sede** (`programa_sede`): Secretaría › Programas solo consulta y tiene "Agregar del catálogo" / "Dejar
  de ofrecer en mi sede" (`PATCH admin/programas/{programa}/oferta`, no con grupos vigentes ni un inactivo); ya no tiene
  rutas para crear/editar programas ni escuelas (su pestaña Escuelas es solo consulta).
- Pantallas (cada una con su Blade/CSS/JS y su prefijo; menú: Principal, Estructura, Personas, Seguimiento, Comunicación y
  Supervisión, que se despliega a un lado como Finanzas/Comunicación en la secretaría: `$desplegables` en el sidebar):
  - **Inicio** (`dashboard`, `gen-`): filtro de sede (chips, se recuerda), KPI con variación, comparación de sedes
    (fila = filtrar, botón "Entrar"), ingresos de 6 meses apilados por sede (8 colores fijos validados para daltonismo,
    `--sede-1..8`, también en Finanzas; la 9.ª sede repite color), alertas (sin secretaría, secretaría sin entrar 30+ días, mora ≥ 20 %,
    ocupación < 50 % o ≥ 95 %, comunicados programados) y actividad (sale de la auditoría).
  - **Sedes** (`sedes/index`, `sed-`): crear y editar (combobox de Colombia), sin eliminar ni desactivar; detalle con
    datos, secretarías y programas.
  - **Secretarías** (`secretarias/index`, `sec-`): cuentas rol admin; asistente de 3 pasos (clave = documento, correo y
    documento únicos, celular `^3\d{9}$`), activar/desactivar con confirmación (avisa si la sede queda sin secretaría)
    y restablecer clave. El pasaporte admite letras.
  - **Programas** (`programas/index`, `prg-`): catálogo real; crear/editar en 3 pasos (datos y jornadas, duración y
    precio, malla editable), activar/desactivar (no con grupos vigentes), y en el detalle a qué sedes se ofrece
    (interruptor; no se quita de una sede con grupos vigentes). Editar no invalida grupos vigentes (cupo ≥ estudiantes
    del grupo más lleno, no se quita una jornada en uso). Pestaña Escuelas: crear, editar, eliminar solo sin programas ni docentes.
  - **Decidido con el usuario**: el Admin **crea y edita igual que la secretaría** (mismas reglas), pero en cualquier
    sede (la elige en el formulario). Matricular, cobrar, grupos y horarios siguen por "Entrar a la sede".
  - **Estudiantes** (`estudiantes/index`, `est-`): todas las sedes (filtros sede/programa/matrícula/pagos, ficha con
    datos, matrícula y pagos, exportar) + registrar y editar a la persona en 2 pasos (correo obligatorio, clave =
    documento, TI solo si es menor, contacto de emergencia obligatorio; no cambia de sede con matrícula vigente).
  - **Docentes** (`docentes/index`, `dcn-`): crear/editar en 3 pasos (sede, escuela, título, especialidad, vinculación),
    activar/desactivar (no con grupos vigentes), restablecer clave; no cambia de sede con grupos vigentes allá.
  - En los tres, documento y correo no se repiten entre ninguna cuenta: lo revisa el servidor (el navegador ya no recibe
    los documentos y correos de todas las cuentas para eso).
  - **Académico** (`academico/index`, `aca-`): solo consulta; sede + pestañas Grupos (avance por módulo),
    Asistencia (informativa) y Notas (módulos sin nota, por habilitar), detalle del grupo con su malla, exportar.
  - **Foro** (`foro/index`, `for-`): foros de todas las sedes; modera (ocultar con motivo —el primer mensaje oculta el
    tema—, restaurar, descartar reportes, fijar, cerrar/reabrir) y participa como Admin (responder, abrir temas).
  - **Finanzas** (`finanzas/index`, `fin-`): sede + mes; pestañas Recaudo (recibo imprimible), Cartera (a hoy, por
    edades, WhatsApp al contacto de emergencia si es menor), Contabilidad (ingresos − egresos) y Devoluciones. Solo consulta.
  - **Comunicados** (`comunicados/index`, `com-`): publicar a todas o algunas sedes por rol (ahora, programar,
    borrador); un publicado no cambia de destinatarios; archivar con motivo; eliminar solo borradores propios.
  - **Reportes** (`reportes/index`, `rep-`): por mes y sede: matriculados, novedades, recaudo, ingresos y egresos,
    cartera por edades (a hoy). Imprimir y Excel.
  - **Auditoría** (`auditoria/index`, `aud-`): filtros de periodo, sede, persona y acción; detalle; exportar.
  - **Configuración** (`configuracion/index`, `cfg-`): institución (datos reales), cuota del sistema, % de devolución del
    derecho al retirarse antes de clases, reglas de acceso
    (intentos, días de inactividad) y desbloquear cuentas; cada formulario con Deshacer y confirmación.
- **"Entrar a la sede" (backend real)**: `POST /general/sedes/{sede}/entrar` (`AdminGeneral/Sedes/EntrarSedeController`,
  `destino` opcional de una lista cerrada) guarda la sede en la sesión (`User::SESION_SEDE`) y lleva a las pantallas
  REALES de la secretaría (`/admin`, rutas con `role:admin,admin_general` + middleware `sede.trabajo` =
  `EnsureSedeDeTrabajo`: sin sede elegida vuelve a `/general`, a un fetch 409). `POST /general/salir-de-sede` la quita.
  El layout de la secretaría muestra la franja "Estás trabajando en la sede X como Admin · Volver al panel general".
  **Regla**: toda pantalla, Form Request y vista de la secretaría lee la sede con `User::sedeTrabajoId()` /
  `sedeTrabajo()` (la propia de la secretaría, o la elegida por el Admin), **nunca `sede_id` del usuario directo**.
  `auditorias.sede_id` guarda la sede de cada acción (migración `2026_11_10_000001`); la actividad del Inicio de la
  secretaría incluye lo que hizo el Admin en ella. Pruebas en `tests/Feature/AdminGeneral/EntrarSedeTest.php`.
  **Pestañas en sedes distintas**: el layout de la secretaría (solo para el Admin) envuelve `fetch` y manda la sede con la
  que se abrió la pestaña en `X-Sede-Trabajo`; si en otra pestaña ya entró a otra sede, `EnsureSedeDeTrabajo` responde 409
  ("Recarga la página…") en vez de guardar en la sede equivocada.
- El Superadmin ya no crea secretarías: crea la cuenta del Admin (Superadmin › Admin de ESAT). Todavía conserva Sedes y
  sus maquetas (Inicio, Estudiantes, Auditoría, Configuración): quitarle lo que pasó al Admin queda pendiente.
- **Datos (backend real, 2026-11-10)**: cada controlador pasa lo de su pantalla con `window.GEN_DATOS = @json($datos)`
  (la forma que leen los JS; ya no hay datos de ejemplo). Servicios en `app/Services/General/`: `EstructuraService` (sedes
  con sus cifras de hoy y del cierre del mes anterior —activos, grupos, cupos, ingresos de 6 meses, mora con
  `InicioService::mora`—, secretarías, escuelas, programas con malla y `con_notas`), `PersonasService` (estudiantes con
  su matrícula, pagos de `PagoService::resumen`, asistencia y promedio; recibos con quién los registró; su contacto de emergencia;
  docentes con sus grupos), `AcademicoService` (grupos con los resúmenes de `AsistenciaService`/`CalificacionService` de
  cada sede), `FinanzasService` (Finanzas y Reportes: recibos, egresos sin anular y devoluciones del último año),
  `ComunicacionService` (comunicados agrupados por lote) y `SupervisionService` (auditoría del último año).
- **Escrituras**: `fetch` con CSRF (`enviar()` en el JS de cada pantalla; 401/419 recarga, 422 marca el campo) y al
  terminar `recargarConAviso()` (los datos y cifras vuelven del servidor). **Las reglas son las de la secretaría**: las Form
  Requests del panel general **extienden** las de `Admin/` y solo cambian la sede (las de personas leen la sede de
  `sedeId()`, que el general sobrescribe con la elegida) o agregan lo propio (malla del programa, sedes de un comunicado).
  Todo queda en la auditoría con la sede afectada (`Auditoria::registrar(..., $sedeId)`).
  - Programas: la malla se guarda en orden (conserva ids), las horas del programa = suma de módulos, y con notas **o
    evaluaciones/actividades del Aula virtual** en sus módulos la malla no cambia (`Programa::mallaEnUso`). Un programa no
    se desactiva, ni sale de "activo" desde el formulario, ni deja de ofrecerse en una sede (Admin o secretaría) con
    grupos vigentes **o estudiantes aplazados** (`Programa::impedimentoParaRetirar`). **La duración (meses y división) no cambia con matrículas vigentes** (activas o aplazadas) en el
    programa: sus cuotas y su grado dependen de ella; el formulario la muestra bloqueada (`vigentes`) y lo valida
    `ProgramaRequest`. **Paso 4 "Sedes"** (al crear y al editar): todas / algunas / ninguna por ahora, con las reglas del
    interruptor de la ficha (no se quita de una sede con grupos vigentes; un inactivo no se ofrece en sedes nuevas); cada
    sede que entra o sale va a la auditoría de esa sede (`ProgramaController::ofrecerEn`). Sin `sedes` en la petición, al
    crear no se ofrece en ninguna y al editar no cambian. Los cruces de horario solo se revisan si cambian las horas o la
    duración. Escuelas: sigla automática y fija (`siglaLibre`), no se eliminan con programas ni docentes.
  - Estudiantes: no cambia de sede con matrícula vigente ni **con deudas** en cualquiera de sus matrículas.
    El formulario pide lo mismo que la secretaría (nacimiento, residencia con el listado de Colombia —regla
    `App\Rules\CiudadDeColombia`— y el contacto de emergencia).
  - Comunicados a varias sedes: **una fila por sede con el mismo `comunicados.lote`**; una fila con lote no se edita ni
    elimina desde las pantallas de la secretaría (tampoco el Admin al entrar a la sede: `solo_general` en `comunicados.js`) (migración `2026_11_10_000002`);
    el Admin los ve, edita, archiva y elimina como uno solo. Destinos: todos (= sede), estudiantes, docentes
    (no hay destino "secretarías"). Sin adjunto. La secretaría no edita ni elimina los del Admin (sí los archiva en su sede).
    Una copia archivada por la secretaría se respeta: editar el envío no la revive, archivarlo conserva su motivo, y el
    estado del envío sale de las copias vivas (`archivado_en_sedes` dice dónde lo archivó la secretaría).
  - Foro: `ForoService` de la secretaría con Form Requests que extienden las suyas; sin adjuntos desde el panel general.
  - Configuración: datos de la institución en `configuraciones` (`institucion_*`, por defecto los reales de
    `Configuracion::INSTITUCION`) y salen en el encabezado de los documentos impresos de la secretaría; cuota del sistema;
    reglas de acceso (`seguridad_intentos_maximos`, `seguridad_dias_inactividad`). **Bloqueo de cuentas real**
    (`Sistema/BloqueoService`): intentos fallidos seguidos (`LoginRequest`; se levanta solo a los 15 minutos,
    `MINUTOS_INTENTOS`, para que nadie deje por fuera a otra persona escribiendo mal su clave) e inactividad (solo cuentas
    que ya ingresaron); `bloqueada()` es la única regla de si está bloqueada ahora. Restablecer la clave (secretaría o
    Admin) también desbloquea;
    `EnsureAccountIsEnabled::motivo` no deja entrar a una bloqueada (el aviso solo sale con la clave correcta); el Admin y el
    superadmin nunca se bloquean; el Admin desbloquea. Restablecer clave (`Sistema/SesionesService`) también cierra sus sesiones.
- Pruebas: `tests/Feature/AdminGeneral/{PanelGeneral,EntrarSede,Estructura,Personas,ComunicacionConfiguracion}Test.php`.
  La auditoría del 11-11-2026 (informe en `HALLAZGOS.md` §10; varias sedes a la vez: precio pactado, duración, grupos finalizados, envíos archivados en una
  sede, pestañas en sedes distintas, sin malla y permisos de todas las rutas `/general` para cada rol) está en
  `AuditoriaPanelTest.php`.

## Panel Docente (datos reales)

- Rutas `/docente` (`role:docente`) a un controlador por pantalla en `Docente/{Inicio,Grupos,Asistencia,Notas,Aula,Comunicados,Foro}/`, y
  `User::panelUrl()` → `docente.dashboard`. Layout propio: `docente/layout.blade.php` +
  `partials/{sidebar,topbar}`, `docente/layout.css` (el del Admin sin los menús flotantes;
  la animación de entrada se llama `.app-anim`) y `layout.js`.
- Pantallas (cada una con su Blade/CSS/JS y su prefijo):
  - **Inicio** (`dashboard`, `ini-`): clases de hoy, pendientes (asistencia sin registrar,
    notas por poner, habilitaciones), estudiantes bajo el 80 % y su horario semanal.
  - **Mis grupos** (`grupos/index`, `grp-`): tarjetas por grupo (Activo · Por iniciar · Finalizado) y ficha con
    Estudiantes, Malla y Horario. **Imprimir lista** (27-11-2026): lista de clase con 6 casillas para firmar
    (`docente/documento.js` + `documento.css`, copiados del Estudiante: hoja con el encabezado legal de Admin › Configuración;
    también en grupos finalizados).
  - **Asistencia** (`asistencia/index`, `tas-`): calendario de clases del grupo y planilla
    P/T/A/E (excusa con motivo obligatorio), con "Todos presentes".
  - **Notas** (`notas/index`, `not-`): por módulo, de 1.0 a 5.0 con un decimal (acepta coma),
    habilitación solo si la nota es menor a 3.0 y el módulo ya terminó, y "Planilla completa"
    (estudiantes × módulos) con **Imprimir planilla**. Bajo cada estudiante, "En el Aula": el promedio de lo calificado en el
    Aula virtual de ese módulo (`AulaService::notasPorModulo`, `datos.aula`), solo como referencia (decidido con el usuario el
    27-11-2026: la nota del módulo la sigue poniendo el docente).
  - **Aula virtual** (`aula/index`, `dau-`, `Docente/Aula/AulaController` + `Academico/AulaService`, 24-11-2026): por
    grupo (chips, `?grupo=ID`) crea evaluaciones de selección múltiple (1–30 preguntas, 2–6 opciones, una sola correcta,
    cierre futuro) y actividades con fecha límite, cada una de un módulo de la malla; ve quién respondió (con sus aciertos)
    y quién entregó, descarga el archivo y califica la entrega de 1,0 a 5,0 con un decimal (acepta coma) con comentario.
    Una evaluación se edita o elimina mientras nadie la responda; una actividad se elimina sin entregas; un grupo
    finalizado es solo consulta (`Requests/Docente/Aula/AulaRequest`: grupo suyo y sin finalizar). Ninguna calcula la nota
    del módulo. Es lo que el estudiante ve en su Aula virtual. **27-11-2026** (migración `2026_11_27_000002`): la actividad
    lleva un **archivo de apoyo** opcional (`actividades.adjunto_*`) y cada grupo tiene **material de clase** (modelo
    `Academico/Material`, tabla `materiales`: título, descripción y módulo opcionales —sin módulo = todo el grupo—, archivo,
    enlace o los dos; `MaterialRequest`). Lo del docente acepta PDF, Word, PowerPoint, Excel, JPG o PNG ≤ 10 MB
    (`AulaService::EXTENSIONES_DOCENTE`), va como FormData (el PUT como POST + `_method`), un archivo nuevo reemplaza al
    anterior y el viejo se borra después de guardar. Lo descarga quien tiene (o tuvo) matrícula en el grupo
    (`AulaService::estudioEn`) y su docente. Pruebas en `tests/Feature/Docente/AulaMaterialTest.php`.
    **Evaluaciones (decidido con el usuario el 27-11-2026, migración `2026_11_27_000003`):** el docente elige un **tiempo
    límite** (`evaluaciones.duracion_minutos`, `Evaluacion::DURACIONES` o sin límite). Con tiempo, el estudiante ve las
    preguntas al pulsar Comenzar (`POST estudiante/aula/evaluaciones/{id}/iniciar`, tabla `evaluacion_intentos`: abrirla
    otra vez no reinicia el reloj); su tiempo termina en `Evaluacion::terminaPara` (nunca después del cierre), el reloj usa
    los segundos que da el servidor (`intento.restante`), lo marcado se guarda en `sessionStorage` y al llegar a cero se
    envía solo: lo que falte queda con `respuestas_estudiante.opcion_id` null y cuenta como malo (60 s de gracia,
    `GRACIA_SEGUNDOS`). Quien la comenzó y no envió a tiempo queda sin responder. Una evaluación **ya comenzada o
    respondida se corrige** (textos, cierre —se puede conservar uno vencido— y la respuesta correcta, que recalcula
    `es_correcta` de quienes ya respondieron) pero no se agregan ni quitan preguntas u opciones, no cambia el tiempo y no se
    elimina. Pruebas en `tests/Feature/Docente/EvaluacionesTest.php`.
  - **Comunicados** (`comunicados/index`, `com-`, `Docente/Comunicados/ComunicadoController` +
    `Comunicacion/ComunicadoDocenteService`, decidido con el usuario el 24-11-2026): pestaña Recibidos (lo que la
    secretaría o el Admin manda a toda la sede o a los docentes; lista + lector del Estudiante, abrir lo marca leído) y Mis
    publicaciones: a **uno o varios de sus grupos** no finalizados (una fila por grupo con el mismo `lote`; cada fila con su
    propia copia del adjunto), publicar ya, programar o borrador con adjunto PDF/JPG/PNG ≤ 5 MB (las reglas de
    `ComunicadoService::guardar`; `Requests/Docente/Comunicados/*` extienden las de la secretaría). Publicado: cambia el
    texto pero no los grupos; archivar con motivo (en todos sus grupos; la copia que archivó la secretaría conserva su
    motivo); solo se eliminan borradores. Quién lo leyó por grupo y WhatsApp a quien falta (a su contacto de emergencia si es menor).
    `?id=ID`, `?vista=mios`.
  - **Foro** (`foro/index`, `for-`, `Docente/Foro/ForoController`): el foro del Estudiante (`ForoService::panel`) sin pedir
    matrícula; **solo participa** (abre temas sin fijarlos, responde y reporta); moderar es de la secretaría. `?tema=ID`.
  - **Mi perfil** (`perfil`, `prf-`, copiado del perfil del Estudiante): cambia correo, celular y dirección; nombre y
    documento (no los acepta `ProfileUpdateRequest`), título, especialidad, escuela y vinculación son solo consulta.
- Reglas acordadas con el usuario: el docente registra y **corrige** asistencia y notas
  mientras el grupo no esté finalizado; un grupo finalizado es solo consulta; no se registran
  clases futuras ni notas de módulos por iniciar.
- Enlaces directos: `asistencia?grupo=ID&fecha=AAAA-MM-DD`, `notas?grupo=ID&modulo=ID`,
  `grupos?grupo=ID` (abre la ficha). Inicio y Mis grupos los usan.
- **Datos** (`Academico/PanelDocenteService`): `datos()` arma lo que leen las 4 pantallas con las reglas de
  `AsistenciaService` y `CalificacionService` (lo que ve el docente cuadra con Admin › Asistencia y Calificaciones):
  sus grupos con horario (`curso_docente`, vigentes primero), módulos con `fechasModulos`, estudiantes (una fila por
  estudiante con su matrícula en el grupo; `desde` = su matrícula o el inicio del grupo, `hasta` = cuando se aplazó,
  retiró o graduó), asistencias y notas (la nota sigue a la matrícula). Cada vista lo pasa con `@include('docente.partials.datos')`
  (`window.DOC_SERVIDOR`) y `public/assets/js/docente/datos.js` arma `DOC_DATOS` (deriva clases, %, estados de nota y
  pendientes, y los recalcula al guardar sin recargar) y `DOC_API`.
- **Guardar asistencia y notas** (`fetch` con CSRF): `POST docente/asistencia/{curso}` (`AsistenciaRequest`: grupo
  suyo y sin terminar, día de clase del horario y no futuro —la de hoy sí, aunque no haya terminado—, toda la lista de
  ese día marcada, excusa con motivo) y `POST docente/notas/{curso}/{modulo}` (`NotasRequest`, solo las que cambiaron:
  nota 1.0–5.0 con un decimal, sin nota la borra, habilitación solo con nota < 3.0 y módulo terminado, matrícula
  activa, módulo del programa, no por iniciar y que cursó en el grupo). Los errores salen uno a la vez con el nombre
  del estudiante (`asistencia.js` señala su fila). Guardan `registrado_por`, la fecha de la nota/habilitación cuando
  cambia y una línea en la auditoría (entidad `Curso`). No necesitó campos nuevos en la BD.
- **Diseño** (simplificado el 9-11-2026, decidido con el usuario; es la referencia de `DESIGN-PANELES.md`): cada cifra aparece **una sola vez** por pantalla y
  ningún elemento está "porque sí". Sin filas de tarjetas de cifras (el saludo del Inicio y la línea de resumen de cada
  pantalla las reemplazan), sin antetítulos sobre los títulos, sin chips de escuela ni círculos de letra de grupo.
  Color con la semántica del Admin: naranja = acción o pendiente, rojo = riesgo, verde = bien, gris/azul de la marca =
  neutro; la jornada no lleva color (se lee en la hora) y la escuela tampoco (`grp-esc-*` es un solo tono), salvo en
  **Mi horario** del Inicio, que es el mismo horario semanal de Admin › Horarios (`.hor-cal`/`.hor-bloque` copiados como
  `.ini-sem*`, `ini-c-{sigla}`): color por escuela (`grupo.sigla`), rango continuo de horas (mínimo 6), jornadas en la
  columna de horas, separador a las 12 y 18 y duración en cada bloque; lo propio del docente es la fecha de cada día. Asistencia:
  control segmentado P/T/A/E con la leyenda arriba (colores de la planilla del Admin: verde, ámbar, rojo, azul para la
  excusa) y el conteo de la clase en la barra de guardar. Notas: la malla sin fechas (van en el encabezado del módulo) y
  el resumen del módulo en una línea. En Mis grupos el número de pendientes va dentro del botón, y solo el botón con algo
  pendiente es naranja. Al agregar algo, que diga algo que no esté ya en la pantalla.

## Panel Estudiante (datos reales, 24-11-2026)

Contenido definido con el usuario el 24-11-2026, con backend real el mismo día. Todo lo que ve sale de la BD con las reglas
de la Secretaría (lo que ve el estudiante cuadra con Matrículas, Asistencia, Calificaciones, Pagos, Comunicados y Foro).
- Rutas `/estudiante` (`role:estudiante`, `transaccion`) a un controlador por pantalla en
  `Estudiante/{Inicio,Programa,Aula,Pagos,Comunicados,Foro}/` (Form Requests en `Requests/Estudiante/{Panel}/`), y
  `User::panelUrl()` → `estudiante.dashboard`. Layout, menú y barra copiados del Docente (`estudiante/layout.blade.php` +
  `partials/{sidebar,topbar}`, `layout.css`/`layout.js`); menú: Principal (Inicio), Mi formación (Mi programa, Aula
  virtual), Matrícula (Mis pagos) y Comunicación (Comunicados, Foro).
- **Datos** (`Academico/PanelEstudianteService::datos()`): la matrícula que se muestra es la de
  `EstudianteService::matriculaVigente` (sin matrícula, cada pantalla dice que no hay nada todavía); módulos con las fechas
  de `fechasModulos` y el estado de `Calificacion::estado()` (o `no_cursado`, `sin_nota`, `en_curso`, `por_iniciar`); clases
  desde su matrícula hasta que se aplazó/retiró (P/T/A/E, sin registrar, hoy, futura) y % de `AsistenciaService::porcentaje`;
  pagos con `PagoService::resumen` (cobros, recibos sin anulados, "A pagar este mes" = vencido + del mes, y cuántas cuotas
  quedarían vencidas con cada plan); comunicados visibles (su sede o rol, o su grupo; publicados, sin archivar, con matrícula
  vigente y cuenta activa); foro de su sede sin lo oculto. Cada vista lo pasa con `@include('estudiante.partials.datos')`
  (`window.EST_SERVIDOR`: datos, institución de Admin › Configuración, sede, logo y rutas) y
  `public/assets/js/estudiante/datos.js` arma `EST_DATOS` y `EST_API` (`fetch` con CSRF; 401/419 recarga, 422 muestra el
  primer error). Toda escritura responde `{ok, mensaje, datos}` y `datos.js` reemplaza `EST_DATOS` sin recargar.
- Pantallas (cada una con su Blade/CSS/JS y su prefijo):
  - **Inicio** (`dashboard`, `ini-`): saludo con resumen, clase de hoy, tu avance (promedio, módulos aprobados,
    asistencia), pendientes con enlace (por responder y entregar, por habilitar, pagos, comunicados sin leer) y el horario
    semanal del Docente con un solo grupo (tono neutro `ini-c-grupo`).
  - **Mi programa** (`programa/index`, `mpr-`): pestañas Notas (malla con nota, estado y lo que tiene en el Aula) y
    Asistencia (calendario del mes con P/T/A/E, lista de faltas, tardes y excusas); **boletín imprimible** (informativo:
    no reemplaza el certificado de la secretaría). `?vista=asistencia`.
  - **Aula virtual** (`aula/index`, `aul-`, `Academico/AulaService`): Por hacer / Resultados, chips por módulo, cajón para
    responder una evaluación de selección múltiple o entregar una actividad. Reglas acordadas: **una evaluación se responde
    una sola vez** hasta su cierre, la nota sale al enviar (`Evaluacion::nota`: 5,0 × aciertos ÷ preguntas, un decimal,
    mínimo 1,0) y las correctas solo viajan **después del cierre**; una actividad recibe y **cambia su archivo hasta la fecha
    límite** o hasta que el docente la califique (PDF, Word, JPG o PNG, ≤ 10 MB, disco privado `aula/{sede}/actividad-{id}`;
    el archivo viejo se borra después de guardar); ninguna calcula la nota del módulo (la sigue poniendo el docente en
    Notas). Solo responde o entrega quien tiene la matrícula **activa** en el grupo. `?id=evaluacion-ID` o
    `?id=actividad-ID` (evaluaciones y actividades son tablas distintas y repiten números), `?modulo=ID`. Pestaña
    **Material** (27-11-2026) con lo que sube el docente, y la guía de cada actividad en su cajón.
  - **Mis pagos** (`pagos/index`, `pag-`): solo consulta (se paga en la sede): elegir el plan una vez (`POST
    estudiante/pagos/plan`, `PagoService::elegirPlan`, con aviso de las cuotas que ya vencieron y confirmación), estado de
    cuenta (a pagar este mes, próxima cuota, pagado), cobros y recibos imprimibles con la hoja del recibo de la secretaría.
    Constancia y paz y salvo: en la secretaría.
  - **Comunicados** (`comunicados/index`, `com-`): lista + lector; se marca leído al abrir
    (`comunicado_lecturas`, `insertOrIgnore`: la primera lectura no cambia); adjunto por `estudiante.comunicados.adjunto`.
    Uno que no le corresponde responde 404. `?id=ID`.
  - **Foro** (`foro/index`, `for-`): lo lee cualquiera; abre temas, responde y reporta **solo con matrícula vigente**
    (`Requests/Estudiante/Foro/ExigeMatriculaVigente`, `participaForo`). El de su sede con `ForoService` y las Form Requests de la secretaría (las suyas las
    extienden: solo su sede, no fija temas); categorías, búsqueda, conversación en cajón, responder (no en temas cerrados ni
    ocultos), abrir tema y reportar con motivo (uno por mensaje, ni los propios ni los ocultos), con un adjunto PDF/JPG/PNG
    ≤ 5 MB. `?tema=ID`.
  - **Mi perfil** (`perfil`, `prf-`, el diseño del perfil de la secretaría y del Admin): cambia correo, celular y dirección;
    nombre y documento los corrige la secretaría (`ProfileUpdateRequest` no los acepta de un estudiante). Su contacto de
    emergencia, solo consulta (lo cambia la secretaría). Cambio de clave.
- **Documentos imprimibles** (`documento.css` + `documento.js`, `EST_DOC.imprimir`): la hoja de los documentos de la
  secretaría (encabezado con NIT y licencia de Admin › Configuración) se arma al final del `<body>` y solo sale al imprimir.
- **BD** (migración `2026_11_24_000001_completar_aula_virtual`): `evaluaciones.programa_modulo_id` + `descripcion`,
  `actividades.programa_modulo_id`, `entregas.archivo_nombre/archivo_tamano/calificado_en/calificado_por` (RESTRICT, como
  toda autoría). Las tablas de septiembre (`preguntas`, `opciones`, `respuestas_estudiante`) se usan como estaban.
- Pruebas: `tests/Feature/Estudiante/{PanelEstudiante,BackendEstudiante}Test.php`.

## Pendiente

- Superadmin: conectar Inicio, Estudiantes, Auditoría y Configuración a la BD
  (al hacerlo, cambiar los `{!! !!}` de las maquetas por salida escapada).
  Agregar Form Request y auditoría a Sedes.
- Aula virtual: si pierde la evaluación o la actividad no hay segundo intento (no se pidió); las notas del Aula no entran
  a ningún reporte de la Secretaría.
- Errores y riesgos abiertos, por prioridad: ver `HALLAZGOS.md`.
