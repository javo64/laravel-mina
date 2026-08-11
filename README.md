# Sistema en la nube Mina

Aplicación Laravel para administrar productos y servicios, requerimientos, aprobaciones y usuarios.

## Requisitos del servidor

- PHP 8.3 o superior.
- MySQL 8 o MariaDB 10.6 o superior.
- Composer 2.
- Extensiones PHP: `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `pdo_mysql` y `tokenizer`.
- Apache con `mod_rewrite` o Nginx con PHP-FPM.
- El dominio debe apuntar exclusivamente a la carpeta `public`.

## Instalación rápida

```bash
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
php deploy/prepare.php
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan optimize
php deploy/check.php
```

Antes de ejecutar las migraciones, configure en `.env` el dominio y las credenciales de MySQL.

La guía completa está en [INSTALACION_SERVIDOR.md](INSTALACION_SERVIDOR.md).

## Acceso inicial

- Correo: `admin@mina.local`
- Contraseña temporal: `Admin2026`

Cambie esta contraseña después del primer ingreso.
