#!/bin/sh
set -e

APP_ENV="${APP_ENV:-prod}"
PORT="${PORT:-80}"
JWT_DIR="/var/www/html/config/jwt"
NGINX_TEMPLATE="/etc/nginx/templates/default.conf.template"
NGINX_CONF="/etc/nginx/conf.d/default.conf"

mkdir -p /var/www/html/var/cache /var/www/html/var/log "$JWT_DIR"
chown -R www-data:www-data /var/www/html/var
chmod -R ug+rwX /var/www/html/var

wait_for_database() {
  php -r '
    $databaseUrl = getenv("DATABASE_URL");
    if (!$databaseUrl) {
        fwrite(STDERR, "DATABASE_URL is not configured.\n");
        exit(1);
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        fwrite(STDERR, "DATABASE_URL is malformed.\n");
        exit(1);
    }

    $host = $parts["host"] ?? "db";
    $port = $parts["port"] ?? 5432;
    $database = isset($parts["path"]) ? ltrim($parts["path"], "/") : "";
    $user = $parts["user"] ?? "";
    $password = $parts["pass"] ?? "";

    try {
        new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", $host, $port, $database), $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
  '
}

if [ -n "${JWT_SECRET_KEY_BASE64:-}" ]; then
  echo "$JWT_SECRET_KEY_BASE64" | base64 -d > "$JWT_DIR/private.pem"
  chmod 600 "$JWT_DIR/private.pem"
fi

if [ -n "${JWT_PUBLIC_KEY_BASE64:-}" ]; then
  echo "$JWT_PUBLIC_KEY_BASE64" | base64 -d > "$JWT_DIR/public.pem"
  chmod 644 "$JWT_DIR/public.pem"
fi

if [ -f "$NGINX_TEMPLATE" ]; then
  export PORT
  export FRONTEND_URL
  envsubst '${PORT} ${FRONTEND_URL}' < "$NGINX_TEMPLATE" > "$NGINX_CONF"
fi

if [ -n "${DATABASE_URL:-}" ]; then
  attempts=0
  until wait_for_database; do
    attempts=$((attempts + 1))

    if [ "$attempts" -ge 30 ]; then
      echo "Database did not become ready after ${attempts} attempts." >&2
      exit 1
    fi

    sleep 2
  done

  echo "Running migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --env="$APP_ENV"
fi

echo "Warming up cache..."
php bin/console cache:warmup --env="$APP_ENV"

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
exec nginx -g 'daemon off;'
