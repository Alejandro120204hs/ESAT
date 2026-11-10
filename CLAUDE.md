# ESAT — Contexto del proyecto

Escuela Nacional de Educación ESAT (instituto técnico colombiano legalmente
licenciado — Lic. Funcionamiento 5850, NIT 900820764-1). El proyecto tiene
dos partes:

1. **Sitio público** (inicio, programas, Educación Continuada y escuelas) — rediseñado
   en noviembre de 2026 (ver "Sitio público" abajo). Todos los datos legales/de
   contacto/sedes ahí son **reales**, nunca inventados.
2. **Plataforma interna** (paneles por rol) — en construcción activa. Casi todo lo de
   abajo es sobre esta parte.

## Sitio público

- **Datos, no vistas sueltas.** `config/sitio.php`: escuelas (con su foto y sus programas
  `[nombre, slug]`), Educación Continuada, sedes (dirección y coordenadas reales), sedes de franquicia
  (`franquicias`: solo el municipio, la lista que envió ESAT el 10-11-2026; van en una franja bajo las sedes del inicio
  —cada chip pregunta por WhatsApp— y en una línea del footer, sin mapa hasta tener su dirección), WhatsApp y
  redes (vacías hasta tener las URL reales). `config/contenido.php`: el texto de cada programa
  (`programas.{slug}`) y de cada línea de Educación Continuada (`continuada.{slug}`). Header,
  footer, inicio y subpáginas leen de ahí: un programa o una sede se agrega en esos archivos,
  no en la vista.
- **Rutas**: `routes/web.php` genera una ruta por programa (`/{slug}`, nombre `programas.{slug}`),
  por línea (`continuada.{slug}`) y por escuela (`/escuelas/{slug}`, `escuelas.{slug}`), todas a
  `Sitio/PaginaController`. Plantillas: `paginas/{programa,continuada,escuela}.blade.php` y
  `paginas/partes/{hero,barra,migas,respaldo}`. El inicio es `welcome.blade.php`.
- **Fotos**: `public/assets/img` tiene el original y su `.webp` + `-960.webp` (procedencia en
  `.impeccable/procedencia-imagenes.md`). En las vistas, `$foto('archivo.png')` devuelve
  `src`/`srcset` del WebP o null si la foto no existe; sin foto, la página se arma con el ícono de
  la escuela (nunca un aviso de "foto pendiente"). Un programa sin foto propia usa otra foto
  real del mismo programa (`PaginaController::conPortada`).
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
- **Pendiente de ESAT**: cifras de estudiantes y egresados, foto y WhatsApp de cada sede, URL de
  redes, fotos de 8 programas de Ciencias y decidir el formulario de Novedades (hoy solo muestra
  la confirmación, no guarda el correo).

Stack: Laravel 12 + Breeze (Blade), MySQL (`esat`, XAMPP, `root` sin clave),
`php artisan serve --port=8765` para probar local. Todo en **español**,
nunca inglés, en toda la UI. (`.env.example` todavía trae sqlite y `APP_LOCALE=en`;
el `.env` local usa MySQL `esat`.) `database/esat.sql` es un volcado de la BD local con
datos personales, hashes y sesiones: **no subirlo a Git**.

Los problemas abiertos de la revisión completa (21-10-2026) están en `HALLAZGOS.md`,
con IDs (C1, A3, M2…) para referenciarlos en commits y PR.

## Roles y acceso

