#!/bin/sh
set -eu

php -r '
  $databaseUrl = getenv("DATABASE_URL");
  if (!$databaseUrl) {
      exit(1);
  }

  $parts = parse_url($databaseUrl);
  if ($parts === false || empty($parts["host"])) {
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
  } catch (Throwable) {
      exit(1);
  }
'
