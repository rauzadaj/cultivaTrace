#!/bin/sh
set -eu

mkdir -p /var/www/html/var/cache /var/www/html/var/log
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

bootstrap_app() {
  if [ "${APP_AUTO_BOOTSTRAP:-0}" != "1" ]; then
    return
  fi

  if [ "${APP_ENV:-dev}" != "dev" ] && [ "${APP_ENV:-dev}" != "test" ]; then
    echo "Skipping automatic bootstrap outside dev/test."
    return
  fi

  echo "Waiting for PostgreSQL..."
  attempts=0
  until wait_for_database; do
    attempts=$((attempts + 1))

    if [ "$attempts" -ge 30 ]; then
      echo "Database did not become ready after ${attempts} attempts." >&2
      exit 1
    fi

    sleep 2
  done

  echo "Applying Doctrine migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

  if [ "${APP_BOOTSTRAP_SEED_DEMO:-0}" = "1" ]; then
    echo "Seeding demo dataset..."
    php bin/console app:seed-demo-data --no-interaction
  fi
}

if [ "${1:-}" = "php-fpm" ]; then
  bootstrap_app
fi

exec docker-php-entrypoint "$@"