Enum `App\Enums\RolUsuario`: `superadmin | admin | estudiante | docente | acudiente`.
**El rol `admin` es la Secretaría de cada sede** (lo pidió el cliente): en pantalla siempre se llama
"Secretaría" (Superadmin › Secretarías, firmas "Secretaría de la sede", el foro y los comunicados
"como secretaría"); por dentro (enum, rutas, carpetas, código) sigue siendo `admin`. Hace todo lo del panel.
**El rol `admin_general` es el Admin de toda ESAT** (en pantalla "Admin"; una sola cuenta, sin sede). El
**Superadmin es el creador del sistema** (el desarrollador). Decidido con el usuario: el Admin ve todo ESAT
consolidado con filtro por sede, **también actúa** en las sedes ("Entrar a la sede": las mismas pantallas de la
secretaría con una franja de la sede elegida, sin duplicarlas; todo a su nombre en la auditoría), y **lo del
Superadmin pasa al Admin** (sedes, secretarías, inicio, estudiantes, auditoría, configuración); al Superadmin le
queda crear la cuenta del Admin y lo técnico. Panel en construcción (ver "Panel Admin general").
Cada rol tiene **su propia carpeta** de vistas/CSS/JS
(`resources/views/{rol}/`, `public/assets/css/{rol}/`, `public/assets/js/{rol}/`).
Redirección post-login por rol vía `User::panelUrl()`. Superadmin, admin y docente
tienen panel (ver "Panel Docente"); estudiante y acudiente
caen por ahora en el `/dashboard` de Breeze ("You're logged in!"). Docente, estudiante y
acudiente usan el `/profile` de Breeze. **Nadie elimina su propia
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
  ProgramaModulo, Curso, CursoSesion, DocentePerfil, EstudianteDocumento,
  Asistencia, Calificacion, PeriodoAcademico y actividades/evaluaciones —estos dos
  últimos sin uso todavía—), `Finanzas/` (Matricula, Pago, Recaudo, Devolucion),
  `Institucional/` (Sede, Configuracion), `Sistema/` (Auditoria). `User` se queda
  en `app/Models/`.
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
  no se digita: se guarda calculada con `Curso::finPara()` = inicio + duración del
  programa, al guardar el grupo y al cambiar la duración del programa (se recalculan
  sus grupos). Asistencia y Calificaciones la vuelven a calcular con `finPara()`. Editar un
  programa no puede invalidar sus grupos vigentes (cupo, jornadas, horas).
  Docente vía `curso_docente`
  (uno por grupo); horario semanal en `curso_sesiones`. Las reglas de horario
  (tope de horas del programa, cruces de docente) viven en
  `App\Services\Academico\HorarioService` para que Grupos y Horarios usen las mismas.
- Backend real hecho: Superadmin (Sedes, Administradores), Admin
  (Programas y Escuelas, Docentes, Grupos, Horarios, Estudiantes, Acudientes, Matrículas, Asistencia, Calificaciones, Pagos, Contabilidad, Comunicados, Foro, Reportes, Inicio)
  y Docente (Inicio, Mis grupos, Asistencia, Notas).
  Del panel Admin solo siguen siendo maqueta las pestañas Documentos de Programas y Docentes.
