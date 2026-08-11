# Instalación del Sistema Mina en un servidor

## 1. Preparar el servidor

Instale PHP 8.3 o superior, MySQL/MariaDB, Composer 2 y las extensiones indicadas en `README.md`. Descomprima el proyecto, por ejemplo, en `/var/www/sistema-mina`.

El `DocumentRoot` del sitio debe ser `/var/www/sistema-mina/public`. Nunca publique la raíz completa del proyecto porque contiene configuración privada.

Hay ejemplos para [Nginx](deploy/nginx.conf.example) y [Apache](deploy/apache-vhost.conf.example).

## 2. Instalar dependencias

Desde la raíz del proyecto:

```bash
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
php deploy/prepare.php
```

Los archivos frontend compilados ya están incluidos en `public/build`, por lo que Node.js no es obligatorio en el servidor.

## 3. Configurar MySQL

Cree una base de datos y un usuario con permisos sobre ella. Luego complete en `.env`:

```dotenv
APP_URL=https://sistema.su-dominio.com
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mina_cloud
DB_USERNAME=mina_app
DB_PASSWORD=una_clave_segura
```

No reutilice el `.env` de otra máquina ni lo coloque dentro de `public`.

### Importar la base incluida en el paquete

El paquete completo contiene `database/mina_cloud.sql`. Puede importarlo desde HeidiSQL seleccionando la base `mina_cloud` y usando **Archivo > Ejecutar archivo SQL**.

Si importa este respaldo, no necesita ejecutar `php artisan migrate --seed --force`. Después de importar ejecute solamente `php artisan migrate --force` para comprobar si existen migraciones posteriores.

## 4. Inicializar Laravel

```bash
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan optimize
php deploy/check.php
```

El comando `php deploy/prepare.php` crea, aunque el descompresor hubiera omitido carpetas vacías:

- `storage/framework/views`
- `storage/framework/sessions`
- `storage/framework/cache/data`
- `storage/logs`
- `bootstrap/cache`

`config/view.php` ya forma parte del proyecto y apunta las vistas compiladas a `storage/framework/views`.

## 5. Permisos en Linux

El usuario de PHP-FPM o Apache debe poder escribir en `storage` y `bootstrap/cache`. En Ubuntu/Debian normalmente:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

No aplique permisos `777`.1
## 6. Tareas programadas y colas

Agregue al cron del servidor:

```cron
* * * * * cd /var/www/sistema-mina && php artisan schedule:run >> /dev/null 2>&1
```

Si más adelante se usan colas en segundo plano, configure Supervisor para ejecutar `php artisan queue:work`.

## 7. Acceso inicial

- Correo: `admin@mina.local`
- Contraseña temporal: `Admin2026`

Cambie la contraseña después del primer ingreso.

## Actualizaciones futuras

Antes de actualizar haga respaldo de la base de datos y del archivo `.env`. Después de reemplazar los archivos ejecute:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php deploy/check.php
```
