# Hallazgos — revisión completa del proyecto

Dos revisiones:
- **Primera (21-10-2026)** sobre `main`, después del PR #41.
- **Segunda (24-10-2026)** sobre `develop`, que ya tiene las correcciones C1–C5 (PR #42).

Las dos cubrieron:
- el backend: finanzas, académico, personas, auth e infraestructura;
- el frontend: Admin, Superadmin, sitio público y pantallas de autenticación;
- el volcado `database/esat.sql`, las migraciones y la suite de pruebas.

En la segunda revisión, además:
- se escribieron **38 pruebas de reproducción**, que **no** están en el repo: al corregir cada hallazgo, conviene convertir su reproducción en prueba de regresión;
- se recorrieron todas las pantallas con los datos reales del volcado (sección 7).

**Cómo leer este documento:**
- **✓** = comprobado: leyendo el código con evidencia, o reproducido con una prueba. La segunda revisión confirmó casi todo lo que la primera había dejado sin ✓ y no descartó ninguno.
- Lo que sigue sin ✓ es "posible": sale de leer el código, pero no se reprodujo.
- **Parcial:** = la segunda revisión corrigió una parte de lo que decía la primera.
- **(2ª)** = hallazgo nuevo de la segunda revisión.
- Los IDs (C1, A3…) sirven para referenciar cada hallazgo en commits y PR.

| Severidad | Cantidad | Qué agrupa |
|---|---|---|
| Crítico | 5 | Seguridad explotable — **los 5 corregidos** (en `develop`; `main` todavía no los tiene) |
| Alto | 12 | Seguridad de datos, dinero que se pierde o queda mal, reglas de grado, proyecto que no arranca |
| Medio | 28 | Funcionalidad rota, alcance por sede, concurrencia, UI que miente, seguridad menor |
| Bajo | 36 | Bordes, rendimiento, accesibilidad, restos |
| Sitio público | 7 | Fotos, peso, SEO |
| Repo y entorno | 7 | Peso del repo, configuración local y de despliegue |

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
- La segunda revisión barrió todo el JS de ambos paneles y no quedó ningún dato editable por un usuario que se pinte sin escapar. Quedan dos puntos para endurecer: B36.

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
- La segunda revisión confirmó que no queda ninguna ruta que borre usuarios. La cascada sigue en la BD (B35).

### C4 ✓ `AdministradorController` acepta cualquier usuario
**Área:** Superadmin › Administradores.

**Qué pasa:**
- `update` (`AdministradorController.php:63`) y `toggleActivo` (`:96`) reciben cualquier `User` sin revisar su rol.
- Con `/superadmin/administradores/{id}` se puede editar o cambiar de sede a estudiantes y docentes.
- También se puede desactivar al propio superadmin y quedar sin acceso.

**Arreglo:** `abort_unless($administrador->role === RolUsuario::Admin, 404)` en ambos métodos, más lo de M9.

**Estado: corregido.**
- `update` y `toggleActivo` llaman `asegurarAdmin()` y responden 404 si la cuenta no es de administrador. `asegurarAdmin` corre antes de validar, así que no filtra datos.
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
- La segunda revisión confirmó que corre en todas las rutas de `web.php` y `auth.php` y que la cookie queda anulada.
- Pendientes relacionados:
  - el JS no lleva al login tras el 401 (M23);
  - `derechoVencido` no tiene índice y ahora corre en cada petición de un estudiante (M26);
  - restablecer la clave no cierra sesiones (B21).

---

## 2. Altos

### Seguridad de datos

#### A1 ✓ Las claves iniciales nunca se cambian — descartado
- En el volcado, **65 de 66 cuentas** siguen con la clave igual a su número de documento. No existe ningún mecanismo que obligue a cambiarla en el primer ingreso.
- El límite de login es de 5 intentos por correo+IP (`LoginRequest.php:75`), y un documento de 6 a 11 dígitos suele ser fácil de conseguir. El login además confirma si la clave era correcta (M21).
- El superadmin no tiene cómo restablecer la clave de un admin, y Docentes tampoco tiene esa acción (`web.php:184-187`, `:217-220`).

**Arreglo:** marcar las cuentas con clave inicial y obligar a cambiarla al primer ingreso.

**Estado: descartado por decisión del usuario (11-11-2026).** El cambio de la clave inicial no es obligatorio: cada persona la cambia desde su perfil si quiere. Lo que sí quedó cubierto después de esta revisión: el bloqueo de cuentas por intentos fallidos e inactividad (Configuración del Admin) y restablecer la clave de secretarías, docentes y acudientes desde el panel del Admin.

#### A2 ✓ `database/esat.sql` no está en `.gitignore`
- El volcado trae datos personales, 66 hashes de contraseñas, 14 sesiones con su contenido y caché.
- Hoy está sin seguimiento (`??`) y nunca se ha subido (`git log --all` vacío), pero un `git add .` lo subiría. `database/.gitignore` solo cubre `*.sqlite*`.

**Arreglo:** agregar `/database/*.sql` a `.gitignore`.

### Dinero y lógica de negocio

#### A3 ✓ Cambiar la duración del programa impide graduar y cobra de más (reproducido)
- Las cuotas se generan con la duración vigente en ese momento (`PagoService.php:64`).
- Para graduar, `EstadoRequest.php:77-80` compara contra la duración **actual** (`duracion_meses`/`duracion_periodos`).
- `ProgramaRequest` no impide cambiar la duración cuando hay matrículas.
- Ejemplo: un estudiante pagó sus 12 cuotas, el programa pasa a 18 meses y la respuesta es "Solo ha pagado 12 de 18".
- **(2ª) Además cobra de más:** si el estudiante se reintegra después del cambio, `PagoService.php:64-76` mezcla cuotas viejas (250.000) con nuevas (166.666), y la última "absorbe" la diferencia con la base nueva. En la prueba, un programa de 3.000.000 termina cobrando **3.083.334**.
- **(2ª)** El texto "Cuota N de {total}" también usa la duración actual (B29).

**Arreglo:** comparar contra el número de cuotas que realmente se generaron (no anuladas), o bloquear el cambio de duración si hay matrículas vigentes.

**Estado: corregido (11-11-2026).** Se bloquea el cambio de duración (meses y división) mientras el programa tenga matrículas activas o aplazadas (`AdminGeneral/Programas/ProgramaRequest`; el formulario la muestra bloqueada). Además el precio queda pactado en la matrícula (`matriculas.precio_programa`), así que un cambio de precio tampoco mezcla cuotas. Pruebas en `tests/Feature/AdminGeneral/AuditoriaPanelTest.php`.

#### A4 ✓ Anular un recibo en una matrícula aplazada o retirada revive deuda (reproducido) — corregido
- `anularRecaudo` (`PagoService.php:192-205`) pasa los cobros `pagado` a pendiente o vencido sin importar el estado de la matrícula. `AnularPagoRequest.php:31` solo lo bloquea en las graduadas.
- Las cuotas que vencen después del aplazamiento o retiro, y el derecho, vuelven a ser deuda.
- En una retirada (estado final) nada las vuelve a anular, así que el paz y salvo queda bloqueado para siempre. Caso probado: tras pagar la deuda real (452.000), `por_pagar` sigue en 504.000.
- En una aplazada, el derecho puede volver a vencer y bloquear el login.

**Arreglo:** al anular un recibo en una matrícula aplazada o retirada, devolver a `anulado` (no a pendiente) los cobros que ya estaban anulados por ese cambio de estado.

**Estado: corregido (11-11-2026).** `anularRecaudo` vuelve a aplicar las consecuencias del estado con su fecha: las cuotas de después siguen anuladas y el derecho sin causar queda en espera (aplazado) o anulado (retirado). Solo vuelve a ser deuda lo que ya se debía. Pruebas en `tests/Feature/Admin/DineroAdelantadoTest.php`.

#### A5 ✓ `adelantado()` olvida cuotas pagadas cuando hay dos cambios de estado (reproducido) — corregido
- `adelantado()` toma la **última** `estado_fecha` (`PagoService.php:228`), y cada cambio de estado la sobrescribe (`MatriculaService.php:90,134`).
- Ejemplo:
  1. El estudiante pagó noviembre, diciembre y enero.
  2. Se aplaza el 20-oct: el adelantado son las tres cuotas (756.000).
  3. Se retira el 20-dic: solo queda reconocido enero (252.000).
- **(2ª)** Lo mismo pasa al cambiar de programa desde una matrícula aplazada: `MatriculaService.php:132-136` pone hoy como `estado_fecha` y solo se trasladan 252.000 de 756.000.
- Lo pagado para el periodo aplazado ya no se puede devolver ni trasladar.

**Arreglo:** marcar en el cobro (o en el pivote) qué cambio de estado lo dejó adelantado, en vez de recalcularlo con la fecha más reciente.

**Estado: corregido (11-11-2026).** Al aplazar o retirar, `anularCuotasFuturas` anula también las cuotas ya pagadas de los meses siguientes, conservando su abono: el dinero queda marcado en el cobro. `adelantado()` ya no depende de `estado_fecha` (son los cobros anulados con dinero). La BD `esat` no tenía casos que convertir. Pruebas en `tests/Feature/Admin/DineroAdelantadoTest.php`.

#### A6 ✓ Saldo huérfano al anular la matrícula nueva de un cambio de programa (reproducido) — corregido
- `EstadoRequest.php:85-90` solo revisa los recaudos. El dinero que llegó por traslado (`saldo_favor` y cobros pagados con él) no es un recaudo, así que la anulación pasa.
- Todas las vistas excluyen las matrículas anuladas (`CarteraService.php:224`), y la devolución da 422 tanto en la matrícula nueva como en la vieja. Ese dinero desaparece del sistema (en la prueba, 504.000).

**Arreglo:** no permitir anular si `saldo_favor > 0` o si tiene cobros con `abonado > 0`.

**Estado: corregido (11-11-2026).** `EstadoRequest` y `cambiarEstado` (con la matrícula bloqueada) no anulan una matrícula con dinero (`PagoService::dineroEn`: lo que sigue en sus cobros más el saldo a favor); Matrículas avisa que use Retirar. Pruebas en `tests/Feature/Admin/DineroAdelantadoTest.php`.

#### A7 ✓ Editar un horario o la fecha de inicio reescribe el pasado (reproducido) — corregido
- `HorarioController.php:58` borra y recrea las sesiones.
- Asistencia (`AsistenciaService.php:55`) y Calificaciones (`CalificacionService.php:31`) recalculan las clases dictadas y las fechas de los módulos con el horario **actual**:
  - los registros en días que se quitaron dejan de contar;
  - los días pasados que se agregaron aparecen como "sin registrar".
- En la prueba, al pasar de lunes/miércoles a martes/jueves, el % de un estudiante pasa de 67 a null y el 6, 8 y 13-oct aparecen "sin registrar".
- `PUT /admin/cursos` mueve la `fecha_inicio` de un grupo con asistencia sin ninguna restricción.

**Arreglo:** guardar desde qué fecha rige cada horario, o bloquear la edición de días y fechas ya dictados.

**Estado: corregido (11-11-2026).** `curso_sesiones.desde/hasta` (migración `2026_11_11_000003`): si el grupo ya empezó y tiene asistencia o notas, el horario de antes rige hasta hoy y el nuevo desde mañana (`Curso::conservaHorarioAnterior`); si no, cambiarlo es corregirlo y se reemplaza. Asistencia, módulos, panel del docente (también `datos.js`) y reportes calculan cada día con `Curso::sesionEn` sobre `historialSesiones`; el tope de horas suma lo ya dictado (`HorarioService::horasDelGrupo`). Un grupo que ya empezó no cambia su fecha de inicio. Probado también en el navegador. Pruebas en `tests/Feature/Admin/HistorialGruposTest.php`.

#### A8 ✓ Un grupo se puede finalizar en cualquier momento (reproducido) — corregido
- `GrupoController.php:138` no revisa fechas ni estudiantes activos. **(2ª)** También se puede finalizar por PUT, porque `GrupoRequest.php:29` acepta `finalizado` (ver M22).
- `EstadoRequest.php:54` se salta la revisión de fecha si el grupo está `finalizado`, así que se puede graduar antes de que termine.
- El grupo además desaparece de Asistencia (`AsistenciaService.php:89`) mientras sus estudiantes siguen activos.

**Arreglo:** permitir finalizar solo desde `fecha_fin`, o exigir que no queden matrículas activas.

**Estado: corregido (11-11-2026).** `Curso::impedimentoParaFinalizar`: antes de su fin solo se finaliza si no le quedan matrículas vigentes (botón de estado y PUT). Graduar exige que el grupo haya terminado por fecha (`fin()`), aunque esté marcado finalizado. Pruebas en `tests/Feature/Admin/HistorialGruposTest.php`.

#### A9 ✓ Derecho a medio pagar y retiro antes de clases (reproducido) — corregido
- `anularDerechoSinCausar` solo anula el derecho si `abonado = 0` (`PagoService.php:412`). CLAUDE.md dice que al retirarse antes de que venza "se anula".
- Con un abono parcial, el derecho queda pendiente, vence al iniciar el grupo y el retirado aparece como deudor. En la prueba: 100.000 abonados y "vencido" desde el 4-nov.
- Lo abonado no se puede devolver, porque `adelantado()` (`:231-232`) solo cuenta cobros anulados o cuotas pagadas.
- **Parcial:** el caso "queda pendiente sin fecha para siempre" (aplazado y luego retirado) solo ocurre si hay un abono parcial. Sin abono, el derecho se anula bien.

**Estado: corregido (11-11-2026).** Al retirarse antes de empezar, el derecho sin causar se anula aunque tenga abono, y lo abonado queda como adelantado (devolución o traslado). Decidido después con el usuario: el derecho **pagado completo** también se anula, y de lo pagado (parcial o completo) se devuelve el porcentaje de Admin › Configuración (`devolucion_derecho_pct`, 50 % por defecto); el resto queda en `pagos.retenido` (migración `2026_11_11_000004`) y no se devuelve ni se traslada. El cambio de programa no retiene nada. Pruebas en `tests/Feature/Admin/DineroAdelantadoTest.php`.

#### A10 ✓ No hay forma en la app de elegir el plan de pago
- `generarCuotas` solo se llama al reintegrar (`MatriculaService.php:115`), y nada en `app/` escribe `plan_pago`: solo se lee.
- Hasta que exista el panel Estudiante, ninguna matrícula nueva tiene cuotas ni puede graduarse: graduar exige plan y cuotas pagadas.
- Las 24 matrículas con plan del volcado se insertaron directo en la BD.
- Es una pieza pendiente de diseño, pero bloquea el flujo completo.

### Proyecto e infraestructura (2ª)

#### A11 ✓ (2ª) Un clon nuevo del proyecto no arranca — corregido
- `database/seeders/DatabaseSeeder.php:20-21` crea un usuario con la columna `name`, que ya no existe: falla con "table users has no column named name".
- No hay seeder ni comando para crear el primer superadmin, así que una BD nueva no tiene a nadie que pueda ingresar.
- El README es el de Laravel.
- `composer setup` migra un SQLite vacío y además necesita `pdo_sqlite` (R4).
- Para arrancar hacen falta `composer install`, `npm run build` (R5), `key:generate` e importar la BD, y no está escrito en ningún lado.

**Arreglo:**
- Un seeder con las 4 sedes reales y un superadmin creado desde variables de entorno.
- Un README con los pasos.
- `.env.example` con `mysql`, `esat`, `es` y ESAT.

**Estado: corregido (11-11-2026).** `DatabaseSeeder` crea las 4 sedes reales y las dos cuentas iniciales (Superadmin y
Admin de toda ESAT) desde `SUPERADMIN_*` y `ADMIN_GENERAL_*` del `.env`, con la clave = documento; se puede correr otra
vez sin duplicar. `.env.example` con MySQL, `esat`, español y ESAT, y README con los pasos. **Probado de punta a punta**
sobre una MariaDB vacía: las 61 migraciones corren, el seeder crea sedes y cuentas, el Admin entra y las 14 pantallas
de su panel cargan sin errores. Pruebas en `tests/Feature/InstalacionTest.php`.

#### A12 ✓ (2ª) El rollback de migraciones destruye dinero y falla a medias — corregido
Se probó en un SQLite temporal, nunca sobre `esat`:
- **Con la BD vacía** todo funciona: `migrate`, `rollback --step=51` y `fresh` dejan el mismo esquema.
- **Con datos, el rollback borra dinero:**
  - `2026_10_08_000003` borra `recaudos` y `recaudo_pago`, y pasa las matrículas anuladas a `retirado`;
  - `2026_10_08_000002` borra los cobros de derecho de matrícula.
- **Con datos, falla en cuatro migraciones:**
  - `2026_10_07_000003`: NOT NULL en `pagos.periodo_academico_id` y el CHECK de `tipo_documento` (PPT);
  - `2026_10_07_000001`: NOT NULL en `plan_pago` y el CHECK de `estado`;
  - `2026_10_06_000007`: NOT NULL en `cursos.periodo_academico_id`;
  - `2026_09_15_201305`: no puede agregar `name` NOT NULL. Esta solo falla en SQLite.
- **Un rollback fallido queda a medias:** quedan tablas `__temp__*` e índices ya borrados, así que el reintento también falla. En MySQL el DDL tampoco es transaccional.
- **Fallos que solo aparecen en MySQL:** el `down()` de `2026_10_06_000007` y el de `2026_10_07_000004` dan error 1553, porque borran el único índice detrás de una FK.

**Arreglo:**
- Declarar sin rollback las migraciones del 07-10 en adelante: un `down()` que lance una excepción con el motivo.
- Nunca correr `rollback`, `refresh` ni `fresh` sobre `esat`.

**Estado: corregido (11-11-2026).** `AppServiceProvider::protegerMigraciones`: deshacer una migración del 07-10 en
adelante se niega con un mensaje mientras haya matrículas, cobros o recibos (con la BD vacía sí se puede). Probado sobre
la copia `esat_auditoria`: el `migrate:rollback` se detuvo sin tocar nada. En producción (`APP_ENV=production`) tampoco
se corren `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe` (`DB::prohibitDestructiveCommands`). El README
explica cómo volver atrás con un respaldo. Pruebas en `tests/Feature/InstalacionTest.php`.

---

## 3. Medios

### M1 ✓ "Desvincular" sigue visible para menores (bug de `[hidden]`)
`acudientes.css:12` le pone `display:inline-flex` a `.acu-btn` y no existe `.acu-btn[hidden]`, así que `acudientes.js:700` (`btnConfirmar.hidden = menor`) no tiene efecto. El servidor rechaza la acción, pero el botón se ve.

**Arreglo:** `.acu-btn[hidden] { display: none; }`.

### M2 ✓ La casilla "Ya pagó… ($0)" aparece en programas sin derecho (bug de `[hidden]`)
`matriculas.css:520` le pone `display:flex` a `.mat-check` y no existe `.mat-check[hidden]`, así que `matriculas.js:1336` no lo oculta. Al marcarla se abre `#nm-pago` (`matriculas.js:1308`).

**Arreglo:** `.mat-check[hidden] { display: none; }`.

La segunda revisión revisó unos 260 elementos que se ocultan con `hidden`: M1 y M2 son los **únicos** casos de este bug que quedan.

### M3 ✓ Los combobox del Superadmin no tienen `alScroll`
- `administradores.js:111` y `sedes-programas.js:195` todavía usan `addEventListener('scroll', posicionar, true)`, y `posicionar` reescribe `list.style.maxHeight` (`administradores.js:100`). Por eso la lista vuelve al inicio y no deja bajar.
- Las 10 copias del Admin ya tienen `alScroll`.

### M4 ✓ Escape y listeners del combobox en modales y drawers
- **Acudientes › Vincular:** el Escape del drawer (`acudientes.js:1039`) se registra al cargar la página. El del combobox (`:214`) se crea después, al abrir la acción (`:680`), así que su `stopImmediatePropagation` llega tarde. Con la lista abierta, Escape sale de la acción en vez de cerrar solo la lista.
- **(2ª) Docentes, Programas y Grupos:** el Escape de la página (`docentes.js:237`, `programas.js:204`, `cursos.js:197`) cierra el modal entero sin mirar si hay una lista abierta. Los combobox de `cursos.js` y `programas.js` ni siquiera manejan Escape. Posiblemente la lista reaparece abierta al reabrir el modal.
- **Listeners acumulados:** `initCombobox` agrega listeners de click y keydown al `document` en cada llamada, y las llamadas dinámicas (`acudientes.js:680`, `horarios.js:778-779`) los acumulan.
- Horarios ya lo resuelve revisando `.app-combobox.is-open` (`horarios.js:1164`): copiar ese patrón.

### M5 ✓ Eliminar una escuela con docentes da error 500 y deja una auditoría falsa (reproducido)
- `EscuelaController::destroy` (`:35`) solo revisa los programas. `docente_perfiles.escuela_id` es una FK restrict (`2026_10_06_000006`), así que la BD lanza `QueryException`.
- **(2ª)** `EscuelaController.php:45` escribe "Eliminó la escuela" en la auditoría **antes** del `delete()` que falla, y fuera de transacción. El registro queda aunque no se eliminó nada. Es el mismo patrón que en M13.

**Arreglo:** contar también los docentes y responder 422; registrar la auditoría después del cambio, dentro de la transacción.

### M6 ✓ Se puede desmarcar un documento después de matricular (reproducido)
- `EstudianteController::documento` (`:120`) alterna `entregado_en` sin mirar si hay una matrícula vigente.
- En el volcado hay 6 matrículas vigentes sin los 3 documentos:
  - 16 y 26 se desmarcaron después de matricular;
  - 13, 27, 33 y 35 se insertaron así directo en la BD.
- Falta decidir si se permite (por ejemplo, un documento devuelto) y, si sí, avisarlo en la ficha.

### M7 ✓ El formulario de perfil de Breeze no funciona (reproducido)
El formulario envía `name` (`update-profile-information-form.blade.php:22`), pero `ProfileUpdateRequest.php:29-30` exige `nombres` y `apellidos`. Para estudiante, docente y acudiente siempre falla. El error queda en `nombres`/`apellidos`, pero el formulario solo muestra `$errors->get('name')`, así que no se ve. Ver también M20.

### M8 ✓ Los roles sin panel caen en el `/dashboard` de Breeze
`User::panelUrl()` (`User.php:133-140`) manda a estudiante, docente y acudiente a `route('dashboard')`, que dice "You're logged in!" en inglés.

**Estado: parcial.** El docente ya entra a su propio panel (`/docente`, por ahora solo frontend con datos de ejemplo). Estudiante y acudiente siguen cayendo en el `/dashboard` de Breeze.

### M9 ✓ Superadmin sin Form Requests ni auditoría (reproducido)
- `SedeController` y `AdministradorController` validan dentro del controlador (`AdministradorController.php:29-41,67-79`) y no escriben ninguna fila en `auditorias`. Las dos cosas van en contra de CLAUDE.md.
- La validación es más débil que en Admin. En la prueba se aceptó:
  - un documento `abc 1`, que queda como clave;
  - un teléfono `<b>tel</b>` (sin regex);
  - una fecha de nacimiento en 2030 (sin `before:today`);
  - el tipo TI para un administrador.
- Los tipos de documento no incluyen `PPT` (ver M28).

### M10 ✓ Superadmin no muestra errores y su interruptor puede mentir
- Los campos vacíos en Sedes se ignoran sin mensaje (`sedes-programas.js:423-428`).
- `sedes-programas.js:451-461` hace `res.json()` sin mirar `res.ok`: un 422, 419 o 500 solo reactiva el botón. El guardado de Administradores (`administradores.js:454-466`) tampoco muestra errores.
- El interruptor activo/inactivo cambia antes de la respuesta (`administradores.js:59-61`) y solo el `.catch` lo revierte (`:63-72`), es decir, solo ante errores de red. Un 4xx o 5xx deja un estado falso.

### M11 ✓ Alcance entre sedes en Escuelas y Programas (reproducido)
- Las escuelas no están limitadas por sede: cualquier admin renombra o elimina cualquier escuela (`EscuelaController.php:24,35`).
- Un programa compartido se edita para todas las sedes a la vez: precio, duración y cupo (`ProgramaController.php:66`).
- Cambiar la duración recalcula `fecha_fin` de **todos** los grupos (`ProgramaController.php:77`). En la prueba cambió el de un grupo **finalizado de otra sede** (de 2026-01-06 a 2026-07-06).
- No hay forma de ofertar en la sede B un programa del catálogo ya creado por la sede A, y el `nombre` único impide crearlo de nuevo (`ProgramaRequest.php:31`).

### M12 ✓ Mensajes 422 que muestran datos de otra sede (Docente y Programa reproducidos)
- `DocenteRequest`, `GrupoRequest` y `ProgramaRequest` tienen `authorize() = true`, y su `after()` corre **antes** de la revisión de sede del controlador. No modifican nada.
- `DocenteRequest.php:60` y `ProgramaRequest.php:62-90` responden 422 con el código de un grupo de otra sede, en vez de 404.
- **Parcial:** `GrupoRequest.php:58,82-95` deja ver si un grupo ajeno tiene inscritos y cómo es su horario, pero no los nombres: `cruceDocente` solo nombra grupos del docente propio.

**Arreglo:** mover la regla de sede a `authorize()`, como ya hace `HorarioRequest`.

### M13 ✓ Concurrencia (leyendo el código; SQLite no permite reproducirla) — corregido
- **Doble matrícula vigente:** la revisión de matrícula vigente (`MatriculaRequest.php:57`, `CambioProgramaRequest.php:41`) no se repite dentro de la transacción y no hay restricción en la BD, así que un doble envío crea dos.
- **(2ª) Doble envío del cambio de programa:** `MatriculaService.php:126-161` no vuelve a leer ni bloquea la matrícula vieja. La segunda petición espera el bloqueo del grupo y crea otra matrícula nueva, con 0 trasladado. Resultado: dos matrículas activas.
- **Doble anulación:** `anularRecaudo` (`PagoService.php:195`) bloquea los cobros pero no vuelve a leer `anulado_en`; la revisión está fuera de la transacción (`AnularPagoRequest.php:29`). Dos anulaciones simultáneas restan dos veces lo del pivote.
- **Códigos sin bloqueo:** `Matricula::siguienteCodigo` (`:162-169`) y los códigos y nombres de grupo no usan bloqueo. Dos creaciones simultáneas chocan con el índice único y dan 500.
- **Sin bloqueo de la matrícula:** `cambiarEstado` y `aplicarDescuento` no la bloquean ni repiten la revisión de la transición.
- La auditoría se escribe en los controladores después del commit, así que no es atómica con el cambio de dinero.

**Estado: corregido (11-11-2026).**
- **Una petición = una transacción** (`TransaccionEscritura`, alias `transaccion` en los grupos `/admin`, `/general` y
  `/docente`): el cambio y su auditoría quedan juntos o no queda ninguno; un 4xx o 5xx deshace todo.
- **`READ COMMITTED`** en la conexión MySQL/MariaDB (`config/database.php`): lo que se revisa después de un
  `lockForUpdate` ve lo último confirmado. Sin esto, la transacción por petición hacía que la segunda revisara con una
  "foto" del inicio de la petición (se vio en la primera carrera: la matrícula duplicada solo la frenó el código único).
- **Se revisa otra vez con la fila bloqueada**: matricular (estudiante bloqueado + sin otra vigente), cambiar de programa
  (la matrícula vieja sigue vigente), cambiar de estado (la transición sigue permitida; anular sin pagos; graduar a paz y
  salvo), descuento (matrícula bloqueada), anular un pago (`PagoService::motivoNoAnulable`, la misma regla de
  `AnularPagoRequest`, con la matrícula y el recibo bloqueados, en el mismo orden que pagar y devolver).
- **La BD lo garantiza**: `matriculas.vigente_de` (columna calculada, índice único; migración `2026_11_11_000002`) no
  admite dos matrículas vigentes del mismo estudiante, ni por un insert directo.
- **Códigos de a uno**: `Configuracion::bloquear()` para el código de matrícula y los consecutivos RC/DV/CE (también la
  primera vez del año); el programa se bloquea al sacar la letra y el código de un grupo nuevo.
- **Respaldo**: un choque de índice único responde 409 "Recarga la página…" en vez de error 500 (`bootstrap/app.php`).
- **Probado con carreras reales** (dos procesos a la vez contra MariaDB, copia `esat_auditoria`): misma matrícula dos
  veces, dos matrículas distintas, dos pagos por el total, el mismo recibo anulado dos veces, el mismo cambio de programa
  y aplazar contra retirar. En todas, una pasa y la otra se rechaza con aviso; ningún dato queda mal. Y por el navegador,
  un doble clic de "Registrar pago": un recibo, una línea de auditoría, nada sobrepagado. Pruebas en
  `tests/Feature/Admin/ConcurrenciaTest.php`.

### M14 ✓ El % de asistencia de las fichas no coincide con el del panel (reproducido)
- `EstudianteService::porcentajeAsistencia` (`:138`) y `CalificacionService::asistenciaPorEstudiante` (`:244`) cuentan **todos** los registros del grupo. No aplican la ventana de matrícula, aplazamiento o retiro ni el filtro de clases dictadas que usa `AsistenciaService`.
- En la prueba, un registro en martes, fuera del horario, deja el panel en 100 % y la ficha en 67 %.

**Arreglo:** calcularlo con la regla de `AsistenciaService` (resuelve también el rendimiento de M17).

### M15 ✓ Grado y paz y salvo no miran todas las matrículas (parcial)
- Graduar solo mira la matrícula actual (`EstadoRequest.php:67`). En la prueba se graduó debiendo 200.000 en una matrícula anterior retirada, y luego el paz y salvo da 404: incoherente.
- **Parcial:** el paz y salvo solo suma las matrículas de la misma sede (`DocumentoController.php:36`), pero hoy eso no se puede alcanzar. `users.sede_id` solo se fija al crear, y `MatriculaRequest` exige que estudiante y grupo sean de la sede del admin.

### M16 ✓ El cambio de programa no revisa documentos (reproducido)
- `CambioProgramaRequest` no repite la revisión de documentos pendientes que sí hace `MatriculaRequest`. Con documentos desmarcados (posible por M6), el cambio responde 200.
- **Parcial:** la parte del acudiente no se puede alcanzar desde la app, porque un menor nunca queda sin acudiente.

### M17 ✓ Matrículas y otras pantallas cargan todo sin paginar (medido)
- `MatriculaController.php:51-60` trae todas las matrículas de la sede, incluido el historial, con cobros, recibos, asistencia y auditoría.
- **Con los datos reales (sección 7):** Matrículas hace 60 consultas y pesa **238 KB para solo 30 matrículas**, unos 8 KB por matrícula. Con 1.000 matrículas serían unos 8 MB.
- `repartoAdelantado` suma 2 consultas por cada matrícula aplazada o retirada (`PagoService.php:230`, `:140`).
- `Configuracion::valor` (`Configuracion.php:16`) no tiene caché y se llama 2 veces por programa (`Programa.php:114,121`). Por eso Matrículas hace 24 consultas incluso en una sede **sin datos**.
- **(2ª)** `CarteraService::datosSede` (Pagos) también carga toda la sede sin filtro.
- **(2ª)** Estudiantes y Acudientes (`EstudianteController.php:43`, `AcudienteController.php:37`) cargan **todas** las asistencias de la sede solo para sacar un porcentaje.

### M18 ✓ Error 500 si estudiante y acudiente nuevo traen el mismo correo (reproducido)
`ValidaAcudiente.php:49` solo compara contra la BD y `:81` solo compara el documento. La validación pasa y la BD lanza violación de unicidad (la transacción hace rollback).

### M19 ✓ (2ª) Un precio con centavos deja un cobro imposible de pagar (reproducido)
- `ProgramaRequest.php:46-47` acepta `numeric` en `precio_total` y `matricula` (por ejemplo 200.000,50), y `cobrarMatricula` y la última cuota (`PagoService.php:35,76`) no redondean.
- `PagoRequest.php:33` solo acepta pagos enteros, y `PagoService.php:155` rechaza con 422 el peso que falta, porque supera los 0,50 que se deben.
- Resultado: el derecho nunca queda pagado, `derechoVencido` bloquea el ingreso **para siempre** y no hay grado ni paz y salvo.

**Arreglo:** validar `integer` en precio y matrícula, y/o redondear al generar los cobros.

### M20 ✓ (2ª) Estudiante, docente y acudiente cambian su nombre y su correo sin auditoría (reproducido)
- `PATCH /profile` (`ProfileController.php:36`) deja que cualquier rol cambie sus nombres, apellidos y correo (que es su usuario), sin auditoría.
- Ese nombre sale en la constancia, el certificado de notas, el paz y salvo y los recibos.
- La UI de Breeze no lo permite (M7), pero una petición armada a mano sí.
- `ProfileUpdateRequest` es más laxo que el Admin: nombre hasta 255 (el Admin pide 100), y teléfono y dirección pueden quedar vacíos. Si un estudiante los vacía, el admin ya no puede guardar su ficha sin completarlos.

**Arreglo:** para los roles que no son admin ni superadmin, dejar editables solo teléfono, dirección y clave. Nombre y correo los cambia el admin. Registrar con `Auditoria::registrar`.

### M21 ✓ (2ª) El login confirma la clave de una cuenta bloqueada (reproducido)
- `LoginRequest.php:54-63` limpia el límite de intentos y responde "Tu cuenta está desactivada" o "el derecho de matrícula venció el dd/mm" **solo si la clave es correcta**.
- Así confirma credenciales de cuentas bloqueadas (grave junto con A1) y muestra el estado de pago. Ya pasaba antes de C5.

**Arreglo:** responder el mismo `auth.failed` genérico, o no limpiar el límite ni revelar el motivo hasta que la cuenta pueda entrar.

### M22 ✓ (2ª) Un grupo finalizado se puede reeditar por PUT (reproducido)
- `PUT /admin/cursos/{id}` deja cambiar la fecha de inicio, la jornada y el docente de un grupo **finalizado**. Horarios sí lo bloquea (`HorarioRequest.php:48`).
- Crear o editar un grupo con `estado=finalizado` se salta las reglas de `cambiarEstado`.

**Arreglo:** en `GrupoRequest`, rechazar la edición de un grupo finalizado salvo para reabrirlo, y no aceptar `finalizado` como estado al crear o editar.

### M23 ✓ (2ª) Las páginas no llevan al login tras 401/419, y varios errores salen en inglés
- El `enviar()` del Admin (8 copias, por ejemplo `matriculas.js:18-32`) muestra `j.message` pero no redirige.
- **Después de C5:** una cuenta desactivada ve el motivo en español, pero la página queda muerta. El siguiente clic da 419 con "CSRF token mismatch.", porque la sesión se regeneró. Solo `pagos.js:101` traduce el 419.
- **403** de `authorize()` (`HorarioRequest`, los Requests de Matrículas): "This action is unauthorized.". Pasa, por ejemplo, si el Superadmin cambia de sede a un admin que tiene la página abierta.
- **500:** con `APP_DEBUG=true` muestra la excepción cruda (por ejemplo el SQLSTATE de M5); en producción, "Server Error".
- **Sin red:** "Failed to fetch" (`cursos.js:296`, `docentes.js:359`, `programas.js:334` y los demás `catch`).
- El Superadmin falla en silencio en todos estos casos (M10).

**Arreglo:** en `enviar()`, ante 401 o 419 llevar al login (o recargar), y traducir los mensajes genéricos.

### M24 ✓ (2ª) Al reintegrarse, las cuotas se cobran desde el reintegro y no desde el inicio del grupo (reproducido; regla por definir)
- `MatriculaService.php:110-115` genera las cuotas desde la fecha del reintegro.
- Caso: reintegro el 7-oct a un grupo que empieza el 1-feb: la primera cuota vence el **10-oct**, cuatro meses antes de las clases.

**Arreglo:** usar el mayor entre la fecha del reintegro y `grupo.fecha_inicio`.

### M25 ✓ (2ª) El "Resumen de caja" no es reproducible (posible)
- `pagos.js:393-395` filtra por `fecha_pago` (que la digita el admin y no tiene límite inferior, B6) y por el estado de anulación **actual**.
- Efectos:
  - anular hoy un recibo de diciembre cambia el total de diciembre que ya se había impreso;
  - en el rango de hoy no aparece esa anulación;
  - un pago registrado hoy con fecha atrasada se mete en un periodo ya cerrado.

**Arreglo:** mostrar las anulaciones en la fecha de `anulado_en` y/o usar la fecha de registro (`created_at`), y poner un límite a `fecha_pago`.

**Estado: corregido (11-11-2026).** La caja cuenta cada pago el día en que **se registró** (`registrado_en`) y cada anulación el día en que se hizo: un recibo anulado dentro del mismo periodo no suma, y uno de un periodo anterior anulado en este se resta aquí ("Anulados de antes"). Un periodo ya impreso no cambia. En `esat`, 188 de 189 recibos tienen la misma fecha de registro y de pago. Prueba en `CobrosRevisionTest`.

### M26 ✓ (2ª) `derechoVencido` recorre toda la tabla de cobros en cada petición de un estudiante
- Desde C5 corre en **cada petición de un estudiante** (`EnsureAccountIsEnabled`). Su consulta (`PagoService.php:452-458`) da `SCAN pagos` con una subconsulta correlacionada (comprobado con `EXPLAIN QUERY PLAN`).

**Arreglo:** partir de las matrículas del estudiante (índice `estudiante_id`) o crear el índice `pagos(matricula_id, tipo)`, y cambiar `whereDate` por `where`.

### M27 ✓ (2ª) "¿Olvidaste tu contraseña?" no envía nada
- El correo está configurado en `log` (`.env.example`, `config/mail.php`). El enlace de `login.blade.php:91` escribe el correo de restablecer en `laravel.log`, en inglés y desde `hello@example.com`.

**Arreglo:** configurar SMTP y `lang/es.json`, u ocultar el enlace hasta que exista.

### M28 ✓ (2ª) Cada pantalla acepta una lista distinta de tipos de documento

| Dónde | Tipos que acepta |
|---|---|
| BD (enum) | CC, TI, CE, PA, PPT |
| Estudiantes | TI, CC, CE, PPT |
| Acudientes | CC, CE, PPT |
| Docentes | CC, CE, PA (sin PPT) |
| Superadmin | CC, TI, CE, PA (sin PPT, y con TI para un admin) |

- `EstudianteService::vincularAcudiente` además fija `'CC'` a todo acudiente nuevo.
- Resultado: un registro creado en una pantalla puede no validar al editarlo en otra.

---

## 4. Bajos

- **B1 ✓** El bloqueo por derecho vencido empieza el **día siguiente** al inicio de clases (`PagoService.php:455`, `fecha_vencimiento < today`). Reproducido: el 3-nov (inicio) entra, el 4-nov se bloquea. CLAUDE.md dice "desde ese día". Hay que decidir cuál es la regla y alinear el código, la prueba (`MatriculasTest.php:324`) y la documentación.
- **B2 ✓** `reprogramarDerecho` no hace nada si la nueva fecha de inicio es hoy o anterior (`PagoService.php:432`). Pasa al cambiar a un grupo que ya empezó o al adelantar el inicio. Es permisivo: da días de más, no bloquea a nadie por error.
- **B3 ✓** `cruceDocente` compara fechas de forma inclusiva (`HorarioService.php:98-99`), pero Asistencia y Calificaciones toman el último día como exclusivo (`lt($fin)`). Un grupo que empieza el día que otro termina se marca como cruce (reproducido).
- **B4 ✓** Calificaciones no valida el rango: `decimal(2,1)` admite 9.9 y no hay revisión de 1.0–5.0. `notaFinal()` (`Calificacion.php:68`) toma la habilitación aunque la nota sea ≥ 3: con nota 4.0 y habilitación 2.0, la nota final es 2.0 y el estado dice "aprobado" (reproducido). El futuro endpoint del Docente debe validarlo.
- **B5 ✓** Los festivos colombianos no se tienen en cuenta (`AsistenciaService.php:55-77`, `fechasModulos`): un lunes festivo aparece como clase "sin registrar".
- **B6 ✓** El año del consecutivo RC/DV sale de `now()` (`Pago.php:131`) y las fechas de pago no tienen límite inferior (`PagoRequest.php:34`). Reproducido: un pago con fecha 28-12-2026 registrado el 5-01-2027 sale como `RC-2027-00001`, y una fecha de 2019 se acepta.
- **B7 ✓** `resumen()['cuotas_total']` cuenta solo las cuotas no anuladas (`PagoService.php:486-488`). Tras un aplazamiento muestra "1 de 1" en vez de 12 (reproducido).
- **B8 ✓** `siguienteCodigo` ordena los códigos como texto con 4 dígitos. Después de 9999, "EST-2026-10000" queda antes que "EST-2026-9999" y el código se repite (reproducido). Poco probable en la práctica.
- **B9 ✓** N+1 en Grupos: `siguienteNombre` y `codigoPara` hacen al menos 2 consultas por programa (`GrupoController.php:51-52`). Medido: +19 consultas al pasar de 1 a 11 programas; con los datos reales, 36 consultas.
- **B10 ✓** `pagos:vencer` está programado (`routes/console.php:17`), pero en el repo no hay cron, tarea de Windows ni `schedule:work`. El impacto es bajo porque todo usa `estadoActual()`; en el volcado hay 6 cobros vencidos que siguen como "pendiente".
- **B11 ✓** Esquema y código sin uso:
  - `periodos_academicos` y sus FK, que siempre están en null;
  - `actividades`, `entregas`, `evaluaciones`, `preguntas`, `opciones` y `respuestas_estudiante`;
  - `calificaciones.curso_id`, que se escribe pero nunca se lee;
  - `users.intentos_fallidos`, `bloqueado_en` y `motivo_bloqueo` (0 referencias);
  - **(2ª)** `pagos.mes`/`anio` repiten `fecha_vencimiento`, y `metodo_pago` y `referencia_pasarela` ya no se usan;
  - **(2ª)** `Curso::recibeEstudiantes`, `CursoSesion::minutos` y `Matricula::periodoActual`;
  - **(2ª)** las rutas de verificación de correo y `confirm-password` (`auth.php:34-49`): `User` no implementa `MustVerifyEmail`, así que el `verified` de `/dashboard` no hace nada.
- **B12 ✓** Pantallas de Breeze en inglés: reset-password, verify-email, confirm-password, dashboard y navegación. No existe `lang/es.json`. `.env.example` trae `APP_NAME=Laravel`, `APP_LOCALE=en` y `DB_CONNECTION=sqlite`.
- **B13 ✓** Las respuestas de `store` devuelven la clave inicial (`EstudianteController.php:81`, `AcudienteController.php:71` y `DocenteController.php:57`).
- **B14 ✓** Los dashboards de maqueta usan `{!! !!}` (`admin/dashboard.blade.php:189`, `superadmin/dashboard.blade.php:133`) y `dashboard.js:59,65` mete el texto con `innerHTML`. Será XSS en cuanto se conecten a la BD. **(2ª)** Además, `admin/dashboard.blade.php:184` usa `addslashes` dentro de un atributo, y eso no escapa `"`.
- **B15 ✓** Sedes:
  - El estado "Activa" está fijo (`sedes-programas.blade.php:47`).
  - El texto promete "desactivar" (`:13`, `sedes-programas.js:11`), pero no hay columna `activo` ni ruta para hacerlo.
  - El `restablecer clave` de un admin tampoco existe.
- **B16 ✓** Configuración del Superadmin:
  - Trae datos de contacto inventados (`configuracion.blade.php:109-130`). La dirección "Calle 72 # 11-35" contradice la real del footer (`footer.blade.php:33`). Están marcados como ficticios en el comentario `:13-16`.
  - No tiene campo de cuota del sistema, aunque `admin/programas/index.blade.php:600` dice que se configura ahí.
- **B17 ✓** Accesibilidad (**parcial**):
  - No hay focus trap.
  - Los diálogos de Grupos tienen `aria-hidden="true"` fijo (`cursos/index.blade.php:219,319`) y `cursos.js` nunca lo cambia.
  - El modal de Escuela (`programas:619-620`) y los modales del Superadmin no tienen `role="dialog"`.
  - Ningún combobox se puede usar con el teclado (flechas, Enter) y las filas clicables no reciben foco.
  - Hay botones de solo ícono sin nombre (`cursos:205,209,230,323`, `programas:623`) y buscadores sin label (`docentes:81`, `programas:98`, `cursos:81`, `horarios:84` y 4 del Superadmin).
  - `programas/index.blade.php:142` usa `strtolower` en vez de `mb_strtolower`.
  - **Corrección:** `aria-selected` sí existe en casi todas las pantallas; solo falta en los tabs de Programas, Docentes y Grupos. El foco también se devuelve al cerrar en Acudientes, Estudiantes y Matrículas, no solo en Asistencia, Calificaciones y Pagos.
- **B18 ✓** Restos (**parcial**):
  - `href="#"` en Contabilidad, Comunicados, Foro y Reportes (`admin/layout.blade.php:38,51,57`, `sidebar.blade.php:134`).
  - Programas › Documentos es maqueta (`programas/index.blade.php:368-404`), y su botón "Subir documento" (`:400`) no hace nada. Docentes › Documentos dice "próximamente".
  - `escuelas.js` es ES6 y el resto ES5.
  - Hay un BOM literal en los CSV de `asistencia.js:623`, `calificaciones.js:567` y `pagos.js:1045`.
  - `configuracion.css` tiene BOM y tildes dañadas en los comentarios.
  - **Corrección:** `@section('page-title')` no es código muerto en todos lados. El Superadmin lo usa como título del topbar (`superadmin/layout.blade.php:28`). En el Admin se define en 12 vistas, pero su layout nunca lo lee.
- **B19 ✓** El bloque `RESERVADO PARA EL PANEL ADMIN` (`sedes-programas.blade.php:124-495`, unas 370 líneas) es código muerto. Programas del Admin se reconstruyó aparte y Asignaciones y Periodos no existen. Arrastra JS muerto (`sedes-programas.js:5-32, 101-154`) y CSS muerto (`sedes-programas.css:123-144`).
- **B20 ✓ (2ª)** Un campo enviado como arreglo en lugar de texto da 500 (reproducido con `telefono[]` en `/profile`). El `(string)` del `prepareForValidation` (`ProfileUpdateRequest.php:16`) lanza "Array to string conversion". El mismo patrón está en el `prepareForValidation` de todos los Requests del Admin.
- **B21 ✓ (2ª)** Restablecer la clave (`EstudianteController.php:98`, `AcudienteController.php:118`) no cambia el `remember_token`, así que las sesiones y cookies abiertas siguen valiendo con la clave vieja.
  - **Corregido:** la secretaría usa `SesionesService::restablecerClave` (cambia `remember_token` y borra sus sesiones), igual que el panel del Admin.
- **B22 ✓ (2ª)** `ultimo_acceso` solo se actualiza al entrar por el formulario (`LoginRequest.php:65`). Quien entra con "Recordarme" no lo actualiza, así que "Último ingreso" y "nunca ha ingresado" (`AcudienteService.php:102`) quedan desactualizados. Arreglo: un listener del evento `Login`.
  - **Corregido:** un listener del evento `Login` (`AppServiceProvider::registrarIngresoRecordado`) atiende el ingreso por cookie: revisa la inactividad y guarda `ultimo_acceso`.
- **B23 ✓ (2ª)** Una cuenta inactiva puede restablecer su clave por `forgot-password`/`reset-password` (`NewPasswordController.php:43-49` no mira `activo`). Después no puede entrar, así que no evade el bloqueo.
  - **Corregido:** una cuenta desactivada no recibe el enlace (la respuesta es la misma, para no revelar su estado) y un enlace pedido antes no cambia su clave.
- **B24 ✓ (2ª)** Pagos (Finanzas) muestra menos "pagado" que Matrículas cuando hay abonos en cuotas anuladas (reproducido: 452.000 contra 552.000). `CarteraService.php:227` descarta los cobros anulados, pero `PagoService::resumen` (`:494`) cuenta su `disponible()`. Se ve también en "Por programa".
- **B25 ✓ (2ª)** El recibo y la auditoría dicen "Abono" aunque el recibo complete el cobro (reproducido). `PagoController.php:76,136` comparan `pivot.monto < monto_total` sin contar lo ya abonado antes.
- **B26 ✓ (2ª)** Los recibos de una matrícula anulada desaparecen de "Pagos recibidos" (`CarteraService.php:224` excluye la matrícula entera), así que el consecutivo RC muestra huecos sin rastro en Finanzas.
  - **Corregido:** `CarteraService` también carga las matrículas anuladas; no salen en Por cobrar, pero sus recibos siguen en Pagos recibidos.
- **B27 ✓ (2ª)** Doble envío de una devolución: `DevolucionRequest` revisa `totalAdelantado` fuera del bloqueo. El segundo envío no mueve dinero, pero deja en la auditoría "se usó completo para cubrir lo que debía" (`PagoController.php:88-91`), y eso es falso.
  - **Corregido:** `registrarDevolucion` vuelve a revisar lo adelantado con la matrícula bloqueada y rechaza el segundo envío.
- **B28 ✓ (2ª)** Plan por periodo cuando la duración no es múltiplo del periodo (`PagoService.php:73,106`). Caso: 8 meses por trimestre, las cuotas caen en los meses 0, 2 y 5, y la cuota del sistema suma 15.999 en vez de 16.000. El plan `periodo` no tiene ninguna prueba.
  - **Corregido:** cada periodo dura lo de su tipo y el último lo que quede (`Programa::mesesDelPeriodo`); cada cuota vence al empezar su periodo y cobra la cuota del sistema de sus meses. Afecta a 7 programas de `esat` (las cuotas ya generadas no se tocaron). Pruebas en `CobrosRevisionTest`.
- **B29 ✓ (2ª)** "Cuota N de {total}" se calcula con la duración actual en el recibo, en Cartera y en Matrículas (`PagoController.php:64`, `CarteraService.php:125`, `MatriculaController.php:224`). Después de cambiar la duración dice, por ejemplo, "Cuota 12 de 18". Ligado a A3.
  - **Corregido:** `Matricula::totalCuotas()` = la última cuota que se le generó; lo usan Matrículas, el recibo, Cartera, el resumen y graduar.
- **B30 ✓ (2ª)** El Superadmin arma las iniciales con `strtoupper(substr(…,0,1))` (`superadmin/partials/topbar.blade.php:18`, `superadmin/profile.blade.php:13`). Con un nombre que empieza por Á, É, Ñ u Ó sale un carácter roto; el Admin ya usa `mb_`.
  - **Corregido:** `mb_strtoupper(mb_substr(…))`.
- **B31 ✓ (2ª)** Los CDN del sitio público (Bootstrap, AOS, Leaflet) no tienen `integrity` (SRI) (`layouts/app.blade.php:15-17,32-34`).
  - **Corregido:** GSAP, ScrollTrigger, Lenis y Leaflet llevan `integrity` + `crossorigin` (las fuentes de Google no admiten SRI).
- **B32 ✓ (2ª, posible)** La grilla "Horario general" agrupa las filas por `jornadaDeHora(hora_inicio)` (`horarios.js:627`), y `JORNADAS` (`:14`) no incluye "Fines de semana". Los grupos de fin de semana aparecen en Mañana, Tarde o Noche: es solo visual, pero choca con la regla de no deducir la jornada de las horas.
  - **Corregido:** la fila es la jornada del grupo, con "Fines de semana".
- **B33 ✓ (2ª)** Inicio del Admin (maqueta): el acceso rápido dice "Inscribir y asignar programa", lo que contradice que Estudiantes ≠ Matrículas. Además, "Nuevo estudiante" y "Registrar pago" llevan a `#` aunque esas pantallas ya existen (`admin/dashboard:132,209,303,310,324`).
- **B34 ✓ (2ª)** Índices (comprobado con `EXPLAIN QUERY PLAN`):
  - el historial de Matrículas busca en `auditorias` por `entidad_tipo` + `entidad_id` sin índice, y esa tabla crece sin límite;
  - `vencerAtrasados` recorre toda la tabla `pagos` (`whereDate` impide usar índice);
  - `curso_sesiones(dia, hora_inicio)` es un índice que nadie usa.
  - El más importante está en M26.
- **B35 ✓ (2ª)** La BD borra en cascada el historial de dinero:
  - `users` → `matriculas` → `pagos`, `recaudos`, `devoluciones`, `calificaciones` y los pivotes.
  - Hoy ninguna ruta lo dispara (C3 quitó la única), pero borrar un usuario desde phpMyAdmin, tinker o un futuro "eliminar estudiante" se llevaría todo.
  - Los `SET NULL` de `registrado_por`, `anulado_por`, `auditorias.user_id` y `matriculas.origen_id` borran la autoría.
  - **Arreglo:** RESTRICT en `matriculas.estudiante_id`, `recaudos/devoluciones.matricula_id` y `recaudo_pago.pago_id`.
  - **Corregido:** migración `2026_11_11_000005` (RESTRICT en matrículas, cobros, recibos, devoluciones, sus pivotes y calificaciones) y `2026_11_11_000006` (RESTRICT en la autoría: quién registró o anuló recibos, devoluciones y egresos, quién puso notas y asistencia, la auditoría y `matriculas.origen_id`), ya corridas en `esat`. Una cuenta con algo a su nombre no se borra: se desactiva.
- **B36 ✓ (2ª)** Puntos para endurecer el escape (no explotables hoy):
  - `asistencia.js:32`: `fmtTel` no escapa; es seguro solo porque `contacto()` escapa (`:511`).
  - `calificaciones.js:42`: tiene una copia sin uso de esa versión de `fmtTel`. Si alguien copia cualquiera de las dos, reabre C1.
  - `js_website.js:308`: el tooltip del mapa se arma con `innerHTML`. Será XSS si el footer pasa a leer la tabla `sedes` (P4).
  - `superadmin/auditoria:180` y `superadmin/estudiantes:231` usan `{!! json_encode() !!}`, seguro mientras sean datos fijos: al conectarlas a la BD, usar `@json`.
  - **Corregido:** `fmtTel` de Asistencia devuelve solo dígitos, se quitó la copia sin uso de Calificaciones, el tooltip del mapa del inicio es texto y el Superadmin usa `@json`.
---

## 5. Sitio público

- **P1 ✓** 17 de 34 páginas muestran a los visitantes "Foto pendiente — public/assets/img/…" porque faltan 56 fotos: 9 de Escuela Ciencias (5 por página), Barbería (4) y las 7 de Educación Continuada.
- **P2 ✓** `public/assets/img` pesa 133 MB (PNG de 2,2 a 2,5 MB). Las imágenes del inicio suman 9,9 MB, y solo el carrusel (3 PNG) son 6,8 MB. No hay `loading="lazy"`, `srcset` ni WebP.
- **P3 ✓ (parcial)** Bootstrap se carga en las 35 páginas (`layouts/app.blade.php:15,32`) y solo lo usa el carrusel del inicio (`welcome:6`). Las teselas del mapa salen directo de `tile.openstreetmap.org` (`js_website.js:263`). **Corrección:** cargar Leaflet en todas las páginas sí se justifica, porque el mapa del footer está en todas.
- **P4 ✓** La navegación está definida dos veces: escritorio en `header.blade.php:61-135` y móvil en `:157-197`. Las sedes y coordenadas del footer están fijas (`footer.blade.php:32-37`) y no salen de la tabla `sedes`.
- **P5 ✓** Las redes sociales apuntan a `href="#"` (`footer:13-16`, `login:102-105`, `forgot-password:78-81`). El newsletter es falso: muestra "¡Gracias!" sin guardar nada (`welcome.blade.php:276`, `js_website.js:386-405`).
- **P6 ✓** SEO: no hay canonical, Open Graph, JSON-LD ni sitemap.
- **P7 ✓** Las 34 páginas dicen "ETDH", pero PRODUCT.md (`:91`) pide no agregarlo salvo que se solicite. Uno de los dos está desactualizado.

---

## 6. Repositorio y entorno

- **R1 ✓** El repo pesa 224 MB empaquetado:
  - el mayor peso es `public/assets/img` (130,5 MB, ver P2);
  - `.impeccable/` ocupa 86 MB (243 capturas PNG y 4 JPG, y `hook.cache.json` con rutas locales `C:\xampp\htdocs\esat\…`);
  - `.claude/skills/impeccable/.../impeccable.exe` pesa 14,6 MB (6,4 MB comprimido);
  - `salvamento-aprender.png` de la raíz es el mismo blob que el de `public/assets/img`: no ocupa espacio extra, solo estorba.
  - Borrar archivos ahora no achica el repo: hay que reescribir el historial (`git filter-repo` + push forzado).
- **R2 ✓** `public/js/js_wbesite.js` está vacío, con el nombre mal escrito, y nadie lo usa. El real es `public/assets/js/js_website.js`.
- **R3 ✓** `package.json` tiene `tailwindcss` 3.4 (lo usa `postcss.config.js`) y `@tailwindcss/vite` 4, que no aparece en `vite.config.js`. Vite/Tailwind solo compilan para los layouts de Breeze.
- **R4 ✓** `pdo_sqlite` está desactivado en `C:\php\php.ini` (`;extension=pdo_sqlite`, línea 949; la DLL sí existe). Sin la extensión fallan 147 de las 148 pruebas actuales. Con ella pasan todas (`php -d extension=pdo_sqlite vendor/bin/phpunit`).
- **R5 ✓ (Medio)** `/public/build` está ignorado. Sin `npm run build`, `@vite` lanza `ViteManifestNotFoundException` (500) en `/dashboard` (donde caen estudiante, docente y acudiente), `/profile` (todos los roles) y las pantallas de restablecer, verificar y confirmar clave. En un CI limpio fallarían además varias pruebas (`ProfileTest`, `CuentaHabilitadaTest`, `PasswordResetTest`).
- **R6 ✓ (2ª, Medio)** `composer dev` no funciona en Windows: `php artisan pail` necesita `pcntl` (lanza RuntimeException) y `--kill-others` tumba también el servidor y Vite. `queue:listen` sobra porque no hay jobs en cola. Arreglo: quitar `pail` y `queue` del script, o documentar `php artisan serve --port=8765`.
- **R7 ✓ (2ª, Alto al desplegar)** `.env.example` trae:
  - `APP_ENV=local` y `APP_DEBUG=true`;
  - `SESSION_ENCRYPT=false` (las sesiones quedan en texto plano en la BD, como en el volcado) y sin `SESSION_SECURE_COOKIE`;
  - `LOG_LEVEL=debug` en un solo archivo sin rotación.

  Arreglo: una lista de verificación para producción. **Hecho:** tabla de variables en el README ("Puesta en producción").

---

## 7. Base de datos (`database/esat.sql`, exportado el 21-10-2026)

**✓ Esquema:** coincide exactamente con las migraciones:
- están aplicadas las 51;
- son las mismas 37 tablas, con las mismas columnas y nulabilidad;
- hay 56 FK en cada lado.

**Datos:**
- 66 usuarios: 1 superadmin, 3 admin, 11 docentes, 35 estudiantes y 16 acudientes.
- 30 matrículas, 228 cobros, 188 recaudos (9 de 2025 y 179 de 2026), 2.613 registros de asistencia y 135 calificaciones (de 2,4 a 4,9).
- Las sedes en la BD local son "Prueba1" (Bogotá), Sasaima, Guaduas, La Dorada y una de Villeta. **Todos los datos de prueba están en Villeta**; las otras sedes no tienen estudiantes, matrículas ni grupos. Solo Prueba1 y Villeta tienen admin.

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

**✓ (2ª) Pantallas con los datos reales.** Se cargó el volcado completo en la BD de pruebas y se recorrió cada pantalla:

| Quién | Pantalla | HTTP | Consultas | Peso |
|---|---|---|---|---|
| Admin de Villeta | las 11 del panel + perfil | todas 200 | 1–60 | 37–238 KB |
| Admin de Villeta | Matrículas | 200 | 60 | 238 KB (M17) |
| Admin de Villeta | Grupos | 200 | 36 | 98 KB (B9) |
| Admin de Villeta | Asistencia / Calificaciones | 200 | 11 / 10 | 167 / 153 KB |
| Admin de Prueba1 (sede vacía) | Matrículas | 200 | 24 | 33 KB (M17) |
| Superadmin | las 6 del panel + perfil | todas 200 | 0–3 | 23–64 KB |

- Los 188 recibos abren sin error.
- La constancia responde 404 en las 3 matrículas no vigentes, y el paz y salvo en las 25 que deben algo o no tienen plan. Es lo esperado: la ficha solo muestra esos botones cuando aplican (`matriculas.js:773-778`).
- No hubo ningún error 500.

---

## 8. Pruebas

En la primera revisión había 140 pruebas con 643 aserciones, y todas pasaban con `pdo_sqlite` cargado. Después de corregir C1–C5 son **148 pruebas y 678 aserciones**, todas en verde.

| Archivo | Pruebas | Qué cubre |
|---|---|---|
| `MatriculasTest` | 41 | Matricular, estados, derecho (también con la sesión abierta), cambio de grupo/programa, devoluciones, documentos imprimibles |
| `HorariosTest` | 22 | Jornada, tope, cruces y protecciones desde Grupos, Docentes y Programas |
| `AsistenciaTest` | 8 | Clases dictadas, % de asistencia, aplazados, filtro por sede |
| `CalificacionesTest` | 6 | Estados de módulo y requisito de módulos aprobados para graduar |
| `PagosTest` | 4 | Cartera y alcance por sede |
| `Superadmin/AdministradoresTest` | 4 | C4 |
| `Auth/CuentaHabilitadaTest` | 3 | C5 |
| Estudiantes, Acudientes, Auth, Perfil | resto | — |

**Pruebas de reproducción de la segunda revisión (fuera del repo):**
- 20 de finanzas: A3–A10, M15, M16, B1, B2, B6–B8, los nuevos de dinero, y que **ninguna** de las 8 escrituras de Matrículas acepta una matrícula de otra sede.
- 18 de académico y personas: M5–M18, A7, A8, B3, B4, B9 y los nuevos de perfil, login y grupos.
- Una prueba de humo con los datos reales del volcado.

Al corregir cada hallazgo, conviene pasar su reproducción a `tests/` como prueba de regresión.

**Huecos en las pruebas del repo:**
- ~~El plan `periodo` no se prueba nunca.~~ Ya se prueba (`CobrosRevisionTest`).
- Las escrituras de Matrículas entre sedes no tienen prueba en el repo (la reproducción confirmó que hoy están bien).
- No se prueba crear, listar ni cambiar el estado de Programas, Docentes y Grupos. El CRUD de Escuelas tampoco tiene pruebas.
- El Superadmin solo tiene las de C4; Sedes no tiene pruebas.
- Faltan pruebas de:
  - ~~cambio de año del consecutivo~~ y ~~el comando `pagos:vencer`~~: `CobrosRevisionTest`;
  - ~~concurrencia~~: `ConcurrenciaTest`;
  - ~~editar horarios o fechas de un grupo que ya tiene asistencia~~: `HistorialGruposTest`.

---

## 9. Lo que está bien

- Arquitectura MVC clara:
  - controladores delgados;
  - reglas en servicios por dominio y en el `after()` de los Form Requests;
  - mensajes en español.
- Alcance por sede bien aplicado en Estudiantes, Acudientes y Matrículas, sin IDOR al cambiar IDs. Las excepciones están en M11 y M12.
- Dinero en `DECIMAL`, sin errores de redondeo en lo normal (salvo M19 y B28).
- Transacciones y `lockForUpdate` en pagos, devoluciones, cupos y consecutivos. Los consecutivos nunca retroceden.
- La regla "un cambio arriba no invalida lo de abajo" está implementada y tiene pruebas.
- Los CSV neutralizan fórmulas en las 7 exportaciones. `@json` se usa bien en el panel Admin.
- Tras C1–C2 no queda ningún texto editable por un usuario que se pinte sin escapar.
- Todas las pantallas funcionan con los datos reales, sin errores.
- Los datos del volcado son coherentes con casi todas las reglas.
- Las constantes de los modelos coinciden con los enums de la BD, salvo `tipo_documento` (M28) y el tope de notas (B4).

---

## 10. Auditoría del panel Admin (11-11-2026)

Revisión del panel **Admin** (rol `admin_general`, `/general`) con **varias sedes trabajando a la vez**. Todo lo que
encontró quedó corregido el mismo día; las pruebas están en `tests/Feature/AdminGeneral/AuditoriaPanelTest.php` y
`EstructuraTest.php`.

**Cómo se probó:**
- Copia de la BD (`esat_auditoria`) con 3 sedes más funcionando (Sasaima, Guaduas y La Dorada, esta sin secretaría),
  armadas con los servicios reales de la app: 34 matrículas más, pagos al día y en mora, aplazados, retirados,
  asistencia, notas, egresos, comunicados y foro con reportes.
- Texto malicioso (`<img src=x onerror=...>`) en nombres de sedes, escuelas, programas, módulos, estudiantes, docentes,
  acudientes, comunicados, foro, egresos, motivos y auditoría. Solo existe en la copia.
- Navegador real: las 14 pantallas del Admin (534 fichas y detalles abiertos) y las 15 de la secretaría entrando a una
  sede.
- 276 combinaciones rol × ruta de `/general`, cifras del Admin contra el Inicio de cada secretaría y 26 chequeos de
  integridad sobre la BD `esat`.

**Lo que salió bien:**
- Ningún texto malicioso se ejecutó; se ve como texto en todas las pantallas.
- Ninguna ruta de `/general` responde a secretaría, docente, estudiante, acudiente ni superadmin (403/404).
- Activos, ingresos del mes, grupos en clases y mora del Admin = los de cada secretaría, en las 5 sedes.
- 23 de los 26 chequeos de la BD en cero (los otros 3 son los datos pendientes de abajo).

| ID | Gravedad | Qué pasaba | Arreglo |
|---|---|---|---|
| AU1 | Alta | Cambiar el precio de un programa cambiaba lo que paga quien ya estaba matriculado (las cuotas usaban el precio del día en que elegía su plan). | **Precio pactado**: `matriculas.precio_programa` se guarda al matricular y las cuotas usan `Matricula::precioPrograma()`. Migración `2026_11_11_000001`; las 30 matrículas de `esat` se llenaron con el precio de su programa (todas cuadraban con él). |
| AU2 | Alta | Cambiar la duración con estudiantes en clases dejaba sus cuotas descuadradas (es A3). | La duración (meses y división) **no cambia con matrículas activas o aplazadas**; el formulario la muestra bloqueada. |
| AU3 | Media | Cambiar la duración reescribía la fecha de fin de los grupos finalizados (y su historial). | Solo se recalculan los no finalizados; todos los cálculos usan `Curso::fin()` (el fin guardado si ya finalizó). |
| AU4 | Media | Editar un comunicado enviado a varias sedes revivía la copia que una secretaría había archivado. | Las copias archivadas no se tocan al editar. |
| AU5 | Media | Si la secretaría de la primera sede archivaba su copia, el Admin ya no podía archivar el envío en las demás y lo veía como archivado. | El estado del envío sale de las copias vivas; al archivar, las ya archivadas conservan su motivo; la ficha dice dónde lo archivó la secretaría. |
| AU6 | Media | Con dos pestañas en sedes distintas, lo hecho en la pestaña vieja se guardaba en la sede elegida después. | El layout de la secretaría manda `X-Sede-Trabajo` en cada `fetch`; si no coincide, 409 con "Recarga la página…". |
| AU7 | Baja | Escape en Programas de la secretaría daba `closeModal is not defined`. | Se quitó la llamada sobrante. |
| AU8 | Media | Un programa sin malla dejaba graduar sin ninguna nota. | `EstadoRequest` lo impide y Matrículas lo avisa (`sin_malla`). |
| AU9 | Media | Editar cualquier dato de un programa volvía a revisar cruces de horario aunque no cambiaran horas ni duración: un cruce viejo bloqueaba cambios que no tenían que ver. | Los horarios solo se revisan si cambian las horas o la duración. |

**Mejora pedida en la misma revisión:** al **crear y al editar** un programa, el paso 4 "Sedes" elige dónde se ofrece:
todas, algunas o ninguna por ahora. Mismas reglas que el interruptor de la ficha (no se quita de una sede con grupos
vigentes, un inactivo no se ofrece en sedes nuevas); cada sede que entra o sale queda en la auditoría de esa sede.
Decidido con el usuario: el precio sigue siendo uno para todas las sedes y un tema del foro es de una sola sede.

**Datos de `esat` pendientes de decisión del usuario:**
- Programas sin malla: "Ingles B1", "Desarrollo web" (1 matrícula) y "Tecnico en matematicas"; sus horas no cuadran con
  la malla.
- "Grupo A" (CUR-ADM-002-A) sigue activo aunque terminó el 02-11-2026.
- Sede "Prueba1" con 2 secretarías de prueba (María Pérez y Pedro Ramírez, `admin@esat.test`).

## 11. Auditoría del panel Estudiante (27-11-2026)

Revisión de todo el panel **Estudiante** (rol `estudiante`, `/estudiante`) sobre `develop` después del PR #59 (panel
Docente terminado). Incluye lo que el estudiante comparte con otros roles: el Aula virtual del docente, Mis pagos y el
plan, comunicados, foro, perfil, login y bloqueo de cuentas.