- **Estudiantes ≠ Matrículas.** Estudiantes (`Admin/Estudiantes/EstudianteController` +
  `EstudianteService`) solo registra/edita a la **persona**: cuenta (rol estudiante,
  **correo obligatorio** = usuario, clave = documento), acudiente (rol acudiente,
  `acudiente_estudiante` con parentesco; obligatorio si es menor), lista de documentos
  (`estudiante_documentos`, del estudiante, `EstudianteDocumento::REQUERIDOS`) y restablecer
  clave. **No matricula.** Matrículas = programa + grupo (`curso_estudiante`),
  cambio de grupo, estado de la matrícula (activo/aplazado/retirado/graduado con fecha y
  motivo) y pagos. **Sin todos los documentos requeridos entregados no se matricula**
  (`EstudianteDocumento::pendientesDe`, en frontend y en `MatriculaRequest`). En la ficha del estudiante la matrícula es solo consulta.
  `matriculas.plan_pago` queda null: lo **elige el estudiante desde su panel**. Mientras ese panel no
  exista, nada en la app elige el plan ni genera las primeras cuotas (`generarCuotas` solo se llama al
  reintegrar), así que una matrícula nueva no puede graduarse; los datos con plan se insertaron en la BD.
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
    guarda. Al **retirarse** antes de que venza, se anula. La misma regla corta también la
    sesión ya abierta (`EnsureAccountIsEnabled`, ver Login).
  - **Graduar** exige grupo terminado, todos los módulos aprobados, plan elegido con todas sus cuotas pagadas y paz y salvo.
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
  consulta**; la asistencia la toma el **docente** (panel Docente) y es **informativa: no bloquea
  nada** (ni grados ni matrículas; 80 % es solo referencia). Clases = días del horario entre el inicio del grupo
  y su fin (la de hoy cuenta cuando termina). Clase **sin registrar** = clase dictada sin ningún registro del
  grupo; no cuenta en el %. % = (presente + tarde) / (presente + tarde + ausente): la excusa no cuenta como falla
  (`AsistenciaService::porcentaje`, la misma regla en las fichas de Estudiantes/Acudientes/Matrículas). De cada
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
  + `pagos.css/js`): pestañas Por cobrar (vencido, este mes, por pagar, WhatsApp de recordatorio —al acudiente si es
  menor—), Pagos recibidos (recibos: ver/imprimir/anular), **Resumen de caja por rango de fechas** (no por día: los
  pagos llegan por mes/periodo; por medio de pago, quién registró, días con pagos; imprimible), Por programa y
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
  **docente** publica a sus grupos (`destino = grupo` + `curso_id`) desde su panel —todavía no existe esa función— y el
  admin solo los ve y los **archiva** (con motivo). Estados borrador / programado / publicado / archivado; un programado
  sale solo cuando llega `publicado_en` (`estadoActual()`, sin tareas). Los destinatarios se calculan al leer (estudiantes
  con matrícula vigente y cuenta activa, docentes y acudientes activos, o los estudiantes del grupo). `ComunicadoRequest`:
  un borrador solo pide destinatarios y título; programar exige fecha y hora futuras; un publicado no cambia de
  destinatarios ni vuelve a borrador; los de docentes y los archivados no se editan. Adjunto PDF/JPG/PNG ≤ 5 MB en el disco
  privado (`storage/app/private/comunicados/{sede}`), se descarga por `admin.comunicados.adjunto` solo desde la misma sede;
  se borra al quitarlo o al eliminar el borrador. El PUT llega como POST con `_method` (FormData). Solo se eliminan
  borradores. Lectura: `comunicado_lecturas.leido_en` (falta la ruta para marcarlo, cuando existan los paneles de
  Estudiante/Acudiente/Docente) y WhatsApp a quien no lo ha leído (al acudiente si es menor). En la BD (sede 7): 18
  comunicados y 240 lecturas simuladas (el usuario lo eligió); quien leyó tiene `ultimo_acceso` posterior y los estudiantes
  con derecho vencido no leen. Pruebas en `tests/Feature/Admin/ComunicadosTest.php`.
- **Foro (Comunicación)** (`Admin/Foro/ForoController`, `Comunicacion/ForoService`, modelos `Comunicacion/{ForoTema,
  ForoMensaje,ForoReporte}`, tablas `foro_temas` + `foro_mensajes` + `foro_reportes`, vista `admin/foro/index.blade.php` +
  `foro.css/js`). Decidido con el usuario: **un solo foro general por sede**; participan estudiantes, docentes, acudientes y
  administración; todos abren temas (`ForoTema::CATEGORIAS`: Dudas académicas, Prácticas y salidas, Trámites y pagos, Vida en
  la sede) y responden; un adjunto por mensaje (PDF/JPG/PNG ≤ 5 MB, disco privado `foro/{sede}`, se descarga por
  `admin.foro.adjunto`). El primer mensaje (menor id) abre el tema: ocultarlo oculta el tema. Moderación **después, con
  reportes** (uno por persona y mensaje; pendiente → atendido al ocultar / descartado): el admin abre temas (`TemaRequest`),
  responde (`RespuestaRequest`: no en temas cerrados ni ocultos), fija y cierra/reabre (`EstadoTemaRequest`), oculta con
  motivo (`OcultarMensajeRequest`), restaura y descarta reportes; todo en la auditoría. En `foro.js` cada persona va con una
  clave `e|d|a|x` + id (`ForoService::personas`). En la BD (sede 7): 16 temas, 43 mensajes, 6 reportes y 4 adjuntos reales;
  cada persona es de la sede, activa, el estudiante con matrícula vigente y sin derecho vencido, y su `ultimo_acceso` es
  posterior a lo que escribió. Falta: que los paneles de Estudiante/Docente/Acudiente lean, publiquen y reporten. Pruebas en
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
  programa (`estudiantesActivos`) y pendientes de hoy con enlace (reportes del foro, cobros que vencen en 7 días, clases sin
  asistencia y módulos sin nota de los resúmenes de Asistencia/Calificaciones, habilitaciones, comunicado programado).
  Accesos rápidos a Estudiantes, Pagos, Programas y Reportes. Las barras entran con `transform` (no animan alto/ancho).
  Pruebas en `tests/Feature/Admin/InicioTest.php`.
