# ESAT · Plataforma académica y financiera

Sitio público y plataforma por roles de la **Escuela Nacional de Educación ESAT** (Lic. de Funcionamiento 5850,
NIT 900820764-1): Admin de toda ESAT, Secretaría de cada sede, Docente, Estudiante y Acudiente.

Laravel 12 (Blade) · PHP 8.2+ · MySQL / MariaDB. Toda la interfaz está en español.

## Requisitos

- PHP 8.2 o superior con las extensiones `pdo_mysql`, `mbstring`, `openssl` y `fileinfo`.
- Composer 2.
- Node.js 18 o superior y npm (solo para compilar los estilos de las pantallas de ingreso).
- MySQL 8 o MariaDB 10.4 o superior.

## Instalación nueva

1. Clona el repositorio y crea la base de datos vacía:

   ```sql
   CREATE DATABASE esat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Copia `.env.example` a `.env` y completa:
   - la conexión (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`);
   - las dos cuentas iniciales: `SUPERADMIN_*` (quien instala y administra el sistema) y `ADMIN_GENERAL_*` (el Admin de
     toda ESAT). Correo y número de documento son obligatorios.

3. Instala, migra y compila:

   ```bash
   composer setup
   ```

   (Hace `composer install`, `key:generate`, `migrate`, `npm install` y `npm run build`.)

4. Crea las 4 sedes reales y las dos cuentas:

   ```bash
   php artisan db:seed
   ```

   La **clave inicial** de cada cuenta es su número de documento. Se puede volver a correr sin duplicar nada.

5. Arranca y entra:

   ```bash
   php artisan serve
   ```

   Con la cuenta del Admin se crean las secretarías de cada sede, el catálogo de programas, los docentes y los
   estudiantes. No hay datos de prueba: todo se crea desde los paneles.

## Pruebas

```bash
php artisan test
```

Usan SQLite en memoria y no tocan la base de datos real. Necesitan la extensión `pdo_sqlite` (si está desactivada:
`php -d extension=pdo_sqlite vendor/bin/phpunit`).

## Puesta en producción (hosting)

Lista de verificación del `.env` antes de abrirlo al público (el `.env.example` trae valores para trabajar en local):

| Variable | En producción | Por qué |
|---|---|---|
| `APP_ENV` | `production` | Con este valor el sistema **no permite** `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni `db:wipe`. |
| `APP_DEBUG` | `false` | Con `true`, un error muestra código, consultas y variables del servidor a cualquiera. |
| `APP_URL` | `https://` + el dominio | Enlaces de los correos y de los documentos. |
| `APP_KEY` | una propia (`php artisan key:generate`) | Nunca la del equipo de desarrollo. |
| `SESSION_ENCRYPT` | `true` | Las sesiones se guardan en la base de datos; sin cifrar quedan legibles en un respaldo. |
| `SESSION_SECURE_COOKIE` | `true` | La cookie de sesión solo viaja por HTTPS. |
| `LOG_STACK` | `daily` | Un archivo de registro por día, en vez de uno que crece sin límite. |
| `LOG_LEVEL` | `warning` | `debug` llena el registro con detalles internos. |
| `MAIL_*` | el SMTP real | Sin correo, "¿Olvidaste tu contraseña?" no envía nada. |
| `DB_PASSWORD` | una clave real | En XAMPP `root` no tiene clave; en el servidor sí debe tener. |

- Sirve el sitio solo por HTTPS.
- Programa el planificador cada minuto para que los cobros pasen a vencidos (`pagos:vencer`):

  ```
  * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
  ```

- Para actualizar: `git pull`, `composer install --no-dev`, `php artisan migrate --force` y `npm run build`.

## Respaldos: nunca deshacer migraciones

**No uses `migrate:rollback` para volver atrás.** Con datos, deshacer migraciones borra recibos y cobros o deja la base a
medias, y el sistema lo impide mientras haya matrículas, cobros o recibos. Para volver atrás, restaura un respaldo:

```bash
mysqldump -u USUARIO -p esat > respaldo.sql      # sacar un respaldo
mysql -u USUARIO -p esat < respaldo.sql          # restaurarlo
```

Al insertar datos por SQL en `matriculas`, nombra las columnas en el `INSERT`: `vigente_de` es una columna calculada.

Los volcados de la base (como `database/esat.sql`) tienen datos personales, claves y sesiones: **no se suben a Git**.

## Documentación interna

- `CLAUDE.md`: reglas de negocio y de cada panel.
- `HALLAZGOS.md`: revisiones y auditorías, con lo corregido y lo pendiente.
- `DESIGN.md`: sistema visual del sitio público.
