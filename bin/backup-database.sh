#!/bin/sh
# CultivaTrace — PostgreSQL backup script
#
# Usage:
#   bin/backup-database.sh                        # dump to var/backups/
#   S3_BUCKET=s3://my-bucket bin/backup-database.sh   # dump + upload to S3
#
# Required env: DATABASE_URL
# Optional env: S3_BUCKET, BACKUP_RETENTION_DAYS (default: 30)
#
# Designed to run as a daily cron:
#   0 3 * * * /var/www/html/bin/backup-database.sh >> /var/log/backup.log 2>&1

set -e

BACKUP_DIR="${BACKUP_DIR:-/var/www/html/var/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/cultivatrace_${TIMESTAMP}.pgdump"

# ── Parse DATABASE_URL ─────────────────────────────────────────────────────

if [ -z "${DATABASE_URL:-}" ]; then
  echo "ERROR: DATABASE_URL is not set." >&2
  exit 1
fi

# Use DATABASE_URL_DIRECT if available (bypasses PgBouncer for direct connection)
DB_URL="${DATABASE_URL_DIRECT:-$DATABASE_URL}"

parse_db_url() {
  php -r '
    $url = getenv("DB_URL");
    $parts = parse_url($url);
    echo "PGHOST=" . ($parts["host"] ?? "") . "\n";
    echo "PGPORT=" . ($parts["port"] ?? 5432) . "\n";
    echo "PGDATABASE=" . ltrim($parts["path"] ?? "", "/") . "\n";
    echo "PGUSER=" . urldecode($parts["user"] ?? "") . "\n";
    echo "PGPASSWORD=" . urldecode($parts["pass"] ?? "") . "\n";
  '
}

eval "$(DB_URL="$DB_URL" parse_db_url)"
export PGHOST PGPORT PGDATABASE PGUSER PGPASSWORD

# ── Create backup ──────────────────────────────────────────────────────────

mkdir -p "$BACKUP_DIR"

echo "[$(date -Iseconds)] Starting backup → ${BACKUP_FILE}"
pg_dump \
  --format=custom \
  --compress=9 \
  --no-password \
  --file="$BACKUP_FILE"

SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)
echo "[$(date -Iseconds)] Backup complete — size: ${SIZE}"

# ── Upload to S3 (optional) ────────────────────────────────────────────────

if [ -n "${S3_BUCKET:-}" ]; then
  S3_KEY="${S3_BUCKET}/$(basename "$BACKUP_FILE")"
  echo "[$(date -Iseconds)] Uploading to ${S3_KEY}..."
  aws s3 cp "$BACKUP_FILE" "$S3_KEY"
  echo "[$(date -Iseconds)] Upload complete."

  # Remove local file after successful upload
  rm -f "$BACKUP_FILE"
fi

# ── Purge old local backups ────────────────────────────────────────────────

if [ -d "$BACKUP_DIR" ]; then
  find "$BACKUP_DIR" -name "cultivatrace_*.pgdump" -mtime "+${RETENTION_DAYS}" -delete
  echo "[$(date -Iseconds)] Purged backups older than ${RETENTION_DAYS} days."
fi

echo "[$(date -Iseconds)] Done."