**Cómo se revisó:**
- Cuatro revisiones independientes: seguridad, lógica contra las reglas de este documento y de `CLAUDE.md`, frontend
  (XSS, reloj, accesibilidad, 375 px, impresión), y pruebas, estructura y rendimiento.
- Cada hallazgo se volvió a comprobar contra el código. Los cuatro más serios se **reprodujeron con pruebas** sobre
  SQLite en memoria (no tocan `esat`); esas pruebas no quedaron en el repo.
- **✓ reproducido** = hay una prueba que lo muestra. **✓ código** = se siguió el camino completo en el código.
  Sin ✓ = lo reportó una revisión y no se volvió a trazar.
- Suite completa: **391 pruebas OK** (`CLAUDE.md` todavía dice 372). No hay Xdebug ni PCOV: la cobertura no se midió.

**Lo que salió bien:**
- **Ningún XSS:**
  - todo lo que viene de la BD pasa por `esc()` antes de `innerHTML`;
  - `@json` escapa `</script>`;
  - el único `{!! !!}` es texto fijo;
  - lo que escribe un estudiante sale escapado en los paneles del Docente, la secretaría y el Admin.
- **Nada de otro estudiante:**
  - cada descarga y escritura revisa que el recurso sea suyo (entregas, guía, material, comunicados, adjuntos del foro);
  - el payload no trae datos personales de compañeros, ni lo oculto del foro, ni quién reportó;
  - las preguntas de una evaluación con tiempo no salen antes de comenzarla.
