# Sistema en la nube Mina — instalación local

## Requisitos

- PHP 8.3 o superior con extensiones `pdo_mysql`, `mbstring`, `openssl` y `fileinfo`.
- MySQL 8 o superior.
- Composer 2 (recomendado para actualizar dependencias).

## Instalación

1. Descomprimir el proyecto en una carpeta local.
2. Crear en MySQL una base de datos vacía llamada `mina_cloud` con codificación `utf8mb4`.
3. Copiar `.env.example` como `.env` y completar `DB_USERNAME` y `DB_PASSWORD`.
4. Desde la carpeta del proyecto ejecutar:

   ```bash
   composer install
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve --host=127.0.0.1 --port=8000
   ```

5. Abrir `http://127.0.0.1:8000`.

## Acceso inicial

- Correo: `admin@mina.local`
- Contraseña: `Admin2026`

El ZIP no contiene el archivo `.env` ni credenciales de la base de datos de la máquina original.
