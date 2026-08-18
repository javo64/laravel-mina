#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR=/var/www/fabulosa
DB_NAME=fabulosa_app
DB_USER=fabulosa_app
DOMAIN=fabulosa.javaltecnologia.es
BACKUP_DIR="/root/backups/fabulosa-$(date +%Y%m%d-%H%M%S)"

if [[ -e /etc/nginx/sites-available/fabulosa || -e "$APP_DIR/.env" ]]; then
    echo "La aplicación ya parece instalada; se cancela para no sobrescribirla." >&2
    exit 1
fi

install -d -m 700 "$BACKUP_DIR"
cp -a /etc/nginx/sites-available "$BACKUP_DIR/nginx-sites-available"
cp -a /etc/nginx/sites-enabled "$BACKUP_DIR/nginx-sites-enabled"

if [[ ! -d "$APP_DIR/.git" ]]; then
    git clone https://github.com/javo64/laravel-mina.git "$APP_DIR"
fi
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

cp .env.example .env
DB_PASS="$(openssl rand -hex 24)"
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

sed -i \
    -e 's/^APP_ENV=.*/APP_ENV=production/' \
    -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
    -e "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" \
    -e 's/^LOG_LEVEL=.*/LOG_LEVEL=warning/' \
    -e "s/^DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" \
    -e "s/^DB_USERNAME=.*/DB_USERNAME=$DB_USER/" \
    -e "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" \
    .env
printf '\nAPP_TIMEZONE=America/Lima\nSESSION_SECURE_COOKIE=true\nSESSION_SAME_SITE=lax\n' >> .env

php artisan key:generate --force
php artisan migrate --force
mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /tmp/fabulosa-data.sql
php artisan storage:link
php deploy/prepare.php
php artisan optimize

chown -R root:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

install -m 0644 /tmp/fabulosa.nginx /etc/nginx/sites-available/fabulosa
ln -s /etc/nginx/sites-available/fabulosa /etc/nginx/sites-enabled/fabulosa
install -m 0644 /tmp/fabulosa-queue.service /etc/systemd/system/fabulosa-queue.service

nginx -t
systemctl reload nginx
systemctl daemon-reload
systemctl enable --now fabulosa-queue.service

rm -f /tmp/fabulosa-data.sql /tmp/fabulosa.nginx /tmp/fabulosa-queue.service

echo "Aplicación instalada en $APP_DIR"
echo "Respaldo de Nginx: $BACKUP_DIR"