- **No hay asignación masiva en el perfil:** el estudiante solo cambia teléfono, dirección y correo.
- **Doble envío protegido:** responder dos veces, reiniciar el reloj, entregar después de la fecha o ya calificada,
  elegir el plan dos veces y reportar dos veces se rechazan con la fila bloqueada y, además, con índices únicos.
- **Archivos:** las subidas validan el tipo real, se guardan con nombre aleatorio en el disco privado y sus rutas solo
  llevan ids.
- **Fuera del panel:** no hay registro público, y los otros roles reciben 403 en `/estudiante`.
- **Lo que ve el estudiante cuadra con la secretaría:**
  - asistencia, con festivos, "No hubo clase" e historial de grupos;
  - promedio y aprobados;
  - resumen de pagos de la matrícula que se muestra;
  - destinatarios de comunicados y reglas del foro.
- **Sin rastros de la maqueta:** no quedan `datos-ejemplo`, `?ejemplo` ni textos en inglés.

### Altos

#### ES1 ✓ Una evaluación con tiempo entrega las respuestas correctas y todavía acepta el envío (reproducido) — corregido
- `AulaService::paraEstudiante` (`AulaService.php:69`, `:101`) manda `correcta` en cuanto pasa `fecha_limite`.
- `responder` (`:183-187`) acepta hasta `terminaPara(inicio) + GRACIA_SEGUNDOS` y no revisa el cierre (`puedeResponder(…, false)`).
- **Reproducido:** la evaluación dura 30 min y cierra a las 09:20; Laura la comienza a las 09:00.
  1. A las 09:20:30 abre el Aula: cada pregunta trae su `correcta`.
  2. Envía esas respuestas y recibe 200: "tu nota es 5,0".