- **Exportar a Excel**: Matrículas, Docentes, Estudiantes, Acudientes, Asistencia, Calificaciones y Pagos
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
  guarda `users.ultimo_acceso`. El middleware `EnsureAccountIsEnabled` (agregado al grupo `web` en
  `bootstrap/app.php`) revisa lo mismo **en cada petición**: si la cuenta se desactiva o al estudiante
  se le vence el derecho, cierra la sesión abierta (y con ella la cookie "Recordarme") y vuelve al login
  con el motivo; a un `fetch` le responde 401. La regla vive en un solo lugar,
  `EnsureAccountIsEnabled::motivo()`, y `LoginRequest` usa la misma: si se agrega otra razón para
  bloquear el acceso, va ahí. `User::$attributes` pone `activo = true` también en memoria; en
  pruebas, un modelo que otra petición cambió hay que `refresh()`-arlo antes de `update()`.
  `pagos:vencer` está programado en `routes/console.php`, pero nada ejecuta el scheduler.
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
(no hay columna `name`). Las demás reglas del Admin están en
`tests/Feature/Admin/{Matriculas,Pagos,Contabilidad,Comunicados,Foro,Reportes,Inicio,Estudiantes,Acudientes,Asistencia,Calificaciones}Test.php`
(datos de apoyo en `DatosAcademicos.php`). Superadmin: `tests/Feature/Superadmin/AdministradoresTest.php`
(Sedes todavía sin pruebas). Sesión y cuentas desactivadas: `tests/Feature/Auth/CuentaHabilitadaTest.php`.
Panel Admin general: `tests/Feature/AdminGeneral/`. Panel Docente: acceso por rol y redirección en `tests/Feature/Docente/PanelDocenteTest.php`; lo que ve y lo que
guarda (asistencia y notas, con sus reglas y lo que luego consulta el Admin) en `tests/Feature/Docente/RegistroDocenteTest.php`.
Sitio público (una página por programa, línea y escuela, rutas de siempre, cierre del footer):
`tests/Feature/Sitio/PaginasTest.php`.

Las pruebas necesitan `pdo_sqlite`, que está desactivado en `C:\php\php.ini`:
sin él fallan casi todas (147 de las 148 actuales). Activarlo en el `php.ini` o correr
`php -d extension=pdo_sqlite vendor/bin/phpunit`.

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
Estudiantes, Acudientes y Matrículas, `fmtTel()` formatea solo si son 10 dígitos y
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
Con departamento/ciudad está en `admin/estudiantes.js`, `admin/acudientes.js`,
`admin/docentes.js` y en los dos del Superadmin (`sedes-programas.js`,
`administradores.js`). **Copiar desde uno del Admin**: los del Superadmin todavía
no tienen `alScroll` (HALLAZGOS M3). Copiar el patrón (`initCombobox` +
`poblarCiudades*` + `alScroll`), no reinventarlo.

## Patrón de fila clicable

En las tablas de solo-editar/ver (Sedes, Administradores, Estudiantes,
Auditoría), toda la fila abre el modal/asistente correspondiente, no solo
el ícono. Cuidado: si una fila tiene además un control propio (como el
interruptor activo/inactivo en Administradores), ese control debe llamar
`e.stopPropagation()` para no disparar también el click de la fila.

