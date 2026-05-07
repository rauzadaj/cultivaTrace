#!/bin/sh
set -e

APP_ENV="${APP_ENV:-prod}"
PORT="${PORT:-80}"
JWT_DIR="/var/www/html/var/jwt"
NGINX_TEMPLATE="/etc/nginx/templates/default.conf.template"
NGINX_CONF="/etc/nginx/conf.d/default.conf"
JWT_SECRET_KEY="${JWT_SECRET_KEY:-$JWT_DIR/private.pem}"
JWT_PUBLIC_KEY="${JWT_PUBLIC_KEY:-$JWT_DIR/public.pem}"

export JWT_SECRET_KEY
export JWT_PUBLIC_KEY

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

    if (empty($parts["host"])) {
        fwrite(STDERR, "DATABASE_URL is missing a host — cannot connect to the database.\n");
        exit(1);
    }

    $host = $parts["host"];
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

validate_required_env() {
  # Strict checks only in production — dev/test tolerate missing secrets
  # because docker-compose provides generated JWT keys and dev defaults.
  if [ "$APP_ENV" != "prod" ]; then
    return 0
  fi

  missing=""
  for var in APP_SECRET DATABASE_URL JWT_PASSPHRASE MERCURE_JWT_SECRET STRIPE_SECRET_KEY STRIPE_WEBHOOK_SECRET STRIPE_PRICE_STARTER STRIPE_PRICE_PRO STRIPE_PRICE_BUSINESS STRIPE_PRICE_ENTERPRISE; do
    eval "val=\${${var}:-}"
    if [ -z "$val" ]; then
      missing="${missing} ${var}"
    fi
  done

  # Warn — not fatal — on optional-but-expected production vars
  for var in MAILER_DSN FRONTEND_URL; do
    eval "val=\${${var}:-}"
    if [ -z "$val" ]; then
      echo "WARNING: ${var} is not set — some features will be unavailable." >&2
    fi
  done

  if [ -n "$missing" ]; then
    echo "ERROR: The following required environment variables are missing:${missing}" >&2
    echo "Set them via .env.local or your platform secrets before starting the application." >&2
    exit 1
  fi
}

validate_required_env

echo "Ensuring JWT key material..."
php /var/www/html/bin/generate-jwt-keys.php
chown www-data:www-data "$JWT_SECRET_KEY" "$JWT_PUBLIC_KEY" 2>/dev/null || true
chmod 640 "$JWT_SECRET_KEY" 2>/dev/null || true
chmod 644 "$JWT_PUBLIC_KEY" 2>/dev/null || true

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
  # Use DATABASE_URL_DIRECT if set — required for PgBouncer/Supavisor in transaction mode
  MIGRATE_URL="${DATABASE_URL_DIRECT:-$DATABASE_URL}"
  DATABASE_URL="$MIGRATE_URL" php bin/console doctrine:migrations:migrate --no-interaction --env="$APP_ENV" || {
    echo "[FATAL] Doctrine migrations failed. Refusing to start to prevent running on an incomplete schema." >&2
    exit 1
  }
fi

echo "Warming up cache..."
php bin/console cache:warmup --env="$APP_ENV"

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
exec nginx -g 'daemon off;'