- **Variante:** un compañero que ya terminó le pasa las correctas a quien sigue dentro de su minuto de gracia.

**Arreglo:** una sola regla, por ejemplo `Evaluacion::correctasVisibles($ahora)` = `ahora > fecha_limite + GRACIA_SEGUNDOS`.
La usan `paraEstudiante` y el texto de `aula.js:359` ("Abajo están las respuestas correctas").

#### ES2 ✓ El docente puede reabrir una evaluación que ya cerró (reproducido) — corregido
- `EvaluacionRequest.php:59`: `after:now` acepta cualquier cierre futuro.
- `mismoCierre()` (`:117`) solo exime cuando se conserva el mismo cierre, así que nada impide alargar uno vencido.
- **Reproducido:** la evaluación cerró a las 09:30 y a las 10:00 Laura ya ve las correctas. El docente le pone cierre al
  día siguiente y Laura responde con 5,0.
- Sin respuestas, las preguntas se vuelven a crear con ids nuevos, pero el texto de la opción correcta es el mismo.

**Arreglo:** si la evaluación ya cerró, solo se conserva su cierre; para dar más tiempo se publica otra.

#### ES3 ✓ El Inicio se congela si el grupo todavía no tiene horario (código) — corregido
- `dashboard.js`, `pintarHorario`: con `M.horario = []`, `Math.min.apply(null, [])` da `Infinity` y `Math.max` da `-Infinity`.
- El `while (hFin - hIni < RANGO_MIN_H)` nunca termina y la pestaña se cuelga.
- Sí pasa: un grupo existe sin horario ("Nuevo horario" solo ofrece grupos sin sesiones) y matricular no lo exige.
- Es la primera pantalla después del login.
- El Docente tiene la guarda (`docente/dashboard.js:397`, `#ini-sem-empty`); en la copia se perdió.

