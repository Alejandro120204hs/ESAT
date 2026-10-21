# Hallazgos — revisión completa del proyecto

Revisión del 21-10-2026 sobre `main` (después del PR #41). Se cubrió:
- el backend: finanzas, académico, personas, auth e infraestructura;
- el frontend: Admin, Superadmin, sitio público y pantallas de autenticación;
- el volcado `database/esat.sql`;
- la suite de pruebas.

No se modificó código para hacer esta revisión.

- **✓** = comprobado a mano en el código o en los datos.
- Lo que no tiene ✓ salió de la revisión por áreas: conviene confirmarlo al corregirlo.
- Los IDs (C1, A3…) sirven para referenciar cada hallazgo en commits y PR.

| Severidad | Cantidad | Qué agrupa |
|---|---|---|
| Crítico | 5 | Seguridad explotable — **los 5 corregidos** en la rama `fix/hallazgos-criticos` |
| Alto | 10 | Seguridad de datos, dinero que se pierde o queda mal, reglas de grado |
| Medio | 18 | Funcionalidad rota, alcance por sede, concurrencia, UI que miente |
| Bajo | 19 | Bordes, rendimiento, accesibilidad, restos |
| Sitio público | 7 | Fotos, peso, SEO |
| Repo y entorno | 5 | Peso del repo, configuración local |

---

## 1. Críticos (seguridad)

### C1 ✓ XSS almacenado por el teléfono
**Área:** el dato entra por el perfil (cualquier rol) y el código se ejecuta en el panel Admin.

**Qué pasa:**
- Cualquier usuario con sesión, incluidos estudiante, acudiente y docente, puede llamar `PATCH /profile` (`routes/web.php:276`).
- `ProfileUpdateRequest.php:22` acepta `telefono` como texto libre de hasta 30 caracteres.
- `fmtTel()` devuelve el valor tal cual si no tiene 10 caracteres (`estudiantes.js:109`, `acudientes.js:109`).
- Ese valor se pinta con `innerHTML` y en los `href` sin escapar:
  - `estudiantes.js:323,346,353,651,673,694,849`
  - `acudientes.js:340,562,598`
  - `matriculas.js:686,688`
- Con algo como `<img src=x onerror=…>` (28 caracteres) se ejecuta código en la sesión del admin apenas abre Estudiantes.

**Arreglo:**
- Validar `regex:/^3\d{9}$/` también en `ProfileUpdateRequest`.
- En el JS, pasar todo teléfono por `esc()`, o quedarse solo con los dígitos dentro de `fmtTel`/`waLink`/`tel:`.

**Estado: corregido.**
- `ProfileUpdateRequest` deja solo los dígitos y exige `^3\d{9}$` (o vacío).
- `fmtTel()` formatea solo si son 10 dígitos y escapa todo lo demás (Estudiantes, Acudientes, Matrículas).
- `waLink()` y `tel:` usan solo dígitos (también en Asistencia, Calificaciones y Pagos).
- Prueba: `ProfileTest::test_el_telefono_del_perfil_solo_acepta_un_celular`.
- Verificado en el navegador con datos maliciosos ya guardados en BD: el texto se ve, no se ejecuta.

### C2 ✓ XSS por el nombre de una escuela
**Área:** Admin › Programas › Escuelas.

**Qué pasa:**
- `escuelas.js:64-66` mete `esc.nombre` en `confirmEl.innerHTML`, y `EscuelaRequest` solo exige `min:3|max:80`.
- Las escuelas son un catálogo global, así que el admin de una sede ataca a los de las demás.
- Hay un caso parecido de menor riesgo, porque son valores del servidor: `programas.js:253-263` pinta `g.nombre`, `g.jornada`, `g.inicio` y `g.fin` sin escapar.

**Arreglo:** `esc(esc.nombre)` y lo mismo en `programas.js`.

**Estado: corregido.**
- `PRG_UTIL` expone `esc`, y `escuelas.js` escapa el nombre antes del `innerHTML`.
- Las tarjetas de grupo de `programas.js` escapan nombre, jornada y fechas.
- Verificado en el navegador con el modal de eliminar.

### C3 ✓ Cualquier usuario puede borrar su cuenta y su historial financiero
**Área:** perfil de Breeze (estudiante, docente, acudiente).

**Qué pasa:**
- `DELETE /profile` (`routes/web.php:277`) llama a `ProfileController::destroy` (`ProfileController.php:51`), que hace un borrado definitivo.
- El botón está visible en `profile/edit.blade.php:24`.
- Por las FK en cascada se borran matrículas (`matriculas.estudiante_id`), cobros, recaudos, `recaudo_pago`, devoluciones, calificaciones, asistencias y documentos.
- Los recibos desaparecen y no queda auditoría. `ProfileTest.php:67` da este comportamiento por bueno.

**Arreglo:** quitar la ruta y el formulario para todos los roles, o al menos para estudiante, docente y acudiente. Desactivar una cuenta es trabajo del admin.

**Estado: corregido.**
- Se quitaron `DELETE /profile`, `ProfileController::destroy` y el parcial `delete-user-form`.
- Prueba: `ProfileTest::test_el_usuario_no_puede_eliminar_su_cuenta` (responde 405 y la cuenta sigue).

### C4 ✓ `AdministradorController` acepta cualquier usuario
**Área:** Superadmin › Administradores.

**Qué pasa:**
- `update` (`AdministradorController.php:63`) y `toggleActivo` (`:96`) reciben cualquier `User` sin revisar su rol.
- Con `/superadmin/administradores/{id}` se puede editar o cambiar de sede a estudiantes y docentes.
- También se puede desactivar al propio superadmin y quedar sin acceso.

**Arreglo:** `abort_unless($administrador->role === RolUsuario::Admin, 404)` en ambos métodos, más lo de M9.

**Estado: corregido.**
- `update` y `toggleActivo` llaman `asegurarAdmin()` y responden 404 si la cuenta no es de administrador.
- Pruebas: `tests/Feature/Superadmin/AdministradoresTest.php`, las primeras del Superadmin.
- M9 (Form Requests y auditoría) sigue abierto.

### C5 ✓ Desactivar una cuenta no corta el acceso ya abierto
**Área:** login y sesión (todos los roles).

**Qué pasa:**
- `activo` y el derecho vencido solo se revisan dentro de `LoginRequest::authenticate` (`LoginRequest.php:57-75`); no hay middleware que lo haga en cada petición.
- Las sesiones abiertas siguen funcionando.
- La cookie "Recordarme" (`login.blade.php:87`) vuelve a autenticar sin pasar por `LoginRequest`, porque `remember_token` no cambia al desactivar.
- Ejemplo: un admin desactivado por el superadmin conserva todo su panel.

**Arreglo:**
- Un middleware en el grupo `auth` que cierre la sesión si `activo = 0` (y, para estudiantes, si `derechoVencido`).
- Regenerar `remember_token` al desactivar.

**Estado: corregido.**
- `App\Http\Middleware\EnsureAccountIsEnabled` (agregado al grupo `web`) revisa en cada petición lo mismo que el login.
  - Si la cuenta ya no puede estar adentro, cierra la sesión (lo que cambia el `remember_token`) y vuelve al login con el motivo. En peticiones `fetch` responde 401.
  - La regla vive en `EnsureAccountIsEnabled::motivo()`, y `LoginRequest` usa la misma.
- `User::$attributes` pone `activo = true` también en memoria (igual que el default de la columna).
- Pruebas:
  - `tests/Feature/Auth/CuentaHabilitadaTest.php`
  - `MatriculasTest::test_con_el_derecho_vencido_tambien_pierde_la_sesion_abierta`

---

## 2. Altos

### Seguridad de datos

#### A1 ✓ Las claves iniciales nunca se cambian
- En el volcado, **65 de 66 cuentas** siguen con la clave igual a su número de documento. No existe ningún mecanismo que obligue a cambiarla en el primer ingreso.
- El límite de login es de 5 intentos por correo+IP, y un documento de 6 a 11 dígitos suele ser fácil de conseguir.
- El superadmin tampoco tiene cómo restablecer la clave de un admin.

**Arreglo:** marcar las cuentas con clave inicial y obligar a cambiarla al primer ingreso.

#### A2 ✓ `database/esat.sql` no está en `.gitignore`
- El volcado trae datos personales, hashes de contraseñas, 14 sesiones con su contenido y caché. Hoy está sin seguimiento (`??`), así que un `git add .` lo subiría.

**Arreglo:** agregar `/database/*.sql` a `.gitignore`.

### Dinero y lógica de negocio

#### A3 ✓ Cambiar la duración del programa impide graduar
- Las cuotas se generan con la duración vigente en ese momento (`PagoService.php:64`).
- Para graduar, `EstadoRequest.php:77` compara contra la duración **actual** (`duracion_meses`/`duracion_periodos`).
- `ProgramaRequest` no impide cambiar la duración cuando hay matrículas.
- Ejemplo: un estudiante pagó sus 12 cuotas, el programa pasa a 18 meses, y ya no se puede graduar.

**Arreglo:** comparar contra el número de cuotas que realmente se generaron (no anuladas), o bloquear el cambio de duración si hay matrículas vigentes.

#### A4 ✓ Anular un recibo en una matrícula aplazada o retirada revive deuda
- `anularRecaudo` (`PagoService.php:192-205`) pasa los cobros `pagado` a pendiente o vencido sin importar el estado de la matrícula. `AnularPagoRequest` solo lo bloquea en las graduadas.
- Las cuotas que vencen después del aplazamiento o retiro, y el derecho, vuelven a ser deuda.
- En una retirada (estado final) nada las vuelve a anular, así que el paz y salvo queda bloqueado para siempre.
- En una aplazada, el derecho puede volver a vencer y bloquear el login.

**Arreglo:** al anular un recibo en una matrícula aplazada o retirada, devolver a `anulado` (no a pendiente) los cobros que ya estaban anulados por ese cambio de estado.

#### A5 ✓ `adelantado()` olvida cuotas pagadas cuando hay dos cambios de estado
- `adelantado()` toma la **última** `estado_fecha` (`PagoService.php:228`), y cada cambio de estado la sobrescribe (`MatriculaService.php:90,134`).
- Ejemplo:
  1. El estudiante pagó noviembre, diciembre y enero.
  2. Se aplaza el 20-oct: el adelantado son las tres cuotas.
  3. Se retira el 20-dic: solo queda reconocido enero.
- Lo pagado para el periodo aplazado ya no se puede devolver ni trasladar.

**Arreglo:** marcar en el cobro (o en el pivote) qué cambio de estado lo dejó adelantado, en vez de recalcularlo con la fecha más reciente.

#### A6 Saldo huérfano al anular la matrícula nueva de un cambio de programa
- `EstadoRequest.php:85-90` solo revisa los recaudos. El dinero que llegó por traslado (`saldo_favor` y cobros pagados con él) no es un recaudo, así que la anulación pasa.
- Todas las vistas excluyen las matrículas anuladas (`CarteraService.php:24`), y las devoluciones exigen retirada o aplazada. Ese dinero desaparece del sistema.

**Arreglo:** no permitir anular si `saldo_favor > 0` o si tiene cobros con `abonado > 0`.

#### A7 Editar un horario reescribe el pasado
- `HorarioController.php:58` borra y recrea las sesiones.
- Asistencia (`AsistenciaService.php:55`) y Calificaciones (`CalificacionService.php:31`) recalculan las clases dictadas y las fechas de los módulos con el horario **actual**:
  - los registros en días que se quitaron dejan de contar;
  - los días pasados que se agregaron aparecen como "sin registrar".
- Mover la `fecha_inicio` de un grupo que ya tiene asistencia tampoco está protegido (`GrupoRequest`).

**Arreglo:** guardar desde qué fecha rige cada horario, o bloquear la edición de días y fechas ya dictados.

#### A8 Un grupo se puede finalizar en cualquier momento
- `GrupoController.php:134` no revisa fechas ni estudiantes activos.
- `EstadoRequest.php:53` se salta la revisión de fecha si el grupo está `finalizado`, así que se puede graduar antes de que termine.
- El grupo además desaparece de Asistencia (`AsistenciaService.php:89`) mientras sus estudiantes siguen activos.

**Arreglo:** permitir finalizar solo desde `fecha_fin`, o exigir que no queden matrículas activas.

#### A9 Derecho a medio pagar y retiro antes de clases
- `anularDerechoSinCausar` solo anula el derecho si `abonado = 0` (`PagoService.php:412`). CLAUDE.md dice que al retirarse antes de que venza "se anula".
- Con un abono parcial, el derecho queda pendiente, vence al iniciar el grupo y el retirado aparece como deudor.
- Lo abonado no se puede devolver, porque `adelantado()` (`:231-232`) solo cuenta cobros anulados o cuotas pagadas.
- Si el derecho estaba en espera (aplazado y luego retirado), queda pendiente sin fecha para siempre.

#### A10 ✓ No hay forma en la app de elegir el plan de pago
- `generarCuotas` solo se llama al reintegrar (`MatriculaService.php:115`), y nada en producción escribe `plan_pago`.
- Hasta que exista el panel Estudiante, ninguna matrícula nueva tiene cuotas ni puede graduarse: graduar exige plan y cuotas pagadas.
- Las 24 matrículas con plan del volcado se insertaron directo en la BD.
- Es una pieza pendiente de diseño, pero bloquea el flujo completo.

---

## 3. Medios

### M1 ✓ "Desvincular" sigue visible para menores (bug de `[hidden]`)
`acudientes.css:12` le pone `display:inline-flex` a `.acu-btn` y no existe `.acu-btn[hidden]`, así que `acudientes.js:698` (`btnConfirmar.hidden = menor`) no tiene efecto. El servidor rechaza la acción, pero el botón se ve.

**Arreglo:** `.acu-btn[hidden] { display: none; }`.

### M2 ✓ La casilla "Ya pagó… ($0)" aparece en programas sin derecho (bug de `[hidden]`)
`matriculas.css:520` le pone `display:flex` a `.mat-check` y no existe `.mat-check[hidden]`, así que `matriculas.js:1335` no lo oculta. Al marcarla se abre `#nm-pago` (`matriculas.js:1308`).

**Arreglo:** `.mat-check[hidden] { display: none; }`.

### M3 ✓ Los combobox del Superadmin no tienen `alScroll`
- `administradores.js:111` y `sedes-programas.js:195` todavía usan `addEventListener('scroll', posicionar, true)`, así que la lista vuelve al inicio y no deja bajar.
- Las 10 copias del Admin ya tienen `alScroll`.

### M4 Escape y listeners del combobox en drawers
- En Acudientes › Vincular, el Escape del drawer (`acudientes.js:1037`) se registra antes que el del combobox dinámico. Con la lista abierta, Escape sale de la acción en vez de cerrar la lista. Horarios lo resuelve con la revisión de `.app-combobox.is-open` (`horarios.js:1164`).
- `initCombobox` agrega listeners de click y keydown al `document` en cada llamada. Las llamadas dinámicas (`acudientes.js:678`, `horarios.js:778-779`) los acumulan.

### M5 ✓ Eliminar una escuela con docentes da error 500
`EscuelaController::destroy` (`:35`) solo revisa los programas. `docente_perfiles.escuela_id` es una FK restrict (`2026_10_06_000006`), así que la BD lanza `QueryException`.

**Arreglo:** contar también los docentes y responder 422.

### M6 ✓ Se puede desmarcar un documento después de matricular
- `EstudianteController::documento` (`:120`) alterna `entregado_en` sin mirar si hay una matrícula vigente.
- En el volcado hay 6 matrículas vigentes sin los 3 documentos:
  - 16 y 26 se desmarcaron después de matricular;
  - 13, 27, 33 y 35 se insertaron así directo en la BD.
- Falta decidir si se permite (por ejemplo, un documento devuelto) y, si sí, avisarlo en la ficha.

### M7 El formulario de perfil de Breeze no funciona
El formulario envía `name` (`update-profile-information-form.blade.php:22`), pero `ProfileUpdateRequest.php:20-21` exige `nombres` y `apellidos`. Para estudiante, docente y acudiente siempre falla, y el error no se ve en el formulario.

### M8 Los roles sin panel caen en el `/dashboard` de Breeze
`User::panelUrl()` (`User.php:133`) manda a estudiante, docente y acudiente a `route('dashboard')`, que dice "You're logged in!" en inglés.

### M9 Superadmin sin Form Requests ni auditoría
- `SedeController` y `AdministradorController` validan dentro del controlador y no llaman `Auditoria::registrar`. Las dos cosas van en contra de CLAUDE.md.
- La validación es más débil que en Admin:
  - `numero_documento` acepta cualquier texto, y ese texto queda como clave;
  - el teléfono no tiene regex;
  - los tipos de documento no incluyen `PPT`;
  - `fecha_nacimiento` no tiene `before:today`.

### M10 Superadmin no muestra errores y su interruptor puede mentir
- Los campos vacíos en Sedes se ignoran sin mensaje (`sedes-programas.js:423-428`).
- Un 422 (nombre, documento o correo repetido) solo reactiva el botón (`sedes-programas.js:451-457`, `administradores.js:454-460`).
- El interruptor activo/inactivo cambia antes de la respuesta (`administradores.js:59-71`). Ante un 419, 403 o 500 queda mostrando un estado falso.

### M11 Alcance entre sedes en Escuelas y Programas
- Las escuelas no están limitadas por sede: cualquier admin renombra o elimina cualquier escuela (`EscuelaController.php:24,35`).
- Un programa compartido se edita para todas las sedes a la vez: precio, duración y cupo (`ProgramaController.php:66`).
- Cambiar la duración recalcula `fecha_fin` de **todos** los grupos, incluidos los finalizados y los de otras sedes (`ProgramaController.php:77`).
- No hay forma de ofertar en la sede B un programa del catálogo ya creado por la sede A, y el `nombre` único impide crearlo de nuevo (`ProgramaRequest.php:31`).

### M12 Mensajes 422 que muestran datos de otra sede
`DocenteRequest`, `GrupoRequest` y `ProgramaRequest` tienen `authorize() = true`, y su `after()` corre **antes** de la revisión de sede del controlador. Así devuelven nombres y códigos de grupos de otra sede (`DocenteRequest.php:60`, `GrupoRequest.php:82-95`, `ProgramaRequest.php:62-90`). No modifican nada.

### M13 Concurrencia
- **Doble matrícula vigente:** la revisión de matrícula vigente (`MatriculaRequest.php:57`, `CambioProgramaRequest.php:41`) no se repite dentro de la transacción y no hay restricción en la BD, así que un doble envío crea dos.
- **Doble anulación:** `anularRecaudo` no vuelve a leer ni a bloquear el recaudo. Dos anulaciones simultáneas restan dos veces lo del pivote.
- **Códigos sin bloqueo:** `Matricula::siguienteCodigo` (`:162-169`) y los códigos y nombres de grupo no usan bloqueo. Dos creaciones simultáneas chocan con el índice único y dan 500.
- **Sin bloqueo de la matrícula:** `cambiarEstado` y `aplicarDescuento` no la bloquean ni repiten la revisión de la transición.
- La auditoría se escribe después del commit, así que no es atómica con el cambio de dinero.

### M14 El % de asistencia de las fichas no coincide con el del panel
`EstudianteService::porcentajeAsistencia` (`:138`) y `CalificacionService::asistenciaPorEstudiante` (`:244`) cuentan **todos** los registros del grupo. No aplican la ventana de matrícula, aplazamiento o retiro ni el filtro de clases dictadas que usa `AsistenciaService`.

### M15 Paz y salvo y grado no miran todas las matrículas
- El paz y salvo solo suma las matrículas de la misma sede (`DocumentoController.php:36`).
- Graduar solo mira la matrícula actual (`EstadoRequest.php:67`), así que se puede graduar debiendo en una matrícula anterior.

### M16 El cambio de programa no revisa documentos ni acudiente
`CambioProgramaRequest` no repite la revisión de documentos pendientes ni la de acudiente para menores que sí hace `MatriculaRequest`.

### M17 Matrículas carga todo sin paginar
- `MatriculaController::index` trae todas las matrículas de la sede, incluido el historial, con cobros, recibos, asistencia y auditoría.
- `repartoAdelantado` suma 2 consultas por cada matrícula aplazada o retirada.
- `Configuracion::valor` se consulta 2 veces por programa (`Programa.php:114,121`).

### M18 Error 500 si estudiante y acudiente nuevo traen el mismo correo
`ValidaAcudiente.php:49` solo compara contra la BD y `:81` solo compara el documento. La validación pasa y la BD lanza violación de unicidad.

---

## 4. Bajos

- **B1** El bloqueo por derecho vencido empieza el **día siguiente** al inicio de clases (`PagoService.php:455`, `fecha_vencimiento < today`). CLAUDE.md dice "desde ese día". Hay que decidir cuál es la regla y alinear el código, la prueba (`MatriculasTest.php:324`) y la documentación.
- **B2** `reprogramarDerecho` no hace nada si la nueva fecha de inicio es hoy o anterior (`PagoService.php:432`). Pasa al cambiar a un grupo que ya empezó o al adelantar el inicio.
- **B3** `cruceDocente` compara fechas de forma inclusiva (`HorarioService.php:98-99`). Un grupo que empieza el día que otro termina se marca como cruce.
- **B4** Calificaciones no valida el rango: `decimal(2,1)` admite 9.9 y no hay revisión de 1.0–5.0. `notaFinal()` toma la habilitación aunque la nota sea ≥ 3. El futuro endpoint del Docente debe validarlo.
- **B5** Los festivos colombianos no se tienen en cuenta: un lunes festivo aparece como clase "sin registrar".
- **B6** El año del consecutivo RC/DV sale de `now()` (`Pago.php:131`) y las fechas de pago no tienen límite inferior (`PagoRequest.php:34`). Un pago de diciembre registrado en enero queda en la serie del año nuevo.
- **B7** `resumen()['cuotas_total']` cuenta solo las cuotas no anuladas (`PagoService.php:486-488`). Tras un aplazamiento muestra "2 de 2" en vez de 12.
- **B8** `siguienteCodigo` ordena los códigos como texto con 4 dígitos. Después de 9999, "EST-2026-10000" queda antes que "EST-2026-9999" y el código se repite.
- **B9** N+1 en Grupos: `siguienteNombre` y `codigoPara` hacen al menos 2 consultas por programa (`GrupoController.php:51-52`).
- **B10** `pagos:vencer` está programado (`routes/console.php:17`), pero nada ejecuta `schedule:run`/`schedule:work`. El impacto es bajo porque todo usa `estadoActual()`; en el volcado hay 6 cobros vencidos que siguen como "pendiente".
- **B11** Esquema sin uso:
  - `periodos_academicos` y sus FK, que siempre están en null;
  - `actividades`, `entregas`, `evaluaciones`, `preguntas`, `opciones` y `respuestas_estudiante`;
  - `calificaciones.curso_id`, que nunca se lee;
  - `users.intentos_fallidos`, `bloqueado_en` y `motivo_bloqueo`.
- **B12** Pantallas de Breeze en inglés: reset-password, verify-email, confirm-password, dashboard y navegación. No existe `lang/es.json`. `.env.example` trae `APP_NAME=Laravel`, `APP_LOCALE=en` y `DB_CONNECTION=sqlite`.
- **B13** Las respuestas de `store` devuelven la clave inicial (`EstudianteController.php:81`, `AcudienteController.php:71`).
- **B14** Los dashboards de maqueta usan `{!! !!}` (`admin/dashboard.blade.php:189`, `superadmin/dashboard.blade.php:133`) y `dashboard.js:65` mete el texto con `innerHTML`. Será XSS en cuanto se conecten a la BD.
- **B15** Sedes:
  - El estado "Activa" está fijo (`sedes-programas.blade.php:47`).
  - El texto promete "desactivar" (`:13`, `sedes-programas.js:11`), pero no hay columna `activo` ni ruta para hacerlo.
  - El `restablecer clave` de un admin tampoco existe.
- **B16** Configuración del Superadmin:
  - Trae datos de contacto inventados (`configuracion.blade.php:109-130`: teléfono, correo y dirección), aunque los reales existen.
  - No tiene campo de cuota del sistema, aunque `admin/programas/index.blade.php:600` dice que se configura ahí.
- **B17** Accesibilidad:
  - No hay focus trap, y el foco se devuelve al cerrar solo en Asistencia, Calificaciones y Pagos.
  - Los diálogos de Grupos tienen `aria-hidden="true"` fijo (`cursos/index.blade.php:219,319`).
  - El modal de Escuela no tiene `role="dialog"`.
  - Las opciones del combobox no se pueden elegir con el teclado y las filas clicables no reciben foco.
  - Hay botones de solo ícono sin nombre y buscadores sin label.
  - Los tabs no tienen `aria-selected`.
  - `programas/index.blade.php:142` usa `strtolower` en vez de `mb_strtolower`.
- **B18** Restos:
  - `href="#"` en Contabilidad, Comunicados, Foro y Reportes (`admin/layout.blade.php:38,51,57`, `sidebar.blade.php:134`).
  - Programas › Documentos es maqueta (`programas/index.blade.php:367-404`) y Docentes › Documentos dice "próximamente".
  - `@section('page-title')` nunca se usa.
  - `escuelas.js` es ES6 y el resto ES5.
  - Hay un BOM literal en los CSV de `asistencia.js:623`, `calificaciones.js:567` y `pagos.js:1045`.
  - `configuracion.css` tiene BOM y tildes dañadas en los comentarios.
- **B19** El bloque `RESERVADO PARA EL PANEL ADMIN` (`sedes-programas.blade.php:124-495`, unas 370 líneas) es código muerto. Programas del Admin se reconstruyó aparte y Asignaciones y Periodos no existen. Arrastra JS muerto (`sedes-programas.js:5-32, 101-154`) y CSS muerto (`sedes-programas.css:123-144`).

---

## 5. Sitio público

- **P1 ✓** 17 de 34 páginas muestran a los visitantes "Foto pendiente — public/assets/img/…" porque faltan 56 fotos: las 9 de Escuela Ciencias, Barbería y las 7 de Educación Continuada.
- **P2** `public/assets/img` pesa 133 MB (PNG de 2,2 a 2,5 MB). El inicio carga unos 13 MB, solo el carrusel unos 7 MB, sin `loading="lazy"`, `srcset` ni WebP.
- **P3** Bootstrap (solo lo usa el carrusel del inicio) y Leaflet se cargan en las 35 páginas. Las teselas salen directo de `tile.openstreetmap.org` (`js_website.js:263`).
- **P4** La navegación está definida dos veces: escritorio en `header.blade.php:61-135` y móvil en `:157-197`. Las sedes y coordenadas del footer están fijas (`footer.blade.php:32-37`) y no salen de la tabla `sedes`.
- **P5** Las redes sociales apuntan a `href="#"` (footer y login). El newsletter es falso: muestra "¡Gracias!" sin guardar nada (`welcome.blade.php:276`, `js_website.js:385-404`).
- **P6** SEO: no hay canonical, Open Graph, JSON-LD ni sitemap.
- **P7** Las 34 páginas dicen "ETDH", pero PRODUCT.md pide no agregarlo salvo que se solicite. Uno de los dos está desactualizado.

---

## 6. Repositorio y entorno

- **R1 ✓** El repo pesa 224 MB empaquetado:
  - `.impeccable/` ocupa 89 MB en 251 archivos (247 capturas, y `hook.cache.json` con rutas locales);
  - `.claude/skills/impeccable/.../impeccable.exe` pesa 14,6 MB;
  - `salvamento-aprender.png` está duplicado en la raíz (idéntico al de `public/assets/img`).
- **R2** `public/js/js_wbesite.js` está vacío y con el nombre mal escrito.
- **R3** `package.json` tiene `tailwindcss ^3` y `@tailwindcss/vite ^4`. El de v4 no se usa, y Vite/Tailwind solo los usan los restos de Breeze.
- **R4 ✓** `pdo_sqlite` está desactivado en `C:\php\php.ini`, así que `php artisan test` falla 139 de 140. Con la extensión cargada pasan las 140 (`php -d extension=pdo_sqlite vendor/bin/phpunit`).
- **R5** `/public/build` está ignorado. Un clon nuevo necesita `npm run build` para que funcionen reset-password y los layouts de Breeze.

---

## 7. Base de datos (`database/esat.sql`, exportado el 21-10-2026)

**✓ Esquema:** coincide exactamente con las migraciones:
- están aplicadas las 51;
- son las mismas 37 tablas, con las mismas columnas y nulabilidad;
- hay 56 FK en cada lado.

**Datos:**
- 66 usuarios: 1 superadmin, 3 admin, 11 docentes, 35 estudiantes y 16 acudientes.
- 30 matrículas, 228 cobros, 188 recaudos (9 de 2025 y 179 de 2026), 2.613 registros de asistencia y 135 calificaciones (de 2,4 a 4,9).
- Las sedes en la BD local son "Prueba1" (Bogotá), Sasaima, Guaduas, La Dorada y una de Villeta. Solo Prueba1 y Villeta tienen admin.

**Reglas que se cumplen:**
- `abonado` = suma de los recaudos vigentes en todos los cobros, y total = programa − descuento + sistema.
- No hay recibos repetidos y el contador no está atrasado.
- No hay estudiantes con dos matrículas vigentes, grupos sobre el cupo ni sesiones fuera de la jornada.
- No hay cruces de docente ni menores sin acudiente.
- La asistencia y las calificaciones son coherentes con su grupo y sus fechas.

**Lo que no cuadra:**
- 6 matrículas vigentes sin los documentos completos (ver M6).
- 65 de 66 cuentas con la clave inicial (ver A1).
- 6 cobros vencidos que siguen marcados como "pendiente" (ver B10).
- 2 matrículas activas con el derecho vencido sin pagar: esos estudiantes no pueden ingresar.

---

## 8. Pruebas

En la revisión había 140 pruebas con 643 aserciones, y todas pasaban con `pdo_sqlite` cargado. Después de corregir C1–C5 son 148 pruebas y 678 aserciones, todas en verde.

| Archivo | Pruebas | Qué cubre |
|---|---|---|
| `MatriculasTest` | 40 | Matricular, estados, derecho, cambio de grupo/programa, devoluciones, documentos imprimibles |
| `HorariosTest` | 22 | Jornada, tope, cruces y protecciones desde Grupos, Docentes y Programas |
| `AsistenciaTest` | 8 | Clases dictadas, % de asistencia, aplazados, filtro por sede |
| `CalificacionesTest` | 6 | Estados de módulo y requisito de módulos aprobados para graduar |
| `PagosTest` | 4 | Cartera y alcance por sede |
| Estudiantes, Acudientes, Auth, Perfil | resto | — |

**Huecos:**
- El plan `periodo` no se prueba nunca.
- No hay pruebas entre sedes en las escrituras de Matrículas (pagos, anular, estado, grupo, descuento, programa, devoluciones).
- No se prueba crear, listar ni cambiar el estado de Programas, Docentes y Grupos. El CRUD de Escuelas tampoco tiene pruebas.
- El Superadmin solo tiene las de C4 (Administradores). Sedes no tiene pruebas.
- Nada de A3 a A9 está probado.
- Faltan pruebas de:
  - cambio de año del consecutivo;
  - el comando `pagos:vencer`;
  - concurrencia;
  - editar horarios o fechas de un grupo que ya tiene asistencia.

---

## 9. Lo que está bien

- Arquitectura MVC clara:
  - controladores delgados;
  - reglas en servicios por dominio y en el `after()` de los Form Requests;
  - mensajes en español.
- Alcance por sede bien aplicado en Estudiantes, Acudientes y Matrículas, sin IDOR al cambiar IDs.
- Dinero en `DECIMAL`.
- Transacciones y `lockForUpdate` en pagos, devoluciones, cupos y consecutivos. Los consecutivos nunca retroceden.
- La regla "un cambio arriba no invalida lo de abajo" está implementada y tiene pruebas.
- Los CSV neutralizan fórmulas en las 7 exportaciones. `@json` se usa bien en el panel Admin.
- Los datos del volcado son coherentes con casi todas las reglas.

---

## Orden sugerido

1. ~~**C1–C5**, cada uno con su prueba.~~ Hecho en la rama `fix/hallazgos-criticos`.
2. **A2**: una línea en `.gitignore`.
3. Arreglos rápidos de UI: **M1, M2, M3, M5**.
4. Dinero: **A3, A4, A5, A6, A9**, con pruebas de cada escenario.
5. Grupos y horarios: **A7, A8**.
6. Antes del panel Estudiante: **A10** (elegir plan) y **A1** (cambio de clave obligatorio).
7. Resto de medios, luego bajos, sitio público y limpieza del repo.