## Estado actual del panel Superadmin

Sedes y Administradores tienen backend real (`SedeController`,
`AdministradorController`, `fetch` con CSRF), todavía sin Form Requests ni
auditoría (HALLAZGOS M9). Editar y activar/desactivar en Administradores solo
aceptan cuentas con rol admin (`asegurarAdmin`, 404 para cualquier otra). Inicio, Estudiantes, Auditoría y Configuración siguen
siendo maqueta con datos fijos en el Blade (rutas closure en `routes/web.php`),
aunque `auditorias`, `pagos` y `recaudos` ya tienen datos reales para conectarlas.


- **Inicio** (`dashboard.blade.php`): recaudo del mes + gráfica con tooltip
  al pasar el cursor, estado de cartera, alertas paginadas (4 por página),
  actividad reciente, franja de sedes/escuelas/programas/administradores.
- **Sedes** (`sedes-programas.blade.php`, ruta `/superadmin/sedes`): crear y
  editar sedes, sin eliminar ni desactivar (nombre, departamento, ciudad, dirección) con buscador +
  paginación + modal crear/editar. Las 4 sedes reales: Bogotá, Sasaima,
  Guaduas, La Dorada.
- **Administradores** (`administradores.blade.php`): tabla + asistente de
  4 pasos (Datos personales → Identificación → Contacto → Sede y
  confirmación), con edad calculada en vivo desde la fecha de nacimiento
  (nunca guardada como número fijo), validación por paso, resumen final.
  Los administradores salen de la BD. La alerta "La Dorada sin admin" del
  Inicio sigue siendo fija.
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
    y restablecer clave.
  - **Programas** (`programas/index`, `prg-`): catálogo real; crear/editar en 3 pasos (datos y jornadas, duración y
    precio, malla editable), activar/desactivar (no con grupos vigentes), y en el detalle a qué sedes se ofrece
    (interruptor; no se quita de una sede con grupos vigentes). Editar no invalida grupos vigentes (cupo ≥ estudiantes
    del grupo más lleno, no se quita una jornada en uso). Pestaña Escuelas: crear, editar, eliminar solo sin programas ni docentes.
  - **Decidido con el usuario**: el Admin **crea y edita igual que la secretaría** (mismas reglas), pero en cualquier
    sede (la elige en el formulario). Matricular, cobrar, grupos y horarios siguen por "Entrar a la sede".
  - **Estudiantes** (`estudiantes/index`, `est-`): todas las sedes (filtros sede/programa/matrícula/pagos, ficha con
    datos, matrícula y pagos, exportar) + registrar y editar a la persona en 2 pasos (correo obligatorio, clave =
    documento, TI solo si es menor, acudiente obligatorio si es menor; no cambia de sede con matrícula vigente).
  - **Acudientes** (`acudientes/index`, `acu-`): crear/editar en 3 pasos (con sede y, opcional, estudiantes a cargo),
    vincular (reemplaza al acudiente anterior) y desvincular (nunca a un menor), activar/desactivar cuenta, restablecer clave.
  - **Docentes** (`docentes/index`, `dcn-`): crear/editar en 3 pasos (sede, escuela, título, especialidad, vinculación),
    activar/desactivar (no con grupos vigentes), restablecer clave; no cambia de sede con grupos vigentes allá.
  - En los tres, documento y correo no se repiten entre ninguna cuenta: lo revisa el servidor (el navegador ya no recibe
    los documentos y correos de todas las cuentas para eso).
  - **Académico** (`academico/index`, `aca-`): solo consulta; sede + pestañas Grupos (avance por módulo),
    Asistencia (informativa) y Notas (módulos sin nota, por habilitar), detalle del grupo con su malla, exportar.
  - **Foro** (`foro/index`, `for-`): foros de todas las sedes; modera (ocultar con motivo —el primer mensaje oculta el
    tema—, restaurar, descartar reportes, fijar, cerrar/reabrir) y participa como Admin (responder, abrir temas).
  - **Finanzas** (`finanzas/index`, `fin-`): sede + mes; pestañas Recaudo (recibo imprimible), Cartera (a hoy, por
    edades, WhatsApp al acudiente si es menor), Contabilidad (ingresos − egresos) y Devoluciones. Solo consulta.
  - **Comunicados** (`comunicados/index`, `com-`): publicar a todas o algunas sedes por rol (ahora, programar,
    borrador); un publicado no cambia de destinatarios; archivar con motivo; eliminar solo borradores propios.
  - **Reportes** (`reportes/index`, `rep-`): por mes y sede: matriculados, novedades, recaudo, ingresos y egresos,
    cartera por edades (a hoy). Imprimir y Excel.
  - **Auditoría** (`auditoria/index`, `aud-`): filtros de periodo, sede, persona y acción; detalle; exportar.
  - **Configuración** (`configuracion/index`, `cfg-`): institución (datos reales), cuota del sistema, reglas de acceso
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
- El Superadmin todavía conserva Sedes, Administradores y sus maquetas: quitarle lo que pasó al Admin queda pendiente.
- **Datos (backend real, 2026-11-10)**: cada controlador pasa lo de su pantalla con `window.GEN_DATOS = @json($datos)`
  (la forma que leen los JS; ya no hay datos de ejemplo). Servicios en `app/Services/General/`: `EstructuraService` (sedes
  con sus cifras de hoy y del cierre del mes anterior —activos, grupos, cupos, ingresos de 6 meses, mora con
  `InicioService::mora`—, secretarías, escuelas, programas con malla y `con_notas`), `PersonasService` (estudiantes con
  su matrícula, pagos de `PagoService::resumen`, asistencia y promedio; recibos con quién los registró; acudientes;
  docentes con sus grupos), `AcademicoService` (grupos con los resúmenes de `AsistenciaService`/`CalificacionService` de
  cada sede), `FinanzasService` (Finanzas y Reportes: recibos, egresos sin anular y devoluciones del último año),
  `ComunicacionService` (comunicados agrupados por lote) y `SupervisionService` (auditoría del último año).