**Arreglo:** copiar esa guarda al principio de `pintarHorario` ("Tu grupo todavía no tiene horario").

### Medios

#### ES4 ✓ Cinco claves malas con el correo de alguien lo sacan de la sesión que ya tenía abierta (reproducido) — corregido
- `BloqueoService::registrarFallo` bloquea la cuenta 15 minutos (`MINUTOS_INTENTOS`).
- `EnsureAccountIsEnabled::motivo` revisa `bloqueada()` en cada petición y cierra la sesión abierta.
- **Reproducido:** con la sesión de Laura abierta, 5 fallos con su correo → su siguiente petición recibe 401.
- **Impacto:**
  - puede tumbar a un compañero en medio de una evaluación con tiempo o antes de una fecha de entrega;
  - se repite cada 15 minutos y se pueden dejar 4 fallos listos para disparar el quinto cuando convenga;
  - vale para todos los roles salvo el Admin y el superadmin.

**Arreglo:** el bloqueo por intentos fallidos impide los **ingresos nuevos**, no corta una sesión abierta (`motivo()` en
cada petición ignora `intentos_fallidos`). Además, contar los intentos por correo + IP.

#### ES5 ✓ Mis pagos no muestra la deuda de otra matrícula (reproducido) — corregido (aviso; los recibos de la otra matrícula siguen sin verse)
- `PanelEstudianteService::pagosDe` solo mira la matrícula que se muestra (`matriculaVigente`).
- La regla de este proyecto dice: "la cartera de un estudiante suma lo vencido de **todas** sus matrículas". Pagos de la
  secretaría la cobra y el paz y salvo se le niega.
