#!/usr/bin/env bash
set -euo pipefail

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASS:?DB_PASS is required}"

BACKUP_DIR="${BACKUP_DIR:-./storage/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date '+%Y%m%d_%H%M%S')"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

export MYSQL_PWD="$DB_PASS"
mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USER" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --set-gtid-purged=OFF \
  "$DB_NAME" | gzip -9 > "$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql.gz"
unset MYSQL_PWD

find "$BACKUP_DIR" -type f -name '*.sql.gz' -mtime "+$RETENTION_DAYS" -delete

echo "Database backup created: $BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql.gz"