- **Escrituras**: `fetch` con CSRF (`enviar()` en el JS de cada pantalla; 401/419 recarga, 422 marca el campo) y al
  terminar `recargarConAviso()` (los datos y cifras vuelven del servidor). **Las reglas son las de la secretaría**: las Form
  Requests del panel general **extienden** las de `Admin/` y solo cambian la sede (las de personas leen la sede de
  `sedeId()`, que el general sobrescribe con la elegida) o agregan lo propio (malla del programa, sedes de un comunicado).
  Todo queda en la auditoría con la sede afectada (`Auditoria::registrar(..., $sedeId)`).
  - Programas: la malla se guarda en orden (conserva ids), las horas del programa = suma de módulos, y con notas puestas la
    malla no cambia. Escuelas: sigla automática y fija (`siglaLibre`), no se eliminan con programas ni docentes.
  - Estudiantes: el formulario pide lo mismo que la secretaría (nacimiento, residencia con el listado de Colombia —regla
    `App\Rules\CiudadDeColombia`—); un menor puede registrarse con un acudiente nuevo en el mismo formulario.
  - Comunicados a varias sedes: **una fila por sede con el mismo `comunicados.lote`** (migración `2026_11_10_000002`);
    el Admin los ve, edita, archiva y elimina como uno solo. Destinos: todos (= sede), estudiantes, docentes, acudientes
    (no hay destino "secretarías"). Sin adjunto. La secretaría no edita ni elimina los del Admin (sí los archiva en su sede).
  - Foro: `ForoService` de la secretaría con Form Requests que extienden las suyas; sin adjuntos desde el panel general.
  - Configuración: datos de la institución en `configuraciones` (`institucion_*`, por defecto los reales de
    `Configuracion::INSTITUCION`) y salen en el encabezado de los documentos impresos de la secretaría; cuota del sistema;
    reglas de acceso (`seguridad_intentos_maximos`, `seguridad_dias_inactividad`). **Bloqueo de cuentas real**
    (`Sistema/BloqueoService`): intentos fallidos seguidos (`LoginRequest`) e inactividad (solo cuentas que ya ingresaron);
    `EnsureAccountIsEnabled::motivo` no deja entrar a una bloqueada (el aviso solo sale con la clave correcta); el Admin y el
    superadmin nunca se bloquean; el Admin desbloquea. Restablecer clave (`Sistema/SesionesService`) también cierra sus sesiones.