- **Reproducido:** matrícula retirada con cuotas vencidas y una nueva activa. El panel dice vencido $0 y "al día".
- Tampoco ve los recibos de la vieja.

**Arreglo:** que el estado de cuenta sume lo vencido y lo por pagar de todas las matrículas no anuladas, o al menos que
avise "Debes $ X de tu matrícula anterior".

#### ES6 ✓ El envío automático de una evaluación con tiempo se puede perder (código) — corregido
- `aula.js`, `tic()`: pone `ev.intento.enviando = true` y llama `mandar()`, que sale sin hacer nada si hay otro envío en curso.
- **Se pierde:**
  - si al llegar a 0:00 había otro envío en curso (el de otra evaluación), esa evaluación nunca se envía;
  - si el envío automático falla por la red, no se reintenta aunque el servidor lo aceptaría dentro de la gracia.
- La pantalla se queda en "Quedan 0:00".

**Arreglo:** marca de envío por evaluación (no la global) y reintento dentro de la gracia; si se rechaza, marcarla
`expirada`.

#### ES7 ✓ El aviso promete un envío que no pasa si se cierra la ventana (código) — corregido sin guardar las marcas en el servidor
- `aula.js:309`, `:322`, `:429` dicen: "si cierras esta ventana el tiempo sigue corriendo. Cuando se acabe se envía lo
  que hayas marcado".
- Pero solo envía la pestaña que tiene el Aula abierta: si la cierra o se va a otra pantalla, queda sin responder
  (regla del servidor: quien la comenzó y no envió a tiempo queda sin responder).
- **Dos pestañas o dispositivos:** las dos envían al llegar a cero, cada una con sus marcas; gana la primera, aunque la
  otra fuera la que tenía las respuestas.

**Arreglo:** corregir el texto ya. De fondo, guardar las marcas en el servidor mientras responde y calificar al vencer.

#### ES8 ✓ En un computador compartido, las marcas de una evaluación pasan al siguiente estudiante (código) — corregido
- `aula.js`, `leerMarcas`/`guardarMarcas`: la clave de `sessionStorage` es `'aul-marcas-' + id`, sin el estudiante.
- `sessionStorage` sobrevive al cerrar sesión en la misma pestaña.
- **Escenario (sala de sistemas):**
  1. Laura marca parte de la evaluación 12 y cierra sesión.
  2. Juan entra en la misma pestaña, abre la evaluación 12 y la ve marcada.
  3. Si es con tiempo, el envío automático lleva las marcas de Laura.

**Arreglo:** poner el id del estudiante en la clave y borrar las `aul-marcas-*` al cerrar sesión.

