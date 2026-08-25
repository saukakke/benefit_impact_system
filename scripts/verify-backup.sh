#!/usr/bin/env bash
set -euo pipefail

: "${BACKUP_FILE:?BACKUP_FILE is required}"

if [[ ! -s "$BACKUP_FILE" ]]; then
  echo "Backup file is missing or empty: $BACKUP_FILE" >&2
  exit 1
fi

gzip -t "$BACKUP_FILE"
printf 'Backup archive integrity check passed: %s\n' "$BACKUP_FILE"