- Pruebas: `tests/Feature/AdminGeneral/{PanelGeneral,EntrarSede,Estructura,Personas,ComunicacionConfiguracion}Test.php`.

## Panel Docente (datos reales)

- Rutas `/docente` (`role:docente`) a un controlador por pantalla en `Docente/{Inicio,Grupos,Asistencia,Notas}/`, y
  `User::panelUrl()` → `docente.dashboard`. Layout propio: `docente/layout.blade.php` +
  `partials/{sidebar,topbar}`, `docente/layout.css` (el del Admin sin los menús flotantes;
  la animación de entrada se llama `.app-anim`) y `layout.js`.
- Pantallas (cada una con su Blade/CSS/JS y su prefijo):
  - **Inicio** (`dashboard`, `ini-`): clases de hoy, pendientes (asistencia sin registrar,
    notas por poner, habilitaciones), estudiantes bajo el 80 % y su horario semanal.
  - **Mis grupos** (`grupos/index`, `grp-`): tarjetas por grupo (Activo · Por iniciar · Finalizado) y ficha con
    Estudiantes, Malla y Horario.
  - **Asistencia** (`asistencia/index`, `tas-`): calendario de clases del grupo y planilla
    P/T/A/E (excusa con motivo obligatorio), con "Todos presentes".
  - **Notas** (`notas/index`, `not-`): por módulo, de 1.0 a 5.0 con un decimal (acepta coma),
    habilitación solo si la nota es menor a 3.0 y el módulo ya terminó, y "Planilla completa"
    (estudiantes × módulos).
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
- **Guardar** (lo único que escribe, `fetch` con CSRF): `POST docente/asistencia/{curso}` (`AsistenciaRequest`: grupo
  suyo y sin terminar, día de clase del horario y no futuro —la de hoy sí, aunque no haya terminado—, toda la lista de
  ese día marcada, excusa con motivo) y `POST docente/notas/{curso}/{modulo}` (`NotasRequest`, solo las que cambiaron:
  nota 1.0–5.0 con un decimal, sin nota la borra, habilitación solo con nota < 3.0 y módulo terminado, matrícula
  activa, módulo del programa, no por iniciar y que cursó en el grupo). Los errores salen uno a la vez con el nombre
  del estudiante (`asistencia.js` señala su fila). Guardan `registrado_por`, la fecha de la nota/habilitación cuando
  cambia y una línea en la auditoría (entidad `Curso`). No necesitó campos nuevos en la BD.
- **Diseño** (simplificado el 9-11-2026, decidido con el usuario): cada cifra aparece **una sola vez** por pantalla y
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
- Su perfil todavía es el de Breeze (roto, HALLAZGOS M7).

## Pendiente

- Paneles Estudiante y Acudiente: sin construir.
  - Estudiante: elegir plan de pago (genera las cuotas). El bloqueo por derecho
    vencido ya corta también las sesiones abiertas (`EnsureAccountIsEnabled`).
- Docente: su perfil.
- Superadmin: conectar Inicio, Estudiantes, Auditoría y Configuración a la BD
  (al hacerlo, cambiar los `{!! !!}` de las maquetas por salida escapada).
  Agregar Form Requests y auditoría a Sedes y Administradores.
- Foro: leer, publicar y reportar desde los paneles de Estudiante/Docente/Acudiente.
- Comunicados: marcar como leído desde los paneles de Estudiante/Acudiente/Docente y que el docente publique a sus grupos.
- Errores y riesgos abiertos, por prioridad: ver `HALLAZGOS.md`.