#### ES9 ✓ Un aplazado, retirado o graduado ve tareas que no puede hacer (código) — corregido
- `PanelEstudianteService.php:84` arma el Aula sin mirar el estado de la matrícula.
- `aula.js` muestra Comenzar, Responder y Entregar, y `dashboard.js` las cuenta en "N cosas pendientes".
- El servidor las rechaza al final ("Solo responde quien tiene la matrícula activa").

**Arreglo:** mandar una bandera como `participaForo` (por ejemplo `puedeAula`) y mostrar todo como solo consulta, sin
contarlo en el Inicio.

#### ES10 ✓ "Sin nota todavía" en módulos que el docente no puede calificar (código) — corregido
- `PanelEstudianteService::modulos` solo marca `no_cursado` si el módulo terminó antes del **inicio de la primera
  ventana**.
- El docente decide con el cruce contra **todas** las ventanas (`cursoAqui` en `docente/datos.js`, `NotasRequest`).
- **Escenario:** aplazada el 20-08.
  - "Normativa" (24-08 al 14-09) le sale "tu docente todavía no pone la nota", pero el docente no puede ponerla ("no
    cursó este módulo").
  - El módulo en curso le sale "En curso" aunque está aplazada.
- `CalificacionService.php:194`, `:209` usa la misma regla: esos módulos le cuentan al docente como pendientes.

**Arreglo:** `no_cursado` cuando el módulo no se cruza con ninguna ventana de `ventanasEn`, en los dos servicios.

#### ES11 ✓ Elegir el plan después de un reintegro cobra los meses de aplazamiento (código) — corregido
- `PagoService::elegirPlan` (`:150`) siempre genera desde `primeraCuota($grupo)`.
- El reintegro (`MatriculaService.php:97`) genera "desde el reintegro, o desde que empieza ese grupo si es después".
- **Escenario:** se matricula sin plan, se aplaza, se reintegra meses después y entonces elige el plan. Las cuotas de
  los meses en que estuvo aplazado le quedan vencidas de una vez.
- El aviso de cuántas quedarían vencidas (`cuotasVencidasSiElige`) repite el mismo cálculo.

**Arreglo:** que `elegirPlan` y el aviso usen la misma fecha de arranque que el reintegro.

#### ES12 ✓ Tras un cambio de grupo desaparece el Aula del grupo anterior (código) — corregido
- `paraEstudiante` (`AulaService.php:62`) solo consulta el grupo actual: sus resultados, entregas y notas del grupo
  viejo dejan de verse.
- `estudioEn` (`:139`) mira `matriculas.curso_id`, no `matricula_grupos`: la guía y el material del grupo viejo dan 404.
- Eso contradice "lo descarga quien tiene (o tuvo) matrícula en el grupo".

**Arreglo:** recorrer los grupos de `matricula_grupos` en los dos.

#### ES13 ✓ El archivo anterior se borra antes de que la petición confirme (código) — corregido
- `AulaService::entregar` (`:257-260`) borra el archivo viejo al salir del `DB::transaction` interno.
- Dentro de `TransaccionEscritura` esa transacción interna es solo un punto de guardado: la petición todavía no confirmó.
- Si después falla `datos()` (500), la BD vuelve a apuntar al archivo viejo, que ya no existe, y el nuevo queda huérfano.
- Mismo patrón en la guía y el material del docente (`:121`, `:173`).

**Arreglo:** `DB::afterCommit(fn () => Storage::delete($viejo))` y borrar el nuevo si se deshace.

#### ES14 ✓ Ninguna escritura del estudiante tiene límite de frecuencia (código) — corregido
- `route:list` no muestra `throttle` en ninguna ruta de `/estudiante`.
- **Ejemplo:** un bucle de temas del foro con adjuntos de 5 MB llena `storage/app/private/foro/{sede}` (hosting
  compartido) y llena de spam el foro que ven menores de edad hasta que alguien modere.
- Lo mismo con reportes, entregas de 10 MB e `iniciar`.

**Arreglo:** `throttle` por usuario en las escrituras (por ejemplo 10 por minuto en el foro) y un tope diario de adjuntos.

#### ES15 ✓ Cada página y cada escritura arma y manda todo el panel (código; medido en la revisión) — corregido en parte (el foro y la consulta por tema)
- **N+1 en el foro:** `ForoTema::primerMensaje()` (`ForoTema.php:56`) hace `$this->mensajes()->first()` y no usa la
  relación ya cargada. `ForoService::panel` llama `oculto()` por cada tema: **una consulta por tema**, en cada página y
  cada escritura (también en el panel Docente).
- **Medido** con 40 temas del foro, 30 comunicados y 12 evaluaciones y 12 actividades:
  - `GET /estudiante`: 79 consultas y 217 KB de JSON (el foro son 139 KB);
  - abrir un comunicado (`leido`): 100 consultas, porque `visible()` y `datos()` arman lo mismo dos veces.
- El foro y los comunicados crecen sin límite y viajan a todas las pantallas, aunque solo los usan las suyas.

**Arreglo:** usar `$this->mensajes->first()` cuando ya está cargada, un `datos()` por pantalla (que cada escritura
devuelva solo lo que cambió) y paginar el foro.

#### ES16 ✓ Bloqueos que duran toda la petición y la fecha límite medida tarde (código) — corregido
- `responder` y `entregar` bloquean la fila **compartida** de la evaluación o la actividad (`AulaService.php:176`, `:232`).
- Ese bloqueo sigue hasta que confirma `TransaccionEscritura`, es decir, después de armar `datos()`.
- En `entregar`, el archivo de hasta 10 MB se guarda dentro del bloqueo y la fecha límite se compara después de esperarlo.
- **Consecuencia:** cerca del cierre se atiende a uno por uno. Quien envió a las 23:59:50 puede recibir "La fecha límite
  ya pasó" o un error por tiempo de espera del bloqueo.

**Arreglo:**
- tomar la hora al llegar la petición;
- guardar el archivo antes de la transacción;
- bloquear la fila del estudiante (su entrega o su matrícula) en vez de la compartida: los índices únicos ya impiden el
  doble envío.

### Bajos

**Seguridad y cuentas**
- **ES17** Los 60 s de gracia también sirven para hacer trampa: quien bloquea el envío automático gana un minuto. Bajar a
  10–15 s o guardar las marcas en el servidor (ES7).
- **ES18** Perfil (relacionado con M20):
  - el estudiante cambia su correo, que es su usuario, sin escribir su clave actual: una sesión robada se vuelve una
    cuenta robada;
  - el cambio no queda en la auditoría y `PATCH /profile` está fuera de `transaccion`;
  - puede poner su celular igual al de su contacto de emergencia (esa regla solo está en las Form Requests de la
    secretaría).
- **ES19** "¿Olvidaste tu contraseña?":
  - responde "No encontramos ningún usuario…": deja saber qué correos existen;
  - la ruta no tiene `throttle` propio (además, M27: todavía no envía el correo).
- **ES20** Endurecimiento:
  - los archivos se sirven en línea sin `X-Content-Type-Options: nosniff` ni CSP;
  - `.env.example` trae `APP_DEBUG=true` y `SESSION_SECURE_COOKIE` sin definir;
  - posible: un nombre de archivo con caracteres de control (TAB) rompe su descarga con 500. Limpiar el nombre al subir.
- **ES21** Foro:
  - los temas, respuestas y reportes del estudiante no quedan en la auditoría (decidir y dejarlo escrito);
  - `ForoService::responder` no vuelve a revisar "cerrado u oculto" con la fila bloqueada (M13).
- Ya conocido y decidido: la clave inicial es el documento y no se obliga a cambiarla (**A1**, descartado).

**Lógica y pantallas** (✓ código las marcadas; las demás las reportó la revisión)
- **ES22** Inicio y Mi carrera:
  - ✓ el "% de la carrera" (`dashboard.js:185`) es clases dictadas ÷ clases: un aplazado o retirado sale en 100 % y un
    cambio de grupo mezcla los dos grupos;
  - una evaluación con el tiempo vencido sigue en "Por responder";
  - "Lo perdiste… tienes una habilitación" con nota < 3,0 en un módulo que sigue en curso;
  - el calendario no llega a las clases del grupo anterior;
  - "Docente: " vacío si el grupo no tiene docente;
  - ✓ el boletín deja en blanco los módulos `no_cursado` (`programa.js:260`) y no dice si la matrícula está aplazada.
- **ES23** Mis pagos:
  - "A pagar este mes" y "Próxima cuota" pueden mostrar la misma cifra;
  - el pie dice "N cuotas" con la duración de hoy de la carrera y no con `totalCuotas` (B29);
  - sin plan no aparece el estado de cuenta aunque deba el derecho;
  - el aviso de vencidas no tiene en cuenta la beca del 100 % ni el saldo a favor;
  - una cuota de $0 sale "Pagado" con "—";
  - pueden aparecer textos como "anuló: null".
- **ES24** Aula, foro y comunicados:
  - el envío automático deshabilita el botón de otra evaluación abierta en el cajón;
  - cerrar el cajón del foro con un envío en curso da un TypeError o repinta otro tema y borra su borrador;
  - al abrir un comunicado el foco se pierde y el lector de pantalla lo lee dos veces;
  - en "Todos" sin comunicados dice "No tienes comunicados sin leer";
  - comunicados, instrucciones y comentarios pierden los saltos de línea (falta `white-space: pre-line`);
  - los errores 409, 429 y 500 salen como "Revisa tu conexión" en vez del mensaje del servidor;
  - dos respuestas que llegan en desorden pueden pisar el estado;
  - el enlace del material solo pasa por `esc()`: conviene aceptar solo `http(s)` también en el JS.
- **ES25** Accesibilidad e impresión:
  - el `<input type=file>` invisible más su `label` hacen dos paradas de tabulador;
  - la tarjeta del Aula (`<article tabindex=0>`) tiene un botón adentro;
  - el foco se pierde al pulsar Comenzar;
  - el menú móvil no tiene `aria-expanded`, se puede tabular estando cerrado y no se cierra con Escape;
  - el menú de usuario no tiene `aria-expanded`;
  - los errores del perfil no están ligados a sus campos;
  - hay textos de menos de 12 px;
  - con Ctrl+P, sin haber usado un botón de imprimir, sale una hoja en blanco o el último documento armado.
- **ES26** Fechas:
  - `datos($ahora)` usa `today()` en pagos y comunicados;
  - "En curso" y la cuenta regresiva usan el reloj del equipo, y `D.hoy` queda fijo si la página pasa la medianoche abierta;
  - el % de asistencia de las fichas cuenta la clase de hoy apenas se toma; el del panel, cuando termina.

**Estructura y BD**
- **ES27** Código muerto o viejo:
  - `PagoService::derechoVencido` (solo lo usa una prueba; el índice de M26 ya no sirve);
  - `PanelEstudianteService::institucion()` (el Blade de datos consulta la BD por su cuenta);
  - imports sin usar en `PanelEstudianteService`;
  - la condición `!== 'anulado'` de `:84`, que nunca se cumple;
  - la columna `entregas.contenido`;
  - comentarios viejos en `bootstrap/app.php:19` y `PanelEstudianteTest.php:11`;
  - estilos del combobox y colores por escuela sin uso en el CSS del estudiante;
  - `var URL` en `dashboard.js` tapa `window.URL`.
- **ES28** Lógica repetida o en el lugar equivocado:
  - `opcionesPlan` y `cuotasVencidasSiElige` repiten `generarCuotas`;
  - `comunicadosVisibles` repite los destinatarios de `ComunicadoService`;
  - `insertOrIgnore` de lecturas y `ForoReporte::create` están en los controladores, repetidos con el Docente;
  - el servicio importa un trait de `Http\Requests`;
  - los nombres no coinciden: `adjunto_ruta`/`adjunto_peso` contra `archivo_path`/`*_tamano`.
- **ES29** Migraciones:
  - se borran en cascada:
    - `matricula_grupos.matricula_id`;
    - `matricula_grupos.curso_id`;
    - `evaluacion_intentos.estudiante_id`;
    - el `curso_id` de `materiales`, `actividades` y `evaluaciones`.

    Va contra "nada de historial se borra" (hoy ninguna ruta borra grupos);
  - `Programa::mallaEnUso` no cuenta el material: si se quita un módulo, su material pasa a "todo el grupo" sin aviso;
  - posible: en el MariaDB de XAMPP `evaluacion_intentos.iniciada_en` (primer `timestamp NOT NULL`) toma
    `ON UPDATE CURRENT_TIMESTAMP`. Hoy esa fila nunca se actualiza; revisarlo con `SHOW CREATE TABLE`.

### Corrección y reauditoría (27-11-2026)

ES1–ES16 se corrigieron el mismo día. Después, dos revisiones independientes (servidor y navegador) volvieron a revisar
todo el panel contra estos hallazgos y buscaron lo que los arreglos pudieran haber dañado. Encontraron 7 problemas nuevos
(N1–N7), que también quedaron corregidos. Pruebas: `tests/Feature/Estudiante/AuditoriaEstudianteTest.php` (15). Suite
completa: **405 OK**. Lo del navegador se comprobó con Playwright sobre una copia de la BD (`esat_auditoria`, ya borrada).

**Qué cambió (resumen):**
- **Evaluaciones:**
  - las correctas salen solo pasada la gracia (`Evaluacion::correctasVisibles`);
  - una evaluación cerrada no cambia su cierre (`Evaluacion::NO_REABRIR`, en la Form Request y en el servicio);
  - una comenzada o respondida no se elimina.
- **Reloj del Aula** (`aula.js`):
  - envío automático por evaluación, con reintentos dentro de la gracia;
  - las marcas solo se borran cuando el servidor confirma o rechaza de verdad (422/409/403/404);
  - se guardan en `localStorage` con el id del estudiante y por pregunta (dos pestañas se juntan);
  - `beforeunload` mientras corre el tiempo;
  - al agotarse la gracia sin conexión, queda "sin responder";
  - los textos ya no prometen un envío con la ventana cerrada.
- **Aula de consulta** (`AulaService::paraMatricula`):
  - incluye los grupos del historial;
  - `participa` (matrícula activa en su grupo actual), `grupo` (grupo anterior) y `leToco` (estuvo abierta en sus fechas en
    el grupo);
  - `aulaMotivo` explica por qué es solo consulta;
  - `estudioEn` mira el historial.
- **Inicio:** guarda de horario vacío y pendientes solo de lo que puede hacer.
- **Notas:** `no_cursado` con `Matricula::cruzaVentanas` (la regla del docente) en el panel y en Calificaciones.
- **Mis pagos:** `pagos.otras` (lo que debe en otras matrículas), con aviso en Mis pagos y pendiente en el Inicio.
- **Plan después de un reintegro:** `PagoService::tramosDeCuotas` / `fechasCuotas` / `primeraCuotaDesde` (el aviso de
  vencidas usa las mismas fechas).
- **Sesión:** el bloqueo por intentos fallidos solo frena ingresos nuevos (`EnsureAccountIsEnabled::motivo(..., enSesion)`).
- **Archivos:** el anterior se borra al confirmar (`DB::afterCommit`) y el nuevo al deshacer (`DB::afterRollBack`).
- **Escrituras:**
  - con límite (`throttle:estudiante`, 60/min, y `throttle:estudiante-publicar`, 10/min y 100/día; `AppServiceProvider`);
  - el 429 y los 409/500 tienen mensaje en español (`datos.js`).
- **Rendimiento:**
  - el foro solo viaja a su pantalla (`datos(..., conForo)`);
  - `ForoTema::primerMensaje` usa la relación cargada.
- **Bloqueos:**
  - estudiante → matrícula (orden de M13), con bloqueo compartido en la evaluación o la actividad;
  - la hora se toma al llegar;
  - el archivo se guarda antes de la transacción.

**Quedan abiertos** (después de corregir también los bajos, ver la sección siguiente):
- **De ES5, ES7 y ES15:**
  - los recibos de la otra matrícula;
  - guardar las marcas en el servidor: hoy, quien cierra la página antes de que se envíe queda sin responder, como se
    decidió;
  - paginar el foro y no mandar los comunicados a todas las pantallas.
- **Bordes de la reauditoría:**
  - aplazar y reintegrar el mismo día se toma como un solo tramo, y un reintegro el día 10 puede duplicar la cuota de ese
    mes (ya pasaba);
  - el ingreso con "Recordarme" de una cuenta bloqueada por intentos entra (la cookie se trata como la sesión abierta);
  - se puede volver a publicar una evaluación nueva con las mismas preguntas después de que se vieran las correctas.

### Corrección de los bajos ES17–ES29 (27-11-2026)

Corregidos el mismo día. Pruebas: 10 más en `AuditoriaEstudianteTest` (25 en total); suite completa **415 OK**; detector
de diseño en 0. En el navegador (Playwright sobre una copia de la BD, ya borrada): las 8 pantallas sin errores de JS y sin
texto de menos de 12 px, 375 px sin scroll horizontal, la hora del panel con el equipo en otra zona, el menú móvil, el
perfil, imprimir con Ctrl+P y "¿Olvidaste tu contraseña?". La migración nueva se probó en el MariaDB de XAMPP (en la copia).

- **ES17** La gracia del envío automático baja a 20 s (`Evaluacion::GRACIA_SEGUNDOS`; `aula.js` reintenta cada 3 s
  hasta 17 s): las correctas salen antes y el margen sigue cubriendo una red lenta.
- **ES18** Perfil (estudiante y docente):
  - cambiar el correo pide la contraseña actual (`ProfileUpdateRequest`, campo `clave_actual` que aparece al editar el
    correo);
  - lo que cambia va a la auditoría ("Actualizó su perfil: correo, celular.");
  - el celular del estudiante no puede ser el de su contacto de emergencia;
  - `/profile` escribe dentro de una transacción (`transaccion`).
- **ES19** "¿Olvidaste tu contraseña?" responde lo mismo exista o no el correo (salvo el aviso de esperar) y tiene
  `throttle:6,1`. Sigue sin enviar el correo (M27).
- **ES20** Endurecimiento:
  - `CabecerasDeSeguridad` (grupo `web`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` y
    `Referrer-Policy`;
  - `App\Support\Archivos::nombre()` limpia el nombre de lo que se sube (caracteres de control, barras) en el Aula, los
    comunicados y el foro;
  - `.env.example` dice qué poner en producción (`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`).
  - **Sin CSP todavía:** los paneles pasan sus datos con `<script>` en línea (`window.EST_SERVIDOR`, `DOC_SERVIDOR`…);
    una CSP útil necesita nonces en esas etiquetas. Queda para antes de desplegar (R7).
- **ES21** Foro:
  - `ForoService::responder` y `ocultar` bloquean el tema y vuelven a revisar si está cerrado u oculto (M13);
  - **decidido:** los temas, respuestas y reportes de estudiantes y docentes no van a la auditoría. El foro ya guarda
    autor, fecha y adjunto de cada uno, y lo que importa (ocultar, restaurar, fijar, cerrar, descartar reportes) sí queda
    en la auditoría.
- **ES22** Inicio y Mi carrera:
  - el avance de la carrera lo calcula el servidor (`PanelEstudianteService::avance`): clases dictadas de su grupo ÷
    clases del grupo, congelado el día en que se aplazó, retiró o graduó;
  - el calendario empieza en su primera clase (también la del grupo anterior);
  - "Docente por asignar" si el grupo no tiene;
  - el boletín dice si la matrícula no está activa.
- **ES23** Mis pagos:
  - "Próxima cuota" es la siguiente a la de este mes (cada cifra una vez);
  - el pie usa `totalCuotas` (B29);
  - sin plan, el estado de cuenta sale igual si debe el derecho;
  - el aviso de vencidas no cuenta cuotas de $ 0 (beca completa) y descuenta el saldo a favor;
  - una cuota de $ 0 dice "No se cobra";
  - el recibo anulado sin motivo ya no dice "null".
- **ES24** Aula, foro y comunicados:
  - responder, abrir tema y reportar en el foro no tocan el cajón si mientras tanto se cerró o se abrió otro tema;
  - al abrir un comunicado el foco sigue en la lista y el lector no se repinta;
  - el texto vacío depende de la pestaña;
  - comunicados, instrucciones y comentarios conservan los saltos de línea (`white-space: pre-line`);
  - dos respuestas en desorden: solo se aplica la más nueva (`datos.js`);
  - el enlace del material solo se pinta si es `http(s)`.
- **ES25** Accesibilidad e impresión:
  - el `<input type=file>` y la tarjeta del Aula ya no son paradas de tabulador (las hacen su etiqueta y su botón);
  - al pulsar Comenzar el foco va a la primera pregunta;
  - menú móvil y menú de usuario con `aria-expanded`, Escape y nada tabulable con el menú cerrado (`visibility`);
  - los errores del perfil están ligados a su campo (`aria-describedby`, `aria-invalid`);
  - ningún texto de pantalla baja de 12 px (0,75 rem) en el panel ni en el perfil del docente;
  - Ctrl+P sin un botón de imprimir imprime la página (`body.est-imprimiendo` solo lo pone el botón).
- **ES26** Fechas:
  - pagos y comunicados usan la hora de `datos($ahora)`;
  - el panel recibe la hora del servidor y su zona (`reloj`, `zona`): "En curso" y el cambio de día no dependen del
    reloj del equipo, y si la página pasa la medianoche abierta el Inicio se recarga;
  - el % de asistencia de las fichas (Estudiantes, Matrículas, panel general) cuenta la clase de hoy cuando termina, como
    Admin › Asistencia y el panel.
- **ES27** Código muerto:
  - se quitaron `PagoService::derechoVencido`, `PanelEstudianteService::institucion()`, los imports sin uso, el combobox,
    los colores por escuela y `.ini-linea-ok` del CSS del estudiante;
  - `var URL` del Inicio se llama `RUTA`;
  - `entregas.contenido` no se usa (la entrega es un archivo): queda escrito en el modelo y no se borra la columna;
  - el índice de M26 se deja (no estorba; quitarlo sería otra migración).
- **ES28** Lógica en su lugar:
  - `ComunicadoService::recibidosPorEstudiante` (antes en el panel) junto a `personas()`, las dos con
    `Matricula::VIGENTES`;
  - `ComunicadoService::marcarLeido` y `ForoService::reportar` (antes en los controladores del Estudiante y del Docente);
  - `ForoService::participa` (antes en el trait de `Http\Requests`);
  - el descuento de las opciones del plan usa `PagoService::descuentoDe` (el de `generarCuotas`);
  - **decidido:** los nombres de columnas distintos (`adjunto_ruta`/`adjunto_peso` contra `archivo_path`/`*_tamano`) no se
    renombran: son tablas con datos y el cambio no arregla nada.
- **ES29** BD (migración `2026_11_27_000006_restringir_borrado_del_aula`):
  - `matricula_grupos` (matrícula y grupo), `evaluacion_intentos.estudiante_id` y el `curso_id` de `materiales`,
    `actividades` y `evaluaciones` pasan de CASCADE a RESTRICT;
  - `evaluacion_intentos.iniciada_en` pasa a DATETIME (en MariaDB tomaba `ON UPDATE CURRENT_TIMESTAMP`; confirmado con
    `SHOW CREATE TABLE`);
  - `Programa::mallaEnUso` cuenta el material de clase.

### Pruebas que faltan (por riesgo, antes de la corrección; casi todas están ahora en `AuditoriaEstudianteTest`)
1. **Escrituras según el estado de la matrícula:** iniciar, responder, entregar y plan con matrícula aplazada,
   retirada, graduada, anulada o sin matrícula.
2. **Evaluación con tiempo:**
   - las correctas no salen durante la gracia (ES1) ni al reabrirla (ES2);
   - envío a los +40 s;
   - envío automático vacío;
   - iniciar ya cerrada o respondida;
   - opción de otra pregunta.
3. **Adjuntos del foro:** `GET foro/mensajes/{id}/adjunto` no tiene ninguna prueba (otra sede, oculto, archivo que falta).
4. **Entregas:**
   - de otro grupo;
   - de un aplazado;
   - de más de 10 MB;
   - el archivo viejo no se pierde si la petición falla (ES13).
5. **Comunicados (`leido` y `adjunto`):** otra sede, programado, archivado, borrador, `visible_hasta` vencido, cuenta
   inactiva.
6. **Foro y plan:**
   - foro: responder o reportar sin matrícula vigente; el aplazado sí participa;
   - plan: beca del 100 %; por periodo; después de un reintegro (ES11).
7. **Lo que muestra el panel:**
   - festivos y "No hubo clase" en el calendario del estudiante (`FestivosTest` solo prueba al docente);
   - dos matrículas en el tiempo;
   - deuda de una matrícula anterior (ES5).
8. **Rendimiento:** una prueba que limite el número de consultas de `datos()` (habría detectado ES15).

## Orden sugerido

1. ~~**C1–C5**, cada uno con su prueba.~~ Hecho y fusionado en `develop` (PR #42). **Falta pasarlo a `main`.**
2. Arreglos rápidos:
   - **A2**: una línea en `.gitignore`;
   - **M1, M2**: dos reglas CSS;
   - **M3**: copiar `alScroll`;
   - **M4**: el Escape de los modales;
   - **M5**: contar docentes y mover la auditoría;
   - **M19**: precios enteros;
   - **M23**: llevar al login ante 401/419.
3. Seguridad de cuentas: **M21** (mensaje genérico), **M20** (qué puede editar cada rol en su perfil), **A1** (cambio de clave obligatorio), **B21**.
4. Dinero: **A3, A4, A5, A6, A9**, más **M24, M25** y **B24–B27**. Las 20 reproducciones de finanzas sirven como base.
5. Grupos y horarios: **A7, A8, M22**.
6. Que un clon nuevo arranque: **A11** (seeder con superadmin, README y `.env.example`), **R5, R6**. Y **A12**: proteger las migraciones contra rollback.
7. Antes del panel Estudiante: **A10** (elegir plan) y **M26** (índice para `derechoVencido`).
8. Resto de medios, luego bajos, sitio público, limpieza del repo y **R7** antes de desplegar.
9. ~~Panel Estudiante (§11): ES1–ES29~~ corregidos el 27-11-2026 (ES1–ES16 reauditados). Queda lo pendiente que dice
   "Corrección y reauditoría" y la CSP (ES20).